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
$values = ['full_name' => '', 'email' => '', 'phone' => '', 'role' => '', 'district_id' => '', 'ds_division_id' => ''];
$errors = [];
$success = '';
$districts = [];
$dsDivisions = [];

try {
    $districts = database()->query(
        "SELECT id,name FROM districts WHERE status='active' ORDER BY name"
    )->fetchAll();
    $dsDivisions = database()->query(
        "SELECT ds.id,ds.district_id,ds.name,ds.division_type,
                EXISTS(
                    SELECT 1 FROM users u
                    WHERE u.ds_division_id=ds.id
                      AND u.role='social-service-officer'
                      AND u.status='active'
                ) AS has_active_sso
         FROM ds_divisions ds
         WHERE ds.status='active'
         ORDER BY ds.division_type,ds.name"
    )->fetchAll();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $errors[] = 'District and DS Division information is currently unavailable.';
}

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
    $selectedDivisionName = null;
    if ($values['role'] === 'social-service-officer') {
        $districtId = filter_var($values['district_id'], FILTER_VALIDATE_INT);
        $dsDivisionId = filter_var($values['ds_division_id'], FILTER_VALIDATE_INT);

        if (!$districtId || !$dsDivisionId) {
            $errors[] = 'Select a district and DS Division for the Social Service Officer.';
        } else {
            $divisionCheck = database()->prepare(
                "SELECT ds.name,
                        EXISTS(
                            SELECT 1 FROM users u
                            WHERE u.ds_division_id=ds.id
                              AND u.role='social-service-officer'
                              AND u.status='active'
                        ) AS has_active_sso
                 FROM ds_divisions ds
                 JOIN districts d ON d.id=ds.district_id
                 WHERE ds.id=:ds_id AND ds.district_id=:district_id
                   AND ds.status='active' AND d.status='active'
                 LIMIT 1"
            );
            $divisionCheck->execute([
                'ds_id' => $dsDivisionId,
                'district_id' => $districtId,
            ]);
            $selectedDivision = $divisionCheck->fetch();
            $selectedDivisionName = $selectedDivision['name'] ?? null;

            if (!$selectedDivisionName) {
                $errors[] = 'The selected DS Division does not belong to the selected district.';
            } elseif ((int) $selectedDivision['has_active_sso'] === 1) {
                // Server validation prevents bypassing an occupied option by editing the form manually.
                $errors[] = t('This division already has an active Social Service Officer.');
            }
        }
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
                     (full_name, email, phone, password_hash, role, division, district_id, ds_division_id)
                     VALUES (:full_name, :email, :phone, :password_hash, :role, :division, :district_id, :ds_division_id)'
                );
                $statement->execute([
                    'full_name' => $values['full_name'],
                    'email' => strtolower($values['email']),
                    'phone' => $values['phone'],
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => $values['role'],
                    'division' => $values['role'] === 'social-service-officer' ? $selectedDivisionName : null,
                    'district_id' => $values['role'] === 'social-service-officer' ? (int) $values['district_id'] : null,
                    'ds_division_id' => $values['role'] === 'social-service-officer' ? (int) $values['ds_division_id'] : null,
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
<html lang="<?= htmlspecialchars(widmsLanguage(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Request an Account | WIDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/login.css?v=3" rel="stylesheet">
</head>
<body>
<main class="login-page signup-page auth-layout">
    <!-- The brand panel keeps account registration visually connected to WIDMS. -->
    <aside class="auth-showcase signup-showcase" aria-label="About WIDMS registration">
        <?php renderLanguageSwitcher('auth-language'); ?>
        <div class="showcase-badge">Join WIDMS</div>
        <div class="showcase-content">
            <p class="showcase-kicker">One coordinated service</p>
            <h2>Create access for your role in the welfare distribution network.</h2>
            <p>Your request is reviewed by an administrator before the account becomes active.</p>
            <ol class="signup-steps">
                <li><span>1</span>Enter your official details</li>
                <li><span>2</span>Select your assigned role</li>
                <li><span>3</span>Wait for administrator approval</li>
            </ol>
        </div>
        <p class="showcase-footer">Secure registration for authorized officers</p>
    </aside>
    <section class="login-card signup-card" aria-labelledby="signup-title">
        <?php renderLanguageSwitcher('mobile-language'); ?>
        <header class="brand d-flex align-items-center">
            <img class="brand-mark" src="assets/images/client-logo.jpeg" alt="WIDMS logo">
            <div>
                <p class="brand-name mb-0">WIDMS</p>
                <p class="brand-description mb-0">Welfare Inventory &amp; Distribution Management</p>
            </div>
        </header>
        <div class="intro">
            <p class="form-kicker">Officer registration</p>
            <h1 id="signup-title">Request an account</h1>
            <p>Complete your details. An administrator will review your request before access is granted.</p>
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
                <div id="division-field" <?= $values['role'] === 'social-service-officer' ? '' : 'hidden' ?>>
                    <label class="form-label" for="district_id">District</label>
                    <select class="form-select" id="district_id" name="district_id">
                        <option value="">Select a district</option>
                        <?php foreach ($districts as $district): ?>
                        <option value="<?= (int) $district['id'] ?>" <?= $values['district_id'] === (string) $district['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($district['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="ds-division-field" <?= $values['role'] === 'social-service-officer' ? '' : 'hidden' ?>>
                    <label class="form-label" for="ds_division_id">DS Division</label>
                    <select class="form-select" id="ds_division_id" name="ds_division_id">
                        <option value="">Select a DS Division</option>
                        <?php foreach ($dsDivisions as $dsDivision): ?>
                        <option value="<?= (int) $dsDivision['id'] ?>" data-district="<?= (int) $dsDivision['district_id'] ?>" data-occupied="<?= (int) $dsDivision['has_active_sso'] ?>" <?= $values['ds_division_id'] === (string) $dsDivision['id'] ? 'selected' : '' ?> <?= (int) $dsDivision['has_active_sso'] === 1 ? 'disabled' : '' ?>>
                            <?= htmlspecialchars(
                                $dsDivision['name'] .
                                ($dsDivision['division_type'] === 'service-centre' ? ' — ' . t('Service Division') : '') .
                                ((int) $dsDivision['has_active_sso'] === 1 ? ' — ' . t('Unavailable: active SSO assigned') : ''),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
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
const dsDivisionField = document.getElementById('ds-division-field');
const district = document.getElementById('district_id');
const division = document.getElementById('ds_division_id');
const divisionOptions = Array.from(division.options).slice(1);

function filterDivisions(resetSelection = false) {
    if (resetSelection) division.value = '';
    divisionOptions.forEach(option => {
        const visible = district.value !== '' && option.dataset.district === district.value;
        option.hidden = !visible;
        // Occupied divisions stay frozen even when their district is selected.
        option.disabled = !visible || option.dataset.occupied === '1';
    });
}

function updateDivision() {
    const required = role.value === 'social-service-officer';
    divisionField.hidden = !required;
    dsDivisionField.hidden = !required;
    district.required = required;
    division.required = required;
    if (!required) {
        district.value = '';
        division.value = '';
    }
    filterDivisions();
}
role.addEventListener('change', updateDivision);
district.addEventListener('change', () => filterDivisions(true));
updateDivision();
</script>
<script src="assets/js/password-toggle.js?v=2"></script>
<?= widmsUiTranslationAssetsHtml() ?>
</body>
</html>
