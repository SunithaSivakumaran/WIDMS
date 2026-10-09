<?php
declare(strict_types=1);

require_once __DIR__ . '/optical-stock.php';
require_once __DIR__ . '/spectacle-categories.php';

/**
 * Store one durable notification for a specific user.
 * The unique key prevents duplicate notifications if a request is retried.
 */
function notifyUser(
    PDO $database,
    int $userId,
    string $notificationKey,
    string $category,
    string $title,
    string $message,
    string $targetUrl
): void {
    if ($userId < 1 || !preg_match('/^[a-z0-9][a-z0-9._:-]{0,159}$/i', $notificationKey)) {
        throw new InvalidArgumentException('Invalid notification recipient or key.');
    }

    // Notification links must remain inside the authenticated SWPCS dashboard.
    if (!preg_match('/^dashboard\.php\?page=[a-z0-9-]+(?:&[a-z0-9_-]+=[a-z0-9_-]+)*(?:#(?:(?:aid|goods|vision-camp)-request|vision-camp-row|fulfillment)-\d+)?$/i', $targetUrl)) {
        throw new InvalidArgumentException('Invalid notification target.');
    }

    $statement = $database->prepare(
        'INSERT INTO user_notifications
            (user_id, notification_key, category, title, message, target_url)
         VALUES
            (:user_id, :notification_key, :category, :title, :message, :target_url)
         ON DUPLICATE KEY UPDATE id = id'
    );
    $statement->execute([
        'user_id' => $userId,
        'notification_key' => mb_substr($notificationKey, 0, 160),
        'category' => mb_substr($category, 0, 100),
        'title' => mb_substr($title, 0, 180),
        'message' => mb_substr($message, 0, 500),
        'target_url' => mb_substr($targetUrl, 0, 500),
    ]);
}

