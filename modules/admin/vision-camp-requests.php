<?php
declare(strict_types=1);

requireRole('admin');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/spectacle-camps.php';
require_once __DIR__ . '/../../includes/spectacle-camp-components.php';

$activePage = 'pending-approvals';
$notice = (string) ($_SESSION['camp_approval_notice'] ?? '');
unset($_SESSION['camp_approval_notice']);
$error = '';
$requests = [];
$counts = ['registrations'=>0, 'aid'=>0, 'stock'=>0, 'corrections'=>0, 'vision-camps'=>0];
header('Cache-Control: no-store');

try {
    $db = database();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!is_string($_POST['csrf_token'] ?? null) || !verifyCsrfToken($_POST['csrf_token'])) {
            throw new DomainException('Your session expired. Refresh the page and try again.');
        }
        if (($_POST['action'] ?? '') !== 'decide-camp') {
            throw new DomainException('Invalid approval request.');
        }
        scPerform($db, (int) $_SESSION['user_id'], 'decide-camp', $_POST);
        $_SESSION['camp_approval_notice'] = t('Vision Camp updated successfully.');
        header('Location: dashboard.php?page=pending-approvals&tab=vision-camps');
        exit;
    }
} catch (DomainException $exception) {
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    error_log('Vision Camp approval failed: ' . $exception->getCode());
    $error = 'Unable to process the Vision Camp action.';
}

try {
    $db = database();
    $requests = scQuery($db, "SELECT c.*, d.name district_name, ds.name division_name, u.full_name requester_name, u.username requester_username, u.role requester_role
        FROM spectacle_camps c JOIN districts d ON d.id=c.district_id
        JOIN ds_divisions ds ON ds.id=c.ds_division_id JOIN users u ON u.id=c.requested_by
        WHERE c.status='pending' ORDER BY c.created_at,c.id")->fetchAll(PDO::FETCH_ASSOC);
    $counts['vision-camps'] = count($requests);
    $counts['registrations'] = (int) $db->query("SELECT COUNT(*) FROM registration_requests WHERE status='pending'")->fetchColumn();
    $counts['aid'] = (int) $db->query("SELECT COUNT(*) FROM aid_requests WHERE status='pending'")->fetchColumn();
    $counts['stock'] = (int) $db->query("SELECT COUNT(DISTINCT COALESCE(NULLIF(request_batch_ref, ''), CONCAT('GR-', id))) FROM goods_requests WHERE status='pending-admin-approval'")->fetchColumn();
    $counts['corrections'] = (int) $db->query("SELECT COUNT(*) FROM correction_requests WHERE status='pending'")->fetchColumn();
} catch (Throwable $exception) {
    error_log('Vision Camp approval list unavailable: ' . $exception->getCode());
    $error = 'Unable to load Vision Camps. Run the latest database migration.';
}
?>
<!doctype html>
<html lang="<?= scEscape(widmsLanguage()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= scLabel('Vision Camp Requests') ?> | SWPCS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css?v=72" rel="stylesheet">
    <link href="assets/css/spectacle-camps.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/spectacle-camps.css') ?>" rel="stylesheet">
</head>
<body class="sc-page">
<?php require __DIR__ . '/../../includes/admin-sidebar.php'; ?>
<div class="admin-shell">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3"><button type="button" class="menu-button" id="menu-button" aria-label="<?= scLabel('Open navigation') ?>">&#9776;</button><h1><?= scLabel('Vision Camp Requests') ?></h1></div>
        <div class="topbar-actions"><button class="notification-button" type="button" aria-label="<?= scLabel('Notifications') ?>">&#128276;</button></div>
    </header>
    <main class="dashboard-content approvals-page admin-correction-review-page registration-review-page">
        <?php if ($notice !== ''): ?><div class="alert alert-success" role="status"><?= scEscape($notice) ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= scLabel($error) ?></div><?php endif; ?>
        <section class="approval-tab-panel active" aria-label="<?= scLabel('Vision Camp Requests') ?>">
            <?php scApprovalCardList($requests, 'camp'); ?>
        </section>
    </main>
</div>
<script src="assets/js/admin-dashboard.js"></script>
<script src="assets/js/spectacle-camps.js?v=<?= filemtime(__DIR__ . '/../../public/assets/js/spectacle-camps.js') ?>"></script>
<script src="assets/js/vision-camp-approvals.js"></script>
</body>
</html>
