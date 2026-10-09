<?php
declare(strict_types=1);

requireRole('subject-officer');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/activity.php';
require_once __DIR__ . '/../../includes/ui-messages.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_once __DIR__ . '/../../includes/beneficiary-division-guard.php';

function notifyDivisionSsosOfDistribution(PDO $database, int $divisionId, int $requestId, int $distributionId, int $excludeUserId = 0): void
{
    $statement = $database->prepare("SELECT id FROM users WHERE role = 'social-service-officer' AND status = 'active' AND ds_division_id = :division_id");
    $statement->execute(['division_id' => $divisionId]);
    foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $officerId) {
        if ((int) $officerId === $excludeUserId) {
            continue;
        }
        notifyUser(
            $database,
            (int) $officerId,
            'division-aid-distributed-' . $distributionId . '-' . $officerId,
            'Aid distributed in your division',
            'AR-' . str_pad((string) $requestId, 4, '0', STR_PAD_LEFT),
            'The approved aid was distributed to a beneficiary in your DS division.',
            'dashboard.php?page=aid-requests#aid-request-' . $requestId
        );
    }
}

$activePage = 'distribute-items';
$database = database();
$userId = (int) $_SESSION['user_id'];
$errors = [];
$success = (string) ($_SESSION['flash_success'] ?? '');
unset($_SESSION['flash_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fulfillmentId = filter_input(INPUT_POST, 'fulfillment_id', FILTER_VALIDATE_INT);
    $action = trim((string) ($_POST['action'] ?? ''));
    $ssoId = filter_input(INPUT_POST, 'sso_id', FILTER_VALIDATE_INT);

    if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = t('Your session expired. Please try again.');
    }
    if (!$fulfillmentId || !in_array($action, ['distribute', 'handover'], true)) {
        $errors[] = t('Invalid fulfillment action.');
    }
    if ($action === 'handover' && !$ssoId) {
        $errors[] = t('Select the beneficiary division SSO.');
    }

    if (!$errors) {
        try {
            $database->beginTransaction();
            $statement = $database->prepare(
                "SELECT f.id, f.aid_request_id, f.status,
                        ar.beneficiary_id, ar.item_id, ar.quantity, i.can_sso_distribute,
                        b.ds_division_id
                 FROM goods_fulfillments f
                 JOIN aid_requests ar ON ar.id = f.aid_request_id
                 JOIN inventory_items i ON i.id = ar.item_id
                 JOIN beneficiaries b ON b.id = ar.beneficiary_id
                 WHERE f.id = :id
                   AND f.status = 'with-subject-officer'
                   AND ar.status = 'approved'
                 FOR UPDATE"
            );
            $statement->execute(['id' => $fulfillmentId]);
            $fulfillment = $statement->fetch();
            if (!$fulfillment) {
                throw new RuntimeException(t('This item is no longer available for distribution.'));
            }
            assertBeneficiaryRecordDivision($database, (int)$fulfillment['beneficiary_id']);

            if ($action === 'handover') {
                if ((int) $fulfillment['can_sso_distribute'] !== 1) {
                    throw new RuntimeException(t('This aid must be distributed by the Subject Officer.'));
                }
                $statement = $database->prepare(
                    "SELECT id FROM users
                     WHERE id = :sso_id
                       AND role = 'social-service-officer'
                       AND status = 'active'
                       AND ds_division_id = :division_id"
                );
                $statement->execute([
                    'sso_id' => $ssoId,
                    'division_id' => (int) $fulfillment['ds_division_id'],
                ]);
                if (!$statement->fetchColumn()) {
                    throw new RuntimeException(t('The selected SSO is not assigned to the beneficiary division.'));
                }

                $statement = $database->prepare(
                    "UPDATE goods_fulfillments
                     SET status = 'pending-sso-handover',
                         sso_id = :sso_id,
                         handed_to_sso_at = NOW(),
                         handed_to_sso_by = :actor_id
                     WHERE id = :id AND status = 'with-subject-officer'"
                );
                $statement->execute(['sso_id' => $ssoId, 'actor_id' => $userId, 'id' => $fulfillmentId]);
                if ($statement->rowCount() !== 1) {
                    throw new RuntimeException(t('This item is no longer available for distribution.'));
                }
                $message = 'Item handed to the division SSO.';
            } else {
                $statement = $database->prepare(
                    "INSERT INTO distributions
                        (aid_request_id, beneficiary_id, ds_division_id, item_id, quantity,
                         distribution_type, source, distributed_by)
                     VALUES
                        (:aid_request_id, :beneficiary_id, :division_id, :item_id, :quantity,
                         'request-based', 'officer-pool', :distributed_by)"
                );
                $statement->execute([
                    'aid_request_id' => (int) $fulfillment['aid_request_id'],
                    'beneficiary_id' => (int) $fulfillment['beneficiary_id'],
                    'division_id' => (int) $fulfillment['ds_division_id'],
                    'item_id' => (int) $fulfillment['item_id'],
                    'quantity' => (int) $fulfillment['quantity'],
                    'distributed_by' => $userId,
                ]);
                $distributionId = (int) $database->lastInsertId();

                $statement = $database->prepare(
                    "UPDATE goods_fulfillments
                     SET status = 'distributed', distributed_at = NOW()
                     WHERE id = :id AND status = 'with-subject-officer'"
                );
                $statement->execute(['id' => $fulfillmentId]);
                if ($statement->rowCount() !== 1) {
                    throw new RuntimeException(t('This item is no longer available for distribution.'));
                }

                $statement = $database->prepare(
                    "UPDATE aid_requests SET status = 'distributed'
                     WHERE id = :id AND status = 'approved'"
                );
                $statement->execute(['id' => (int) $fulfillment['aid_request_id']]);
                if ($statement->rowCount() !== 1) {
                    throw new RuntimeException(t('The aid request is no longer ready for distribution.'));
                }
                notifyDivisionSsosOfDistribution($database, (int) $fulfillment['ds_division_id'], (int) $fulfillment['aid_request_id'], $distributionId);
                $message = 'Item distributed directly to the beneficiary.';
            }

            $database->commit();
            logActivity(
                'Goods Fulfillment',
                $message,
                'FUL-' . str_pad((string) $fulfillmentId, 4, '0', STR_PAD_LEFT),
                $action
            );
            $_SESSION['flash_success'] = $message;
            unset($_SESSION['csrf_token']);
            header('Location: dashboard.php?page=distribution-history#' . ($action === 'distribute' ? 'distribution-' . $distributionId : 'handover-' . $fulfillmentId));
            exit;
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            error_log($exception->getMessage());
            $errors[] = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : t('Unable to process this item. Please try again.');
        }
    }
}

