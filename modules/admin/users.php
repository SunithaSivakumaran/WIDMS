<?php
declare(strict_types=1);

requireRole('admin');
require_once __DIR__ . '/../../config/database.php';

$activePage = 'users';
$users = [];
$loadError = '';

try {
    $users = database()->query(
        'SELECT id, full_name, username, phone, division, role, status, created_at
         FROM users
         ORDER BY id ASC'
    )->fetchAll();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $loadError = 'Unable to load users. Make sure MySQL is running.';
}

$roleLabels = [
    'admin' => ['Admin', 'red'],
    'subject-officer' => ['Subject Officer', 'green'],
    'store-keeper' => ['Store Keeper', 'yellow'],
    'social-service-officer' => ['Social Service Officer', 'blue'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User Management | WIDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css" rel="stylesheet">
</head>
<body>
    <?php require __DIR__ . '/../../includes/admin-sidebar.php'; ?>

    <div class="admin-shell">
        <header class="topbar">
            <div class="d-flex align-items-center gap-3">
                <button type="button" class="menu-button" id="menu-button" aria-label="Open navigation">☰</button>
                <h1>User Management</h1>
            </div>
            <div class="topbar-actions">
                <label class="search-box">
                    <span aria-hidden="true">🔍</span>
                    <input type="search" placeholder="Search anything..." aria-label="Search">
                </label>
                <button class="notification-button" type="button" aria-label="Notifications">🔔</button>
            </div>
        </header>

        <main class="dashboard-content users-page">
            <div class="users-toolbar">
                <button type="button" class="add-user-button">+ Add User</button>
            </div>

            <?php if ($loadError !== ''): ?>
                <div class="alert alert-danger" role="alert"><?= htmlspecialchars($loadError, ENT_QUOTES, 'UTF-8') ?></div>
            <?php else: ?>
                <section class="users-card" aria-label="System users">
                    <div class="users-table-wrap">
                        <table class="users-table">
                            <thead>
                                <tr><th>Name</th><th>Role</th><th>Division</th><th>Status</th><th>Created</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php if ($users === []): ?>
                                    <tr><td colspan="6" class="text-center text-secondary py-4">No users available.</td></tr>
                                <?php else: foreach ($users as $user): ?>
                                    <?php $role = $roleLabels[$user['role']] ?? [ucwords(str_replace('-', ' ', $user['role'])), 'blue']; ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                            <small><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></small>
                                        </td>
                                        <td><span class="role-label <?= $role[1] ?>"><?= htmlspecialchars($role[0], ENT_QUOTES, 'UTF-8') ?></span></td>
                                        <td><?= htmlspecialchars($user['division'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><span class="user-status <?= $user['status'] === 'active' ? 'active' : 'inactive' ?>"><?= ucfirst(htmlspecialchars($user['status'], ENT_QUOTES, 'UTF-8')) ?></span></td>
                                        <td><?= date('d M Y', strtotime($user['created_at'])) ?></td>
                                        <td class="user-actions">
                                            <button type="button" class="edit-user-button">Edit</button>
                                            <button type="button" class="suspend-user-button" <?= (int) $user['id'] === (int) $_SESSION['user_id'] ? 'disabled title="You cannot suspend your own account"' : '' ?>>Suspend</button>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>
        </main>
    </div>

    <script src="assets/js/admin-dashboard.js"></script>
</body>
</html>
