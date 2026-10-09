<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

// Load language selection after the session starts so the preference persists.
require_once __DIR__ . '/i18n.php';
widmsLanguage();

function csrfToken(): string
{
    // A successful action may clear the legacy key while another form is still
    // open. Keep one token for the login session instead of invalidating every
    // other tab or an in-flight repeat submission.
    if (!isset($_SESSION['csrf_token_stable'])) {
        $_SESSION['csrf_token_stable'] = is_string($_SESSION['csrf_token'] ?? null)
            && preg_match('/\A[a-f0-9]{64}\z/D', $_SESSION['csrf_token'])
            ? $_SESSION['csrf_token']
            : bin2hex(random_bytes(32));
    }
    $_SESSION['csrf_token'] = $_SESSION['csrf_token_stable'];
    return $_SESSION['csrf_token_stable'];
}

function verifyCsrfToken(string $token): bool
{
    $expected = $_SESSION['csrf_token_stable'] ?? $_SESSION['csrf_token'] ?? null;
    $valid = is_string($expected) && $token !== '' && hash_equals($expected, $token);
    if ($valid) {
        $_SESSION['csrf_token_stable'] = $expected;
        $_SESSION['csrf_token'] = $expected;
        // Signing in does not create a workflow notification.  Do not start
        // the SMS outbox worker here: an unreachable SMS gateway can otherwise
        // delay the successful login redirect for several seconds.
        $scriptName = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($scriptName !== 'login.php') {
            require_once __DIR__ . '/notification-sms.php';
            widmsScheduleNotificationSms();
        }
    }
    return $valid;
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id'], $_SESSION['role']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function loginUser(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['profile_image'] = $user['profile_image'] ?? null;
    unset($_SESSION['csrf_token'], $_SESSION['csrf_token_stable']);
}

function logoutUser(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'],
            (bool) $params['secure'], (bool) $params['httponly']);
    }

    session_destroy();
}
