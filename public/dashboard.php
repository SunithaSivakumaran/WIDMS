<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

requireLogin();

$adminPages = [
    'dashboard' => __DIR__ . '/../modules/admin/dashboard.php',
    'pending-approvals' => __DIR__ . '/../modules/admin/pending-approvals.php',
    'users' => __DIR__ . '/../modules/admin/users.php',
    'system-config' => __DIR__ . '/../modules/admin/system-config.php',
    'correction-requests' => __DIR__ . '/../modules/admin/correction-requests.php',
    'divisions' => __DIR__ . '/../modules/admin/divisions.php',
    'goods-requests' => __DIR__ . '/../modules/admin/goods-requests.php',
    'vision-camp-requests' => __DIR__ . '/../modules/admin/vision-camp-requests.php',
    'contact-lens-orders' => __DIR__ . '/../modules/admin/contact-lens-orders.php',
    'item-requests' => __DIR__ . '/../modules/admin/item-requests.php',
    'beneficiaries' => __DIR__ . '/../modules/admin/beneficiaries.php',
    'central-stock' => __DIR__ . '/../modules/admin/central-stock.php',
    'suppliers' => __DIR__ . '/../modules/admin/suppliers.php',
    'payments' => __DIR__ . '/../modules/admin/payments.php',
    'officer-pools' => __DIR__ . '/../modules/admin/officer-pools.php',
    'reports' => __DIR__ . '/../modules/admin/reports.php',
    'audit-log' => __DIR__ . '/../modules/admin/audit-log.php',
];

$socialOfficerPages = [
    'dashboard' => __DIR__ . '/../modules/social-service-officer/dashboard.php',
    'pool-quota' => __DIR__ . '/../modules/social-service-officer/pool-quota.php',
    'new-aid-request' => __DIR__ . '/../modules/social-service-officer/aid-requests.php',
    'aid-requests' => __DIR__ . '/../modules/social-service-officer/aid-requests.php',
    'distribute-aid' => __DIR__ . '/../modules/social-service-officer/distribute-aid.php',
    'pending-handover' => __DIR__ . '/../modules/social-service-officer/pending-handover.php',
    'pending-lens-handover' => __DIR__ . '/../modules/social-service-officer/pending-lens-handover.php',
    'request-status-report' => __DIR__ . '/../modules/social-service-officer/request-status-report.php',
    'beneficiaries' => __DIR__ . '/../modules/social-service-officer/beneficiaries.php',
    'process-return' => __DIR__ . '/../modules/social-service-officer/process-return.php',
];

$subjectOfficerPages = [
    'dashboard' => __DIR__ . '/../modules/subject-officer/dashboard.php',
    'request-goods' => __DIR__ . '/../modules/subject-officer/request-goods.php',
    'vision-camp' => __DIR__ . '/../modules/subject-officer/vision-camp.php',
    'contact-lens-orders' => __DIR__ . '/../modules/subject-officer/contact-lens-orders.php',
    'aid-distribution' => __DIR__ . '/../modules/subject-officer/aid-requests.php',
    'beneficiaries' => __DIR__ . '/../modules/subject-officer/beneficiaries.php',
    'distribute-items' => __DIR__ . '/../modules/subject-officer/distribute-items.php',
    'returns' => __DIR__ . '/../modules/subject-officer/workspace.php',
    'aid-requests' => __DIR__ . '/../modules/subject-officer/aid-requests.php',
    'correction-approval' => __DIR__ . '/../modules/admin/correction-requests.php',
    'central-stock' => __DIR__ . '/../modules/subject-officer/workspace.php',
    'suppliers' => __DIR__ . '/../modules/subject-officer/suppliers.php',
    'eligibility-rules' => __DIR__ . '/../modules/subject-officer/eligibility-rules.php',
    'item-categories' => __DIR__ . '/../modules/subject-officer/item-categories.php',
    'officer-pools' => __DIR__ . '/../modules/subject-officer/workspace.php',
    'reports' => __DIR__ . '/../modules/subject-officer/workspace.php',
    'audit-log' => __DIR__ . '/../modules/subject-officer/workspace.php',
];

$requestedPage = (string) ($_GET['page'] ?? 'dashboard');

$storeKeeperPages = [
    'dashboard' => __DIR__ . '/../modules/store-keeper/dashboard.php',
    'receive-items' => __DIR__ . '/../modules/store-keeper/receive-items.php',
    'current-stock' => __DIR__ . '/../modules/store-keeper/current-stock.php',
    'correction-requests' => __DIR__ . '/../modules/store-keeper/correction-requests.php',
    'approved-dispatches' => __DIR__ . '/../modules/store-keeper/approved-dispatches.php',
    'recent-dispatches' => __DIR__ . '/../modules/store-keeper/recent-dispatches.php',
];

$dashboards = [
    'admin' => $adminPages[$requestedPage] ?? $adminPages['dashboard'],
    'subject-officer' => $subjectOfficerPages[$requestedPage] ?? $subjectOfficerPages['dashboard'],
    'store-keeper' => $storeKeeperPages[$requestedPage] ?? $storeKeeperPages['dashboard'],
    'social-service-officer' => $socialOfficerPages[$requestedPage] ?? $socialOfficerPages['dashboard'],
];

$dashboard = $dashboards[$_SESSION['role']] ?? null;
if ($dashboard === null || !is_file($dashboard)) {
    http_response_code(403);
    exit('Dashboard is unavailable.');
}

// Legacy modules share one safe visible-text translator while they are migrated to t().
ob_start();
require $dashboard;
$dashboardHtml = (string) ob_get_clean();
$language = widmsLanguage();
$dashboardHtml = preg_replace('/<html(?:\s+lang="[^"]*")?>/i', '<html lang="' . htmlspecialchars($language, ENT_QUOTES, 'UTF-8') . '">', $dashboardHtml, 1) ?? $dashboardHtml;

if ($language !== 'en') {
    $dashboardHtml = str_replace('</body>', widmsUiTranslationAssetsHtml() . '</body>', $dashboardHtml);
}

echo $dashboardHtml;
