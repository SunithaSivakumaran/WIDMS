<?php
declare(strict_types=1);

requireRole('subject-officer');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/activity.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_once __DIR__ . '/../../includes/goods-request-actor.php';

function subjectOfficerGoodsRequestActionTrail(array $request): string
{
    $steps = [
        ['Submitted', $request['requester_username'] ?? null, $request['requester_role'] ?? null],
    ];
    if (!empty($request['approver_username'])) {
        $steps[] = ['Reviewed', $request['approver_username'], $request['approver_role'] ?? null];
    }
    if (!empty($request['dispatcher_username'])) {
        $steps[] = ['Released', $request['dispatcher_username'], $request['dispatcher_role'] ?? null];
    }
    if (!empty($request['allocated_to_sso_at'])) {
        $steps[] = ['Assigned to SSO', $request['allocator_username'] ?? $request['requester_username'] ?? null, $request['allocator_role'] ?? $request['requester_role'] ?? null];
    }

    $html = '<div class="subject-request-action-trail">';
    foreach ($steps as [$action, $username, $role]) {
        $name = trim((string) $username);
        $html .= '<div class="subject-request-action-step"><span>'
            . htmlspecialchars(t($action), ENT_QUOTES, 'UTF-8') . '</span><strong>'
            . htmlspecialchars($name !== '' ? $name : 'User not recorded', ENT_QUOTES, 'UTF-8')
            . '</strong><small>'
            . htmlspecialchars(t(widmsGoodsRequestRoleLabel((string) $role)), ENT_QUOTES, 'UTF-8')
            . '</small></div>';
    }
    return $html . '</div>';
}

$beneficiaryPage = (string) ($_GET['page'] ?? '') === 'my-beneficiary-requests';
$activePage = $beneficiaryPage ? 'my-beneficiary-requests' : 'my-goods-requests';
$pageTitle = $beneficiaryPage ? 'Beneficiary Request Bundles' : 'Stock Quota Requests';
$errors = [];
$success = (string) ($_SESSION['flash_success'] ?? '');
unset($_SESSION['flash_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($beneficiaryPage) {
        $errors[] = 'SSO quota assignment is not available on this page.';
    }
    $requestId = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
    $action = (string) ($_POST['action'] ?? '');
    if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Your session expired. Please refresh the page and try again.';
    }
    if (!$requestId || $action !== 'assign-to-sso') {
        $errors[] = 'Invalid SSO assignment request.';
    }

    if (!$errors) {
        $database = database();
        try {
            $database->beginTransaction();
            $statement = $database->prepare(
                "SELECT g.*, i.item_name, target.full_name AS sso_name
                 FROM goods_requests g
                 JOIN inventory_items i ON i.id = g.item_id
                 JOIN users target ON target.id = g.destination_sso_id
                 WHERE g.id = :id
                   AND g.status = 'dispatched' AND g.allocated_to_sso_at IS NULL
                   AND g.destination_sso_id IS NOT NULL
                   AND g.aid_request_id IS NULL
                   AND NOT EXISTS (
                       SELECT 1 FROM goods_request_aid_requests gar
                       WHERE gar.goods_request_id = g.id
                   )
                 FOR UPDATE"
            );
            $statement->execute(['id' => $requestId]);
            $request = $statement->fetch();
            if (!$request) {
                throw new RuntimeException('This allocation is unavailable or was already assigned.');
            }

            $target = $database->prepare(
                "SELECT id FROM users
                 WHERE role = 'social-service-officer' AND status = 'active'
                   AND ds_division_id = :division
                 ORDER BY (id = :planned) DESC, id ASC LIMIT 1 FOR UPDATE"
            );
            $target->execute([
                'planned' => $request['destination_sso_id'],
                'division' => $request['destination_ds_division_id'],
            ]);
            $activeSsoId = (int) $target->fetchColumn();
            if (!$activeSsoId) {
                throw new RuntimeException('No active SSO is assigned to this DS Division.');
            }

            $pool = $database->prepare(
                'INSERT INTO division_pools (ds_division_id, item_id, allocated)
                 VALUES (:division, :item, :quantity)
                 ON DUPLICATE KEY UPDATE allocated = allocated + VALUES(allocated)'
            );
            $pool->execute([
                'division' => $request['destination_ds_division_id'],
                'item' => $request['item_id'],
                'quantity' => $request['quantity'],
            ]);
            $database->prepare(
                'INSERT INTO pool_allocations (officer_id, ds_division_id, item_id, quantity, allocated_by)
                 VALUES (:officer, :division, :item, :quantity, :subject)'
            )->execute([
                'officer' => $activeSsoId,
                'division' => $request['destination_ds_division_id'],
                'item' => $request['item_id'],
                'quantity' => $request['quantity'],
                'subject' => (int) $_SESSION['user_id'],
            ]);
            $update = $database->prepare(
                'UPDATE goods_requests SET allocated_to_sso_at = NOW(), allocated_to_sso_by = :actor,
                     destination_sso_id = :active_sso_id
                 WHERE id = :id AND allocated_to_sso_at IS NULL'
            );
            $update->execute(['id' => $requestId, 'actor' => (int) $_SESSION['user_id'], 'active_sso_id' => $activeSsoId]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('This allocation changed while it was being assigned.');
            }

            notifyUser(
                $database,
                $activeSsoId,
                'goods-pool-allocation-' . $requestId,
                'Stock allocated',
                'New stock quota added to your pool',
                $request['item_name'] . ' × ' . (int) $request['quantity'] . ' was assigned by the Subject Officer.',
                'dashboard.php?page=assigned-stock-quotas#goods-request-' . $requestId
            );
            $database->commit();
            logActivity('Stock Quota Requests', 'Assigned dispatched stock quota to the planned SSO pool', 'GR-' . str_pad((string) $requestId, 4, '0', STR_PAD_LEFT), 'assigned');
            $_SESSION['flash_success'] = 'Stock quota assigned to the selected SSO pool.';
            unset($_SESSION['csrf_token']);
            header('Location: dashboard.php?page=my-goods-requests');
            exit;
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            error_log($exception->getMessage());
            $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to assign the stock quota to the SSO.';
        }
    }
}