try {
    $statement = $database->prepare(
        "SELECT f.id, f.goods_request_id, f.aid_request_id, f.status, f.lens_unit_identifier,
                ar.quantity, category.name AS spectacle_category_name,
                b.full_name, b.nic, b.elders_card_number, b.address, b.ds_division_id,
                i.item_name, i.variety, i.can_sso_distribute, ds.name AS division_name,
                recipient.username AS recipient_username, recipient.role AS recipient_role
         FROM goods_fulfillments f
         JOIN aid_requests ar ON ar.id = f.aid_request_id
         JOIN beneficiaries b ON b.id = ar.beneficiary_id
         JOIN inventory_items i ON i.id = ar.item_id
         LEFT JOIN spectacle_categories category ON category.id = ar.spectacle_category_id
         JOIN ds_divisions ds ON ds.id = b.ds_division_id
         LEFT JOIN users recipient ON recipient.id = f.subject_officer_id
         WHERE f.status = 'with-subject-officer'
           AND ar.status = 'approved'
         ORDER BY f.id DESC"
    );
    $statement->execute();
    $fulfillments = $statement->fetchAll();

    $officerRows = $database->query(
        "SELECT id, full_name, ds_division_id
         FROM users
         WHERE role = 'social-service-officer' AND status = 'active'
         ORDER BY full_name"
    )->fetchAll();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $fulfillments = [];
    $officerRows = [];
    $errors[] = t('The fulfillment queue is temporarily unavailable.');
}

$officersByDivision = [];
foreach ($officerRows as $officer) {
    $officersByDivision[(int) $officer['ds_division_id']][] = $officer;
}

