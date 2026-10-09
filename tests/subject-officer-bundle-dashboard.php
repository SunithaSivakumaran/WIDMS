<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/approved-aid-bundle-availability.php';

$officer = database()->query(
    "SELECT id, full_name FROM users WHERE role = 'subject-officer' AND status = 'active' LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);
if (!$officer) {
    echo "SKIP: no active Subject Officer account.\n";
    exit(0);
}

$_SESSION['user_id'] = (int) $officer['id'];
$_SESSION['role'] = 'subject-officer';
$_SESSION['full_name'] = (string) $officer['full_name'];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['page'] = 'dashboard';

$ready = count(widmsApprovedAidBundleAvailability(database())['available']);
ob_start();
require __DIR__ . '/../public/dashboard.php';
$html = (string) ob_get_clean();

if (!str_contains($html, 'href="dashboard.php?page=approved-aid-bundles"')) {
    throw new RuntimeException('Dashboard link does not open Approved Aid Bundles.');
}
if ($ready > 0 && !str_contains($html, 'class="approved-needs-ready-count"')) {
    throw new RuntimeException('Dashboard is missing the ready-to-bundle count.');
}
if ($ready > 0 && !preg_match('/href="dashboard\.php\?page=approved-aid-bundles"[^>]*>(?:(?!<\/a>).)*class="sidebar-distribution-count"[^>]*>' . $ready . '<\/span>/s', $html)) {
    throw new RuntimeException('Approved Aid Bundles sidebar badge does not match the selectable count.');
}
if ($ready === 0 && str_contains($html, 'class="approved-needs-ready-count"')) {
    throw new RuntimeException('Dashboard shows a ready badge when no requests are selectable.');
}
echo "PASS: dashboard bundle link and ready count match bundle availability ($ready ready).\n";
