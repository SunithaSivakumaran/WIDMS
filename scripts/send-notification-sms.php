<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/notification-sms.php';

$db = database();
if (($argv[1] ?? '') === '--check') {
    foreach ($db->query('SELECT status,COUNT(*) total FROM notification_sms_outbox GROUP BY status')->fetchAll() as $row) {
        echo $row['status'], ': ', $row['total'], PHP_EOL;
    }
    echo "Read-only check; no messages queued or sent.\n";
    exit;
}
if (($argv[1] ?? '') !== '--send') {
    fwrite(STDERR, "Use --check for queue status, or --send to collect and send new notification SMS.\n");
    exit(2);
}
echo 'Queued: ', widmsQueueNotificationSms($db), PHP_EOL;
echo json_encode(widmsProcessNotificationSms($db, 100, null, 50)), PHP_EOL;
