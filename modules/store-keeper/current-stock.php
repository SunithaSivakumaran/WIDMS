<?php
declare(strict_types=1);

if (!in_array((string) ($_SESSION['role'] ?? ''), ['store-keeper', 'subject-officer', 'admin'], true)) {
    http_response_code(403);
    exit('You do not have permission to access this page.');
}
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/payment-status-control.php';
require_once __DIR__ . '/../../includes/payment-status-guide.php';

$activePage = 'current-stock';
$stockItems = [];
$loadError = '';
$lowStockThreshold = 10;

try {
    $thresholdStatement = database()->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'low_stock_threshold' LIMIT 1");
    $thresholdStatement->execute();
    $configuredThreshold = $thresholdStatement->fetchColumn();
    if ($configuredThreshold !== false) $lowStockThreshold = max(0, (int) $configuredThreshold);

    $stockItems = database()->query(
        "SELECT i.id, i.item_name, i.quantity,
                COUNT(r.id) AS receipt_count,
                GROUP_CONCAT(CASE WHEN r.power IS NOT NULL THEN CONCAT(IF(r.power >= 0, '+', ''), FORMAT(r.power, 2), ' × ', r.quantity) END ORDER BY r.power SEPARATOR ', ') AS power_summary,
                COALESCE(SUM(r.paid_amount), 0) AS total_paid,
                COALESCE(SUM(r.balance_amount), 0) AS total_balance
         FROM inventory_items i
         LEFT JOIN stock_receipts r ON r.item_id = i.id
         GROUP BY i.id, i.item_name, i.quantity
         ORDER BY i.item_name"
    )->fetchAll();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $loadError = t('Unable to load current stock. Import database/migration_current_stock.sql first.');
}

function inventoryPaymentStatus(array $item): string
{
    if ((int) $item['receipt_count'] === 0) return 'no-receipts';
    if ((float) $item['total_balance'] <= 0) return 'fully-paid';
    if ((float) $item['total_paid'] <= 0) return 'unpaid';
    return 'partially-paid';
}

function inventoryStockStatus(array $item, int $threshold): string
{
    $quantity = (int) $item['quantity'];
    if ($quantity <= 0) return 'out-of-stock';
    if ($quantity <= $threshold) return 'low-stock';
    return 'healthy';
}

$paymentLabels = [
    'fully-paid' => t('Fully Paid'),
    'partially-paid' => t('Partially Paid'),
    'unpaid' => t('Outstanding — Not Yet Paid'),
    'no-receipts' => t('No Receipts Yet'),
];
$stockStatusLabels = [
    'healthy' => t('Healthy'),
    'low-stock' => t('Low Stock'),
    'out-of-stock' => t('Out of Stock'),
];
$aidOptions = array_values(array_unique(array_map(static fn(array $item): string => (string) $item['item_name'], $stockItems)));
natcasesort($aidOptions);
$aidOptions = array_values($aidOptions);
?>
<!doctype html>
<html lang="<?= htmlspecialchars(widmsLanguage(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(t('Current Stock'), ENT_QUOTES, 'UTF-8') ?> | WIDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css?v=43" rel="stylesheet">
</head>
<body class="store-page store-stock-page widms-unified-ui">
<?php if (hasRole('admin')): ?>
    <?php require __DIR__ . '/../../includes/admin-sidebar.php'; ?>
<?php elseif (hasRole('subject-officer')): ?>
    <?php require __DIR__ . '/../../includes/subject-officer-sidebar.php'; ?>
<?php else: ?>
    <?php require __DIR__ . '/../../includes/store-keeper-sidebar.php'; ?>
