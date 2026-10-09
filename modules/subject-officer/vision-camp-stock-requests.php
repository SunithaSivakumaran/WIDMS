<?php
declare(strict_types=1);

requireRole('subject-officer');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/spectacle-camps.php';
require_once __DIR__ . '/../../includes/spectacle-camp-components.php';

header('Cache-Control: no-store');
$activePage = 'vision-camp-stock-requests';
$actorId = (int) ($_SESSION['user_id'] ?? 0);
$highlightCampId = filter_var($_GET['camp_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$error = '';
$cards = [];

try {
    $db = database();
    $actor = scActor($db, $actorId);
    if ($actor['role'] !== 'subject-officer') throw new DomainException('Access denied.');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
            throw new DomainException('Your session expired. Refresh the page and try again.');
        }
        if (($_POST['action'] ?? '') !== 'request-stock-batch') {
            throw new DomainException('Invalid Vision Camp stock action.');
        }
        scPerform($db, $actorId, 'request-stock-batch', $_POST);
        $_SESSION['camp_notice'] = 'Camp stock request sent to Admin for approval.';
        header('Location: dashboard.php?page=my-spectacle-camps', true, 303);
        exit;
    }
} catch (DomainException $exception) {
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    error_log('Vision Camp stock request failed: ' . $exception->getMessage());
    $error = 'Unable to process the Vision Camp stock request.';
}

