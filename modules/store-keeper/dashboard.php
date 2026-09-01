<?php
declare(strict_types=1);

requireRole('store-keeper');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/activity.php';

$activePage = 'dashboard';
$loadError = '';
$totalStock = 0;
$itemTypeCount = 0;
$lowStockThreshold = 10;
$lowStockItems = [];
$outstandingBalance = 0.0;
$outstandingSuppliers = 0;
$itemsToDispatch = 0;
$recentActivities = [];
$pendingDispatches = [];

try {
    $summary = database()->query('SELECT COALESCE(SUM(quantity), 0) AS total_stock, COUNT(*) AS item_types FROM inventory_items')->fetch();
    $totalStock = (int) $summary['total_stock'];
    $itemTypeCount = (int) $summary['item_types'];

    $threshold = database()->query("SELECT setting_value FROM system_settings WHERE setting_key = 'low_stock_threshold' LIMIT 1")->fetchColumn();
    if ($threshold !== false) $lowStockThreshold = max(0, (int) $threshold);

    $lowStatement = database()->prepare(
        'SELECT item_name, variety, quantity FROM inventory_items WHERE quantity <= :threshold ORDER BY quantity ASC, item_name ASC'
    );
    $lowStatement->execute(['threshold' => $lowStockThreshold]);
    $lowStockItems = $lowStatement->fetchAll();

    $payment = database()->query(
        'SELECT COALESCE(SUM(r.balance_amount), 0) AS outstanding_balance,
                COUNT(DISTINCT CASE WHEN r.balance_amount > 0 THEN r.supplier_id END) AS supplier_count
         FROM stock_receipts r'
    )->fetch();
    $outstandingBalance = (float) $payment['outstanding_balance'];
    $outstandingSuppliers = (int) $payment['supplier_count'];

    $pendingDispatches = database()->query("SELECT g.id,g.quantity,i.item_name,ds.name division_name FROM goods_requests g JOIN inventory_items i ON i.id=g.item_id JOIN ds_divisions ds ON ds.id=g.destination_ds_division_id WHERE g.status='approved-awaiting-dispatch' ORDER BY g.approved_at LIMIT 6")->fetchAll();
    $itemsToDispatch = count($pendingDispatches);

    $receiptStatement = database()->prepare(
        'SELECT r.id, r.quantity, r.created_at, s.company_name, i.item_name, i.variety
         FROM stock_receipts r JOIN suppliers s ON s.id = r.supplier_id JOIN inventory_items i ON i.id = r.item_id
         WHERE r.received_by = :user_id ORDER BY r.created_at DESC LIMIT 10'
    );
    $receiptStatement->execute(['user_id' => $_SESSION['user_id']]);
    foreach ($receiptStatement->fetchAll() as $receipt) {
        $recentActivities[] = [
            'created_at' => $receipt['created_at'],
            'action' => sprintf(
                'Batch BAT-%04d recorded — %d %s%s from %s',
                $receipt['id'],
                $receipt['quantity'],
                $receipt['item_name'],
                $receipt['variety'] !== '' ? ' (' . $receipt['variety'] . ')' : '',
                $receipt['company_name']
            ),
            'by' => 'Me',
            'status' => 'Done',
        ];
    }

    $correctionStatement = database()->prepare(
        'SELECT id, record_reference, status, created_at FROM correction_requests
         WHERE submitted_by = :user_id ORDER BY created_at DESC LIMIT 10'
    );
    $correctionStatement->execute(['user_id' => $_SESSION['user_id']]);
    foreach ($correctionStatement->fetchAll() as $correction) {
        $recentActivities[] = [
            'created_at' => $correction['created_at'],
            'action' => sprintf('Correction request CR-%03d submitted for %s', $correction['id'], $correction['record_reference']),
            'by' => 'Me',
            'status' => $correction['status'] === 'approved' ? 'Done' : ucfirst($correction['status']),
        ];
    }

    usort($recentActivities, static fn(array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));
    $recentActivities = array_slice($recentActivities, 0, 5);
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $loadError = 'Unable to load dashboard information. Confirm that all database migrations are installed.';
}

