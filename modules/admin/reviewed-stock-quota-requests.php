<?php
declare(strict_types=1);

requireRole('admin');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/goods-request-actor.php';

$beneficiaryPage = (string) ($_GET['page'] ?? '') === 'reviewed-beneficiary-requests';
$activePage = $beneficiaryPage ? 'reviewed-beneficiary-requests' : 'reviewed-stock-quota-requests';
$pageTitle = $beneficiaryPage ? 'Reviewed Beneficiary Requests' : 'Reviewed SSO Quotas';
$rows = [];
$error = '';
$success = (string) ($_SESSION['flash_success'] ?? '');
unset($_SESSION['flash_success']);

try {
    $rows = database()->query(
        "SELECT g.*, i.item_name, i.variety,
                ds.id AS division_id, ds.name AS division_name, d.name AS district_name,
                requester.full_name AS requester_name,
                requester.username AS requester_username, requester.role AS requester_role,
                target.full_name AS sso_name,
                approver.full_name AS approver_name,
                approver.username AS approver_username, approver.role AS approver_role,
                dispatcher.full_name AS dispatcher_name,
                dispatcher.username AS dispatcher_username, dispatcher.role AS dispatcher_role,
                (g.aid_request_id IS NOT NULL OR EXISTS (
                    SELECT 1 FROM goods_request_aid_requests linked
                    WHERE linked.goods_request_id = g.id
                )) AS beneficiary_linked
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
    $beneficiaryDetails = [];
    if ($beneficiaryPage) {
        $detailRows = database()->query(
        "SELECT g.id AS goods_request_id, ar.id AS aid_request_id, ar.quantity,
                spectacle_type.name AS spectacle_type,
                beneficiary.id AS beneficiary_id, beneficiary.full_name AS beneficiary_name,
                beneficiary.nic, beneficiary.elders_card_number
         FROM goods_requests g
         LEFT JOIN goods_request_aid_requests linked ON linked.goods_request_id = g.id
         JOIN aid_requests ar ON ar.id = COALESCE(linked.aid_request_id, g.aid_request_id)
         JOIN beneficiaries beneficiary ON beneficiary.id = ar.beneficiary_id
         LEFT JOIN spectacle_categories spectacle_type ON spectacle_type.id = ar.spectacle_category_id
         WHERE g.status IN ('approved-awaiting-dispatch', 'dispatched', 'rejected')
         ORDER BY g.id DESC, ar.id DESC"
        )->fetchAll();
        foreach ($detailRows as $detail) {
            $beneficiaryDetails[(int) $detail['goods_request_id']][] = $detail;
        }
    }
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $error = 'Reviewed stock quota requests are currently unavailable.';
    $beneficiaryDetails = [];
}

$quotaRows = [];
$beneficiaryBundles = [];
$divisionOptions = [];
$itemOptions = [];
foreach ($rows as $row) {
    if ((int) $row['beneficiary_linked'] === 0 && $row['destination_sso_id'] !== null) {
        $quotaRows[] = $row;
        continue;
    }
    $reference = trim((string) ($row['request_batch_ref'] ?? ''));
    $key = $reference !== '' ? $reference : 'GR-' . str_pad((string) $row['id'], 4, '0', STR_PAD_LEFT);
    if (!isset($beneficiaryBundles[$key])) {
        $beneficiaryBundles[$key] = [
            'reference' => $key,
            'created_at' => $row['created_at'],
            'requester_name' => $row['requester_name'],
            'rows' => [],
        ];
    }
    $beneficiaryBundles[$key]['rows'][] = $row;
}
$visibleRows = $beneficiaryPage
    ? array_merge([], ...array_map(static fn(array $bundle): array => $bundle['rows'], array_values($beneficiaryBundles)))
    : $quotaRows;
foreach ($visibleRows as $row) {
    $divisionOptions[(int) $row['division_id']] = $row['district_name'] . ' / ' . $row['division_name'];
    $itemOptions[(int) $row['item_id']] = widmsAidItemName((string) $row['item_name']);
}
asort($divisionOptions, SORT_NATURAL | SORT_FLAG_CASE);
asort($itemOptions, SORT_NATURAL | SORT_FLAG_CASE);

$batchStatuses = [];
if ($beneficiaryPage) {
    foreach ($beneficiaryBundles as $batch) {
        $batchStatuses[$batch['reference']] = (string) $batch['rows'][0]['status'];
    }
} else {
    foreach ($quotaRows as $row) {
        $batchKey = trim((string) ($row['request_batch_ref'] ?? '')) ?: 'legacy-' . (int) $row['id'];
        $batchStatuses[$batchKey] = (string) $row['status'];
    }
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
    <title><?= htmlspecialchars(t($pageTitle), ENT_QUOTES, 'UTF-8') ?> | SWPCS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/admin-dashboard.css') ?>" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/../../includes/admin-sidebar.php'; ?>
<div class="admin-shell">
    <header class="topbar"><div class="d-flex align-items-center gap-3"><button type="button" class="menu-button" id="menu-button" aria-label="Open navigation">&#9776;</button><h1><?= htmlspecialchars(t($pageTitle), ENT_QUOTES, 'UTF-8') ?></h1></div></header>
    <main class="dashboard-content admin-correction-review-page reviewed-stock-quota-page">
        <?php renderSuccessMessage($success); ?>
        <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars(t($error), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <section class="admin-data-card admin-correction-table-card" aria-label="<?= htmlspecialchars(t($pageTitle), ENT_QUOTES, 'UTF-8') ?>">
            <div class="reviewed-quota-filters">
                <label><?= htmlspecialchars(t('DS Division'), ENT_QUOTES, 'UTF-8') ?>
                    <select id="reviewed-division-filter">
                        <option value=""><?= htmlspecialchars(t('All divisions'), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php foreach ($divisionOptions as $divisionId => $divisionName): ?><option value="<?= (int) $divisionId ?>"><?= htmlspecialchars($divisionName, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label><?= htmlspecialchars(t('Aid Item'), ENT_QUOTES, 'UTF-8') ?>
                    <select id="reviewed-item-filter">
                        <option value=""><?= htmlspecialchars(t('All stock items'), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php foreach ($itemOptions as $itemId => $itemName): ?><option value="<?= (int) $itemId ?>"><?= htmlspecialchars($itemName, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                    </select>
                </label>
                <div class="correction-review-counts"><span class="approved"><strong><?= $counts['approved'] ?></strong> <?= htmlspecialchars(t('Approved'), ENT_QUOTES, 'UTF-8') ?></span><span class="rejected"><strong><?= $counts['rejected'] ?></strong> <?= htmlspecialchars(t('Rejected'), ENT_QUOTES, 'UTF-8') ?></span></div>
            </div>
            <?php if (!$beneficiaryPage): ?>
            <div class="admin-data-table-wrap reviewed-stock-quota-wrap">
                <table class="admin-data-table reviewed-stock-quota-table">
                    <thead><tr>
                        <th><?= htmlspecialchars(t('Batch'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Request'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Aid Item'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Quantity'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('District / DS Division'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Receiving SSO'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Requested By'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Reviewed By'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th>Last Action By</th>
                        <th><?= htmlspecialchars(t('Status'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Admin Response'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Document'), ENT_QUOTES, 'UTF-8') ?></th>
                    </tr></thead>
                    <tbody>
                    <?php if ($quotaRows === []): ?>
                        <tr><td colspan="12" class="admin-empty-row"><?= htmlspecialchars(t('No reviewed stock quota requests yet.'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                    <?php else: foreach ($quotaRows as $row): ?>
                        <?php $isRejected = $row['status'] === 'rejected'; $actor = widmsGoodsRequestLastActor($row); ?>
                        <tr id="stock-quota-<?= (int) $row['id'] ?>" class="stock-quota-review-row reviewed-filter-row <?= $isRejected ? 'is-rejected' : 'is-approved' ?> admin-notification-target" data-filter-lines="<?= htmlspecialchars(json_encode([['division' => (int) $row['division_id'], 'item' => (int) $row['item_id']]], JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8') ?>" tabindex="-1">
                            <td><strong><?= htmlspecialchars((string) ($row['request_batch_ref'] ?: '-'), ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td>GR-<?= str_pad((string) $row['id'], 4, '0', STR_PAD_LEFT) ?></td>
                            <td><strong><?= htmlspecialchars(widmsAidItemName((string) $row['item_name']), ENT_QUOTES, 'UTF-8') ?></strong><?php if ((string) $row['variety'] !== ''): ?><small><?= htmlspecialchars((string) $row['variety'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?></td>
                            <td><?= number_format((int) $row['quantity']) ?></td>
                            <td><?= htmlspecialchars($row['district_name'] . ' / ' . $row['division_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($row['sso_name'] ?: t('Not assigned')), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) $row['requester_name'], ENT_QUOTES, 'UTF-8') ?><small><?= date('d M Y, H:i', strtotime((string) $row['created_at'])) ?></small></td>
                            <td><?= htmlspecialchars((string) ($row['approver_name'] ?: t('Administrator')), ENT_QUOTES, 'UTF-8') ?><?php if ($row['approved_at']): ?><small><?= date('d M Y, H:i', strtotime((string) $row['approved_at'])) ?></small><?php endif; ?></td>
                            <td class="goods-actor-cell"><strong><?= htmlspecialchars($actor['username'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars(t($actor['role']), ENT_QUOTES, 'UTF-8') ?></small></td>
                            <td><span class="goods-status-pill status-<?= htmlspecialchars((string) $row['status'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t($labels[$row['status']] ?? (string) $row['status']), ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= $row['rejection_reason'] ? nl2br(htmlspecialchars((string) $row['rejection_reason'], ENT_QUOTES, 'UTF-8')) : '&mdash;' ?></td>
                            <td><a class="outline-action" target="_blank" rel="noopener" href="dashboard.php?page=goods-request-document&amp;request_id=<?= (int) $row['id'] ?>&amp;print=1"><?= htmlspecialchars(t('View PDF'), ENT_QUOTES, 'UTF-8') ?></a></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    <?php if ($quotaRows !== []): ?><tr class="reviewed-no-matches" hidden><td colspan="12" class="admin-empty-row">No requests match these filters.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="admin-data-table-wrap reviewed-stock-quota-wrap">
                <table class="admin-data-table reviewed-beneficiary-table">
                    <thead><tr><th>Request ID</th><th><?= htmlspecialchars(t('Requested By'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Status'), ENT_QUOTES, 'UTF-8') ?></th><th>Action</th></tr></thead>
                    <tbody>
                    <?php if ($beneficiaryBundles === []): ?>
                        <tr><td colspan="4" class="admin-empty-row">No reviewed beneficiary requests yet.</td></tr>
                    <?php else: foreach ($beneficiaryBundles as $bundle):
                        $bundleRows = $bundle['rows'];
                        $statuses = array_values(array_unique(array_column($bundleRows, 'status')));
                        $status = count($statuses) === 1 ? (string) $statuses[0] : '';
                        $detailsId = 'reviewed-bundle-' . substr(hash('sha256', (string) $bundle['reference']), 0, 16);
                        $filters = array_map(static fn(array $row): array => ['division' => (int) $row['division_id'], 'item' => (int) $row['item_id']], $bundleRows);
                        ?>
                        <tr id="stock-quota-<?= (int) $bundleRows[0]['id'] ?>" class="reviewed-filter-row reviewed-bundle-row admin-notification-target" data-filter-lines="<?= htmlspecialchars(json_encode($filters, JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8') ?>" data-details-id="<?= $detailsId ?>" tabindex="-1">
                            <td><?php foreach (array_slice($bundleRows, 1) as $linkedRow): ?><span id="stock-quota-<?= (int) $linkedRow['id'] ?>" class="stock-quota-anchor" aria-hidden="true"></span><?php endforeach; ?><strong><?= htmlspecialchars((string) $bundle['reference'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td><?= htmlspecialchars((string) $bundle['requester_name'], ENT_QUOTES, 'UTF-8') ?><small><?= date('d M Y, H:i', strtotime((string) $bundle['created_at'])) ?></small></td>
                            <td><span class="goods-status-pill <?= $status !== '' ? 'status-' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') : 'is-mixed' ?>"><?= htmlspecialchars($status !== '' ? t($labels[$status] ?? $status) : 'Mixed statuses', ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><button type="button" class="outline-action reviewed-bundle-toggle" aria-controls="<?= $detailsId ?>" aria-expanded="false">View details</button></td>
                        </tr>
                        <tr id="<?= $detailsId ?>" class="reviewed-bundle-details" hidden><td colspan="4"><div class="reviewed-bundle-detail-wrap"><table class="admin-data-table">
                            <thead><tr><th><?= htmlspecialchars(t('Request'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Beneficiary'), ENT_QUOTES, 'UTF-8') ?></th><th>ID Number</th><th><?= htmlspecialchars(t('Aid Item'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Spectacle Type'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Quantity'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('District / DS Division'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Reviewed By'), ENT_QUOTES, 'UTF-8') ?></th><th>Last Action By</th><th><?= htmlspecialchars(t('Status'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Admin Response'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Document'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead>
                            <tbody><?php foreach ($bundleRows as $row):
                                $details = $beneficiaryDetails[(int) $row['id']] ?? [null];
                                foreach ($details as $detail):
                                    $identity = trim((string) ($detail['nic'] ?? ''));
                                    $identityLabel = 'NIC';
                                    if ($identity === '') { $identity = trim((string) ($detail['elders_card_number'] ?? '')); $identityLabel = "Elders' Identity Card"; }
                                    $optical = $detail['spectacle_type'] ?? null;
                                    if ($optical !== null) $optical = 'Type: ' . t((string) $optical);
                                    $actor = widmsGoodsRequestLastActor($row);
                                    ?>
                                    <tr><td>GR-<?= str_pad((string) $row['id'], 4, '0', STR_PAD_LEFT) ?></td><td><?= htmlspecialchars((string) ($detail['beneficiary_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td><td><?= $identity !== '' ? htmlspecialchars($identityLabel . ': ' . $identity, ENT_QUOTES, 'UTF-8') : '&mdash;' ?></td><td><?= htmlspecialchars(widmsAidItemName((string) $row['item_name']), ENT_QUOTES, 'UTF-8') ?></td><td><?= $optical !== null ? htmlspecialchars((string) $optical, ENT_QUOTES, 'UTF-8') : '&mdash;' ?></td><td><?= number_format((int) ($detail['quantity'] ?? $row['quantity'])) ?></td><td><?= htmlspecialchars($row['district_name'] . ' / ' . $row['division_name'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string) ($row['approver_name'] ?: t('Administrator')), ENT_QUOTES, 'UTF-8') ?></td><td class="goods-actor-cell"><strong><?= htmlspecialchars($actor['username'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars(t($actor['role']), ENT_QUOTES, 'UTF-8') ?></small></td><td><span class="goods-status-pill status-<?= htmlspecialchars((string) $row['status'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t($labels[$row['status']] ?? $row['status']), ENT_QUOTES, 'UTF-8') ?></span></td><td><?= $row['rejection_reason'] ? nl2br(htmlspecialchars((string) $row['rejection_reason'], ENT_QUOTES, 'UTF-8')) : '&mdash;' ?></td><td><a class="outline-action" target="_blank" rel="noopener" href="dashboard.php?page=goods-request-document&amp;request_id=<?= (int) $row['id'] ?>&amp;print=1"><?= htmlspecialchars(t('View PDF'), ENT_QUOTES, 'UTF-8') ?></a></td></tr>
                                <?php endforeach; endforeach; ?></tbody>
                        </table></div></td></tr>
                    <?php endforeach; endif; ?>
                    <?php if ($beneficiaryBundles !== []): ?><tr class="reviewed-no-matches" hidden><td colspan="4" class="admin-empty-row">No requests match these filters.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>
    </main>
</div>
<script src="assets/js/admin-dashboard.js"></script>
<script>
(() => {
    const division = document.getElementById('reviewed-division-filter');
    const item = document.getElementById('reviewed-item-filter');
    const rows = [...document.querySelectorAll('.reviewed-filter-row')];
    const noMatches = document.querySelector('.reviewed-no-matches');
    const applyFilters = () => {
        let visible = 0;
        rows.forEach((row) => {
            const lines = JSON.parse(row.dataset.filterLines || '[]');
            const matches = lines.some((line) =>
                (!division.value || String(line.division) === division.value)
                && (!item.value || String(line.item) === item.value));
            row.hidden = !matches;
            if (matches) visible++;
            const details = row.dataset.detailsId && document.getElementById(row.dataset.detailsId);
            if (details) details.hidden = !matches || row.dataset.expanded !== 'true';
        });
        if (noMatches) noMatches.hidden = visible !== 0;
    };
    division?.addEventListener('change', applyFilters);
    item?.addEventListener('change', applyFilters);
    document.querySelectorAll('.reviewed-bundle-toggle').forEach((button) => {
        button.addEventListener('click', () => {
            const row = button.closest('.reviewed-bundle-row');
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
