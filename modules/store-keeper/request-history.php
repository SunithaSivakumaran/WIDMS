<?php
declare(strict_types=1);

requireRole('store-keeper');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/correction-request-history.php';

$activePage = 'request-history';
$errors = [];
$requests = [];
$errorTypes = [
    'wrong-unit-cost' => 'Wrong unit cost',
    'wrong-quantity' => 'Wrong quantity', 'wrong-supplier' => 'Wrong supplier',
    'wrong-date' => 'Wrong date', 'wrong-cost' => 'Wrong cost',
    'wrong-item' => 'Wrong item', 'wrong-bill-number' => 'Wrong bill / invoice number',
    'wrong-payment-amount' => 'Wrong payment amount',
    'wrong-check-number' => 'Wrong check number',
    'wrong-payment-date' => 'Wrong payment date',
    'other' => 'Other',
];

try {
    $statement = database()->prepare(
        'SELECT id, record_reference, error_type, proposed_correction, status, admin_reason, created_at
         FROM correction_requests
         WHERE submitted_by = :submitted_by
         ORDER BY id DESC'
    );
    $statement->execute(['submitted_by' => $_SESSION['user_id']]);
    $requests = $statement->fetchAll();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $errors[] = 'Correction request history is unavailable. Import the correction request migration.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Request History | WIDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css?v=43" rel="stylesheet">
</head>
<body class="store-page store-correction-page request-history-page">
<?php require __DIR__ . '/../../includes/store-keeper-sidebar.php'; ?>
<div class="admin-shell">
    <header class="topbar"><div class="d-flex align-items-center gap-3"><button type="button" class="menu-button" id="menu-button" aria-label="Open navigation">☰</button><h1>Request History</h1></div></header>
    <main class="dashboard-content correction-page">
        <?php if ($errors !== []): ?><div class="alert alert-danger" role="alert"><ul class="mb-0 ps-3"><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <?php renderCorrectionRequestHistory($requests, $errorTypes); ?>
    </main>
</div>
<script src="assets/js/admin-dashboard.js"></script>
</body>
</html>
