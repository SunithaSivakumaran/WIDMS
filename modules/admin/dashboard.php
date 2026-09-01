<?php
declare(strict_types=1);

requireRole('admin');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/activity.php';

$activePage = 'dashboard';

$activities = recentActivities(null, 8);
$metrics=['stock'=>0,'item_types'=>0,'pending'=>0,'officers'=>0,'today'=>0,'registrations'=>0,'aid'=>0,'goods'=>0,'corrections'=>0,'beneficiaries'=>0,'districts'=>0,'month'=>0];
try{$db=database();$row=$db->query('SELECT COALESCE(SUM(quantity),0) stock,COUNT(*) item_types FROM inventory_items')->fetch();$metrics=array_merge($metrics,$row);$metrics['registrations']=(int)$db->query("SELECT COUNT(*) FROM registration_requests WHERE status='pending'")->fetchColumn();$metrics['aid']=(int)$db->query("SELECT COUNT(*) FROM aid_requests WHERE status='pending'")->fetchColumn();$metrics['goods']=(int)$db->query("SELECT COUNT(*) FROM goods_requests WHERE status='pending-admin-approval'")->fetchColumn();$metrics['corrections']=(int)$db->query("SELECT COUNT(*) FROM correction_requests WHERE status='pending'")->fetchColumn();$metrics['pending']=$metrics['registrations']+$metrics['aid']+$metrics['goods']+$metrics['corrections']+(int)$db->query("SELECT COUNT(*) FROM beneficiary_registration_requests WHERE status='pending'")->fetchColumn()+(int)$db->query("SELECT COUNT(*) FROM vision_camps WHERE stage IN ('awaiting-vendor-approval','awaiting-goods-release')")->fetchColumn()+(int)$db->query("SELECT COUNT(*) FROM contact_lens_orders WHERE status='pending'")->fetchColumn();$metrics['officers']=(int)$db->query("SELECT COUNT(*) FROM users WHERE role='social-service-officer' AND status='active'")->fetchColumn();$metrics['today']=(int)$db->query('SELECT COALESCE(SUM(quantity),0) FROM distributions WHERE DATE(distributed_at)=CURDATE()')->fetchColumn();$metrics['beneficiaries']=(int)$db->query("SELECT COUNT(*) FROM beneficiaries WHERE status='active'")->fetchColumn();$metrics['districts']=(int)$db->query("SELECT COUNT(*) FROM districts WHERE status='active'")->fetchColumn();$metrics['month']=(int)$db->query('SELECT COALESCE(SUM(quantity),0) FROM distributions WHERE YEAR(distributed_at)=YEAR(CURDATE()) AND MONTH(distributed_at)=MONTH(CURDATE())')->fetchColumn();}catch(PDOException $e){error_log($e->getMessage());}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard | WIDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css" rel="stylesheet">
</head>
<body>
    <?php require __DIR__ . '/../../includes/admin-sidebar.php'; ?>

    <div class="admin-shell">
        <header class="topbar">
            <div class="d-flex align-items-center gap-3">
                <button type="button" class="menu-button" id="menu-button" aria-label="Open navigation">☰</button>
                <h1>Dashboard</h1>
            </div>
            <div class="topbar-actions">
                <label class="search-box">
                    <span aria-hidden="true">🔍</span>
                    <input type="search" placeholder="Search anything..." aria-label="Search">
                </label>
                <button class="notification-button" type="button" aria-label="Notifications">🔔</button>
            </div>
        </header>

        <main class="dashboard-content admin-dashboard-page">
            <section class="stats-grid" aria-label="System statistics">
                <article class="stat-card">
                    <span class="stat-icon">📦</span>
                    <p>Total Central Stock</p>
                    <strong><?=number_format((int)$metrics['stock'])?></strong>
                    <small>Across <?=(int)$metrics['item_types']?> item types</small>
                </article>
                <article class="stat-card">
                    <span class="stat-icon">🔔</span>
                    <p>Pending Approvals</p>
                    <strong><?=(int)$metrics['pending']?></strong>
                    <small>Across all approval queues</small>
                </article>
                <article class="stat-card">
                    <span class="stat-icon">🏛️</span>
                    <p>Active Social Service Officers</p>
                    <strong><?=(int)$metrics['officers']?></strong>
                    <small>Active distributors</small>
                </article>
                <article class="stat-card">
                    <span class="stat-icon">🤝</span>
                    <p>Distributions Today</p>
                    <strong><?=(int)$metrics['today']?></strong>
                    <small>Items issued today</small>
                </article>
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

                <article class="panel actions-panel">
                    <div class="panel-header">
                        <h2>🔔 Pending Actions</h2>
                        <button type="button" class="view-all">View All</button>
                    </div>
                    <ul class="pending-list">
                        <li><span>👥 <b>User registrations awaiting approval</b></span><em><?=$metrics['registrations']?></em></li>
                        <li><span>📋 <b>Aid requests awaiting Admin decision</b></span><em><?=$metrics['aid']?></em></li>
                        <li><span>📤 <b>Stock release requests pending approval</b></span><em><?=$metrics['goods']?></em></li>
                        <li><span>📝 <b>Correction requests pending review</b></span><em><?=$metrics['corrections']?></em></li>
                    </ul>
                    <div class="system-overview">
                        <h3>System Overview</h3>
                        <dl>
                            <div><dt>Central Stock Items</dt><dd><?=number_format((int)$metrics['stock'])?></dd></div>
                            <div><dt>Total Beneficiaries</dt><dd><?=number_format((int)$metrics['beneficiaries'])?></dd></div>
                            <div><dt>Districts Covered</dt><dd><?=(int)$metrics['districts']?></dd></div>
                            <div><dt>Distributions This Month</dt><dd><?=number_format((int)$metrics['month'])?></dd></div>
                        </dl>
                    </div>
                </article>
            </section>
        </main>
    </div>

    <script src="assets/js/admin-dashboard.js"></script>
</body>
</html>
