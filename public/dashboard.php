<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/ui-messages.php';

requireLogin();

$adminPages = [
    'dashboard' => __DIR__ . '/../modules/admin/dashboard.php',
    'pending-approvals' => __DIR__ . '/../modules/admin/pending-approvals.php',
    'users' => __DIR__ . '/../modules/admin/users.php',
    'system-config' => __DIR__ . '/../modules/admin/system-config.php',
    'correction-requests' => __DIR__ . '/../modules/admin/correction-requests.php',
    'reviewed-correction-requests' => __DIR__ . '/../modules/admin/reviewed-correction-requests.php',
    'divisions' => __DIR__ . '/../modules/admin/divisions.php',
    'goods-requests' => __DIR__ . '/../modules/admin/goods-requests.php',
    'vision-camp-requests' => __DIR__ . '/../modules/admin/vision-camp-requests.php',
    'contact-lens-orders' => __DIR__ . '/../modules/admin/contact-lens-orders.php',
    'item-requests' => __DIR__ . '/../modules/admin/item-requests.php',
    'central-stock' => __DIR__ . '/../modules/store-keeper/current-stock.php',
    'current-stock' => __DIR__ . '/../modules/store-keeper/current-stock.php',
    'receipt-history' => __DIR__ . '/../modules/store-keeper/receipt-history.php',
    'receipt-payment-details' => __DIR__ . '/../modules/store-keeper/receipt-payment-details.php',
    'item-payment-details' => __DIR__ . '/../modules/store-keeper/item-payment-details.php',
    'item-power-details' => __DIR__ . '/../modules/store-keeper/item-power-details.php',
    'suppliers' => __DIR__ . '/../modules/admin/suppliers.php',
    'supplier-config' => __DIR__ . '/../modules/admin/suppliers.php',
    'supplier-details' => __DIR__ . '/../modules/admin/supplier-details.php',
    'supplier-balances' => __DIR__ . '/../modules/admin/supplier-balances.php',
    // Keep old admin bookmarks working, but use the shared receipt history
    // workflow instead of the retired standalone payment workspace.
    'payments' => __DIR__ . '/../modules/store-keeper/receipt-history.php',
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
    // Reuse the live inventory view; keep the old route for existing bookmarks.
    'central-stock' => __DIR__ . '/../modules/store-keeper/current-stock.php',
    'current-stock' => __DIR__ . '/../modules/store-keeper/current-stock.php',
    'receipt-history' => __DIR__ . '/../modules/store-keeper/receipt-history.php',
    'receipt-payment-details' => __DIR__ . '/../modules/store-keeper/receipt-payment-details.php',
    'item-payment-details' => __DIR__ . '/../modules/store-keeper/item-payment-details.php',
    'item-power-details' => __DIR__ . '/../modules/store-keeper/item-power-details.php',
    'suppliers' => __DIR__ . '/../modules/subject-officer/suppliers.php',
    'supplier-config' => __DIR__ . '/../modules/subject-officer/suppliers.php',
    'supplier-details' => __DIR__ . '/../modules/subject-officer/supplier-details.php',
    'supplier-balances' => __DIR__ . '/../modules/subject-officer/supplier-balances.php',
    // Separate rule building from rule review so each workflow has a focused page.
    'eligibility-rules' => __DIR__ . '/../modules/subject-officer/eligibility-rules.php',
    'item-categories' => __DIR__ . '/../modules/subject-officer/item-categories.php',
    'edit-aid-rule' => __DIR__ . '/../modules/subject-officer/edit-aid-rule.php',
    'officer-pools' => __DIR__ . '/../modules/subject-officer/workspace.php',
    'reports' => __DIR__ . '/../modules/subject-officer/workspace.php',
    'audit-log' => __DIR__ . '/../modules/subject-officer/workspace.php',
];

$requestedPage = (string) ($_GET['page'] ?? 'dashboard');

