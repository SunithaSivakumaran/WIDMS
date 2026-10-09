<?php
declare(strict_types=1);

requireRole('store-keeper');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/activity.php';
require_once __DIR__ . '/../../includes/correction-request-form.php';
require_once __DIR__ . '/../../includes/correction-reference.php';

$activePage = 'correction-requests';
$errors = [];
$success = (string) ($_SESSION['flash_success'] ?? '');
unset($_SESSION['flash_success']);
$errorTypes = [
    'wrong-unit-cost' => 'Wrong unit cost',
    'wrong-quantity' => 'Wrong quantity',
    'wrong-bill-number' => 'Wrong bill / invoice number',
    'wrong-date' => 'Wrong date received',
    'wrong-payment-amount' => 'Wrong payment amount',
    'wrong-check-number' => 'Wrong check number',
];
$requestedReferenceType = (string) ($_GET['reference_type'] ?? 'batch');
if (!in_array($requestedReferenceType, ['batch', 'payment'], true)) $requestedReferenceType = 'batch';
$requestedErrorType = (string) ($_GET['error_type'] ?? '');
if (!isset($errorTypes[$requestedErrorType])) $requestedErrorType = $requestedReferenceType === 'payment' ? 'wrong-payment-amount' : 'wrong-quantity';
$values = [
    'reference_type' => $requestedReferenceType,
    'record_reference' => trim((string) ($_GET['record_reference'] ?? '')),
    'error_type' => $requestedErrorType,
    'current_value' => '',
    'proposed_correction' => '',
    'request_reason' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($values) as $field) {
        $values[$field] = trim((string) ($_POST[$field] ?? ''));
    }
    if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) $errors[] = 'Your session expired. Refresh the page and try again.';
    if (!isset($errorTypes[$values['error_type']])) $errors[] = 'Select a valid correction field.';
    if ($values['record_reference'] === '' || mb_strlen($values['record_reference']) > 100) $errors[] = 'Enter a valid inventory record reference.';
    if ($errors === []) {
        try {
            $record = resolveCorrectionReference(database(), $values['reference_type'], $values['record_reference']);
            $values['record_reference'] = $record['record_reference'];
            // Always use the live database value; never trust the read-only browser field.
            $values['current_value'] = correctionReferenceCurrentValue($record, $values['error_type']);
        } catch (RuntimeException $exception) {
            $errors[] = $exception->getMessage();
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $errors[] = 'Unable to load the current record value.';
        }
    }
    if ($values['proposed_correction'] === '') $errors[] = 'Enter the proposed correction.';
    if ($values['request_reason'] === '') $errors[] = 'Explain why the correction is needed.';

    if ($errors === []) {
        try {
            $statement = database()->prepare(
                'INSERT INTO correction_requests (record_reference, error_type, current_value, proposed_correction, request_reason, submitted_by)
                 VALUES (:record_reference, :error_type, :current_value, :proposed_correction, :request_reason, :submitted_by)'
            );
            $statement->execute([
                'record_reference' => $values['record_reference'],
                'error_type' => $values['error_type'],
                'current_value' => $values['current_value'],
                'proposed_correction' => $values['proposed_correction'],
                'request_reason' => $values['request_reason'],
                'submitted_by' => $_SESSION['user_id'],
            ]);
            $requestId = (int) database()->lastInsertId();
            logActivity('Corrections', 'Submitted an inventory correction request', 'CR-' . str_pad((string) $requestId, 3, '0', STR_PAD_LEFT), 'pending');
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
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(t('Correction Requests'), ENT_QUOTES, 'UTF-8') ?> | SWPCS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css?v=50" rel="stylesheet">
</head>
<body class="store-page store-correction-page">
<?php require __DIR__ . '/../../includes/store-keeper-sidebar.php'; ?>
<div class="admin-shell">
    <header class="topbar"><div class="d-flex align-items-center gap-3"><button type="button" class="menu-button" id="menu-button" aria-label="<?= htmlspecialchars(t('Open navigation'), ENT_QUOTES, 'UTF-8') ?>">☰</button><h1><?= htmlspecialchars(t('Correction Requests'), ENT_QUOTES, 'UTF-8') ?></h1></div></header>
    <main class="dashboard-content correction-page">
        <?php if ($success !== ''): ?><div class="alert alert-success" role="status"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if ($errors !== []): ?><div class="alert alert-danger" role="alert"><ul class="mb-0 ps-3"><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <section class="correction-card correction-form-card">
            <div class="correction-card-header"><div class="correction-entry-title"><span class="correction-entry-symbol" aria-hidden="true">↻</span><div><h2><?= htmlspecialchars(t('Submit Correction Request'), ENT_QUOTES, 'UTF-8') ?></h2><p><?= htmlspecialchars(t('Report an incorrect inventory record for administrator review.'), ENT_QUOTES, 'UTF-8') ?></p></div></div></div>
            <?php renderCorrectionRequestForm($values, $errorTypes); ?>
        </section>
    </main>
</div>
<script src="assets/js/admin-dashboard.js"></script>
</body>
</html>