$loggedActivities = recentActivities((int) $_SESSION['user_id'], 8);
if ($loggedActivities !== []) {
    $recentActivities = array_map(static fn(array $activity): array => [
        'created_at' => $activity['created_at'],
        'action' => $activity['action'],
        'by' => $activity['actor_name'],
        'status' => ucfirst($activity['status']),
    ], $loggedActivities);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Store Keeper Dashboard | WIDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css" rel="stylesheet">
</head>
<body class="store-page store-dashboard">
<?php require __DIR__ . '/../../includes/store-keeper-sidebar.php'; ?>
<div class="admin-shell">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3"><button type="button" class="menu-button" id="menu-button" aria-label="Open navigation">☰</button><h1>Dashboard</h1></div>
        <div class="topbar-actions"><label class="search-box"><span aria-hidden="true">⌕</span><input type="search" placeholder="Search anything..." aria-label="Search"></label><button class="notification-button" type="button" aria-label="Notifications">●</button></div>
    </header>
    <main class="dashboard-content">
        <?php if ($loadError !== ''): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($loadError, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <section class="stats-grid" aria-label="Store Keeper statistics">
            <article class="stat-card"><span class="stat-icon">📥</span><p>Items to Dispatch</p><strong><?= $itemsToDispatch ?></strong><small><?= $itemsToDispatch > 0 ? 'Approved, awaiting release' : 'No approved requests waiting' ?></small></article>
            <article class="stat-card"><span class="stat-icon">📦</span><p>Central Stock Items</p><strong><?= number_format($totalStock) ?></strong><small>Across <?= $itemTypeCount ?> item type<?= $itemTypeCount === 1 ? '' : 's' ?></small></article>
            <article class="stat-card"><span class="stat-icon">⚠</span><p>Low Stock Alerts</p><strong><?= count($lowStockItems) ?></strong><small class="<?= $lowStockItems !== [] ? 'negative' : 'positive' ?>"><?= $lowStockItems !== [] ? htmlspecialchars(implode(' · ', array_slice(array_column($lowStockItems, 'item_name'), 0, 2)), ENT_QUOTES, 'UTF-8') : 'All stock levels are healthy' ?></small></article>
            <article class="stat-card"><span class="stat-icon">💳</span><p>Outstanding Payments</p><strong>Rs <?= number_format($outstandingBalance, 2) ?></strong><small class="<?= $outstandingBalance > 0 ? 'negative' : 'positive' ?>"><?= $outstandingSuppliers ?> supplier<?= $outstandingSuppliers === 1 ? '' : 's' ?></small></article>
        </section>
        <section class="dashboard-grid">
            <article class="panel activity-panel">
                <div class="panel-header"><h2>📋 Recent Activity</h2></div>
                <div class="activity-table-wrap"><table class="activity-table">
                    <thead><tr><th>Time</th><th>Action</th><th>By</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if ($recentActivities === []): ?><tr><td colspan="4" class="text-center text-secondary py-4">No activity recorded yet.</td></tr>
                    <?php else: foreach ($recentActivities as $activity): ?>
                        <tr><td><?= date('d M, H:i', strtotime($activity['created_at'])) ?></td><td><?= htmlspecialchars($activity['action'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($activity['by'], ENT_QUOTES, 'UTF-8') ?></td><td><span class="status <?= strtolower($activity['status']) ?>"><?= htmlspecialchars($activity['status'], ENT_QUOTES, 'UTF-8') ?></span></td></tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table></div>
            </article>
            <article class="panel attention-panel">
                <div class="panel-header"><h2>⚠ Items Requiring Attention</h2></div>
                <div class="attention-content">
                    <h3>Low Stock Alerts</h3>
                    <?php if ($lowStockItems === []): ?><p class="dashboard-empty-state">No low-stock items.</p>
                    <?php else: ?><ul class="low-stock-list"><?php foreach ($lowStockItems as $stockItem): ?><li><span><b><?= htmlspecialchars($stockItem['item_name'], ENT_QUOTES, 'UTF-8') ?></b><?= $stockItem['variety'] !== '' ? ' — ' . htmlspecialchars($stockItem['variety'], ENT_QUOTES, 'UTF-8') : '' ?><small>Only <?= (int) $stockItem['quantity'] ?> remaining · minimum threshold: <?= $lowStockThreshold ?></small></span><em>Low</em></li><?php endforeach; ?></ul><?php endif; ?>
                    <h3 class="dispatch-heading">Pending Dispatches</h3>
                    <?php if ($pendingDispatches === []): ?><p class="dashboard-empty-state">No approved requests are waiting for dispatch.</p><?php else:?><ul class="low-stock-list"><?php foreach($pendingDispatches as $request):?><li><span><b>GR-<?=str_pad((string)$request['id'],4,'0',STR_PAD_LEFT)?> · <?=htmlspecialchars($request['item_name'],ENT_QUOTES,'UTF-8')?></b><small><?=htmlspecialchars($request['division_name'],ENT_QUOTES,'UTF-8')?> · Qty <?=(int)$request['quantity']?></small></span><em>Ready</em></li><?php endforeach;?></ul><?php endif; ?>
                </div>
            </article>
        </section>
    </main>
</div>
<script src="assets/js/admin-dashboard.js"></script>
</body>
</html>
