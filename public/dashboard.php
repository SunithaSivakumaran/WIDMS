<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/ui-messages.php';
require_once __DIR__ . '/../includes/form-submissions.php';

requireLogin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && !widmsConsumeFormSubmissionToken($_POST['widms_submission_token'] ?? null)) {
    $_SESSION['widms_form_replay_notice'] = 'This form was already submitted or has expired. Open the form again before trying another action.';
    $query = http_build_query($_GET);
    header('Location: dashboard.php' . ($query !== '' ? '?' . $query : ''), true, 303);
    exit;
}

$adminPages = [
    'dashboard' => __DIR__ . '/../modules/admin/dashboard.php',
    'recent-activity' => __DIR__ . '/../modules/admin/recent-activity.php',
    'pending-approvals' => __DIR__ . '/../modules/admin/pending-approvals.php',
    'users' => __DIR__ . '/../modules/admin/users.php',
    'system-config' => __DIR__ . '/../modules/admin/system-config.php',
    'correction-requests' => __DIR__ . '/../modules/admin/correction-requests.php',
    'reviewed-correction-requests' => __DIR__ . '/../modules/admin/reviewed-correction-requests.php',
    'divisions' => __DIR__ . '/../modules/admin/divisions.php',
    'goods-requests' => __DIR__ . '/../modules/admin/goods-requests.php',
    'reviewed-stock-quota-requests' => __DIR__ . '/../modules/admin/reviewed-stock-quota-requests.php',
    'reviewed-beneficiary-requests' => __DIR__ . '/../modules/admin/reviewed-stock-quota-requests.php',
    'item-requests' => __DIR__ . '/../modules/admin/item-requests.php',
    'direct-aid-request' => __DIR__ . '/../modules/social-service-officer/aid-requests.php',
    'direct-distribution' => __DIR__ . '/../modules/social-service-officer/aid-requests.php',
    'direct-aid-release-queue' => __DIR__ . '/../modules/admin/direct-aid-release-queue.php',
    // Retain old Admin history links; the queue and completed history now share a page.
    'my-aid-requests' => __DIR__ . '/../modules/admin/direct-aid-release-queue.php',
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
    'goods-request-document' => __DIR__ . '/../modules/shared/goods-request-document.php',
];

$socialOfficerPages = [
    'dashboard' => __DIR__ . '/../modules/social-service-officer/dashboard.php',
    'recent-activity' => __DIR__ . '/../modules/social-service-officer/recent-activity.php',
    'pool-quota' => __DIR__ . '/../modules/social-service-officer/pool-quota.php',
    'assigned-stock-quotas' => __DIR__ . '/../modules/social-service-officer/assigned-stock-quotas.php',
    'new-aid-request' => __DIR__ . '/../modules/social-service-officer/aid-requests.php',
    'aid-requests' => __DIR__ . '/../modules/social-service-officer/aid-requests.php',
    'distribute-aid' => __DIR__ . '/../modules/social-service-officer/distribute-aid.php',
    'sso-distribution-history' => __DIR__ . '/../modules/social-service-officer/distribution-history.php',
    'pending-handover' => __DIR__ . '/../modules/social-service-officer/pending-handover.php',
    'request-status-report' => __DIR__ . '/../modules/social-service-officer/request-status-report.php',
    'beneficiaries' => __DIR__ . '/../modules/social-service-officer/beneficiaries.php',
    'process-return' => __DIR__ . '/../modules/shared/process-return.php',
    'return-history' => __DIR__ . '/../modules/shared/process-return.php',
    'goods-request-document' => __DIR__ . '/../modules/shared/goods-request-document.php',
];