$requests = [];
$beneficiaryBundles = [];
$pendingAssignments = [];
try {
    if (!$beneficiaryPage) {
    $pendingStatement = database()->query(
        "SELECT g.id, g.request_batch_ref, g.quantity, g.destination_ds_division_id,
                i.item_name, ds.name AS division_name, district.name AS district_name,
                (SELECT active_sso.full_name FROM users active_sso
                 WHERE active_sso.role = 'social-service-officer'
                   AND active_sso.status = 'active'
                   AND active_sso.ds_division_id = g.destination_ds_division_id
                 ORDER BY (active_sso.id = g.destination_sso_id) DESC, active_sso.id ASC
                 LIMIT 1) AS sso_name,
                requester.username AS requester_username,
                requester.role AS requester_role, recipient.username AS recipient_username,
                recipient.role AS recipient_role
         FROM goods_requests g
         JOIN inventory_items i ON i.id = g.item_id
         JOIN ds_divisions ds ON ds.id = g.destination_ds_division_id
         JOIN districts district ON district.id = ds.district_id
         JOIN users requester ON requester.id = g.requested_by
         LEFT JOIN users recipient ON recipient.id = g.released_to_subject_id
         WHERE g.status = 'dispatched' AND g.allocated_to_sso_at IS NULL
           AND g.destination_sso_id IS NOT NULL
           AND g.aid_request_id IS NULL
           AND NOT EXISTS (
               SELECT 1 FROM goods_request_aid_requests linked
               WHERE linked.goods_request_id = g.id
           )
         ORDER BY g.created_at ASC, g.id ASC"
    );
    $pendingAssignments = $pendingStatement->fetchAll();
    $statement = database()->prepare(
        "SELECT g.*, i.item_name, i.variety, ds.name AS division_name,
                d.name AS district_name, target.full_name AS sso_name,
                requester.username AS requester_username, requester.role AS requester_role,
                approver.full_name AS approver_name,
                approver.username AS approver_username, approver.role AS approver_role,
                dispatcher.username AS dispatcher_username, dispatcher.role AS dispatcher_role,
                allocator.username AS allocator_username, allocator.role AS allocator_role
         FROM goods_requests g
         JOIN inventory_items i ON i.id = g.item_id
         JOIN ds_divisions ds ON ds.id = g.destination_ds_division_id
         JOIN districts d ON d.id = ds.district_id
         JOIN users requester ON requester.id = g.requested_by
         LEFT JOIN users target ON target.id = g.destination_sso_id
         LEFT JOIN users approver ON approver.id = g.approved_by
         LEFT JOIN users dispatcher ON dispatcher.id = g.dispatched_by
         LEFT JOIN users allocator ON allocator.id = g.allocated_to_sso_by
         WHERE g.requested_by = :requester
           AND g.destination_sso_id IS NOT NULL
           AND g.aid_request_id IS NULL
           AND NOT EXISTS (
               SELECT 1 FROM goods_request_aid_requests linked
               WHERE linked.goods_request_id = g.id
           )
         ORDER BY g.created_at DESC, g.id DESC"
    );
    $statement->execute(['requester' => (int) $_SESSION['user_id']]);
    $requests = $statement->fetchAll();
    } else {
    $beneficiaryStatement = database()->prepare(
        "SELECT g.id, g.item_id, g.destination_ds_division_id,
                g.request_batch_ref, g.status, g.created_at, g.quantity,
                g.allocated_to_sso_at,
                i.item_name, i.variety,
                spectacle_type.name AS spectacle_type,
                beneficiary.id AS beneficiary_id, beneficiary.full_name AS beneficiary_name,
                beneficiary.nic, beneficiary.elders_card_number,
                ar.id AS aid_request_id, ds.name AS division_name, d.name AS district_name,
                requester.username AS requester_username, requester.role AS requester_role,
                approver.username AS approver_username, approver.role AS approver_role,
                dispatcher.username AS dispatcher_username, dispatcher.role AS dispatcher_role
         FROM goods_requests g
         JOIN inventory_items i ON i.id = g.item_id
         JOIN ds_divisions ds ON ds.id = g.destination_ds_division_id
         JOIN districts d ON d.id = ds.district_id
         JOIN users requester ON requester.id = g.requested_by
         LEFT JOIN users approver ON approver.id = g.approved_by
         LEFT JOIN users dispatcher ON dispatcher.id = g.dispatched_by
         LEFT JOIN goods_request_aid_requests linked ON linked.goods_request_id = g.id
         JOIN aid_requests ar ON ar.id = COALESCE(linked.aid_request_id, g.aid_request_id)
         JOIN beneficiaries beneficiary ON beneficiary.id = ar.beneficiary_id
         LEFT JOIN spectacle_categories spectacle_type ON spectacle_type.id = ar.spectacle_category_id
         WHERE g.requested_by = :requester
         ORDER BY g.created_at DESC, g.id DESC, ar.id DESC"
    );
    $beneficiaryStatement->execute(['requester' => (int) $_SESSION['user_id']]);
    foreach ($beneficiaryStatement->fetchAll() as $line) {
        $batch = trim((string) ($line['request_batch_ref'] ?? ''));
        if ($batch === '') {
            $batch = 'GR-' . str_pad((string) $line['id'], 4, '0', STR_PAD_LEFT);
        }
        if (!isset($beneficiaryBundles[$batch])) {
            $beneficiaryBundles[$batch] = [
                'reference' => $batch,
                'created_at' => $line['created_at'],
                'lines' => [],
            ];
        }
        $beneficiaryBundles[$batch]['lines'][] = $line;
    }
    }
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $errors[] = 'Stock quota request history is unavailable.';
}

