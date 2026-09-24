<?php
declare(strict_types=1);

requireRole('admin');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/activity.php';
require_once __DIR__ . '/../../includes/sms.php';

$activePage = 'users';
$users = $districts = $dsDivisions = $errors = [];
$loadError = '';
$success = (string) ($_SESSION['flash_success'] ?? '');
unset($_SESSION['flash_success']);

$allowedCreationRoles = [
    'admin' => 'Administrator',
    'store-keeper' => 'Store Keeper',
    'social-service-officer' => 'Social Services Officer',
];
$roleLabels = [
    'admin' => ['Administrator', 'red'],
    'subject-officer' => ['Subject Officer', 'green'],
    'store-keeper' => ['Store Keeper', 'yellow'],
    'social-service-officer' => ['Social Services Officer', 'blue'],
];
$values = ['full_name'=>'','email'=>'','phone'=>'','role'=>'','district_id'=>'','ds_division_id'=>''];
$submittedAction = (string) ($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = t('Your session expired. Please refresh the page and try again.');
    } elseif ($submittedAction === 'create-user') {
        foreach (array_keys($values) as $field) $values[$field] = trim((string) ($_POST[$field] ?? ''));
        $values['email'] = strtolower($values['email']);
        $password = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if (mb_strlen($values['full_name']) < 2 || mb_strlen($values['full_name']) > 100) $errors[] = t('Enter a valid name between 2 and 100 characters.');
        if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || strlen($values['email']) > 120) $errors[] = t('Enter a valid email address.');
        if (strlen($values['phone']) > 25 || widmsSmsPhone($values['phone']) === null) $errors[] = t('Enter a valid Sri Lankan mobile number (07XXXXXXXX or +947XXXXXXXX).');
        if (!isset($allowedCreationRoles[$values['role']])) $errors[] = t('Select a valid role.');
        if (strlen($password) < 8 || strlen($password) > 255) $errors[] = t('Password must contain at least 8 characters.');
        if (!hash_equals($password, $confirmPassword)) $errors[] = t('Password and confirm password do not match.');

        $districtId = null;
        $dsDivisionId = null;
        if ($values['role'] === 'social-service-officer') {
            $districtId = filter_var($values['district_id'], FILTER_VALIDATE_INT) ?: null;
            $dsDivisionId = filter_var($values['ds_division_id'], FILTER_VALIDATE_INT) ?: null;
            if (!$districtId || !$dsDivisionId) $errors[] = t('Select a district and DS Division for the Social Service Officer.');
        }

        if ($errors === []) {
            try {
                $connection = database();
                $connection->beginTransaction();
                $duplicate = $connection->prepare('SELECT id FROM users WHERE username=:username OR email=:email LIMIT 1 FOR UPDATE');
                $duplicate->execute(['username'=>$values['email'], 'email'=>$values['email']]);
                if ($duplicate->fetchColumn()) throw new RuntimeException(t('An account already exists for this email address.'));

                $divisionName = null;
                if ($values['role'] === 'social-service-officer') {
                    // Lock the division to prevent concurrent active SSO assignments.
                    $divisionCheck = $connection->prepare("SELECT ds.name FROM ds_divisions ds JOIN districts d ON d.id=ds.district_id WHERE ds.id=:ds AND ds.district_id=:district AND ds.status='active' AND d.status='active' LIMIT 1 FOR UPDATE");
                    $divisionCheck->execute(['ds'=>$dsDivisionId,'district'=>$districtId]);
                    $divisionName = $divisionCheck->fetchColumn();
                    if (!$divisionName) throw new RuntimeException(t('The selected DS Division does not belong to the selected district.'));
                    $activeOfficer = $connection->prepare("SELECT id FROM users WHERE role='social-service-officer' AND status='active' AND ds_division_id=:division LIMIT 1 FOR UPDATE");
                    $activeOfficer->execute(['division'=>$dsDivisionId]);
                    if ($activeOfficer->fetchColumn()) throw new RuntimeException(t('This division already has an active Social Service Officer.'));
                }

                $insert = $connection->prepare("INSERT INTO users (full_name,username,email,phone,division,district_id,ds_division_id,password_hash,role,status) VALUES (:full_name,:username,:email,:phone,:division,:district_id,:ds_division_id,:password_hash,:role,'active')");
                $insert->execute([
                    'full_name'=>$values['full_name'],'username'=>$values['email'],'phone'=>$values['phone'],
                    'email'=>$values['email'],
                    'division'=>$divisionName,'district_id'=>$districtId,'ds_division_id'=>$dsDivisionId,
                    'password_hash'=>password_hash($password, PASSWORD_DEFAULT),'role'=>$values['role'],
                ]);
                $newUserId = (int) $connection->lastInsertId();
                $connection->commit();
                logActivity('Users', 'Created ' . $values['role'] . ' account', 'USR-' . $newUserId, 'created');
                $_SESSION['flash_success'] = t('User account created successfully.');
                unset($_SESSION['csrf_token']);
                header('Location: dashboard.php?page=users');
                exit;
            } catch (Throwable $exception) {
                if (isset($connection) && $connection->inTransaction()) $connection->rollBack();
                error_log($exception->getMessage());
                if ($exception instanceof RuntimeException) $errors[] = $exception->getMessage();
                elseif ($exception instanceof PDOException && (string) $exception->getCode() === '23000') $errors[] = t('An account already exists for this email address.');
                else $errors[] = t('Unable to create the user. Run the latest database migration and try again.');
            }
        }
    } elseif (in_array($submittedAction, ['suspend-user','reactivate-user'], true)) {
        $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        $reason = trim((string) ($_POST['suspension_reason'] ?? ''));
        if (!$userId) $errors[] = t('Select a valid user.');
        elseif ((int) $userId === (int) $_SESSION['user_id']) $errors[] = t('You cannot suspend your own account.');
        elseif ($submittedAction === 'suspend-user' && ($reason === '' || mb_strlen($reason) > 500)) $errors[] = $reason === '' ? t('A suspension reason is required.') : t('The suspension reason must not exceed 500 characters.');
        else {
            try {
                $connection = database();
                $connection->beginTransaction();
                $select = $connection->prepare('SELECT id,full_name,role,status,ds_division_id FROM users WHERE id=:id LIMIT 1 FOR UPDATE');
                $select->execute(['id'=>$userId]);
                $target = $select->fetch();
                if (!$target) throw new RuntimeException(t('User not found.'));
                if ($submittedAction === 'suspend-user') {
                    if ($target['status'] !== 'active') throw new RuntimeException(t('This user is already suspended.'));
                    $connection->prepare("UPDATE users SET status='inactive',deactivation_reason=:reason,deactivated_by=:admin,deactivated_at=NOW() WHERE id=:id AND status='active'")->execute(['reason'=>$reason,'admin'=>$_SESSION['user_id'],'id'=>$userId]);
                    $message = t('User suspended successfully.');
                    $activityStatus = 'suspended';
                } else {
                    if ($target['status'] !== 'inactive') throw new RuntimeException(t('This user is already active.'));
                    if ($target['role'] === 'social-service-officer' && !empty($target['ds_division_id'])) {
                        $divisionLock = $connection->prepare('SELECT id FROM ds_divisions WHERE id=:division LIMIT 1 FOR UPDATE');
                        $divisionLock->execute(['division'=>$target['ds_division_id']]);
                        $conflict = $connection->prepare("SELECT id FROM users WHERE role='social-service-officer' AND status='active' AND ds_division_id=:division AND id<>:id LIMIT 1 FOR UPDATE");
                        $conflict->execute(['division'=>$target['ds_division_id'],'id'=>$userId]);
                        if ($conflict->fetchColumn()) throw new RuntimeException(t('This division already has an active Social Service Officer.'));
                    }
                    $connection->prepare("UPDATE users SET status='active',deactivation_reason=NULL,deactivated_by=NULL,deactivated_at=NULL WHERE id=:id AND status='inactive'")->execute(['id'=>$userId]);
                    $message = t('User reactivated successfully.');
                    $activityStatus = 'reactivated';
                }
                $connection->commit();
                logActivity('Users', $message . ' ' . $target['full_name'], 'USR-' . $userId, $activityStatus);
                $_SESSION['flash_success'] = $message;
                unset($_SESSION['csrf_token']);
                header('Location: dashboard.php?page=users');
                exit;
            } catch (Throwable $exception) {
                if (isset($connection) && $connection->inTransaction()) $connection->rollBack();
                error_log($exception->getMessage());
                $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : t('Unable to update the user. Run the latest database migration and try again.');
            }
        }
    } else $errors[] = t('Invalid user management action.');
}

