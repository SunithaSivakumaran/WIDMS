<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/activity.php';

requireLogin();

$roleLabels = [
    'admin' => 'Administrator',
    'subject-officer' => 'Subject Officer',
    'store-keeper' => 'Store Keeper',
    'social-service-officer' => 'Social Service Officer',
];
$errors = [];
$success = (string) ($_SESSION['flash_success'] ?? '');
unset($_SESSION['flash_success']);
$activePage = 'profile';

try {
    $statement = database()->prepare('SELECT id, full_name, username, phone, division, profile_image, password_hash, role FROM users WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $_SESSION['user_id']]);
    $user = $statement->fetch();
    if (!$user) {
        logoutUser();
        header('Location: login.php');
        exit;
    }
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    exit('Unable to load your profile. Import database/migration_user_profiles.sql first.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $division = trim((string) ($_POST['division'] ?? ''));
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) $errors[] = 'Your session expired. Refresh the page and try again.';
    if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 100) $errors[] = 'Enter a valid name between 2 and 100 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (!preg_match('/^[0-9+()\-\s]{7,25}$/', $phone)) $errors[] = 'Enter a valid phone number.';
    if ($user['role'] === 'social-service-officer' && $division === '') $errors[] = 'Division is required for a Social Service Officer.';
    if ($newPassword !== '') {
        if (strlen($newPassword) < 8) $errors[] = 'The new password must contain at least 8 characters.';
        if ($newPassword !== $confirmPassword) $errors[] = 'New password and confirmation do not match.';
        if (!password_verify($currentPassword, $user['password_hash'])) $errors[] = 'Your current password is incorrect.';
    }

    if ($errors === []) {
        $duplicate = database()->prepare('SELECT id FROM users WHERE username = :email AND id <> :id LIMIT 1');
        $duplicate->execute(['email' => $email, 'id' => $user['id']]);
        if ($duplicate->fetch()) $errors[] = 'Another account already uses this email address.';
    }

    $profileImage = $user['profile_image'];
    if ($errors === [] && isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload = $_FILES['profile_image'];
        if ($upload['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'The profile image could not be uploaded.';
        } elseif ((int) $upload['size'] > 2 * 1024 * 1024) {
            $errors[] = 'The profile image must be 2 MB or smaller.';
        } else {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
            $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($extensions[$mime])) {
                $errors[] = 'Upload a JPG, PNG, or WebP image.';
            } else {
                $uploadDirectory = __DIR__ . '/uploads/profiles';
                if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
                    $errors[] = 'The profile-image folder could not be created.';
                } else {
                    $filename = sprintf('user-%d-%s.%s', $user['id'], bin2hex(random_bytes(8)), $extensions[$mime]);
                    if (!move_uploaded_file($upload['tmp_name'], $uploadDirectory . '/' . $filename)) {
                        $errors[] = 'The profile image could not be saved.';
                    } else {
                        $profileImage = 'uploads/profiles/' . $filename;
                    }
                }
            }
        }
    }

    if ($errors === []) {
        try {
            $sql = 'UPDATE users SET full_name = :full_name, username = :email, phone = :phone, division = :division, profile_image = :profile_image';
            $parameters = [
                'full_name' => $fullName, 'email' => $email, 'phone' => $phone,
                'division' => $user['role'] === 'social-service-officer' ? $division : null,
                'profile_image' => $profileImage, 'id' => $user['id'],
            ];
            if ($newPassword !== '') {
                $sql .= ', password_hash = :password_hash';
                $parameters['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
            }
            $sql .= ' WHERE id = :id';
            $update = database()->prepare($sql);
            $update->execute($parameters);

            $_SESSION['full_name'] = $fullName;
            $_SESSION['username'] = $email;
            $_SESSION['profile_image'] = $profileImage;
            $user = array_merge($user, ['full_name' => $fullName, 'username' => $email, 'phone' => $phone, 'division' => $parameters['division'], 'profile_image' => $profileImage]);
            $_SESSION['flash_success'] = 'Your profile was updated successfully.';
            logActivity('Profile', 'Updated profile settings', 'USR-' . $user['id'], 'done');
            unset($_SESSION['csrf_token']);
            header('Location: profile.php');
            exit;
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            $errors[] = 'Unable to update your profile.';
        }
    } else {
        $user = array_merge($user, ['full_name' => $fullName, 'username' => $email, 'phone' => $phone, 'division' => $division]);
    }
}

