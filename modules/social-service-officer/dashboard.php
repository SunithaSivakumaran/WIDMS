<?php
declare(strict_types=1);

requireRole('social-service-officer');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/activity.php';
$activePage = 'dashboard';

$activities = recentActivities((int) $_SESSION['user_id'], 8);
$metrics=['remaining'=>0,'item_types'=>0,'today'=>0,'open'=>0,'returns'=>0,'handovers'=>0];$poolRows=[];
try{$db=database();$user=(int)$_SESSION['user_id'];$stmt=$db->prepare('SELECT i.item_name,i.variety,(p.allocated-p.distributed+p.reused) remaining FROM officer_pools p JOIN inventory_items i ON i.id=p.item_id WHERE p.officer_id=:user ORDER BY i.item_name');$stmt->execute(['user'=>$user]);$poolRows=$stmt->fetchAll();$metrics['remaining']=array_sum(array_column($poolRows,'remaining'));$metrics['item_types']=count($poolRows);$stmt=$db->prepare('SELECT COALESCE(SUM(quantity),0) FROM distributions WHERE distributed_by=:user AND DATE(distributed_at)=CURDATE()');$stmt->execute(['user'=>$user]);$metrics['today']=(int)$stmt->fetchColumn();$stmt=$db->prepare("SELECT COUNT(*) FROM aid_requests WHERE submitted_by=:user AND status IN ('pending','approved')");$stmt->execute(['user'=>$user]);$metrics['open']=(int)$stmt->fetchColumn();$stmt=$db->prepare('SELECT COUNT(*) FROM item_returns WHERE processed_by=:user AND YEAR(processed_at)=YEAR(CURDATE()) AND MONTH(processed_at)=MONTH(CURDATE())');$stmt->execute(['user'=>$user]);$metrics['returns']=(int)$stmt->fetchColumn();$stmt=$db->prepare("SELECT COUNT(*) FROM vision_camp_handovers WHERE officer_id=:user AND status='pending'");$stmt->execute(['user'=>$user]);$metrics['handovers']=(int)$stmt->fetchColumn();}catch(PDOException $e){error_log($e->getMessage());}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Social Service Officer Dashboard | WIDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css" rel="stylesheet">
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
                <article class="stat-card"><span class="stat-icon">📦</span><p>My Pool Quota (Remaining)</p><strong><?=number_format((int)$metrics['remaining'])?></strong><small>Across <?=$metrics['item_types']?> item types</small></article>
                <article class="stat-card"><span class="stat-icon">🤝</span><p>Distributed Today</p><strong><?=$metrics['today']?></strong><small>Items issued today</small></article>
                <article class="stat-card"><span class="stat-icon">📋</span><p>My Open Requests</p><strong><?=$metrics['open']?></strong><small>Pending or approved</small></article>
                <article class="stat-card"><span class="stat-icon">🔄</span><p>Returns This Month</p><strong><?=$metrics['returns']?></strong><small>Returns processed</small></article>
            </section>

            <section class="dashboard-grid">
                <article class="panel activity-panel">
                    <div class="panel-header"><h2>📋 Recent Activity</h2></div>
                    <div class="activity-table-wrap">
                        <table class="activity-table">
                            <thead><tr><th>Time</th><th>Action</th><th>By</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php if ($activities === []): ?>
                                    <tr><td colspan="4" class="text-center text-secondary py-4">No recent activity.</td></tr>
                                <?php else: foreach ($activities as $activity): ?>
                                    <tr>
                                        <td><?= date('d M, H:i', strtotime($activity['created_at'])) ?></td>
                                        <td><?= htmlspecialchars($activity['action'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($activity['actor_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><span class="status <?= htmlspecialchars(strtolower($activity['status']), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucfirst($activity['status']), ENT_QUOTES, 'UTF-8') ?></span></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="panel social-quota-panel">
                    <div class="panel-header">
                        <h2>📦 My Pool Quota</h2>
                        <a href="dashboard.php?page=pool-quota" class="outline-action">Full View</a>
                    </div>
                    <div class="quota-summary social-quota-summary">
                        <?php if(!$poolRows):?><p class="dashboard-empty-state">No pool quota allocations available.</p><?php else:?><ul class="pending-list"><?php foreach(array_slice($poolRows,0,4) as $row):?><li><span><b><?=htmlspecialchars($row['item_name'],ENT_QUOTES,'UTF-8')?></b></span><em><?=(int)$row['remaining']?></em></li><?php endforeach;?></ul><?php endif;?>
                    </div>
                    <div class="social-dashboard-actions">
                        <a href="dashboard.php?page=distribute-aid" class="distribute-aid-button">🤝 Distribute Aid to Beneficiary</a>
                        <div class="handover-notice"><span>👓 <b><?=$metrics['handovers']?> Vision Camp item<?=((int)$metrics['handovers']===1?'':'s')?></b> pending distribution.</span><a href="dashboard.php?page=pending-handover">View →</a></div>
                    </div>
                </article>
            </section>
        </main>
    </div>

    <script src="assets/js/admin-dashboard.js"></script>
</body>
</html>
