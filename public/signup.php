<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/sms.php';
require_once __DIR__ . '/../includes/registration.php';
require_once __DIR__ . '/../includes/form-submissions.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$roles = [
    'subject-officer' => 'Subject Officer',
    'store-keeper' => 'Store Keeper',
    'social-service-officer' => 'Social Service Officer',
];
$values = ['full_name' => '', 'salary_number' => '', 'email' => '', 'phone' => '', 'role' => '', 'district_id' => '', 'ds_division_id' => ''];
$errors = [];
$success = (string) ($_SESSION['signup_success'] ?? '');
unset($_SESSION['signup_success']);
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
    if (!widmsConsumeFormSubmissionToken($_POST['widms_submission_token'] ?? null)) {
        $errors[] = 'This form was already submitted or has expired. Refresh the page and try again.';
    }
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
    if (widmsSalaryNumber($values['salary_number']) === null) {
        $errors[] = 'Enter a valid salary number using 1 to 30 digits.';
    }
    if (strlen($values['phone']) > 25 || widmsSmsPhone($values['phone']) === null) {
        $errors[] = 'Enter a valid Sri Lankan mobile number (07XXXXXXXX or +947XXXXXXXX).';
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
                "SELECT email FROM registration_requests WHERE email = :request_email AND status IN ('pending','approved')
                 UNION SELECT username AS email FROM users WHERE username = :user_email OR email = :contact_email LIMIT 1"
            );
            $normalizedEmail = strtolower($values['email']);
            $duplicate->execute([
                'request_email' => $normalizedEmail,
                'user_email' => $normalizedEmail,
                'contact_email' => $normalizedEmail,
            ]);
            $salaryDuplicate = database()->prepare(
                'SELECT id FROM registration_requests WHERE salary_number = :request_salary
                 UNION SELECT id FROM users WHERE salary_number = :user_salary OR username = :login LIMIT 1'
            );
            $salaryDuplicate->execute(['request_salary' => $values['salary_number'], 'user_salary' => $values['salary_number'], 'login' => widmsSalaryUsername($values['salary_number'])]);

            if ($duplicate->fetch()) {
                $errors[] = 'This email already has an account or a pending request.';
            } elseif ($salaryDuplicate->fetch()) {
                $errors[] = 'This salary number is already registered.';
            } else {
                $statement = database()->prepare(
                    'INSERT INTO registration_requests
                     (full_name, salary_number, email, phone, password_hash, role, division, district_id, ds_division_id)
                     VALUES (:full_name, :salary_number, :email, :phone, :password_hash, :role, :division, :district_id, :ds_division_id)'
                );
                $statement->execute([
                    'full_name' => $values['full_name'],
                    'salary_number' => $values['salary_number'],
                    'email' => strtolower($values['email']),
                    'phone' => $values['phone'],
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => $values['role'],
                    'division' => $values['role'] === 'social-service-officer' ? $selectedDivisionName : null,
                    'district_id' => $values['role'] === 'social-service-officer' ? (int) $values['district_id'] : null,
                    'ds_division_id' => $values['role'] === 'social-service-officer' ? (int) $values['ds_division_id'] : null,
                ]);
                $_SESSION['signup_success'] = 'Your request was sent to the administrator. You will receive an email after it is reviewed.';
                header('Location: signup.php', true, 303);
                exit;
            }
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            $errors[] = (int) ($exception->errorInfo[1] ?? 0) === 1062
                ? 'This salary number is already registered.'
                : 'Unable to submit the request. Ask the administrator to install the registration database migration.';
        }
    }
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars(widmsLanguage(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(t('Request an account'), ENT_QUOTES, 'UTF-8') ?> | SWPCS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/login.css?v=3" rel="stylesheet">
</head>
<body>
<main class="login-page signup-page auth-layout">
    <!-- The brand panel keeps account registration visually connected to SWPCS. -->
    <aside class="auth-showcase signup-showcase" aria-label="<?= htmlspecialchars(t('About SWPCS registration'), ENT_QUOTES, 'UTF-8') ?>">
        <?php renderLanguageSwitcher('auth-language'); ?>
        <div class="showcase-badge"><?= htmlspecialchars(t('Join SWPCS'), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="showcase-content">
            <p class="showcase-kicker"><?= htmlspecialchars(t('One coordinated service'), ENT_QUOTES, 'UTF-8') ?></p>
            <h2><?= htmlspecialchars(t('Create access for your role in the welfare distribution network.'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p><?= htmlspecialchars(t('Your request is reviewed by an administrator before the account becomes active.'), ENT_QUOTES, 'UTF-8') ?></p>
            <ol class="signup-steps">
                <li><span>1</span><?= htmlspecialchars(t('Enter your official details'), ENT_QUOTES, 'UTF-8') ?></li>
                <li><span>2</span><?= htmlspecialchars(t('Select your assigned role'), ENT_QUOTES, 'UTF-8') ?></li>
                <li><span>3</span><?= htmlspecialchars(t('Wait for administrator approval'), ENT_QUOTES, 'UTF-8') ?></li>
            </ol>
        </div>
        <p class="showcase-footer"><?= htmlspecialchars(t('Secure registration for authorized officers'), ENT_QUOTES, 'UTF-8') ?></p>
    </aside>
    <section class="login-card signup-card" aria-labelledby="signup-title">
        <?php renderLanguageSwitcher('mobile-language'); ?>
        <header class="brand d-flex align-items-center">
            <img class="brand-mark" src="assets/images/client-logo.jpeg" alt="SWPCS logo">
            <div>
                <p class="brand-name mb-0">SWPCS</p>
                <p class="brand-description mb-0"><?= htmlspecialchars(t('Welfare Inventory & Distribution Management'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </header>
        <div class="intro">
            <p class="form-kicker"><?= htmlspecialchars(t('Officer registration'), ENT_QUOTES, 'UTF-8') ?></p>
            <h1 id="signup-title"><?= htmlspecialchars(t('Request an account'), ENT_QUOTES, 'UTF-8') ?></h1>
            <p><?= htmlspecialchars(t('Complete your details. An administrator will review your request before access is granted.'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>

        <?php if ($errors !== []): ?>
        <div class="alert alert-danger py-2" role="alert">
            <ul class="mb-0 ps-3">

                <?php foreach ($errors as $error): ?>
                <li>
                    <?= htmlspecialchars(t($error), ENT_QUOTES, 'UTF-8') ?>
                </li>
                <?php endforeach; ?>
                
            </ul>
        </div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
        <div class="alert alert-success" role="status">
            <?= htmlspecialchars(t($success), ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>

        <form method="post" action="signup.php">
            <?= widmsFormSubmissionField() ?>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="signup-grid">
                <div class="full-width">
                    <label class="form-label" for="full_name"><?= htmlspecialchars(t('Full name'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input class="form-control" id="full_name" name="full_name" maxlength="100" value="<?= htmlspecialchars($values['full_name'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="full-width">
                    <label class="form-label" for="salary_number"><?= htmlspecialchars(t('Salary Number'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input class="form-control" type="text" id="salary_number" name="salary_number" inputmode="numeric" pattern="[0-9]{1,30}" maxlength="30" aria-describedby="salary-help" value="<?= htmlspecialchars($values['salary_number'], ENT_QUOTES, 'UTF-8') ?>" required>
                    <small id="salary-help" class="form-text"><?= htmlspecialchars(t('Your username will be swpcs followed by your salary number.'), ENT_QUOTES, 'UTF-8') ?></small>
                </div>
                <div>
                    <label class="form-label" for="email"><?= htmlspecialchars(t('Email address'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input class="form-control" type="email" id="email" name="email" maxlength="120" value="<?= htmlspecialchars($values['email'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div>
                    <label class="form-label" for="phone"><?= htmlspecialchars(t('Phone Number'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input class="form-control" type="tel" id="phone" name="phone" maxlength="25" autocomplete="tel" aria-describedby="phone-help" value="<?= htmlspecialchars($values['phone'], ENT_QUOTES, 'UTF-8') ?>" required>
                    <small id="phone-help" class="form-text"><?= htmlspecialchars(t('Use a mobile number to receive your account approval SMS.'), ENT_QUOTES, 'UTF-8') ?></small>
                </div>
                <div>
                    <label class="form-label" for="password"><?= htmlspecialchars(t('Password'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input class="form-control" type="password" id="password" name="password" minlength="8" autocomplete="new-password" required>
                </div>
                <div>
                    <label class="form-label" for="confirm_password"><?= htmlspecialchars(t('Confirm password'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input class="form-control" type="password" id="confirm_password" name="confirm_password" minlength="8" autocomplete="new-password" required>
                </div>
                <div class="full-width">
                    <label class="form-label" for="role"><?= htmlspecialchars(t('Requested role'), ENT_QUOTES, 'UTF-8') ?></label>
                    <select class="form-select" id="role" name="role" required>
                        <option value=""><?= htmlspecialchars(t('Select a role'), ENT_QUOTES, 'UTF-8') ?></option>

                        <?php foreach ($roles as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $values['role'] === $value ? 'selected' : '' ?>>
                            <?= htmlspecialchars(t($label), ENT_QUOTES, 'UTF-8') ?>
                        </option><?php endforeach; ?>

                    </select>
                </div>
                <div id="division-field" <?= $values['role'] === 'social-service-officer' ? '' : 'hidden' ?>>
                    <label class="form-label" for="district_id"><?= htmlspecialchars(t('District'), ENT_QUOTES, 'UTF-8') ?></label>
                    <select class="form-select" id="district_id" name="district_id">
                        <option value=""><?= htmlspecialchars(t('Select a district'), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php foreach ($districts as $district): ?>
                        <option value="<?= (int) $district['id'] ?>" <?= $values['district_id'] === (string) $district['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($district['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="ds-division-field" <?= $values['role'] === 'social-service-officer' ? '' : 'hidden' ?>>
                    <label class="form-label" for="ds_division_id"><?= htmlspecialchars(t('DS Division'), ENT_QUOTES, 'UTF-8') ?></label>
                    <select class="form-select" id="ds_division_id" name="ds_division_id">
                        <option value=""><?= htmlspecialchars(t('Select a DS Division'), ENT_QUOTES, 'UTF-8') ?></option>
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
                    <p class="field-help"><?= htmlspecialchars(t('Required only for Social Service Officers.'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
            <button class="btn btn-primary sign-in-button w-100 mt-3" type="submit"><?= htmlspecialchars(t('Send request'), ENT_QUOTES, 'UTF-8') ?></button>
            <p class="signup-prompt mb-0"><?= htmlspecialchars(t('Already registered?'), ENT_QUOTES, 'UTF-8') ?> <a href="login.php"><?= htmlspecialchars(t('Back to sign in'), ENT_QUOTES, 'UTF-8') ?></a></p>
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
<script src="assets/js/form-submit-guard.js?v=<?= filemtime(__DIR__ . '/assets/js/form-submit-guard.js') ?>"></script>
<?= widmsUiTranslationAssetsHtml() ?>
</body>
</html>
