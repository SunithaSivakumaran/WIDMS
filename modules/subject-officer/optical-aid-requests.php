<?php
declare(strict_types=1);

requireRole('subject-officer');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/activity.php';
require_once __DIR__ . '/../../includes/optical-stock.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_once __DIR__ . '/../../includes/ui-messages.php';
require_once __DIR__ . '/../../includes/aid-stock-comparison.php';
require_once __DIR__ . '/../../includes/approved-aid-bundle-availability.php';
require_once __DIR__ . '/../../includes/goods-request-actor.php';

$activePage = 'approved-aid-bundles';
$database = database();
$userId = (int) $_SESSION['user_id'];
$errors = [];
$success = (string) ($_SESSION['flash_success'] ?? '');
$justification = trim((string) ($_POST['justification'] ?? ''));
$highlightRequestId = filter_input(INPUT_GET, 'request_id', FILTER_VALIDATE_INT) ?: 0;
unset($_SESSION['flash_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedIds = array_values(array_unique(array_filter(array_map(
        static fn(mixed $value): int => (int) filter_var($value, FILTER_VALIDATE_INT),
        is_array($_POST['request_ids'] ?? null) ? $_POST['request_ids'] : []
    ))));

    if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = t('Your session expired. Please refresh the page and try again.');
    }
    if ($selectedIds === [] || count($selectedIds) > 50) {
        $errors[] = t('Select between 1 and 50 approved aid requests.');
    }
    if (mb_strlen($justification) < 10 || mb_strlen($justification) > 1000) {
        $errors[] = t('Provide a justification between 10 and 1000 characters.');
    }

    if ($errors === []) {
        sort($selectedIds, SORT_NUMERIC);
        try {
            $database->beginTransaction();
            do {
                $batchReference = 'GRB-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(8)));
                $statement = $database->prepare('SELECT 1 FROM goods_requests WHERE request_batch_ref = :reference LIMIT 1');
                $statement->execute(['reference' => $batchReference]);
            } while ($statement->fetchColumn());

            $requestStatement = $database->prepare(
                "SELECT ar.id, ar.item_id, ar.quantity, ar.spectacle_category_id,
                        b.ds_division_id, i.item_name, i.variety, c.name AS category_name
                 FROM aid_requests ar
                 JOIN beneficiaries b ON b.id = ar.beneficiary_id
                 JOIN inventory_items i ON i.id = ar.item_id
                 JOIN item_categories c ON c.id = i.category_id
                 JOIN users submitter ON submitter.id = ar.submitted_by
                 WHERE ar.id = :id
                   AND submitter.role IN ('subject-officer', 'social-service-officer', 'admin')
                   AND ar.status = 'approved'
                   AND NOT EXISTS (SELECT 1 FROM distributions d WHERE d.aid_request_id = ar.id)
                   AND NOT EXISTS (SELECT 1 FROM goods_fulfillments f WHERE f.aid_request_id = ar.id)
                   AND NOT EXISTS (SELECT 1 FROM admin_direct_releases direct_release WHERE direct_release.aid_request_id = ar.id)
                   AND NOT EXISTS (SELECT 1 FROM goods_requests goods WHERE goods.aid_request_id = ar.id AND goods.status <> 'rejected')
                   AND NOT EXISTS (
                       SELECT 1
                       FROM goods_request_aid_requests link
                       JOIN goods_requests goods ON goods.id = link.goods_request_id
                       WHERE link.aid_request_id = ar.id AND goods.status <> 'rejected'
                   )
                   AND NOT EXISTS (
                       SELECT 1 FROM users sso
                       JOIN division_pools pool ON pool.ds_division_id = sso.ds_division_id AND pool.item_id = ar.item_id
                       WHERE sso.role = 'social-service-officer' AND sso.status = 'active'
                         AND sso.ds_division_id = b.ds_division_id AND b.status = 'active'
                         AND i.can_sso_distribute = 1
                         AND (pool.allocated - pool.distributed + pool.reused) >= ar.quantity
                   )
                 FOR UPDATE"
            );
            $insertGoods = $database->prepare(
                "INSERT INTO goods_requests
                    (aid_request_id, request_batch_ref, item_id, quantity,
                     destination_ds_division_id, destination_sso_id, justification, requested_by)
                 VALUES
                    (:aid_request_id, :batch_reference, :item_id, :quantity,
                     :division_id, NULL, :justification, :requested_by)"
            );
            $insertLink = $database->prepare(
                'INSERT INTO goods_request_aid_requests (goods_request_id, aid_request_id)
                 VALUES (:goods_request_id, :aid_request_id)'
            );

            $selectedRequests = [];
            $itemTotals = [];
            $spectacleTotals = [];

            foreach ($selectedIds as $requestId) {
                $requestStatement->execute(['id' => $requestId]);
                $request = $requestStatement->fetch();
                if (!$request) throw new RuntimeException(t('One selected approved aid request is no longer available.'));
                $itemId=(int)$request['item_id'];
                $itemTotals[$itemId]=($itemTotals[$itemId]??0)+(int)$request['quantity'];
                if (widmsIsSpectacleItem((string) $request['item_name'])) {
                    $spectacleCategoryId = (int) ($request['spectacle_category_id'] ?? 0);
                    if ($spectacleCategoryId < 1) {
                        throw new RuntimeException(t('A spectacle request is missing its type.'));
                    }
                    $spectacleTotals[$itemId][$spectacleCategoryId] =
                        ($spectacleTotals[$itemId][$spectacleCategoryId] ?? 0) + (int) $request['quantity'];
                }
                $selectedRequests[]=$request;
            }

            ksort($itemTotals,SORT_NUMERIC);
            $stockStatement=$database->prepare('SELECT quantity FROM inventory_items WHERE id=? FOR UPDATE');
            $reservedStatement=$database->prepare("SELECT COALESCE(SUM(quantity),0) FROM goods_requests WHERE item_id=? AND status IN ('pending-admin-approval','approved-awaiting-dispatch')");
            foreach ($itemTotals as $itemId=>$needed) {
                $stockStatement->execute([$itemId]);
                $stock=$stockStatement->fetchColumn();
                $reservedStatement->execute([$itemId]);
                $available=$stock===false?0:max(0,(int)$stock-(int)$reservedStatement->fetchColumn());
                if ($available<$needed) throw new RuntimeException(sprintf(t('Only %d units of %s are available in Central Stock.'),$available,widmsAidItemName((string)$selectedRequests[0]['item_name'])));
                if (isset($spectacleTotals[$itemId])) {
                    $categoryBalances = widmsSpectacleCategoryBalances($database, $itemId);
                    foreach ($spectacleTotals[$itemId] as $categoryId => $categoryNeeded) {
                        if (($categoryBalances[$categoryId] ?? 0) < $categoryNeeded) {
                            throw new RuntimeException(t('Not enough Central Stock in the selected spectacle type.'));
                        }
                    }
                }
            }

            $firstGoodsRequestId=0;
            foreach ($selectedRequests as $request) {
                $insertGoods->execute([
                    'aid_request_id' => (int) $request['id'],
                    'batch_reference' => $batchReference,
                    'item_id' => (int) $request['item_id'],
                    'quantity' => (int) $request['quantity'],
                    'division_id' => (int) $request['ds_division_id'],
                    'justification' => $justification,
                    'requested_by' => $userId,
                ]);
                $goodsRequestId=(int)$database->lastInsertId();
                if ($firstGoodsRequestId===0) $firstGoodsRequestId=$goodsRequestId;
                $insertLink->execute([
                    'goods_request_id' => $goodsRequestId,
                    'aid_request_id' => (int) $request['id'],
                ]);
            }

            $admins=$database->query("SELECT id FROM users WHERE role='admin' AND status='active'")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($admins as $adminId) notifyUser(
                $database,(int)$adminId,'aid-bundle-'.strtolower($batchReference),
                'Approved aid bundle request',$batchReference.' needs approval',
                count($selectedRequests).' approved beneficiary requests are ready for stock review.',
                'dashboard.php?page=goods-requests#goods-request-'.$firstGoodsRequestId
            );

            $database->commit();
            logActivity(
                'Approved Aid Bundles',
                'Submitted ' . count($selectedIds) . ' approved aid request(s) for Admin approval',
                $batchReference,
                'pending'
            );
            $_SESSION['flash_success'] = 'Approved aid bundle submitted for Admin approval.';
            unset($_SESSION['csrf_token']);
            header('Location: dashboard.php?page=my-beneficiary-requests#' . rawurlencode(strtolower($batchReference)));
            exit;
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            error_log($exception->getMessage());
            $errors[] = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : t('Unable to submit the approved aid bundle.');
        }
    }
}

