<?php
declare(strict_types=1);

requireRole('admin');
require_once __DIR__ . '/../../config/database.php';

$activePage = 'reviewed-stock-quota-requests';
$rows = [];
$error = '';
$success = (string) ($_SESSION['flash_success'] ?? '');
unset($_SESSION['flash_success']);

try {
    $rows = database()->query(
        "SELECT g.*, i.item_name, i.variety,
                ds.name AS division_name, d.name AS district_name,
                requester.full_name AS requester_name,
                target.full_name AS sso_name,
                approver.full_name AS approver_name,
                dispatcher.full_name AS dispatcher_name,
                (SELECT ar.prescribed_power
                 FROM goods_request_aid_requests power_link
                 JOIN aid_requests ar ON ar.id = power_link.aid_request_id
                 WHERE power_link.goods_request_id = g.id LIMIT 1) AS prescribed_power
         FROM goods_requests g
         JOIN inventory_items i ON i.id = g.item_id
         JOIN ds_divisions ds ON ds.id = g.destination_ds_division_id
         JOIN districts d ON d.id = ds.district_id
         JOIN users requester ON requester.id = g.requested_by
         LEFT JOIN users target ON target.id = g.destination_sso_id
         LEFT JOIN users approver ON approver.id = g.approved_by
         LEFT JOIN users dispatcher ON dispatcher.id = g.dispatched_by
         WHERE g.status IN ('approved-awaiting-dispatch', 'dispatched', 'rejected')
         ORDER BY COALESCE(g.approved_at, g.updated_at) DESC, g.id DESC"
    )->fetchAll();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $error = 'Reviewed stock quota requests are currently unavailable.';
}

$batchStatuses = [];
foreach ($rows as $row) {
    $batchKey = trim((string) ($row['request_batch_ref'] ?? '')) ?: 'legacy-' . (int) $row['id'];
    $batchStatuses[$batchKey] = (string) $row['status'];
}
$counts = ['approved' => 0, 'rejected' => 0];
foreach ($batchStatuses as $status) {
    $counts[$status === 'rejected' ? 'rejected' : 'approved']++;
}
$labels = [
    'approved-awaiting-dispatch' => 'Approved - Awaiting Dispatch',
    'dispatched' => 'Dispatched',
    'rejected' => 'Rejected',
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(t('Reviewed Stock Quota Requests'), ENT_QUOTES, 'UTF-8') ?> | WIDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/../../includes/admin-sidebar.php'; ?>
<div class="admin-shell">
    <header class="topbar"><div class="d-flex align-items-center gap-3"><button type="button" class="menu-button" id="menu-button" aria-label="Open navigation">&#9776;</button><h1><?= htmlspecialchars(t('Reviewed Stock Quota Requests'), ENT_QUOTES, 'UTF-8') ?></h1></div></header>
    <main class="dashboard-content admin-correction-review-page reviewed-stock-quota-page">
        <?php renderSuccessMessage($success); ?>
        <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars(t($error), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <section class="admin-data-card admin-correction-table-card" aria-label="<?= htmlspecialchars(t('Reviewed Stock Quota Requests'), ENT_QUOTES, 'UTF-8') ?>">
            <div class="admin-data-header correction-card-header admin-correction-review-header">
                <a class="outline-action" href="dashboard.php?page=goods-requests"><?= htmlspecialchars(t('View Pending Quota Requests'), ENT_QUOTES, 'UTF-8') ?></a>
                <div class="correction-review-counts"><span class="approved"><strong><?= $counts['approved'] ?></strong> <?= htmlspecialchars(t('Approved'), ENT_QUOTES, 'UTF-8') ?></span><span class="rejected"><strong><?= $counts['rejected'] ?></strong> <?= htmlspecialchars(t('Rejected'), ENT_QUOTES, 'UTF-8') ?></span></div>
            </div>
            <div class="admin-data-table-wrap reviewed-stock-quota-wrap">
                <table class="admin-data-table reviewed-stock-quota-table">
                    <thead><tr>
                        <th><?= htmlspecialchars(t('Batch'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Request'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Aid Item'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Prescribed Power'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Quantity'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('District / DS Division'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Receiving SSO'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Requested By'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Reviewed By'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Status'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Admin Response'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Document'), ENT_QUOTES, 'UTF-8') ?></th>
                    </tr></thead>
                    <tbody>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="12" class="admin-empty-row"><?= htmlspecialchars(t('No reviewed stock quota requests yet.'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                    <?php else: foreach ($rows as $row): ?>
                        <?php $isRejected = $row['status'] === 'rejected'; ?>
                        <tr id="stock-quota-<?= (int) $row['id'] ?>" class="stock-quota-review-row <?= $isRejected ? 'is-rejected' : 'is-approved' ?> admin-notification-target" tabindex="-1">
                            <td><strong><?= htmlspecialchars((string) ($row['request_batch_ref'] ?: '-'), ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td>GR-<?= str_pad((string) $row['id'], 4, '0', STR_PAD_LEFT) ?></td>
                            <td><strong><?= htmlspecialchars((string) $row['item_name'], ENT_QUOTES, 'UTF-8') ?></strong><?php if ((string) $row['variety'] !== ''): ?><small><?= htmlspecialchars((string) $row['variety'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?></td>
                            <td><?= $row['prescribed_power'] !== null ? sprintf('%+.2f', (float) $row['prescribed_power']) : '&mdash;' ?></td>
                            <td><?= number_format((int) $row['quantity']) ?></td>
                            <td><?= htmlspecialchars($row['district_name'] . ' / ' . $row['division_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($row['sso_name'] ?: ($row['prescribed_power'] !== null ? t('Subject Officer direct release') : t('Not assigned'))), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) $row['requester_name'], ENT_QUOTES, 'UTF-8') ?><small><?= date('d M Y, H:i', strtotime((string) $row['created_at'])) ?></small></td>
                            <td><?= htmlspecialchars((string) ($row['approver_name'] ?: t('Administrator')), ENT_QUOTES, 'UTF-8') ?><?php if ($row['approved_at']): ?><small><?= date('d M Y, H:i', strtotime((string) $row['approved_at'])) ?></small><?php endif; ?></td>
                            <td><span class="goods-status-pill status-<?= htmlspecialchars((string) $row['status'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t($labels[$row['status']] ?? (string) $row['status']), ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= $row['rejection_reason'] ? nl2br(htmlspecialchars((string) $row['rejection_reason'], ENT_QUOTES, 'UTF-8')) : '&mdash;' ?></td>
                            <td><a class="outline-action" target="_blank" rel="noopener" href="dashboard.php?page=goods-request-document&amp;request_id=<?= (int) $row['id'] ?>&amp;print=1"><?= htmlspecialchars(t('View PDF'), ENT_QUOTES, 'UTF-8') ?></a></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
<script src="assets/js/admin-dashboard.js"></script>
</body>
</html>
