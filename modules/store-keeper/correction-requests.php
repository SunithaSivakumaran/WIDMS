<?php
declare(strict_types=1);

requireRole('store-keeper');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/activity.php';

$activePage = 'correction-requests';
$errors = [];
$success = (string) ($_SESSION['flash_success'] ?? '');
unset($_SESSION['flash_success']);
$requests = [];
$errorTypes = [
    'wrong-quantity' => 'Wrong quantity', 'wrong-supplier' => 'Wrong supplier',
    'wrong-date' => 'Wrong date', 'wrong-cost' => 'Wrong cost',
    'wrong-item' => 'Wrong item', 'other' => 'Other',
];
$values = ['record_reference' => '', 'error_type' => 'wrong-quantity', 'current_value' => '', 'proposed_correction' => '', 'request_reason' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($values) as $field) $values[$field] = trim((string) ($_POST[$field] ?? ''));
    if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) $errors[] = 'Your session expired. Refresh the page and try again.';
    if ($values['record_reference'] === '' || mb_strlen($values['record_reference']) > 100) $errors[] = 'Enter a valid inventory record reference.';
    if (!isset($errorTypes[$values['error_type']])) $errors[] = 'Select a valid error type.';
    if ($values['current_value'] === '') $errors[] = 'Enter the current incorrect value.';
    if ($values['proposed_correction'] === '') $errors[] = 'Enter the proposed correction.';
    if ($values['request_reason'] === '') $errors[] = 'Explain why the correction is needed.';

    if ($errors === []) {
        try {
            $statement = database()->prepare(
                'INSERT INTO correction_requests (record_reference, error_type, current_value, proposed_correction, request_reason, submitted_by)
                 VALUES (:record_reference, :error_type, :current_value, :proposed_correction, :request_reason, :submitted_by)'
            );
            $statement->execute($values + ['submitted_by' => $_SESSION['user_id']]);
            $requestId = (int) database()->lastInsertId();
            logActivity('Corrections', 'Submitted an inventory correction request', 'CR-' . str_pad((string)$requestId,3,'0',STR_PAD_LEFT), 'pending');
            $_SESSION['flash_success'] = sprintf('Correction request CR-%03d sent to the administrator.', $requestId);
            unset($_SESSION['csrf_token']);
            header('Location: dashboard.php?page=correction-requests');
            exit;
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            $errors[] = 'Unable to submit the request. Import database/migration_correction_requests.sql first.';
        }
    }
}

try {
    $statement = database()->prepare(
        'SELECT id, record_reference, error_type, proposed_correction, status, admin_reason, created_at
         FROM correction_requests WHERE submitted_by = :submitted_by ORDER BY id DESC'
    );
    $statement->execute(['submitted_by' => $_SESSION['user_id']]);
    $requests = $statement->fetchAll();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    if ($errors === []) $errors[] = 'Correction requests are unavailable. Import the correction request migration.';
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Correction Requests | WIDMS</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="assets/css/admin-dashboard.css" rel="stylesheet"></head>
<body class="store-page store-correction-page"><?php require __DIR__ . '/../../includes/store-keeper-sidebar.php'; ?>
<div class="admin-shell">
    <header class="topbar"><div class="d-flex align-items-center gap-3"><button type="button" class="menu-button" id="menu-button" aria-label="Open navigation">☰</button><h1>Correction Requests</h1></div></header>
    <main class="dashboard-content correction-page">
        <?php if ($success !== ''): ?><div class="alert alert-success" role="status"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if ($errors !== []): ?><div class="alert alert-danger" role="alert"><ul class="mb-0 ps-3"><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <section class="correction-card"><div class="correction-card-header"><h2>Submit Correction Request</h2></div>
            <form method="post" action="dashboard.php?page=correction-requests" class="correction-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="correction-grid">
                    <label>Inventory Record Reference<input name="record_reference" maxlength="100" placeholder="e.g. BAT-0001 or BILL-2026-0041" value="<?= htmlspecialchars($values['record_reference'], ENT_QUOTES, 'UTF-8') ?>" required></label>
                    <label>Nature of Error<select name="error_type" required><?php foreach ($errorTypes as $value => $label): ?><option value="<?= $value ?>" <?= $values['error_type'] === $value ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label>
                    <label class="full-width">Current (incorrect) Value<input name="current_value" placeholder="What the record currently shows" value="<?= htmlspecialchars($values['current_value'], ENT_QUOTES, 'UTF-8') ?>" required></label>
                    <label class="full-width">Proposed Correction<input name="proposed_correction" placeholder="What it should be changed to" value="<?= htmlspecialchars($values['proposed_correction'], ENT_QUOTES, 'UTF-8') ?>" required></label>
                    <label class="full-width">Reason<textarea name="request_reason" rows="3" placeholder="Explain why this correction is needed..." required><?= htmlspecialchars($values['request_reason'], ENT_QUOTES, 'UTF-8') ?></textarea></label>
                </div><button class="correction-submit-button" type="submit">Submit Correction Request</button>
            </form>
        </section>
        <section class="correction-card correction-list-card"><div class="correction-card-header"><h2>My Correction Requests</h2></div><div class="correction-table-wrap"><table class="correction-table">
            <thead><tr><th>ID</th><th>Record Ref</th><th>Error Type</th><th>Proposed Fix</th><th>Submitted</th><th>Status</th><th>Admin Response</th></tr></thead>
            <tbody><?php if ($requests === []): ?><tr><td colspan="7" class="empty-table">No correction requests submitted yet.</td></tr><?php else: foreach ($requests as $request): ?><tr>
                <td>CR-<?= str_pad((string) $request['id'], 3, '0', STR_PAD_LEFT) ?></td><td><?= htmlspecialchars($request['record_reference'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($errorTypes[$request['error_type']] ?? $request['error_type'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($request['proposed_correction'], ENT_QUOTES, 'UTF-8') ?></td><td><?= date('d M Y', strtotime($request['created_at'])) ?></td><td><span class="correction-status <?= $request['status'] ?>"><?= $request['status'] === 'approved' ? 'Done' : ucfirst($request['status']) ?></span></td><td class="admin-response"><?= htmlspecialchars($request['admin_reason'] ?: ($request['status'] === 'pending' ? 'Awaiting admin review' : '—'), ENT_QUOTES, 'UTF-8') ?></td>
            </tr><?php endforeach; endif; ?></tbody>
        </table></div></section>
    </main>
</div><script src="assets/js/admin-dashboard.js"></script></body></html>
