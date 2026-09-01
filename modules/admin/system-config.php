<?php
declare(strict_types=1);

requireRole('admin');
require_once __DIR__ . '/../../config/database.php';

$activePage = 'system-config';
$allowedSettings = [
    'low_stock_threshold' => ['integer', 0, 100000],
    'session_timeout_minutes' => ['integer', 1, 1440],
    'max_failed_logins' => ['integer', 1, 20],
    'audit_retention_years' => ['integer', 1, 50],
    'notify_low_stock' => ['boolean'],
    'notify_pending_approval' => ['boolean'],
    'notify_payment_due' => ['boolean'],
    'email_notifications' => ['boolean'],
];

$error = '';
$success = isset($_GET['saved']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'save-setting');
    $key = (string) ($_POST['setting_key'] ?? '');
    $value = trim((string) ($_POST['setting_value'] ?? ''));
    $token = (string) ($_POST['csrf_token'] ?? '');

    if (!verifyCsrfToken($token)) {
        $error = 'Your session expired. Please try again.';
    } elseif ($action === 'add-disability') {
        $name = preg_replace('/\s+/', ' ', trim((string) ($_POST['disability_name'] ?? '')));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 150) {
            $error = 'Enter a disability type between 2 and 150 characters.';
        } else {
            try {
                $statement = database()->prepare("INSERT INTO disability_types(name,status,created_by) VALUES(:name,'active',:user) ON DUPLICATE KEY UPDATE status='active'");
                $statement->execute(['name' => $name, 'user' => $_SESSION['user_id']]);
                header('Location: dashboard.php?page=system-config&saved=1#disability-types');
                exit;
            } catch (PDOException $exception) {
                error_log($exception->getMessage());
                $error = 'Unable to add the disability type.';
            }
        }
    } elseif ($action === 'toggle-disability') {
        $disabilityId = filter_input(INPUT_POST, 'disability_id', FILTER_VALIDATE_INT);
        if (!$disabilityId) {
            $error = 'Select a valid disability type.';
        } else {
            try {
                $statement = database()->prepare("UPDATE disability_types SET status=IF(status='active','inactive','active') WHERE id=:id");
                $statement->execute(['id' => $disabilityId]);
                header('Location: dashboard.php?page=system-config&saved=1#disability-types');
                exit;
            } catch (PDOException $exception) {
                error_log($exception->getMessage());
                $error = 'Unable to update the disability type.';
            }
        }
    } elseif (!isset($allowedSettings[$key])) {
        $error = 'The selected setting is invalid.';
    } else {
        $rules = $allowedSettings[$key];
        if ($rules[0] === 'boolean' && !in_array($value, ['true', 'false'], true)) {
            $error = 'Select a valid true or false value.';
        } elseif ($rules[0] === 'integer' && filter_var($value, FILTER_VALIDATE_INT) === false) {
            $error = 'Enter a valid whole number.';
        } elseif ($rules[0] === 'integer' && ((int) $value < $rules[1] || (int) $value > $rules[2])) {
            $error = sprintf('Enter a value between %d and %d.', $rules[1], $rules[2]);
        }

        if ($error === '') {
            try {
                $statement = database()->prepare(
                    'UPDATE system_settings
                     SET setting_value = :setting_value, updated_by = :updated_by
                     WHERE setting_key = :setting_key'
                );
                $statement->execute([
                    'setting_value' => $value,
                    'updated_by' => $_SESSION['user_id'],
                    'setting_key' => $key,
                ]);
                header('Location: dashboard.php?page=system-config&saved=1');
                exit;
            } catch (PDOException $exception) {
                error_log($exception->getMessage());
                $error = 'Unable to save the setting. Make sure MySQL is running.';
            }
        }
    }
}

$settings = ['general' => [], 'notification' => []];
$disabilityTypes = [];
try {
    $rows = database()->query(
        "SELECT setting_key, setting_value, setting_type, setting_group, description
         FROM system_settings
         ORDER BY FIELD(setting_group, 'general', 'notification'), id"
    )->fetchAll();
    foreach ($rows as $row) {
        $settings[$row['setting_group']][] = $row;
    }
    $disabilityTypes = database()->query("SELECT id,name,status FROM disability_types ORDER BY status='active' DESC,name")->fetchAll();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $error = 'Unable to load system settings. Make sure MySQL is running.';
}

