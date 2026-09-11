<?php
declare(strict_types=1);

if ((string) ($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); exit('You do not have permission to access this page.'); }
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/activity.php';
require_once __DIR__ . '/../../includes/correction-application.php';
require_once __DIR__ . '/../../includes/admin-approval-tabs.php';

$activePage = 'correction-requests';
$notice = '';
$noticeType = 'success';
$requests = [];
$errorTypes = ['wrong-unit-cost' => 'Wrong unit cost', 'wrong-quantity' => 'Wrong quantity', 'wrong-bill-number' => 'Wrong bill / invoice number', 'wrong-supplier' => 'Wrong supplier', 'wrong-date' => 'Wrong date received', 'wrong-cost' => 'Wrong cost', 'wrong-item' => 'Wrong item', 'wrong-payment-amount' => 'Wrong payment amount', 'wrong-check-number' => 'Wrong check number', 'wrong-payment-date' => 'Wrong payment date', 'other' => 'Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
    $decision = (string) ($_POST['decision'] ?? '');
    $adminReason = trim((string) ($_POST['admin_reason'] ?? ''));

    if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $notice = 'Your session expired. Refresh the page and try again.'; $noticeType = 'danger';
    } elseif (!$requestId || !in_array($decision, ['approved', 'rejected'], true)) {
        $notice = 'Invalid correction request.'; $noticeType = 'danger';
    } elseif ($decision === 'rejected' && $adminReason === '') {
        $notice = 'A rejection reason is required so the Store Keeper knows why it was rejected.'; $noticeType = 'danger';
    } else {
        try {
            $database = database();
            $database->beginTransaction();
            $requestStatement = $database->prepare("SELECT * FROM correction_requests WHERE id = ? AND status = 'pending' FOR UPDATE");
            $requestStatement->execute([$requestId]);
            $correctionRequest = $requestStatement->fetch();
            if (!$correctionRequest) throw new RuntimeException('This request was already reviewed or does not exist.');

            if ($decision === 'approved') applyApprovedCorrection($database, $correctionRequest);

            $statement = $database->prepare(
                "UPDATE correction_requests SET status = :status, admin_reason = :admin_reason, reviewed_by = :reviewed_by, reviewed_at = NOW()
                 WHERE id = :id AND status = 'pending'"
            );
            $statement->execute([
                'status' => $decision, 'admin_reason' => $adminReason !== '' ? $adminReason : null,
                'reviewed_by' => $_SESSION['user_id'], 'id' => $requestId,
            ]);
            if ($statement->rowCount() !== 1) throw new RuntimeException('This request was already reviewed or does not exist.');
            $database->commit();
            logActivity('Corrections', ucfirst($decision) . ' correction request', 'CR-' . str_pad((string)$requestId,3,'0',STR_PAD_LEFT), $decision);
            if ($decision === 'approved') {
                logActivity(
                    'Inventory Corrections',
                    'Applied ' . ($errorTypes[$correctionRequest['error_type']] ?? $correctionRequest['error_type']) . ' from CR-' . str_pad((string) $requestId, 3, '0', STR_PAD_LEFT),
                    (string) $correctionRequest['record_reference'],
                    'done'
                );
            }
            $notice = $decision === 'approved' ? 'Correction request approved. The record was updated automatically.' : 'Correction request rejected. The reason is now visible to the Store Keeper.';
        } catch (Throwable $exception) {
            if (isset($database) && $database->inTransaction()) $database->rollBack();
            error_log($exception->getMessage());
            if ($exception instanceof RuntimeException) {
                $notice = $exception->getMessage();
            } elseif ($exception instanceof PDOException && $exception->getCode() === '23000') {
                $notice = 'The proposed bill or check number is already in use.';
            } else {
                $notice = 'Unable to apply the correction request.';
            }
            $noticeType = 'danger';
        }
    }
}

try {
    $requests = database()->query(
        "SELECT c.*, u.full_name AS submitted_name
         FROM correction_requests c JOIN users u ON u.id = c.submitted_by
         WHERE c.status = 'pending'
         ORDER BY c.id DESC"
    )->fetchAll();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $notice = 'Correction requests are unavailable. Import database/migration_correction_requests.sql.'; $noticeType = 'danger';
}

