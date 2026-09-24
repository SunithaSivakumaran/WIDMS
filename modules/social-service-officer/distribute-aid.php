<?php
declare(strict_types=1);requireRole('social-service-officer');require_once __DIR__.'/../../config/database.php';require_once __DIR__.'/../../includes/activity.php';require_once __DIR__.'/../../includes/eligibility.php';$activePage='distribute-aid';$errors=[];$success=(string)($_SESSION['flash_success']??'');unset($_SESSION['flash_success']);$db=database();$userId=(int)$_SESSION['user_id'];
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
                "SELECT ar.id, ar.beneficiary_id, ar.item_id, ar.quantity
                 FROM aid_requests ar
                 JOIN beneficiaries b ON b.id = ar.beneficiary_id
                 WHERE ar.id = :id AND ar.status = 'approved' AND b.status = 'active'
                   AND b.ds_division_id = (SELECT ds_division_id FROM users WHERE id = :user)
                   AND NOT EXISTS (SELECT 1 FROM distributions d WHERE d.aid_request_id = ar.id)
                   AND NOT EXISTS (SELECT 1 FROM goods_fulfillments f WHERE f.aid_request_id = ar.id)
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
            $beneficiaryId = (int) $request['beneficiary_id'];
            $itemId = (int) $request['item_id'];
            if ($quantity !== (int) $request['quantity']) throw new RuntimeException('Distribution quantity must match the approved request.');
            $eligibility = beneficiaryEligibility($db, $beneficiaryId, $itemId, (int) $requestId);
            if (!$eligibility['eligible']) throw new RuntimeException($eligibility['reason']);

            $pool = $db->prepare('SELECT allocated, distributed, reused FROM officer_pools WHERE officer_id = :officer AND item_id = :item FOR UPDATE');
            $pool->execute(['officer' => $userId, 'item' => $itemId]);
            $balance = $pool->fetch();
            $available = $balance ? (int) $balance['allocated'] - (int) $balance['distributed'] + (int) $balance['reused'] : 0;
            if ($available < $quantity) throw new RuntimeException("Insufficient pool quota. Available: $available.");
            $statement = $db->prepare('UPDATE officer_pools SET distributed = distributed + :quantity WHERE officer_id = :officer AND item_id = :item AND (allocated - distributed + reused) >= :required');
            $statement->execute(['quantity' => $quantity, 'officer' => $userId, 'item' => $itemId, 'required' => $quantity]);
            if ($statement->rowCount() !== 1) throw new RuntimeException('The pool balance changed. Refresh and try again.');
            $statement = $db->prepare("INSERT INTO distributions (aid_request_id, beneficiary_id, item_id, quantity, distribution_type, notes, distributed_by) VALUES (:request, :beneficiary, :item, :quantity, 'request-based', :notes, :officer)");
            $statement->execute(['request' => $requestId, 'beneficiary' => $beneficiaryId, 'item' => $itemId, 'quantity' => $quantity, 'notes' => $notes ?: null, 'officer' => $userId]);
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
        "SELECT ar.id,ar.quantity,b.full_name,b.nic,i.item_name,i.variety,
                (p.allocated-p.distributed+p.reused) available
         FROM aid_requests ar
         JOIN beneficiaries b ON b.id=ar.beneficiary_id
         JOIN inventory_items i ON i.id=ar.item_id
         JOIN officer_pools p ON p.item_id=ar.item_id AND p.officer_id=:pool_user
         WHERE ar.status='approved' AND b.status='active'
           AND b.ds_division_id=(SELECT ds_division_id FROM users WHERE id=:division_user)
           AND (p.allocated-p.distributed+p.reused)>=ar.quantity
           AND NOT EXISTS (SELECT 1 FROM distributions d WHERE d.aid_request_id=ar.id)
           AND NOT EXISTS (SELECT 1 FROM goods_fulfillments f WHERE f.aid_request_id=ar.id)
           AND NOT EXISTS (
               SELECT 1 FROM goods_request_aid_requests link
               JOIN goods_requests g ON g.id=link.goods_request_id
               WHERE link.aid_request_id=ar.id AND g.status<>'rejected'
           )
         ORDER BY ar.reviewed_at"
    );
    $approved->execute(['pool_user'=>$userId,'division_user'=>$userId]);
    $approved=$approved->fetchAll();
    $today=$db->prepare('SELECT d.*,b.full_name,i.item_name,i.variety FROM distributions d JOIN beneficiaries b ON b.id=d.beneficiary_id JOIN inventory_items i ON i.id=d.item_id WHERE d.distributed_by=:user AND DATE(d.distributed_at)=CURDATE() ORDER BY d.id DESC');
    $today->execute(['user'=>$userId]);
    $today=$today->fetchAll();
} catch(PDOException $e) {
    error_log($e->getMessage());
    $approved=$today=[];
    $errors[]='Distribution workflow is unavailable.';
}
$selectedRequestId=filter_input(INPUT_GET,'request_id',FILTER_VALIDATE_INT)?:0;
$selectedRequestQuantity=0;
foreach($approved as $approvedRequest){if((int)$approvedRequest['id']===$selectedRequestId){$selectedRequestQuantity=(int)$approvedRequest['quantity'];break;}}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Distribute Items | WIDMS</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="assets/css/admin-dashboard.css" rel="stylesheet"></head><body class="distribution-page-body"><?php require __DIR__.'/../../includes/social-service-officer-sidebar.php';?><div class="admin-shell"><header class="topbar"><div class="d-flex align-items-center gap-3"><button class="menu-button" id="menu-button">&#9776;</button><h1>Distribute Items</h1></div></header><main class="dashboard-content distribution-page"><?php if($success):?><div class="alert alert-success"><?=htmlspecialchars($success,ENT_QUOTES,'UTF-8')?></div><?php endif;?><?php if($errors):?><div class="alert alert-danger"><?=htmlspecialchars(implode(' ',$errors),ENT_QUOTES,'UTF-8')?></div><?php endif;?>
<section class="distribution-panel active" id="request-panel"><article class="distribution-card"><div class="distribution-card-header"><h2>Approved-Request Distribution</h2><small>Admin-approved requests available in your pool</small></div><form method="post" class="distribution-form"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrfToken(),ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="distribution_type" value="request-based"><div class="distribution-notice success"><strong>Ready from your pool</strong><span>Only approved requests with sufficient pool stock are listed.</span></div><div class="distribution-grid two-columns"><label>Approved Request *<select name="aid_request_id" id="approved-aid-request" onchange="document.getElementById('approved-aid-quantity').value=this.selectedOptions[0]?.dataset.quantity||''" required><option value="">Select approved request</option><?php foreach($approved as $r):?><option value="<?=(int)$r['id']?>" data-quantity="<?=(int)$r['quantity']?>" data-available="<?=(int)$r['available']?>" <?=$selectedRequestId===(int)$r['id']?'selected':''?>>AR-<?=str_pad((string)$r['id'],4,'0',STR_PAD_LEFT)?> — <?=htmlspecialchars($r['full_name'].' — '.$r['item_name'].' × '.$r['quantity'].' ('.$r['available'].' in pool)',ENT_QUOTES,'UTF-8')?></option><?php endforeach;?></select><?php if(!$approved):?><small>No approved requests can currently be fulfilled from your pool.</small><?php endif;?></label><label>Approved Quantity *<input type="number" name="quantity" id="approved-aid-quantity" min="1" value="<?=$selectedRequestQuantity?:''?>" readonly required></label></div><label class="distribution-full-field">Notes<textarea name="notes" rows="2" maxlength="1000"></textarea></label><div class="distribution-actions"><button class="distribution-primary-button" <?=$approved?'':'disabled'?>>Confirm Distribution</button></div></form></article></section>
<section class="distribution-history-card"><div class="distribution-history-header"><h2>Today's Distributions</h2></div><div class="distribution-table-wrap"><table class="distribution-table"><thead><tr><th>Beneficiary</th><th>Item</th><th>Type</th><th>Qty</th><th>Source</th><th>Time</th><th>Ref</th></tr></thead><tbody><?php if(!$today):?><tr><td colspan="7" class="distribution-empty">No distributions recorded today.</td></tr><?php else:foreach($today as $r):?><tr><td><?=htmlspecialchars($r['full_name'],ENT_QUOTES,'UTF-8')?></td><td><?=htmlspecialchars($r['item_name'].($r['variety']?' — '.$r['variety']:''),ENT_QUOTES,'UTF-8')?></td><td><?=ucwords(str_replace('-',' ',$r['distribution_type']))?></td><td><?=(int)$r['quantity']?></td><td>My Pool Quota</td><td><?=date('h:i A',strtotime($r['distributed_at']))?></td><td>DIST-<?=str_pad((string)$r['id'],4,'0',STR_PAD_LEFT)?></td></tr><?php endforeach;endif;?></tbody></table></div></section></main></div><script src="assets/js/admin-dashboard.js"></script></body></html>
