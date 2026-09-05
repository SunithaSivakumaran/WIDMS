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
$pendingItemRequests = 0;
$pendingStockReleases = 0;
$roleLabels = [
    'subject-officer' => ['Subject Officer', 'green'],
    'store-keeper' => ['Store Keeper', 'yellow'],
    'social-service-officer' => ['Social Service Officer', 'blue'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
    $decision = (string) ($_POST['decision'] ?? '');
    $decisionReason = trim((string) ($_POST['decision_reason'] ?? ''));

    if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $notice = 'Your session expired. Refresh the page and try again.';
        $noticeType = 'danger';
    } elseif (!$requestId || !in_array($decision, ['approved', 'rejected'], true)) {
        $notice = 'Invalid approval request.';
        $noticeType = 'danger';
    } elseif ($decision === 'rejected' && $decisionReason === '') {
        $notice = t('A rejection reason is required.');
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

                if ($request['role'] === 'social-service-officer') {
                    if (empty($request['district_id']) || empty($request['ds_division_id'])) {
                        throw new RuntimeException('This SSO request has no valid district and DS Division. Ask the applicant to submit a new request.');
                    }

                    $activeOfficer = $connection->prepare(
                        "SELECT full_name
                         FROM users
                         WHERE role='social-service-officer'
                           AND status='active'
                           AND ds_division_id=:ds_division_id
                         LIMIT 1 FOR UPDATE"
                    );
                    $activeOfficer->execute(['ds_division_id' => $request['ds_division_id']]);
                    $activeOfficerName = $activeOfficer->fetchColumn();

                    if ($activeOfficerName) {
                        throw new RuntimeException(
                            $activeOfficerName . ' is already the active Social Service Officer for ' .
                            $request['division'] . '. This request cannot be approved.'
                        );
                    }
                }

                $createUser = $connection->prepare(
                    'INSERT INTO users (full_name, username, phone, division, district_id, ds_division_id, password_hash, role, status)
                     VALUES (:full_name, :email, :phone, :division, :district_id, :ds_division_id, :password_hash, :role, :status)'
                );
                $createUser->execute([
                    'full_name' => $request['full_name'],
                    'email' => $request['email'],
                    'phone' => $request['phone'],
                    'division' => $request['division'],
                    'district_id' => $request['district_id'],
                    'ds_division_id' => $request['ds_division_id'],
                    'password_hash' => $request['password_hash'],
                    'role' => $request['role'],
                    'status' => 'active',
                ]);
            }

            $update = $connection->prepare(
                'UPDATE registration_requests SET status=:status,rejection_reason=:reason,reviewed_by=:reviewed_by,reviewed_at=NOW() WHERE id=:id'
            );
            $update->execute(['status'=>$decision,'reason'=>$decision==='rejected'?$decisionReason:null,'reviewed_by'=>$_SESSION['user_id'],'id'=>$requestId]);
            $connection->commit();
            logActivity('Users', ucfirst($decision) . ' user registration request', 'REG-' . str_pad((string)$requestId,3,'0',STR_PAD_LEFT), $decision);

            // Rejected applicants need the administrator's reason in their notification.
            $emailSent = sendRegistrationDecisionEmail($request['email'], $request['full_name'], $decision, $decisionReason);
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
            $noticeType = 'danger';
        }
    }
}

try {
    $connection = database();
    $registrations = $connection->query(
        "SELECT rr.id,rr.full_name,rr.email,rr.phone,rr.role,rr.division,rr.created_at,
                d.name district_name,ds.name ds_division_name
         FROM registration_requests rr
         LEFT JOIN districts d ON d.id=rr.district_id
         LEFT JOIN ds_divisions ds ON ds.id=rr.ds_division_id
         WHERE rr.status='pending' ORDER BY rr.created_at ASC"
    )->fetchAll();

    // Show live approval totals so these controls reflect their actual workflow queues.
    $pendingItemRequests = (int) $connection
        ->query("SELECT COUNT(*) FROM aid_requests WHERE status='pending'")
        ->fetchColumn();
    $pendingStockReleases = (int) $connection
        ->query("SELECT COUNT(*) FROM goods_requests WHERE status='pending-admin-approval'")
        ->fetchColumn();
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
            <!-- These queues already have complete pages, so navigation is clearer than empty placeholder panels. -->
            <a class="approval-tab" href="dashboard.php?page=item-requests">Item Requests <span class="tab-count yellow"><?= $pendingItemRequests ?></span></a>
            <a class="approval-tab" href="dashboard.php?page=goods-requests">Stock Release <span class="tab-count yellow"><?= $pendingStockReleases ?></span></a>
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
                        <!-- Admin needs the assigned DS Division here; district remains stored for validation and reporting. -->
                        <td><?= htmlspecialchars(
                            $registration['ds_division_name'] ?: ($registration['division'] ?: '—'),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?></td>
                        <td><?= date('d M Y H:i', strtotime($registration['created_at'])) ?></td>
                        <td class="approval-actions">
                            <form method="post" action="dashboard.php?page=pending-approvals" class="registration-decision-form">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="request_id" value="<?= (int) $registration['id'] ?>">
                                <!-- Rejection reason is collected in a dialog only when Reject is selected. -->
                                <input type="hidden" name="decision_reason" value="">
                                <div class="decision-button-row"><button name="decision" value="approved" class="approve-button" type="submit">✓ <?= htmlspecialchars(t('Approve'), ENT_QUOTES, 'UTF-8') ?></button><button class="reject-button" type="button" data-reason-trigger data-submit-name="decision" data-submit-value="rejected" data-dialog-title="<?= htmlspecialchars(t('Reject registration request'), ENT_QUOTES, 'UTF-8') ?>" data-dialog-confirm="<?= htmlspecialchars(t('Confirm rejection'), ENT_QUOTES, 'UTF-8') ?>" data-reason-field="decision_reason">✕ <?= htmlspecialchars(t('Reject'), ENT_QUOTES, 'UTF-8') ?></button></div>
                            </form>
                        </td>
                    </tr><?php endforeach; ?></tbody>
                </table></div>
                <?php endif; ?>
            </article>
        </section>
    </main>
</div>
<script src="assets/js/admin-dashboard.js"></script>
<script src="assets/js/pending-approvals.js"></script>
<script src="assets/js/admin-reason-dialog.js?v=1"></script>
</body>
</html>
