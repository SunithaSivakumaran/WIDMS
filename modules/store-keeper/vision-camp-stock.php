<?php
declare(strict_types=1);

requireRole('store-keeper');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/spectacle-camps.php';

$activePage = 'vision-camp-stock';
$campRows = [];
$districts = [];
$loadError = '';

try {
    $db = database();
    $camps = $db->query("SELECT c.id, c.district_id, c.ds_division_id, c.completed_at,
            d.name AS district_name, ds.name AS division_name
        FROM spectacle_camps c
        JOIN districts d ON d.id = c.district_id
        JOIN ds_divisions ds ON ds.id = c.ds_division_id
        WHERE c.status = 'completed' AND c.conducted_at IS NOT NULL
        ORDER BY c.completed_at DESC, c.id DESC")->fetchAll(PDO::FETCH_ASSOC);

    $selectedStatement = $db->prepare("SELECT spectacle_category_id, COUNT(*) AS quantity
        FROM spectacle_camp_participants
        WHERE camp_id = ? AND status = 'approved' AND spectacle_category_id IS NOT NULL
        GROUP BY spectacle_category_id");
    foreach ($camps as $camp) {
        $campId = (int) $camp['id'];
        $districts[(int) $camp['district_id']] = (string) $camp['district_name'];
        $selectedStatement->execute([$campId]);
        $selected = [];
        foreach ($selectedStatement->fetchAll(PDO::FETCH_ASSOC) as $type) {
            $selected[(int) $type['spectacle_category_id']] = (int) $type['quantity'];
        }
        $balances = scBalances($db, $campId);
        $typeIds = array_unique(array_merge(array_keys($selected), array_keys($balances)));
        $received = 0;
        $store = 0;
        $reserved = 0;
        $released = 0;
        $awaiting = 0;
        foreach ($typeIds as $typeId) {
            $balance = $balances[$typeId] ?? [];
            $neededForType = (int) ($selected[$typeId] ?? 0);
            $receivedForType = (int) ($balance['received'] ?? 0);
            $received += $receivedForType;
            $store += (int) ($balance['store'] ?? 0);
            $reserved += (int) ($balance['reserved'] ?? 0);
            $released += (int) ($balance['released'] ?? 0);
            $awaiting += max(0, $neededForType - $receivedForType);
        }
        $status = $awaiting > 0
            ? ($received > 0 ? 'partial' : 'awaiting')
            : ($store > 0 ? 'in-stock' : ($released > 0 ? 'released' : 'none'));
        $campRows[] = [
            'camp' => $camp,
            'awaiting' => $awaiting,
            'reserved' => $reserved,
            'status' => $status,
        ];
    }
    asort($districts, SORT_NATURAL | SORT_FLAG_CASE);
} catch (Throwable $exception) {
    error_log('Unable to load Vision Camp stock: ' . $exception->getMessage());
    $campRows = [];
    $loadError = 'Unable to load Vision Camp stock.';
}

function campStockEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$statusLabels = [
    'awaiting' => 'Awaiting receipt',
    'partial' => 'Partially received',
    'in-stock' => 'In stock',
    'released' => 'Released',
    'none' => 'No stock required',
];
?>
<!doctype html>
<html lang="<?= campStockEscape(widmsLanguage()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= campStockEscape(t('Vision Camp Stock')) ?> | SWPCS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/admin-dashboard.css') ?>" rel="stylesheet">
    <link href="assets/css/vision-camp-stock.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/vision-camp-stock.css') ?>" rel="stylesheet">
</head>
<body class="store-page store-stock-page widms-unified-ui">
<?php require __DIR__ . '/../../includes/store-keeper-sidebar.php'; ?>
<div class="admin-shell">
    <header class="topbar"><div class="d-flex align-items-center gap-3"><button type="button" class="menu-button" id="menu-button" aria-label="<?= campStockEscape(t('Open navigation')) ?>">&#9776;</button><h1><?= campStockEscape(t('Vision Camp Stock')) ?></h1></div></header>
    <main class="dashboard-content admin-operation-page camp-stock-page">
        <?php if ($loadError !== ''): ?><div class="alert alert-danger" role="alert"><?= campStockEscape($loadError) ?></div><?php endif; ?>
        <section class="admin-data-card">
            <div class="receipt-history-filter-bar camp-stock-filters" role="search" aria-label="Camp stock filters">
                <label class="receipt-filter-field"><span><?= campStockEscape(t('Search')) ?></span><input id="camp-stock-search" type="search" placeholder="<?= campStockEscape(t('Search camp or location')) ?>" autocomplete="off"></label>
                <label class="receipt-filter-field"><span><?= campStockEscape(t('District')) ?></span><select id="camp-stock-district"><option value=""><?= campStockEscape(t('All districts')) ?></option><?php foreach ($districts as $id => $name): ?><option value="<?= (int) $id ?>"><?= campStockEscape($name) ?></option><?php endforeach; ?></select></label>
                <label class="receipt-filter-field"><span><?= campStockEscape(t('Stock status')) ?></span><select id="camp-stock-status"><option value=""><?= campStockEscape(t('All statuses')) ?></option><?php foreach ($statusLabels as $status => $label): ?><option value="<?= campStockEscape($status) ?>"><?= campStockEscape(t($label)) ?></option><?php endforeach; ?></select></label>
                <button type="button" class="outline-action receipt-clear-filters" id="camp-stock-clear" hidden><?= campStockEscape(t('Clear filters')) ?></button>
            </div>
            <div class="admin-data-table-wrap store-table-card camp-stock-table-wrap">
                <table class="admin-data-table camp-stock-table">
                    <thead><tr><th><?= campStockEscape(t('Camp / Location')) ?></th><th><?= campStockEscape(t('Stock Status')) ?></th><th><?= campStockEscape(t('Action')) ?></th></tr></thead>
                    <tbody>
                    <?php if ($campRows === []): ?><tr><td colspan="3" class="admin-empty-row"><?= campStockEscape(t('No completed Vision Camps found.')) ?></td></tr>
                    <?php else: foreach ($campRows as $row): $camp = $row['camp']; ?>
                        <tr data-camp-stock-row data-district="<?= (int) $camp['district_id'] ?>" data-stock-status="<?= campStockEscape($row['status']) ?>">
                            <td class="camp-stock-location"><strong>VC-<?= (int) $camp['id'] ?></strong><span><?= campStockEscape($camp['division_name']) ?></span><small><?= campStockEscape($camp['district_name']) ?></small></td>
                            <td><span class="camp-stock-badge camp-stock-<?= campStockEscape($row['status']) ?>"><?= campStockEscape(t($statusLabels[$row['status']])) ?></span></td>
                            <td class="camp-stock-actions"><?php if ($row['awaiting'] > 0): ?><a class="outline-action" href="dashboard.php?page=receive-items&amp;camp_id=<?= (int) $camp['id'] ?>"><?= campStockEscape(t('Receive stock')) ?></a><?php endif; ?><?php if ($row['reserved'] > 0): ?><a class="outline-action" href="dashboard.php?page=spectacle-camp-releases"><?= campStockEscape(t('Release request')) ?></a><?php endif; ?><?php if ($row['awaiting'] === 0 && $row['reserved'] === 0): ?>—<?php endif; ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    <?php if ($campRows !== []): ?><tr id="camp-stock-filter-empty" hidden><td colspan="3" class="admin-empty-row"><?= campStockEscape(t('No camp stock matches these filters.')) ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
<script src="assets/js/admin-dashboard.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const search = document.getElementById('camp-stock-search');
    const district = document.getElementById('camp-stock-district');
    const status = document.getElementById('camp-stock-status');
    const clear = document.getElementById('camp-stock-clear');
    const empty = document.getElementById('camp-stock-filter-empty');
    const rows = [...document.querySelectorAll('[data-camp-stock-row]')];
    const apply = () => {
        const term = search.value.trim().toLocaleLowerCase();
        let visible = 0;
        for (const row of rows) {
            row.hidden = (district.value !== '' && row.dataset.district !== district.value)
                || (status.value !== '' && row.dataset.stockStatus !== status.value)
                || (term !== '' && !row.textContent.toLocaleLowerCase().includes(term));
            if (!row.hidden) visible++;
        }
        if (empty) empty.hidden = visible !== 0;
        clear.hidden = term === '' && district.value === '' && status.value === '';
    };
    search.addEventListener('input', apply);
    district.addEventListener('change', apply);
    status.addEventListener('change', apply);
    clear.addEventListener('click', () => { search.value = ''; district.value = ''; status.value = ''; apply(); });
});
</script>
</body>
</html>