$storeKeeperPages = [
    'dashboard' => __DIR__ . '/../modules/store-keeper/dashboard.php',
    'receive-items' => __DIR__ . '/../modules/store-keeper/receive-items.php',
    'receipt-history' => __DIR__ . '/../modules/store-keeper/receipt-history.php',
    'receipt-payment-details' => __DIR__ . '/../modules/store-keeper/receipt-payment-details.php',
    'item-payment-details' => __DIR__ . '/../modules/store-keeper/item-payment-details.php',
    'item-power-details' => __DIR__ . '/../modules/store-keeper/item-power-details.php',
    'current-stock' => __DIR__ . '/../modules/store-keeper/current-stock.php',
    'correction-requests' => __DIR__ . '/../modules/store-keeper/correction-requests.php',
    'correction-reference-value' => __DIR__ . '/../modules/store-keeper/correction-reference-value.php',
    'request-history' => __DIR__ . '/../modules/store-keeper/request-history.php',
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
$dashboardHtml = str_replace(
    'class="alert alert-success"',
    'class="alert alert-success widms-success-message"',
    $dashboardHtml
);
$language = widmsLanguage();
$dashboardHtml = preg_replace('/<html(?:\s+lang="[^"]*")?>/i', '<html lang="' . htmlspecialchars($language, ENT_QUOTES, 'UTF-8') . '">', $dashboardHtml, 1) ?? $dashboardHtml;
$dashboardHtml = preg_replace(
    '/assets\/css\/admin-dashboard\.css(?:\?v=\d+)?/',
    'assets/css/admin-dashboard.css?v=66',
    $dashboardHtml
) ?? $dashboardHtml;
$dashboardHtml = preg_replace(
    '/assets\/js\/admin-dashboard\.js(?:\?v=\d+)?/',
    'assets/js/admin-dashboard.js?v=22',
    $dashboardHtml
) ?? $dashboardHtml;

if ($language !== 'en') {
    $dashboardHtml = str_replace('</body>', widmsUiTranslationAssetsHtml() . '</body>', $dashboardHtml);
}

// Delegated fallback for every dashboard module: it also works when a module
// serves an older cached copy of the shared JavaScript bundle.
$alertDismissFallback = '<script>(function(){document.addEventListener("click",function(e){var b=e.target&&e.target.closest?e.target.closest(".notification-close"):null;if(b){e.preventDefault();e.stopPropagation();var n=b.closest(".alert-success,.alert-danger");if(n){n.hidden=true;n.style.display="none";if(n.parentNode)n.parentNode.removeChild(n);}}},true);})();</script>';
$dashboardHtml = str_replace('</body>', $alertDismissFallback . '</body>', $dashboardHtml);
$dashboardHtml = str_replace('</body>', '<script>document.querySelectorAll(".alert-success,.alert-danger").forEach(function(a){if(a.querySelector(".notification-close"))return;var b=document.createElement("button");b.type="button";b.className="notification-close";b.setAttribute("aria-label","Close");b.textContent="×";a.appendChild(b);});</script></body>', $dashboardHtml);
// Receipt History uses the same shared outline action and data-card markup as
// the other dashboard pages. Keep the popup enhancement here so the details
// page remains a normal reusable data-card when opened directly as well.
$receiptHistoryEnhancement = '<script>(function(){if(!document.body.classList.contains("store-receipt-history-page"))return;document.querySelectorAll(".admin-data-table tbody tr").forEach(function(row){var first=row.querySelector("td"),action=row.lastElementChild;if(!first||!action||!/^BAT-\\d+/.test(first.textContent)||action.querySelector(".receipt-history-button"))return;var id=first.textContent.replace(/\\D/g,"");var button=document.createElement("button");button.type="button";button.className="outline-action receipt-history-button";button.textContent="History";button.addEventListener("click",function(){fetch("dashboard.php?page=receipt-payment-details&receipt_id="+id).then(function(response){if(!response.ok)throw new Error();return response.text()}).then(function(html){var doc=new DOMParser().parseFromString(html,"text/html"),card=doc.querySelector(".admin-data-card"),modal=document.createElement("div");modal.className="history-modal";modal.innerHTML="<div class=history-modal-card><button type=button class=history-modal-close aria-label=Close>×</button>"+(card?card.outerHTML:"<p>Payment history could not be loaded.</p>")+"</div>";document.body.appendChild(modal);modal.querySelector(".history-modal-close").onclick=function(){modal.remove()};modal.addEventListener("click",function(event){if(event.target===modal)modal.remove()});}).catch(function(){var modal=document.createElement("div");modal.className="history-modal";modal.innerHTML="<div class=history-modal-card><button type=button class=history-modal-close aria-label=Close>×</button><p>Payment history could not be loaded.</p></div>";document.body.appendChild(modal);modal.querySelector(".history-modal-close").onclick=function(){modal.remove()};});});action.appendChild(button);});})();</script>';
$dashboardHtml = str_replace('</body>', $receiptHistoryEnhancement . '</body>', $dashboardHtml);
echo $dashboardHtml;
