<?php
declare(strict_types=1);

requireRole('social-service-officer');
require_once __DIR__ . '/../../config/database.php';

$activePage = 'assigned-stock-quotas';
$rows = [];
$error = '';

try {
    $statement = database()->prepare(
        "SELECT g.id, g.request_batch_ref, g.quantity, g.status, g.created_at,
                g.approved_at, g.dispatched_at, g.allocated_to_sso_at,
                i.item_name, i.variety,
                d.name AS district_name, ds.name AS division_name,
                requester.full_name AS requester_name
         FROM goods_requests g
         JOIN inventory_items i ON i.id = g.item_id
         JOIN ds_divisions ds ON ds.id = g.destination_ds_division_id
         JOIN districts d ON d.id = ds.district_id
         JOIN users requester ON requester.id = g.requested_by
         WHERE g.destination_sso_id = :user_id
           AND g.status IN ('approved-awaiting-dispatch', 'dispatched')
         ORDER BY COALESCE(g.approved_at, g.created_at) DESC, g.id DESC"
    );
    $statement->execute(['user_id' => (int) $_SESSION['user_id']]);
    $rows = $statement->fetchAll();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $error = 'Assigned stock quotas are currently unavailable.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(t('Assigned Stock Quotas'), ENT_QUOTES, 'UTF-8') ?> | WIDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css" rel="stylesheet">
</head>
<body class="pool-quota-page-body">
<?php require __DIR__ . '/../../includes/social-service-officer-sidebar.php'; ?>
<div class="admin-shell">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3"><button type="button" class="menu-button" id="menu-button" aria-label="<?= htmlspecialchars(t('Open navigation'), ENT_QUOTES, 'UTF-8') ?>">&#9776;</button><h1><?= htmlspecialchars(t('Assigned Stock Quotas'), ENT_QUOTES, 'UTF-8') ?></h1></div>
        <div class="topbar-actions"><label class="search-box"><span aria-hidden="true">&#128269;</span><input type="search" placeholder="<?= htmlspecialchars(t('Search anything...'), ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars(t('Search'), ENT_QUOTES, 'UTF-8') ?>"></label><button class="notification-button" type="button" aria-label="<?= htmlspecialchars(t('Notifications'), ENT_QUOTES, 'UTF-8') ?>">&#128276;</button></div>
    </header>

    <main class="dashboard-content pool-quota-page">
        <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars(t($error), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <section class="admin-data-card sso-standard-table-card">
            <div class="admin-data-table-wrap sso-standard-table-wrap">
                <table class="admin-data-table sso-standard-table assigned-stock-quota-table">
                    <thead><tr>
                        <th><?= htmlspecialchars(t('Batch'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Aid Item'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Quantity'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('District / DS Division'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Requested By'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Status'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Document'), ENT_QUOTES, 'UTF-8') ?></th>
                    </tr></thead>
                    <tbody>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="7" class="admin-empty-row"><?= htmlspecialchars(t('No stock quota allocations are assigned to you.'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                    <?php else: foreach ($rows as $row): ?>
                        <?php
                        $statusLabel = (string) $row['status'] === 'approved-awaiting-dispatch'
                            ? 'Awaiting Store Keeper Release'
                            : ($row['allocated_to_sso_at'] ? 'Added to My Pool' : 'Released to Subject Officer');
                        $statusClass = $row['allocated_to_sso_at']
                            ? 'is-approved'
                            : ((string) $row['status'] === 'approved-awaiting-dispatch' ? 'is-pending' : 'is-dispatched');
                        ?>
                        <tr id="goods-request-<?= (int) $row['id'] ?>" class="admin-notification-target" tabindex="-1">
                            <td><strong><?= htmlspecialchars((string) ($row['request_batch_ref'] ?: 'GR-' . str_pad((string) $row['id'], 4, '0', STR_PAD_LEFT)), ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td><strong><?= htmlspecialchars((string) $row['item_name'], ENT_QUOTES, 'UTF-8') ?></strong><?php if ((string) $row['variety'] !== ''): ?><small><?= htmlspecialchars((string) $row['variety'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?></td>
                            <td><?= number_format((int) $row['quantity']) ?></td>
                            <td><?= htmlspecialchars($row['district_name'] . ' / ' . $row['division_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) $row['requester_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="goods-status-pill <?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t($statusLabel), ENT_QUOTES, 'UTF-8') ?></span></td>
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
