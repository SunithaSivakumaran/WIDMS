<?php
declare(strict_types=1);

require_once __DIR__ . '/sms.php';

function widmsNotificationSmsText(string $role, string $category, string $reference): string
{
    $roles = ['admin' => 'Admin', 'subject-officer' => 'Subject Officer',
        'store-keeper' => 'Store Keeper', 'social-service-officer' => 'SSO'];
    // Categories are workflow labels; detailed notification bodies stay in WIDMS.
    $clean = static fn(string $text): string => trim(preg_replace('/[\r\n\t]+/', ' ', strip_tags($text)) ?? '');
    return 'WIDMS (' . ($roles[$role] ?? 'User') . '): ' . mb_substr($clean($category), 0, 80)
        . '. Ref: ' . mb_substr($clean($reference), 0, 60) . '. Sign in to view details.';
}

/** Queue only newly visible events; do not depend on the recipient being online. */
function widmsQueueNotificationSms(PDO $db): int
{
    if ($db->inTransaction()) { throw new LogicException('SMS collection requires committed changes.'); }
    $query = $db->query("SELECT c.user_id,c.notification_key,c.category,c.reference_code,u.role
        FROM notification_sms_candidates c
        JOIN users u ON u.id=c.user_id AND u.status='active'
        JOIN notification_sms_state s ON s.id=1 AND c.occurred_at>=s.activated_at
        WHERE NOT EXISTS (SELECT 1 FROM notification_sms_outbox o
            WHERE o.user_id=c.user_id AND o.notification_key=c.notification_key)
        ORDER BY c.occurred_at,c.user_id,c.notification_key LIMIT 500");
    $insert = $db->prepare('INSERT INTO notification_sms_outbox (user_id,notification_key,message)
        VALUES (:user,:key,:message) ON DUPLICATE KEY UPDATE id=id');
    $queued = 0;
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $event) {
        $insert->execute(['user' => $event['user_id'], 'key' => $event['notification_key'],
            'message' => widmsNotificationSmsText($event['role'], $event['category'], $event['reference_code'])]);
        $queued += $insert->rowCount() === 1 ? 1 : 0;
    }
    return $queued;
}

/** Atomic claims prevent concurrent requests or workers from resending a job. */
function widmsProcessNotificationSms(PDO $db, int $limit = 10, ?callable $sender = null, int $seconds = 25): array
{
    if ($db->inTransaction()) { throw new LogicException('SMS sending requires committed changes.'); }
    $limit = max(1, min(100, $limit));
    $deadline = microtime(true) + max(1, $seconds);
    $totals = ['sent' => 0, 'failed' => 0, 'unknown' => 0, 'skipped' => 0];
    $jobs = $db->query("SELECT id FROM notification_sms_outbox WHERE status='queued' ORDER BY id LIMIT $limit")->fetchAll(PDO::FETCH_COLUMN);
    $claim = $db->prepare("UPDATE notification_sms_outbox SET status='sending',attempted_at=CURRENT_TIMESTAMP
        WHERE id=:id AND status='queued'");
    $fetch = $db->prepare('SELECT o.message,u.phone,u.status user_status FROM notification_sms_outbox o
        JOIN users u ON u.id=o.user_id WHERE o.id=:id');
    $finish = $db->prepare("UPDATE notification_sms_outbox SET status=:status,error_code=:error,gateway_reference=:reference,
        accepted_at=CASE WHEN :accepted='sent' THEN CURRENT_TIMESTAMP ELSE NULL END WHERE id=:id AND status='sending'");
    foreach ($jobs as $id) {
        if (microtime(true) >= $deadline) { break; }
        $claim->execute(['id' => $id]);
        if ($claim->rowCount() !== 1) { continue; }
        try {
            $fetch->execute(['id' => $id]);
            $job = $fetch->fetch(PDO::FETCH_ASSOC);
            if (!$job || $job['user_status'] !== 'active') {
                $result = ['status' => 'skipped', 'error' => 'inactive-recipient'];
            } else {
                $result = $sender !== null ? $sender($job['phone'], $job['message']) : widmsSendSms($job['phone'], $job['message']);
            }
        } catch (Throwable $exception) {
            $result = ['status' => 'unknown', 'error' => 'notification-error'];
        }
        if (!isset($totals[$result['status'] ?? ''])) {
            $result = ['status' => 'unknown', 'error' => 'invalid-result'];
        }
        // Never reset uncertain/sending jobs automatically: the phone may have
        // received the message even if the gateway reply or DB write failed.
        $finish->execute(['id' => $id, 'status' => $result['status'], 'error' => $result['error'] ?? null,
            'reference' => $result['reference'] ?? null, 'accepted' => $result['status']]);
        $totals[$result['status']]++;
    }
    return $totals;
}

/** Run after a legitimate form submission, including redirects, never on GET. */
function widmsScheduleNotificationSms(): void
{
    static $registered = false;
    if ($registered || PHP_SAPI === 'cli' || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { return; }
    $registered = true;
    register_shutdown_function(static function (): void {
        $lastError = error_get_last();
        if ($lastError && in_array($lastError['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR], true)) { return; }
        try {
            require_once __DIR__ . '/../config/database.php';
            $db = database();
            if ($db->inTransaction()) { return; }
            if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
            if (function_exists('fastcgi_finish_request')) { fastcgi_finish_request(); }
            widmsQueueNotificationSms($db);
            widmsProcessNotificationSms($db);
        } catch (Throwable $exception) {
            // Keep workflow results and the Admin confirmation free of SMS errors.
            error_log('WIDMS system notification SMS worker could not complete. Check migrations and SMS configuration.');
        }
    });
}
