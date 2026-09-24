<?php
declare(strict_types=1);

requireRole('store-keeper');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/activity.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_once __DIR__ . '/../../includes/optical-stock.php';
require_once __DIR__ . '/../../includes/store-dispatch-request-card.php';

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
                        ar.quantity, ar.prescribed_power, i.item_name, i.variety,
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
            if (widmsIsOpticalItem((string) $release['item_name'], (string) $release['variety'], (string) $release['category_name'])) {
                if ($release['prescribed_power'] === null
                    || widmsOpticalPowerAvailable($database, (int) $release['item_id'], (float) $release['prescribed_power'], true) < (int) $release['quantity']) {
                    throw new RuntimeException('The exact optical power is not available for this direct aid release.');
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
    if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Your session expired. Please refresh the page and try again.';
    }
    if (!$requestId) {
        $errors[] = 'Select an approved stock quota request.';
    }

    if (!$errors) {
        try {
            $database->beginTransaction();
            $requestStatement = $database->prepare(
                "SELECT g.*,
                        (SELECT COUNT(*) FROM goods_request_aid_requests gar WHERE gar.goods_request_id = g.id) AS linked_needs
                 FROM goods_requests g
                 WHERE g.id = :id AND g.status = 'approved-awaiting-dispatch'
                 FOR UPDATE"
            );
            $requestStatement->execute(['id' => $requestId]);
            $request = $requestStatement->fetch();
            if (!$request) {
                throw new RuntimeException('This request is not approved for release.');
            }

            $stock = $database->prepare('SELECT quantity FROM inventory_items WHERE id = :item FOR UPDATE');
            $stock->execute(['item' => $request['item_id']]);
            $available = (int) $stock->fetchColumn();
            if ($available < (int) $request['quantity']) {
                throw new RuntimeException('Insufficient central stock. Available: ' . $available . '.');
            }

            $linkedPowerStatement = $database->prepare(
                "SELECT ar.item_id, ar.prescribed_power,
                        i.item_name, i.variety, c.name AS category_name
                 FROM goods_request_aid_requests link
                 JOIN aid_requests ar ON ar.id = link.aid_request_id
                 JOIN inventory_items i ON i.id = ar.item_id
                 JOIN item_categories c ON c.id = i.category_id
                 WHERE link.goods_request_id = :goods_request_id"
            );
            $linkedPowerStatement->execute(['goods_request_id' => $requestId]);
            foreach ($linkedPowerStatement->fetchAll() as $linkedRequest) {
                if (!widmsIsOpticalItem(
                    (string) $linkedRequest['item_name'],
                    (string) $linkedRequest['variety'],
                    (string) $linkedRequest['category_name']
                )) {
                    continue;
                }
                if ($linkedRequest['prescribed_power'] === null) {
                    throw new RuntimeException('The optical request has no prescribed power.');
                }
                $balances = widmsOpticalPowerBalances(
                    $database,
                    (int) $linkedRequest['item_id'],
                    true
                );
                $powerKey = widmsPowerKey((float) $linkedRequest['prescribed_power']);
                if ((int) ($balances[$powerKey] ?? 0) < 0) {
                    throw new RuntimeException(sprintf(
                        'Power %+.2f is no longer available for this optical request.',
                        (float) $linkedRequest['prescribed_power']
                    ));
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
                    "SELECT ar.id, ar.prescribed_power,
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
            $message = 'Stock quota released to the Subject Officer who submitted the request.';

            $database->commit();
            logActivity(
                'Dispatch',
                $message,
                'GR-' . str_pad((string) $requestId, 4, '0', STR_PAD_LEFT),
                'dispatched'
            );
            $_SESSION['flash_success'] = $message;
            unset($_SESSION['csrf_token']);
            header('Location: dashboard.php?page=recent-dispatches#goods-request-' . $requestId);
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
        "SELECT adr.id, ar.id AS aid_request_id, ar.quantity, ar.prescribed_power,
                i.item_name, i.variety, i.quantity AS central_stock,
                b.full_name AS beneficiary_name, ds.name AS division_name,
                admin_user.full_name AS admin_name
         FROM admin_direct_releases adr
         JOIN aid_requests ar ON ar.id = adr.aid_request_id
         JOIN inventory_items i ON i.id = ar.item_id
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
                (SELECT ar.prescribed_power
                 FROM goods_request_aid_requests power_link
                 JOIN aid_requests ar ON ar.id = power_link.aid_request_id
                 WHERE power_link.goods_request_id = g.id LIMIT 1) AS prescribed_power,
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
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $adminDirectRows = [];
    $rows = [];
    $errors[] = 'Approved dispatches are unavailable.';
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars(widmsLanguage(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars(t('Dispatch Requests'), ENT_QUOTES, 'UTF-8') ?> | WIDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/admin-dashboard.css') ?>" rel="stylesheet">
</head>
<body class="store-page store-dispatch-requests-page">
<?php require __DIR__ . '/../../includes/store-keeper-sidebar.php'; ?>
<div class="admin-shell">
    <header class="topbar"><div class="d-flex align-items-center gap-3"><button class="menu-button" id="menu-button" type="button" aria-label="<?= htmlspecialchars(t('Open navigation'), ENT_QUOTES, 'UTF-8') ?>">&#9776;</button><h1><?= htmlspecialchars(t('Dispatch Requests'), ENT_QUOTES, 'UTF-8') ?></h1></div></header>
    <main class="dashboard-content admin-correction-review-page aid-request-card-page store-dispatch-workflow-page">
        <?php renderSuccessMessage($success); ?>
        <?php if ($errors): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars(implode(' ', array_map('t', array_unique($errors))), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <section class="admin-data-card admin-correction-review-card store-dispatch-review-card">
            <div class="admin-data-header admin-correction-review-header">
                <div><h2><?= htmlspecialchars(t('Dispatch Requests'), ENT_QUOTES, 'UTF-8') ?></h2><p><?= htmlspecialchars(t('Review the requester and recipient before releasing goods.'), ENT_QUOTES, 'UTF-8') ?></p></div>
                <div class="correction-review-counts"><span><strong><?= count($adminDirectRows) + count($rows) ?></strong> <?= htmlspecialchars(t('Pending'), ENT_QUOTES, 'UTF-8') ?></span><a class="outline-action" href="dashboard.php?page=recent-dispatches"><?= htmlspecialchars(t('View Recently Dispatched'), ENT_QUOTES, 'UTF-8') ?></a></div>
            </div>
            <div class="admin-correction-list">
                <?php if (!$adminDirectRows && !$rows): ?><div class="empty-corrections"><strong><?= htmlspecialchars(t('No dispatch requests are waiting.'), ENT_QUOTES, 'UTF-8') ?></strong></div><?php endif; ?>
                <?php foreach ($adminDirectRows as $directRow) renderStoreDispatchRequestCard($directRow, 'direct'); ?>
                <?php foreach ($rows as $row) renderStoreDispatchRequestCard($row, 'quota'); ?>
            </div>
        </section>
    </main>
</div>
<script src="assets/js/admin-dashboard.js"></script>
</body>
</html>