try {
    $connection = database();
    $users = $connection->query("SELECT u.id,u.full_name,u.username,u.phone,u.division,u.role,u.status,u.deactivation_reason,u.created_at,d.name district_name,ds.name ds_division_name FROM users u LEFT JOIN districts d ON d.id=u.district_id LEFT JOIN ds_divisions ds ON ds.id=u.ds_division_id ORDER BY FIELD(u.status,'active','inactive'),u.full_name,u.id")->fetchAll();
    $districts = $connection->query("SELECT id,name FROM districts WHERE status='active' ORDER BY name")->fetchAll();
    $dsDivisions = $connection->query("SELECT ds.id,ds.district_id,ds.name,ds.division_type,EXISTS(SELECT 1 FROM users u WHERE u.ds_division_id=ds.id AND u.role='social-service-officer' AND u.status='active') has_active_sso FROM ds_divisions ds JOIN districts d ON d.id=ds.district_id WHERE ds.status='active' AND d.status='active' ORDER BY d.name,ds.division_type,ds.name")->fetchAll();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $loadError = t('Unable to load users. Make sure MySQL is running and apply the latest database migration.');
}
$reopenCreateDialog = $submittedAction === 'create-user' && $errors !== [];
?>
<!doctype html>
<html lang="<?= htmlspecialchars(widmsLanguage(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(t('User Management'), ENT_QUOTES, 'UTF-8') ?> | WIDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css?v=67" rel="stylesheet">
</head>
<body class="widms-unified-ui">
<?php require __DIR__ . '/../../includes/admin-sidebar.php'; ?>
<div class="admin-shell">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3"><button type="button" class="menu-button" id="menu-button" aria-label="<?= htmlspecialchars(t('Open navigation'), ENT_QUOTES, 'UTF-8') ?>">☰</button><h1><?= htmlspecialchars(t('User Management'), ENT_QUOTES, 'UTF-8') ?></h1></div>
        <div class="topbar-actions"><label class="search-box"><span aria-hidden="true">🔍</span><input type="search" placeholder="<?= htmlspecialchars(t('Search anything...'), ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars(t('Search'), ENT_QUOTES, 'UTF-8') ?>"></label><button class="notification-button" type="button" aria-label="<?= htmlspecialchars(t('Notifications'), ENT_QUOTES, 'UTF-8') ?>">🔔</button></div>
    </header>
    <main class="dashboard-content users-page">
        <?php renderSuccessMessage($success); ?>
        <?php if ($errors !== []): ?><div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <?php if ($loadError !== ''): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($loadError, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <div class="users-toolbar"><button type="button" class="add-user-button" data-user-dialog-open>+ <?= htmlspecialchars(t('Add User'), ENT_QUOTES, 'UTF-8') ?></button></div>
        <?php if ($loadError === ''): ?>
        <section class="users-card" aria-label="<?= htmlspecialchars(t('System users'), ENT_QUOTES, 'UTF-8') ?>"><div class="users-table-wrap"><table class="users-table">
            <thead><tr><th><?= htmlspecialchars(t('Name'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Role'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Division'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Status'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Created'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Actions'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead>
            <tbody><?php if ($users === []): ?><tr><td colspan="6" class="text-center text-secondary py-4"><?= htmlspecialchars(t('No users available.'), ENT_QUOTES, 'UTF-8') ?></td></tr><?php else: foreach ($users as $user): ?>
                <?php $role=$roleLabels[$user['role']]??[ucwords(str_replace('-',' ',$user['role'])),'blue'];$isActive=$user['status']==='active';$divisionLabel=$user['ds_division_name']?:($user['division']?:'—'); ?>
                <tr>
                    <td><strong><?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?><?= $user['phone'] ? ' · ' . htmlspecialchars($user['phone'], ENT_QUOTES, 'UTF-8') : '' ?></small></td>
                    <td><span class="role-label <?= htmlspecialchars($role[1], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t($role[0]), ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td><?= htmlspecialchars($divisionLabel, ENT_QUOTES, 'UTF-8') ?><?php if ($user['district_name']): ?><small class="user-district-name"><?= htmlspecialchars($user['district_name'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?></td>
                    <td><span class="user-status <?= $isActive?'active':'inactive' ?>"><?= htmlspecialchars(t($isActive?'Active':'Suspended'), ENT_QUOTES, 'UTF-8') ?></span><?php if (!$isActive && $user['deactivation_reason']): ?><small class="user-suspension-reason"><?= htmlspecialchars($user['deactivation_reason'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?></td>
                    <td><?= date('d-m-Y', strtotime($user['created_at'])) ?></td>
                    <td class="user-actions"><form method="post" class="user-status-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                        <?php if ($isActive): ?><input type="hidden" name="suspension_reason" value=""><button type="button" class="suspend-user-button" data-reason-trigger data-submit-name="action" data-submit-value="suspend-user" data-reason-field="suspension_reason" data-dialog-title="<?= htmlspecialchars(t('Suspend user'), ENT_QUOTES, 'UTF-8') ?>" data-dialog-confirm="<?= htmlspecialchars(t('Confirm suspension'), ENT_QUOTES, 'UTF-8') ?>" data-reason-label="<?= htmlspecialchars(t('Reason for suspension'), ENT_QUOTES, 'UTF-8') ?>" data-reason-required="<?= htmlspecialchars(t('Enter a reason before suspending this user.'), ENT_QUOTES, 'UTF-8') ?>" data-cancel-label="<?= htmlspecialchars(t('Cancel'), ENT_QUOTES, 'UTF-8') ?>" <?= (int)$user['id']===(int)$_SESSION['user_id']?'disabled title="'.htmlspecialchars(t('You cannot suspend your own account'),ENT_QUOTES,'UTF-8').'"':'' ?>><?= htmlspecialchars(t('Suspend'), ENT_QUOTES, 'UTF-8') ?></button>
                        <?php else: ?><button type="submit" name="action" value="reactivate-user" class="reactivate-user-button"><?= htmlspecialchars(t('Reactivate'), ENT_QUOTES, 'UTF-8') ?></button><?php endif; ?>
                    </form></td>
                </tr>
            <?php endforeach; endif; ?></tbody>
        </table></div></section>
        <?php endif; ?>
    </main>
</div>

<dialog class="admin-user-dialog" id="admin-user-dialog" aria-labelledby="admin-user-dialog-title">
    <form method="post" class="admin-user-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="create-user">
        <div class="admin-user-dialog-heading"><div><span class="admin-user-dialog-icon" aria-hidden="true">+</span><div><h2 id="admin-user-dialog-title"><?= htmlspecialchars(t('Create User Account'), ENT_QUOTES, 'UTF-8') ?></h2><p><?= htmlspecialchars(t('The user can sign in immediately with the password you provide.'), ENT_QUOTES, 'UTF-8') ?></p></div></div><button type="button" class="admin-user-dialog-close" data-user-dialog-close aria-label="<?= htmlspecialchars(t('Close'), ENT_QUOTES, 'UTF-8') ?>">&times;</button></div>
        <?php if ($reopenCreateDialog): ?><div class="alert alert-danger admin-user-dialog-errors" role="alert"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <div class="admin-user-form-grid">
            <label class="full-width"><span><?= htmlspecialchars(t('Full name'), ENT_QUOTES, 'UTF-8') ?></span><input name="full_name" maxlength="100" value="<?= htmlspecialchars($values['full_name'], ENT_QUOTES, 'UTF-8') ?>" autocomplete="name" required></label>
            <label><span><?= htmlspecialchars(t('Email address'), ENT_QUOTES, 'UTF-8') ?></span><input type="email" name="email" maxlength="120" value="<?= htmlspecialchars($values['email'], ENT_QUOTES, 'UTF-8') ?>" autocomplete="off" required></label>
            <label><span><?= htmlspecialchars(t('Phone Number'), ENT_QUOTES, 'UTF-8') ?></span><input type="tel" name="phone" maxlength="25" value="<?= htmlspecialchars($values['phone'], ENT_QUOTES, 'UTF-8') ?>" autocomplete="off" required></label>
            <label class="full-width"><span><?= htmlspecialchars(t('Role'), ENT_QUOTES, 'UTF-8') ?></span><select name="role" id="admin-create-user-role" required><option value=""><?= htmlspecialchars(t('Select a role'), ENT_QUOTES, 'UTF-8') ?></option><?php foreach($allowedCreationRoles as $roleKey=>$roleLabel): ?><option value="<?= htmlspecialchars($roleKey, ENT_QUOTES, 'UTF-8') ?>" <?= $values['role']===$roleKey?'selected':'' ?>><?= htmlspecialchars(t($roleLabel), ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label>
            <div class="admin-user-sso-fields full-width" id="admin-user-sso-fields" hidden>
                <label><span><?= htmlspecialchars(t('District'), ENT_QUOTES, 'UTF-8') ?></span><select name="district_id" id="admin-create-user-district"><option value=""><?= htmlspecialchars(t('Select a district'), ENT_QUOTES, 'UTF-8') ?></option><?php foreach($districts as $district): ?><option value="<?= (int)$district['id'] ?>" <?= (string)$values['district_id']===(string)$district['id']?'selected':'' ?>><?= htmlspecialchars($district['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label>
                <label><span><?= htmlspecialchars(t('DS Division'), ENT_QUOTES, 'UTF-8') ?></span><select name="ds_division_id" id="admin-create-user-division"><option value=""><?= htmlspecialchars(t('Select a DS Division'), ENT_QUOTES, 'UTF-8') ?></option><?php foreach($dsDivisions as $division): ?><option value="<?= (int)$division['id'] ?>" data-district-id="<?= (int)$division['district_id'] ?>" data-occupied="<?= (int)$division['has_active_sso'] ?>" <?= (int)$division['has_active_sso']===1?'disabled':'' ?> <?= (string)$values['ds_division_id']===(string)$division['id']?'selected':'' ?>><?= htmlspecialchars($division['name'].((int)$division['has_active_sso']===1?' — '.t('Active SSO assigned'):''),ENT_QUOTES,'UTF-8') ?></option><?php endforeach; ?></select></label>
            </div>
            <label><span><?= htmlspecialchars(t('Password'), ENT_QUOTES, 'UTF-8') ?></span><input type="password" name="password" minlength="8" maxlength="255" autocomplete="new-password" required><small><?= htmlspecialchars(t('At least 8 characters'), ENT_QUOTES, 'UTF-8') ?></small></label>
            <label><span><?= htmlspecialchars(t('Confirm password'), ENT_QUOTES, 'UTF-8') ?></span><input type="password" name="confirm_password" minlength="8" maxlength="255" autocomplete="new-password" required></label>
        </div>
        <div class="admin-user-form-actions"><button type="button" class="outline-action" data-user-dialog-close><?= htmlspecialchars(t('Cancel'), ENT_QUOTES, 'UTF-8') ?></button><button type="submit" class="admin-primary-action"><?= htmlspecialchars(t('Create User'), ENT_QUOTES, 'UTF-8') ?></button></div>
    </form>
</dialog>
<script src="assets/js/admin-dashboard.js?v=22"></script><script src="assets/js/admin-reason-dialog.js?v=2"></script>
<script>
(() => {
    const dialog=document.getElementById('admin-user-dialog'),role=document.getElementById('admin-create-user-role'),district=document.getElementById('admin-create-user-district'),division=document.getElementById('admin-create-user-division'),ssoFields=document.getElementById('admin-user-sso-fields');
    const filterDivisions=()=>{const districtId=district.value;let selectedIsAvailable=division.value==='';Array.from(division.options).forEach((option,index)=>{if(index===0)return;const matches=districtId!==''&&option.dataset.districtId===districtId;option.hidden=!matches;option.disabled=!matches||option.dataset.occupied==='1';if(option.selected&&!option.disabled)selectedIsAvailable=true;});if(!selectedIsAvailable)division.value='';};
    const syncSsoFields=()=>{const isSso=role.value==='social-service-officer';ssoFields.hidden=!isSso;district.required=isSso;division.required=isSso;if(isSso)filterDivisions();};
    document.querySelector('[data-user-dialog-open]')?.addEventListener('click',()=>{syncSsoFields();dialog.showModal();});
    document.querySelectorAll('[data-user-dialog-close]').forEach(button=>button.addEventListener('click',()=>dialog.close()));
    dialog.addEventListener('click',event=>{if(event.target===dialog)dialog.close();});
    role.addEventListener('change',syncSsoFields);district.addEventListener('change',()=>{division.value='';filterDivisions();});syncSsoFields();
    <?php if($reopenCreateDialog): ?>dialog.showModal();<?php endif; ?>
})();
</script>
</body></html>