$availableRequests = [];
$waitingRequests = [];
$unavailableCount = 0;
try {
    $bundleAvailability = widmsApprovedAidBundleAvailability($database);
    $availableRequests = $bundleAvailability['available'];
    $waitingRequests = $bundleAvailability['waiting'];
    $unavailableCount = count($waitingRequests);
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $errors[] = t('Approved aid requests are temporarily unavailable.');
}
$readyBundleCount = count($availableRequests);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars(t('Approved Aid Bundles'), ENT_QUOTES, 'UTF-8') ?> | SWPCS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css?v=92" rel="stylesheet">
    <link href="assets/css/approved-aid-bundles.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/approved-aid-bundles.css') ?>" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/../../includes/subject-officer-sidebar.php'; ?>
<div class="admin-shell">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="menu-button" id="menu-button" type="button" aria-label="<?= htmlspecialchars(t('Open navigation'), ENT_QUOTES, 'UTF-8') ?>">&#9776;</button>
            <div><h1><?= htmlspecialchars(t('Approved Aid Bundles'), ENT_QUOTES, 'UTF-8') ?></h1></div>
        </div>
    </header>
    <main class="dashboard-content optical-request-page widms-unified-ui">
        <?php renderSuccessMessage($success); ?>
        <?php if ($errors): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars(implode(' ', $errors), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

        <section class="admin-data-card optical-request-card">
            <div class="admin-data-header">
                <div><h2><?= htmlspecialchars(t('Ready for a bundle'), ENT_QUOTES, 'UTF-8') ?></h2><p><?= htmlspecialchars(t('Choose approved beneficiaries with matching Central Stock, then send one bundle to Admin.'), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars(t('Select one or more approved requests.'), ENT_QUOTES, 'UTF-8') ?></p></div>
                <span class="fulfillment-count"><?= count($availableRequests) ?> <?= htmlspecialchars(t('available'), ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <?php if ($unavailableCount > 0): ?>
                <div class="optical-stock-note"><?= htmlspecialchars(sprintf(t('%d approved request(s) are waiting for enough Central Stock or the correct spectacle type.'), $unavailableCount), ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="post" class="optical-request-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <?php if ($availableRequests === [] && $waitingRequests === []): ?><div class="aid-bundle-empty"><?= htmlspecialchars(t('No approved requests currently have enough matching Central Stock.'), ENT_QUOTES, 'UTF-8') ?></div><?php else: ?>
                <div class="aid-bundle-grid">
                    <?php foreach (array_merge($availableRequests, $waitingRequests) as $request): $requestId=(int)$request['id']; $ready = $request['central_available'] >= (int)$request['quantity'] && (!$request['is_spectacles'] || ($request['spectacle_available']??0) >= (int)$request['quantity']); ?>
                    <label id="aid-request-<?= $requestId ?>" class="aid-bundle-beneficiary-card admin-notification-target <?= $ready ? '' : 'aid-bundle-waiting' ?>">
                        <span class="aid-bundle-top"><span class="aid-bundle-ref">AR-<?= str_pad((string)$requestId,4,'0',STR_PAD_LEFT) ?></span><?php if ($ready): ?><input type="checkbox" name="request_ids[]" value="<?= $requestId ?>" aria-label="<?= htmlspecialchars(t('Select request').' AR-'.str_pad((string)$requestId,4,'0',STR_PAD_LEFT),ENT_QUOTES,'UTF-8') ?>" <?= $highlightRequestId===$requestId || in_array($requestId,array_map('intval',is_array($_POST['request_ids']??null)?$_POST['request_ids']:[]),true)?'checked':'' ?>><?php else: ?><span class="aid-bundle-waiting-badge"><?= htmlspecialchars(t('Waiting for stock'),ENT_QUOTES,'UTF-8') ?></span><?php endif; ?></span>
                        <strong class="aid-bundle-name"><?= htmlspecialchars((string)$request['beneficiary_name'],ENT_QUOTES,'UTF-8') ?></strong>
                        <?php if ($request['is_spectacles']): ?><span class="aid-bundle-id"><?= htmlspecialchars(t('Spectacle Type') . ': ' . t((string) ($request['spectacle_category_name'] ?? t('Not selected'))), ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                        <span class="aid-bundle-id"><?= htmlspecialchars($request['nic']?'NIC: '.$request['nic']:($request['elders_card_number']?'Elder Card: '.$request['elders_card_number']:t('No identification recorded')),ENT_QUOTES,'UTF-8') ?></span>
                        <?php renderAidStockComparison(widmsAidItemName((string)$request['item_name']).((string)$request['variety']!==''?' — '.$request['variety']:''), (int)$request['quantity'], (int)$request['central_available']); ?>
                        <?php if ($request['is_spectacles'] && (int) $request['spectacle_available'] < (int) $request['quantity']): ?>
                            <span class="fulfillment-warning"><?= htmlspecialchars(t('Selected spectacle type is short in Central Stock.'), ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                        <span class="aid-bundle-location"><?= htmlspecialchars($request['district_name'].' / '.$request['division_name'],ENT_QUOTES,'UTF-8') ?></span>
                        <span class="aid-bundle-source subject-actor-label"><span><?= htmlspecialchars(t('Submitted By'), ENT_QUOTES, 'UTF-8') ?></span><strong><?= htmlspecialchars((string) $request['submitter_username'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars(t(widmsGoodsRequestRoleLabel((string) $request['submitter_role'])), ENT_QUOTES, 'UTF-8') ?></small></span>
                    </label>
                    <?php endforeach; ?>
                </div><?php endif; ?>

                <?php if ($availableRequests): ?>
                    <div class="optical-request-submit">
                        <label>
                            <span><?= htmlspecialchars(t('Request Justification'), ENT_QUOTES, 'UTF-8') ?> *</span>
                            <textarea name="justification" minlength="10" maxlength="1000" required placeholder="<?= htmlspecialchars(t('Explain why these approved items should be released to you for beneficiary distribution.'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($justification, ENT_QUOTES, 'UTF-8') ?></textarea>
                        </label>
                        <button class="admin-primary-action" type="submit"><?= htmlspecialchars(t('Submit Selected for Admin Approval'), ENT_QUOTES, 'UTF-8') ?> &rarr;</button>
                    </div>
                <?php endif; ?>
            </form>
        </section>
    </main>
</div>
<script src="assets/js/admin-dashboard.js"></script>
</body>
</html>