$requestCounts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
foreach ($requests as $request) {
    $status = (string) ($request['status'] ?? '');
    if (isset($requestCounts[$status])) {
        $requestCounts[$status]++;
    }
}
$approvalQueueCounts = ['registrations' => 0, 'aid' => 0, 'stock' => 0, 'corrections' => $requestCounts['pending']];
try {
    $database = database();
    $approvalQueueCounts['registrations'] = (int) $database->query("SELECT COUNT(*) FROM registration_requests WHERE status='pending'")->fetchColumn();
    $approvalQueueCounts['aid'] = (int) $database->query("SELECT COUNT(*) FROM aid_requests WHERE status='pending'")->fetchColumn();
    $approvalQueueCounts['stock'] = (int) $database->query("SELECT COUNT(*) FROM goods_requests WHERE status='pending-admin-approval'")->fetchColumn();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Correction Requests | WIDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css?v=58" rel="stylesheet">
</head>
<body class="admin-correction-page">
<?php require __DIR__ . '/../../includes/admin-sidebar.php'; ?>
<div class="admin-shell">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button type="button" class="menu-button" id="menu-button" aria-label="Open navigation">&#9776;</button>
            <h1>Correction Requests</h1>
        </div>
    </header>

    <main class="dashboard-content correction-page admin-correction-review-page">
        <?php if ($notice !== ''): ?>
            <div class="alert alert-<?= htmlspecialchars($noticeType, ENT_QUOTES, 'UTF-8') ?>" role="<?= $noticeType === 'danger' ? 'alert' : 'status' ?>">
                <?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php renderAdminApprovalTabs($approvalQueueCounts, 'corrections'); ?>

        <section class="admin-data-card admin-correction-review-card" aria-label="<?= htmlspecialchars(t('Pending correction requests'), ENT_QUOTES, 'UTF-8') ?>">
            <div class="admin-correction-list">
                <?php if ($requests === []): ?>
                    <div class="empty-corrections"><strong><?= htmlspecialchars(t('No pending correction requests'), ENT_QUOTES, 'UTF-8') ?></strong><span><?= htmlspecialchars(t('New requests will appear here for approval.'), ENT_QUOTES, 'UTF-8') ?></span></div>
                <?php else: foreach ($requests as $request): ?>
                    <?php $referenceType = t(str_starts_with((string) $request['record_reference'], 'PAY-') ? 'Payment record' : 'Inventory batch'); ?>
                    <article id="correction-request-<?= (int) $request['id'] ?>" class="admin-correction-item admin-notification-target" tabindex="-1">
                        <div class="correction-summary">
                            <div>
                                <div class="correction-reference-line">
                                    <strong>CR-<?= str_pad((string) $request['id'], 3, '0', STR_PAD_LEFT) ?></strong>
                                    <span><?= htmlspecialchars($request['record_reference'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <small><?= htmlspecialchars($referenceType, ENT_QUOTES, 'UTF-8') ?></small>
                                </div>
                                <p class="correction-submission-meta"><?= htmlspecialchars(t('Submitted by'), ENT_QUOTES, 'UTF-8') ?> <strong><?= htmlspecialchars($request['submitted_name'], ENT_QUOTES, 'UTF-8') ?></strong> · <?= date('d M Y, H:i', strtotime($request['created_at'])) ?></p>
                            </div>
                            <span class="correction-status pending"><?= htmlspecialchars(t('Pending'), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <dl class="correction-details">
                            <div class="correction-detail-type"><dt><?= htmlspecialchars(t('Field'), ENT_QUOTES, 'UTF-8') ?></dt><dd><?= htmlspecialchars(t($errorTypes[$request['error_type']] ?? $request['error_type']), ENT_QUOTES, 'UTF-8') ?></dd></div>
                            <div class="correction-detail-current"><dt><?= htmlspecialchars(t('Recorded Value'), ENT_QUOTES, 'UTF-8') ?></dt><dd><?= nl2br(htmlspecialchars($request['current_value'], ENT_QUOTES, 'UTF-8')) ?></dd></div>
                            <div class="correction-detail-proposed"><dt><?= htmlspecialchars(t('Proposed Value'), ENT_QUOTES, 'UTF-8') ?></dt><dd><?= nl2br(htmlspecialchars($request['proposed_correction'], ENT_QUOTES, 'UTF-8')) ?></dd></div>
                            <div class="correction-detail-reason"><dt><?= htmlspecialchars(t('Reason for Correction'), ENT_QUOTES, 'UTF-8') ?></dt><dd><?= nl2br(htmlspecialchars($request['request_reason'], ENT_QUOTES, 'UTF-8')) ?></dd></div>
                        </dl>
                        <form method="post" action="dashboard.php?page=correction-requests" class="admin-decision-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                            <label><?= htmlspecialchars(t('Admin note'), ENT_QUOTES, 'UTF-8') ?><textarea name="admin_reason" rows="2" placeholder="<?= htmlspecialchars(t('Required when rejecting the request'), ENT_QUOTES, 'UTF-8') ?>"></textarea></label>
                            <div class="correction-decision-footer">
                                <small><?= htmlspecialchars(t('Approving immediately applies the proposed value to the selected record.'), ENT_QUOTES, 'UTF-8') ?></small>
                                <div class="correction-decision-actions">
                                    <button class="approve-button" name="decision" value="approved" type="submit"><?= htmlspecialchars(t('Approve & Apply'), ENT_QUOTES, 'UTF-8') ?></button>
                                    <button class="reject-button" name="decision" value="rejected" type="submit"><?= htmlspecialchars(t('Reject'), ENT_QUOTES, 'UTF-8') ?></button>
                                </div>
                            </div>
                        </form>
                    </article>
                <?php endforeach; endif; ?>
            </div>
        </section>

    </main>
</div>
<script src="assets/js/admin-dashboard.js?v=17"></script>
</body>
</html>
