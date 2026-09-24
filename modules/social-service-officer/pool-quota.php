<?php
declare(strict_types=1);

requireRole('social-service-officer');
require_once __DIR__.'/../../config/database.php';
$activePage = 'pool-quota';
$rows=[];
try{
    $database=database();
    $stmt=$database->prepare('SELECT p.*,i.item_name,i.variety,(p.allocated-p.distributed+p.reused) remaining FROM officer_pools p JOIN inventory_items i ON i.id=p.item_id WHERE p.officer_id=:user ORDER BY i.item_name,i.variety');
    $stmt->execute(['user'=>$_SESSION['user_id']]);
    $rows=$stmt->fetchAll();
}catch(PDOException $e){error_log($e->getMessage());}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?=htmlspecialchars(t('My Pool Quota'),ENT_QUOTES,'UTF-8')?> | WIDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css" rel="stylesheet">
</head>
<body class="pool-quota-page-body">
<?php require __DIR__ . '/../../includes/social-service-officer-sidebar.php'; ?>

<div class="admin-shell">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button type="button" class="menu-button" id="menu-button" aria-label="Open navigation">&#9776;</button>
            <h1><?=htmlspecialchars(t('My Pool Quota'),ENT_QUOTES,'UTF-8')?></h1>
        </div>
        <div class="topbar-actions">
            <label class="search-box"><span aria-hidden="true">&#128269;</span><input type="search" placeholder="Search anything..." aria-label="Search"></label>
            <button class="notification-button" type="button" aria-label="Notifications">&#128276;</button>
        </div>
    </header>

    <main class="dashboard-content pool-quota-page">
        <section class="admin-data-card sso-standard-table-card">
            <div class="admin-data-table-wrap sso-standard-table-wrap">
                <table class="admin-data-table sso-standard-table pool-balance-table">
                    <thead>
                        <tr><th><?=htmlspecialchars(t('Item'),ENT_QUOTES,'UTF-8')?></th><th><?=htmlspecialchars(t('Variety'),ENT_QUOTES,'UTF-8')?></th><th><?=htmlspecialchars(t('Allocated'),ENT_QUOTES,'UTF-8')?></th><th><?=htmlspecialchars(t('Distributed'),ENT_QUOTES,'UTF-8')?></th><th><?=htmlspecialchars(t('Remaining'),ENT_QUOTES,'UTF-8')?></th><th><?=htmlspecialchars(t('Usage'),ENT_QUOTES,'UTF-8')?></th><th><?=htmlspecialchars(t('Status'),ENT_QUOTES,'UTF-8')?></th><th><?=htmlspecialchars(t('Action'),ENT_QUOTES,'UTF-8')?></th></tr>
                    </thead>
                    <tbody>
                    <?php if(!$rows):?>
                        <tr><td colspan="8" class="admin-empty-row"><?=htmlspecialchars(t('No pool quota allocations available.'),ENT_QUOTES,'UTF-8')?></td></tr>
                    <?php else:foreach($rows as $row):?>
                        <?php $usage=(int)$row['allocated']>0?min(100,(int)round((int)$row['distributed']*100/(int)$row['allocated'])):0; ?>
                        <tr>
                            <td><strong><?=htmlspecialchars($row['item_name'],ENT_QUOTES,'UTF-8')?></strong></td>
                            <td><?=htmlspecialchars($row['variety']?:'—',ENT_QUOTES,'UTF-8')?></td>
                            <td><?=(int)$row['allocated']?></td>
                            <td><?=(int)$row['distributed']?></td>
                            <td><?=(int)$row['remaining']?></td>
                            <td>
                                <div class="pool-usage-progress<?= $usage >= 100 ? ' is-complete' : '' ?>" role="progressbar" aria-label="<?=htmlspecialchars(t('Usage'),ENT_QUOTES,'UTF-8')?>" aria-valuenow="<?=$usage?>" aria-valuemin="0" aria-valuemax="100">
                                    <span class="pool-usage-track"><span class="pool-usage-fill" style="width:<?=$usage?>%"></span></span>
                                    <strong><?=$usage?>%</strong>
                                </div>
                            </td>
                            <td><span class="goods-status-pill <?= (int)$row['remaining']>0?'is-approved':'is-rejected' ?>"><?=htmlspecialchars(t((int)$row['remaining']>0?'Available':'Empty'),ENT_QUOTES,'UTF-8')?></span></td>
                            <td><a class="outline-action" href="dashboard.php?page=distribute-aid"><?=htmlspecialchars(t('Distribute'),ENT_QUOTES,'UTF-8')?></a></td>
                        </tr>
                    <?php endforeach;endif;?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>
</div>

<script src="assets/js/admin-dashboard.js"></script>
</body>
</html>
