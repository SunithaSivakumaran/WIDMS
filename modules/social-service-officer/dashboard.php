<?php
declare(strict_types=1);

requireRole('social-service-officer');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/inventory-pie-chart.php';
$activePage = 'dashboard';

$metrics = ['remaining' => 0, 'item_types' => 0, 'today' => 0, 'open' => 0, 'returns' => 0];
$poolRows = [];
try {
    $db = database();
    $user = (int) $_SESSION['user_id'];
    $stmt = $db->prepare('SELECT i.item_name,i.variety,(p.allocated-p.distributed+p.reused) remaining FROM officer_pools p JOIN inventory_items i ON i.id=p.item_id WHERE p.officer_id=:user ORDER BY i.item_name');
    $stmt->execute(['user' => $user]);
    $poolRows = $stmt->fetchAll();
    $metrics['remaining'] = array_sum(array_column($poolRows, 'remaining'));
    $metrics['item_types'] = count($poolRows);
    $stmt = $db->prepare('SELECT COALESCE(SUM(quantity),0) FROM distributions WHERE distributed_by=:user AND DATE(distributed_at)=CURDATE()');
    $stmt->execute(['user' => $user]);
    $metrics['today'] = (int) $stmt->fetchColumn();
    $stmt = $db->prepare("SELECT COUNT(*) FROM aid_requests WHERE submitted_by=:user AND status IN ('pending','approved')");
    $stmt->execute(['user' => $user]);
    $metrics['open'] = (int) $stmt->fetchColumn();
    $stmt = $db->prepare('SELECT COUNT(*) FROM item_returns WHERE processed_by=:user AND YEAR(processed_at)=YEAR(CURDATE()) AND MONTH(processed_at)=MONTH(CURDATE())');
    $stmt->execute(['user' => $user]);
    $metrics['returns'] = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log($e->getMessage());
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Social Service Officer Dashboard | WIDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css?v=85" rel="stylesheet">
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
                <a class="stat-card stat-card-link" href="dashboard.php?page=aid-requests"><span class="stat-icon">📋</span><p>My Open Requests</p><strong><?=$metrics['open']?></strong><small>Pending or approved</small><span class="stat-card-action"><?=htmlspecialchars(t('View details'),ENT_QUOTES,'UTF-8')?> <span aria-hidden="true">&#8594;</span></span></a>
                <a class="stat-card stat-card-link" href="dashboard.php?page=process-return"><span class="stat-icon">🔄</span><p>Returns This Month</p><strong><?=$metrics['returns']?></strong><small>Returns processed</small><span class="stat-card-action"><?=htmlspecialchars(t('View details'),ENT_QUOTES,'UTF-8')?> <span aria-hidden="true">&#8594;</span></span></a>
            </section>

            <section class="dashboard-grid">
                <article class="panel social-quota-panel inventory-pie-panel" aria-label="<?= htmlspecialchars(t('Existing Goods in My Pool'), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="panel-header">
                        <h2><span aria-hidden="true">&#128230;</span> <?= htmlspecialchars(t('Existing Goods in My Pool'), ENT_QUOTES, 'UTF-8') ?></h2>
                    </div>
                    <?php renderInventoryPieChart(
                        array_map(static fn(array $row): array => ['name' => $row['item_name'], 'quantity' => $row['remaining']], $poolRows),
                        t('Existing Goods in My Pool'),
                        t('No goods are currently available in your pool.'),
                        true
                    ); ?>
                </article>
            </section>
        </main>
    </div>

    <script src="assets/js/admin-dashboard.js"></script>
</body>
</html>
