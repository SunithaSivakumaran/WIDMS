<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}
if (!in_array($argv[1] ?? '', ['--check', '--apply'], true)
    || !ctype_digit((string) ($argv[2] ?? '')) || (int) $argv[2] < 1) {
    throw new InvalidArgumentException('Usage: php scripts/reopen-legacy-central-return.php --check|--apply RETURN_ID');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/notification-sms.php';
require_once __DIR__ . '/../includes/activity.php';

$apply = $argv[1] === '--apply';
$returnId = (int) $argv[2];
$database = database();
$database->beginTransaction();
try {
    $statement = $database->prepare(
        "SELECT ret.id, ret.quantity, ret.restore_to, ret.item_condition,
                ret.stock_review_status, ret.stock_reviewed_by, ret.stock_reviewed_at,
                distribution.item_id, aid_request.prescribed_power,
                item.item_name, item.variety, category.name AS category_name
         FROM item_returns ret
         JOIN distributions distribution ON distribution.id = ret.distribution_id
         LEFT JOIN aid_requests aid_request ON aid_request.id = distribution.aid_request_id
         JOIN inventory_items item ON item.id = distribution.item_id
         JOIN item_categories category ON category.id = item.category_id
         WHERE ret.id = :id FOR UPDATE"
    );
    $statement->execute(['id' => $returnId]);
    $return = $statement->fetch();
    if (!$return || $return['restore_to'] !== 'central-stock' || $return['item_condition'] !== 'good') {
        throw new RuntimeException('Only a good return already credited to Central Stock can be reopened.');
    }
    if ($return['stock_review_status'] === 'pending') {
        $database->rollBack();
        echo "RET-$returnId is already pending Store Keeper review; no stock changed.\n";
        exit(0);
    }
    if ($return['stock_review_status'] !== 'accepted'
        || $return['stock_reviewed_by'] !== null || $return['stock_reviewed_at'] !== null) {
        throw new RuntimeException('A manually reviewed or rejected return cannot be reopened.');
    }
    $quantity = (int) $return['quantity'];
    $itemId = (int) $return['item_id'];
    $statement = $database->prepare('SELECT quantity FROM inventory_items WHERE id = :id FOR UPDATE');
    $statement->execute(['id' => $itemId]);
    $stock = $statement->fetchColumn();
    if ($stock === false || (int) $stock < $quantity) {
        throw new RuntimeException('Central Stock is too low to reverse this legacy credit safely.');
    }
    if (widmsIsOpticalItem((string) $return['item_name'], (string) $return['variety'], (string) $return['category_name'])
        && $return['prescribed_power'] !== null
        && widmsOpticalPowerAvailable($database, $itemId, (float) $return['prescribed_power'], true) < $quantity) {
        throw new RuntimeException('The exact optical power is no longer available to reverse safely.');
    }

    if (!$apply) {
        $database->rollBack();
        echo "RET-$returnId can be reopened: $quantity unit(s) will be reserved from Central Stock. No data changed.\n";
        exit(0);
    }

    $statement = $database->prepare('UPDATE inventory_items SET quantity = quantity - :quantity WHERE id = :id AND quantity >= :quantity_check');
    $statement->execute(['quantity' => $quantity, 'id' => $itemId, 'quantity_check' => $quantity]);
    if ($statement->rowCount() !== 1) {
        throw new RuntimeException('Central Stock changed before it could be reserved.');
    }
    $statement = $database->prepare(
        "UPDATE item_returns SET stock_review_status = 'pending', stock_review_note = NULL
         WHERE id = :id AND stock_review_status = 'accepted' AND stock_reviewed_by IS NULL"
    );
    $statement->execute(['id' => $returnId]);
    if ($statement->rowCount() !== 1) {
        throw new RuntimeException('The return changed before it could be reopened.');
    }

    $keepers = $database->query("SELECT id FROM users WHERE role = 'store-keeper' AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
    if (!$keepers) {
        throw new RuntimeException('No active Store Keeper can receive this review request.');
    }
    $key = 'return-review-' . $returnId;
    $reference = 'RET-' . str_pad((string) $returnId, 4, '0', STR_PAD_LEFT);
    $selectNotification = $database->prepare('SELECT id FROM user_notifications WHERE user_id = :user AND notification_key = :notification_key');
    $queueSms = $database->prepare(
        'INSERT INTO notification_sms_outbox (user_id, notification_key, message)
         VALUES (:user, :notification_key, :message) ON DUPLICATE KEY UPDATE id = id'
    );
    foreach ($keepers as $keeperId) {
        notifyUser($database, (int) $keeperId, $key, 'Return awaiting stock acceptance',
            'Good return awaiting acceptance', "$reference needs review before Central Stock is updated.",
            'dashboard.php?page=return-stock-review');
        $selectNotification->execute(['user' => (int) $keeperId, 'notification_key' => $key]);
        $notificationId = (int) $selectNotification->fetchColumn();
        $queueSms->execute([
            'user' => (int) $keeperId,
            'notification_key' => 'user-notification-' . $notificationId,
            'message' => widmsNotificationSmsText('store-keeper', 'Return awaiting stock acceptance', $reference),
        ]);
    }
    $database->commit();
    logActivity('Returns', 'Reopened previously restocked return for Store Keeper acceptance', $reference, 'pending');
    echo "$reference reopened; $quantity unit(s) removed from available Central Stock. Store Keepers notified and SMS queued.\n";
} catch (Throwable $exception) {
    if ($database->inTransaction()) $database->rollBack();
    throw $exception;
}