function renderSetting(array $setting): void
{
    $key = htmlspecialchars($setting['setting_key'], ENT_QUOTES, 'UTF-8');
    $description = htmlspecialchars($setting['description'], ENT_QUOTES, 'UTF-8');
    $value = htmlspecialchars($setting['setting_value'], ENT_QUOTES, 'UTF-8');
    $type = htmlspecialchars(ucfirst($setting['setting_type']), ENT_QUOTES, 'UTF-8');
    ?>
    <form class="setting-row" method="post" action="dashboard.php?page=system-config">
        <div class="setting-heading"><code><?= $key ?></code><span><?= $type ?></span></div>
        <p><?= $description ?></p>
        <div class="setting-control">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="setting_key" value="<?= $key ?>">
            <?php if ($setting['setting_type'] === 'boolean'): ?>
                <select name="setting_value" aria-label="<?= $key ?> value">
                    <option value="true" <?= $setting['setting_value'] === 'true' ? 'selected' : '' ?>>true</option>
                    <option value="false" <?= $setting['setting_value'] === 'false' ? 'selected' : '' ?>>false</option>
                </select>
            <?php else: ?>
                <input type="number" name="setting_value" value="<?= $value ?>" aria-label="<?= $key ?> value" required>
            <?php endif; ?>
            <button type="submit">Save</button>
        </div>
    </form>
    <?php
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>System Configuration | WIDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css" rel="stylesheet">
</head>
<body>
    <?php require __DIR__ . '/../../includes/admin-sidebar.php'; ?>

    <div class="admin-shell">
        <header class="topbar">
            <div class="d-flex align-items-center gap-3">
                <button type="button" class="menu-button" id="menu-button" aria-label="Open navigation">☰</button>
                <h1>System Configuration</h1>
            </div>
            <div class="topbar-actions">
                <label class="search-box"><span aria-hidden="true">🔍</span><input type="search" placeholder="Search anything..." aria-label="Search"></label>
                <button class="notification-button" type="button" aria-label="Notifications">🔔</button>
            </div>
        </header>

        <main class="dashboard-content config-page">
            <div class="config-warning">⚠️ These settings affect system-wide behavior. Changes take effect immediately without redeployment.</div>
            <?php if ($success): ?><div class="config-success" role="status">Setting saved successfully.</div><?php endif; ?>
            <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

            <div class="config-grid">
                <section class="config-card">
                    <h2>General Settings</h2>
                    <div class="config-card-body">
                        <?php foreach ($settings['general'] as $setting) renderSetting($setting); ?>
                    </div>
                </section>
                <section class="config-card">
                    <h2>Notification Settings</h2>
                    <div class="config-card-body">
                        <?php foreach ($settings['notification'] as $setting) renderSetting($setting); ?>
                    </div>
                </section>
            </div>

            <section class="config-card disability-config-card" id="disability-types">
                <h2>Disability Types</h2>
                <div class="config-card-body">
                    <p class="config-section-help">Active types appear in the Nature of Disability dropdown on aid and beneficiary forms.</p>
                    <form class="disability-add-form" method="post" action="dashboard.php?page=system-config#disability-types">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="add-disability">
                        <label for="disability-name">New disability type</label>
                        <div><input id="disability-name" name="disability_name" maxlength="150" placeholder="e.g. Psychosocial Disability" required><button type="submit">Add Type</button></div>
                    </form>
                    <div class="disability-type-list">
                        <?php if ($disabilityTypes === []): ?><p class="dashboard-empty-state">No disability types configured.</p><?php else: foreach ($disabilityTypes as $type): ?>
                            <div class="disability-type-row">
                                <span><?= htmlspecialchars($type['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                <form method="post" action="dashboard.php?page=system-config#disability-types">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="action" value="toggle-disability">
                                    <input type="hidden" name="disability_id" value="<?= (int) $type['id'] ?>">
                                    <button type="submit" class="disability-status <?= $type['status'] === 'active' ? 'active' : 'inactive' ?>"><?= $type['status'] === 'active' ? 'Active' : 'Inactive' ?></button>
                                </form>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script src="assets/js/admin-dashboard.js"></script>
</body>
</html>
