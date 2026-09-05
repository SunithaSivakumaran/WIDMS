<?php
declare(strict_types=1);

requireRole('subject-officer');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/activity.php';
$activePage = 'dashboard';

$activities = recentActivities((int) $_SESSION['user_id'], 8);
$metrics=['submitted'=>0,'beneficiaries'=>0,'releases'=>0,'returns'=>0,'approved'=>0,'pending'=>0,'rejected'=>0];
$approvedNeeds=[];
try{$approvedNeeds=database()->query("SELECT i.item_name,i.variety,COUNT(*) beneficiary_count,SUM(ar.quantity) quantity FROM aid_requests ar JOIN inventory_items i ON i.id=ar.item_id WHERE ar.status='approved' GROUP BY i.id,i.item_name,i.variety ORDER BY quantity DESC,i.item_name")->fetchAll();}catch(PDOException $e){error_log($e->getMessage());}
try{$db=database();$user=(int)$_SESSION['user_id'];$stmt=$db->prepare('SELECT COUNT(*) FROM goods_requests WHERE requested_by=:user');$stmt->execute(['user'=>$user]);$metrics['submitted']=(int)$stmt->fetchColumn();$stmt=$db->prepare("SELECT COUNT(*) FROM beneficiaries b WHERE b.status='active' AND (b.ds_division_id=(SELECT ds_division_id FROM users WHERE id=:user) OR (SELECT ds_division_id FROM users WHERE id=:user2) IS NULL)");$stmt->execute(['user'=>$user,'user2'=>$user]);$metrics['beneficiaries']=(int)$stmt->fetchColumn();$stmt=$db->prepare("SELECT COUNT(*) FROM goods_requests WHERE requested_by=:user AND status='approved-awaiting-dispatch'");$stmt->execute(['user'=>$user]);$metrics['releases']=(int)$stmt->fetchColumn();$stmt=$db->prepare("SELECT COUNT(*) FROM item_returns r JOIN distributions d ON d.id=r.distribution_id JOIN beneficiaries b ON b.id=d.beneficiary_id WHERE MONTH(r.processed_at)=MONTH(CURDATE()) AND YEAR(r.processed_at)=YEAR(CURDATE()) AND (b.ds_division_id=(SELECT ds_division_id FROM users WHERE id=:user) OR (SELECT ds_division_id FROM users WHERE id=:user2) IS NULL)");$stmt->execute(['user'=>$user,'user2'=>$user]);$metrics['returns']=(int)$stmt->fetchColumn();foreach(['approved-awaiting-dispatch'=>'approved','pending-admin-approval'=>'pending','rejected'=>'rejected'] as $status=>$key){$stmt=$db->prepare('SELECT COUNT(*) FROM goods_requests WHERE requested_by=:user AND status=:status');$stmt->execute(['user'=>$user,'status'=>$status]);$metrics[$key]=(int)$stmt->fetchColumn();}}catch(PDOException $e){error_log($e->getMessage());}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Subject Officer Dashboard | WIDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css" rel="stylesheet">
</head>
<body class="subject-dashboard">
    <?php require __DIR__ . '/../../includes/subject-officer-sidebar.php'; ?>

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

        <main class="dashboard-content admin-dashboard-page">
            <section class="stats-grid" aria-label="Subject Officer statistics">
                <a class="stat-card stat-card-link" href="dashboard.php?page=request-goods"><span class="stat-icon">📋</span><p>Submitted Requests</p><strong><?=$metrics['submitted']?></strong><small>Goods requests submitted</small><span class="stat-card-action"><?=htmlspecialchars(t('View details'),ENT_QUOTES,'UTF-8')?> <span aria-hidden="true">&#8594;</span></span></a>
                <a class="stat-card stat-card-link" href="dashboard.php?page=beneficiaries"><span class="stat-icon">🗃️</span><p>Beneficiaries in Division</p><strong><?=$metrics['beneficiaries']?></strong><small>Active beneficiary records</small><span class="stat-card-action"><?=htmlspecialchars(t('View details'),ENT_QUOTES,'UTF-8')?> <span aria-hidden="true">&#8594;</span></span></a>
                <a class="stat-card stat-card-link" href="dashboard.php?page=central-stock"><span class="stat-icon">📤</span><p>Pending Stock Releases</p><strong><?=$metrics['releases']?></strong><small>Approved, awaiting dispatch</small><span class="stat-card-action"><?=htmlspecialchars(t('View details'),ENT_QUOTES,'UTF-8')?> <span aria-hidden="true">&#8594;</span></span></a>
                <a class="stat-card stat-card-link" href="dashboard.php?page=returns"><span class="stat-icon">🔄</span><p>Returns This Month</p><strong><?=$metrics['returns']?></strong><small>Division return records</small><span class="stat-card-action"><?=htmlspecialchars(t('View details'),ENT_QUOTES,'UTF-8')?> <span aria-hidden="true">&#8594;</span></span></a>
            </section>

            <section class="panel approved-needs-panel">
                <div class="panel-header"><h2>Approved Needs — Awaiting Goods</h2><a class="admin-primary-action" href="dashboard.php?page=request-goods">Open Batch Queue</a></div>
                <div class="operation-summary-grid p-3">
                    <?php if($approvedNeeds===[]):?><article class="operation-summary-card"><p>Approved Needs</p><strong>0</strong><small>No requests awaiting goods</small></article><?php else:foreach($approvedNeeds as $need):?>
                    <article class="operation-summary-card"><p><?=htmlspecialchars($need['item_name'].($need['variety']?' — '.$need['variety']:''),ENT_QUOTES,'UTF-8')?> Approved</p><strong><?=(int)$need['quantity']?></strong><small><?=(int)$need['beneficiary_count']?> beneficiary request(s)</small></article>
                    <?php endforeach;endif;?>
                </div>
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

                <article class="panel division-panel">
                    <div class="panel-header"><h2>📊 My Division Summary</h2></div>
                    <div class="quota-summary">
                        <h3>My Pool Quota Status</h3>
                        <p class="dashboard-empty-state">No pool quota information available.</p>
                    </div>
                    <div class="request-pipeline">
                        <h3>My Request Pipeline</h3>
                        <dl>
                            <div><dt>Total Submitted</dt><dd><?=$metrics['submitted']?></dd></div>
                            <div><dt>Approved</dt><dd><?=$metrics['approved']?></dd></div>
                            <div><dt>Pending</dt><dd><?=$metrics['pending']?></dd></div>
                            <div><dt>Rejected</dt><dd><?=$metrics['rejected']?></dd></div>
                        </dl>
                    </div>
                </article>
            </section>
        </main>
    </div>

    <script src="assets/js/admin-dashboard.js"></script>
</body>
</html>
