<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$statement = database()->query("SELECT id, full_name FROM users WHERE role = 'store-keeper' AND status = 'active' LIMIT 1");
$keeper = $statement->fetch();
if (!$keeper) { echo "SKIP: no active Store Keeper account.\n"; exit(0); }

$_SESSION['user_id'] = (int) $keeper['id'];
$_SESSION['role'] = 'store-keeper';
$_SESSION['full_name'] = (string) $keeper['full_name'];
$_GET['page'] = 'request-history';
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
require __DIR__ . '/../public/dashboard.php';
$html = (string) ob_get_clean();
if (!str_contains($html, '<h1>Correction Request History</h1>')
    || str_contains($html, '<h2>My Correction Requests</h2>')
    || !str_contains($html, 'id="correction-history-search"')
    || !str_contains($html, 'id="correction-history-status"')
    || !str_contains($html, 'id="correction-history-type"')) {
    throw new RuntimeException('Correction Request History heading or filters did not render as expected.');
}
echo "PASS: correction history heading and filters render without a duplicate heading.\n";
