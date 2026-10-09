<?php
declare(strict_types=1);

requireRole('social-service-officer');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/inventory-pie-chart.php';
$activePage = 'dashboard';

$metrics = ['remaining' => 0, 'item_types' => 0, 'today' => 0, 'returns' => 0];
$requestPipeline = ['submitted' => 0, 'approved' => 0, 'pending' => 0, 'rejected' => 0];
$poolRows = [];
$lowStockThreshold = 10;
$lowStockItems = [];
try {
    $db = database();
    $user = (int) $_SESSION['user_id'];
    $stmt = $db->prepare('SELECT i.item_name,i.variety,GREATEST(p.allocated-p.distributed+p.reused,0) remaining FROM division_pools p JOIN inventory_items i ON i.id=p.item_id JOIN users u ON u.ds_division_id=p.ds_division_id WHERE u.id=:user AND u.status="active" ORDER BY i.item_name,i.variety');
    $stmt->execute(['user' => $user]);
    $poolRows = $stmt->fetchAll();
    $metrics['remaining'] = array_sum(array_column($poolRows, 'remaining'));
    $metrics['item_types'] = count($poolRows);
    $stmt = $db->prepare('SELECT COALESCE(SUM(d.quantity),0) FROM distributions d JOIN users u ON u.ds_division_id=d.ds_division_id WHERE u.id=:user AND DATE(d.distributed_at)=CURDATE()');
    $stmt->execute(['user' => $user]);
    $metrics['today'] = (int) $stmt->fetchColumn();
    $stmt = $db->prepare('SELECT COUNT(*) FROM item_returns r JOIN distributions d ON d.id=r.distribution_id JOIN users u ON u.ds_division_id=d.ds_division_id WHERE u.id=:user AND YEAR(r.processed_at)=YEAR(CURDATE()) AND MONTH(r.processed_at)=MONTH(CURDATE())');
    $stmt->execute(['user' => $user]);
    $metrics['returns'] = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log($e->getMessage());
}
try {
    $threshold = database()->query("SELECT setting_value FROM system_settings WHERE setting_key = 'low_stock_threshold' LIMIT 1")->fetchColumn();
    if ($threshold !== false) {
        $lowStockThreshold = max(0, (int) $threshold);
    }
} catch (PDOException $e) {
    error_log('SSO low-stock threshold unavailable: ' . $e->getMessage());
}
foreach ($poolRows as $row) {
    if ((int) $row['remaining'] <= $lowStockThreshold) {
        $lowStockItems[] = $row;
    }
}
usort($lowStockItems, static fn(array $a, array $b): int => (int) $a['remaining'] <=> (int) $b['remaining']);
try {
    $stmt = database()->prepare(
        "SELECT
            COALESCE(SUM(ar.status <> 'draft'), 0) AS submitted,
            COALESCE(SUM(ar.status IN ('approved', 'goods-requested', 'distributed')), 0) AS approved,
            COALESCE(SUM(ar.status = 'pending'), 0) AS pending,
            COALESCE(SUM(ar.status = 'rejected'), 0) AS rejected
         FROM aid_requests ar
         JOIN beneficiaries b ON b.id=ar.beneficiary_id
         WHERE b.ds_division_id = (SELECT ds_division_id FROM users WHERE id=:user)"
    );
    $stmt->execute(['user' => (int) $_SESSION['user_id']]);
    foreach ($stmt->fetch(PDO::FETCH_ASSOC) ?: [] as $status => $count) {
        $requestPipeline[$status] = (int) $count;
    }
} catch (PDOException $e) {
    error_log('SSO request pipeline unavailable: ' . $e->getMessage());
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Social Service Officer Dashboard | SWPCS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/admin-dashboard.css') ?>" rel="stylesheet">
</head>
<body class="social-officer-dashboard">
    <?php require __DIR__ . '/../../includes/social-service-officer-sidebar.php'; ?>

    <div class="admin-shell">
        <header class="topbar">
            <div class="d-flex align-items-center gap-3">
                <button type="button" class="menu-button" id="menu-button" aria-label="Open navigation">☰</button>
                <h1>Dashboard</h1>
            </div>
            <div class="topbar-actions">
                <label class="search-box"><span aria-hidden="true">🔍</span><input type="search" placeholder="Search anything..." aria-label="Search"></label>
                <button class="notification-button" type="button" aria-label="Notifications">🔔</button>
            </div>
        </header>

        <main class="dashboard-content social-dashboard-page">
            <section class="stats-grid" aria-label="Social Service Officer statistics">
                <a class="stat-card stat-card-link" href="dashboard.php?page=pool-quota"><span class="stat-icon">📦</span><p>My Pool Quota (Remaining)</p><strong><?=number_format((int)$metrics['remaining'])?></strong><small>Across <?=$metrics['item_types']?> item types</small><span class="stat-card-action"><?=htmlspecialchars(t('View details'),ENT_QUOTES,'UTF-8')?> <span aria-hidden="true">&#8594;</span></span></a>
                <a class="stat-card stat-card-link" href="dashboard.php?page=distribute-aid"><span class="stat-icon">🤝</span><p>Distributed Today</p><strong><?=$metrics['today']?></strong><small>Items issued today</small><span class="stat-card-action"><?=htmlspecialchars(t('View details'),ENT_QUOTES,'UTF-8')?> <span aria-hidden="true">&#8594;</span></span></a>
                <a class="stat-card stat-card-link" href="dashboard.php?page=pool-quota&amp;stock=low"><span class="stat-icon" aria-hidden="true">&#9888;</span><p><?= htmlspecialchars(t('Low Stock Alerts'), ENT_QUOTES, 'UTF-8') ?></p><strong><?= count($lowStockItems) ?></strong><small class="<?= $lowStockItems === [] ? 'positive' : 'negative' ?>"><?= htmlspecialchars($lowStockItems === [] ? t('Stock levels are healthy') : t('Minimum') . ': ' . $lowStockThreshold, ENT_QUOTES, 'UTF-8') ?></small><span class="stat-card-action"><?= htmlspecialchars(t('View details'), ENT_QUOTES, 'UTF-8') ?> <span aria-hidden="true">&#8594;</span></span></a>
                <a class="stat-card stat-card-link" href="dashboard.php?page=process-return"><span class="stat-icon">🔄</span><p>Returns This Month</p><strong><?=$metrics['returns']?></strong><small>Returns processed</small><span class="stat-card-action"><?=htmlspecialchars(t('View details'),ENT_QUOTES,'UTF-8')?> <span aria-hidden="true">&#8594;</span></span></a>
            </section>

            <div class="sso-dashboard-detail-grid">
                <section class="panel sso-request-pipeline" aria-labelledby="sso-request-pipeline-title">
                    <div class="panel-header">
                        <h2 id="sso-request-pipeline-title"><?= htmlspecialchars(t('Division Request Pipeline'), ENT_QUOTES, 'UTF-8') ?></h2>
                        <a href="dashboard.php?page=aid-requests"><?= htmlspecialchars(t('View details'), ENT_QUOTES, 'UTF-8') ?> <span aria-hidden="true">&#8594;</span></a>
                    </div>
                    <div class="sso-request-pipeline-grid">
                        <article class="sso-pipeline-stat is-submitted"><span aria-hidden="true">&#128196;</span><div><small><?= htmlspecialchars(t('Total Submitted'), ENT_QUOTES, 'UTF-8') ?></small><strong><?= number_format($requestPipeline['submitted']) ?></strong></div></article>
                        <article class="sso-pipeline-stat is-approved"><span aria-hidden="true">&#10003;</span><div><small><?= htmlspecialchars(t('Approved'), ENT_QUOTES, 'UTF-8') ?></small><strong><?= number_format($requestPipeline['approved']) ?></strong></div></article>
                        <article class="sso-pipeline-stat is-pending"><span aria-hidden="true">&#8987;</span><div><small><?= htmlspecialchars(t('Pending'), ENT_QUOTES, 'UTF-8') ?></small><strong><?= number_format($requestPipeline['pending']) ?></strong></div></article>
                        <article class="sso-pipeline-stat is-rejected"><span aria-hidden="true">&#10005;</span><div><small><?= htmlspecialchars(t('Rejected'), ENT_QUOTES, 'UTF-8') ?></small><strong><?= number_format($requestPipeline['rejected']) ?></strong></div></article>
                    </div>
                </section>

                <article class="panel social-quota-panel inventory-pie-panel" aria-label="<?= htmlspecialchars(t('Existing Goods in My Pool'), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="panel-header">
                        <h2><span aria-hidden="true">&#128230;</span> <?= htmlspecialchars(t('Existing Goods in My Pool'), ENT_QUOTES, 'UTF-8') ?></h2>
                        <a href="dashboard.php?page=pool-quota"><?= htmlspecialchars(t('View details'), ENT_QUOTES, 'UTF-8') ?> <span aria-hidden="true">&#8594;</span></a>
                    </div>
                    <?php renderInventoryPieChart(
                        array_map(static fn(array $row): array => ['name' => widmsAidItemName((string)$row['item_name']), 'quantity' => $row['remaining']], $poolRows),
                        t('Existing Goods in My Pool'),
                        t('No goods are currently available in your pool.'),
                        true
                    ); ?>
                </article>
            </div>

            <section class="panel sso-low-stock-panel" id="sso-low-stock-panel" aria-labelledby="sso-low-stock-title">
                <div class="panel-header">
                    <h2 id="sso-low-stock-title"><span aria-hidden="true">&#9888;</span> <?= htmlspecialchars(t('Low Stock Alerts'), ENT_QUOTES, 'UTF-8') ?></h2>
                    <div class="sso-low-stock-actions">
                        <span class="attention-total-count" aria-label="<?= htmlspecialchars(t('Low Stock Alerts') . ': ' . count($lowStockItems), ENT_QUOTES, 'UTF-8') ?>"><?= count($lowStockItems) ?></span>
                        <a href="dashboard.php?page=pool-quota&amp;stock=low"><?= htmlspecialchars(t('View details'), ENT_QUOTES, 'UTF-8') ?> <span aria-hidden="true">&#8594;</span></a>
                    </div>
                </div>
                <div class="sso-low-stock-content">
                    <?php if ($lowStockItems === []): ?>
                        <div class="attention-empty-state"><span aria-hidden="true">&#10003;</span><div><strong><?= htmlspecialchars(t('Stock levels are healthy'), ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars(t('No low-stock items.'), ENT_QUOTES, 'UTF-8') ?></small></div></div>
                    <?php else: ?>
                        <ul class="attention-item-list sso-low-stock-list">
                            <?php foreach ($lowStockItems as $stockItem): ?>
                                <?php $remaining = (int) $stockItem['remaining']; ?>
                                <li class="attention-item is-low">
                                    <span class="attention-item-symbol" aria-hidden="true">&#128230;</span>
                                    <span class="attention-item-copy"><b><?= htmlspecialchars(widmsAidItemName((string) $stockItem['item_name']) . ((string) $stockItem['variety'] !== '' ? ' — ' . (string) $stockItem['variety'] : ''), ENT_QUOTES, 'UTF-8') ?></b><small><?= htmlspecialchars(t('Available'), ENT_QUOTES, 'UTF-8') ?>: <strong><?= $remaining ?></strong> · <?= htmlspecialchars(t('Minimum'), ENT_QUOTES, 'UTF-8') ?>: <?= $lowStockThreshold ?></small></span>
                                    <em class="attention-badge danger"><?= htmlspecialchars(t($remaining === 0 ? 'Out of Stock' : 'Low'), ENT_QUOTES, 'UTF-8') ?></em>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>

    <script src="assets/js/admin-dashboard.js"></script>
</body>
</html>
