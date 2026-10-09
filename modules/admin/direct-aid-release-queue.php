<?php
declare(strict_types=1);

requireRole('admin');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/activity.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_once __DIR__ . '/../../includes/aid-request-details.php';
require_once __DIR__ . '/../../includes/beneficiary-division-guard.php';

$activePage = 'direct-aid-release-queue';
$database = database();
$adminId = (int) $_SESSION['user_id'];
$errors = [];
$success = (string) ($_SESSION['flash_success'] ?? '');
unset($_SESSION['flash_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $releaseId = filter_input(INPUT_POST, 'release_id', FILTER_VALIDATE_INT);
    if (!$releaseId || !verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = t('Invalid distribution or expired session.');
    } else {
        try {
            $database->beginTransaction();
            $statement = $database->prepare(
                "SELECT release_record.id, release_record.aid_request_id,
                        request.beneficiary_id, request.item_id, request.quantity,
                        beneficiary.ds_division_id, beneficiary.full_name,
                        item.item_name
                 FROM admin_direct_releases release_record
                 JOIN aid_requests request ON request.id = release_record.aid_request_id
                 JOIN beneficiaries beneficiary ON beneficiary.id = request.beneficiary_id
                 JOIN inventory_items item ON item.id = request.item_id
                 WHERE release_record.id = :id AND release_record.admin_id = :admin_id
                   AND release_record.status = 'released-to-admin'
                   AND request.status = 'approved'
                   AND NOT EXISTS (
                       SELECT 1 FROM distributions distribution
                       WHERE distribution.aid_request_id = request.id
                   )
                 FOR UPDATE"
            );
            $statement->execute(['id' => $releaseId, 'admin_id' => $adminId]);
            $release = $statement->fetch();
            if (!$release) {
                throw new RuntimeException(t('This released item is no longer available for distribution.'));
            }
            assertBeneficiaryRecordDivision($database, (int)$release['beneficiary_id']);

            $statement = $database->prepare(
                "INSERT INTO distributions
                    (aid_request_id, beneficiary_id, ds_division_id, item_id, quantity,
                     distribution_type, source, distributed_by)
                 VALUES
                    (:request_id, :beneficiary_id, (SELECT ds_division_id FROM beneficiaries WHERE id = :division_beneficiary), :item_id, :quantity,
                     'direct', 'central-stock', :admin_id)"
            );
            $statement->execute([
                'request_id' => $release['aid_request_id'],
                'beneficiary_id' => $release['beneficiary_id'],
                'division_beneficiary' => $release['beneficiary_id'],
                'item_id' => $release['item_id'],
                'quantity' => $release['quantity'],
                'admin_id' => $adminId,
            ]);
            $distributionId = (int) $database->lastInsertId();

            $updateRequest = $database->prepare(
                "UPDATE aid_requests SET status = 'distributed'
                 WHERE id = :id AND status = 'approved'"
            );
            $updateRequest->execute(['id' => $release['aid_request_id']]);
            $updateRelease = $database->prepare(
                "UPDATE admin_direct_releases
                 SET status = 'distributed', distributed_at = NOW()
                 WHERE id = :id AND status = 'released-to-admin'"
            );
            $updateRelease->execute(['id' => $releaseId]);
            if ($updateRequest->rowCount() !== 1 || $updateRelease->rowCount() !== 1) {
                throw new RuntimeException(t('The release changed. Refresh and try again.'));
            }

            $ssoStatement = $database->prepare(
                "SELECT id FROM users
                 WHERE role = 'social-service-officer' AND status = 'active'
                   AND ds_division_id = :division_id"
            );
            $ssoStatement->execute(['division_id' => $release['ds_division_id']]);
            $ssoIds = $ssoStatement->fetchAll(PDO::FETCH_COLUMN);
            foreach ($ssoIds as $ssoId) {
                notifyUser(
                    $database,
                    (int) $ssoId,
                    'admin-direct-distributed-' . $distributionId,
                    'Direct aid distribution',
                    'AR-' . str_pad((string) $release['aid_request_id'], 4, '0', STR_PAD_LEFT),
                    'The Administrator distributed ' . $release['item_name'] . ' to ' . $release['full_name'] . '.',
                    'dashboard.php?page=aid-requests#aid-request-' . $release['aid_request_id']
                );
            }

            $database->commit();
            logActivity(
                'Distribution',
                'Admin directly distributed aid to beneficiary',
                'DIST-' . $distributionId,
                'distributed'
            );
            $_SESSION['flash_success'] = $ssoIds
                ? t('Direct distribution recorded. The beneficiary division SSO was notified.')
                : t('Direct distribution recorded. No active SSO is assigned to the beneficiary division.');
            unset($_SESSION['csrf_token']);
            header('Location: dashboard.php?page=direct-aid-release-queue&view=history#aid-request-' . $release['aid_request_id']);
            exit;
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            error_log($exception->getMessage());
            $errors[] = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : t('Unable to record direct distribution.');
        }
    }
}

try {
    $statement = $database->prepare(
        "SELECT release_record.id AS release_id,
                COALESCE(release_record.status, 'distributed') AS release_status,
                COALESCE(release_record.created_at, request.created_at) AS release_created_at,
                release_record.released_at,
                COALESCE(release_record.distributed_at,
                    (SELECT MAX(completed.distributed_at) FROM distributions completed
                     WHERE completed.aid_request_id = request.id AND completed.distribution_type = 'direct')
                ) AS distributed_at,
                request.*, request.id AS aid_request_id,
                beneficiary.full_name AS beneficiary_name,
                beneficiary.nic, beneficiary.elders_card_number,
                beneficiary.date_of_birth, beneficiary.address,
                district.name AS district_name, division.name AS division_name,
                item.item_name, item.variety, spectacle_type.name AS spectacle_category_name,
                EXISTS (
                    SELECT 1 FROM distributions distribution
                    JOIN item_returns item_return ON item_return.distribution_id = distribution.id
                        AND item_return.stock_review_status = 'accepted'
                    WHERE distribution.aid_request_id = request.id
                ) AS has_recorded_return
         FROM aid_requests request
         LEFT JOIN admin_direct_releases release_record ON request.id = release_record.aid_request_id
         JOIN beneficiaries beneficiary ON beneficiary.id = request.beneficiary_id
         JOIN districts district ON district.id = beneficiary.district_id
         JOIN ds_divisions division ON division.id = beneficiary.ds_division_id
         JOIN inventory_items item ON item.id = request.item_id
         LEFT JOIN spectacle_categories spectacle_type ON spectacle_type.id = request.spectacle_category_id
         WHERE release_record.admin_id = :release_admin
            OR (request.submitted_by = :history_admin AND EXISTS (
                SELECT 1 FROM distributions historical
                WHERE historical.aid_request_id = request.id AND historical.distribution_type = 'direct'
            ))
         ORDER BY COALESCE(distributed_at, release_record.released_at, request.created_at) DESC,
                  request.id DESC"
    );
    $statement->execute(['release_admin' => $adminId, 'history_admin' => $adminId]);
    $allRows = $statement->fetchAll();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $allRows = [];
    $errors[] = t('Direct distribution queue is unavailable. Run database migrations.');
}

$view = (string) ($_GET['view'] ?? 'all');
if ((string) ($_GET['page'] ?? '') === 'my-aid-requests') {
    $view = 'history';
}
if (!in_array($view, ['all', 'queue', 'history'], true)) {
    $view = 'all';
}
$queueCount = count(array_filter($allRows, static fn(array $row): bool => $row['release_status'] !== 'distributed'));
$historyCount = count($allRows) - $queueCount;
$rows = array_values(array_filter($allRows, static fn(array $row): bool =>
    $view === 'all' || ($view === 'history' ? $row['release_status'] === 'distributed' : $row['release_status'] !== 'distributed')
));

$h = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="<?= $h(widmsLanguage()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $h(t('Direct Aid Releases & History')) ?> | SWPCS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/admin-dashboard.css') ?>" rel="stylesheet">
</head>
<body class="admin-direct-release-queue-page">
<?php require __DIR__ . '/../../includes/admin-sidebar.php'; ?>
<div class="admin-shell">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="menu-button" id="menu-button" type="button" aria-label="<?= $h(t('Open navigation')) ?>">&#9776;</button>
            <h1><?= $h(t('Direct Aid Releases & History')) ?></h1>
        </div>
    </header>
    <main class="dashboard-content admin-operation-page admin-item-requests-page admin-direct-release-queue-content">
        <?php if ($success !== ''): ?><div class="alert alert-success" role="status"><?= $h(t($success)) ?></div><?php endif; ?>
        <?php if ($errors): ?><div class="alert alert-danger" role="alert"><?= $h(implode(' ', $errors)) ?></div><?php endif; ?>
        <nav class="direct-aid-view-tabs" aria-label="<?= $h(t('Distribution views')) ?>">
            <a href="dashboard.php?page=direct-aid-release-queue"<?= $view === 'all' ? ' aria-current="page"' : '' ?>><?= $h(t('All')) ?> <strong><?= count($allRows) ?></strong></a>
            <a href="dashboard.php?page=direct-aid-release-queue&amp;view=queue"<?= $view === 'queue' ? ' aria-current="page"' : '' ?>><?= $h(t('Release Queue')) ?> <strong><?= $queueCount ?></strong></a>
            <a href="dashboard.php?page=direct-aid-release-queue&amp;view=history"<?= $view === 'history' ? ' aria-current="page"' : '' ?>><?= $h(t('Distribution History')) ?> <strong><?= $historyCount ?></strong></a>
        </nav>
        <div class="approval-legend approval-legend-standalone direct-release-guide" aria-label="<?= $h(t('Release guide')) ?>">
            <strong class="approval-legend-title"><?= $h(t('Release guide')) ?></strong>
            <span>⏳ <?= $h(t('Awaiting Store Keeper')) ?></span>
            <span>✓ <?= $h(t('Released to Admin')) ?></span>
        </div>
        <section class="admin-data-card">
            <div class="approval-legend approval-legend-standalone" aria-label="<?= $h(t('Approval icon meanings')) ?>">
                <strong class="approval-legend-title"><?= $h(t('Approval guide')) ?></strong>
                <span>🩺 <?= $h(t('Government Medical Officer')) ?></span>
                <span>🏡 <?= $h(t('Grama Niladhari')) ?></span>
                <span>🏛 <?= $h(t('Social Services Officer')) ?></span>
                <span>📋 <?= $h(t('Divisional Secretary')) ?></span>
                <span>❌ <?= $h(t('Not approved')) ?></span>
            </div>
            <div class="admin-data-table-wrap">
                <div class="identification-guide" aria-label="<?= $h(t('Identification guide')) ?>">
                    <strong><?= $h(t('Identification guide')) ?></strong>
                    <span class="identification-guide-nic">NIC</span>
                    <span class="identification-guide-elder"><?= $h(t("Elders' Identity Card")) ?></span>
                </div>
                <table class="admin-data-table item-requests-table aid-request-list-table">
                    <thead><tr>
                        <th><?= $h(t('ID')) ?></th>
                        <th><?= $h(t('Beneficiary')) ?></th>
                        <th><?= $h(t('Identification')) ?></th>
                        <th><?= $h(t('Age')) ?></th>
                        <th><?= $h(t('Address')) ?></th>
                        <th><?= $h(t('District')) ?></th>
                        <th><?= $h(t('DS Division')) ?></th>
                        <th><?= $h(t('Aid Requested')) ?></th>
                        <th><?= $h(t('Approvals')) ?></th>
                        <th><?= $h(t('Date')) ?></th>
                        <th><?= $h(t('Release Status')) ?></th>
                        <th><?= $h(t('Notes')) ?></th>
                        <th><?= $h(t('Action')) ?></th>
                    </tr></thead>
                    <tbody>
                    <?php if (!$rows): ?><tr><td colspan="13" class="admin-empty-row"><?= $h(t('No direct aid records match this view.')) ?></td></tr><?php endif; ?>
                    <?php foreach ($rows as $row): ?>
                        <?php $displayStatus = $row['release_status'] === 'distributed'
                            ? (!empty($row['has_recorded_return']) ? 'return' : 'direct-distribution')
                            : (string) $row['release_status']; ?>
                        <tr id="aid-request-<?= (int) $row['aid_request_id'] ?>" class="admin-notification-target aid-review-row <?= $row['release_status'] === 'distributed' ? 'is-release-complete' : ($row['release_status'] === 'released-to-admin' ? 'is-release-ready' : 'is-release-waiting') ?>" tabindex="-1">
                            <td><strong>AR-<?= str_pad((string) $row['aid_request_id'], 4, '0', STR_PAD_LEFT) ?></strong></td>
                            <td class="request-beneficiary-name"><strong><?= $h($row['beneficiary_name']) ?></strong></td>
                            <td class="request-identification-cell">
                                <?php if ((string) ($row['nic'] ?? '') !== ''): ?><span class="request-identification-value identification-nic"><small>NIC</small><?= $h($row['nic']) ?></span><?php endif; ?>
                                <?php if ((string) ($row['elders_card_number'] ?? '') !== ''): ?><span class="request-identification-value identification-elder"><small><?= $h(t("Elders' ID")) ?></small><?= $h($row['elders_card_number']) ?></span><?php endif; ?>
                                <?php if (empty($row['nic']) && empty($row['elders_card_number'])): ?>&mdash;<?php endif; ?>
                            </td>
                            <td><?= $row['date_of_birth'] ? (int) (new DateTimeImmutable((string) $row['date_of_birth']))->diff(new DateTimeImmutable('today'))->y : '&mdash;' ?></td>
                            <td class="request-address"><?= $row['address'] !== '' ? $h($row['address']) : '&mdash;' ?></td>
                            <td><?= $h($row['district_name']) ?></td>
                            <td><?= $h($row['division_name']) ?></td>
                            <td class="request-aid-cell">
                                <div class="request-aid-content">
                                    <strong><?= $h(widmsAidItemName((string)$row['item_name']) . ($row['variety'] ? ' / ' . $row['variety'] : '') . ' × ' . $row['quantity']) ?></strong>
                                    <?php if ($row['spectacle_category_name'] !== null): ?><small><?= $h(t('Spectacle Type')) ?>: <?= $h(t((string) $row['spectacle_category_name'])) ?></small><?php endif; ?>
                                    <?php $details = aidRequestDetails($row); if ($details): ?>
                                    <button type="button" class="request-extra-info-button" data-request-extra-info="<?= $h(json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>" data-dialog-title="<?= $h(t('Beneficiary Details')) ?>" data-close-label="<?= $h(t('Close')) ?>"><?= $h(t('View details')) ?></button>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="request-approvals-cell">
                                <span class="approval-icon-set" title="<?= $h(t('Official Approvals')) ?>">
                                    <span title="<?= $h(t('Government Medical Officer')) ?>"><?= !empty($row['medical_officer_approved']) ? '🩺' : '❌' ?></span>
                                    <span title="<?= $h(t('Grama Niladhari')) ?>"><?= !empty($row['grama_niladhari_approved']) ? '🏡' : '❌' ?></span>
                                    <span title="<?= $h(t('Social Services Officer')) ?>"><?= !empty($row['social_services_approved']) ? '🏛' : '❌' ?></span>
                                    <span title="<?= $h(t('Divisional Secretary')) ?>"><?= !empty($row['divisional_secretary_approved']) ? '📋' : '❌' ?></span>
                                </span>
                            </td>
                            <td class="request-submitted-cell"><?= $h(date('d M Y, H:i', strtotime((string) ($row['distributed_at'] ?: $row['released_at'] ?: $row['release_created_at'])))) ?></td>
                            <td class="request-status-cell"><span class="request-status-pill status-<?= $h($displayStatus) ?>"><?= $h(t(ucwords(str_replace('-', ' ', $displayStatus)))) ?></span></td>
                            <td class="request-notes-cell"><?= $row['notes'] !== null && trim((string) $row['notes']) !== '' ? $h($row['notes']) : '&mdash;' ?></td>
                            <td class="request-action-cell">
                                <?php if ($row['release_status'] === 'released-to-admin'): ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= $h(csrfToken()) ?>">
                                    <input type="hidden" name="release_id" value="<?= (int) $row['release_id'] ?>">
                                    <button class="admin-primary-action" type="submit"><?= $h(t('Confirm Beneficiary Distribution')) ?></button>
                                </form>
                                <?php else: ?>&mdash;<?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
<script src="assets/js/admin-dashboard.js"></script>
</body>
</html>