$visibleLines = $beneficiaryPage
    ? array_merge([], ...array_map(static fn(array $bundle): array => $bundle['lines'], array_values($beneficiaryBundles)))
    : $requests;
$counts = array_count_values(array_column($visibleLines, 'status'));
$divisionOptions = [];
$itemOptions = [];
foreach ($visibleLines as $line) {
    $divisionOptions[(int) $line['destination_ds_division_id']] = $line['district_name'] . ' / ' . $line['division_name'];
    $itemOptions[(int) $line['item_id']] = widmsAidItemName((string) $line['item_name']);
}
asort($divisionOptions, SORT_NATURAL | SORT_FLAG_CASE);
asort($itemOptions, SORT_NATURAL | SORT_FLAG_CASE);
$statusLabels = [
    'pending-admin-approval' => 'Pending Admin Approval',
    'approved-awaiting-dispatch' => 'Approved — Awaiting Dispatch',
    'dispatched' => 'Dispatched',
    'rejected' => 'Rejected',
];
$statusClasses = [
    'pending-admin-approval' => 'is-pending',
    'approved-awaiting-dispatch' => 'is-approved',
    'dispatched' => 'is-dispatched',
    'rejected' => 'is-rejected',
];
$seenBatches = [];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars(t($pageTitle), ENT_QUOTES, 'UTF-8') ?> | SWPCS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/admin-dashboard.css') ?>" rel="stylesheet">
    <link href="assets/css/approved-aid-bundles.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/approved-aid-bundles.css') ?>" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/../../includes/subject-officer-sidebar.php'; ?>
