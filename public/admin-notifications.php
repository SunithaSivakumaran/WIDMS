<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

$role = (string) ($_SESSION['role'] ?? '');
if (!isLoggedIn() || !in_array($role, ['admin', 'store-keeper', 'subject-officer', 'social-service-officer'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notificationId = filter_input(INPUT_POST, 'notification_id', FILTER_VALIDATE_INT);
    $notificationKey = trim((string) ($_POST['notification_key'] ?? ''));
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');

    $allowedDynamicPattern = match ($role) {
        'store-keeper' => '/^(?:dispatch|payment)-\d+$/',
        'subject-officer' => '/^fulfillment-\d+$/',
        'social-service-officer' => '/^sso-handover-\d+$/',
        default => '/(?!)/',
    };
    $allowedDynamicKey = preg_match($allowedDynamicPattern, $notificationKey) === 1;
    if ((!$notificationId && !$allowedDynamicKey) || !verifyCsrfToken($csrfToken)) {
        http_response_code(422);
        echo json_encode(['error' => 'Invalid notification request.']);
        exit;
    }

    try {
        $database = database();
        if ($notificationId) {
            $statement = $database->prepare(
                'UPDATE user_notifications
                 SET read_at = COALESCE(read_at, NOW())
                 WHERE id = :id AND user_id = :user_id'
            );
            $statement->execute([
                'id' => $notificationId,
                'user_id' => (int) $_SESSION['user_id'],
            ]);

            if ($statement->rowCount() < 1) {
                http_response_code(404);
                echo json_encode(['error' => 'Notification not found.']);
                exit;
            }
        } else {
            $statement = $database->prepare(
                'INSERT INTO notification_reads (user_id, notification_key)
                 VALUES (:user_id, :notification_key)
                 ON DUPLICATE KEY UPDATE read_at = read_at'
            );
            $statement->execute([
                'user_id' => (int) $_SESSION['user_id'],
                'notification_key' => $notificationKey,
            ]);
        }

        echo json_encode(['success' => true]);
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        http_response_code(503);
        echo json_encode(['error' => 'Unable to update notification.']);
    }
    exit;
}

$errorTypes = [
    'wrong-unit-cost' => 'Wrong unit cost',
    'wrong-quantity' => 'Wrong quantity',
    'wrong-bill-number' => 'Wrong bill / invoice number',
    'wrong-supplier' => 'Wrong supplier',
    'wrong-date' => 'Wrong date received',
    'wrong-cost' => 'Wrong cost',
    'wrong-item' => 'Wrong item',
    'wrong-payment-amount' => 'Wrong payment amount',
    'wrong-check-number' => 'Wrong check number',
    'wrong-payment-date' => 'Wrong payment date',
    'other' => 'Other',
];

try {
    $database = database();
    $items = [];
    $userId = (int) $_SESSION['user_id'];
    $respond = static function (array $notifications, int $total): never {
        usort($notifications, static fn(array $left, array $right): int => strcmp($right['created_at'], $left['created_at']));
        $notifications = array_slice($notifications, 0, 12);
        foreach ($notifications as &$notification) {
            unset($notification['created_at']);
        }
        unset($notification);
        echo json_encode(
            ['count' => $total, 'items' => $notifications, 'csrf_token' => csrfToken()],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        exit;
    };

    $persistentCountStatement = $database->prepare(
        "SELECT COUNT(*) FROM user_notifications
         WHERE user_id = :user_id AND read_at IS NULL
           AND COALESCE(target_url, '') NOT LIKE '%page=vision-camp%'
           AND COALESCE(target_url, '') NOT LIKE '%page=contact-lens-orders%'
           AND COALESCE(target_url, '') NOT LIKE '%page=pending-lens-handover%'"
    );
    $persistentCountStatement->execute(['user_id' => $userId]);
    $persistentCount = (int) $persistentCountStatement->fetchColumn();

    $persistentStatement = $database->prepare(
        "SELECT id, category, title, message, target_url, created_at
         FROM user_notifications
         WHERE user_id = :user_id AND read_at IS NULL
           AND COALESCE(target_url, '') NOT LIKE '%page=vision-camp%'
           AND COALESCE(target_url, '') NOT LIKE '%page=contact-lens-orders%'
           AND COALESCE(target_url, '') NOT LIKE '%page=pending-lens-handover%'
         ORDER BY created_at DESC, id DESC
         LIMIT 12"
    );
    $persistentStatement->execute(['user_id' => $userId]);
    foreach ($persistentStatement->fetchAll() as $notification) {
        $notificationId = (int) $notification['id'];
        $items[] = [
            'key' => 'user-notification-' . $notificationId,
            'notification_id' => $notificationId,
            'category' => t((string) $notification['category']),
            'title' => (string) $notification['title'],
            'detail' => t((string) $notification['message']),
            'submitted_by' => t('Reviewed by Administrator'),
            'created_label' => date('d M Y, H:i', strtotime((string) $notification['created_at'])),
            'created_at' => (string) $notification['created_at'],
            'url' => (string) $notification['target_url'],
        ];
    }

    if ($role === 'store-keeper') {
        $countStatement = $database->prepare(
            "SELECT
                (SELECT COUNT(*) FROM goods_requests g
                 WHERE g.status = 'approved-awaiting-dispatch'
                 AND NOT EXISTS (
                    SELECT 1 FROM notification_reads nr
                    WHERE nr.user_id = :dispatch_user
                    AND nr.notification_key = CONCAT('dispatch-', g.id)
                 )) +
                (SELECT COUNT(*) FROM stock_receipts sr
                 WHERE sr.balance_amount > 0
                 AND NOT EXISTS (
                    SELECT 1 FROM notification_reads nr
                    WHERE nr.user_id = :payment_user
                    AND nr.notification_key = CONCAT('payment-', sr.id)
                 ))"
        );
        $countStatement->execute([
            'dispatch_user' => $userId,
            'payment_user' => $userId,
        ]);
        $count = $persistentCount + (int) $countStatement->fetchColumn();
        $dispatchStatement = $database->prepare(
            "SELECT g.id, g.quantity, g.approved_at created_at, i.item_name
             FROM goods_requests g JOIN inventory_items i ON i.id = g.item_id
             WHERE g.status = 'approved-awaiting-dispatch'
             AND NOT EXISTS (
                SELECT 1 FROM notification_reads nr
                WHERE nr.user_id = :user_id
                AND nr.notification_key = CONCAT('dispatch-', g.id)
             )
             ORDER BY g.approved_at DESC, g.id DESC LIMIT 12"
        );
        $dispatchStatement->execute(['user_id' => $userId]);
        $dispatches = $dispatchStatement->fetchAll();
        foreach ($dispatches as $request) {
            $id = (int) $request['id'];
            $items[] = ['key' => 'dispatch-' . $id, 'notification_key' => 'dispatch-' . $id, 'category' => t('Approved stock quota'), 'title' => 'GR-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT) . ' · ' . $request['item_name'], 'detail' => t('Quantity') . ' ' . (int) $request['quantity'], 'submitted_by' => t('Ready for dispatch'), 'created_label' => date('d M Y, H:i', strtotime((string) $request['created_at'])), 'created_at' => (string) $request['created_at'], 'url' => 'dashboard.php?page=approved-dispatches#goods-request-' . $id];
        }
        $paymentStatement = $database->prepare(
            "SELECT sr.id, sr.bill_number, sr.balance_amount, sr.created_at, i.item_name
             FROM stock_receipts sr JOIN inventory_items i ON i.id = sr.item_id
             WHERE sr.balance_amount > 0
             AND NOT EXISTS (
                SELECT 1 FROM notification_reads nr
                WHERE nr.user_id = :user_id
                AND nr.notification_key = CONCAT('payment-', sr.id)
             )
             ORDER BY sr.created_at DESC, sr.id DESC LIMIT 12"
        );
        $paymentStatement->execute(['user_id' => $userId]);
        $payments = $paymentStatement->fetchAll();
        foreach ($payments as $payment) {
            $id = (int) $payment['id'];
            $items[] = ['key' => 'payment-' . $id, 'notification_key' => 'payment-' . $id, 'category' => t('Supplier payment reminder'), 'title' => 'BAT-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT) . ' · ' . $payment['item_name'], 'detail' => t('Balance') . ' Rs ' . number_format((float) $payment['balance_amount'], 2), 'submitted_by' => t('Bill') . ' ' . $payment['bill_number'], 'created_label' => date('d M Y, H:i', strtotime((string) $payment['created_at'])), 'created_at' => (string) $payment['created_at'], 'url' => 'dashboard.php?page=receipt-history#receipt-' . $id];
        }
        $respond($items, $count);
    }

    if ($role === 'subject-officer') {
        $countStatement = $database->prepare(
            "SELECT COUNT(*) FROM goods_fulfillments f
             WHERE f.subject_officer_id = :user AND f.status = 'with-subject-officer'
             AND NOT EXISTS (
                SELECT 1 FROM notification_reads nr
                WHERE nr.user_id = :read_user
                AND nr.notification_key = CONCAT('fulfillment-', f.id)
             )"
        );
        $countStatement->execute(['user' => $userId, 'read_user' => $userId]);
        $count = $persistentCount + (int) $countStatement->fetchColumn();
        $statement = $database->prepare(
            "SELECT f.id, f.created_at, i.item_name, b.full_name beneficiary_name
             FROM goods_fulfillments f
             JOIN aid_requests ar ON ar.id = f.aid_request_id
             JOIN inventory_items i ON i.id = ar.item_id
             JOIN beneficiaries b ON b.id = ar.beneficiary_id
             WHERE f.subject_officer_id = :user AND f.status = 'with-subject-officer'
             AND NOT EXISTS (
                SELECT 1 FROM notification_reads nr
                WHERE nr.user_id = :read_user
                AND nr.notification_key = CONCAT('fulfillment-', f.id)
             )
             ORDER BY f.created_at DESC, f.id DESC LIMIT 12"
        );
        $statement->execute(['user' => $userId, 'read_user' => $userId]);
        foreach ($statement->fetchAll() as $request) {
            $id = (int) $request['id'];
            $items[] = ['key' => 'fulfillment-' . $id, 'notification_key' => 'fulfillment-' . $id, 'category' => t('Goods received'), 'title' => 'FUL-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT) . ' · ' . $request['item_name'], 'detail' => t('For') . ' ' . $request['beneficiary_name'], 'submitted_by' => t('Distribution action required'), 'created_label' => date('d M Y, H:i', strtotime((string) $request['created_at'])), 'created_at' => (string) $request['created_at'], 'url' => 'dashboard.php?page=distribute-items#fulfillment-' . $id];
        }
        $respond($items, $count);
    }

    if ($role === 'social-service-officer') {
        $goodsCount = $database->prepare(
            "SELECT COUNT(*) FROM goods_fulfillments f
             WHERE f.sso_id = :user AND f.status = 'pending-sso-handover'
             AND NOT EXISTS (
                SELECT 1 FROM notification_reads nr
                WHERE nr.user_id = :read_user
                AND nr.notification_key = CONCAT('sso-handover-', f.id)
             )"
        );
        $goodsCount->execute(['user' => $userId, 'read_user' => $userId]);
        $count = $persistentCount + (int) $goodsCount->fetchColumn();
        $statement = $database->prepare(
            "SELECT f.id, f.created_at, i.item_name, b.full_name beneficiary_name
             FROM goods_fulfillments f
             JOIN aid_requests ar ON ar.id = f.aid_request_id
             JOIN inventory_items i ON i.id = ar.item_id
             JOIN beneficiaries b ON b.id = ar.beneficiary_id
             WHERE f.sso_id = :user AND f.status = 'pending-sso-handover'
             AND NOT EXISTS (
                SELECT 1 FROM notification_reads nr
                WHERE nr.user_id = :read_user
                AND nr.notification_key = CONCAT('sso-handover-', f.id)
             )
             ORDER BY f.created_at DESC, f.id DESC LIMIT 12"
        );
        $statement->execute(['user' => $userId, 'read_user' => $userId]);
        foreach ($statement->fetchAll() as $request) {
            $id = (int) $request['id'];
            $items[] = ['key' => 'sso-handover-' . $id, 'notification_key' => 'sso-handover-' . $id, 'category' => t('Pending handover'), 'title' => 'FUL-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT) . ' · ' . $request['item_name'], 'detail' => t('For') . ' ' . $request['beneficiary_name'], 'submitted_by' => t('Final distribution required'), 'created_label' => date('d M Y, H:i', strtotime((string) $request['created_at'])), 'created_at' => (string) $request['created_at'], 'url' => 'dashboard.php?page=pending-handover#fulfillment-' . $id];
        }
        $respond($items, $count);
    }

    $count = $persistentCount + (int) $database->query(
        "SELECT
            (SELECT COUNT(*) FROM correction_requests WHERE status = 'pending') +
            (SELECT COUNT(*) FROM registration_requests WHERE status = 'pending') +
            (SELECT COUNT(*) FROM aid_requests WHERE status = 'pending') +
            (SELECT COUNT(DISTINCT COALESCE(NULLIF(request_batch_ref, ''), CONCAT('GR-', id))) FROM goods_requests WHERE status = 'pending-admin-approval')"
    )->fetchColumn();

    $correctionStatement = $database->query(
        "SELECT c.id, c.record_reference, c.error_type, c.created_at, u.full_name AS submitted_name
         FROM correction_requests c
         JOIN users u ON u.id = c.submitted_by
         WHERE c.status = 'pending'
         ORDER BY c.created_at DESC, c.id DESC
         LIMIT 12"
    );
    foreach ($correctionStatement->fetchAll() as $request) {
        $requestId = (int) $request['id'];
        $items[] = [
            'key' => 'correction-' . $requestId,
            'category' => t('Correction request'),
            'title' => 'CR-' . str_pad((string) $requestId, 3, '0', STR_PAD_LEFT) . ' · ' . (string) $request['record_reference'],
            'detail' => t($errorTypes[$request['error_type']] ?? (string) $request['error_type']),
            'submitted_by' => t('Submitted by') . ' ' . (string) $request['submitted_name'],
            'created_label' => date('d M Y, H:i', strtotime((string) $request['created_at'])),
            'created_at' => (string) $request['created_at'],
            'url' => 'dashboard.php?page=correction-requests#correction-request-' . $requestId,
        ];
    }

    $registrationStatement = $database->query(
        "SELECT id, full_name, role, created_at
         FROM registration_requests
         WHERE status = 'pending'
         ORDER BY created_at DESC, id DESC
         LIMIT 12"
    );
    foreach ($registrationStatement->fetchAll() as $request) {
        $requestId = (int) $request['id'];
        $items[] = [
            'key' => 'registration-' . $requestId,
            'category' => t('User registration'),
            'title' => 'REG-' . str_pad((string) $requestId, 3, '0', STR_PAD_LEFT) . ' · ' . (string) $request['full_name'],
            'detail' => t(ucwords(str_replace('-', ' ', (string) $request['role']))),
            'submitted_by' => t('New account request'),
            'created_label' => date('d M Y, H:i', strtotime((string) $request['created_at'])),
            'created_at' => (string) $request['created_at'],
            'url' => 'dashboard.php?page=pending-approvals#registration-request-' . $requestId,
        ];
    }

    $aidStatement = $database->query(
        "SELECT ar.id, ar.quantity, ar.created_at, b.full_name AS beneficiary_name,
                i.item_name, u.full_name AS submitted_name
         FROM aid_requests ar
         JOIN beneficiaries b ON b.id = ar.beneficiary_id
         JOIN inventory_items i ON i.id = ar.item_id
         JOIN users u ON u.id = ar.submitted_by
         WHERE ar.status = 'pending'
         ORDER BY ar.created_at DESC, ar.id DESC
         LIMIT 12"
    );
    foreach ($aidStatement->fetchAll() as $request) {
        $requestId = (int) $request['id'];
        $items[] = [
            'key' => 'aid-' . $requestId,
            'category' => t('Aid request'),
            'title' => 'AR-' . str_pad((string) $requestId, 4, '0', STR_PAD_LEFT) . ' · ' . (string) $request['beneficiary_name'],
            'detail' => (string) $request['item_name'] . ' × ' . (int) $request['quantity'],
            'submitted_by' => t('Submitted by') . ' ' . (string) $request['submitted_name'],
            'created_label' => date('d M Y, H:i', strtotime((string) $request['created_at'])),
            'created_at' => (string) $request['created_at'],
            'url' => 'dashboard.php?page=item-requests&view=pending#aid-request-' . $requestId,
        ];
    }

    $goodsStatement = $database->query(
        "SELECT MIN(gr.id) id,
                COALESCE(NULLIF(gr.request_batch_ref, ''), CONCAT('GR-', gr.id)) batch_reference,
                COUNT(*) allocation_count, SUM(gr.quantity) total_quantity,
                MIN(gr.created_at) created_at, u.full_name AS requested_name
         FROM goods_requests gr
         JOIN users u ON u.id = gr.requested_by
         WHERE gr.status = 'pending-admin-approval'
         GROUP BY COALESCE(NULLIF(gr.request_batch_ref, ''), CONCAT('GR-', gr.id)), gr.requested_by, u.full_name
         ORDER BY created_at DESC, id DESC
         LIMIT 12"
    );
    foreach ($goodsStatement->fetchAll() as $request) {
        $requestId = (int) $request['id'];
        $items[] = [
            'key' => 'goods-' . $requestId,
            'category' => t('Stock quota request'),
            'title' => (string) $request['batch_reference'],
            'detail' => (int) $request['allocation_count'] . ' ' . t((int) $request['allocation_count'] === 1 ? 'Allocation' : 'Allocations') . ' - ' . (int) $request['total_quantity'] . ' ' . t('Total Units'),
            'submitted_by' => t('Requested by') . ' ' . (string) $request['requested_name'],
            'created_label' => date('d M Y, H:i', strtotime((string) $request['created_at'])),
            'created_at' => (string) $request['created_at'],
            'url' => 'dashboard.php?page=goods-requests#goods-request-' . $requestId,
        ];
    }

    usort($items, static fn(array $left, array $right): int => strcmp($right['created_at'], $left['created_at']));
    $items = array_slice($items, 0, 12);
    foreach ($items as &$item) {
        unset($item['created_at']);
    }
    unset($item);

    echo json_encode(
        ['count' => $count, 'items' => $items, 'csrf_token' => csrfToken()],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    http_response_code(503);
    echo json_encode(
        ['error' => 'Notifications are temporarily unavailable.'],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
}