try {
    $db = database();
    $camps = scQuery($db, "SELECT c.id, c.camp_date, c.completed_at, d.name AS district_name,
            ds.name AS division_name, u.username AS conductor_username
        FROM spectacle_camps c
        JOIN districts d ON d.id = c.district_id
        JOIN ds_divisions ds ON ds.id = c.ds_division_id
        JOIN spectacle_camp_events e ON e.camp_id = c.id AND e.action = 'conduct-camp' AND e.actor_id = ?
        JOIN users u ON u.id = e.actor_id
        WHERE c.status = 'completed'
        ORDER BY (c.id = ?) DESC, c.completed_at DESC, c.id DESC", [$actorId, $highlightCampId])->fetchAll(PDO::FETCH_ASSOC);
    $selectedStatement = $db->prepare("SELECT p.spectacle_category_id, category.name AS category_name, COUNT(*) AS selected_count
        FROM spectacle_camp_participants p
        JOIN spectacle_categories category ON category.id = p.spectacle_category_id
        WHERE p.camp_id = ? AND p.status = 'approved'
        GROUP BY p.spectacle_category_id, category.name
        ORDER BY category.name");
    foreach ($camps as $camp) {
        $campId = (int) $camp['id'];
        $balances = scBalances($db, $campId);
        $requestNeeds = scCampRequestNeed($db, $campId);
        $selectedStatement->execute([$campId]);
        $types = [];
        $totalSelected = $totalReceived = $totalReady = 0;
        $waiting = false;
        foreach ($selectedStatement->fetchAll(PDO::FETCH_ASSOC) as $type) {
            $categoryId = (int) $type['spectacle_category_id'];
            $balance = $balances[$categoryId] ?? [];
            $selected = (int) $type['selected_count'];
            $received = (int) ($balance['received'] ?? 0);
            $ready = (int) ($requestNeeds[$categoryId] ?? 0);
            $requested = (int) ($balance['pending'] ?? 0) + (int) ($balance['reserved'] ?? 0)
                + (int) ($balance['released'] ?? 0);
            $types[] = ['name' => (string) $type['category_name'], 'selected' => $selected,
                'received' => $received, 'ready' => $ready, 'requested' => $requested];
            $totalSelected += $selected;
            $totalReceived += $received;
            $totalReady += $ready;
            if ($selected > $requested + $ready) $waiting = true;
        }
        if ($types === []) continue;
        $state = $totalReady > 0 ? 'ready' : ($waiting ? 'waiting' : 'requested');
        if ($state === 'ready') {
            $cards[] = ['camp' => $camp, 'types' => $types, 'selected' => $totalSelected,
                'received' => $totalReceived, 'ready' => $totalReady, 'state' => $state];
        }
    }
} catch (Throwable $exception) {
    error_log('Unable to load Vision Camp stock request cards: ' . $exception->getMessage());
    $error = 'Unable to load Vision Camp stock requests. Run the latest database migration.';
    $cards = [];
}
?>
<!doctype html>
<html lang="<?= scEscape(widmsLanguage()) ?>">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= scLabel('Request Camp Stock') ?> | SWPCS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/admin-dashboard.css') ?>" rel="stylesheet">
    <link href="assets/css/vision-camp-stock-requests.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/vision-camp-stock-requests.css') ?>" rel="stylesheet">
</head>
<body class="widms-unified-ui camp-request-page">
<?php require __DIR__ . '/../../includes/subject-officer-sidebar.php'; ?>
<div class="admin-shell">
    <header class="topbar"><div class="d-flex align-items-center gap-3"><button type="button" class="menu-button" id="menu-button" aria-label="<?= scLabel('Open navigation') ?>">&#9776;</button><h1><?= scLabel('Request Camp Stock') ?></h1></div></header>
    <main class="dashboard-content">
        <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= scEscape($error) ?></div><?php endif; ?>
        <?php if ($cards !== []): ?>
        <section class="admin-data-card camp-request-section">
            <div class="camp-request-grid">
                <?php foreach ($cards as $card): $camp = $card['camp']; ?>
                <article id="camp-<?= (int) $camp['id'] ?>" class="camp-request-card camp-request-<?= scEscape($card['state']) ?><?= (int) $camp['id'] === $highlightCampId ? ' camp-request-highlight' : '' ?>">
                    <div class="camp-request-card-top"><strong>VC-<?= (int) $camp['id'] ?></strong><span class="camp-request-pill camp-request-pill-<?= scEscape($card['state']) ?>"><?= scEscape(['ready' => 'Ready to request', 'waiting' => 'Waiting for stock', 'requested' => 'Already requested'][$card['state']]) ?></span></div>
                    <h3><?= scEscape($camp['division_name']) ?></h3><p class="camp-request-location"><?= scEscape($camp['district_name']) ?></p>
                    <div class="camp-request-metrics"><div><small>SELECTED SPECTACLES</small><strong><?= (int) $card['selected'] ?> units</strong></div><div class="camp-request-stock-metric"><small>RECEIVED IN CAMP STOCK</small><strong><?= (int) $card['received'] ?> units</strong><span><?= (int) $card['ready'] ?> available to request</span></div></div>
                    <div class="camp-request-types"><strong>Spectacle types</strong><table><thead><tr><th>Type</th><th>Selected</th><th>Received</th><th>Request now</th></tr></thead><tbody><?php foreach ($card['types'] as $type): ?><tr><td><?= scEscape($type['name']) ?></td><td><?= (int) $type['selected'] ?></td><td><?= (int) $type['received'] ?></td><td><?= (int) $type['ready'] ?></td></tr><?php endforeach; ?></tbody></table></div>
                    <div class="camp-request-actor"><span>Conducted by</span><strong><?= scEscape($camp['conductor_username']) ?></strong><small><?= scLabel('Subject Officer') ?></small></div>
                    <form method="post" class="camp-request-form">
                        <input type="hidden" name="csrf_token" value="<?= scEscape(csrfToken()) ?>">
                        <input type="hidden" name="request_token" value="<?= bin2hex(random_bytes(16)) ?>">
                        <input type="hidden" name="action" value="request-stock-batch">
                        <input type="hidden" name="camp_id" value="<?= (int) $camp['id'] ?>">
                        <label><?= scLabel('Planned Distribution Date') ?><input type="date" name="planned_distribution_date" min="<?= scToday() ?>" value="<?= scEscape(is_string($_POST['planned_distribution_date']??null)?$_POST['planned_distribution_date']:scToday()) ?>" required></label>
                        <?php scCampTimeFields('planned_distribution','Planned Distribution Time'); ?>
                        <label><?= scLabel('Distribution Place') ?><input type="text" name="planned_distribution_place" maxlength="255" value="<?= scEscape(is_string($_POST['planned_distribution_place']??null)?$_POST['planned_distribution_place']:'') ?>" placeholder="<?= scLabel('Enter the distribution venue or address') ?>" required></label>
                        <label>Remarks <span>(optional)</span><textarea name="remarks" maxlength="500" rows="2"></textarea></label>
                        <button type="submit" class="admin-primary-action">Submit camp request for Admin approval</button>
                    </form>
                </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </main>
</div>
<script src="assets/js/admin-dashboard.js"></script>
</body>
</html>
