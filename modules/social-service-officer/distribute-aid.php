<?php
declare(strict_types=1);requireRole('social-service-officer');require_once __DIR__.'/../../config/database.php';require_once __DIR__.'/../../includes/activity.php';require_once __DIR__.'/../../includes/eligibility.php';require_once __DIR__.'/../../includes/beneficiary-division-guard.php';$activePage='distribute-aid';$errors=[];$success=(string)($_SESSION['flash_success']??'');unset($_SESSION['flash_success']);$db=database();$userId=(int)$_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = (string) ($_POST['distribution_type'] ?? '');
    $requestId = filter_input(INPUT_POST, 'aid_request_id', FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
    $notes = trim((string) ($_POST['notes'] ?? ''));
    if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) $errors[] = 'Your session expired.';
    if ($type !== 'request-based') $errors[] = 'Only Admin may distribute aid without an approved request.';
    if (!$requestId || !$quantity || $quantity < 1) $errors[] = 'Select a valid approved request and quantity.';
    if (mb_strlen($notes) > 1000) $errors[] = 'Notes cannot exceed 1000 characters.';

    if ($errors === []) {
        try {
            $db->beginTransaction();
            $statement = $db->prepare(
                "SELECT ar.id, ar.beneficiary_id, ar.item_id, ar.quantity, i.can_sso_distribute
                 FROM aid_requests ar
                 JOIN beneficiaries b ON b.id = ar.beneficiary_id
                 JOIN inventory_items i ON i.id = ar.item_id
                 WHERE ar.id = :id AND ar.status = 'approved' AND b.status = 'active'
                   AND b.ds_division_id = (SELECT ds_division_id FROM users WHERE id = :user)
                   AND NOT EXISTS (SELECT 1 FROM distributions d WHERE d.aid_request_id = ar.id)
                   AND NOT EXISTS (SELECT 1 FROM goods_fulfillments f WHERE f.aid_request_id = ar.id)
                   AND NOT EXISTS (SELECT 1 FROM admin_direct_releases direct_release WHERE direct_release.aid_request_id = ar.id)
                   AND NOT EXISTS (SELECT 1 FROM goods_requests goods WHERE goods.aid_request_id = ar.id AND goods.status <> 'rejected')
                   AND NOT EXISTS (
                       SELECT 1 FROM goods_request_aid_requests link
                       JOIN goods_requests g ON g.id = link.goods_request_id
                       WHERE link.aid_request_id = ar.id AND g.status <> 'rejected'
                   )
                 FOR UPDATE"
            );
            $statement->execute(['id' => $requestId, 'user' => $userId]);
            $request = $statement->fetch();
            if (!$request) throw new RuntimeException('Approved request is unavailable, outside your division, or already assigned to another delivery.');
            if ((int) $request['can_sso_distribute'] !== 1) throw new RuntimeException(t('This aid must be distributed by the Subject Officer.'));
            $beneficiaryId = (int) $request['beneficiary_id'];
            assertBeneficiaryRecordDivision($db, $beneficiaryId);
            $itemId = (int) $request['item_id'];
            if ($quantity !== (int) $request['quantity']) throw new RuntimeException('Distribution quantity must match the approved request.');
            $eligibility = beneficiaryEligibility($db, $beneficiaryId, $itemId, (int) $requestId);
            if (!$eligibility['eligible']) throw new RuntimeException($eligibility['reason']);

            $pool = $db->prepare('SELECT p.allocated, p.distributed, p.reused FROM division_pools p JOIN users u ON u.ds_division_id = p.ds_division_id WHERE u.id = :officer AND u.status = "active" AND p.item_id = :item FOR UPDATE');
            $pool->execute(['officer' => $userId, 'item' => $itemId]);
            $balance = $pool->fetch();
            $available = $balance ? (int) $balance['allocated'] - (int) $balance['distributed'] + (int) $balance['reused'] : 0;
            if ($available < $quantity) throw new RuntimeException("Insufficient pool quota. Available: $available.");
            $statement = $db->prepare('UPDATE division_pools SET distributed = distributed + :quantity WHERE ds_division_id = (SELECT ds_division_id FROM users WHERE id = :officer AND status = "active") AND item_id = :item AND (allocated - distributed + reused) >= :required');
            $statement->execute(['quantity' => $quantity, 'officer' => $userId, 'item' => $itemId, 'required' => $quantity]);
            if ($statement->rowCount() !== 1) throw new RuntimeException('The pool balance changed. Refresh and try again.');
            $statement = $db->prepare("INSERT INTO distributions (aid_request_id, beneficiary_id, ds_division_id, item_id, quantity, distribution_type, notes, distributed_by) VALUES (:request, :beneficiary, (SELECT ds_division_id FROM users WHERE id = :division_user), :item, :quantity, 'request-based', :notes, :officer)");
            $statement->execute(['request' => $requestId, 'beneficiary' => $beneficiaryId, 'division_user' => $userId, 'item' => $itemId, 'quantity' => $quantity, 'notes' => $notes ?: null, 'officer' => $userId]);
            $distributionId = (int) $db->lastInsertId();
            $statement = $db->prepare("UPDATE aid_requests SET status = 'distributed' WHERE id = :id AND status = 'approved'");
            $statement->execute(['id' => $requestId]);
            if ($statement->rowCount() !== 1) throw new RuntimeException('The approved request changed. Refresh and try again.');
            $db->commit();
            logActivity('Distribution', 'Issued approved aid from officer pool', 'DIST-' . str_pad((string) $distributionId, 4, '0', STR_PAD_LEFT), 'request-based');
            $_SESSION['flash_success'] = 'Distribution recorded and pool quota updated.';
            unset($_SESSION['csrf_token']);
            header('Location: dashboard.php?page=distribute-aid');
            exit;
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            error_log($exception->getMessage());
            $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to complete distribution.';
        }
    }
}
try {
    $approved=$db->prepare(
        "SELECT ar.id,ar.quantity,b.full_name,b.nic,b.elders_card_number,i.item_name,i.variety,
                ds.name division_name,d.name district_name,
                submitter.username submitter_username,submitter.role submitter_role,
                (p.allocated-p.distributed+p.reused) available
         FROM aid_requests ar
         JOIN beneficiaries b ON b.id=ar.beneficiary_id
         JOIN inventory_items i ON i.id=ar.item_id
         JOIN users officer ON officer.id=:officer_id AND officer.role='social-service-officer' AND officer.status='active'
         JOIN division_pools p ON p.item_id=ar.item_id AND p.ds_division_id=officer.ds_division_id
         JOIN ds_divisions ds ON ds.id=b.ds_division_id
         JOIN districts d ON d.id=ds.district_id
         JOIN users submitter ON submitter.id=ar.submitted_by
         WHERE ar.status='approved' AND b.status='active' AND i.can_sso_distribute=1
           AND b.ds_division_id=officer.ds_division_id
           AND (p.allocated-p.distributed+p.reused)>=ar.quantity
           AND NOT EXISTS (SELECT 1 FROM distributions d WHERE d.aid_request_id=ar.id)
           AND NOT EXISTS (SELECT 1 FROM goods_fulfillments f WHERE f.aid_request_id=ar.id)
           AND NOT EXISTS (SELECT 1 FROM admin_direct_releases direct_release WHERE direct_release.aid_request_id=ar.id)
           AND NOT EXISTS (SELECT 1 FROM goods_requests goods WHERE goods.aid_request_id=ar.id AND goods.status<>'rejected')
           AND NOT EXISTS (
               SELECT 1 FROM goods_request_aid_requests link
               JOIN goods_requests g ON g.id=link.goods_request_id
               WHERE link.aid_request_id=ar.id AND g.status<>'rejected'
           )
         ORDER BY ar.reviewed_at"
    );
    $approved->execute(['officer_id'=>$userId]);
    $approved=$approved->fetchAll();
} catch(PDOException $e) {
    error_log($e->getMessage());
    $approved=[];
    $errors[]='Distribution workflow is unavailable.';
}
foreach ($approved as &$row) $row['item_name'] = widmsAidItemName((string)$row['item_name']);
unset($row);
$retryRequestId=filter_input(INPUT_POST,'aid_request_id',FILTER_VALIDATE_INT)?:0;
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Distribute Items | SWPCS</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="assets/css/admin-dashboard.css?v=<?=filemtime(__DIR__.'/../../public/assets/css/admin-dashboard.css')?>" rel="stylesheet"><link href="assets/css/approved-aid-bundles.css?v=<?=filemtime(__DIR__.'/../../public/assets/css/approved-aid-bundles.css')?>" rel="stylesheet"></head><body class="distribution-page-body"><?php require __DIR__.'/../../includes/social-service-officer-sidebar.php';?><div class="admin-shell"><header class="topbar"><div class="d-flex align-items-center gap-3"><button class="menu-button" id="menu-button">&#9776;</button><h1>Distribute Items</h1></div></header><main class="dashboard-content distribution-page"><?php if($success):?><div class="alert alert-success"><?=htmlspecialchars($success,ENT_QUOTES,'UTF-8')?></div><?php endif;?><?php if($errors):?><div class="alert alert-danger"><?=htmlspecialchars(implode(' ',$errors),ENT_QUOTES,'UTF-8')?></div><?php endif;?>
<section class="admin-data-card optical-request-card sso-distribution-section" id="request-panel">
    <div class="admin-data-header"><div><h2><?=htmlspecialchars(t('Approved aid ready to distribute'),ENT_QUOTES,'UTF-8')?></h2><p><?=htmlspecialchars(t('Admin-approved beneficiary requests with enough stock in your DS Division pool.'),ENT_QUOTES,'UTF-8')?></p></div><div class="sso-distribution-header-actions"><span class="fulfillment-count"><?=count($approved)?> <?=htmlspecialchars(t('available'),ENT_QUOTES,'UTF-8')?></span><a class="outline-action" href="dashboard.php?page=sso-distribution-history"><?=htmlspecialchars(t('Distribution History'),ENT_QUOTES,'UTF-8')?></a></div></div>
    <?php if($approved===[]):?><div class="aid-bundle-empty"><?=htmlspecialchars(t('No approved requests can currently be fulfilled from your pool.'),ENT_QUOTES,'UTF-8')?></div><?php else:?>
    <div class="aid-bundle-grid sso-distribution-grid">
        <?php foreach($approved as $request):$requestId=(int)$request['id'];$requestNumber='AR-'.str_pad((string)$requestId,4,'0',STR_PAD_LEFT);?>
        <article id="aid-request-<?=$requestId?>" class="aid-bundle-beneficiary-card sso-distribution-card admin-notification-target" tabindex="-1">
            <div class="aid-bundle-top"><span class="aid-bundle-ref"><?=htmlspecialchars($requestNumber,ENT_QUOTES,'UTF-8')?></span><span class="sso-ready-badge"><?=htmlspecialchars(t('Ready from your pool'),ENT_QUOTES,'UTF-8')?></span></div>
            <strong class="aid-bundle-name"><?=htmlspecialchars((string)$request['full_name'],ENT_QUOTES,'UTF-8')?></strong>
            <span class="aid-bundle-id"><?=htmlspecialchars($request['nic']?'NIC: '.$request['nic']:($request['elders_card_number']?'Elder Card: '.$request['elders_card_number']:t('No identification recorded')),ENT_QUOTES,'UTF-8')?></span>
            <div class="aid-stock-comparison"><div class="aid-stock-metric"><span><?=htmlspecialchars(t('Approved aid'),ENT_QUOTES,'UTF-8')?></span><strong><?=htmlspecialchars((string)$request['item_name'].((string)$request['variety']!==''?' — '.$request['variety']:''),ENT_QUOTES,'UTF-8')?></strong><small><?=number_format((int)$request['quantity'])?> <?=htmlspecialchars(t('units'),ENT_QUOTES,'UTF-8')?></small></div><div class="aid-stock-metric aid-stock-matched"><span><?=htmlspecialchars(t('My Pool Quota'),ENT_QUOTES,'UTF-8')?></span><strong><?=number_format((int)$request['available'])?> <?=htmlspecialchars(t('available'),ENT_QUOTES,'UTF-8')?></strong><small><?=htmlspecialchars(t('Enough stock'),ENT_QUOTES,'UTF-8')?></small></div></div>
            <span class="aid-bundle-location"><?=htmlspecialchars($request['district_name'].' / '.$request['division_name'],ENT_QUOTES,'UTF-8')?></span>
            <span class="aid-bundle-source subject-actor-label"><span><?=htmlspecialchars(t('Submitted By'),ENT_QUOTES,'UTF-8')?></span><strong><?=htmlspecialchars((string)$request['submitter_username'],ENT_QUOTES,'UTF-8')?></strong><small><?=htmlspecialchars(t(ucwords(str_replace('-',' ',(string)$request['submitter_role']))),ENT_QUOTES,'UTF-8')?></small></span>
            <form method="post" class="sso-distribute-form"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrfToken(),ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="distribution_type" value="request-based"><input type="hidden" name="aid_request_id" value="<?=$requestId?>"><input type="hidden" name="quantity" value="<?=(int)$request['quantity']?>"><label><?=htmlspecialchars(t('Notes'),ENT_QUOTES,'UTF-8')?> <small>(<?=htmlspecialchars(t('optional'),ENT_QUOTES,'UTF-8')?>)</small><textarea name="notes" rows="2" maxlength="1000"><?= $retryRequestId===$requestId?htmlspecialchars((string)($_POST['notes']??''),ENT_QUOTES,'UTF-8'):'' ?></textarea></label><button type="submit" class="admin-primary-action"><?=htmlspecialchars(t('Distribute'),ENT_QUOTES,'UTF-8')?></button></form>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
</main></div><script src="assets/js/admin-dashboard.js"></script></body></html>