<?php endif; ?>
<div class="admin-shell">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button type="button" class="menu-button" id="menu-button" aria-label="<?= htmlspecialchars(t('Open navigation'), ENT_QUOTES, 'UTF-8') ?>">&#9776;</button>
            <h1><?= htmlspecialchars(t('Current Stock'), ENT_QUOTES, 'UTF-8') ?></h1>
        </div>
    </header>
    <main class="dashboard-content admin-operation-page">
        <?php if ($loadError !== ''): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($loadError, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <section class="admin-data-card">
            <?= renderPaymentStatusGuide($paymentLabels, t('Payment status guide')) ?>
            <div class="receipt-history-filter-bar current-stock-filter-bar" role="search" aria-label="<?= htmlspecialchars(t('Stock filters'), ENT_QUOTES, 'UTF-8') ?>">
                <label class="receipt-filter-field">
                    <span><?= htmlspecialchars(t('Stock Status'), ENT_QUOTES, 'UTF-8') ?></span>
                    <select id="current-stock-status-filter" aria-label="<?= htmlspecialchars(t('Filter by stock status'), ENT_QUOTES, 'UTF-8') ?>">
                        <option value=""><?= htmlspecialchars(t('All stock statuses'), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php foreach ($stockStatusLabels as $statusKey => $statusLabel): ?><option value="<?= htmlspecialchars($statusKey, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label class="receipt-filter-field">
                    <span><?= htmlspecialchars(t('Aid Type'), ENT_QUOTES, 'UTF-8') ?></span>
                    <select id="current-stock-aid-filter" aria-label="<?= htmlspecialchars(t('Filter by aid'), ENT_QUOTES, 'UTF-8') ?>">
                        <option value=""><?= htmlspecialchars(t('All aids'), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php foreach ($aidOptions as $aidOption): ?><option value="<?= htmlspecialchars($aidOption, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($aidOption, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                    </select>
                </label>
                <button type="button" class="outline-action receipt-clear-filters" id="current-stock-clear-filters" hidden><?= htmlspecialchars(t('Clear filters'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
            <div class="admin-data-table-wrap store-table-card">
                <table class="admin-data-table current-stock-table">
                    <thead><tr><th><?= htmlspecialchars(t('Aid'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('In Stock'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Stock Status'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Payment Summary'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Batch Details'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead>
                    <tbody>
                    <?php if ($stockItems === []): ?><tr><td colspan="5" class="admin-empty-row"><?= htmlspecialchars(t('No inventory items found.'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                    <?php else: foreach ($stockItems as $item): $paymentStatus = inventoryPaymentStatus($item); $stockStatus = inventoryStockStatus($item, $lowStockThreshold); ?>
                        <tr data-aid-name="<?= htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8') ?>" data-stock-status="<?= htmlspecialchars($stockStatus, ENT_QUOTES, 'UTF-8') ?>">
                            <td><strong><?= htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td><strong><?= (int) $item['quantity'] ?></strong></td>
                            <td><span class="stock-status-badge <?= htmlspecialchars($stockStatus, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($stockStatusLabels[$stockStatus], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= renderPaymentStatusControl($paymentStatus, t('View Batch Payment Summary'), 'dashboard.php?page=receipt-history&from=current-stock&aid=' . rawurlencode((string) $item['item_name']), t('View batch payment summary for ') . $item['item_name'] . ' — ' . $paymentLabels[$paymentStatus], 'stock-payment-history-button') ?></td>
                            <td><?php if ((int) $item['receipt_count'] > 0): ?><button type="button" class="outline-action stock-power-history-button" data-power-history data-item-id="<?= (int) $item['id'] ?>"><?= htmlspecialchars(t('View batches'), ENT_QUOTES, 'UTF-8') ?></button><?php else: ?>—<?php endif; ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    <?php if ($stockItems !== []): ?><tr id="current-stock-filter-empty" hidden><td colspan="5" class="admin-empty-row"><?= htmlspecialchars(t('No stock items match the selected filter.'), ENT_QUOTES, 'UTF-8') ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
<script src="assets/js/admin-dashboard.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const stockStatusFilter = document.getElementById('current-stock-status-filter');
    const stockAidFilter = document.getElementById('current-stock-aid-filter');
    const stockClearFilters = document.getElementById('current-stock-clear-filters');
    const stockRows = Array.from(document.querySelectorAll('.current-stock-table tbody tr[data-stock-status]'));
    const stockFilterEmpty = document.getElementById('current-stock-filter-empty');
    const applyStockFilters = () => {
        const selectedStatus = stockStatusFilter?.value || '';
        const selectedAid = stockAidFilter?.value || '';
        const searchTerm = document.querySelector('.topbar .search-box input')?.value.trim().toLowerCase() || '';
        let visibleRows = 0;
        stockRows.forEach(row => {
            const matchesStatus = selectedStatus === '' || row.dataset.stockStatus === selectedStatus;
            const matchesAid = selectedAid === '' || row.dataset.aidName === selectedAid;
            const matchesSearch = searchTerm === '' || row.textContent.toLowerCase().includes(searchTerm);
            row.hidden = !(matchesStatus && matchesAid && matchesSearch);
            if (!row.hidden) visibleRows += 1;
        });
        if (stockFilterEmpty) stockFilterEmpty.hidden = visibleRows > 0 || stockRows.length === 0;
        if (stockClearFilters) stockClearFilters.hidden = selectedStatus === '' && selectedAid === '' && searchTerm === '';
    };
    stockStatusFilter?.addEventListener('change', applyStockFilters);
    stockAidFilter?.addEventListener('change', applyStockFilters);
    document.querySelector('.topbar')?.addEventListener('input', event => {
        if (event.target.matches('.search-box input')) applyStockFilters();
    });
    stockClearFilters?.addEventListener('click', () => {
        stockStatusFilter.value = '';
        stockAidFilter.value = '';
        const searchInput = document.querySelector('.topbar .search-box input');
        if (searchInput) searchInput.value = '';
        applyStockFilters();
    });
    applyStockFilters();

    document.querySelectorAll('[data-stock-history]').forEach(button => button.addEventListener('click', () => {
        const itemId = Number(button.dataset.itemId);
        if (!itemId) return;
        if (window.openWidmsDataModal) {
            window.openWidmsDataModal(`dashboard.php?page=item-payment-details&item_id=${encodeURIComponent(itemId)}`, { modalClass: 'history-modal stock-item-history-modal', errorMessage: 'Payment history could not be loaded.' });
            return;
        }
        fetch(`dashboard.php?page=item-payment-details&item_id=${encodeURIComponent(itemId)}`)
            .then(response => { if (!response.ok) throw new Error(); return response.text(); })
            .then(html => {
                const card = new DOMParser().parseFromString(html, 'text/html').querySelector('.admin-data-card');
                const modal = document.createElement('div');
                modal.className = 'history-modal stock-item-history-modal';
                modal.innerHTML = '<div class="history-modal-card"><button type="button" class="history-modal-close" aria-label="Close">×</button></div>';
                if (card) modal.querySelector('.history-modal-card').append(card);
                document.body.append(modal);
                modal.querySelector('.history-modal-close').addEventListener('click', () => modal.remove());
                modal.addEventListener('click', event => { if (event.target === modal) modal.remove(); });
            })
            .catch(() => {
                const modal = document.createElement('div');
                modal.className = 'history-modal stock-item-history-modal';
                modal.innerHTML = '<div class="history-modal-card"><button type="button" class="history-modal-close" aria-label="Close">×</button><p>Payment history could not be loaded.</p></div>';
                document.body.append(modal);
                modal.querySelector('.history-modal-close').addEventListener('click', () => modal.remove());
            });
    }));

    document.querySelectorAll('[data-power-history]').forEach(button => button.addEventListener('click', () => {
        const itemId = Number(button.dataset.itemId);
        if (!itemId) return;
        if (window.openWidmsDataModal) {
            window.openWidmsDataModal(`dashboard.php?page=item-power-details&item_id=${encodeURIComponent(itemId)}`, { modalClass: 'history-modal stock-power-history-modal', errorMessage: 'Power batch details could not be loaded.' });
            return;
        }
        fetch(`dashboard.php?page=item-power-details&item_id=${encodeURIComponent(itemId)}`)
            .then(response => { if (!response.ok) throw new Error(); return response.text(); })
            .then(html => {
                const card = new DOMParser().parseFromString(html, 'text/html').querySelector('.admin-data-card');
                const modal = document.createElement('div');
                modal.className = 'history-modal stock-power-history-modal';
                modal.innerHTML = '<div class="history-modal-card"><button type="button" class="history-modal-close" aria-label="Close">×</button></div>';
                if (card) modal.querySelector('.history-modal-card').append(card);
                document.body.append(modal);
                modal.querySelector('.history-modal-close').addEventListener('click', () => modal.remove());
                modal.addEventListener('click', event => { if (event.target === modal) modal.remove(); });
            })
            .catch(() => {
                const modal = document.createElement('div');
                modal.className = 'history-modal stock-power-history-modal';
                modal.innerHTML = '<div class="history-modal-card"><button type="button" class="history-modal-close" aria-label="Close">×</button><p>Power batch details could not be loaded.</p></div>';
                document.body.append(modal);
                modal.querySelector('.history-modal-close').addEventListener('click', () => modal.remove());
            });
    }));
});
</script>
</body>
</html>
