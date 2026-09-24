<?php
declare(strict_types=1);

requireRole('subject-officer');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/activity.php';
require_once __DIR__ . '/../../includes/ui-messages.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_once __DIR__ . '/../../includes/optical-stock.php';

$activePage = 'distribute-items';
$database = database();
$userId = (int) $_SESSION['user_id'];
$errors = [];
$success = (string) ($_SESSION['flash_success'] ?? '');
unset($_SESSION['flash_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && (string) ($_POST['action'] ?? '') === 'distribute-from-sso-pool') {
    $aidRequestId = filter_input(INPUT_POST, 'aid_request_id', FILTER_VALIDATE_INT);
    if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = t('Your session expired. Please try again.');
    }
    if (!$aidRequestId) {
        $errors[] = t('Select a valid approved aid request.');
    }

    if ($errors === []) {
        try {
            $database->beginTransaction();
            $statement = $database->prepare(
                "SELECT ar.id, ar.beneficiary_id, ar.item_id, ar.quantity,
                        b.ds_division_id, i.item_name, i.variety,
                        c.name AS category_name
                 FROM aid_requests ar
                 JOIN beneficiaries b ON b.id = ar.beneficiary_id
                 JOIN inventory_items i ON i.id = ar.item_id
                 JOIN item_categories c ON c.id = i.category_id
                 WHERE ar.id = :id
                   AND ar.submitted_by = :user_id
                   AND ar.status = 'approved'
                   AND NOT EXISTS (SELECT 1 FROM distributions d WHERE d.aid_request_id = ar.id)
                   AND NOT EXISTS (
                       SELECT 1 FROM goods_request_aid_requests link
                       JOIN goods_requests goods ON goods.id = link.goods_request_id
                       WHERE link.aid_request_id = ar.id AND goods.status <> 'rejected'
                   )
                 FOR UPDATE"
            );
            $statement->execute(['id' => $aidRequestId, 'user_id' => $userId]);
            $request = $statement->fetch();
            if (!$request) {
                throw new RuntimeException(t('This approved request is no longer available for direct distribution.'));
            }
            if (widmsIsOpticalItem(
                (string) $request['item_name'],
                (string) $request['variety'],
                (string) $request['category_name']
            )) {
                throw new RuntimeException(t('Optical items must use the power-matched release workflow.'));
            }

            $statement = $database->prepare(
                "SELECT p.officer_id, u.full_name,
                        (p.allocated - p.distributed + p.reused) AS available
                 FROM officer_pools p
                 JOIN users u ON u.id = p.officer_id
                 WHERE p.item_id = :item_id
                   AND u.role = 'social-service-officer'
                   AND u.status = 'active'
                   AND u.ds_division_id = :division_id
                   AND (p.allocated - p.distributed + p.reused) >= :quantity
                 ORDER BY available DESC, p.officer_id
                 LIMIT 1
                 FOR UPDATE"
            );
            $statement->execute([
                'item_id' => (int) $request['item_id'],
                'division_id' => (int) $request['ds_division_id'],
                'quantity' => (int) $request['quantity'],
            ]);
            $sourcePool = $statement->fetch();
            if (!$sourcePool) {
                throw new RuntimeException(t('The division SSO pool no longer has enough stock for this request.'));
            }

            $statement = $database->prepare(
                'UPDATE officer_pools
                 SET distributed = distributed + :quantity
                 WHERE officer_id = :officer_id AND item_id = :item_id
                   AND (allocated - distributed + reused) >= :quantity_check'
            );
            $statement->execute([
                'quantity' => (int) $request['quantity'],
                'officer_id' => (int) $sourcePool['officer_id'],
                'item_id' => (int) $request['item_id'],
                'quantity_check' => (int) $request['quantity'],
            ]);
            if ($statement->rowCount() !== 1) {
                throw new RuntimeException(t('The SSO pool balance changed. Refresh and try again.'));
            }

            $statement = $database->prepare(
                "INSERT INTO distributions
                    (aid_request_id, beneficiary_id, item_id, quantity,
                     distribution_type, source, notes, distributed_by)
                 VALUES
                    (:aid_request_id, :beneficiary_id, :item_id, :quantity,
                     'request-based', 'officer-pool', :notes, :distributed_by)"
            );
            $statement->execute([
                'aid_request_id' => (int) $request['id'],
                'beneficiary_id' => (int) $request['beneficiary_id'],
                'item_id' => (int) $request['item_id'],
                'quantity' => (int) $request['quantity'],
                'notes' => 'Distributed by Subject Officer from SSO pool: ' . (string) $sourcePool['full_name'],
                'distributed_by' => $userId,
            ]);
            $distributionId = (int) $database->lastInsertId();

            $statement = $database->prepare(
                "UPDATE aid_requests SET status = 'distributed'
                 WHERE id = :id AND status = 'approved'"
            );
            $statement->execute(['id' => (int) $request['id']]);
            if ($statement->rowCount() !== 1) {
                throw new RuntimeException(t('The aid request changed while it was being distributed.'));
            }

            notifyUser(
                $database,
                (int) $sourcePool['officer_id'],
                'subject-used-pool-' . $distributionId,
                'Pool stock distributed',
                'AR-' . str_pad((string) $request['id'], 4, '0', STR_PAD_LEFT),
                'The Subject Officer distributed ' . (int) $request['quantity'] . ' unit(s) from your pool.',
                'dashboard.php?page=pool-quota'
            );

            $database->commit();
            $message = 'Item distributed directly to the beneficiary from the division SSO pool.';
            logActivity(
                'Distribution',
                $message,
                'DIST-' . str_pad((string) $distributionId, 4, '0', STR_PAD_LEFT),
                'distributed'
            );
            $_SESSION['flash_success'] = $message;
            unset($_SESSION['csrf_token']);
            header('Location: dashboard.php?page=distribute-items#aid-request-' . $aidRequestId);
            exit;
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            error_log($exception->getMessage());
            $errors[] = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : t('Unable to complete the direct distribution.');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && (string) ($_POST['action'] ?? '') !== 'distribute-from-sso-pool') {
    $fulfillmentId = filter_input(INPUT_POST, 'fulfillment_id', FILTER_VALIDATE_INT);
    $action = trim((string) ($_POST['action'] ?? ''));
    $ssoId = filter_input(INPUT_POST, 'sso_id', FILTER_VALIDATE_INT);

    if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = t('Your session expired. Please try again.');
    }
    if (!$fulfillmentId || !in_array($action, ['distribute', 'handover'], true)) {
        $errors[] = t('Invalid fulfillment action.');
    }
    if ($action === 'handover' && !$ssoId) {
        $errors[] = t('Select the beneficiary division SSO.');
    }

    if (!$errors) {
        try {
            $database->beginTransaction();
            $statement = $database->prepare(
                "SELECT f.id, f.aid_request_id, f.status,
                        ar.beneficiary_id, ar.item_id, ar.quantity,
                        b.ds_division_id
                 FROM goods_fulfillments f
                 JOIN aid_requests ar ON ar.id = f.aid_request_id
                 JOIN beneficiaries b ON b.id = ar.beneficiary_id
                 WHERE f.id = :id
                   AND f.subject_officer_id = :user_id
                   AND f.status = 'with-subject-officer'
                   AND ar.status = 'approved'
                 FOR UPDATE"
            );
            $statement->execute(['id' => $fulfillmentId, 'user_id' => $userId]);
            $fulfillment = $statement->fetch();
            if (!$fulfillment) {
                throw new RuntimeException(t('This item is no longer available in your custody.'));
            }

            if ($action === 'handover') {
                $statement = $database->prepare(
                    "SELECT id FROM users
                     WHERE id = :sso_id
                       AND role = 'social-service-officer'
                       AND status = 'active'
                       AND ds_division_id = :division_id"
                );
                $statement->execute([
                    'sso_id' => $ssoId,
                    'division_id' => (int) $fulfillment['ds_division_id'],
                ]);
                if (!$statement->fetchColumn()) {
                    throw new RuntimeException(t('The selected SSO is not assigned to the beneficiary division.'));
                }

                $statement = $database->prepare(
                    "UPDATE goods_fulfillments
                     SET status = 'pending-sso-handover',
                         sso_id = :sso_id,
                         handed_to_sso_at = NOW()
                     WHERE id = :id AND status = 'with-subject-officer'"
                );
                $statement->execute(['sso_id' => $ssoId, 'id' => $fulfillmentId]);
                if ($statement->rowCount() !== 1) {
                    throw new RuntimeException(t('This item is no longer available in your custody.'));
                }
                $message = 'Item handed to the division SSO.';
            } else {
                $statement = $database->prepare(
                    "INSERT INTO distributions
                        (aid_request_id, beneficiary_id, item_id, quantity,
                         distribution_type, source, distributed_by)
                     VALUES
                        (:aid_request_id, :beneficiary_id, :item_id, :quantity,
                         'request-based', 'officer-pool', :distributed_by)"
                );
                $statement->execute([
                    'aid_request_id' => (int) $fulfillment['aid_request_id'],
                    'beneficiary_id' => (int) $fulfillment['beneficiary_id'],
                    'item_id' => (int) $fulfillment['item_id'],
                    'quantity' => (int) $fulfillment['quantity'],
                    'distributed_by' => $userId,
                ]);

                $statement = $database->prepare(
                    "UPDATE goods_fulfillments
                     SET status = 'distributed', distributed_at = NOW()
                     WHERE id = :id AND status = 'with-subject-officer'"
                );
                $statement->execute(['id' => $fulfillmentId]);
                if ($statement->rowCount() !== 1) {
                    throw new RuntimeException(t('This item is no longer available in your custody.'));
                }

                $statement = $database->prepare(
                    "UPDATE aid_requests SET status = 'distributed'
                     WHERE id = :id AND status = 'approved'"
                );
                $statement->execute(['id' => (int) $fulfillment['aid_request_id']]);
                if ($statement->rowCount() !== 1) {
                    throw new RuntimeException(t('The aid request is no longer ready for distribution.'));
                }
                $message = 'Item distributed directly to the beneficiary.';
            }

            $database->commit();
            logActivity(
                'Goods Fulfillment',
                $message,
                'FUL-' . str_pad((string) $fulfillmentId, 4, '0', STR_PAD_LEFT),
                $action
            );
            $_SESSION['flash_success'] = $message;
            unset($_SESSION['csrf_token']);
            header('Location: dashboard.php?page=distribute-items#fulfillment-' . $fulfillmentId);
            exit;
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            error_log($exception->getMessage());
            $errors[] = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : t('Unable to process this item. Please try again.');
        }
    }
}

try {
    $statement = $database->prepare(
        "SELECT f.id, f.goods_request_id, f.aid_request_id, f.status, f.lens_unit_identifier,
                ar.quantity, ar.prescribed_power,
                b.full_name, b.nic, b.address, b.ds_division_id,
                i.item_name, i.variety, ds.name AS division_name
         FROM goods_fulfillments f
         JOIN aid_requests ar ON ar.id = f.aid_request_id
         JOIN beneficiaries b ON b.id = ar.beneficiary_id
         JOIN inventory_items i ON i.id = ar.item_id
         JOIN ds_divisions ds ON ds.id = b.ds_division_id
         WHERE f.subject_officer_id = :user_id
         ORDER BY FIELD(f.status, 'with-subject-officer', 'pending-sso-handover', 'distributed'), f.id DESC"
    );
    $statement->execute(['user_id' => $userId]);
    $fulfillments = $statement->fetchAll();

    $statement = $database->prepare(
        "SELECT ar.id, ar.quantity,
                b.full_name, b.nic, b.address, b.ds_division_id,
                i.item_name, i.variety, c.name AS category_name,
                ds.name AS division_name,
                p.officer_id AS source_sso_id, source_sso.full_name AS source_sso_name,
                (p.allocated - p.distributed + p.reused) AS pool_available
         FROM aid_requests ar
         JOIN beneficiaries b ON b.id = ar.beneficiary_id
         JOIN inventory_items i ON i.id = ar.item_id
         JOIN item_categories c ON c.id = i.category_id
         JOIN ds_divisions ds ON ds.id = b.ds_division_id
         JOIN users source_sso
           ON source_sso.role = 'social-service-officer'
          AND source_sso.status = 'active'
          AND source_sso.ds_division_id = b.ds_division_id
         JOIN officer_pools p
           ON p.officer_id = source_sso.id AND p.item_id = ar.item_id
         WHERE ar.submitted_by = :user_id
           AND ar.status = 'approved'
           AND (p.allocated - p.distributed + p.reused) >= ar.quantity
           AND LOWER(CONCAT(i.item_name, ' ', i.variety, ' ', c.name))
               NOT REGEXP 'contact[[:space:]]*lens|spectacle|glasses|(^|[[:space:]])specs([[:space:]]|$)'
           AND NOT EXISTS (SELECT 1 FROM distributions d WHERE d.aid_request_id = ar.id)
           AND NOT EXISTS (
               SELECT 1 FROM goods_request_aid_requests link
               JOIN goods_requests goods ON goods.id = link.goods_request_id
               WHERE link.aid_request_id = ar.id AND goods.status <> 'rejected'
           )
         ORDER BY ar.id, pool_available DESC, p.officer_id"
    );
    $statement->execute(['user_id' => $userId]);
    $poolReadyRequests = [];
    foreach ($statement->fetchAll() as $poolReadyRequest) {
        $requestId = (int) $poolReadyRequest['id'];
        $poolReadyRequests[$requestId] ??= $poolReadyRequest;
    }
    $poolReadyRequests = array_values($poolReadyRequests);

    $officerRows = $database->query(
        "SELECT id, full_name, ds_division_id
         FROM users
         WHERE role = 'social-service-officer' AND status = 'active'
         ORDER BY full_name"
    )->fetchAll();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $fulfillments = [];
    $poolReadyRequests = [];
    $officerRows = [];
    $errors[] = t('The fulfillment queue is temporarily unavailable.');
}

$officersByDivision = [];
foreach ($officerRows as $officer) {
    $officersByDivision[(int) $officer['ds_division_id']][] = $officer;
}

$statusLabels = [
    'with-subject-officer' => 'Ready to Distribute',
    'pending-sso-handover' => 'Pending SSO Handover',
    'distributed' => 'Distributed',
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars(t('Final Distribution and SSO Handover'), ENT_QUOTES, 'UTF-8') ?> | WIDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css?v=91" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/../../includes/subject-officer-sidebar.php'; ?>
<div class="admin-shell">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="menu-button" id="menu-button" type="button" aria-label="<?= htmlspecialchars(t('Open navigation'), ENT_QUOTES, 'UTF-8') ?>">&#9776;</button>
            <div>
                <h1><?= htmlspecialchars(t('Final Distribution and SSO Handover'), ENT_QUOTES, 'UTF-8') ?></h1>
                <p><?= htmlspecialchars(t('Distribute released goods to the beneficiary or hand them to the assigned division SSO.'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
    </header>

    <main class="dashboard-content fulfillment-page widms-unified-ui">
        <?php renderSuccessMessage($success); ?>
        <?php if ($errors): ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars(implode(' ', $errors), ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <section class="admin-data-card subject-pool-distribution-card">
            <div class="admin-data-header fulfillment-header">
                <div>
                    <h2><?= htmlspecialchars(t('Approved Requests Available in SSO Pools'), ENT_QUOTES, 'UTF-8') ?></h2>
                    <p><?= htmlspecialchars(t('You remain the distributor. The required quantity is deducted securely from the beneficiary division SSO pool.'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <span class="fulfillment-count"><?= count($poolReadyRequests) ?> <?= htmlspecialchars(t('ready'), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="admin-data-table-wrap">
                <table class="admin-data-table fulfillment-table">
                    <thead><tr>
                        <th><?= htmlspecialchars(t('Request'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Beneficiary'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Aid Item'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('DS Division'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Source SSO Pool'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Action'), ENT_QUOTES, 'UTF-8') ?></th>
                    </tr></thead>
                    <tbody>
                    <?php if ($poolReadyRequests === []): ?>
                        <tr><td colspan="6" class="admin-empty-row"><?= htmlspecialchars(t('No approved requests are currently ready in a division SSO pool.'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                    <?php else: foreach ($poolReadyRequests as $request): ?>
                        <?php $requestId = (int) $request['id']; ?>
                        <tr id="aid-request-<?= $requestId ?>" class="admin-notification-target" tabindex="-1">
                            <td><strong>AR-<?= str_pad((string) $requestId, 4, '0', STR_PAD_LEFT) ?></strong></td>
                            <td><strong><?= htmlspecialchars((string) $request['full_name'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars((string) ($request['nic'] ?: t('No NIC')), ENT_QUOTES, 'UTF-8') ?></small></td>
                            <td><strong><?= htmlspecialchars((string) $request['item_name'] . ((string) $request['variety'] !== '' ? ' — ' . $request['variety'] : ''), ENT_QUOTES, 'UTF-8') ?></strong><small><?= (int) $request['quantity'] ?> <?= htmlspecialchars(t('units required'), ENT_QUOTES, 'UTF-8') ?></small></td>
                            <td><?= htmlspecialchars((string) $request['division_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><strong><?= htmlspecialchars((string) $request['source_sso_name'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= (int) $request['pool_available'] ?> <?= htmlspecialchars(t('available'), ENT_QUOTES, 'UTF-8') ?></small></td>
                            <td>
                                <form method="post" class="fulfillment-action-form">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="aid_request_id" value="<?= $requestId ?>">
                                    <button type="submit" name="action" value="distribute-from-sso-pool" class="approve-button"><?= htmlspecialchars(t('Distribute to Beneficiary'), ENT_QUOTES, 'UTF-8') ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="admin-data-card">
            <div class="admin-data-header fulfillment-header">
                <div>
                    <h2><?= htmlspecialchars(t('Items Released to Me'), ENT_QUOTES, 'UTF-8') ?></h2>
                    <p><?= htmlspecialchars(t('Optical power and unit details are shown when they were recorded for the approved request.'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <span class="fulfillment-count"><?= count($fulfillments) ?> <?= htmlspecialchars(t('items'), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="admin-data-table-wrap">
                <table class="admin-data-table fulfillment-table">
                    <thead><tr>
                        <th><?= htmlspecialchars(t('Reference'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Beneficiary'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Contact Details'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Aid Item'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Optical Details'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('DS Division'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Status'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Action'), ENT_QUOTES, 'UTF-8') ?></th>
                    </tr></thead>
                    <tbody>
                    <?php if (!$fulfillments): ?>
                        <tr><td colspan="8" class="admin-empty-state"><?= htmlspecialchars(t('No released items are waiting for distribution.'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($fulfillments as $fulfillment): ?>
                            <?php
                            $rowId = (int) $fulfillment['id'];
                            $status = (string) $fulfillment['status'];
                            $divisionOfficers = $officersByDivision[(int) $fulfillment['ds_division_id']] ?? [];
                            $itemLabel = (string) $fulfillment['item_name'];
                            if (trim((string) $fulfillment['variety']) !== '') {
                                $itemLabel .= ' — ' . $fulfillment['variety'];
                            }
                            ?>
                            <tr id="fulfillment-<?= $rowId ?>">
                                <td><strong>FUL-<?= str_pad((string) $rowId, 4, '0', STR_PAD_LEFT) ?></strong></td>
                                <td><strong><?= htmlspecialchars((string) $fulfillment['full_name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                <td>
                                    <span><?= htmlspecialchars((string) ($fulfillment['nic'] ?: t('No NIC')), ENT_QUOTES, 'UTF-8') ?></span>
                                    <small><?= htmlspecialchars((string) $fulfillment['address'], ENT_QUOTES, 'UTF-8') ?></small>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($itemLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                                    <small><?= (int) $fulfillment['quantity'] ?> <?= htmlspecialchars(t('units'), ENT_QUOTES, 'UTF-8') ?></small>
                                </td>
                                <td>
                                    <?php if ($fulfillment['lens_unit_identifier'] || $fulfillment['prescribed_power'] !== null): ?>
                                        <?php if ($fulfillment['lens_unit_identifier']): ?><span><?= htmlspecialchars((string) $fulfillment['lens_unit_identifier'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                                        <?php if ($fulfillment['prescribed_power'] !== null): ?><small><?= htmlspecialchars(t('Power'), ENT_QUOTES, 'UTF-8') ?>: <?= sprintf('%+.2f', (float) $fulfillment['prescribed_power']) ?></small><?php endif; ?>
                                    <?php else: ?>
                                        <span aria-label="<?= htmlspecialchars(t('Not applicable'), ENT_QUOTES, 'UTF-8') ?>">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars((string) $fulfillment['division_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="goods-status-pill status-<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t($statusLabels[$status] ?? ucwords(str_replace('-', ' ', $status))), ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td class="fulfillment-action-cell">
                                    <?php if ($status === 'with-subject-officer'): ?>
                                        <form method="post" class="fulfillment-action-form">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="fulfillment_id" value="<?= $rowId ?>">
                                            <button type="submit" name="action" value="distribute" class="approve-button"><?= htmlspecialchars(t('Distribute to Beneficiary'), ENT_QUOTES, 'UTF-8') ?></button>
                                            <div class="fulfillment-handover-controls">
                                                <label for="sso-<?= $rowId ?>" class="visually-hidden"><?= htmlspecialchars(t('Division SSO'), ENT_QUOTES, 'UTF-8') ?></label>
                                                <select id="sso-<?= $rowId ?>" name="sso_id" <?= !$divisionOfficers ? 'disabled' : '' ?>>
                                                    <option value=""><?= htmlspecialchars(t('Select division SSO'), ENT_QUOTES, 'UTF-8') ?></option>
                                                    <?php foreach ($divisionOfficers as $officer): ?>
                                                        <option value="<?= (int) $officer['id'] ?>"><?= htmlspecialchars((string) $officer['full_name'], ENT_QUOTES, 'UTF-8') ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" name="action" value="handover" class="admin-primary-action" <?= !$divisionOfficers ? 'disabled' : '' ?>><?= htmlspecialchars(t('Hand to SSO'), ENT_QUOTES, 'UTF-8') ?></button>
                                            </div>
                                            <?php if (!$divisionOfficers): ?><small class="fulfillment-warning"><?= htmlspecialchars(t('No active SSO is assigned to this division.'), ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
                                        </form>
                                    <?php else: ?>
                                        <span aria-hidden="true">&mdash;</span>
                                    <?php endif; ?>
                                    <a class="outline-action" target="_blank" rel="noopener" href="dashboard.php?page=goods-request-document&amp;request_id=<?= (int) $fulfillment['goods_request_id'] ?>&amp;print=1"><?= htmlspecialchars(t('View PDF'), ENT_QUOTES, 'UTF-8') ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
<script src="assets/js/admin-dashboard.js"></script>
</body>
</html>
