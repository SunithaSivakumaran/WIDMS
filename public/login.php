<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $token = (string) ($_POST['csrf_token'] ?? '');

    if (!verifyCsrfToken($token)) {
        $error = 'Your session expired. Please try again.';
    } elseif ($username === '' || $password === '') {
        $error = 'Enter your username and password.';
    } else {
        try {
            $statement = database()->prepare(
                'SELECT id, full_name, username, profile_image, password_hash, role
                 FROM users
                 WHERE username = :username AND status = :status
                 LIMIT 1'
            );
            $statement->execute(['username' => $username, 'status' => 'active']);
            $user = $statement->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                loginUser($user);
                $update = database()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
                $update->execute(['id' => $user['id']]);
                logActivity('Authentication', 'Signed in to WIDMS', null, 'done');
                header('Location: dashboard.php');
                exit;
            }

            $error = 'Invalid username or password.';
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            $error = 'Unable to connect to the system. Make sure MySQL is running and the database is installed.';
        }
    }
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars(widmsLanguage(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Sign in to the Welfare Inventory and Distribution Management System">
    <title>Sign In | WIDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/login.css?v=3" rel="stylesheet">
</head>
<body>
    <main class="login-page auth-layout">
        <!-- The brand panel gives users context before they enter the secure system. -->
        <aside class="auth-showcase" aria-label="About WIDMS">
            <?php renderLanguageSwitcher('auth-language'); ?>
            <div class="showcase-badge">Southern Province</div>
            <div class="showcase-content">
                <p class="showcase-kicker">Welfare services, connected</p>
                <h2>Support reaches people faster when every step is visible.</h2>
                <p>Manage welfare inventory, requests, approvals and distributions through one secure workspace.</p>
                <div class="showcase-features" aria-label="System benefits">
                    <span>Secure access</span>
                    <span>Clear approvals</span>
                    <span>Reliable records</span>
                </div>
            </div>
            <p class="showcase-footer">Welfare Inventory &amp; Distribution Management System</p>
        </aside>
        <section class="login-card" aria-labelledby="login-title">
            <?php renderLanguageSwitcher('mobile-language'); ?>
            <header class="brand d-flex align-items-center">
                <img class="brand-mark" src="assets/images/client-logo.jpeg" alt="WIDMS logo">
                <div>
                    <p class="brand-name mb-0">WIDMS</p>
                    <p class="brand-description mb-0">Welfare Inventory &amp; Distribution Management</p>
                </div>
            </header>

            <div class="intro">
                <p class="form-kicker"><?= htmlspecialchars(t('Welcome back'), ENT_QUOTES, 'UTF-8') ?></p>
                <h1 id="login-title"><?= htmlspecialchars(t('Sign in to your account'), ENT_QUOTES, 'UTF-8') ?></h1>
                <p><?= htmlspecialchars(t('Enter your approved WIDMS credentials to continue.'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>

            <form id="login-form" method="post" action="login.php">
                
                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger py-2" role="alert">
                        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

                <div class="mb-4">
                    <label for="username" class="form-label"><?= htmlspecialchars(t('Username'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input type="email" class="form-control" id="username" name="username"
                           value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>"
                           autocomplete="username" required>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label"><?= htmlspecialchars(t('Password'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input type="password" class="form-control" id="password" name="password"
                           autocomplete="current-password" required>
                </div>

                <button type="submit" class="btn btn-primary sign-in-button w-100"><?= htmlspecialchars(t('Sign In'), ENT_QUOTES, 'UTF-8') ?></button>
                <p class="signup-prompt mb-0"><?= htmlspecialchars(t('New to WIDMS?'), ENT_QUOTES, 'UTF-8') ?> <a href="signup.php"><?= htmlspecialchars(t('Request an account'), ENT_QUOTES, 'UTF-8') ?></a></p>
            </form>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/password-toggle.js?v=2"></script>
    <?= widmsUiTranslationAssetsHtml() ?>
</body>
</html>
