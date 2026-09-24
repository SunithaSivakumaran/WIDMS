<?php
declare(strict_types=1);

requireRole('store-keeper');
require_once __DIR__ . '/../../config/database.php';

$activePage = 'recent-dispatches';
$rows = [];
$errors = [];
$success = (string) ($_SESSION['flash_success'] ?? '');
unset($_SESSION['flash_success']);

try {
    $database = database();
    $rows = $database->query(
        "SELECT g.id, g.request_batch_ref, g.quantity, g.dispatched_at,
                i.item_name, i.variety,
                d.name AS district_name, ds.name AS division_name,
                requester.full_name AS requester_name,
                target.full_name AS sso_name,
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
         LEFT JOIN users dispatcher ON dispatcher.id = g.dispatched_by
         WHERE g.status = 'dispatched'
         ORDER BY g.dispatched_at DESC, g.id DESC
         LIMIT 200"
    )->fetchAll();
    $adminDirectRows = $database->query(
        "SELECT adr.id, ar.id AS aid_request_id, ar.quantity, ar.prescribed_power,
                adr.released_at, adr.status, i.item_name, i.variety,
                b.full_name AS beneficiary_name, ds.name AS division_name,
                d.name AS district_name, admin_user.full_name AS admin_name,
                keeper.full_name AS keeper_name
         FROM admin_direct_releases adr
         JOIN aid_requests ar ON ar.id = adr.aid_request_id
         JOIN inventory_items i ON i.id = ar.item_id
         JOIN beneficiaries b ON b.id = ar.beneficiary_id
         JOIN ds_divisions ds ON ds.id = b.ds_division_id
         JOIN districts d ON d.id = b.district_id
         JOIN users admin_user ON admin_user.id = adr.admin_id
         LEFT JOIN users keeper ON keeper.id = adr.released_by
         WHERE adr.status IN ('released-to-admin', 'distributed')
         ORDER BY adr.released_at DESC, adr.id DESC LIMIT 200"
    )->fetchAll();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $adminDirectRows = [];
    $errors[] = 'Recently dispatched stock quotas are currently unavailable.';
}
$dispatchRows = [];
foreach ($rows as $row) {
    $dispatchRows[] = [
        'kind' => 'quota', 'id' => (int) $row['id'], 'aid_request_id' => null,
        'batch' => (string) ($row['request_batch_ref'] ?? ''),
        'item_name' => (string) $row['item_name'], 'variety' => (string) $row['variety'],
        'power' => $row['prescribed_power'], 'quantity' => (int) $row['quantity'],
        'district' => (string) $row['district_name'], 'division' => (string) $row['division_name'],
        'beneficiary' => '', 'requester' => (string) $row['requester_name'],
        'recipient' => (string) $row['requester_name'], 'sso' => (string) ($row['sso_name'] ?? ''),
        'dispatcher' => (string) ($row['dispatcher_name'] ?: t('Store Keeper')),
        'date' => (string) $row['dispatched_at'], 'status' => 'dispatched',
    ];
}
foreach ($adminDirectRows as $row) {
    $dispatchRows[] = [
        'kind' => 'direct', 'id' => (int) $row['id'], 'aid_request_id' => (int) $row['aid_request_id'],
        'batch' => '', 'item_name' => (string) $row['item_name'], 'variety' => (string) $row['variety'],
        'power' => $row['prescribed_power'], 'quantity' => (int) $row['quantity'],
        'district' => (string) $row['district_name'], 'division' => (string) $row['division_name'],
        'beneficiary' => (string) $row['beneficiary_name'], 'requester' => (string) $row['admin_name'],
        'recipient' => (string) $row['admin_name'], 'sso' => '',
        'dispatcher' => (string) ($row['keeper_name'] ?: t('Store Keeper')),
        'date' => (string) $row['released_at'], 'status' => (string) $row['status'],
    ];
}
usort($dispatchRows, static fn(array $a, array $b): int => strcmp($b['date'], $a['date']) ?: ($b['id'] <=> $a['id']));
$itemFilters = array_values(array_unique(array_column($dispatchRows, 'item_name')));
natcasesort($itemFilters);
?>
<!doctype html>
<html lang="<?= htmlspecialchars(widmsLanguage(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(t('Recently Dispatched'), ENT_QUOTES, 'UTF-8') ?> | WIDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/admin-dashboard.css') ?>" rel="stylesheet">
</head>
<body class="store-page store-dispatch-page">
<?php require __DIR__ . '/../../includes/store-keeper-sidebar.php'; ?>
<div class="admin-shell">
    <header class="topbar"><div class="d-flex align-items-center gap-3"><button type="button" class="menu-button" id="menu-button" aria-label="<?= htmlspecialchars(t('Open navigation'), ENT_QUOTES, 'UTF-8') ?>">&#9776;</button><h1><?= htmlspecialchars(t('Recently Dispatched'), ENT_QUOTES, 'UTF-8') ?></h1></div></header>
    <main class="dashboard-content store-dispatch-workflow-page">
        <?php renderSuccessMessage($success); ?>
        <?php if ($errors): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars(implode(' ', array_map('t', $errors)), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

        <section class="admin-data-card store-standard-table-card store-merged-dispatch-card">
            <div class="admin-data-header">
                <div class="store-table-filters" data-store-table-filter="recently-dispatched-table">
                    <label><span><?= htmlspecialchars(t('Search'), ENT_QUOTES, 'UTF-8') ?></span><input type="search" data-filter-search placeholder="<?= htmlspecialchars(t('Search batch, item or officer...'), ENT_QUOTES, 'UTF-8') ?>"></label>
                    <label><span><?= htmlspecialchars(t('Aid Item'), ENT_QUOTES, 'UTF-8') ?></span><select data-filter-item aria-label="<?= htmlspecialchars(t('Filter by aid item'), ENT_QUOTES, 'UTF-8') ?>"><option value=""><?= htmlspecialchars(t('All Items'), ENT_QUOTES, 'UTF-8') ?></option><?php foreach ($itemFilters as $itemFilter): ?><option value="<?= htmlspecialchars(mb_strtolower($itemFilter), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($itemFilter, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label>
                </div>
                <a class="outline-action" href="dashboard.php?page=approved-dispatches"><?= htmlspecialchars(t('View Pending Dispatches'), ENT_QUOTES, 'UTF-8') ?></a>
            </div>
            <div class="admin-data-table-wrap store-standard-table-wrap">
                <table class="admin-data-table item-requests-table aid-request-list-table store-filterable-table recently-dispatched-table" id="recently-dispatched-table">
                    <thead><tr>
                        <th><?= htmlspecialchars(t('Request'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Beneficiary'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Aid Item'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Prescribed Power'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Quantity'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('District / DS Division'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Requested By'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Released To'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Planned SSO'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Dispatched By'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Dispatched Date'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Status'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Document'), ENT_QUOTES, 'UTF-8') ?></th>
                    </tr></thead>
                    <tbody>
                    <?php if ($dispatchRows === []): ?><tr><td colspan="13" class="admin-empty-row"><?= htmlspecialchars(t('No dispatches recorded yet.'), ENT_QUOTES, 'UTF-8') ?></td></tr><?php endif; ?>
                    <?php foreach ($dispatchRows as $row): ?>
                        <?php $dispatchClass = $row['kind'] === 'direct' ? 'dispatch-to-admin' : ($row['sso'] !== '' ? 'dispatch-for-sso' : 'dispatch-to-subject'); ?>
                        <tr id="<?= $row['kind'] === 'direct' ? 'aid-request-' . (int) $row['aid_request_id'] : 'goods-request-' . (int) $row['id'] ?>" class="admin-notification-target <?= $dispatchClass ?>" tabindex="-1" data-filter-row data-item="<?= htmlspecialchars(mb_strtolower($row['item_name']), ENT_QUOTES, 'UTF-8') ?>">
                            <td><strong><?= $row['kind'] === 'direct' ? 'AR-' . str_pad((string) $row['aid_request_id'], 4, '0', STR_PAD_LEFT) : 'GR-' . str_pad((string) $row['id'], 4, '0', STR_PAD_LEFT) ?></strong><?php if ($row['batch'] !== ''): ?><small><?= htmlspecialchars($row['batch'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?></td>
                            <td><?= $row['beneficiary'] !== '' ? htmlspecialchars($row['beneficiary'], ENT_QUOTES, 'UTF-8') : '&mdash;' ?></td>
                            <td class="request-aid-cell"><strong><?= htmlspecialchars($row['item_name'] . ($row['variety'] !== '' ? ' / ' . $row['variety'] : ''), ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td><?= $row['power'] !== null ? htmlspecialchars(sprintf('%+.2f', (float) $row['power']), ENT_QUOTES, 'UTF-8') : '&mdash;' ?></td>
                            <td><strong><?= number_format($row['quantity']) ?></strong></td>
                            <td><?= htmlspecialchars($row['district'] . ' / ' . $row['division'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['requester'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="dispatch-role-badge <?= $row['kind'] === 'direct' ? 'role-admin' : 'role-subject' ?>"><?= htmlspecialchars(t($row['kind'] === 'direct' ? 'Administrator' : 'Subject Officer'), ENT_QUOTES, 'UTF-8') ?></span><strong class="dispatch-person-name"><?= htmlspecialchars($row['recipient'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td><?= $row['sso'] !== '' ? '<span class="dispatch-role-badge role-sso">' . htmlspecialchars(t('SSO Planned'), ENT_QUOTES, 'UTF-8') . '</span><span class="dispatch-person-name">' . htmlspecialchars($row['sso'], ENT_QUOTES, 'UTF-8') . '</span>' : '&mdash;' ?></td>
                            <td><?= htmlspecialchars($row['dispatcher'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= $row['date'] !== '' ? htmlspecialchars(date('d M Y, H:i', strtotime($row['date'])), ENT_QUOTES, 'UTF-8') : '&mdash;' ?></td>
                            <td><span class="request-status-pill <?= $row['kind'] === 'direct' && $row['status'] === 'released-to-admin' ? 'status-released-to-admin' : 'status-distributed' ?>"><?= htmlspecialchars(t($row['kind'] === 'direct' ? ucwords(str_replace('-', ' ', $row['status'])) : 'Dispatched'), ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= $row['kind'] === 'quota' ? '<a class="outline-action" target="_blank" rel="noopener" href="dashboard.php?page=goods-request-document&amp;request_id=' . (int) $row['id'] . '&amp;print=1">' . htmlspecialchars(t('View PDF'), ENT_QUOTES, 'UTF-8') . '</a>' : '&mdash;' ?></td>
                        </tr>
                    <?php endforeach; ?><tr data-filter-empty hidden><td colspan="13" class="admin-empty-row"><?= htmlspecialchars(t('No dispatches match the selected filters.'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
<script src="assets/js/admin-dashboard.js"></script>
</body>
</html>
