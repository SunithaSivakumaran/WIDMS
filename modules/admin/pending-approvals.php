<?php
declare(strict_types=1);

requireRole('admin');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/activity.php';

$activePage = 'pending-approvals';
$notice = '';
$noticeType = 'success';
$loadError = '';
$registrations = [];
$roleLabels = [
    'subject-officer' => ['Subject Officer', 'green'],
    'store-keeper' => ['Store Keeper', 'yellow'],
    'social-service-officer' => ['Social Service Officer', 'blue'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
    $decision = (string) ($_POST['decision'] ?? '');

    if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $notice = 'Your session expired. Refresh the page and try again.';
        $noticeType = 'danger';
    } elseif (!$requestId || !in_array($decision, ['approved', 'rejected'], true)) {
        $notice = 'Invalid approval request.';
        $noticeType = 'danger';
    } else {
        try {
            $connection = database();
            $connection->beginTransaction();
            $select = $connection->prepare("SELECT * FROM registration_requests WHERE id = :id AND status = 'pending' FOR UPDATE");
            $select->execute(['id' => $requestId]);
            $request = $select->fetch();

            if (!$request) {
                throw new RuntimeException('This request has already been processed or does not exist.');
            }

            if ($decision === 'approved') {
                $existing = $connection->prepare('SELECT id FROM users WHERE username = :email LIMIT 1');
                $existing->execute(['email' => $request['email']]);
                if ($existing->fetch()) {
                    throw new RuntimeException('An account already exists for this email address.');
                }

                $createUser = $connection->prepare(
                    'INSERT INTO users (full_name, username, phone, division, password_hash, role, status)
                     VALUES (:full_name, :email, :phone, :division, :password_hash, :role, :status)'
                );
                $createUser->execute([
                    'full_name' => $request['full_name'],
                    'email' => $request['email'],
                    'phone' => $request['phone'],
                    'division' => $request['division'],
                    'password_hash' => $request['password_hash'],
                    'role' => $request['role'],
                    'status' => 'active',
                ]);
            }

            $update = $connection->prepare(
                'UPDATE registration_requests SET status = :status, reviewed_by = :reviewed_by, reviewed_at = NOW() WHERE id = :id'
            );
            $update->execute(['status' => $decision, 'reviewed_by' => $_SESSION['user_id'], 'id' => $requestId]);
            $connection->commit();
            logActivity('Users', ucfirst($decision) . ' user registration request', 'REG-' . str_pad((string)$requestId,3,'0',STR_PAD_LEFT), $decision);

            $emailSent = sendRegistrationDecisionEmail($request['email'], $request['full_name'], $decision);
            $emailUpdate = $connection->prepare('UPDATE registration_requests SET email_status = :email_status WHERE id = :id');
            $emailUpdate->execute(['email_status' => $emailSent ? 'sent' : 'failed', 'id' => $requestId]);

            $notice = sprintf(
                'Request %s. %s',
                $decision,
                $emailSent ? 'The applicant was notified by email.' : 'The decision was saved, but email delivery failed. Configure email on the server.'
            );
            $noticeType = $emailSent ? 'success' : 'warning';
        } catch (Throwable $exception) {
            if (isset($connection) && $connection->inTransaction()) {
                $connection->rollBack();
            }
            error_log($exception->getMessage());
            $notice = $exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to process the request. Check the database migration.';
            $noticeType = 'danger';
        }
    }
}

try {
    $registrations = database()->query(
        "SELECT id, full_name, email, phone, role, division, created_at
         FROM registration_requests WHERE status = 'pending' ORDER BY created_at ASC"
    )->fetchAll();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $loadError = 'Registration requests are unavailable. Import database/migration_registration_requests.sql first.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pending Approvals | WIDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/../../includes/admin-sidebar.php'; ?>
<div class="admin-shell">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3"><button type="button" class="menu-button" id="menu-button" aria-label="Open navigation">☰</button><h1>Pending Approvals</h1></div>
        <div class="topbar-actions"><label class="search-box"><span aria-hidden="true">⌕</span><input type="search" placeholder="Search anything..." aria-label="Search"></label><button class="notification-button" type="button" aria-label="Notifications">●</button></div>
    </header>
    <main class="dashboard-content approvals-page">
        <?php if ($notice !== ''): ?><div class="alert alert-<?= $noticeType ?>" role="status"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if ($loadError !== ''): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($loadError, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <div class="approval-tabs" role="tablist" aria-label="Approval categories">
            <button class="approval-tab active" type="button" role="tab" data-tab="registrations" aria-selected="true">User Registrations <span class="tab-count red"><?= count($registrations) ?></span></button>
            <button class="approval-tab" type="button" role="tab" data-tab="items" aria-selected="false">Item Requests <span class="tab-count yellow">0</span></button>
            <button class="approval-tab" type="button" role="tab" data-tab="stock" aria-selected="false">Stock Release</button>
        </div>
        <section class="approval-tab-panel active" data-panel="registrations">
            <?php if ($registrations !== []): ?><div class="approval-alert">⚠ <?= count($registrations) ?> user registration request<?= count($registrations) === 1 ? '' : 's' ?> require your review.</div><?php endif; ?>
            <article class="approval-card <?= $registrations === [] ? 'mt-4' : '' ?>">
                <h2>User Registration Requests</h2>
                <?php if ($registrations === [] && $loadError === ''): ?>
                    <p class="p-4 mb-0 text-secondary">There are no pending registration requests.</p>
                <?php elseif ($registrations !== []): ?>
                <div class="approval-table-wrap"><table class="approval-table registration-table">
                    <thead><tr><th>Applicant</th><th>Role</th><th>Contact</th><th>Division</th><th>Submitted</th><th>Action</th></tr></thead>
                    <tbody><?php foreach ($registrations as $registration): $role = $roleLabels[$registration['role']] ?? [$registration['role'], 'blue']; ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($registration['full_name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                        <td><span class="role-label <?= $role[1] ?>"><?= htmlspecialchars($role[0], ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td><?= htmlspecialchars($registration['email'], ENT_QUOTES, 'UTF-8') ?><br><small><?= htmlspecialchars($registration['phone'], ENT_QUOTES, 'UTF-8') ?></small></td>
                        <td><?= htmlspecialchars($registration['division'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= date('d M Y H:i', strtotime($registration['created_at'])) ?></td>
                        <td class="approval-actions">
                            <form method="post" action="dashboard.php?page=pending-approvals" class="d-flex gap-2" onsubmit="return confirm('Are you sure you want to ' + event.submitter.value + ' this request?');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="request_id" value="<?= (int) $registration['id'] ?>">
                                <button name="decision" value="approved" class="approve-button" type="submit">✓ Approve</button>
                                <button name="decision" value="rejected" class="reject-button" type="submit">✕ Reject</button>
                            </form>
                        </td>
                    </tr><?php endforeach; ?></tbody>
                </table></div>
                <?php endif; ?>
            </article>
        </section>
        <section class="approval-tab-panel" data-panel="items"><article class="approval-card empty-approval-card"><h2>Item Requests</h2><p>Item request approval records will be connected when the Item Requests module is developed.</p></article></section>
        <section class="approval-tab-panel" data-panel="stock"><article class="approval-card empty-approval-card"><h2>Stock Release Requests</h2><p>Stock release approval records will be connected when the Central Stock module is developed.</p></article></section>
    </main>
</div>
<script src="assets/js/admin-dashboard.js"></script>
<script src="assets/js/pending-approvals.js"></script>
</body>
</html>
