<?php
declare(strict_types=1);

requireRole('subject-officer');

$activePage = (string) ($_GET['page'] ?? 'dashboard');
$definitions = [
    'request-goods' => ['Request Goods from Store', 'My Goods Requests', ['ID', 'Item', 'Variety', 'Quantity', 'Requested Date', 'Status', 'Approved By', 'Dispatch Status', 'Action'], 'No goods requests available.'],
    'vision-camp' => ['Vision Camp / Direct Procurement', 'My Vision Camp Requests', ['ID', 'Division', 'DS Division', 'Vendor', 'Camp Date', 'People Identified', 'Attended', 'Stage', 'Action'], 'No Vision Camp requests available.'],
    'contact-lens-orders' => ['Contact Lens Orders', 'My Contact Lens Orders', ['ID', 'Beneficiary', 'NIC', 'Requested Power', 'Current Power', 'Power Changed?', 'Stock Check', 'Status', 'Date', 'Action'], 'No contact lens orders available.'],
    'aid-distribution' => ['Request for Aid Distribution', 'My Aid Distribution Requests', ['ID', 'Beneficiary', 'NIC', 'District', 'DS Division', 'Aid Requested', 'Approvals', 'Date', 'Status', 'Action'], 'No aid distribution requests available.'],
    'beneficiaries' => ['Beneficiary Management', 'Registered Beneficiaries', ['Name', 'NIC', 'District', 'DS Division', 'Phone', 'Last Received', 'Eligibility', 'Action'], 'No registered beneficiaries available.'],
    'distribute-items' => ['Distribute Items', "Today's Distributions", ['Beneficiary', 'Item', 'Type', 'Quantity', 'Source', 'Time', 'Reference'], 'No distributions recorded today.'],
    'returns' => ['Return Management', 'Recent Returns', ['Return ID', 'Beneficiary', 'Item', 'Condition', 'Reusable', 'Restored To', 'Date'], 'No returns recorded yet.'],
    'aid-requests' => ['Aid Requests Monitor', 'Aid Requests', ['ID', 'Beneficiary', 'NIC', 'District', 'Aid Requested', 'Submitted By', 'Date', 'Status', 'Action'], 'No aid requests available.'],
    'correction-approval' => ['Correction Approval', 'Correction Requests', ['ID', 'Record Reference', 'Error Type', 'Proposed Fix', 'Submitted By', 'Status', 'Action'], 'No correction requests available.'],
    'central-stock' => ['Central Stock', 'Central Stock Inventory', ['Item', 'Category', 'Variety', 'In Stock', 'Payment Status'], 'No central stock items available.'],
    'suppliers' => ['Supplier Management', 'Registered Supplier Companies', ['Company', 'Contact', 'Phone', 'Authorized Items', 'Status'], 'No registered suppliers available.'],
    'eligibility-rules' => ['Eligibility Rules', 'Eligibility Rules', ['Rule', 'Aid Category', 'Restriction Period', 'Status', 'Action'], 'No eligibility rules available.'],
    'item-categories' => ['Item Categories', 'Item Categories', ['Category', 'Item', 'Variety', 'Distribution Type', 'Returnable', 'Status', 'Action'], 'No item categories available.'],
    'officer-pools' => ['Social Service Officer Pools', 'Officer Pool Summary', ['Officer Name', 'DS Division', 'District', 'Allocated', 'Distributed', 'Remaining', 'Reused', 'Stock Level', 'Status', 'Action'], 'No Social Service Officer pools available.'],
    'audit-log' => ['Audit Log', 'Activity Log', ['Timestamp', 'User', 'Role', 'Module', 'Action', 'Record Reference'], 'No audit log entries available.'],
];

$definition = $definitions[$activePage] ?? null;
if ($activePage !== 'reports' && $definition === null) {
    http_response_code(404);
    exit('Page unavailable.');
}
$pageTitle = $activePage === 'reports' ? 'Reports' : $definition[0];
$summaryPages = ['vision-camp', 'officer-pools'];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> | WIDMS</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="assets/css/admin-dashboard.css" rel="stylesheet"></head>
<body><?php require __DIR__ . '/../../includes/subject-officer-sidebar.php'; ?><div class="admin-shell">
<header class="topbar"><div class="d-flex align-items-center gap-3"><button type="button" class="menu-button" id="menu-button" aria-label="Open navigation">&#9776;</button><h1><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1></div><div class="topbar-actions"><label class="search-box"><span aria-hidden="true">&#128269;</span><input type="search" placeholder="Search anything..." aria-label="Search"></label><button class="notification-button" type="button" aria-label="Notifications">&#128276;</button></div></header>
<main class="dashboard-content admin-operation-page subject-workspace-page">
<?php if ($activePage === 'reports'): ?>
    <?php $reports = [['Inventory Report','Current stock levels by item, category and variety'],['Distribution Report','Distribution activity by date and region'],['Beneficiary Report','Eligibility and distribution history'],['Officer Pool Report','Allocated, distributed and remaining quota'],['Request Status Report','Request statuses and turnaround times'],['Return & Reuse Report','Returns, condition and reuse status'],['Audit Log Report','Activity filtered by user, date and action']]; ?>
    <section class="reports-grid"><?php foreach ($reports as $report): ?><article class="report-card"><span class="report-icon">&#128202;</span><h2><?= htmlspecialchars($report[0], ENT_QUOTES, 'UTF-8') ?></h2><p><?= htmlspecialchars($report[1], ENT_QUOTES, 'UTF-8') ?></p><div><button type="button" disabled>Generate</button><button type="button" disabled>PDF</button><button type="button" disabled>CSV</button></div></article><?php endforeach; ?></section>
<?php else: ?>
    <?php if (in_array($activePage, $summaryPages, true)): ?><section class="operation-summary-grid subject-summary-grid"><?php foreach (['Total', 'Pending', 'In Progress', 'Completed'] as $label): ?><article class="operation-summary-card"><span>&#128203;</span><p><?= $label ?></p><strong>0</strong><small>No records available</small></article><?php endforeach; ?></section><?php endif; ?>
    <div class="subject-page-toolbar"><input type="search" placeholder="Search records..." aria-label="Search records"><button type="button" class="admin-primary-action" disabled>New Record</button></div>
    <section class="admin-data-card<?= in_array($activePage, $summaryPages, true) ? ' operation-list-card' : '' ?>"><div class="admin-data-header"><h2><?= htmlspecialchars($definition[1], ENT_QUOTES, 'UTF-8') ?></h2></div><div class="admin-data-table-wrap"><table class="admin-data-table subject-workspace-table"><thead><tr><?php foreach ($definition[2] as $column): ?><th><?= htmlspecialchars($column, ENT_QUOTES, 'UTF-8') ?></th><?php endforeach; ?></tr></thead><tbody><tr><td colspan="<?= count($definition[2]) ?>" class="admin-empty-row"><?= htmlspecialchars($definition[3], ENT_QUOTES, 'UTF-8') ?></td></tr></tbody></table></div></section>
<?php endif; ?>
</main></div><script src="assets/js/admin-dashboard.js"></script></body></html>
