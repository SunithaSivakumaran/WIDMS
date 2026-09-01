<?php
declare(strict_types=1);

requireRole('store-keeper');
require_once __DIR__ . '/../../config/database.php';

$activePage = 'current-stock';
$stockItems = [];
$loadError = '';
$lowStockThreshold = 10;

try {
    $thresholdStatement = database()->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'low_stock_threshold' LIMIT 1");
    $thresholdStatement->execute();
    $configuredThreshold = $thresholdStatement->fetchColumn();
    if ($configuredThreshold !== false) {
        $lowStockThreshold = max(0, (int) $configuredThreshold);
    }

    $stockItems = database()->query(
        "SELECT i.id, i.item_name, i.category, i.variety, i.quantity,
                COUNT(r.id) AS receipt_count,
                COALESCE(SUM(r.paid_amount), 0) AS total_paid,
                COALESCE(SUM(r.balance_amount), 0) AS total_balance
         FROM inventory_items i
         LEFT JOIN stock_receipts r ON r.item_id = i.id
         GROUP BY i.id, i.item_name, i.category, i.variety, i.quantity
         ORDER BY i.item_name, i.variety"
    )->fetchAll();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $loadError = 'Unable to load current stock. Import database/migration_current_stock.sql first.';
}

function inventoryPaymentStatus(array $item): string
{
    if ((int) $item['receipt_count'] === 0) return 'no-receipts';
    if ((float) $item['total_balance'] <= 0) return 'fully-paid';
    if ((float) $item['total_paid'] <= 0) return 'unpaid';
    return 'partially-paid';
}

$paymentLabels = [
    'fully-paid' => 'Fully Paid',
    'partially-paid' => 'Partially Paid',
    'unpaid' => 'Outstanding — Not Yet Paid',
    'no-receipts' => 'No Receipts Yet',
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Current Stock | WIDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css" rel="stylesheet">
</head>
<body class="store-page store-stock-page">
<?php require __DIR__ . '/../../includes/store-keeper-sidebar.php'; ?>
<div class="admin-shell">
    <header class="topbar"><div class="d-flex align-items-center gap-3"><button type="button" class="menu-button" id="menu-button" aria-label="Open navigation">☰</button><h1>Current Stock</h1></div><div class="topbar-actions"><button class="notification-button" type="button" aria-label="Notifications">●</button></div></header>
    <main class="dashboard-content current-stock-page">
        <?php if ($loadError !== ''): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($loadError, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <section class="current-stock-card">
            <div class="current-stock-header"><h2>Central Stock Inventory</h2></div>
            <div class="current-stock-table-wrap"><table class="current-stock-table">
                <thead><tr><th>Item</th><th>Category</th><th>Variety</th><th>In Stock</th><th>Payment Status</th><th>Alert</th></tr></thead>
                <tbody>
                <?php if ($stockItems === []): ?><tr><td colspan="6" class="empty-table">No inventory items found.</td></tr>
                <?php else: foreach ($stockItems as $item): $paymentStatus = inventoryPaymentStatus($item); $isLow = (int) $item['quantity'] <= $lowStockThreshold; ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                        <td><?= htmlspecialchars($item['category'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($item['variety'] ?: 'Standard', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><strong><?= (int) $item['quantity'] ?></strong></td>
                        <td><span class="payment-badge <?= $paymentStatus ?>"><?= htmlspecialchars($paymentLabels[$paymentStatus], ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td><?= $isLow ? '<span class="low-alert">⚠ Low</span>' : '—' ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table></div>
            <p class="stock-table-note">ⓘ Stock quantity and payment status are independent. An item remains available for distribution while payment is outstanding. Low stock threshold: <?= $lowStockThreshold ?>.</p>
        </section>
    </main>
</div>
<script src="assets/js/admin-dashboard.js"></script>
</body></html>
