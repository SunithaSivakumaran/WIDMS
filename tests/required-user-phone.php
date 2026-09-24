<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../config/database.php';
$db = database();
// Temporary clones exercise the installed constraints without changing user data.
$db->exec('CREATE TEMPORARY TABLE phone_test_users LIKE users');
$db->exec('CREATE TEMPORARY TABLE phone_test_requests LIKE registration_requests');
$hash = password_hash('Fixture-only-482!', PASSWORD_DEFAULT);
$user = $db->prepare("INSERT INTO phone_test_users (full_name,username,phone,password_hash,role)
    VALUES ('Phone Test','phone@example.test',:phone,:hash,'store-keeper')");
$request = $db->prepare("INSERT INTO phone_test_requests (full_name,email,phone,password_hash,role)
    VALUES ('Phone Test','phone@example.test',:phone,:hash,'store-keeper')");
foreach ([$user, $request] as $insert) {
    foreach ([null, '', '       ', "\t\n\r    ", '-------', '123'] as $phone) {
        try {
            $insert->execute(['phone' => $phone, 'hash' => $hash]);
            throw new RuntimeException('A missing or blank phone was accepted.');
        } catch (PDOException $expected) {
            if (!in_array((int) ($expected->errorInfo[1] ?? 0), [1048, 4025], true)) {
                throw $expected;
            }
        }
    }
    $insert->execute(['phone' => '0763121801', 'hash' => $hash]);
}
try {
    $db->exec("UPDATE phone_test_users SET phone='' WHERE username='phone@example.test'");
    throw new RuntimeException('An existing user phone could be cleared.');
} catch (PDOException $expected) {
    if ((int) ($expected->errorInfo[1] ?? 0) !== 4025) { throw $expected; }
}
echo "Required-phone constraints passed for inserts and updates; operational data unchanged.\n";
