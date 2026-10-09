<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
require_once __DIR__ . '/../includes/sms.php';
$settings = require __DIR__ . '/../config/sms.php';
$configured = !empty($settings['enabled']) && !empty($settings['user_id']) && !empty($settings['password']);
echo $configured ? "SMS credentials configured.\n" : "Configure config/sms.local.php first.\n";
echo function_exists('curl_init') ? "PHP cURL available.\n" : "PHP cURL is required.\n";
if (!$configured || !function_exists('curl_init')) { exit(1); }
if (($argv[1] ?? '') !== '--send') {
    echo "No message sent. For a real test: php scripts/test-sms.php --send 07XXXXXXXX\n";
    exit(0);
}
$phone = (string) ($argv[2] ?? '');
if (widmsSmsPhone($phone) === null) {
    fwrite(STDERR, "Provide one valid Sri Lankan mobile number.\n");
    exit(1);
}
$result = widmsSendSms($phone, 'SWPCS SMS connection test. This is a test message.');
echo $result['status'] === 'sent'
    ? "SMS accepted by the gateway. Check the recipient phone.\n"
    : "SMS not confirmed: " . ($result['error'] ?? $result['status']) . ".\n";
exit($result['status'] === 'sent' ? 0 : 1);