$subjectOfficerPages = [
    'dashboard' => __DIR__ . '/../modules/subject-officer/dashboard.php',
    'recent-activity' => __DIR__ . '/../modules/subject-officer/recent-activity.php',
    'request-goods' => __DIR__ . '/../modules/subject-officer/request-goods.php',
    'optical-aid-requests' => __DIR__ . '/../modules/subject-officer/optical-aid-requests.php',
    'approved-aid-bundles' => __DIR__ . '/../modules/subject-officer/optical-aid-requests.php',
    'vision-camp-stock-requests' => __DIR__ . '/../modules/subject-officer/vision-camp-stock-requests.php',
    'my-goods-requests' => __DIR__ . '/../modules/subject-officer/my-goods-requests.php',
    'my-beneficiary-requests' => __DIR__ . '/../modules/subject-officer/my-goods-requests.php',
    'aid-distribution' => __DIR__ . '/../modules/subject-officer/aid-requests.php',
    // Retain the old URL as a safe alias; direct beneficiary registration is retired.
    'beneficiaries' => __DIR__ . '/../modules/social-service-officer/aid-requests.php',
    'direct-aid-request' => __DIR__ . '/../modules/social-service-officer/aid-requests.php',
    'my-aid-requests' => __DIR__ . '/../modules/social-service-officer/aid-requests.php',
    'distribute-items' => __DIR__ . '/../modules/subject-officer/distribute-items.php',
    'distribution-history' => __DIR__ . '/../modules/subject-officer/distribution-history.php',
    'returns' => __DIR__ . '/../modules/shared/process-return.php',
    'return-history' => __DIR__ . '/../modules/shared/process-return.php',
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
    'officer-pools' => __DIR__ . '/../modules/admin/officer-pools.php',
    'reports' => __DIR__ . '/../modules/admin/reports.php',
    'audit-log' => __DIR__ . '/../modules/subject-officer/workspace.php',
    'goods-request-document' => __DIR__ . '/../modules/shared/goods-request-document.php',
];

$requestedPage = (string) ($_GET['page'] ?? 'dashboard');

$storeKeeperPages = [
    'dashboard' => __DIR__ . '/../modules/store-keeper/dashboard.php',
    'recent-activity' => __DIR__ . '/../modules/store-keeper/recent-activity.php',
    'receive-items' => __DIR__ . '/../modules/store-keeper/receive-items.php',
    'vision-camp-stock' => __DIR__ . '/../modules/store-keeper/vision-camp-stock.php',
    'receipt-history' => __DIR__ . '/../modules/store-keeper/receipt-history.php',
    'receipt-payment-details' => __DIR__ . '/../modules/store-keeper/receipt-payment-details.php',
    'item-payment-details' => __DIR__ . '/../modules/store-keeper/item-payment-details.php',
    'item-power-details' => __DIR__ . '/../modules/store-keeper/item-power-details.php',
    'current-stock' => __DIR__ . '/../modules/store-keeper/current-stock.php',
    'correction-requests' => __DIR__ . '/../modules/store-keeper/correction-requests.php',
    'correction-reference-value' => __DIR__ . '/../modules/store-keeper/correction-reference-value.php',
    'request-history' => __DIR__ . '/../modules/store-keeper/request-history.php',
    'approved-dispatches' => __DIR__ . '/../modules/store-keeper/approved-dispatches.php',
    'return-stock-review' => __DIR__ . '/../modules/store-keeper/return-stock-review.php',
    'recent-dispatches' => __DIR__ . '/../modules/store-keeper/recent-dispatches.php',
    'goods-request-document' => __DIR__ . '/../modules/shared/goods-request-document.php',
];

$campWorkspace = __DIR__ . '/../modules/shared/spectacle-camps.php';
foreach (['spectacle-camps','spectacle-camp-approvals','spectacle-camp-stock-approvals','spectacle-camp-participants'] as $page) $adminPages[$page] = $campWorkspace;
$adminPages['spectacle-camp-approvals'] = __DIR__ . '/../modules/admin/vision-camp-requests.php';
foreach (['spectacle-camps','spectacle-camp-new','my-spectacle-camps','spectacle-camp-register','spectacle-camp-participants'] as $page) $subjectOfficerPages[$page] = $campWorkspace;
$storeKeeperPages['spectacle-camp-releases'] = $campWorkspace;
foreach (['spectacle-camps','spectacle-camp-participants'] as $page) $socialOfficerPages[$page] = $campWorkspace;

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
    'assets/css/admin-dashboard.css?v=' . (string) filemtime(__DIR__ . '/assets/css/admin-dashboard.css'),
    $dashboardHtml
) ?? $dashboardHtml;
$dashboardHtml = preg_replace(
    '/assets\/js\/admin-dashboard\.js(?:\?v=\d+)?/',
    'assets/js/admin-dashboard.js?v=' . (string) filemtime(__DIR__ . '/assets/js/admin-dashboard.js'),
    $dashboardHtml
) ?? $dashboardHtml;

