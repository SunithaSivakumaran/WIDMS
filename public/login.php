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
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Sign in to the Welfare Inventory and Distribution Management System">
    <title>Sign In | WIDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/login.css" rel="stylesheet">
</head>
<body>
    <main class="login-page d-flex align-items-center justify-content-center">
        <section class="login-card" aria-labelledby="login-title">
            <header class="brand d-flex align-items-center">
                <img class="brand-mark" src="assets/images/client-logo.jpeg" alt="WIDMS logo">
                <div>
                    <p class="brand-name mb-0">WIDMS</p>
                    <p class="brand-description mb-0">Welfare Inventory &amp; Distribution Management</p>
                </div>
            </header>

            <div class="intro">
                <h1 id="login-title">Sign in</h1>
                <p>Enter your account details to continue</p>
            </div>

            <form id="login-form" method="post" action="login.php">
                
                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger py-2" role="alert">
                        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

                <div class="mb-4">
                    <label for="username" class="form-label">Username</label>
                    <input type="email" class="form-control" id="username" name="username"
                           value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>"
                           autocomplete="username" required>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password"
                           autocomplete="current-password" required>
                </div>

                <button type="submit" class="btn btn-primary sign-in-button w-100">Sign In</button>
                <p class="signup-prompt mb-0">New to WIDMS? <a href="signup.php">Request an account</a></p>
            </form>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