$sidebarFiles = [
    'admin' => __DIR__ . '/../includes/admin-sidebar.php',
    'subject-officer' => __DIR__ . '/../includes/subject-officer-sidebar.php',
    'store-keeper' => __DIR__ . '/../includes/store-keeper-sidebar.php',
    'social-service-officer' => __DIR__ . '/../includes/social-service-officer-sidebar.php',
];
$sidebar = $sidebarFiles[$_SESSION['role']] ?? null;
?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Edit Profile | WIDMS</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="assets/css/admin-dashboard.css" rel="stylesheet">
    </head>
    <body class="profile-page-body">
        <?php if ($sidebar) require $sidebar; ?>
        <div class="admin-shell">
            <header class="topbar">
                <div class="d-flex align-items-center gap-3">
                    <button type="button" class="menu-button" id="menu-button" aria-label="Open navigation">
                        ☰
                    </button>
                    <h1>Edit Profile</h1>
                </div>
            </header>
            <main class="dashboard-content profile-page">

                <?php if ($success !== ''): ?>
                <div class="alert alert-success" role="status">
                    <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php endif; ?>

                <?php if ($errors !== []): ?>
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0 ps-3">

                        <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>

                    </ul>
                </div>
                <?php endif; ?>

                <section class="profile-card">
                    <div class="profile-card-header">
                        <h2>Personal Information</h2>
                        <p>You can update your personal details. Your assigned role cannot be changed.</p>
                    </div>
                    <form method="post" enctype="multipart/form-data" class="profile-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="profile-photo-section">

                            <?php if ($user['profile_image']): ?>
                                <img src="<?= htmlspecialchars($user['profile_image'], ENT_QUOTES, 'UTF-8') ?>" alt="Current profile photo" class="profile-photo-preview">
                            <?php else: ?>
                                <span class="profile-photo-preview profile-photo-fallback">
                                    <?= htmlspecialchars(strtoupper(substr($user['full_name'], 0, 1)), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            <?php endif; ?>

                                <label>
                                    Profile image
                                    <input type="file" name="profile_image" accept="image/jpeg,image/png,image/webp">
                                    <small>JPG, PNG, or WebP · maximum 2 MB</small>
                                </label>
                        </div>
                        <div class="profile-form-grid">
                            <label>
                                Full name
                                <input name="full_name" maxlength="100" value="<?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </label>
                            <label>
                                Email address
                                <input type="email" name="email" maxlength="120" value="<?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </label>
                            <label>
                                Phone number
                                <input type="tel" name="phone" maxlength="25" value="<?= htmlspecialchars((string) $user['phone'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </label>
                            <label>
                                Role
                                <input value="<?= htmlspecialchars($roleLabels[$user['role']] ?? $user['role'], ENT_QUOTES, 'UTF-8') ?>" disabled>
                                <small>Your role can only be managed by an administrator.</small>
                            </label>

                            <?php if ($user['role'] === 'social-service-officer'): ?>
                            <label class="full-width">
                                Division
                                <input name="division" maxlength="120" value="<?= htmlspecialchars((string) $user['division'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </label>
                            <?php endif; ?>

                        </div>
                        <div class="password-section">
                            <h3>Change Password</h3>
                            <p>Leave these fields empty to keep your current password.</p>
                            <div class="profile-form-grid">
                                <label>
                                    Current password
                                    <input type="password" name="current_password" autocomplete="current-password">
                                </label>
                                <label>
                                    New password
                                    <input type="password" name="new_password" minlength="8" autocomplete="new-password">
                                </label>
                                <label>
                                    Confirm new password
                                    <input type="password" name="confirm_password" minlength="8" autocomplete="new-password">
                                </label>
                            </div>
                        </div>
                        <button class="profile-save-button" type="submit">Save Profile</button>
                    </form>
                </section>
            </main>
        </div>
        <script src="assets/js/admin-dashboard.js"></script>
    </body>
</html>