<div class="admin-shell">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="menu-button" id="menu-button">&#9776;</button>
            <h1><?= htmlspecialchars(t($pageTitle), ENT_QUOTES, 'UTF-8') ?></h1>
        </div>
    </header>
    <main class="dashboard-content goods-history-page">
        <?php renderSuccessMessage($success); ?>
        <?php if ($errors): ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars(implode(' ', array_map('t', $errors)), ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if (!$beneficiaryPage): ?>
        <section class="admin-data-card goods-history-card">
            <div class="goods-history-toolbar"><strong><?= htmlspecialchars(t('Pending SSO Stock Assignments'), ENT_QUOTES, 'UTF-8') ?> <span class="goods-status-pill is-pending"><?= count($pendingAssignments) ?></span></strong></div>
            <div class="admin-data-table-wrap">
                <table class="admin-data-table goods-history-table">
                    <thead><tr><th><?= htmlspecialchars(t('Request'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Aid Item'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Quantity'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('District / DS Division'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Receiving SSO'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Submitted By'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Released To'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Action'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead>
                    <tbody>
                    <?php if (!$pendingAssignments): ?><tr><td colspan="8" class="admin-empty-row"><?= htmlspecialchars(t('No dispatched SSO quotas are waiting for assignment.'), ENT_QUOTES, 'UTF-8') ?></td></tr><?php endif; ?>
                    <?php foreach ($pendingAssignments as $pending): ?>
                        <tr id="pending-assignment-<?= (int) $pending['id'] ?>">
                            <td><strong>GR-<?= str_pad((string) $pending['id'], 4, '0', STR_PAD_LEFT) ?></strong><small><?= htmlspecialchars((string) ($pending['request_batch_ref'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small></td>
                            <td><?= htmlspecialchars(widmsAidItemName((string) $pending['item_name']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= number_format((int) $pending['quantity']) ?></td>
                            <td><?= htmlspecialchars((string) $pending['district_name'] . ' / ' . (string) $pending['division_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($pending['sso_name'] ?: t('No active SSO')), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="subject-actor-label"><strong><?= htmlspecialchars((string) $pending['requester_username'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars(t(widmsGoodsRequestRoleLabel((string) $pending['requester_role'])), ENT_QUOTES, 'UTF-8') ?></small></span></td>
                            <td><span class="subject-actor-label"><strong><?= htmlspecialchars((string) ($pending['recipient_username'] ?: t('Not recorded')), ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars(t(widmsGoodsRequestRoleLabel((string) ($pending['recipient_role'] ?? 'subject-officer'))), ENT_QUOTES, 'UTF-8') ?></small></span></td>
                            <td><form method="post" class="goods-sso-assignment-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="request_id" value="<?= (int) $pending['id'] ?>"><input type="hidden" name="action" value="assign-to-sso"><button class="admin-primary-action"><?= htmlspecialchars(t('Assign to SSO'), ENT_QUOTES, 'UTF-8') ?></button></form></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>

        <h2 class="goods-history-own-heading"><?= htmlspecialchars(t('My Request History'), ENT_QUOTES, 'UTF-8') ?></h2>

        <section class="operation-summary-grid goods-history-summary">
            <?php foreach ([
                ['Pending Admin Approval', (int) ($counts['pending-admin-approval'] ?? 0)],
                ['Approved — Awaiting Dispatch', (int) ($counts['approved-awaiting-dispatch'] ?? 0)],
                ['Dispatched', (int) ($counts['dispatched'] ?? 0)],
                ['Rejected', (int) ($counts['rejected'] ?? 0)],
            ] as [$label, $count]): ?>
                <article class="operation-summary-card">
                    <span aria-hidden="true">&#128230;</span>
                    <p><?= htmlspecialchars(t($label), ENT_QUOTES, 'UTF-8') ?></p>
                    <strong><?= $count ?></strong>
                    <small><?= htmlspecialchars(t('Request lines'), ENT_QUOTES, 'UTF-8') ?></small>
                </article>
            <?php endforeach; ?>
        </section>

        <div class="goods-history-toolbar">
            <label><?= htmlspecialchars(t('Status'), ENT_QUOTES, 'UTF-8') ?>
                <select id="goods-history-status" aria-label="<?= htmlspecialchars(t('Filter by status'), ENT_QUOTES, 'UTF-8') ?>">
                    <option value=""><?= htmlspecialchars(t('All Status'), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php foreach ($statusLabels as $status => $label): ?><option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t($label), ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                </select>
            </label>
            <label><?= htmlspecialchars(t('DS Division'), ENT_QUOTES, 'UTF-8') ?>
                <select id="goods-history-division"><option value=""><?= htmlspecialchars(t('All divisions'), ENT_QUOTES, 'UTF-8') ?></option><?php foreach ($divisionOptions as $id => $name): ?><option value="<?= (int) $id ?>"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select>
            </label>
            <label><?= htmlspecialchars(t('Aid Item'), ENT_QUOTES, 'UTF-8') ?>
                <select id="goods-history-item"><option value=""><?= htmlspecialchars(t('All stock items'), ENT_QUOTES, 'UTF-8') ?></option><?php foreach ($itemOptions as $id => $name): ?><option value="<?= (int) $id ?>"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select>
            </label>
            <?php if (!$beneficiaryPage): ?><a class="admin-primary-action" href="dashboard.php?page=request-goods">+ <?= htmlspecialchars(t('New Quota Request'), ENT_QUOTES, 'UTF-8') ?></a><?php endif; ?>
        </div>

        <?php if (!$beneficiaryPage): ?>
        <section class="admin-data-card goods-history-card">
            <div class="admin-data-table-wrap">
                <table class="admin-data-table goods-history-table">
                    <thead>
                    <tr>
                        <th><?= htmlspecialchars(t('Batch'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Request'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Aid Item'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Quantity'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('District / DS Division'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Assigned SSO'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Submitted'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Justification'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Status'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('SSO Assignment'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th>Action History</th>
                        <th><?= htmlspecialchars(t('Admin Response'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Document'), ENT_QUOTES, 'UTF-8') ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$requests): ?>
                        <tr><td colspan="13" class="admin-empty-row"><?= htmlspecialchars(t('No stock quota requests available.'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($requests as $request):
                            $batch = (string) ($request['request_batch_ref'] ?: 'GRB-' . str_pad((string) $request['id'], 6, '0', STR_PAD_LEFT));
                            $firstInBatch = !isset($seenBatches[$batch]);
                            $seenBatches[$batch] = true;
                            ?>
                            <tr id="goods-request-<?= (int) $request['id'] ?>" class="goods-history-filter-row" data-filter-lines="<?= htmlspecialchars(json_encode([['division' => (int) $request['destination_ds_division_id'], 'item' => (int) $request['item_id'], 'status' => (string) $request['status']]], JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8') ?>">
                                <td>
                                    <?php if ($firstInBatch): ?><span id="<?= htmlspecialchars(strtolower($batch), ENT_QUOTES, 'UTF-8') ?>"></span><?php endif; ?>
                                    <strong><?= htmlspecialchars($batch, ENT_QUOTES, 'UTF-8') ?></strong>
                                </td>
                                <td>GR-<?= str_pad((string) $request['id'], 4, '0', STR_PAD_LEFT) ?></td>
                                <td><strong><?= htmlspecialchars(widmsAidItemName((string) $request['item_name']), ENT_QUOTES, 'UTF-8') ?></strong><?php if ((string) $request['variety'] !== ''): ?><small><?= htmlspecialchars((string) $request['variety'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?></td>
                                <td><strong><?= number_format((int) $request['quantity']) ?></strong></td>
                                <td><?= htmlspecialchars($request['district_name'] . ' / ' . $request['division_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($request['sso_name'] ?: t('Not assigned')), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= date('d M Y, H:i', strtotime((string) $request['created_at'])) ?></td>
                                <td class="goods-justification-cell"><span title="<?= htmlspecialchars((string) $request['justification'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(trim(preg_replace('/\s+/', ' ', (string) $request['justification']) ?? ''), ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><span class="goods-status-pill <?= htmlspecialchars($statusClasses[$request['status']] ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t($statusLabels[$request['status']] ?? $request['status']), ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td>
                                    <?php if (!$request['sso_name']): ?>
                                        —
                                    <?php elseif ($request['allocated_to_sso_at']): ?>
                                        <span class="goods-status-pill is-approved"><?= htmlspecialchars(t('Assigned to SSO'), ENT_QUOTES, 'UTF-8') ?></span>
                                        <small><?= date('d M Y, H:i', strtotime((string) $request['allocated_to_sso_at'])) ?></small>
                                    <?php elseif ($request['status'] === 'dispatched'): ?>
                                        <form method="post" class="goods-sso-assignment-form">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                                            <input type="hidden" name="action" value="assign-to-sso">
                                            <button class="admin-primary-action"><?= htmlspecialchars(t('Assign to SSO'), ENT_QUOTES, 'UTF-8') ?></button>
                                        </form>
                                    <?php else: ?>
                                        <span class="goods-assignment-note"><?= htmlspecialchars(t('Planned for') . ' ' . $request['sso_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= subjectOfficerGoodsRequestActionTrail($request) ?></td>
                                <td>
                                    <?php if ($request['status'] === 'rejected'): ?>
                                        <span class="goods-admin-response is-rejected"><?= htmlspecialchars((string) ($request['rejection_reason'] ?: 'No reason provided.'), ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php elseif ($request['approver_name']): ?>
                                        <?= htmlspecialchars(t('Approved by') . ' ' . $request['approver_name'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td><a class="outline-action" target="_blank" rel="noopener" href="dashboard.php?page=goods-request-document&amp;request_id=<?= (int) $request['id'] ?>&amp;print=1"><?= htmlspecialchars(t('View PDF'), ENT_QUOTES, 'UTF-8') ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if ($requests): ?><tr class="goods-history-no-matches" hidden><td colspan="13" class="admin-empty-row">No requests match these filters.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php else: ?>
        <section class="admin-data-card goods-history-card beneficiary-bundles-card">
            <div class="admin-data-table-wrap">
                <table class="admin-data-table beneficiary-bundles-table">
                    <thead>
                    <tr>
                        <th>Request ID</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$beneficiaryBundles): ?>
                        <tr><td colspan="4" class="admin-empty-row">No beneficiary request bundles available.</td></tr>
                    <?php else: ?>
                        <?php foreach ($beneficiaryBundles as $bundle):
                            $bundleLines = $bundle['lines'];
                            $bundleStatuses = array_values(array_unique(array_column($bundleLines, 'status')));
                            $bundleStatus = count($bundleStatuses) === 1 ? (string) $bundleStatuses[0] : '';
                            $bundleDetailId = 'beneficiary-bundle-' . substr(hash('sha256', (string) $bundle['reference']), 0, 16);
                            $filters = array_map(static fn(array $line): array => ['division' => (int) $line['destination_ds_division_id'], 'item' => (int) $line['item_id'], 'status' => (string) $line['status']], $bundleLines);
                            ?>
                            <tr id="<?= htmlspecialchars(strtolower((string) $bundle['reference']), ENT_QUOTES, 'UTF-8') ?>" class="goods-history-filter-row" data-filter-lines="<?= htmlspecialchars(json_encode($filters, JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8') ?>" data-details-id="<?= $bundleDetailId ?>">
                                <td><?php foreach ($bundleLines as $line): ?><span id="goods-request-<?= (int) $line['id'] ?>" class="stock-quota-anchor" aria-hidden="true"></span><?php endforeach; ?><strong><?= htmlspecialchars((string) $bundle['reference'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                <td><?= date('d M Y, H:i', strtotime((string) $bundle['created_at'])) ?></td>
                                <td><span class="goods-status-pill <?= htmlspecialchars($statusClasses[$bundleStatus] ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($bundleStatus !== '' ? t($statusLabels[$bundleStatus] ?? $bundleStatus) : 'Mixed statuses', ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><button type="button" class="outline-action beneficiary-bundle-toggle" aria-expanded="false" aria-controls="<?= $bundleDetailId ?>">View details</button></td>
                            </tr>
                            <tr id="<?= $bundleDetailId ?>" class="beneficiary-bundle-details" hidden>
                                <td colspan="4">
                                    <div class="beneficiary-bundle-detail-wrap">
                                        <table class="admin-data-table">
                                            <thead><tr><th>Request</th><th>Beneficiary</th><th>ID Number</th><th>Aid Item</th><th>Optical Detail</th><th>Quantity</th><th>Action History</th><th>Status</th><th>Document</th></tr></thead>
                                            <tbody>
                                            <?php foreach ($bundleLines as $line):
                                                $identity = trim((string) ($line['nic'] ?? ''));
                                                $identityLabel = 'NIC';
                                                if ($identity === '') {
                                                    $identity = trim((string) ($line['elders_card_number'] ?? ''));
                                                    $identityLabel = "Elders' Identity Card";
                                                }
                                                $opticalDetail = '—';
                                                if ($line['spectacle_type'] !== null) {
                                                    $opticalDetail = 'Type: ' . t((string) $line['spectacle_type']);
                                                }
                                                ?>
                                                <tr>
                                                    <td>GR-<?= str_pad((string) $line['id'], 4, '0', STR_PAD_LEFT) ?></td>
                                                    <td><strong><?= htmlspecialchars((string) $line['beneficiary_name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                                    <td><?= $identity !== '' ? htmlspecialchars($identityLabel . ': ' . $identity, ENT_QUOTES, 'UTF-8') : '&mdash;' ?></td>
                                                    <td><?= htmlspecialchars(widmsAidItemName((string) $line['item_name']), ENT_QUOTES, 'UTF-8') ?><?php if ((string) $line['variety'] !== ''): ?> <small><?= htmlspecialchars((string) $line['variety'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?></td>
                                                    <td><?= htmlspecialchars($opticalDetail, ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td><?= number_format((int) $line['quantity']) ?></td>
                                                    <td><?= subjectOfficerGoodsRequestActionTrail($line) ?></td>
                                                    <td><span class="goods-status-pill <?= htmlspecialchars($statusClasses[$line['status']] ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t($statusLabels[$line['status']] ?? $line['status']), ENT_QUOTES, 'UTF-8') ?></span></td>
                                                    <td><a class="outline-action" target="_blank" rel="noopener" href="dashboard.php?page=goods-request-document&amp;request_id=<?= (int) $line['id'] ?>&amp;print=1"><?= htmlspecialchars(t('View PDF'), ENT_QUOTES, 'UTF-8') ?></a></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if ($beneficiaryBundles): ?><tr class="goods-history-no-matches" hidden><td colspan="4" class="admin-empty-row">No requests match these filters.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>
    </main>
</div>
<script src="assets/js/admin-dashboard.js"></script>
<script>
(() => {
    const status = document.getElementById('goods-history-status');
    const division = document.getElementById('goods-history-division');
    const item = document.getElementById('goods-history-item');
    const rows = [...document.querySelectorAll('.goods-history-filter-row')];
    const noMatches = document.querySelector('.goods-history-no-matches');
    const applyFilters = () => {
        let visible = 0;
        rows.forEach((row) => {
            const lines = JSON.parse(row.dataset.filterLines || '[]');
            const matches = lines.some((line) =>
                (!status.value || line.status === status.value)
                && (!division.value || String(line.division) === division.value)
                && (!item.value || String(line.item) === item.value));
            row.hidden = !matches;
            if (matches) visible++;
            const details = row.dataset.detailsId && document.getElementById(row.dataset.detailsId);
            if (details) details.hidden = !matches || row.dataset.expanded !== 'true';
        });
        if (noMatches) noMatches.hidden = visible !== 0;
    };
    [status, division, item].forEach((filter) => filter?.addEventListener('change', applyFilters));
    document.querySelectorAll('.beneficiary-bundle-toggle').forEach((button) => {
        button.addEventListener('click', () => {
            const row = button.closest('.goods-history-filter-row');
            if (!row) return;
            row.dataset.expanded = row.dataset.expanded === 'true' ? 'false' : 'true';
            const expanded = row.dataset.expanded === 'true';
            button.setAttribute('aria-expanded', String(expanded));
            button.textContent = expanded ? 'Hide details' : 'View details';
            applyFilters();
        });
    });
})();
</script>
</body>
</html>
