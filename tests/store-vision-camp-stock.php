<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../config/database.php';

$_SESSION['role'] = 'store-keeper';
$_SESSION['full_name'] = 'Store Keeper Test';

$expected = array_map('intval', database()->query("SELECT id FROM spectacle_camps WHERE status='completed' AND conducted_at IS NOT NULL ORDER BY id")->fetchAll(PDO::FETCH_COLUMN));
ob_start();
require __DIR__ . '/../modules/store-keeper/vision-camp-stock.php';
$html = (string) ob_get_clean();

preg_match_all('/<tr data-camp-stock-row[^>]*>\s*<td[^>]*><strong>VC-(\d+)<\/strong>/s', $html, $matches);
$rendered = array_map('intval', $matches[1]);
sort($rendered, SORT_NUMERIC);
if ($rendered !== $expected) {
    throw new RuntimeException('Store Keeper stock must show each completed camp exactly once.');
}
if (!str_contains($html, '<th>Camp / Location</th><th>Stock Status</th><th>Action</th>')
    || str_contains($html, '<th>Spectacle Type</th>')
    || str_contains($html, '<th>Selected</th>')) {
    throw new RuntimeException('Store Keeper stock still shows the spectacle-type breakdown.');
}

echo "Store Keeper Vision Camp Stock: one compact row per completed camp.\n";
