<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/notification-action-state.php';

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
foreach ([
    'CREATE TABLE spectacle_camps (id INTEGER PRIMARY KEY, status TEXT)',
    'CREATE TABLE spectacle_camp_stock_requests (id INTEGER PRIMARY KEY, camp_id INTEGER, batch_ref TEXT, status TEXT, created_at TEXT)',
    'CREATE TABLE spectacle_camp_receipts (id INTEGER PRIMARY KEY, camp_id INTEGER, created_at TEXT, spectacle_category_id INTEGER, quantity INTEGER, stock_receipt_id INTEGER)',
    'CREATE TABLE spectacle_camp_participants (id INTEGER PRIMARY KEY, camp_id INTEGER, status TEXT, spectacle_category_id INTEGER)',
    'CREATE TABLE spectacle_camp_distributions (participant_id INTEGER)',
    'CREATE TABLE stock_receipt_spectacle_lines (stock_receipt_id INTEGER, spectacle_category_id INTEGER, quantity INTEGER)',
    'CREATE TABLE goods_requests (id INTEGER PRIMARY KEY, request_batch_ref TEXT, aid_request_id INTEGER, status TEXT)',
    'CREATE TABLE admin_direct_releases (aid_request_id INTEGER PRIMARY KEY, status TEXT)',
    'CREATE TABLE item_returns (id INTEGER PRIMARY KEY, stock_review_status TEXT)',
    'CREATE TABLE aid_requests (id INTEGER PRIMARY KEY, status TEXT)',
    'CREATE TABLE distributions (aid_request_id INTEGER)',
    'CREATE TABLE goods_fulfillments (aid_request_id INTEGER)',
    'CREATE TABLE goods_request_aid_requests (goods_request_id INTEGER, aid_request_id INTEGER)',
] as $schema) {
    $db->exec($schema);
}

$assert = static function (string $key, bool $expected) use ($db): void {
    $actual = notificationActionIsPending($db, $key);
    if ($actual !== $expected) {
        throw new RuntimeException($key . ': expected ' . ($expected ? 'visible' : 'hidden'));
    }
};

$db->exec("INSERT INTO spectacle_camps VALUES (1, 'pending')");
$assert('sc-1-requested', true);
$db->exec("UPDATE spectacle_camps SET status = 'approved' WHERE id = 1");
$assert('sc-1-requested', false);
$db->exec("INSERT INTO spectacle_camp_stock_requests VALUES (2, 1, 'SCB-ABC', 'pending', '2026-09-30 10:00:00')");
$assert('sc-1-stock-request-batch-SCB-ABC', true);
$db->exec("UPDATE spectacle_camp_stock_requests SET status = 'approved' WHERE id = 2");
$assert('sc-1-stock-request-batch-SCB-ABC', false);
$assert('sc-1-release-needed-batch-SCB-ABC', true);
$assert('sc-1-release-needed-2', true);
$db->exec("UPDATE spectacle_camp_stock_requests SET status = 'released' WHERE id = 2");
$assert('sc-1-release-needed-batch-SCB-ABC', false);
$assert('sc-1-release-needed-2', false);
$db->exec("INSERT INTO spectacle_camp_receipts VALUES (8, 1, '2026-10-01 09:00:00', 2, 1, NULL)");
$assert('sc-1-received-8', true);
$db->exec("INSERT INTO spectacle_camp_stock_requests VALUES (9, 1, 'SCB-DEF', 'pending', '2026-10-01 10:00:00')");
$assert('sc-1-received-8', false);
$db->exec("INSERT INTO spectacle_camp_participants VALUES (10, 1, 'approved', 2)");
$assert('sc-1-completed', false);
$db->exec("INSERT INTO spectacle_camp_participants VALUES (11, 1, 'approved', 2)");
$assert('sc-1-completed', true);
$db->exec("INSERT INTO spectacle_camp_receipts VALUES (12, 1, '2026-10-01 11:00:00', 2, 1, NULL)");
$assert('sc-1-completed', false);
$assert('sc-1-released-batch-SCB-ABC', true);
$db->exec('INSERT INTO spectacle_camp_distributions VALUES (10)');
$db->exec('INSERT INTO spectacle_camp_distributions VALUES (11)');
$assert('sc-1-released-batch-SCB-ABC', false);

$db->exec("INSERT INTO goods_requests VALUES (3, 'GRB-A', NULL, 'pending-admin-approval')");
$assert('aid-bundle-grb-a', true);
$db->exec("UPDATE goods_requests SET status = 'approved-awaiting-dispatch' WHERE id = 3");
$assert('aid-bundle-grb-a', false);
$assert('goods-release-3-10', true);
$db->exec("UPDATE goods_requests SET status = 'dispatched' WHERE id = 3");
$assert('goods-release-3-10', false);

$db->exec("INSERT INTO admin_direct_releases VALUES (4, 'awaiting-store-keeper')");
$assert('admin-direct-release-4', true);
$db->exec("UPDATE admin_direct_releases SET status = 'released-to-admin' WHERE aid_request_id = 4");
$assert('admin-direct-release-4', false);
$assert('admin-direct-ready-4', true);
$db->exec("UPDATE admin_direct_releases SET status = 'distributed' WHERE aid_request_id = 4");
$assert('admin-direct-ready-4', false);

$db->exec("INSERT INTO item_returns VALUES (5, 'pending')");
$assert('return-review-5', true);
$db->exec("UPDATE item_returns SET stock_review_status = 'accepted' WHERE id = 5");
$assert('return-review-5', false);

$db->exec("INSERT INTO aid_requests VALUES (6, 'approved')");
$assert('approved-aid-ready-6', true);
$assert('approved-subject-distribution-6', true);
$assert('approved-optical-routing-6', true);
$assert('approved-aid-stock-needed-6', true);
$db->exec("INSERT INTO goods_requests VALUES (7, 'GRB-B', 6, 'pending-admin-approval')");
$assert('approved-aid-stock-needed-6', false);
$db->exec('DELETE FROM goods_requests WHERE id = 7');
$db->exec('INSERT INTO distributions VALUES (6)');
$assert('approved-aid-ready-6', false);

// Information-only updates stay visible until opened, even after later actions.
$assert('aid-request-decision-6', true);
$assert('sc-1-stock-decision-batch-SCB-ABC', true);

echo "Notification action-state checks passed.\n";
