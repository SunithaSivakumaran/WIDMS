<?php
declare(strict_types=1);

requireRole('store-keeper');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/activity.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_once __DIR__ . '/../../includes/optical-stock.php';
require_once __DIR__ . '/../../includes/spectacle-categories.php';
require_once __DIR__ . '/../../includes/store-dispatch-request-card.php';
require_once __DIR__ . '/../../includes/aid-stock-comparison.php';

$activePage = 'approved-dispatches';
$database = database();
$errors = [];
$success = (string) ($_SESSION['flash_success'] ?? '');
unset($_SESSION['flash_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_direct_release_id'])) {
    $releaseId = filter_input(INPUT_POST, 'admin_direct_release_id', FILTER_VALIDATE_INT);
    if (!$releaseId || !verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Invalid direct aid release or expired session.';
    } else {
        try {
            $database->beginTransaction();
            $statement = $database->prepare(
                "SELECT adr.id, adr.admin_id, ar.id AS aid_request_id, ar.item_id,
                        ar.quantity, ar.spectacle_category_id, i.item_name, i.variety,
                        c.name AS category_name
                 FROM admin_direct_releases adr
                 JOIN aid_requests ar ON ar.id = adr.aid_request_id
                 JOIN inventory_items i ON i.id = ar.item_id
                 JOIN item_categories c ON c.id = i.category_id
                 JOIN users admin_user ON admin_user.id = adr.admin_id
                 WHERE adr.id = :id AND adr.status = 'awaiting-store-keeper'
                   AND ar.status = 'approved' AND admin_user.role = 'admin'
                   AND admin_user.status = 'active'
                 FOR UPDATE"
            );
            $statement->execute(['id' => $releaseId]);
            $release = $statement->fetch();
            if (!$release) {
                throw new RuntimeException('This Admin direct aid request is no longer ready for release.');
            }
            $stock = $database->prepare('SELECT quantity FROM inventory_items WHERE id = :id FOR UPDATE');
            $stock->execute(['id' => $release['item_id']]);
            $quantityInStock = (int) $stock->fetchColumn();
            $reservedStatement = $database->prepare(
                "SELECT COALESCE(SUM(quantity), 0) FROM goods_requests
                 WHERE item_id = :item AND status = 'approved-awaiting-dispatch'"
            );
            $reservedStatement->execute(['item' => $release['item_id']]);
            $unreserved = $quantityInStock - (int) $reservedStatement->fetchColumn();
            if ($unreserved < (int) $release['quantity']) {
                throw new RuntimeException('Insufficient unreserved Central Stock for this direct aid release.');
            }
            if (widmsIsSpectacleItem((string) $release['item_name'])) {
                $categoryId = (int) ($release['spectacle_category_id'] ?? 0);
                $categoryBalances = widmsSpectacleCategoryBalances($database, (int) $release['item_id']);
                if ($categoryId < 1 || ($categoryBalances[$categoryId] ?? 0) < (int) $release['quantity']) {
                    throw new RuntimeException('The selected spectacle type is not available for this direct aid release.');
                }
            }
            $updateStock = $database->prepare(
                'UPDATE inventory_items SET quantity = quantity - :quantity WHERE id = :item AND quantity >= :minimum'
            );
            $updateStock->execute(['quantity' => $release['quantity'], 'item' => $release['item_id'], 'minimum' => $release['quantity']]);
            if ($updateStock->rowCount() !== 1) {
                throw new RuntimeException('Central Stock changed. Refresh and try again.');
            }
            $updateRelease = $database->prepare(
                "UPDATE admin_direct_releases SET status = 'released-to-admin', released_by = :keeper,
                        released_at = NOW() WHERE id = :id AND status = 'awaiting-store-keeper'"
            );
            $updateRelease->execute(['keeper' => (int) $_SESSION['user_id'], 'id' => $releaseId]);
            if ($updateRelease->rowCount() !== 1) {
                throw new RuntimeException('The release changed. Refresh and try again.');
            }
            notifyUser(
                $database, (int) $release['admin_id'], 'admin-direct-ready-' . $release['aid_request_id'],
                'Direct aid released', 'AR-' . str_pad((string) $release['aid_request_id'], 4, '0', STR_PAD_LEFT),
                'The Store Keeper released this item to you. Record beneficiary distribution after handover.',
                'dashboard.php?page=direct-aid-release-queue#aid-request-' . $release['aid_request_id']
            );
            $database->commit();
            logActivity('Dispatch', 'Released direct aid to Administrator', 'AR-' . $release['aid_request_id'], 'released');
            $_SESSION['flash_success'] = 'Aid released to the Administrator.';
            unset($_SESSION['csrf_token']);
            header('Location: dashboard.php?page=approved-dispatches');
            exit;
        } catch (Throwable $exception) {
            if ($database->inTransaction()) $database->rollBack();
            error_log($exception->getMessage());
            $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to release direct aid.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['admin_direct_release_id'])) {
    $requestId = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
    $batchReference = trim((string) ($_POST['batch_reference'] ?? ''));
    if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Your session expired. Please refresh the page and try again.';
    }
    if (($batchReference !== '' && strlen($batchReference) > 100)
        || (!$requestId && $batchReference === '')) {
        $errors[] = 'Select an approved stock quota request.';
    }

    if (!$errors) {
        try {
            $database->beginTransaction();
            if ($batchReference !== '') {
                $requestStatement = $database->prepare(
                    "SELECT g.*,
                            (SELECT COUNT(*) FROM goods_request_aid_requests gar WHERE gar.goods_request_id = g.id) AS linked_needs
                     FROM goods_requests g
                     WHERE g.request_batch_ref = :batch AND g.status = 'approved-awaiting-dispatch'
                     ORDER BY g.id FOR UPDATE"
                );
                $requestStatement->execute(['batch' => $batchReference]);
            } else {
                $requestStatement = $database->prepare(
                    "SELECT g.*,
                            (SELECT COUNT(*) FROM goods_request_aid_requests gar WHERE gar.goods_request_id = g.id) AS linked_needs
                     FROM goods_requests g
                     WHERE g.id = :id AND g.status = 'approved-awaiting-dispatch'
                     FOR UPDATE"
                );
                $requestStatement->execute(['id' => $requestId]);
            }
            $requests = $requestStatement->fetchAll();
            if (!$requests) {
                throw new RuntimeException('This request is not approved for release.');
            }

            foreach ($requests as $request) {
            $requestId = (int) $request['id'];

            $stock = $database->prepare('SELECT quantity FROM inventory_items WHERE id = :item FOR UPDATE');
            $stock->execute(['item' => $request['item_id']]);
            $available = (int) $stock->fetchColumn();
            if ($available < (int) $request['quantity']) {
                throw new RuntimeException('Insufficient central stock. Available: ' . $available . '.');
            }

            $linkedPowerStatement = $database->prepare(
                "SELECT ar.item_id, ar.spectacle_category_id,
                        i.item_name, i.variety, c.name AS category_name
                 FROM goods_request_aid_requests link
                 JOIN aid_requests ar ON ar.id = link.aid_request_id
                 JOIN inventory_items i ON i.id = ar.item_id
                 JOIN item_categories c ON c.id = i.category_id
                 WHERE link.goods_request_id = :goods_request_id"
            );
            $linkedPowerStatement->execute(['goods_request_id' => $requestId]);
            foreach ($linkedPowerStatement->fetchAll() as $linkedRequest) {
                if (widmsIsSpectacleItem((string) $linkedRequest['item_name'])) {
                    $categoryId = (int) ($linkedRequest['spectacle_category_id'] ?? 0);
                    $categoryBalances = widmsSpectacleCategoryBalances(
                        $database,
                        (int) $linkedRequest['item_id']
                    );
                    if ($categoryId < 1 || ($categoryBalances[$categoryId] ?? -1) < 0) {
                        throw new RuntimeException('The selected spectacle type is no longer available for this dispatch.');
                    }
                    continue;
                }
            }

            $subject = $database->prepare(
                "SELECT id, full_name FROM users
                 WHERE id = :id AND role = 'subject-officer' AND status = 'active'
                 FOR UPDATE"
            );
            $subject->execute(['id' => $request['requested_by']]);
            $requestingSubject = $subject->fetch();
            if (!$requestingSubject) {
                throw new RuntimeException('The Subject Officer who submitted this request is no longer active.');
            }

            $database->prepare('UPDATE inventory_items SET quantity = quantity - :quantity WHERE id = :item')
                ->execute(['quantity' => $request['quantity'], 'item' => $request['item_id']]);

            $database->prepare(
                "UPDATE goods_requests
                 SET status = 'dispatched', dispatched_by = :keeper,
                     dispatched_at = NOW(), released_to_subject_id = :subject, received_at = NOW()
                 WHERE id = :id"
            )->execute([
                'keeper' => (int) $_SESSION['user_id'],
                'subject' => (int) $requestingSubject['id'],
                'id' => $requestId,
            ]);

            $releasedFulfillmentId = 0;
            if ((int) $request['linked_needs'] > 0) {
                $needs = $database->prepare(
                    "SELECT ar.id,
                            LOWER(CONCAT(i.item_name, ' ', i.variety, ' ', c.name)) AS item_text
                     FROM goods_request_aid_requests gar
                     JOIN aid_requests ar ON ar.id = gar.aid_request_id
                     JOIN inventory_items i ON i.id = ar.item_id
                     JOIN item_categories c ON c.id = i.category_id
                     WHERE gar.goods_request_id = :goods"
                );
                $needs->execute(['goods' => $requestId]);
                $insert = $database->prepare(
                    'INSERT INTO goods_fulfillments
                        (goods_request_id, aid_request_id, subject_officer_id, lens_unit_identifier)
                     VALUES (:goods, :aid, :subject, :unit)'
                );
                $sequence = 0;
                foreach ($needs->fetchAll() as $need) {
                    $sequence++;
                    $isLens = str_contains($need['item_text'], 'contact') && str_contains($need['item_text'], 'lens');
                    $isSpectacles = str_contains($need['item_text'], 'spectacle')
                        || str_contains($need['item_text'], 'glasses')
                        || str_contains($need['item_text'], 'specs');
                    $unitPrefix = $isLens ? 'CL' : ($isSpectacles ? 'SP' : '');
                    $unit = $unitPrefix !== ''
                        ? $unitPrefix . '-GR' . str_pad((string) $requestId, 4, '0', STR_PAD_LEFT) . '-' . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT)
                        : null;
                    $insert->execute([
                        'goods' => $requestId,
                        'aid' => $need['id'],
                        'subject' => (int) $requestingSubject['id'],
                        'unit' => $unit,
                    ]);
                    if ($releasedFulfillmentId === 0) {
                        $releasedFulfillmentId = (int) $database->lastInsertId();
                    }
                }
            }

            $notificationTarget = $releasedFulfillmentId > 0
                ? 'dashboard.php?page=distribute-items#fulfillment-' . $releasedFulfillmentId
                : 'dashboard.php?page=my-goods-requests#goods-request-' . $requestId;

            notifyUser(
                $database,
                (int) $requestingSubject['id'],
                'goods-dispatched-' . $requestId,
                'Stock quota request update',
                'GR-' . str_pad((string) $requestId, 4, '0', STR_PAD_LEFT) . ' released to you',
                'The Store Keeper released the approved stock quota to you.',
                $notificationTarget
            );
            }
            $message = count($requests) === 1
                ? 'Stock quota released to the Subject Officer who submitted the request.'
                : 'Stock quota batch released to the Subject Officer who submitted the requests.';

            $database->commit();
            logActivity(
                'Dispatch',
                $message,
                $batchReference !== '' ? $batchReference : 'GR-' . str_pad((string) $requestId, 4, '0', STR_PAD_LEFT),
                'dispatched'
            );
            $_SESSION['flash_success'] = $message;
            unset($_SESSION['csrf_token']);
            header('Location: dashboard.php?page=recent-dispatches#goods-request-' . (int) $requests[0]['id']);
            exit;
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            error_log($exception->getMessage());
            $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to release the stock quota.';
        }
    }
}

