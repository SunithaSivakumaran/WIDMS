<?php
declare(strict_types=1);

requireRole('store-keeper');
$activePage = 'recent-dispatches';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recently Dispatched | WIDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css" rel="stylesheet">
</head>
<body class="store-page store-dispatch-page">
<?php require __DIR__ . '/../../includes/store-keeper-sidebar.php'; ?>
<div class="admin-shell">
    <header class="topbar"><div class="d-flex align-items-center gap-3"><button type="button" class="menu-button" id="menu-button" aria-label="Open navigation">☰</button><h1>Recently Dispatched</h1></div></header>
    <main class="dashboard-content dispatch-page">
        <section class="dispatch-card">
            <div class="dispatch-card-header"><h2>Recently Dispatched</h2></div>
            <div class="dispatch-table-wrap"><table class="dispatch-table">
                <thead><tr><th>Request ID</th><th>Item</th><th>Variety</th><th>Qty</th><th>Division</th><th>Dispatched By</th></tr></thead>
                <tbody></tbody>
            </table></div>
        </section>
    </main>
</div>
<script src="assets/js/admin-dashboard.js"></script>
</body>
</html>
