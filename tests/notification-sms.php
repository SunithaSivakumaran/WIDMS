<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/notification-sms.php';

function notificationSmsCheck(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

$db = database();
// These temporary shadows exist only on this connection. All sends below use
// injected fakes; operational tables and the real gateway are never written.
foreach (['notification_sms_outbox','notification_sms_state','users'] as $table) {
    // MariaDB rejects CREATE ... table LIKE table with identical names.
    $db->exec('CREATE TEMPORARY TABLE sms_fixture_' . $table . ' LIKE ' . $table);
    $db->exec('CREATE TEMPORARY TABLE ' . $table . ' LIKE sms_fixture_' . $table);
}
$db->exec('CREATE TEMPORARY TABLE notification_sms_candidates (
    user_id INT, notification_key VARCHAR(160), category VARCHAR(100), reference_code VARCHAR(100), occurred_at DATETIME)
    DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$db->exec("INSERT INTO notification_sms_state (id,activated_at) VALUES (1,'2026-01-01 00:00:00')");
$insertUser = $db->prepare("INSERT INTO users (id,full_name,username,phone,password_hash,role,status)
    VALUES (:id,'SMS Fixture',:username,:phone,'not-a-real-login',:role,'active')");
$roles = ['admin','store-keeper','subject-officer','social-service-officer'];
foreach ($roles as $offset => $role) {
    $insertUser->execute(['id' => $offset+1,'username' => $role.'@example.test','phone' => '077123456'.($offset+1),'role' => $role]);
}
$event = $db->prepare('INSERT INTO notification_sms_candidates VALUES (:user,:key,:category,:reference,:date)');
foreach ($roles as $offset => $role) {
    $event->execute(['user' => $offset+1, 'key' => 'event-'.($offset+1), 'category' => 'Aid request update',
        'reference' => 'AR-123', 'date' => '2026-01-02 00:00:00']);
}
$event->execute(['user'=>1,'key'=>'historical','category'=>'Old alert','reference'=>'OLD','date'=>'2025-12-01 00:00:00']);
$event->execute(['user'=>1,'key'=>'baseline','category'=>'Old alert','reference'=>'OLD','date'=>'2026-01-01 00:00:00']);
$db->exec("INSERT INTO notification_sms_outbox (user_id,notification_key,message,status,error_code)
    VALUES (1,'baseline','','skipped','before-activation')");
notificationSmsCheck(widmsQueueNotificationSms($db) === 4, 'Not all roles were queued, or historical alerts were replayed.');
notificationSmsCheck(widmsQueueNotificationSms($db) === 0, 'Duplicate collection created more messages.');
$calls = [];
$sender = static function (string $phone, string $message) use (&$calls): array {
    $calls[] = [$phone,$message];
    return ['status'=>'sent','reference'=>'fixture-'.count($calls),'error'=>null];
};
$db->beginTransaction();
try {
    widmsProcessNotificationSms($db,10,$sender);
    throw new RuntimeException('SMS processing was allowed within a transaction.');
} catch (LogicException $expected) {
    notificationSmsCheck($calls === [], 'SMS was attempted before commit.');
} finally { $db->rollBack(); }
$totals = widmsProcessNotificationSms($db,10,$sender);
notificationSmsCheck($totals['sent'] === 4 && count($calls) === 4, 'Role delivery count is wrong.');
foreach ($calls as $index => [$phone,$message]) {
    notificationSmsCheck($phone === '077123456'.($index+1), 'Notification sent to another recipient phone.');
    notificationSmsCheck(str_contains($message,'AR-123') && str_contains($message,'Sign in'), 'Summary is missing its reference or action.');
}
widmsProcessNotificationSms($db,10,$sender);
notificationSmsCheck(count($calls)===4,'Completed SMS was sent twice.');
notificationSmsCheck((int)$db->query("SELECT COUNT(*) FROM notification_sms_outbox WHERE status='sent' AND accepted_at IS NOT NULL")->fetchColumn()===4,'Acceptance timestamps were not recorded.');

// An inactive recipient is skipped, and an uncertain send is never retried.
$event->execute(['user'=>2,'key'=>'inactive','category'=>'Update','reference'=>'GR-2','date'=>'2026-01-02 00:00:00']);
$event->execute(['user'=>3,'key'=>'uncertain','category'=>'Update','reference'=>'GR-3','date'=>'2026-01-02 00:00:00']);
widmsQueueNotificationSms($db);
$db->exec("UPDATE users SET status='inactive' WHERE id=2");
$uncertainCalls = 0;
$uncertain = static function () use (&$uncertainCalls): array {
    $uncertainCalls++;
    return ['status'=>'unknown','error'=>'gateway-unconfirmed'];
};
$totals = widmsProcessNotificationSms($db,10,$uncertain);
notificationSmsCheck($totals['skipped']===1 && $totals['unknown']===1 && $uncertainCalls===1,'Inactive or uncertain delivery was mishandled.');
widmsProcessNotificationSms($db,10,$uncertain);
notificationSmsCheck($uncertainCalls===1,'Uncertain message was retried.');

// A concurrent sender that already claimed a job must not be duplicated.
$db->exec("INSERT INTO notification_sms_outbox (user_id,notification_key,message,status) VALUES (1,'claimed','Fixture','sending')");
widmsProcessNotificationSms($db,10,$sender);
notificationSmsCheck(count($calls)===4,'Already claimed message was resent.');
echo "System notification SMS checks passed for all four roles; no real SMS or operational changes.\n";