try {
    $adminDirectRows = $database->query(
        "SELECT adr.id, ar.id AS aid_request_id, ar.item_id, ar.quantity, ar.spectacle_category_id,
                i.item_name, i.variety, i.quantity AS central_stock, c.name AS category_name,
                spectacle_type.name AS spectacle_category_name,
                b.full_name AS beneficiary_name, ds.name AS division_name,
                admin_user.full_name AS admin_name
         FROM admin_direct_releases adr
         JOIN aid_requests ar ON ar.id = adr.aid_request_id
         JOIN inventory_items i ON i.id = ar.item_id
         JOIN item_categories c ON c.id = i.category_id
         LEFT JOIN spectacle_categories spectacle_type ON spectacle_type.id = ar.spectacle_category_id
         JOIN beneficiaries b ON b.id = ar.beneficiary_id
         JOIN ds_divisions ds ON ds.id = b.ds_division_id
         JOIN users admin_user ON admin_user.id = adr.admin_id
         WHERE adr.status = 'awaiting-store-keeper' AND ar.status = 'approved'
         ORDER BY adr.id"
    )->fetchAll();
    $rows = $database->query(
        "SELECT g.*, i.item_name, i.variety, i.quantity AS central_stock,
                ds.name AS division_name, d.name AS district_name,
                requester.full_name AS requester_name,
                approver.full_name AS approver_name,
                target.full_name AS sso_name,
                (SELECT b.full_name FROM goods_request_aid_requests link JOIN aid_requests ar ON ar.id=link.aid_request_id JOIN beneficiaries b ON b.id=ar.beneficiary_id WHERE link.goods_request_id=g.id LIMIT 1) AS beneficiary_name,
                (SELECT COALESCE(NULLIF(b.nic,''),NULLIF(b.elders_card_number,'')) FROM goods_request_aid_requests link JOIN aid_requests ar ON ar.id=link.aid_request_id JOIN beneficiaries b ON b.id=ar.beneficiary_id WHERE link.goods_request_id=g.id LIMIT 1) AS beneficiary_identification,
                COUNT(gar.aid_request_id) AS linked_needs
         FROM goods_requests g
         JOIN inventory_items i ON i.id = g.item_id
         JOIN ds_divisions ds ON ds.id = g.destination_ds_division_id
         JOIN districts d ON d.id = ds.district_id
         JOIN users requester ON requester.id = g.requested_by
         JOIN users approver ON approver.id = g.approved_by
         LEFT JOIN users target ON target.id = g.destination_sso_id
         LEFT JOIN goods_request_aid_requests gar ON gar.goods_request_id = g.id
         WHERE g.status = 'approved-awaiting-dispatch'
         GROUP BY g.id
         ORDER BY g.approved_at, g.id"
    )->fetchAll();
    $reservedByItem = [];
    $reservedStatement = $database->prepare("SELECT COALESCE(SUM(quantity),0) FROM goods_requests WHERE item_id = :item_id AND status = 'approved-awaiting-dispatch'");
    $powerBalancesByItem = [];
    foreach ($adminDirectRows as &$directRow) {
        $itemId = (int) $directRow['item_id'];
        if (!isset($reservedByItem[$itemId])) {
            $reservedStatement->execute(['item_id' => $itemId]);
            $reservedByItem[$itemId] = (int) $reservedStatement->fetchColumn();
        }
        $directRow['central_available'] = max(0, (int) $directRow['central_stock'] - $reservedByItem[$itemId]);
        $directRow['optical'] = widmsIsOpticalItem((string) $directRow['item_name'], (string) $directRow['variety'], (string) $directRow['category_name']);
        $directRow['spectacles'] = widmsIsSpectacleItem((string) $directRow['item_name']);
        $directRow['spectacle_available'] = $directRow['spectacles']
            ? (widmsSpectacleCategoryBalances($database, $itemId)[(int) $directRow['spectacle_category_id']] ?? 0)
            : null;
    }
    unset($directRow);
    $linkedStatement = $database->prepare(
        'SELECT ar.quantity, ar.spectacle_category_id,
                spectacle_type.name AS spectacle_category_name, i.item_name, i.variety, c.name AS category_name,
                b.full_name AS beneficiary_name,
                COALESCE(NULLIF(b.nic,\'\'), NULLIF(b.elders_card_number,\'\')) AS identification
         FROM goods_request_aid_requests link
         JOIN aid_requests ar ON ar.id = link.aid_request_id
         JOIN inventory_items i ON i.id = ar.item_id
         JOIN item_categories c ON c.id = i.category_id
         LEFT JOIN spectacle_categories spectacle_type ON spectacle_type.id = ar.spectacle_category_id
         JOIN beneficiaries b ON b.id = ar.beneficiary_id
         WHERE link.goods_request_id = :goods_id ORDER BY ar.id'
    );
    foreach ($rows as &$row) {
        $linkedStatement->execute(['goods_id' => (int) $row['id']]);
        $row['linked_aid'] = $linkedStatement->fetchAll();
        $itemId = (int) $row['item_id'];
        $requiredByCategory = [];
        foreach ($row['linked_aid'] as $linked) {
            if ($linked['spectacle_category_id'] !== null) {
                $categoryId = (int) $linked['spectacle_category_id'];
                $requiredByCategory[$categoryId] = ($requiredByCategory[$categoryId] ?? 0) + (int) $linked['quantity'];
            }
        }
        foreach ($row['linked_aid'] as &$linked) {
            $linked['optical'] = widmsIsOpticalItem((string) $linked['item_name'], (string) $linked['variety'], (string) $linked['category_name']);
            $linked['spectacles'] = widmsIsSpectacleItem((string) $linked['item_name']);
            $linked['spectacle_available'] = null;
            if ($linked['spectacles']) {
                $categoryId = (int) ($linked['spectacle_category_id'] ?? 0);
                $balances = widmsSpectacleCategoryBalances($database, $itemId);
                $linked['spectacle_available'] = ($balances[$categoryId] ?? 0) + ($requiredByCategory[$categoryId] ?? 0);
                $linked['spectacle_matched'] = $categoryId > 0 && ($balances[$categoryId] ?? -1) >= 0;
            }
        }
        unset($linked);
    }
    unset($row);
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $adminDirectRows = [];
    $rows = [];
    $errors[] = 'Approved dispatches are unavailable.';
}

$batches = [];
foreach ($rows as $row) {
    $reference = trim((string) ($row['request_batch_ref'] ?? ''));
    $key = $reference !== '' ? $reference : 'legacy-' . (int) $row['id'];
    if (!isset($batches[$key])) {
        $batches[$key] = [
            'reference' => $reference,
            'first_id' => (int) $row['id'],
            'requester_name' => (string) $row['requester_name'],
            'approved_by' => (string) $row['approver_name'],
            'created_at' => (string) $row['created_at'],
            'justification' => (string) $row['justification'],
            'rows' => [],
        ];
    }
    $batches[$key]['rows'][] = $row;
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars(widmsLanguage(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars(t('Dispatch Requests'), ENT_QUOTES, 'UTF-8') ?> | SWPCS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/admin-dashboard.css') ?>" rel="stylesheet">
    <link href="assets/css/approved-aid-bundles.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/approved-aid-bundles.css') ?>" rel="stylesheet">
</head>
<body class="store-page store-dispatch-requests-page">
<?php require __DIR__ . '/../../includes/store-keeper-sidebar.php'; ?>
<div class="admin-shell">
    <header class="topbar"><div class="d-flex align-items-center gap-3"><button class="menu-button" id="menu-button" type="button" aria-label="<?= htmlspecialchars(t('Open navigation'), ENT_QUOTES, 'UTF-8') ?>">&#9776;</button><h1><?= htmlspecialchars(t('Dispatch Requests'), ENT_QUOTES, 'UTF-8') ?></h1></div></header>
    <main class="dashboard-content admin-correction-review-page aid-request-card-page store-dispatch-workflow-page admin-stock-quota-page">
        <?php renderSuccessMessage($success); ?>
        <?php if ($errors): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars(implode(' ', array_map('t', array_unique($errors))), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <section class="admin-data-card admin-correction-review-card store-dispatch-review-card">
            <div class="admin-data-header admin-correction-review-header">
                <div><h2><?= htmlspecialchars(t('Dispatch Requests'), ENT_QUOTES, 'UTF-8') ?></h2><p><?= htmlspecialchars(t('Review the requester and recipient before releasing goods.'), ENT_QUOTES, 'UTF-8') ?></p></div>
                <div class="correction-review-counts"><span><strong><?= count($adminDirectRows) + count($batches) ?></strong> <?= htmlspecialchars(t('Pending'), ENT_QUOTES, 'UTF-8') ?></span><a class="outline-action" href="dashboard.php?page=recent-dispatches"><?= htmlspecialchars(t('View Recently Dispatched'), ENT_QUOTES, 'UTF-8') ?></a></div>
            </div>
            <div class="admin-correction-list stock-quota-card-list">
                <?php if (!$adminDirectRows && !$rows): ?><div class="empty-corrections"><strong><?= htmlspecialchars(t('No dispatch requests are waiting.'), ENT_QUOTES, 'UTF-8') ?></strong></div><?php endif; ?>
                <?php foreach ($adminDirectRows as $directRow) renderStoreDispatchRequestCard($directRow, 'direct'); ?>
                <?php foreach ($batches as $batch):
                    $lines = $batch['rows'];
                    $displayReference = $batch['reference'] !== '' ? $batch['reference'] : 'GR-' . str_pad((string) $batch['first_id'], 4, '0', STR_PAD_LEFT);
                    $totalUnits = array_sum(array_map('intval', array_column($lines, 'quantity')));
                    $requiredStock = [];
                    $stockAvailable = [];
                    $batchReady = true;
                    foreach ($lines as $line) {
                        $itemId = (int) $line['item_id'];
                        $requiredStock[$itemId] = ($requiredStock[$itemId] ?? 0) + (int) $line['quantity'];
                        $stockAvailable[$itemId] = (int) $line['central_stock'];
                        foreach ($line['linked_aid'] as $linked) {
                            if (!empty($linked['spectacles']) && empty($linked['spectacle_matched'])) {
                                $batchReady = false;
                            }
                        }
                    }
                    foreach ($requiredStock as $itemId => $required) {
                        if ($required > $stockAvailable[$itemId]) $batchReady = false;
                    }
                    ?>
                    <article id="goods-request-<?= (int) $batch['first_id'] ?>" class="admin-correction-item stock-quota-card store-quota-batch-card admin-notification-target" tabindex="-1">
                        <?php foreach (array_slice($lines, 1) as $line): ?><span id="goods-request-<?= (int) $line['id'] ?>" class="stock-quota-anchor" aria-hidden="true"></span><?php endforeach; ?>
                        <div class="correction-summary stock-quota-summary">
                            <div>
                                <div class="correction-reference-line"><strong><?= htmlspecialchars($displayReference, ENT_QUOTES, 'UTF-8') ?></strong><span><?= count($lines) ?> <?= htmlspecialchars(t(count($lines) === 1 ? 'Allocation' : 'Allocations'), ENT_QUOTES, 'UTF-8') ?></span><small><?= number_format($totalUnits) ?> <?= htmlspecialchars(t('Total Units'), ENT_QUOTES, 'UTF-8') ?></small></div>
                                <p class="correction-submission-meta"><?= htmlspecialchars(t('Requested by'), ENT_QUOTES, 'UTF-8') ?> <strong><?= htmlspecialchars($batch['requester_name'], ENT_QUOTES, 'UTF-8') ?></strong> &middot; <?= date('d M Y, H:i', strtotime($batch['created_at'])) ?> &middot; <?= htmlspecialchars(t('Approved by') . ' ' . $batch['approved_by'], ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                            <span class="correction-status pending"><?= htmlspecialchars(t('Awaiting dispatch'), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="stock-quota-justification"><span><?= htmlspecialchars(t('Quota Justification'), ENT_QUOTES, 'UTF-8') ?></span><p><?= nl2br(htmlspecialchars($batch['justification'], ENT_QUOTES, 'UTF-8')) ?></p></div>
                        <div class="stock-quota-lines-wrap"><table class="admin-data-table stock-quota-lines-table">
                            <thead><tr><th>#</th><th><?= htmlspecialchars(t('Beneficiary'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('NIC / Elder Card'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Aid Item'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Spectacle Type'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Available Stock'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Quantity'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('District / DS Division'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Receiving SSO'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Document'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead>
                            <tbody><?php foreach ($lines as $index => $line):
                                $linked = $line['linked_aid'][0] ?? null;
                                $powerOrType = $linked['spectacle_category_name'] ?? null;
                                ?><tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars((string) ($linked['beneficiary_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($linked['identification'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><strong><?= htmlspecialchars(widmsAidItemName((string) $line['item_name']), ENT_QUOTES, 'UTF-8') ?></strong><?php if ((string) $line['variety'] !== ''): ?><small><?= htmlspecialchars((string) $line['variety'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?></td>
                                <td><?= $powerOrType !== null ? htmlspecialchars(t((string) $powerOrType), ENT_QUOTES, 'UTF-8') : '&mdash;' ?></td>
                                <td><?= number_format((int) $line['central_stock']) ?></td>
                                <td><strong><?= number_format((int) $line['quantity']) ?></strong></td>
                                <td><?= htmlspecialchars($line['district_name'] . ' / ' . $line['division_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($line['sso_name'] ?: t('Subject Officer direct release')), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><a class="outline-action" target="_blank" rel="noopener" href="dashboard.php?page=goods-request-document&amp;request_id=<?= (int) $line['id'] ?>&amp;print=1"><?= htmlspecialchars(t('View PDF'), ENT_QUOTES, 'UTF-8') ?></a></td>
                            </tr><?php endforeach; ?></tbody>
                        </table></div>
                        <form method="post" class="stock-quota-decision-form store-quota-batch-actions">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                            <?php if ($batch['reference'] !== ''): ?><input type="hidden" name="batch_reference" value="<?= htmlspecialchars($batch['reference'], ENT_QUOTES, 'UTF-8') ?>"><?php else: ?><input type="hidden" name="request_id" value="<?= (int) $batch['first_id'] ?>"><?php endif; ?>
                            <small><?= htmlspecialchars(t('All allocations in this batch will be released to the Subject Officer together.'), ENT_QUOTES, 'UTF-8') ?></small>
                            <button class="admin-primary-action" type="submit" <?= $batchReady ? '' : 'disabled' ?>><?= htmlspecialchars(t('Release Stock Quota'), ENT_QUOTES, 'UTF-8') ?></button>
                            <?php if (!$batchReady): ?><small class="fulfillment-warning"><?= htmlspecialchars(t('Waiting for matching Central Stock'), ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
</div>
<script src="assets/js/admin-dashboard.js"></script>
<script>
document.querySelectorAll('.store-quota-batch-actions').forEach((form) => {
    form.addEventListener('submit', () => {
        const button = form.querySelector('button[type="submit"]');
        if (!button) return;
        button.disabled = true;
        button.textContent = 'Releasing...';
    });
});
</script>
</body>
</html>
