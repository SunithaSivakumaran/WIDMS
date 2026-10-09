<?php
declare(strict_types=1);

requireRole('admin');
require_once __DIR__ . '/../../config/database.php';

$activePage = 'dashboard';
$pendingVisionCamps = 0;

$metrics=['stock'=>0,'item_types'=>0,'pending'=>0,'officers'=>0,'today'=>0,'registrations'=>0,'aid'=>0,'goods'=>0,'corrections'=>0,'beneficiaries'=>0,'districts'=>0,'month'=>0];
try {
    $db = database();
    $row = $db->query('SELECT COALESCE(SUM(quantity),0) stock,COUNT(*) item_types FROM inventory_items')->fetch();
    $metrics = array_merge($metrics, $row);
    $metrics['registrations'] = (int) $db->query("SELECT COUNT(*) FROM registration_requests WHERE status='pending'")->fetchColumn();
    $metrics['aid'] = (int) $db->query("SELECT COUNT(*) FROM aid_requests WHERE status='pending'")->fetchColumn();
    $metrics['goods'] = (int) $db->query("SELECT COUNT(DISTINCT COALESCE(NULLIF(request_batch_ref, ''), CONCAT('GR-', id))) FROM goods_requests WHERE status='pending-admin-approval'")->fetchColumn();
    $metrics['corrections'] = (int) $db->query("SELECT COUNT(*) FROM correction_requests WHERE status='pending'")->fetchColumn();
    $pendingVisionCamps = (int) $db->query("SELECT COUNT(*) FROM spectacle_camps WHERE status='pending'")->fetchColumn();
    $metrics['pending'] = $metrics['registrations'] + $metrics['aid'] + $metrics['goods'] + $metrics['corrections'] + $pendingVisionCamps
        + (int) $db->query("SELECT COUNT(*) FROM beneficiary_registration_requests WHERE status='pending'")->fetchColumn();
    $metrics['officers'] = (int) $db->query("SELECT COUNT(*) FROM users WHERE role='social-service-officer' AND status='active'")->fetchColumn();
    $metrics['today'] = (int) $db->query('SELECT COALESCE(SUM(quantity),0) FROM distributions WHERE DATE(distributed_at)=CURDATE()')->fetchColumn();
    $metrics['beneficiaries'] = (int) $db->query("SELECT COUNT(*) FROM beneficiaries WHERE status='active'")->fetchColumn();
    $metrics['districts'] = (int) $db->query("SELECT COUNT(*) FROM districts WHERE status='active'")->fetchColumn();
    $metrics['month'] = (int) $db->query('SELECT COALESCE(SUM(quantity),0) FROM distributions WHERE YEAR(distributed_at)=YEAR(CURDATE()) AND MONTH(distributed_at)=MONTH(CURDATE())')->fetchColumn();
} catch (PDOException $e) {
    error_log($e->getMessage());
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard | SWPCS</title>
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
                <a class="stat-card stat-card-link" href="dashboard.php?page=central-stock">
                    <span class="stat-icon">📦</span>
                    <p>Total Central Stock</p>
                    <strong><?=number_format((int)$metrics['stock'])?></strong>
                    <small>Across <?=(int)$metrics['item_types']?> item types</small>
                    <span class="stat-card-action"><?= htmlspecialchars(t('View details'), ENT_QUOTES, 'UTF-8') ?> <span aria-hidden="true">&#8594;</span></span>
                </a>
                <a class="stat-card stat-card-link" href="dashboard.php?page=pending-approvals">
                    <span class="stat-icon">🔔</span>
                    <p>Pending Approvals</p>
                    <strong><?=(int)$metrics['pending']?></strong>
                    <small>Across all approval queues</small>
                    <span class="stat-card-action"><?= htmlspecialchars(t('View details'), ENT_QUOTES, 'UTF-8') ?> <span aria-hidden="true">&#8594;</span></span>
                </a>
                <a class="stat-card stat-card-link" href="dashboard.php?page=divisions">
                    <span class="stat-icon">🏛️</span>
                    <p>Active Social Service Officers</p>
                    <strong><?=(int)$metrics['officers']?></strong>
                    <small>Active distributors</small>
                    <span class="stat-card-action"><?= htmlspecialchars(t('View details'), ENT_QUOTES, 'UTF-8') ?> <span aria-hidden="true">&#8594;</span></span>
                </a>
                <a class="stat-card stat-card-link" href="dashboard.php?page=reports">
                    <span class="stat-icon">🤝</span>
                    <p>Distributions Today</p>
                    <strong><?=(int)$metrics['today']?></strong>
                    <small>Items issued today</small>
                    <span class="stat-card-action"><?= htmlspecialchars(t('View details'), ENT_QUOTES, 'UTF-8') ?> <span aria-hidden="true">&#8594;</span></span>
                </a>
            </section>

            <section class="dashboard-grid admin-actions-grid">
                <article class="panel actions-panel">
                    <div class="panel-header admin-actions-header">
                        <div class="admin-actions-heading">
                            <span class="admin-actions-heading-icon" aria-hidden="true">🔔</span>
                            <div><h2><?=htmlspecialchars(t('Pending Actions'),ENT_QUOTES,'UTF-8')?></h2><p><?=htmlspecialchars(t('Requests waiting for your review'),ENT_QUOTES,'UTF-8')?></p></div>
                        </div>
                        <a class="view-all" href="dashboard.php?page=pending-approvals"><?=htmlspecialchars(t('View All'),ENT_QUOTES,'UTF-8')?> <span aria-hidden="true">→</span></a>
                    </div>

                    <div class="admin-actions-content">
                        <div class="pending-action-grid">
                            <a class="pending-action-card action-aid" href="dashboard.php?page=pending-approvals&amp;tab=vision-camps">
                                <span class="pending-action-icon" aria-hidden="true">&#128083;</span>
                                <span class="pending-action-copy"><small><?=htmlspecialchars(t('Vision Camp Requests'),ENT_QUOTES,'UTF-8')?></small><strong><?=htmlspecialchars(t('Awaiting approval'),ENT_QUOTES,'UTF-8')?></strong></span>
                                <span class="pending-action-count <?=$pendingVisionCamps>0?'has-items':'is-zero'?>"><?=(int)$pendingVisionCamps?></span>
                                <span class="pending-action-arrow" aria-hidden="true">&#8594;</span>
                            </a>
                            <a class="pending-action-card action-registration" href="dashboard.php?page=pending-approvals">
                                <span class="pending-action-icon" aria-hidden="true">👥</span>
                                <span class="pending-action-copy"><small><?=htmlspecialchars(t('User Registration'),ENT_QUOTES,'UTF-8')?></small><strong><?=htmlspecialchars(t('Awaiting approval'),ENT_QUOTES,'UTF-8')?></strong></span>
                                <span class="pending-action-count <?=$metrics['registrations']>0?'has-items':'is-zero'?>"><?=(int)$metrics['registrations']?></span>
                                <span class="pending-action-arrow" aria-hidden="true">→</span>
                            </a>
                            <a class="pending-action-card action-aid" href="dashboard.php?page=item-requests&amp;view=pending">
                                <span class="pending-action-icon" aria-hidden="true">📋</span>
                                <span class="pending-action-copy"><small><?=htmlspecialchars(t('Aid Requests'),ENT_QUOTES,'UTF-8')?></small><strong><?=htmlspecialchars(t('Awaiting Admin decision'),ENT_QUOTES,'UTF-8')?></strong></span>
                                <span class="pending-action-count <?=$metrics['aid']>0?'has-items':'is-zero'?>"><?=(int)$metrics['aid']?></span>
                                <span class="pending-action-arrow" aria-hidden="true">→</span>
                            </a>
                            <a class="pending-action-card action-stock" href="dashboard.php?page=goods-requests">
                                <span class="pending-action-icon" aria-hidden="true">📤</span>
                                <span class="pending-action-copy"><small><?=htmlspecialchars(t('Stock Quota Requests'),ENT_QUOTES,'UTF-8')?></small><strong><?=htmlspecialchars(t('Pending approval'),ENT_QUOTES,'UTF-8')?></strong></span>
                                <span class="pending-action-count <?=$metrics['goods']>0?'has-items':'is-zero'?>"><?=(int)$metrics['goods']?></span>
                                <span class="pending-action-arrow" aria-hidden="true">→</span>
                            </a>
                            <a class="pending-action-card action-correction" href="dashboard.php?page=correction-requests">
                                <span class="pending-action-icon" aria-hidden="true">📝</span>
                                <span class="pending-action-copy"><small><?=htmlspecialchars(t('Correction Requests'),ENT_QUOTES,'UTF-8')?></small><strong><?=htmlspecialchars(t('Pending review'),ENT_QUOTES,'UTF-8')?></strong></span>
                                <span class="pending-action-count <?=$metrics['corrections']>0?'has-items':'is-zero'?>"><?=(int)$metrics['corrections']?></span>
                                <span class="pending-action-arrow" aria-hidden="true">→</span>
                            </a>
                        </div>

                        <div class="system-overview">
                            <div class="system-overview-heading"><div><h3><?=htmlspecialchars(t('System Overview'),ENT_QUOTES,'UTF-8')?></h3><p><?=htmlspecialchars(t('Live operational totals'),ENT_QUOTES,'UTF-8')?></p></div></div>
                            <div class="system-overview-grid">
                                <article class="system-overview-stat"><span aria-hidden="true">📦</span><div><small><?=htmlspecialchars(t('Central Stock Items'),ENT_QUOTES,'UTF-8')?></small><strong><?=number_format((int)$metrics['stock'])?></strong></div></article>
                                <article class="system-overview-stat"><span aria-hidden="true">🤝</span><div><small><?=htmlspecialchars(t('Total Beneficiaries'),ENT_QUOTES,'UTF-8')?></small><strong><?=number_format((int)$metrics['beneficiaries'])?></strong></div></article>
                                <article class="system-overview-stat"><span aria-hidden="true">🗺️</span><div><small><?=htmlspecialchars(t('Districts Covered'),ENT_QUOTES,'UTF-8')?></small><strong><?=(int)$metrics['districts']?></strong></div></article>
                                <article class="system-overview-stat"><span aria-hidden="true">📊</span><div><small><?=htmlspecialchars(t('Distributions This Month'),ENT_QUOTES,'UTF-8')?></small><strong><?=number_format((int)$metrics['month'])?></strong></div></article>
                            </div>
                        </div>
                    </div>
                </article>
            </section>
        </main>
    </div>

    <script src="assets/js/admin-dashboard.js"></script>
</body>
</html>
