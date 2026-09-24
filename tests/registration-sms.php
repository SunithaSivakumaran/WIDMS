<?php
declare(strict_types=1);

// No gateway calls and no changes to the application database.
require_once __DIR__ . '/../includes/sms.php';

function smsCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

foreach (['077 123 4567', '+94 77 123 4567', '94771234567', '0094771234567'] as $phone) {
    smsCheck(widmsSmsPhone($phone) === '94771234567', 'Mobile number normalization failed.');
}
foreach (['0111234567', '077123456', '07712345678', '0771234567,0777654321', 'abc', '+447712345678'] as $phone) {
    smsCheck(widmsSmsPhone($phone) === null, 'Invalid or multiple recipients were accepted.');
}
$settings = ['enabled' => true, 'user_id' => 'test-user', 'password' => 'test-secret'];
$result = widmsSendSms('0771234567', 'WIDMS test', $settings, static function (string $url, string $payload): array {
    parse_str($payload, $parameters);
    smsCheck($url === 'https://textit.biz/sendmsg/', 'SMS must use the fixed HTTPS endpoint.');
    smsCheck($parameters['to'] === '94771234567' && $parameters['text'] === 'WIDMS test', 'Gateway payload is incorrect.');
    return ['http_status' => 200, 'body' => "OK:12345\n"];
});
smsCheck($result['status'] === 'sent' && $result['reference'] === '12345', 'Gateway acceptance was not recognized.');
foreach ([
    ["\xEF\xBB\xBF OK : msg.123/45:+abc \r\n", 'msg.123/45:+abc'],
    [" ok : abc-123_45 \n", 'abc-123_45'],
    ["OK:123.456\r\nCredits remaining: 10\r\n", '123.456'],
] as [$body, $reference]) {
    $result = widmsSendSms('0771234567', 'Test', $settings, static fn(): array => ['http_status' => 200, 'body' => $body]);
    smsCheck($result['status'] === 'sent' && $result['reference'] === $reference, 'Formatted success response was not recognized.');
}
foreach ([
    ['', 'empty-response'],
    ["\xEF\xBB\xBF \r\n", 'empty-response'],
    ['<html>OK:123</html>', 'html-response'],
    ['OK:', 'invalid-reference'],
    ['OK:<script>alert(1)</script>', 'invalid-reference'],
    ["OK:123\0", 'invalid-reference'],
    ['OK:' . str_repeat('x', 101), 'invalid-reference'],
    ["OK:123\nERROR:rejected", 'multiple-response'],
    ["OK:123\nOK:456", 'multiple-response'],
    ['There was an error; OK:123', 'unexpected-response'],
] as [$body, $error]) {
    $result = widmsParseSmsResponse($body);
    smsCheck($result['status'] === 'unknown' && $result['error'] === $error, 'Unsafe or ambiguous response was accepted: ' . $error);
}
smsCheck(widmsParseSmsResponse("\xEF\xBB\xBF ERR : private error")['status'] === 'failed', 'Formatted rejection was not recognized.');
foreach ([
    [['http_status' => 200, 'body' => 'ERROR:secret provider details'], 'failed'],
    [['http_status' => 200, 'body' => '<html>Error</html>'], 'unknown'],
    [['http_status' => 302, 'body' => 'OK:123'], 'unknown'],
    [['http_status' => 200, 'body' => 'OK:123', 'network_error' => true], 'unknown'],
] as [$response, $expected]) {
    $result = widmsSendSms('0771234567', 'Test', $settings, static fn(): array => $response);
    smsCheck($result['status'] === $expected, 'Unexpected gateway error classification.');
    smsCheck(!str_contains(json_encode($result), 'secret'), 'Provider details leaked.');
}
$called = false;
$result = widmsSendSms('invalid', 'Test', $settings, static function () use (&$called): array { $called = true; return []; });
smsCheck(!$called && $result['status'] === 'failed', 'Invalid phone reached the gateway.');

$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec("CREATE TABLE users (username TEXT, status TEXT, salary_number TEXT UNIQUE);
    CREATE TABLE registration_requests (id INTEGER PRIMARY KEY, email TEXT, phone TEXT, status TEXT,
        sms_status TEXT DEFAULT 'not-sent', sms_error_code TEXT, sms_gateway_reference TEXT, sms_sent_at TEXT, salary_number TEXT UNIQUE);
    INSERT INTO users (username,status) VALUES ('applicant@example.test','active');
    INSERT INTO registration_requests (id,email,phone,status) VALUES
        (1,'applicant@example.test','0771234567','approved'),
        (2,'applicant@example.test','0771234567','pending'),
        (3,'applicant@example.test','0771234567','rejected'),
        (4,'missing@example.test','0771234567','approved'),
        (5,'applicant@example.test','0771234567','approved');");
$calls = 0;
$sender = static function (string $phone, string $message) use (&$calls): array {
    $calls++;
    smsCheck(str_contains($message, 'Username: applicant@example.test'), 'Wrong approval username.');
    return ['status' => 'sent', 'reference' => 'test-123', 'error' => null];
};
$db->beginTransaction();
try {
    widmsSendRegistrationApprovalSms($db, 1, $sender);
    throw new RuntimeException('SMS was allowed before approval committed.');
} catch (LogicException $expected) {
    smsCheck($calls === 0, 'SMS was sent before commit.');
}
$db->rollBack();
smsCheck(widmsSendRegistrationApprovalSms($db, 1, $sender)['status'] === 'sent', 'Approved account was not notified.');
foreach ([1, 2, 3, 4] as $id) {
    smsCheck(widmsSendRegistrationApprovalSms($db, $id, $sender)['status'] === 'skipped', 'Repeated or unapproved account was notified.');
}
smsCheck($calls === 1, 'Duplicate SMS was sent.');
smsCheck($db->query('SELECT sms_sent_at FROM registration_requests WHERE id=1')->fetchColumn() !== null, 'Acceptance timestamp missing.');
widmsSendRegistrationApprovalSms($db, 5, static fn(): array => ['status' => 'unknown', 'error' => 'gateway-unconfirmed']);
smsCheck(widmsSendRegistrationApprovalSms($db, 5, $sender)['status'] === 'skipped', 'Ambiguous delivery was retried automatically.');

// Salary-based accounts are matched by salary number, not by their email login.
$db->exec("INSERT INTO users VALUES ('swpcs00123','active','00123');
    INSERT INTO registration_requests (id,email,phone,status,salary_number)
    VALUES (6,'salary@example.test','0771234567','approved','00123')");
$salaryCalls = 0;
$salarySender = static function (string $phone, string $message) use (&$salaryCalls): array {
    $salaryCalls++;
    smsCheck(str_contains($message, 'Username: swpcs00123.'), 'Salary username missing from SMS.');
    smsCheck(str_contains($message, 'password you entered during registration'), 'Registration password instructions missing.');
    return ['status' => 'sent', 'reference' => 'salary-123', 'error' => null];
};
smsCheck(widmsSendRegistrationApprovalSms($db, 6, $salarySender)['status'] === 'sent', 'Salary account SMS was not sent.');
smsCheck(widmsSendRegistrationApprovalSms($db, 6, $salarySender)['status'] === 'skipped' && $salaryCalls === 1, 'Salary account received duplicate SMS.');
echo "Registration SMS checks passed; no real SMS messages sent.\n";