/** Route an approved request without losing Subject Officer ownership. */
function notifyApprovedAidRouting(PDO $database, int $requestId, ?int $responsibleSubjectOfficerId = null): void
{
    if ($requestId < 1) {
        throw new InvalidArgumentException('Invalid approved aid request.');
    }

    $contextStatement = $database->prepare(
        "SELECT ar.item_id, ar.quantity, ar.prescribed_power, ar.spectacle_category_id, ar.submitted_by,
                b.ds_division_id, i.item_name, i.variety, c.name AS category_name,
                submitter.role AS submitter_role
         FROM aid_requests ar
         JOIN beneficiaries b ON b.id = ar.beneficiary_id
         JOIN inventory_items i ON i.id = ar.item_id
         JOIN item_categories c ON c.id = i.category_id
         JOIN users submitter ON submitter.id = ar.submitted_by
         WHERE ar.id = :id AND ar.status = 'approved'
         LIMIT 1"
    );
    $contextStatement->execute(['id' => $requestId]);
    $context = $contextStatement->fetch();
    if (!$context) {
        return;
    }

    $ssoStatement = $database->prepare(
        "SELECT u.id, COALESCE(p.allocated - p.distributed + p.reused, 0) AS available
         FROM users u
         LEFT JOIN division_pools p
           ON p.ds_division_id = u.ds_division_id AND p.item_id = :item_id
         WHERE u.role = 'social-service-officer'
           AND u.status = 'active'
           AND u.ds_division_id = :division_id
         ORDER BY (COALESCE(p.allocated - p.distributed + p.reused, 0) >= :quantity) DESC,
                  available DESC, u.id
         LIMIT 1"
    );
    $ssoStatement->execute([
        'item_id' => (int) $context['item_id'],
        'division_id' => (int) $context['ds_division_id'],
        'quantity' => (int) $context['quantity'],
    ]);
    $assignedSso = $ssoStatement->fetch();
    $requestCode = 'AR-' . str_pad((string) $requestId, 4, '0', STR_PAD_LEFT);
    $title = $requestCode . ' · ' . (string) $context['item_name'];
    $isOptical = widmsIsOpticalItem(
        (string) $context['item_name'],
        (string) $context['variety'],
        (string) $context['category_name']
    );
    if (widmsIsSpectacleItem((string) $context['item_name'])) {
        $categoryId = (int) ($context['spectacle_category_id'] ?? 0);
        $balances = widmsSpectacleCategoryBalances($database, (int) $context['item_id']);
        $matched = $categoryId > 0
            && ($balances[$categoryId] ?? 0) >= (int) $context['quantity'];
        $recipients = (string) $context['submitter_role'] === 'subject-officer'
            ? [(int) $context['submitted_by']]
            : array_map('intval', $database->query(
                "SELECT id FROM users WHERE role = 'subject-officer' AND status = 'active'"
            )->fetchAll(PDO::FETCH_COLUMN));
        foreach ($recipients as $subjectOfficerId) {
            notifyUser(
                $database,
                $subjectOfficerId,
                'approved-spectacle-routing-' . $requestId,
                $matched ? 'Spectacle type available' : 'Spectacle type unavailable',
                $title,
                $matched
                    ? 'The selected spectacle type is in Central Stock. Add this request to an approved aid bundle.'
                    : 'The selected spectacle type is not currently available in Central Stock.',
                'dashboard.php?page=approved-aid-bundles&request_id=' . $requestId . '#aid-request-' . $requestId
            );
        }
        return;
    }

    // A Subject Officer remains responsible for requests submitted under that
    // account. SSO pool stock may be consumed by the Subject Officer, but the
    // request is never reassigned to the SSO for beneficiary distribution.
    if ((string) $context['submitter_role'] === 'subject-officer') {
        $subjectOfficerId = (int) $context['submitted_by'];
        if ($isOptical) {
            notifyUser(
                $database,
                $subjectOfficerId,
                'approved-optical-routing-' . $requestId,
                'Contact Lens request approved',
                $title,
                'Check Central Stock quantity and select this request for Admin-approved release.',
                'dashboard.php?page=approved-aid-bundles&request_id=' . $requestId . '#aid-request-' . $requestId
            );
            return;
        }

        $hasSsoPoolStock = $assignedSso
            && (int) $assignedSso['available'] >= (int) $context['quantity'];
        notifyUser(
            $database,
            $subjectOfficerId,
            'approved-subject-distribution-' . $requestId,
            $hasSsoPoolStock ? 'Aid ready for Subject Officer distribution' : 'SSO pool stock required',
            $title,
            $hasSsoPoolStock
                ? 'The item is available in the division SSO pool. You can distribute it directly to the beneficiary.'
                : 'The assigned SSO pool has insufficient stock. Arrange a stock quota.',
            $hasSsoPoolStock
                ? 'dashboard.php?page=distribute-items&request_id=' . $requestId . '#aid-request-' . $requestId
                : 'dashboard.php?page=approved-aid-bundles&request_id=' . $requestId
        );
        return;
    }

    // Contact Lens requests use Central Stock quantity and the approval flow.
    if ($isOptical) {
        $subjectOfficerIds = $database->query(
            "SELECT id FROM users WHERE role = 'subject-officer' AND status = 'active' ORDER BY id"
        )->fetchAll(PDO::FETCH_COLUMN);
        foreach ($subjectOfficerIds as $subjectOfficerId) {
            notifyUser(
                $database,
                (int) $subjectOfficerId,
                'approved-optical-routing-' . $requestId,
                'Contact Lens request approved',
                $title,
                'Check Central Stock quantity and select this request for Admin-approved release.',
                'dashboard.php?page=optical-aid-requests&request_id=' . $requestId . '#aid-request-' . $requestId
            );
        }
        return;
    }

    if ($assignedSso && (int) $assignedSso['available'] >= (int) $context['quantity']) {
        notifyUser($database, (int) $assignedSso['id'], 'approved-aid-ready-' . $requestId,
            'Aid ready for distribution', $title,
            'The approved item is available in your pool. Complete the beneficiary distribution.',
            'dashboard.php?page=distribute-aid&request_id=' . $requestId);
        return;
    }

    if ($responsibleSubjectOfficerId !== null && $responsibleSubjectOfficerId > 0) {
        $recipientStatement = $database->prepare(
            "SELECT id FROM users WHERE id = :id AND role = 'subject-officer' AND status = 'active'"
        );
        $recipientStatement->execute(['id' => $responsibleSubjectOfficerId]);
    } else {
        $recipientStatement = $database->query(
            "SELECT id FROM users WHERE role = 'subject-officer' AND status = 'active' ORDER BY id"
        );
    }

    $subjectOfficerIds = $recipientStatement->fetchAll(PDO::FETCH_COLUMN);
    if ($subjectOfficerIds === [] && $responsibleSubjectOfficerId !== null) {
        $subjectOfficerIds = $database->query(
            "SELECT id FROM users WHERE role = 'subject-officer' AND status = 'active' ORDER BY id"
        )->fetchAll(PDO::FETCH_COLUMN);
    }

    foreach ($subjectOfficerIds as $subjectOfficerId) {
        notifyUser($database, (int) $subjectOfficerId, 'approved-aid-stock-needed-' . $requestId,
            'SSO pool stock required', $title,
            'An approved request cannot be fulfilled because the assigned SSO pool has insufficient stock. Arrange a stock quota.',
            'dashboard.php?page=request-goods&aid_request_id=' . $requestId);
    }
}
