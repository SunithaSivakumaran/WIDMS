<?php
declare(strict_types=1);

if (!in_array((string)($_SESSION['role'] ?? ''), ['admin','subject-officer'], true)) { http_response_code(403); exit('You do not have permission to access this page.'); }
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/activity.php';

$isSubjectReviewer = ($_SESSION['role'] ?? '') === 'subject-officer';
$activePage = $isSubjectReviewer ? 'correction-approval' : 'correction-requests';
$notice = '';
$noticeType = 'success';
$requests = [];
$errorTypes = ['wrong-quantity' => 'Wrong quantity', 'wrong-supplier' => 'Wrong supplier', 'wrong-date' => 'Wrong date', 'wrong-cost' => 'Wrong cost', 'wrong-item' => 'Wrong item', 'other' => 'Other'];

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
            $statement = database()->prepare(
                "UPDATE correction_requests SET status = :status, admin_reason = :admin_reason, reviewed_by = :reviewed_by, reviewed_at = NOW()
                 WHERE id = :id AND status = 'pending'"
            );
            $statement->execute([
                'status' => $decision, 'admin_reason' => $adminReason !== '' ? $adminReason : null,
                'reviewed_by' => $_SESSION['user_id'], 'id' => $requestId,
            ]);
            if ($statement->rowCount() !== 1) throw new RuntimeException('This request was already reviewed or does not exist.');
            logActivity('Corrections', ucfirst($decision) . ' correction request', 'CR-' . str_pad((string)$requestId,3,'0',STR_PAD_LEFT), $decision);
            $notice = $decision === 'approved' ? 'Correction request approved and marked Done.' : 'Correction request rejected. The reason is now visible to the Store Keeper.';
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $notice = $exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to review the correction request.';
            $noticeType = 'danger';
        }
    }
}

try {
    $requests = database()->query(
        "SELECT c.*, u.full_name AS submitted_name, reviewer.full_name AS reviewer_name
         FROM correction_requests c JOIN users u ON u.id = c.submitted_by
         LEFT JOIN users reviewer ON reviewer.id = c.reviewed_by
         ORDER BY CASE c.status WHEN 'pending' THEN 0 ELSE 1 END, c.id DESC"
    )->fetchAll();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $notice = 'Correction requests are unavailable. Import database/migration_correction_requests.sql.'; $noticeType = 'danger';
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Correction Requests | WIDMS</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="assets/css/admin-dashboard.css" rel="stylesheet"></head>
<body><?php require $isSubjectReviewer ? __DIR__ . '/../../includes/subject-officer-sidebar.php' : __DIR__ . '/../../includes/admin-sidebar.php'; ?>
<div class="admin-shell"><header class="topbar"><div class="d-flex align-items-center gap-3"><button type="button" class="menu-button" id="menu-button" aria-label="Open navigation">☰</button><h1>Correction Requests</h1></div></header>
<main class="dashboard-content correction-page">
    <?php if ($notice !== ''): ?><div class="alert alert-<?= $noticeType ?>" role="status"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <section class="correction-card"><div class="correction-card-header"><h2>Store Keeper Correction Requests</h2></div><div class="admin-correction-list">
    <?php if ($requests === []): ?><p class="empty-corrections">No correction requests found.</p><?php else: foreach ($requests as $request): ?>
        <article class="admin-correction-item">
            <div class="correction-summary"><div><strong>CR-<?= str_pad((string) $request['id'], 3, '0', STR_PAD_LEFT) ?> · <?= htmlspecialchars($request['record_reference'], ENT_QUOTES, 'UTF-8') ?></strong><small>Submitted by <?= htmlspecialchars($request['submitted_name'], ENT_QUOTES, 'UTF-8') ?> on <?= date('d M Y H:i', strtotime($request['created_at'])) ?></small></div><span class="correction-status <?= $request['status'] ?>"><?= $request['status'] === 'approved' ? 'Done' : ucfirst($request['status']) ?></span></div>
            <dl class="correction-details"><div><dt>Error Type</dt><dd><?= htmlspecialchars($errorTypes[$request['error_type']] ?? $request['error_type'], ENT_QUOTES, 'UTF-8') ?></dd></div><div><dt>Current Value</dt><dd><?= nl2br(htmlspecialchars($request['current_value'], ENT_QUOTES, 'UTF-8')) ?></dd></div><div><dt>Proposed Correction</dt><dd><?= nl2br(htmlspecialchars($request['proposed_correction'], ENT_QUOTES, 'UTF-8')) ?></dd></div><div><dt>Request Reason</dt><dd><?= nl2br(htmlspecialchars($request['request_reason'], ENT_QUOTES, 'UTF-8')) ?></dd></div></dl>
            <?php if ($request['status'] === 'pending'): ?><form method="post" action="dashboard.php?page=correction-requests" class="admin-decision-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><label>Admin reason / note<textarea name="admin_reason" rows="2" placeholder="Required when rejecting the request"></textarea></label><div><button class="approve-button" name="decision" value="approved" type="submit">✓ Approve</button><button class="reject-button" name="decision" value="rejected" type="submit">✕ Reject</button></div></form>
            <?php elseif ($request['admin_reason']): ?><p class="reviewed-reason"><strong>Admin response:</strong> <?= nl2br(htmlspecialchars($request['admin_reason'], ENT_QUOTES, 'UTF-8')) ?></p><?php endif; ?>
        </article>
    <?php endforeach; endif; ?>
    </div></section>
</main></div><script src="assets/js/admin-dashboard.js"></script></body></html>
