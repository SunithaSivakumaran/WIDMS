<?php
declare(strict_types=1);

requireRole('subject-officer');
require_once __DIR__ . '/../../config/database.php';
$activePage = 'dashboard';

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
                <a class="stat-card stat-card-link" href="dashboard.php?page=my-goods-requests"><span class="stat-icon">📋</span><p>Submitted Quota Requests</p><strong><?=$metrics['submitted']?></strong><small>Stock quota requests submitted</small><span class="stat-card-action"><?=htmlspecialchars(t('View details'),ENT_QUOTES,'UTF-8')?> <span aria-hidden="true">&#8594;</span></span></a>
                <a class="stat-card stat-card-link" href="dashboard.php?page=beneficiaries"><span class="stat-icon">🗃️</span><p>Beneficiaries in Division</p><strong><?=$metrics['beneficiaries']?></strong><small>Active beneficiary records</small><span class="stat-card-action"><?=htmlspecialchars(t('View details'),ENT_QUOTES,'UTF-8')?> <span aria-hidden="true">&#8594;</span></span></a>
                <a class="stat-card stat-card-link" href="dashboard.php?page=my-goods-requests"><span class="stat-icon">📤</span><p>Pending Quota Releases</p><strong><?=$metrics['releases']?></strong><small>Approved, awaiting Store Keeper release</small><span class="stat-card-action"><?=htmlspecialchars(t('View details'),ENT_QUOTES,'UTF-8')?> <span aria-hidden="true">&#8594;</span></span></a>
                <a class="stat-card stat-card-link" href="dashboard.php?page=returns"><span class="stat-icon">🔄</span><p>Returns This Month</p><strong><?=$metrics['returns']?></strong><small>Division return records</small><span class="stat-card-action"><?=htmlspecialchars(t('View details'),ENT_QUOTES,'UTF-8')?> <span aria-hidden="true">&#8594;</span></span></a>
            </section>

            <section class="panel approved-needs-panel">
                <div class="panel-header approved-needs-header">
                    <div class="approved-needs-heading">
                        <span class="approved-needs-heading-icon" aria-hidden="true">✓</span>
                        <div>
                            <h2><?=htmlspecialchars(t('Approved Needs — Awaiting Goods'),ENT_QUOTES,'UTF-8')?></h2>
                            <p><?=htmlspecialchars(t('Approved beneficiary needs by aid item'),ENT_QUOTES,'UTF-8')?></p>
                        </div>
                    </div>
                    <div class="approved-needs-actions">
                        <span class="approved-needs-count"><?=count($approvedNeeds)?> <?=htmlspecialchars(t('aid item types'),ENT_QUOTES,'UTF-8')?></span>
                        <a class="admin-primary-action" href="dashboard.php?page=request-goods"><?=htmlspecialchars(t('Open Batch Queue'),ENT_QUOTES,'UTF-8')?> <span aria-hidden="true">→</span></a>
                    </div>
                </div>
                <div class="operation-summary-grid p-3">
                    <?php if($approvedNeeds===[]):?><article class="approved-needs-empty"><span aria-hidden="true">✓</span><div><strong><?=htmlspecialchars(t('All caught up'),ENT_QUOTES,'UTF-8')?></strong><p><?=htmlspecialchars(t('No requests awaiting goods'),ENT_QUOTES,'UTF-8')?></p></div></article><?php else:foreach($approvedNeeds as $need):?>
                    <article class="operation-summary-card approved-need-card">
                        <div class="approved-need-topline"><span class="approved-need-badge"><?=htmlspecialchars(t('Approved'),ENT_QUOTES,'UTF-8')?></span></div>
                        <h3><?=htmlspecialchars(t((string)$need['item_name']).($need['variety']?' — '.$need['variety']:''),ENT_QUOTES,'UTF-8')?></h3>
                        <div class="approved-need-quantity"><strong><?=(int)$need['quantity']?></strong><span><?=htmlspecialchars(t('units required'),ENT_QUOTES,'UTF-8')?></span></div>
                        <p class="approved-need-beneficiaries"><span aria-hidden="true">👥</span> <?=(int)$need['beneficiary_count']?> <?=htmlspecialchars(t('beneficiary request(s)'),ENT_QUOTES,'UTF-8')?></p>
                    </article>
                    <?php endforeach;endif;?>
                </div>
            </section>

            <section class="subject-summary-section">
                <article class="panel division-panel division-summary-panel">
                    <div class="panel-header division-summary-header">
                        <span class="division-summary-icon" aria-hidden="true">📊</span>
                        <div>
                            <h2><?=htmlspecialchars(t('My Division Summary'),ENT_QUOTES,'UTF-8')?></h2>
                            <p><?=htmlspecialchars(t('Quota requests submitted by you'),ENT_QUOTES,'UTF-8')?></p>
                        </div>
                    </div>

                    <div class="division-summary-body">
                        <div class="quota-overview-callout">
                            <span class="quota-overview-icon" aria-hidden="true">📦</span>
                            <div class="quota-overview-copy">
                                <h3><?=htmlspecialchars(t('Quota Request Overview'),ENT_QUOTES,'UTF-8')?></h3>
                                <p><?=htmlspecialchars(t('Follow your requests through Admin approval and Store Keeper dispatch.'),ENT_QUOTES,'UTF-8')?></p>
                            </div>
                            <a class="quota-history-link" href="dashboard.php?page=my-goods-requests"><?=htmlspecialchars(t('Quota Request History'),ENT_QUOTES,'UTF-8')?> <span aria-hidden="true">→</span></a>
                        </div>

                        <div class="request-pipeline">
                            <h3><?=htmlspecialchars(t('My Request Pipeline'),ENT_QUOTES,'UTF-8')?></h3>
                            <div class="request-pipeline-grid">
                                <article class="pipeline-stat pipeline-total">
                                    <span class="pipeline-stat-icon" aria-hidden="true">📄</span>
                                    <div><span><?=htmlspecialchars(t('Total Submitted'),ENT_QUOTES,'UTF-8')?></span><strong><?=$metrics['submitted']?></strong></div>
                                </article>
                                <article class="pipeline-stat pipeline-approved">
                                    <span class="pipeline-stat-icon" aria-hidden="true">✓</span>
                                    <div><span><?=htmlspecialchars(t('Approved'),ENT_QUOTES,'UTF-8')?></span><strong><?=$metrics['approved']?></strong></div>
                                </article>
                                <article class="pipeline-stat pipeline-pending">
                                    <span class="pipeline-stat-icon" aria-hidden="true">⌛</span>
                                    <div><span><?=htmlspecialchars(t('Pending'),ENT_QUOTES,'UTF-8')?></span><strong><?=$metrics['pending']?></strong></div>
                                </article>
                                <article class="pipeline-stat pipeline-rejected">
                                    <span class="pipeline-stat-icon" aria-hidden="true">×</span>
                                    <div><span><?=htmlspecialchars(t('Rejected'),ENT_QUOTES,'UTF-8')?></span><strong><?=$metrics['rejected']?></strong></div>
                                </article>
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
