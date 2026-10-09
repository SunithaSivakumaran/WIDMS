<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$page = (string) ($argv[1] ?? '');
$pages = ['approved-aid-bundles', 'distribute-items', 'distribution-history', 'my-goods-requests', 'goods-request-document'];
if (!in_array($page, $pages, true)) {
    throw new InvalidArgumentException('Choose a Subject Officer queue page.');
}

$officer = database()->query(
    "SELECT id, username, full_name FROM users WHERE role = 'subject-officer' AND status = 'active' ORDER BY id LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);
if (!$officer) {
    echo "SKIP: no active Subject Officer account.\n";
    exit(0);
}

$_SESSION['user_id'] = (int) $officer['id'];
$_SESSION['role'] = 'subject-officer';
$_SESSION['username'] = (string) $officer['username'];
$_SESSION['full_name'] = (string) $officer['full_name'];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['page'] = $page;
if ($page === 'goods-request-document') {
    $requestStatement = database()->prepare(
        'SELECT id FROM goods_requests ORDER BY (requested_by <> :officer_id) DESC, id DESC LIMIT 1'
    );
    $requestStatement->execute(['officer_id' => (int) $officer['id']]);
    $requestId = $requestStatement->fetchColumn();
    if (!$requestId) {
        echo "SKIP: no goods request document to open.\n";
        exit(0);
    }
    $_GET['request_id'] = (int) $requestId;
}
$testPage = $page;
$testUsername = (string) $officer['username'];

ob_start();
require __DIR__ . '/../public/dashboard.php';
$html = (string) ob_get_clean();

if ($html === '' || str_contains($html, 'temporarily unavailable') || str_contains($html, 'Stock quota request history is unavailable')) {
    throw new RuntimeException("Subject Officer page failed to load: {$testPage}");
}
if ($testPage === 'my-goods-requests'
    && (!str_contains($html, 'Pending SSO Stock Assignments') || !str_contains($html, 'My Request History'))) {
    throw new RuntimeException('Shared assignment queue and personal history must be separate.');
}
if ($testPage === 'my-goods-requests') {
    $pendingIds = database()->query(
        "SELECT g.id FROM goods_requests g
         WHERE g.status = 'dispatched' AND g.allocated_to_sso_at IS NULL
           AND g.destination_sso_id IS NOT NULL AND g.aid_request_id IS NULL
           AND NOT EXISTS (SELECT 1 FROM goods_request_aid_requests linked WHERE linked.goods_request_id = g.id)"
    )->fetchAll(PDO::FETCH_COLUMN);
    foreach ($pendingIds as $pendingId) {
        if (!str_contains($html, 'id="pending-assignment-' . (int) $pendingId . '"')) {
            throw new RuntimeException('A shared pending assignment is missing from the queue.');
        }
    }
}
if ($testPage === 'distribute-items') {
    $fulfillmentIds = database()->query(
        "SELECT f.id FROM goods_fulfillments f JOIN aid_requests ar ON ar.id = f.aid_request_id
         WHERE f.status = 'with-subject-officer' AND ar.status = 'approved'"
    )->fetchAll(PDO::FETCH_COLUMN);
    foreach ($fulfillmentIds as $fulfillmentId) {
        if (!str_contains($html, 'id="fulfillment-' . (int) $fulfillmentId . '"')) {
            throw new RuntimeException('A released item is missing from the shared distribution queue.');
        }
    }
}

echo "PASS: {$testPage} loads for {$testUsername}.\n";
