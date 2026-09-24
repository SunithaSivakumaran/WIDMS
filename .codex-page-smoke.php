<?php
declare(strict_types=1);

$smokeRole = (string) ($argv[1] ?? '');
$smokePage = (string) ($argv[2] ?? 'dashboard');
$expectedText = (string) ($argv[4] ?? '');
$allowedRoles = ['admin', 'store-keeper', 'subject-officer', 'social-service-officer'];
if (!in_array($smokeRole, $allowedRoles, true) || !preg_match('/^[a-z0-9-]+$/', $smokePage)) {
    fwrite(STDERR, "Invalid smoke-test role or page.\n");
    exit(2);
}

require_once __DIR__ . '/config/database.php';
$statement = database()->prepare("SELECT id, full_name, username, role FROM users WHERE role = :role AND status = 'active' ORDER BY id LIMIT 1");
$statement->execute(['role' => $smokeRole]);
$user = $statement->fetch();
if (!$user) {
    echo "SKIP $smokeRole/$smokePage (no active user)\n";
    exit(0);
}

$sessionPath = __DIR__ . '/.codex-smoke-sessions';
if (!is_dir($sessionPath) && !mkdir($sessionPath, 0700, true) && !is_dir($sessionPath)) {
    fwrite(STDERR, "Unable to create the smoke-test session directory.\n");
    exit(2);
}
ini_set('session.save_path', $sessionPath);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['full_name'] = (string) $user['full_name'];
$_SESSION['username'] = (string) $user['username'];
$_SESSION['role'] = $smokeRole;
$_GET = ['page' => $smokePage];
if (isset($argv[3]) && ctype_digit((string) $argv[3])) {
    $_GET['aid_request_id'] = (string) $argv[3];
    if ($smokePage === 'goods-request-document') {
        $_GET['request_id'] = (string) $argv[3];
    }
}
$_POST = [];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/WIDMS/public/dashboard.php';
$_SERVER['PHP_SELF'] = '/WIDMS/public/dashboard.php';

if ($smokePage === 'goods-request-document' && ($argv[5] ?? '') === 'pdf') {
    $_GET['print'] = '1';
    register_shutdown_function(static function () use ($smokeRole): void {
        $pdf = (string) ob_get_contents();
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (str_starts_with($pdf, '%PDF-') && strlen($pdf) > 1000) {
            fwrite(STDOUT, "OK $smokeRole/goods-request-document PDF " . strlen($pdf) . " bytes\n");
        } else {
            fwrite(STDERR, "FAIL $smokeRole/goods-request-document PDF output is invalid.\n");
        }
    });
    ob_start();
    chdir(__DIR__ . '/public');
    require __DIR__ . '/public/dashboard.php';
    exit;
}

set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    ob_start();
    chdir(__DIR__ . '/public');
    require __DIR__ . '/public/dashboard.php';
    $html = (string) ob_get_clean();
    $trimmedOutput = ltrim($html);
    $isJsonResponse = ($trimmedOutput !== '' && in_array($trimmedOutput[0], ['{', '['], true));
    if ($isJsonResponse) {
        json_decode($trimmedOutput, true, 512, JSON_THROW_ON_ERROR);
    } elseif (stripos($html, '<html') === false || stripos($html, '</html>') === false) {
        throw new RuntimeException('Page did not render a complete HTML document.');
    }
    if ($expectedText !== '' && stripos($html, $expectedText) === false) {
        throw new RuntimeException('Expected page text was not rendered: ' . $expectedText);
    }
    session_destroy();
    echo "OK $smokeRole/$smokePage " . strlen($html) . " bytes\n";
} catch (Throwable $exception) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    fwrite(STDERR, "FAIL $smokeRole/$smokePage: " . $exception->getMessage() . "\n");
    exit(1);
}
