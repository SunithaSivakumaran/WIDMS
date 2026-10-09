<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/../includes/auth.php';

$originalSession = $_SESSION;
try {
    $_SESSION = [];
    $token = csrfToken();
    if (!verifyCsrfToken($token)) throw new RuntimeException('New form token was rejected.');

    // Existing handlers still clear this legacy key after a successful POST.
    unset($_SESSION['csrf_token']);
    if (!verifyCsrfToken($token) || csrfToken() !== $token) {
        throw new RuntimeException('Another successful request invalidated an open form.');
    }

    $wrongToken = $token === str_repeat('0', 64) ? str_repeat('1', 64) : str_repeat('0', 64);
    if (verifyCsrfToken($wrongToken)) throw new RuntimeException('Invalid form token was accepted.');

    loginUser(['id' => 123, 'full_name' => 'Test', 'username' => 'test', 'role' => 'store-keeper']);
    $newLoginToken = csrfToken();
    if ($newLoginToken === $token || verifyCsrfToken($token)) {
        throw new RuntimeException('A token carried over to a new login.');
    }
} finally {
    $_SESSION = $originalSession;
}

echo "PASS: CSRF tokens survive other submissions but reset on login.\n";