$statusLabels = [
    'with-subject-officer' => 'Ready to Distribute',
    'distributed' => 'Distributed',
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars(t('Final Distribution and SSO Handover'), ENT_QUOTES, 'UTF-8') ?> | SWPCS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/admin-dashboard.css') ?>" rel="stylesheet">
    <link href="assets/css/approved-aid-bundles.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/approved-aid-bundles.css') ?>" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/../../includes/subject-officer-sidebar.php'; ?>
<div class="admin-shell">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="menu-button" id="menu-button" type="button" aria-label="<?= htmlspecialchars(t('Open navigation'), ENT_QUOTES, 'UTF-8') ?>">&#9776;</button>
            <div>
                <h1><?= htmlspecialchars(t('Final Distribution and SSO Handover'), ENT_QUOTES, 'UTF-8') ?></h1>
            </div>
        </div>
    </header>

    <main class="dashboard-content fulfillment-page widms-unified-ui">
        <?php renderSuccessMessage($success); ?>
        <?php if ($errors): ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars(implode(' ', $errors), ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <section class="admin-data-card">
            <?php if (!$fulfillments): ?>
                <div class="aid-bundle-empty"><?= htmlspecialchars(t('No released items are waiting for distribution.'), ENT_QUOTES, 'UTF-8') ?></div>
            <?php else: ?>
                <div class="aid-bundle-grid released-aid-grid">
                    <?php foreach ($fulfillments as $fulfillment):
                        $rowId = (int) $fulfillment['id'];
                        $status = (string) $fulfillment['status'];
                        $divisionOfficers = $officersByDivision[(int) $fulfillment['ds_division_id']] ?? [];
                        $itemLabel = widmsAidItemName((string) $fulfillment['item_name']) . (trim((string) $fulfillment['variety']) !== '' ? ' — ' . $fulfillment['variety'] : '');
                        $identification = $fulfillment['nic'] ?: ($fulfillment['elders_card_number'] ?: t('No identification recorded'));
                    ?>
                        <article id="fulfillment-<?= $rowId ?>" class="aid-bundle-beneficiary-card released-aid-card admin-notification-target" tabindex="-1">
                            <div class="aid-bundle-top"><span class="aid-bundle-ref">AR-<?= str_pad((string) $fulfillment['aid_request_id'], 4, '0', STR_PAD_LEFT) ?> · FUL-<?= str_pad((string) $rowId, 4, '0', STR_PAD_LEFT) ?></span><span class="goods-status-pill status-<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t($statusLabels[$status] ?? ucwords(str_replace('-', ' ', $status))), ENT_QUOTES, 'UTF-8') ?></span></div>
                            <strong class="aid-bundle-name"><?= htmlspecialchars((string) $fulfillment['full_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <span class="aid-bundle-id"><?= htmlspecialchars((string) $identification, ENT_QUOTES, 'UTF-8') ?></span>
                            <div class="aid-stock-comparison">
                                <div class="aid-stock-metric aid-stock-requested"><span><?= htmlspecialchars(t('Requested aid'), ENT_QUOTES, 'UTF-8') ?></span><strong><?= htmlspecialchars($itemLabel, ENT_QUOTES, 'UTF-8') ?></strong><?php if ($fulfillment['spectacle_category_name'] !== null): ?><small><?= htmlspecialchars(t('Spectacle Type'), ENT_QUOTES, 'UTF-8') ?>: <b><?= htmlspecialchars(t((string) $fulfillment['spectacle_category_name']), ENT_QUOTES, 'UTF-8') ?></b></small><?php endif; ?><small><?= (int) $fulfillment['quantity'] ?> <?= htmlspecialchars(t('units'), ENT_QUOTES, 'UTF-8') ?></small></div>
                                <div class="aid-stock-metric aid-stock-matched"><span><?= htmlspecialchars(t('Released to Subject Officer'), ENT_QUOTES, 'UTF-8') ?></span><strong><?= (int) $fulfillment['quantity'] ?> <?= htmlspecialchars(t('units'), ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars(t('Ready for beneficiary'), ENT_QUOTES, 'UTF-8') ?></small></div>
                            </div>
                            <span class="aid-bundle-location"><?= htmlspecialchars((string) $fulfillment['division_name'] . ' · ' . (string) $fulfillment['address'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if (!empty($fulfillment['recipient_username'])): ?>
                                <span class="subject-actor-label"><small><?= htmlspecialchars(t('Released to'), ENT_QUOTES, 'UTF-8') ?></small><strong><?= htmlspecialchars((string) $fulfillment['recipient_username'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars(t('Subject Officer'), ENT_QUOTES, 'UTF-8') ?></small></span>
                            <?php endif; ?>
                            <?php if ($status === 'with-subject-officer'): ?>
                                <form method="post" class="released-aid-actions">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="fulfillment_id" value="<?= $rowId ?>">
                                    <button type="submit" name="action" value="distribute" class="approve-button"><?= htmlspecialchars(t('Distribute to Beneficiary'), ENT_QUOTES, 'UTF-8') ?></button>
                                    <?php if ((int) $fulfillment['can_sso_distribute'] === 1): ?>
                                    <div class="fulfillment-handover-controls">
                                        <label for="sso-<?= $rowId ?>" class="visually-hidden"><?= htmlspecialchars(t('Division SSO'), ENT_QUOTES, 'UTF-8') ?></label>
                                        <select id="sso-<?= $rowId ?>" name="sso_id" <?= !$divisionOfficers ? 'disabled' : '' ?>><option value=""><?= htmlspecialchars(t('Select division SSO'), ENT_QUOTES, 'UTF-8') ?></option><?php foreach ($divisionOfficers as $officer): ?><option value="<?= (int) $officer['id'] ?>"><?= htmlspecialchars((string) $officer['full_name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select>
                                        <button type="submit" name="action" value="handover" class="admin-primary-action" <?= !$divisionOfficers ? 'disabled' : '' ?>><?= htmlspecialchars(t('Hand to SSO'), ENT_QUOTES, 'UTF-8') ?></button>
                                    </div>
                                    <?php if (!$divisionOfficers): ?><small class="fulfillment-warning"><?= htmlspecialchars(t('No active SSO is assigned to this division.'), ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
                                    <?php else: ?><small class="fulfillment-warning"><?= htmlspecialchars(t('This aid must be distributed by the Subject Officer.'), ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
                                </form>
                            <?php endif; ?>
                            <a class="outline-action" target="_blank" rel="noopener" href="dashboard.php?page=goods-request-document&amp;request_id=<?= (int) $fulfillment['goods_request_id'] ?>&amp;print=1"><?= htmlspecialchars(t('View PDF'), ENT_QUOTES, 'UTF-8') ?></a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>
<script src="assets/js/admin-dashboard.js"></script>
</body>
</html>
