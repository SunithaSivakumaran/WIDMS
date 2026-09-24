<?php
declare(strict_types=1);

requireRole('subject-officer');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/activity.php';
require_once __DIR__ . '/../../includes/notifications.php';

$activePage = 'my-goods-requests';
$errors = [];
$success = (string) ($_SESSION['flash_success'] ?? '');
unset($_SESSION['flash_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                 WHERE g.id = :id AND g.requested_by = :requester
                   AND g.released_to_subject_id = :recipient
                   AND g.status = 'dispatched' AND g.allocated_to_sso_at IS NULL
                   AND NOT EXISTS (
                       SELECT 1 FROM goods_request_aid_requests gar
                       WHERE gar.goods_request_id = g.id
                   )
                 FOR UPDATE"
            );
            $statement->execute([
                'id' => $requestId,
                'requester' => (int) $_SESSION['user_id'],
                'recipient' => (int) $_SESSION['user_id'],
            ]);
            $request = $statement->fetch();
            if (!$request) {
                throw new RuntimeException('This allocation is unavailable or was already assigned.');
            }

            $target = $database->prepare(
                "SELECT id FROM users
                 WHERE id = :sso AND role = 'social-service-officer'
                   AND status = 'active' AND ds_division_id = :division
                 FOR UPDATE"
            );
            $target->execute([
                'sso' => $request['destination_sso_id'],
                'division' => $request['destination_ds_division_id'],
            ]);
            if (!$target->fetchColumn()) {
                throw new RuntimeException('The planned SSO is no longer active in this DS Division.');
            }

            $pool = $database->prepare(
                'INSERT INTO officer_pools (officer_id, ds_division_id, item_id, allocated)
                 VALUES (:officer, :division, :item, :quantity)
                 ON DUPLICATE KEY UPDATE
                    ds_division_id = VALUES(ds_division_id),
                    allocated = allocated + VALUES(allocated)'
            );
            $pool->execute([
                'officer' => $request['destination_sso_id'],
                'division' => $request['destination_ds_division_id'],
                'item' => $request['item_id'],
                'quantity' => $request['quantity'],
            ]);
            $database->prepare(
                'INSERT INTO pool_allocations (officer_id, item_id, quantity, allocated_by)
                 VALUES (:officer, :item, :quantity, :subject)'
            )->execute([
                'officer' => $request['destination_sso_id'],
                'item' => $request['item_id'],
                'quantity' => $request['quantity'],
                'subject' => (int) $_SESSION['user_id'],
            ]);
            $update = $database->prepare(
                'UPDATE goods_requests SET allocated_to_sso_at = NOW()
                 WHERE id = :id AND allocated_to_sso_at IS NULL'
            );
            $update->execute(['id' => $requestId]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('This allocation changed while it was being assigned.');
            }

            notifyUser(
                $database,
                (int) $request['destination_sso_id'],
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
            header('Location: dashboard.php?page=my-goods-requests#goods-request-' . $requestId);
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

try {
    $statement = database()->prepare(
        "SELECT g.*, i.item_name, i.variety, ds.name AS division_name,
                d.name AS district_name, target.full_name AS sso_name,
                approver.full_name AS approver_name,
                (SELECT ar.prescribed_power
                 FROM goods_request_aid_requests power_link
                 JOIN aid_requests ar ON ar.id = power_link.aid_request_id
                 WHERE power_link.goods_request_id = g.id LIMIT 1) AS prescribed_power,
                COUNT(gar.aid_request_id) AS beneficiary_count
         FROM goods_requests g
         JOIN inventory_items i ON i.id = g.item_id
         JOIN ds_divisions ds ON ds.id = g.destination_ds_division_id
         JOIN districts d ON d.id = ds.district_id
         LEFT JOIN users target ON target.id = g.destination_sso_id
         LEFT JOIN users approver ON approver.id = g.approved_by
         LEFT JOIN goods_request_aid_requests gar ON gar.goods_request_id = g.id
         WHERE g.requested_by = :requester
         GROUP BY g.id
         ORDER BY g.created_at DESC, g.id DESC"
    );
    $statement->execute(['requester' => (int) $_SESSION['user_id']]);
    $requests = $statement->fetchAll();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $requests = [];
    $errors[] = 'Stock quota request history is unavailable.';
}

$counts = array_count_values(array_column($requests, 'status'));
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
    <title><?= htmlspecialchars(t('Quota Request History'), ENT_QUOTES, 'UTF-8') ?> | WIDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/../../includes/subject-officer-sidebar.php'; ?>
<div class="admin-shell">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="menu-button" id="menu-button">&#9776;</button>
            <h1><?= htmlspecialchars(t('Stock Quota Requests'), ENT_QUOTES, 'UTF-8') ?></h1>
        </div>
    </header>
    <main class="dashboard-content goods-history-page">
        <?php renderSuccessMessage($success); ?>
        <?php if ($errors): ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars(implode(' ', array_map('t', $errors)), ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

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

        <section class="admin-data-card goods-history-card">
            <div class="admin-data-header">
                <div>
                    <h2><?= htmlspecialchars(t('Quota Request History'), ENT_QUOTES, 'UTF-8') ?></h2>
                    <small><?= htmlspecialchars(t('Only stock quota requests submitted by you are shown here.'), ENT_QUOTES, 'UTF-8') ?></small>
                </div>
                <div class="goods-history-actions">
                    <select id="goods-history-status" aria-label="<?= htmlspecialchars(t('Filter by status'), ENT_QUOTES, 'UTF-8') ?>">
                        <option value=""><?= htmlspecialchars(t('All Status'), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php foreach ($statusLabels as $status => $label): ?>
                            <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t($label), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <a class="admin-primary-action" href="dashboard.php?page=request-goods">+ <?= htmlspecialchars(t('New Quota Request'), ENT_QUOTES, 'UTF-8') ?></a>
                </div>
            </div>
            <div class="admin-data-table-wrap">
                <table class="admin-data-table goods-history-table">
                    <thead>
                    <tr>
                        <th><?= htmlspecialchars(t('Batch'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Request'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Aid Item'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Prescribed Power'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Quantity'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('District / DS Division'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Assigned SSO'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Submitted'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Justification'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Status'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('SSO Assignment'), ENT_QUOTES, 'UTF-8') ?></th>
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
                            <tr id="goods-request-<?= (int) $request['id'] ?>" data-status="<?= htmlspecialchars((string) $request['status'], ENT_QUOTES, 'UTF-8') ?>">
                                <td>
                                    <?php if ($firstInBatch): ?><span id="<?= htmlspecialchars(strtolower($batch), ENT_QUOTES, 'UTF-8') ?>"></span><?php endif; ?>
                                    <strong><?= htmlspecialchars($batch, ENT_QUOTES, 'UTF-8') ?></strong>
                                </td>
                                <td>GR-<?= str_pad((string) $request['id'], 4, '0', STR_PAD_LEFT) ?></td>
                                <td><strong><?= htmlspecialchars((string) $request['item_name'], ENT_QUOTES, 'UTF-8') ?></strong><?php if ((string) $request['variety'] !== ''): ?><small><?= htmlspecialchars((string) $request['variety'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?></td>
                                <td><?= $request['prescribed_power'] !== null ? sprintf('%+.2f', (float) $request['prescribed_power']) : '&mdash;' ?></td>
                                <td><strong><?= number_format((int) $request['quantity']) ?></strong></td>
                                <td><?= htmlspecialchars($request['district_name'] . ' / ' . $request['division_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($request['sso_name'] ?: ((int) $request['beneficiary_count'] > 0 ? t('Subject Officer direct release') : t('Not assigned'))), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= date('d M Y, H:i', strtotime((string) $request['created_at'])) ?></td>
                                <td class="goods-justification-cell"><span title="<?= htmlspecialchars((string) $request['justification'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(trim(preg_replace('/\s+/', ' ', (string) $request['justification']) ?? ''), ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><span class="goods-status-pill <?= htmlspecialchars($statusClasses[$request['status']] ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t($statusLabels[$request['status']] ?? $request['status']), ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td>
                                    <?php if ((int) $request['beneficiary_count'] > 0): ?>
                                        <span class="goods-assignment-note"><?= htmlspecialchars(t('Handled through beneficiary fulfillment'), ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php elseif (!$request['sso_name']): ?>
                                        —
                                    <?php elseif ($request['allocated_to_sso_at']): ?>
                                        <span class="goods-status-pill is-approved"><?= htmlspecialchars(t('Assigned to SSO'), ENT_QUOTES, 'UTF-8') ?></span>
                                        <small><?= date('d M Y, H:i', strtotime((string) $request['allocated_to_sso_at'])) ?></small>
                                    <?php elseif ($request['status'] === 'dispatched' && (int) $request['released_to_subject_id'] === (int) $_SESSION['user_id']): ?>
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
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
<script src="assets/js/admin-dashboard.js"></script>
<script>
(() => {
    const filter = document.getElementById('goods-history-status');
    if (!filter) return;
    filter.addEventListener('change', () => {
        document.querySelectorAll('.goods-history-table tbody tr[data-status]').forEach((row) => {
            row.hidden = filter.value !== '' && row.dataset.status !== filter.value;
        });
    });
})();
</script>
</body>
</html>
