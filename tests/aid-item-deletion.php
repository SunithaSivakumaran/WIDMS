<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/aid-item-deletion.php';

$database = database();
$itemIds = $database->query(
    'SELECT item_id FROM aid_requests
     UNION SELECT item_id FROM stock_receipts
     UNION SELECT item_id FROM distributions
     LIMIT 20'
)->fetchAll(PDO::FETCH_COLUMN);

foreach ($itemIds as $itemId) {
    if (!aidItemHasRecordedUse($database, (int) $itemId)) {
        throw new RuntimeException('An aid item used by a request, receipt, or distribution was considered deletable.');
    }
}

try {
    aidItemHasRecordedUse($database, 0);
    throw new RuntimeException('Invalid aid item ID was accepted.');
} catch (InvalidArgumentException) {
    // Expected.
}

echo count($itemIds) . " referenced aid item(s) protected; no database changes made.\n";
