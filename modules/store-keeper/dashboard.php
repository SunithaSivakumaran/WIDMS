<?php
declare(strict_types=1);

requireRole('store-keeper');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/inventory-pie-chart.php';

$activePage = 'dashboard';
$loadError = '';
$totalStock = 0;
$itemTypeCount = 0;
$lowStockThreshold = 10;
$lowStockItems = [];
$outstandingBalance = 0.0;
$outstandingSuppliers = 0;
$itemsToDispatch = 0;
$pendingDispatches = [];
$stockChartRows = [];

try {
    $summary = database()->query('SELECT COALESCE(SUM(quantity), 0) AS total_stock, COUNT(*) AS item_types FROM inventory_items')->fetch();
    $totalStock = (int) $summary['total_stock'];
    $itemTypeCount = (int) $summary['item_types'];

    $stockChartRows = database()->query(
        'SELECT item_name AS name, SUM(GREATEST(quantity, 0)) AS quantity
         FROM inventory_items
         GROUP BY item_name
         HAVING SUM(GREATEST(quantity, 0)) > 0
         ORDER BY quantity DESC, item_name ASC'
    )->fetchAll();

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

    $itemsToDispatch = (int) database()->query(
        "SELECT (SELECT COUNT(*) FROM goods_requests WHERE status='approved-awaiting-dispatch')
              + (SELECT COUNT(*) FROM admin_direct_releases adr
                 JOIN aid_requests ar ON ar.id=adr.aid_request_id
                 WHERE adr.status='awaiting-store-keeper' AND ar.status='approved')"
    )->fetchColumn();
    $pendingDispatches = database()->query(
        "SELECT g.id, g.quantity, i.item_name, ds.name AS division_name,
                'quota' AS request_kind, g.approved_at AS queued_at
         FROM goods_requests g
         JOIN inventory_items i ON i.id=g.item_id
         JOIN ds_divisions ds ON ds.id=g.destination_ds_division_id
         WHERE g.status='approved-awaiting-dispatch'
         UNION ALL
         SELECT ar.id, ar.quantity, i.item_name, ds.name AS division_name,
                'direct' AS request_kind, adr.created_at AS queued_at
         FROM admin_direct_releases adr
         JOIN aid_requests ar ON ar.id=adr.aid_request_id
         JOIN inventory_items i ON i.id=ar.item_id
         JOIN beneficiaries b ON b.id=ar.beneficiary_id
         JOIN ds_divisions ds ON ds.id=b.ds_division_id
         WHERE adr.status='awaiting-store-keeper' AND ar.status='approved'
         ORDER BY queued_at ASC LIMIT 6"
    )->fetchAll();

} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $loadError = 'Unable to load dashboard information. Confirm that all database migrations are installed.';
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(t('Store Keeper Dashboard'), ENT_QUOTES, 'UTF-8') ?> | SWPCS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/admin-dashboard.css') ?>" rel="stylesheet">
</head>
<body class="store-page store-dashboard">
<?php require __DIR__ . '/../../includes/store-keeper-sidebar.php'; ?>
<div class="admin-shell">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3"><button type="button" class="menu-button" id="menu-button" aria-label="<?= htmlspecialchars(t('Open navigation'), ENT_QUOTES, 'UTF-8') ?>">☰</button><h1><?= htmlspecialchars(t('Dashboard'), ENT_QUOTES, 'UTF-8') ?></h1></div>
        <div class="topbar-actions"><label class="search-box"><span aria-hidden="true">⌕</span><input type="search" placeholder="<?= htmlspecialchars(t('Search anything...'), ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars(t('Search'), ENT_QUOTES, 'UTF-8') ?>"></label><button class="notification-button" type="button" aria-label="<?= htmlspecialchars(t('Notifications'), ENT_QUOTES, 'UTF-8') ?>">●</button></div>
    </header>
    <main class="dashboard-content">
        <?php if ($loadError !== ''): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($loadError, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <section class="stats-grid" aria-label="<?= htmlspecialchars(t('Store Keeper statistics'), ENT_QUOTES, 'UTF-8') ?>">
            <a class="stat-card stat-card-link" href="dashboard.php?page=approved-dispatches"><span class="stat-icon">📥</span><p><?= htmlspecialchars(t('Dispatch Requests'), ENT_QUOTES, 'UTF-8') ?></p><strong><?= $itemsToDispatch ?></strong><small><?= htmlspecialchars(t($itemsToDispatch > 0 ? 'Requests awaiting release' : 'No dispatch requests are waiting.'), ENT_QUOTES, 'UTF-8') ?></small><span class="stat-card-action"><?=htmlspecialchars(t('Open Dispatch Requests'),ENT_QUOTES,'UTF-8')?> <span aria-hidden="true">&#8594;</span></span></a>
            <a class="stat-card stat-card-link" href="dashboard.php?page=current-stock"><span class="stat-icon">📦</span><p><?= htmlspecialchars(t('Central Stock Items'), ENT_QUOTES, 'UTF-8') ?></p><strong><?= number_format($totalStock) ?></strong><small><?= htmlspecialchars(t('Across'), ENT_QUOTES, 'UTF-8') ?> <?= $itemTypeCount ?> <?= htmlspecialchars(t($itemTypeCount === 1 ? 'item type' : 'item types'), ENT_QUOTES, 'UTF-8') ?></small><span class="stat-card-action"><?=htmlspecialchars(t('View details'),ENT_QUOTES,'UTF-8')?> <span aria-hidden="true">&#8594;</span></span></a>
            <a class="stat-card stat-card-link" href="dashboard.php?page=current-stock"><span class="stat-icon">⚠</span><p><?= htmlspecialchars(t('Low Stock Alerts'), ENT_QUOTES, 'UTF-8') ?></p><strong><?= count($lowStockItems) ?></strong><small class="<?= $lowStockItems !== [] ? 'negative' : 'positive' ?>"><?= $lowStockItems !== [] ? htmlspecialchars(implode(' · ', array_slice(array_column($lowStockItems, 'item_name'), 0, 2)), ENT_QUOTES, 'UTF-8') : htmlspecialchars(t('All stock levels are healthy'), ENT_QUOTES, 'UTF-8') ?></small><span class="stat-card-action"><?=htmlspecialchars(t('View details'),ENT_QUOTES,'UTF-8')?> <span aria-hidden="true">&#8594;</span></span></a>
            <article class="stat-card"><span class="stat-icon">💳</span><p><?= htmlspecialchars(t('Outstanding Payments'), ENT_QUOTES, 'UTF-8') ?></p><strong>Rs <?= number_format($outstandingBalance, 2) ?></strong><small class="<?= $outstandingBalance > 0 ? 'negative' : 'positive' ?>"><?= $outstandingSuppliers ?> <?= htmlspecialchars(t($outstandingSuppliers === 1 ? 'supplier' : 'suppliers'), ENT_QUOTES, 'UTF-8') ?></small></article>
        </section>
        <section class="dashboard-grid">
            <article class="panel inventory-pie-panel store-stock-pie-panel">
                <div class="panel-header">
                    <div>
                        <h2>&#128230; <?= htmlspecialchars(t('Central Stock Composition'), ENT_QUOTES, 'UTF-8') ?></h2>
                        <p><?= htmlspecialchars(t('Current stock by aid item'), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <a href="dashboard.php?page=current-stock" class="outline-action"><?= htmlspecialchars(t('Full View'), ENT_QUOTES, 'UTF-8') ?></a>
                </div>
                <?php renderInventoryPieChart(
                    $stockChartRows,
                    t('Central Stock Composition'),
                    t('No goods are currently available in Central Stock.')
                ); ?>
            </article>
            <article class="panel attention-panel">
                <div class="panel-header attention-panel-header">
                    <h2><span class="attention-title-icon" aria-hidden="true">!</span> <?= htmlspecialchars(t('Items Requiring Attention'), ENT_QUOTES, 'UTF-8') ?></h2>
                    <span class="attention-total-count"><?= count($lowStockItems) + $itemsToDispatch ?></span>
                </div>
                <div class="attention-content">
                    <section class="attention-group low-stock-group">
                        <div class="attention-group-heading">
                            <span><span class="attention-group-icon" aria-hidden="true">&#8595;</span> <?= htmlspecialchars(t('Low Stock Alerts'), ENT_QUOTES, 'UTF-8') ?></span>
                            <strong><?= count($lowStockItems) ?></strong>
                        </div>
                        <?php if ($lowStockItems === []): ?>
                            <div class="attention-empty-state"><span aria-hidden="true">&#10003;</span><div><strong><?= htmlspecialchars(t('Stock levels are healthy'), ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars(t('No low-stock items.'), ENT_QUOTES, 'UTF-8') ?></small></div></div>
                        <?php else: ?>
                            <ul class="attention-item-list">
                                <?php foreach ($lowStockItems as $stockItem): ?>
                                    <li class="attention-item is-low">
                                        <span class="attention-item-symbol" aria-hidden="true">&#128230;</span>
                                        <span class="attention-item-copy">
                                            <b><?= htmlspecialchars(widmsAidItemName((string)$stockItem['item_name']), ENT_QUOTES, 'UTF-8') ?><?= $stockItem['variety'] !== '' ? ' — ' . htmlspecialchars($stockItem['variety'], ENT_QUOTES, 'UTF-8') : '' ?></b>
                                            <small><?= htmlspecialchars(t('Available'), ENT_QUOTES, 'UTF-8') ?>: <strong><?= (int) $stockItem['quantity'] ?></strong> · <?= htmlspecialchars(t('Minimum'), ENT_QUOTES, 'UTF-8') ?>: <?= $lowStockThreshold ?></small>
                                        </span>
                                        <em class="attention-badge danger"><?= htmlspecialchars(t('Low'), ENT_QUOTES, 'UTF-8') ?></em>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </section>

                    <section class="attention-group dispatch-group">
                        <div class="attention-group-heading">
                            <span><span class="attention-group-icon dispatch" aria-hidden="true">&#8594;</span> <?= htmlspecialchars(t('Dispatch Requests'), ENT_QUOTES, 'UTF-8') ?></span>
                            <a class="attention-dispatch-link" href="dashboard.php?page=approved-dispatches"><?= htmlspecialchars(t('View All'), ENT_QUOTES, 'UTF-8') ?> &#8594;</a>
                            <strong><?= $itemsToDispatch ?></strong>
                        </div>
                        <?php if ($pendingDispatches === []): ?>
                            <div class="attention-empty-state"><span aria-hidden="true">&#10003;</span><div><strong><?= htmlspecialchars(t('All caught up'), ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars(t('No approved requests are waiting for dispatch.'), ENT_QUOTES, 'UTF-8') ?></small></div></div>
                        <?php else: ?>
                            <ul class="attention-item-list">
                                <?php foreach ($pendingDispatches as $request): ?>
                                    <li class="attention-item is-ready">
                                        <span class="attention-item-symbol" aria-hidden="true">&#128666;</span>
                                        <span class="attention-item-copy"><b><?= $request['request_kind'] === 'direct' ? 'AR-' : 'GR-' ?><?=str_pad((string)$request['id'],4,'0',STR_PAD_LEFT)?> · <?=htmlspecialchars(widmsAidItemName((string)$request['item_name']),ENT_QUOTES,'UTF-8')?></b><small><?=htmlspecialchars($request['division_name'],ENT_QUOTES,'UTF-8')?> · <?= htmlspecialchars(t('Qty'), ENT_QUOTES, 'UTF-8') ?> <?=(int)$request['quantity']?></small></span>
                                        <em class="attention-badge ready"><?= htmlspecialchars(t('Ready'), ENT_QUOTES, 'UTF-8') ?></em>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </section>
                </div>
            </article>
        </section>
    </main>
</div>
<script src="assets/js/admin-dashboard.js"></script>
</body>
</html>
