<?php
declare(strict_types=1);

requireRole('admin');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/activity.php';
require_once __DIR__ . '/../../includes/admin-approval-tabs.php';

$activePage = 'pending-approvals';
$notice = '';
$noticeType = 'success';
$loadError = '';
$registrations = [];
$pendingItemRequests = 0;
$pendingStockReleases = 0;
$pendingCorrectionRequests = 0;
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
    $pendingCorrectionRequests = (int) $connection
        ->query("SELECT COUNT(*) FROM correction_requests WHERE status='pending'")
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
    <main class="dashboard-content approvals-page admin-correction-review-page registration-review-page">
        <?php if ($notice !== ''): ?><div class="alert alert-<?= $noticeType ?>" role="status"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if ($loadError !== ''): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($loadError, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php renderAdminApprovalTabs([
            'registrations' => count($registrations),
            'aid' => $pendingItemRequests,
            'stock' => $pendingStockReleases,
            'corrections' => $pendingCorrectionRequests,
        ], 'registrations'); ?>
        <section class="approval-tab-panel active" data-panel="registrations">
            <div class="admin-data-card admin-correction-review-card">
                <div class="admin-correction-list">
                <?php if ($registrations === [] && $loadError === ''): ?>
                    <div class="empty-corrections"><strong><?= htmlspecialchars(t('No pending user registration requests'), ENT_QUOTES, 'UTF-8') ?></strong><span><?= htmlspecialchars(t('New registration requests will appear here for approval.'), ENT_QUOTES, 'UTF-8') ?></span></div>
                <?php elseif ($registrations !== []): ?>
                    <?php foreach ($registrations as $registration): $role = $roleLabels[$registration['role']] ?? [$registration['role'], 'blue']; ?>
                        <article id="registration-request-<?= (int) $registration['id'] ?>" class="admin-correction-item admin-notification-target" tabindex="-1">
                            <div class="correction-summary">
                                <div>
                                    <div class="correction-reference-line">
                                        <strong>REG-<?= str_pad((string) $registration['id'], 3, '0', STR_PAD_LEFT) ?></strong>
                                        <span><?= htmlspecialchars($registration['full_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="role-label <?= htmlspecialchars($role[1], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t($role[0]), ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <p class="correction-submission-meta"><?= htmlspecialchars(t('Submitted on'), ENT_QUOTES, 'UTF-8') ?> <?= date('d M Y, H:i', strtotime($registration['created_at'])) ?></p>
                                </div>
                                <span class="correction-status pending"><?= htmlspecialchars(t('Pending'), ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <dl class="correction-details registration-card-details">
                                <div><dt><?= htmlspecialchars(t('Email Address'), ENT_QUOTES, 'UTF-8') ?></dt><dd><?= htmlspecialchars($registration['email'], ENT_QUOTES, 'UTF-8') ?></dd></div>
                                <div><dt><?= htmlspecialchars(t('Phone Number'), ENT_QUOTES, 'UTF-8') ?></dt><dd><?= htmlspecialchars($registration['phone'], ENT_QUOTES, 'UTF-8') ?></dd></div>
                                <div><dt><?= htmlspecialchars(t('District'), ENT_QUOTES, 'UTF-8') ?></dt><dd><?= htmlspecialchars($registration['district_name'] ?: '—', ENT_QUOTES, 'UTF-8') ?></dd></div>
                                <div><dt><?= htmlspecialchars(t('DS Division'), ENT_QUOTES, 'UTF-8') ?></dt><dd><?= htmlspecialchars($registration['ds_division_name'] ?: ($registration['division'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></dd></div>
                            </dl>
                            <form method="post" action="dashboard.php?page=pending-approvals" class="admin-decision-form registration-decision-form">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="request_id" value="<?= (int) $registration['id'] ?>">
                                <label><?= htmlspecialchars(t('Admin note'), ENT_QUOTES, 'UTF-8') ?><textarea name="decision_reason" rows="2" placeholder="<?= htmlspecialchars(t('Required when rejecting the request'), ENT_QUOTES, 'UTF-8') ?>"></textarea></label>
                                <div class="correction-decision-footer registration-decision-footer">
                                    <small><?= htmlspecialchars(t('Approving creates an active account with the requested role and assigned division.'), ENT_QUOTES, 'UTF-8') ?></small>
                                    <div class="correction-decision-actions">
                                        <button name="decision" value="approved" class="approve-button" type="submit">✓ <?= htmlspecialchars(t('Approve'), ENT_QUOTES, 'UTF-8') ?></button>
                                        <button name="decision" value="rejected" class="reject-button" type="submit">✕ <?= htmlspecialchars(t('Reject'), ENT_QUOTES, 'UTF-8') ?></button>
                                    </div>
                                </div>
                            </form>
                    </article>
                    <?php endforeach; ?>
                <?php endif; ?>
                </div>
            </div>
        </section>
    </main>
</div>
<script src="assets/js/admin-dashboard.js"></script>
<script src="assets/js/pending-approvals.js"></script>
</body>
</html>
