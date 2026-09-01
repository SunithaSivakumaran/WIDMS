<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$roles = [
    'subject-officer' => 'Subject Officer',
    'store-keeper' => 'Store Keeper',
    'social-service-officer' => 'Social Service Officer',
];
$values = ['full_name' => '', 'email' => '', 'phone' => '', 'role' => '', 'division' => ''];
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($values) as $field) {
        $values[$field] = trim((string) ($_POST[$field] ?? ''));
    }
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Your session expired. Please refresh the page and try again.';
    }
    if (mb_strlen($values['full_name']) < 2 || mb_strlen($values['full_name']) > 100) {
        $errors[] = 'Enter a valid name between 2 and 100 characters.';
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if (!preg_match('/^[0-9+()\-\s]{7,25}$/', $values['phone'])) {
        $errors[] = 'Enter a valid phone number.';
    }
    if (!isset($roles[$values['role']])) {
        $errors[] = 'Select a valid role.';
    }
    if ($values['role'] === 'social-service-officer' && $values['division'] === '') {
        $errors[] = 'Division is required for a Social Service Officer.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must contain at least 8 characters.';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Password and confirm password do not match.';
    }

    if ($errors === []) {
        try {
            $duplicate = database()->prepare(
                "SELECT email FROM registration_requests WHERE email = :request_email AND status = 'pending'
                 UNION SELECT username AS email FROM users WHERE username = :user_email LIMIT 1"
            );
            $normalizedEmail = strtolower($values['email']);
            $duplicate->execute([
                'request_email' => $normalizedEmail,
                'user_email' => $normalizedEmail,
            ]);

            if ($duplicate->fetch()) {
                $errors[] = 'This email already has an account or a pending request.';
            } else {
                $statement = database()->prepare(
                    'INSERT INTO registration_requests
                     (full_name, email, phone, password_hash, role, division)
                     VALUES (:full_name, :email, :phone, :password_hash, :role, :division)'
                );
                $statement->execute([
                    'full_name' => $values['full_name'],
                    'email' => strtolower($values['email']),
                    'phone' => $values['phone'],
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => $values['role'],
                    'division' => $values['role'] === 'social-service-officer' ? $values['division'] : null,
                ]);
                $success = 'Your request was sent to the administrator. You will receive an email after it is reviewed.';
                $values = array_fill_keys(array_keys($values), '');
                unset($_SESSION['csrf_token']);
            }
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            $errors[] = 'Unable to submit the request. Ask the administrator to install the registration database migration.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Request an Account | WIDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/login.css" rel="stylesheet">
</head>
<body>
<main class="login-page signup-page d-flex align-items-center justify-content-center">
    <section class="login-card signup-card" aria-labelledby="signup-title">
        <header class="brand d-flex align-items-center">
            <img class="brand-mark" src="assets/images/client-logo.jpeg" alt="WIDMS logo">
            <div>
                <p class="brand-name mb-0">WIDMS</p>
                <p class="brand-description mb-0">Welfare Inventory &amp; Distribution Management</p>
            </div>
        </header>
        <div class="intro">
            <h1 id="signup-title">Request an account</h1>
            <p>Complete the form. An administrator must approve your account before you can sign in.</p>
        </div>

        <?php if ($errors !== []): ?>
        <div class="alert alert-danger py-2" role="alert">
            <ul class="mb-0 ps-3">

                <?php foreach ($errors as $error): ?>
                <li>
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </li>
                <?php endforeach; ?>
                
            </ul>
        </div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
        <div class="alert alert-success" role="status">
            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>

        <form method="post" action="signup.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="signup-grid">
                <div class="full-width">
                    <label class="form-label" for="full_name">Full name</label>
                    <input class="form-control" id="full_name" name="full_name" maxlength="100" value="<?= htmlspecialchars($values['full_name'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div>
                    <label class="form-label" for="email">Email address</label>
                    <input class="form-control" type="email" id="email" name="email" maxlength="120" value="<?= htmlspecialchars($values['email'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div>
                    <label class="form-label" for="phone">Phone number</label>
                    <input class="form-control" type="tel" id="phone" name="phone" maxlength="25" value="<?= htmlspecialchars($values['phone'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div>
                    <label class="form-label" for="password">Password</label>
                    <input class="form-control" type="password" id="password" name="password" minlength="8" autocomplete="new-password" required>
                </div>
                <div>
                    <label class="form-label" for="confirm_password">Confirm password</label>
                    <input class="form-control" type="password" id="confirm_password" name="confirm_password" minlength="8" autocomplete="new-password" required>
                </div>
                <div class="full-width">
                    <label class="form-label" for="role">Requested role</label>
                    <select class="form-select" id="role" name="role" required>
                        <option value="">Select a role</option>

                        <?php foreach ($roles as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $values['role'] === $value ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option><?php endforeach; ?>

                    </select>
                </div>
                <div class="full-width" id="division-field" <?= $values['role'] === 'social-service-officer' ? '' : 'hidden' ?>>
                    <label class="form-label" for="division">Division</label>
                    <input class="form-control" id="division" name="division" maxlength="120" value="<?= htmlspecialchars($values['division'], ENT_QUOTES, 'UTF-8') ?>">
                    <p class="field-help">Required only for Social Service Officers.</p>
                </div>
            </div>
            <button class="btn btn-primary sign-in-button w-100 mt-3" type="submit">Send request</button>
            <p class="signup-prompt mb-0">Already registered? <a href="login.php">Back to sign in</a></p>
        </form>
    </section>
</main>
<script>
const role = document.getElementById('role');
const divisionField = document.getElementById('division-field');
const division = document.getElementById('division');
function updateDivision() {
    const required = role.value === 'social-service-officer';
    divisionField.hidden = !required;
    division.required = required;
    if (!required) division.value = '';
}
role.addEventListener('change', updateDivision);
updateDivision();
</script>
</body>
</html>
