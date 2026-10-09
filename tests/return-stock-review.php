<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$database = database();
$columns = $database->query("SHOW COLUMNS FROM item_returns LIKE 'stock_review_status'")->fetchAll();
if (count($columns) !== 1) {
    throw new RuntimeException('Return stock review migration is missing.');
}
$statement = $database->query("SELECT id, full_name FROM users WHERE role = 'store-keeper' AND status = 'active' LIMIT 1");
$keeper = $statement->fetch();
if (!$keeper) {
    echo "SKIP: no active Store Keeper account for page smoke test.\n";
    exit(0);
}

$_SESSION['user_id'] = (int) $keeper['id'];
$_SESSION['role'] = 'store-keeper';
$_SESSION['full_name'] = (string) $keeper['full_name'];
$_GET['page'] = 'return-stock-review';
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
require __DIR__ . '/../public/dashboard.php';
$html = (string) ob_get_clean();
if (!str_contains($html, 'Return Stock Review') || !str_contains($html, 'return-review-status')) {
    throw new RuntimeException('Store Keeper review page did not render.');
}
$pending = (int) $database->query("SELECT COUNT(*) FROM item_returns WHERE stock_review_status = 'pending' AND restore_to = 'central-stock'")->fetchColumn();
if ($pending > 0 && (!str_contains($html, 'name="decision" value="accept"')
    || !str_contains($html, 'data-submit-value="reject"')
    || !str_contains($html, 'data-reason-field="stock_review_note"')
    || !str_contains($html, 'admin-reason-dialog.js'))) {
    throw new RuntimeException('Pending returns must show manual Accept and Reject actions.');
}
$statement = $database->query("SELECT id, full_name FROM users WHERE role = 'subject-officer' AND status = 'active' LIMIT 1");
$subject = $statement->fetch();
if ($subject) {
    $_SESSION['user_id'] = (int) $subject['id'];
    $_SESSION['role'] = 'subject-officer';
    $_SESSION['full_name'] = (string) $subject['full_name'];
    $_GET['page'] = 'return-history';
    ob_start();
    require __DIR__ . '/../public/dashboard.php';
    $history = (string) ob_get_clean();
    if (!str_contains($history, 'Stock Status') || !str_contains($history, 'return-history-condition')) {
        throw new RuntimeException('Subject Officer return status history did not render.');
    }
}
echo "PASS: review schema and return history page loads.\n";
