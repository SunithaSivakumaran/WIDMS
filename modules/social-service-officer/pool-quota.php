<?php
declare(strict_types=1);

requireRole('social-service-officer');
require_once __DIR__.'/../../config/database.php';
$activePage = 'pool-quota';
$rows=[];$beneficiaryCount=0;
try{$stmt=database()->prepare('SELECT p.*,i.item_name,i.variety,(p.allocated-p.distributed+p.reused) remaining FROM officer_pools p JOIN inventory_items i ON i.id=p.item_id WHERE p.officer_id=:user ORDER BY i.item_name,i.variety');$stmt->execute(['user'=>$_SESSION['user_id']]);$rows=$stmt->fetchAll();$stmt=database()->prepare('SELECT COUNT(DISTINCT beneficiary_id) FROM distributions WHERE distributed_by=:user');$stmt->execute(['user'=>$_SESSION['user_id']]);$beneficiaryCount=(int)$stmt->fetchColumn();}catch(PDOException $e){error_log($e->getMessage());}
$allocated=array_sum(array_column($rows,'allocated'));$distributed=array_sum(array_column($rows,'distributed'));$remaining=array_sum(array_column($rows,'remaining'));$reused=array_sum(array_column($rows,'reused'));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Pool Quota | WIDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css" rel="stylesheet">
</head>
<body class="pool-quota-page-body">
<?php require __DIR__ . '/../../includes/social-service-officer-sidebar.php'; ?>

<div class="admin-shell">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button type="button" class="menu-button" id="menu-button" aria-label="Open navigation">&#9776;</button>
            <h1>My Pool Quota</h1>
        </div>
        <div class="topbar-actions">
            <label class="search-box"><span aria-hidden="true">&#128269;</span><input type="search" placeholder="Search anything..." aria-label="Search"></label>
            <button class="notification-button" type="button" aria-label="Notifications">&#128276;</button>
        </div>
    </header>

    <main class="dashboard-content pool-quota-page">
        <section class="pool-summary-grid" aria-label="Pool quota summary">
            <article class="pool-summary-card allocated">
                <span class="pool-summary-icon" aria-hidden="true">&#128230;</span>
                <p>Total Allocated</p>
                <strong><?=number_format((int)$allocated)?></strong>
                <small>Across <?=count($rows)?> item types</small>
            </article>
            <article class="pool-summary-card distributed">
                <span class="pool-summary-icon" aria-hidden="true">&#129309;</span>
                <p>Distributed</p>
                <strong><?=number_format((int)$distributed)?></strong>
                <small>To <?=$beneficiaryCount?> beneficiaries</small>
            </article>
            <article class="pool-summary-card remaining">
                <span class="pool-summary-icon" aria-hidden="true">&#128203;</span>
                <p>Remaining</p>
                <strong><?=number_format((int)$remaining)?></strong>
                <small>Available to issue</small>
            </article>
            <article class="pool-summary-card reused">
                <span class="pool-summary-icon" aria-hidden="true">&#128260;</span>
                <p>Reused Items</p>
                <strong><?=number_format((int)$reused)?></strong>
                <small>Returned and re-issued</small>
            </article>
        </section>

        <section class="pool-quota-card">
            <div class="pool-quota-header"><h2>My Pool Quota</h2></div>
            <div class="pool-quota-table-wrap">
                <table class="pool-quota-table">
                    <thead>
                        <tr><th>Item</th><th>Variety</th><th>Allocated</th><th>Distributed</th><th>Remaining</th><th>Usage</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody><?php if(!$rows):?><tr><td colspan="8" class="pool-quota-empty">No pool quota allocations available.</td></tr><?php else:foreach($rows as $row):$usage=(int)$row['allocated']>0?min(100,(int)round((int)$row['distributed']*100/(int)$row['allocated'])):0;?><tr><td><strong><?=htmlspecialchars($row['item_name'],ENT_QUOTES,'UTF-8')?></strong></td><td><?=htmlspecialchars($row['variety']?:'—',ENT_QUOTES,'UTF-8')?></td><td><?=(int)$row['allocated']?></td><td><?=(int)$row['distributed']?></td><td><?=(int)$row['remaining']?></td><td><?=$usage?>%</td><td><?=((int)$row['remaining']>0?'Available':'Empty')?></td><td><a class="outline-action" href="dashboard.php?page=distribute-aid">Distribute</a></td></tr><?php endforeach;endif;?></tbody>
                </table>
            </div>
        </section>
    </main>
</div>

<script src="assets/js/admin-dashboard.js"></script>
</body>
</html>
