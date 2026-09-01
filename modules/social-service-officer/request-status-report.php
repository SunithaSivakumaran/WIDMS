<?php
declare(strict_types=1);

requireRole('social-service-officer');
require_once __DIR__.'/../../config/database.php';
$activePage = 'request-status-report';
$rows=[];try{$stmt=database()->prepare('SELECT ar.*,b.full_name,i.item_name,i.variety FROM aid_requests ar JOIN beneficiaries b ON b.id=ar.beneficiary_id JOIN inventory_items i ON i.id=ar.item_id WHERE ar.submitted_by=:user ORDER BY ar.id DESC');$stmt->execute(['user'=>$_SESSION['user_id']]);$rows=$stmt->fetchAll();}catch(PDOException $e){error_log($e->getMessage());}$counts=array_count_values(array_column($rows,'status'));$total=count($rows);$approved=(int)($counts['approved']??0)+(int)($counts['distributed']??0);$rate=$total?(int)round($approved*100/$total):0;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Request Status Report | WIDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css" rel="stylesheet">
</head>
<body class="request-report-page-body">
<?php require __DIR__ . '/../../includes/social-service-officer-sidebar.php'; ?>

<div class="admin-shell">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button type="button" class="menu-button" id="menu-button" aria-label="Open navigation">&#9776;</button>
            <h1>Request Status Report</h1>
        </div>
        <div class="topbar-actions">
            <label class="search-box"><span aria-hidden="true">&#128269;</span><input type="search" placeholder="Search anything..." aria-label="Search"></label>
            <button class="notification-button" type="button" aria-label="Notifications">&#128276;</button>
        </div>
    </header>

    <main class="dashboard-content request-report-page">
        <section class="request-report-summary" aria-label="Request status summary">
            <article class="request-report-stat submitted">
                <span class="request-report-icon" aria-hidden="true">&#128203;</span>
                <p>Total Submitted</p>
                <strong><?=$total?></strong>
                <small>All submitted requests</small>
            </article>
            <article class="request-report-stat approved">
                <span class="request-report-icon" aria-hidden="true">&#9989;</span>
                <p>Approved</p>
                <strong><?=$approved?></strong>
                <small><?=$rate?>% approval rate</small>
            </article>
            <article class="request-report-stat rejected">
                <span class="request-report-icon" aria-hidden="true">&#10060;</span>
                <p>Rejected</p>
                <strong><?=(int)($counts['rejected']??0)?></strong>
                <small>With Admin reason</small>
            </article>
            <article class="request-report-stat pending">
                <span class="request-report-icon" aria-hidden="true">&#8987;</span>
                <p>Pending</p>
                <strong><?=(int)($counts['pending']??0)?></strong>
                <small>Awaiting Admin</small>
            </article>
        </section>

        <section class="request-history-card">
            <div class="request-history-header">
                <h2>My Request History</h2>
                <div class="request-export-actions" aria-label="Report export options">
                    <button type="button" disabled><span aria-hidden="true">&#128196;</span> PDF</button>
                    <button type="button" disabled><span aria-hidden="true">&#128202;</span> CSV</button>
                </div>
            </div>
            <div class="request-report-table-wrap">
                <table class="request-report-table">
                    <thead><tr><th>Request ID</th><th>Beneficiary</th><th>Item</th><th>Prescription Power</th><th>Submitted</th><th>Status</th><th>Admin Notes</th></tr></thead>
                    <tbody><?php if(!$rows):?><tr><td colspan="7" class="request-report-empty">No request history available.</td></tr><?php else:foreach($rows as $row):?><tr><td>AR-<?=str_pad((string)$row['id'],4,'0',STR_PAD_LEFT)?></td><td><?=htmlspecialchars($row['full_name'],ENT_QUOTES,'UTF-8')?></td><td><?=htmlspecialchars($row['item_name'].($row['variety']?' — '.$row['variety']:''),ENT_QUOTES,'UTF-8')?></td><td><?=$row['prescribed_power']!==null?sprintf('%+.2f',(float)$row['prescribed_power']):'—'?></td><td><?=date('d M Y',strtotime($row['created_at']))?></td><td><?=ucfirst($row['status'])?></td><td><?=htmlspecialchars($row['rejection_reason']?:'—',ENT_QUOTES,'UTF-8')?></td></tr><?php endforeach;endif;?></tbody>
                </table>
            </div>
        </section>
    </main>
</div>

<script src="assets/js/admin-dashboard.js"></script>
</body>
</html>
