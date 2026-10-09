<?php
declare(strict_types=1);

requireRole('subject-officer');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/ui-messages.php';
require_once __DIR__ . '/../../includes/goods-request-actor.php';

$activePage = 'distribution-history';
$success = (string) ($_SESSION['flash_success'] ?? '');
unset($_SESSION['flash_success']);
$rows = [];
$error = '';

try {
    $statement = database()->prepare(
        "SELECT d.id, d.aid_request_id, d.quantity, d.distributed_at AS event_at,
                category.name AS spectacle_category_name, b.full_name AS beneficiary_name,
                b.nic, b.elders_card_number, i.item_name, i.variety,
                ds.name AS division_name, distributor.full_name AS distributor_name,
                distributor.username AS actor_username, distributor.role AS actor_role,
                EXISTS (SELECT 1 FROM item_returns ret WHERE ret.distribution_id = d.id AND ret.stock_review_status = 'accepted') AS has_return
         FROM distributions d
         JOIN aid_requests ar ON ar.id = d.aid_request_id
         JOIN beneficiaries b ON b.id = d.beneficiary_id
         JOIN inventory_items i ON i.id = d.item_id
         LEFT JOIN spectacle_categories category ON category.id = ar.spectacle_category_id
         JOIN ds_divisions ds ON ds.id = b.ds_division_id
         JOIN users distributor ON distributor.id = d.distributed_by
         WHERE d.distributed_by = :distributor_id
         ORDER BY d.distributed_at DESC, d.id DESC"
    );
    $statement->execute(['distributor_id' => (int) $_SESSION['user_id']]);
    foreach ($statement->fetchAll() as $row) {
        $row['event_type'] = 'distribution';
        $rows[] = $row;
    }

    $statement = database()->prepare(
        "SELECT f.id, f.aid_request_id, ar.quantity, f.handed_to_sso_at AS event_at,
                category.name AS spectacle_category_name, b.full_name AS beneficiary_name,
                b.nic, b.elders_card_number, i.item_name, i.variety,
                ds.name AS division_name, recipient.full_name AS recipient_name,
                recipient.username AS recipient_username, recipient.role AS recipient_role,
                actor.username AS actor_username, actor.role AS actor_role
         FROM goods_fulfillments f
         JOIN aid_requests ar ON ar.id = f.aid_request_id
         JOIN beneficiaries b ON b.id = ar.beneficiary_id
         JOIN inventory_items i ON i.id = ar.item_id
         LEFT JOIN spectacle_categories category ON category.id = ar.spectacle_category_id
         JOIN ds_divisions ds ON ds.id = b.ds_division_id
         LEFT JOIN users recipient ON recipient.id = f.sso_id
         LEFT JOIN users actor ON actor.id = COALESCE(f.handed_to_sso_by, f.subject_officer_id)
         WHERE COALESCE(f.handed_to_sso_by, f.subject_officer_id) = :subject_officer_id
           AND f.handed_to_sso_at IS NOT NULL"
    );
    $statement->execute(['subject_officer_id' => (int) $_SESSION['user_id']]);
    foreach ($statement->fetchAll() as $row) {
        $row['event_type'] = 'handover';
        $rows[] = $row;
    }
    usort($rows, static function (array $left, array $right): int {
        return strcmp((string) $right['event_at'], (string) $left['event_at'])
            ?: ((int) $right['id'] <=> (int) $left['id']);
    });
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $error = t('Distribution history is temporarily unavailable.');
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars(widmsLanguage(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars(t('Distribution History'), ENT_QUOTES, 'UTF-8') ?> | SWPCS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/admin-dashboard.css') ?>" rel="stylesheet">
    <link href="assets/css/distribution-history.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/distribution-history.css') ?>" rel="stylesheet">
</head>
<body class="subject-distribution-history-page">
<?php require __DIR__ . '/../../includes/subject-officer-sidebar.php'; ?>
<div class="admin-shell">
    <header class="topbar"><div class="d-flex align-items-center gap-3"><button class="menu-button" id="menu-button" type="button" aria-label="<?= htmlspecialchars(t('Open navigation'), ENT_QUOTES, 'UTF-8') ?>">&#9776;</button><h1><?= htmlspecialchars(t('Distribution History'), ENT_QUOTES, 'UTF-8') ?></h1></div></header>
    <main class="dashboard-content fulfillment-page widms-unified-ui">
        <?php renderSuccessMessage($success); ?>
        <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <section class="admin-data-card distribution-history-card">
            <div class="admin-data-table-wrap">
                <table class="admin-data-table fulfillment-table distribution-history-table">
                    <thead><tr>
                        <th><?= htmlspecialchars(t('Reference'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Beneficiary'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Aid Item'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Quantity'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Spectacle Type'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('DS Division'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Officer / Recipient'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Date'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars(t('Status'), ENT_QUOTES, 'UTF-8') ?></th>
                    </tr></thead>
                    <tbody>
                        <?php if ($rows === []): ?><tr><td colspan="9" class="admin-empty-row"><?= htmlspecialchars(t('No distributions or SSO handovers recorded yet.'), ENT_QUOTES, 'UTF-8') ?></td></tr><?php endif; ?>
                        <?php foreach ($rows as $row):
                            $eventId = (int) $row['id'];
                            $isHandover = $row['event_type'] === 'handover';
                            $item = widmsAidItemName((string) $row['item_name']) . ((string) $row['variety'] !== '' ? ' — ' . $row['variety'] : '');
                            $identifier = $row['nic'] ?: ($row['elders_card_number'] ?: t('No identification recorded'));
                        ?>
                            <tr id="<?= $isHandover ? 'handover' : 'distribution' ?>-<?= $eventId ?>" class="admin-notification-target <?= $isHandover ? 'is-handover' : (!empty($row['has_return']) ? 'is-returned' : 'is-distributed') ?>" tabindex="-1">
                                <td><strong><?= $isHandover ? 'FUL-' : 'DIST-' ?><?= str_pad((string) $eventId, 4, '0', STR_PAD_LEFT) ?></strong><small><a href="dashboard.php?page=my-aid-requests#aid-request-<?= (int) $row['aid_request_id'] ?>">AR-<?= str_pad((string) $row['aid_request_id'], 4, '0', STR_PAD_LEFT) ?></a></small></td>
                                <td><strong><?= htmlspecialchars((string) $row['beneficiary_name'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars((string) $identifier, ENT_QUOTES, 'UTF-8') ?></small></td>
                                <td><strong><?= htmlspecialchars($item, ENT_QUOTES, 'UTF-8') ?></strong></td>
                                <td><?= (int) $row['quantity'] ?></td>
                                <td><?= $row['spectacle_category_name'] !== null ? htmlspecialchars(t((string) $row['spectacle_category_name']), ENT_QUOTES, 'UTF-8') : '&mdash;' ?></td>
                                <td><?= htmlspecialchars((string) $row['division_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="goods-actor-cell"><strong><?= htmlspecialchars((string) ($row['actor_username'] ?: 'User not recorded'), ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars(t(widmsGoodsRequestRoleLabel((string) ($row['actor_role'] ?? ''))), ENT_QUOTES, 'UTF-8') ?></small><?php if ($isHandover): ?><small><?= htmlspecialchars(t('To SSO') . ': ' . (string) ($row['recipient_username'] ?: 'User not recorded'), ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?></td>
                                <td><?= htmlspecialchars(date('d M Y, H:i', strtotime((string) $row['event_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="goods-status-pill <?= $isHandover ? 'status-handover' : (!empty($row['has_return']) ? 'status-returned' : 'status-distributed') ?>"><?= htmlspecialchars(t($isHandover ? 'Handed to SSO' : (!empty($row['has_return']) ? 'Returned' : 'Distributed')), ENT_QUOTES, 'UTF-8') ?></span></td>
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
