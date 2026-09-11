<?php
declare(strict_types=1);

if ((string) ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('You do not have permission to access this page.');
}

require_once __DIR__ . '/../../config/database.php';

$activePage = 'reviewed-correction-requests';
$requests = [];
$error = '';
$errorTypes = [
    'wrong-unit-cost' => 'Wrong unit cost',
    'wrong-quantity' => 'Wrong quantity',
    'wrong-bill-number' => 'Wrong bill / invoice number',
    'wrong-supplier' => 'Wrong supplier',
    'wrong-date' => 'Wrong date received',
    'wrong-cost' => 'Wrong cost',
    'wrong-item' => 'Wrong item',
    'wrong-payment-amount' => 'Wrong payment amount',
    'wrong-check-number' => 'Wrong check number',
    'wrong-payment-date' => 'Wrong payment date',
    'other' => 'Other',
];

try {
    $requests = database()->query(
        "SELECT c.*, u.full_name AS submitted_name, reviewer.full_name AS reviewer_name
         FROM correction_requests c
         JOIN users u ON u.id = c.submitted_by
         LEFT JOIN users reviewer ON reviewer.id = c.reviewed_by
         WHERE c.status IN ('approved', 'rejected')
         ORDER BY c.reviewed_at DESC, c.id DESC"
    )->fetchAll();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $error = 'Reviewed correction requests are currently unavailable.';
}

$counts = ['approved' => 0, 'rejected' => 0];
foreach ($requests as $request) {
    $status = (string) ($request['status'] ?? '');
    if (isset($counts[$status])) {
        $counts[$status]++;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(t('Reviewed Correction Requests'), ENT_QUOTES, 'UTF-8') ?> | WIDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css?v=58" rel="stylesheet">
</head>
<body class="admin-correction-page">
<?php require __DIR__ . '/../../includes/admin-sidebar.php'; ?>
<div class="admin-shell">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button type="button" class="menu-button" id="menu-button" aria-label="Open navigation">&#9776;</button>
            <h1><?= htmlspecialchars(t('Reviewed Correction Requests'), ENT_QUOTES, 'UTF-8') ?></h1>
        </div>
    </header>

    <main class="dashboard-content correction-page admin-correction-review-page">
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <section class="admin-data-card admin-correction-table-card" aria-label="<?= htmlspecialchars(t('Approved and rejected correction requests'), ENT_QUOTES, 'UTF-8') ?>">
            <div class="admin-data-header correction-card-header admin-correction-review-header">
                <div class="correction-review-counts" aria-label="<?= htmlspecialchars(t('Reviewed correction request totals'), ENT_QUOTES, 'UTF-8') ?>">
                    <span class="approved"><strong><?= $counts['approved'] ?></strong> <?= htmlspecialchars(t('Approved'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="rejected"><strong><?= $counts['rejected'] ?></strong> <?= htmlspecialchars(t('Rejected'), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>
            <div class="admin-data-table-wrap admin-correction-table-wrap">
                <table class="admin-data-table admin-correction-table">
                    <thead>
                        <tr>
                            <th><?= htmlspecialchars(t('Request'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Record Reference'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Field'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Recorded Value'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(t('Proposed Value'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Submitted'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Status'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Admin Response'), ENT_QUOTES, 'UTF-8') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($requests === []): ?>
                        <tr><td colspan="8" class="admin-empty-row"><?= htmlspecialchars(t('No reviewed correction requests yet.'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                    <?php else: foreach ($requests as $request): ?>
                        <?php
                        $requestStatus = (string) $request['status'];
                        $referenceType = t(str_starts_with((string) $request['record_reference'], 'PAY-') ? 'Payment record' : 'Inventory batch');
                        ?>
                        <tr class="correction-table-row is-<?= htmlspecialchars($requestStatus, ENT_QUOTES, 'UTF-8') ?>">
                            <td><strong>CR-<?= str_pad((string) $request['id'], 3, '0', STR_PAD_LEFT) ?></strong></td>
                            <td><strong><?= htmlspecialchars($request['record_reference'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($referenceType, ENT_QUOTES, 'UTF-8') ?></small></td>
                            <td><?= htmlspecialchars(t($errorTypes[$request['error_type']] ?? $request['error_type']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="correction-table-value recorded"><?= nl2br(htmlspecialchars($request['current_value'], ENT_QUOTES, 'UTF-8')) ?></td>
                            <td class="correction-table-value proposed"><?= nl2br(htmlspecialchars($request['proposed_correction'], ENT_QUOTES, 'UTF-8')) ?><small><?= htmlspecialchars($request['request_reason'], ENT_QUOTES, 'UTF-8') ?></small></td>
                            <td><?= htmlspecialchars($request['submitted_name'], ENT_QUOTES, 'UTF-8') ?><small><?= date('d M Y, H:i', strtotime($request['created_at'])) ?></small></td>
                            <td><span class="correction-status <?= htmlspecialchars($requestStatus, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t(ucfirst($requestStatus)), ENT_QUOTES, 'UTF-8') ?></span><?php if ($request['reviewed_at']): ?><small><?= htmlspecialchars((string) ($request['reviewer_name'] ?: t('Administrator')), ENT_QUOTES, 'UTF-8') ?><br><?= date('d M Y, H:i', strtotime($request['reviewed_at'])) ?></small><?php endif; ?></td>
                            <td class="correction-table-action"><span class="correction-table-response"><?= $request['admin_reason'] ? nl2br(htmlspecialchars($request['admin_reason'], ENT_QUOTES, 'UTF-8')) : '&mdash;' ?></span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
<script src="assets/js/admin-dashboard.js?v=19"></script>
</body>
</html>
