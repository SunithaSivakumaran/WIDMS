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
            ['count' => $total, 'items' => $notifications],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        exit;
    };

    if ($role === 'store-keeper') {
        $count = (int) $database->query(
            "SELECT
                (SELECT COUNT(*) FROM goods_requests WHERE status = 'approved-awaiting-dispatch') +
                (SELECT COUNT(*) FROM stock_receipts WHERE balance_amount > 0)"
        )->fetchColumn();
        $dispatches = $database->query(
            "SELECT g.id, g.quantity, g.approved_at created_at, i.item_name
             FROM goods_requests g JOIN inventory_items i ON i.id = g.item_id
             WHERE g.status = 'approved-awaiting-dispatch'
             ORDER BY g.approved_at DESC, g.id DESC LIMIT 12"
        )->fetchAll();
        foreach ($dispatches as $request) {
            $id = (int) $request['id'];
            $items[] = ['key' => 'dispatch-' . $id, 'category' => t('Approved stock release'), 'title' => 'GR-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT) . ' · ' . $request['item_name'], 'detail' => t('Quantity') . ' ' . (int) $request['quantity'], 'submitted_by' => t('Ready for dispatch'), 'created_label' => date('d M Y, H:i', strtotime((string) $request['created_at'])), 'created_at' => (string) $request['created_at'], 'url' => 'dashboard.php?page=approved-dispatches'];
        }
        $payments = $database->query(
            "SELECT sr.id, sr.bill_number, sr.balance_amount, sr.created_at, i.item_name
             FROM stock_receipts sr JOIN inventory_items i ON i.id = sr.item_id
             WHERE sr.balance_amount > 0 ORDER BY sr.created_at DESC, sr.id DESC LIMIT 12"
        )->fetchAll();
        foreach ($payments as $payment) {
            $id = (int) $payment['id'];
            $items[] = ['key' => 'payment-' . $id, 'category' => t('Supplier payment reminder'), 'title' => 'BAT-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT) . ' · ' . $payment['item_name'], 'detail' => t('Balance') . ' Rs ' . number_format((float) $payment['balance_amount'], 2), 'submitted_by' => t('Bill') . ' ' . $payment['bill_number'], 'created_label' => date('d M Y, H:i', strtotime((string) $payment['created_at'])), 'created_at' => (string) $payment['created_at'], 'url' => 'dashboard.php?page=receipt-history'];
        }
        $respond($items, $count);
    }

    if ($role === 'subject-officer') {
        $countStatement = $database->prepare("SELECT COUNT(*) FROM goods_fulfillments WHERE subject_officer_id = :user AND status = 'with-subject-officer'");
        $countStatement->execute(['user' => $userId]);
        $count = (int) $countStatement->fetchColumn();
        $statement = $database->prepare(
            "SELECT f.id, f.created_at, i.item_name, b.full_name beneficiary_name
             FROM goods_fulfillments f
             JOIN aid_requests ar ON ar.id = f.aid_request_id
             JOIN inventory_items i ON i.id = ar.item_id
             JOIN beneficiaries b ON b.id = ar.beneficiary_id
             WHERE f.subject_officer_id = :user AND f.status = 'with-subject-officer'
             ORDER BY f.created_at DESC, f.id DESC LIMIT 12"
        );
        $statement->execute(['user' => $userId]);
        foreach ($statement->fetchAll() as $request) {
            $id = (int) $request['id'];
            $items[] = ['key' => 'fulfillment-' . $id, 'category' => t('Goods received'), 'title' => 'FUL-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT) . ' · ' . $request['item_name'], 'detail' => t('For') . ' ' . $request['beneficiary_name'], 'submitted_by' => t('Distribution action required'), 'created_label' => date('d M Y, H:i', strtotime((string) $request['created_at'])), 'created_at' => (string) $request['created_at'], 'url' => 'dashboard.php?page=distribute-items'];
        }
        $respond($items, $count);
    }

    if ($role === 'social-service-officer') {
        $goodsCount = $database->prepare("SELECT COUNT(*) FROM goods_fulfillments WHERE sso_id = :user AND status = 'pending-sso-handover'");
        $goodsCount->execute(['user' => $userId]);
        $lensCount = $database->prepare("SELECT COUNT(*) FROM contact_lens_units WHERE sso_id = :user AND status = 'pending-handover'");
        $lensCount->execute(['user' => $userId]);
        $count = (int) $goodsCount->fetchColumn() + (int) $lensCount->fetchColumn();
        $statement = $database->prepare(
            "SELECT f.id, f.created_at, i.item_name, b.full_name beneficiary_name
             FROM goods_fulfillments f
             JOIN aid_requests ar ON ar.id = f.aid_request_id
             JOIN inventory_items i ON i.id = ar.item_id
             JOIN beneficiaries b ON b.id = ar.beneficiary_id
             WHERE f.sso_id = :user AND f.status = 'pending-sso-handover'
             ORDER BY f.created_at DESC, f.id DESC LIMIT 12"
        );
        $statement->execute(['user' => $userId]);
        foreach ($statement->fetchAll() as $request) {
            $id = (int) $request['id'];
            $items[] = ['key' => 'sso-handover-' . $id, 'category' => t('Pending handover'), 'title' => 'FUL-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT) . ' · ' . $request['item_name'], 'detail' => t('For') . ' ' . $request['beneficiary_name'], 'submitted_by' => t('Final distribution required'), 'created_label' => date('d M Y, H:i', strtotime((string) $request['created_at'])), 'created_at' => (string) $request['created_at'], 'url' => 'dashboard.php?page=pending-handover'];
        }
        $lensStatement = $database->prepare(
            "SELECT lu.id, lu.unit_code, lu.power, lu.created_at, b.full_name beneficiary_name
             FROM contact_lens_units lu
             JOIN aid_requests ar ON ar.id = lu.aid_request_id
             JOIN beneficiaries b ON b.id = ar.beneficiary_id
             WHERE lu.sso_id = :user AND lu.status = 'pending-handover'
             ORDER BY lu.created_at DESC, lu.id DESC LIMIT 12"
        );
        $lensStatement->execute(['user' => $userId]);
        foreach ($lensStatement->fetchAll() as $request) {
            $id = (int) $request['id'];
            $items[] = ['key' => 'lens-handover-' . $id, 'category' => t('Contact lens handover'), 'title' => $request['unit_code'] . ' · ' . $request['beneficiary_name'], 'detail' => t('Power') . ' ' . sprintf('%+.2f', (float) $request['power']), 'submitted_by' => t('Identity verification required'), 'created_label' => date('d M Y, H:i', strtotime((string) $request['created_at'])), 'created_at' => (string) $request['created_at'], 'url' => 'dashboard.php?page=pending-lens-handover'];
        }
        $respond($items, $count);
    }

    $count = (int) $database->query(
        "SELECT
            (SELECT COUNT(*) FROM correction_requests WHERE status = 'pending') +
            (SELECT COUNT(*) FROM registration_requests WHERE status = 'pending') +
            (SELECT COUNT(*) FROM aid_requests WHERE status = 'pending') +
            (SELECT COUNT(*) FROM goods_requests WHERE status = 'pending-admin-approval')"
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
        "SELECT gr.id, gr.quantity, gr.created_at, i.item_name, u.full_name AS requested_name
         FROM goods_requests gr
         JOIN inventory_items i ON i.id = gr.item_id
         JOIN users u ON u.id = gr.requested_by
         WHERE gr.status = 'pending-admin-approval'
         ORDER BY gr.created_at DESC, gr.id DESC
         LIMIT 12"
    );
    foreach ($goodsStatement->fetchAll() as $request) {
        $requestId = (int) $request['id'];
        $items[] = [
            'key' => 'goods-' . $requestId,
            'category' => t('Stock release request'),
            'title' => 'GR-' . str_pad((string) $requestId, 4, '0', STR_PAD_LEFT) . ' · ' . (string) $request['item_name'],
            'detail' => t('Quantity') . ' ' . (int) $request['quantity'],
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
        ['count' => $count, 'items' => $items],
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