if ($language !== 'en') {
    $dashboardHtml = str_replace('</body>', widmsUiTranslationAssetsHtml() . '</body>', $dashboardHtml);
}

// Delegated fallback for every dashboard module: it also works when a module
// serves an older cached copy of the shared JavaScript bundle.
$alertDismissFallback = '<script>(function(){document.addEventListener("click",function(e){var b=e.target&&e.target.closest?e.target.closest(".notification-close"):null;if(b){e.preventDefault();e.stopPropagation();var n=b.closest(".alert-success,.alert-danger");if(n){n.hidden=true;n.style.display="none";if(n.parentNode)n.parentNode.removeChild(n);}}},true);})();</script>';
$dashboardHtml = str_replace('</body>', $alertDismissFallback . '</body>', $dashboardHtml);
$dashboardHtml = str_replace('</body>', '<script>document.querySelectorAll(".alert-success,.alert-danger").forEach(function(a){a.classList.add("widms-dismissible-alert");if(a.querySelector(".notification-close"))return;var b=document.createElement("button");b.type="button";b.className="notification-close";b.setAttribute("aria-label","Close");b.textContent="×";a.appendChild(b);});</script></body>', $dashboardHtml);
// Receipt History uses the same shared outline action and data-card markup as
// the other dashboard pages. Keep the popup enhancement here so the details
// page remains a normal reusable data-card when opened directly as well.
$receiptHistoryEnhancement = '<script>(function(){if(!document.body.classList.contains("store-receipt-history-page"))return;document.querySelectorAll(".admin-data-table tbody tr").forEach(function(row){var first=row.querySelector("td"),action=row.lastElementChild;if(!first||!action||!/^BAT-\\d+/.test(first.textContent)||action.querySelector(".receipt-history-button"))return;var id=first.textContent.replace(/\\D/g,"");var button=document.createElement("button");button.type="button";button.className="outline-action receipt-history-button";button.textContent="History";button.addEventListener("click",function(){fetch("dashboard.php?page=receipt-payment-details&receipt_id="+id).then(function(response){if(!response.ok)throw new Error();return response.text()}).then(function(html){var doc=new DOMParser().parseFromString(html,"text/html"),card=doc.querySelector(".admin-data-card"),modal=document.createElement("div");modal.className="history-modal";modal.innerHTML="<div class=history-modal-card><button type=button class=history-modal-close aria-label=Close>×</button>"+(card?card.outerHTML:"<p>Payment history could not be loaded.</p>")+"</div>";document.body.appendChild(modal);modal.querySelector(".history-modal-close").onclick=function(){modal.remove()};modal.addEventListener("click",function(event){if(event.target===modal)modal.remove()});}).catch(function(){var modal=document.createElement("div");modal.className="history-modal";modal.innerHTML="<div class=history-modal-card><button type=button class=history-modal-close aria-label=Close>×</button><p>Payment history could not be loaded.</p></div>";document.body.appendChild(modal);modal.querySelector(".history-modal-close").onclick=function(){modal.remove()};});});action.appendChild(button);});})();</script>';
$dashboardHtml = str_replace('</body>', $receiptHistoryEnhancement . '</body>', $dashboardHtml);
$dashboardHtml = str_replace('</body>', '<script src="assets/js/form-submit-guard.js?v='
    . filemtime(__DIR__ . '/assets/js/form-submit-guard.js') . '"></script></body>', $dashboardHtml);
$replayNotice = (string) ($_SESSION['widms_form_replay_notice'] ?? '');
unset($_SESSION['widms_form_replay_notice']);
if ($replayNotice !== '') {
    $noticeHtml = '<div class="alert alert-warning" role="alert">'
        . htmlspecialchars($replayNotice, ENT_QUOTES, 'UTF-8') . '</div>';
    $dashboardHtml = preg_replace_callback('/<main\b[^>]*>/i',
        static fn(array $match): string => $match[0] . $noticeHtml, $dashboardHtml, 1) ?? $dashboardHtml;
}
$dashboardHtml = widmsAddFormSubmissionFields($dashboardHtml);
echo $dashboardHtml;
