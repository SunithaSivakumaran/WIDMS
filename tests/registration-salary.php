<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../includes/registration.php';

function salaryCheck(bool $passed, string $message): void
{
    if (!$passed) { throw new RuntimeException($message); }
}

salaryCheck(widmsSalaryUsername(' 00123 ') === 'swpcs00123', 'Prefix or leading zeros changed.');
foreach (['', '1 23', '12/34', '12.3', '-12', 'swpcs123', str_repeat('1', 31), "123\n456"] as $invalid) {
    salaryCheck(widmsSalaryNumber($invalid) === null, 'Invalid salary number accepted.');
    try { widmsSalaryUsername($invalid); throw new RuntimeException('Invalid username generated.'); }
    catch (InvalidArgumentException $expected) {}
}
salaryCheck(widmsSalaryNumber('0') === '0', 'Zero identifier rejected.');
salaryCheck(widmsSalaryNumber(str_repeat('1', 30)) !== null, 'Valid maximum-length number rejected.');

if (($argv[1] ?? '') === '--database') {
    require_once __DIR__ . '/../config/database.php';
    $db = database();
    // Connection-local clones test installed schema constraints, not operational data.
    $db->exec('CREATE TEMPORARY TABLE salary_test_users LIKE users');
    $db->exec('CREATE TEMPORARY TABLE salary_test_requests LIKE registration_requests');
    $password = 'Local-test-only-482!';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $insert = $db->prepare("INSERT INTO salary_test_users (full_name,username,email,salary_number,phone,password_hash,role,status)
        VALUES ('Salary Test',:username,:email,:salary,'0771234567',:hash,'store-keeper','active')");
    $insert->execute(['username' => 'swpcs00123', 'email' => 'salary@example.test', 'salary' => '00123', 'hash' => $hash]);
    try {
        $insert->execute(['username' => 'different-login', 'email' => 'other@example.test', 'salary' => '00123', 'hash' => $hash]);
        throw new RuntimeException('Database allowed duplicate salary numbers in users.');
    } catch (PDOException $expected) {
        salaryCheck((int) ($expected->errorInfo[1] ?? 0) === 1062, 'Unexpected users constraint error.');
    }
    $request = $db->prepare("INSERT INTO salary_test_requests (full_name,email,phone,salary_number,password_hash,role)
        VALUES ('Salary Test',:email,'0771234567','00123',:hash,'store-keeper')");
    $request->execute(['email' => 'salary@example.test', 'hash' => $hash]);
    try {
        $request->execute(['email' => 'other@example.test', 'hash' => $hash]);
        throw new RuntimeException('Database allowed duplicate salary numbers in requests.');
    } catch (PDOException $expected) {
        salaryCheck((int) ($expected->errorInfo[1] ?? 0) === 1062, 'Unexpected registration constraint error.');
    }
    $account = $db->query("SELECT username,password_hash FROM salary_test_users WHERE username='swpcs00123' AND status='active'")->fetch();
    salaryCheck($account && password_verify($password, $account['password_hash']), 'Salary login/password pairing failed.');
    $db->exec("UPDATE salary_test_users SET email='changed@example.test' WHERE salary_number='00123'");
    salaryCheck($db->query("SELECT username FROM salary_test_users WHERE salary_number='00123'")->fetchColumn() === 'swpcs00123', 'Email update changed salary username.');
}
echo "Salary registration checks passed; no operational records or messages created.\n";
