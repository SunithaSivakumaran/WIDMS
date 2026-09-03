<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../config/database.php';

$username = trim((string) ($argv[1] ?? 'admin@widms.gov'));

if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Enter a valid account email address.\n");
    fwrite(STDERR, "Usage: php database/reset_user_password.php [email]\n");
    exit(1);
}

$db = database();
$findUser = $db->prepare(
    'SELECT id, full_name, username, role, status
     FROM users
     WHERE username = :username
     LIMIT 1'
);
$findUser->execute(['username' => strtolower($username)]);
$user = $findUser->fetch();

if (!$user) {
    fwrite(STDERR, "No WIDMS user exists with that email address.\n");
    exit(1);
}

echo 'Account: ' . $user['full_name'] . ' <' . $user['username'] . ">\n";
echo 'Role: ' . $user['role'] . "\n";
echo 'Status: ' . $user['status'] . "\n\n";
echo "New password (minimum 8 characters): ";
$password = rtrim((string) fgets(STDIN), "\r\n");
echo "Confirm new password: ";
$confirmation = rtrim((string) fgets(STDIN), "\r\n");

if (strlen($password) < 8) {
    fwrite(STDERR, "Password must contain at least 8 characters.\n");
    exit(1);
}

if ($password !== $confirmation) {
    fwrite(STDERR, "The passwords do not match. Nothing was changed.\n");
    exit(1);
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

if ($passwordHash === false) {
    fwrite(STDERR, "Unable to hash the password. Nothing was changed.\n");
    exit(1);
}

$update = $db->prepare(
    "UPDATE users
     SET password_hash = :password_hash,
         status = 'active'
     WHERE id = :id"
);
$update->execute([
    'password_hash' => $passwordHash,
    'id' => $user['id'],
]);

echo "\nPassword reset successfully. The account is active.\n";
