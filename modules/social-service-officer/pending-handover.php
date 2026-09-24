<?php

declare(strict_types=1);

requireRole('social-service-officer');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/activity.php';

$activePage = 'pending-handover';
$database = database();
$userId = (int) $_SESSION['user_id'];
$errors = [];
$success = (string) ($_SESSION['flash_success'] ?? '');
unset($_SESSION['flash_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $handoverId = filter_input(INPUT_POST, 'handover_id', FILTER_VALIDATE_INT);
    if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Your session expired.';
    }
    if (!$handoverId) {
        $errors[] = 'Invalid handover.';
    }

    if ($errors === []) {
        try {
            $database->beginTransaction();
            $statement = $database->prepare(
                "SELECT f.id, f.aid_request_id, ar.beneficiary_id, ar.item_id, ar.quantity
                 FROM goods_fulfillments f
                 JOIN aid_requests ar ON ar.id = f.aid_request_id
                 WHERE f.id = :id AND f.sso_id = :user AND f.status = 'pending-sso-handover'
                   AND ar.status = 'approved'
                   AND NOT EXISTS (SELECT 1 FROM distributions d WHERE d.aid_request_id = ar.id)
                 FOR UPDATE"
            );
            $statement->execute(['id' => $handoverId, 'user' => $userId]);
            $handover = $statement->fetch();
            if (!$handover) {
                throw new RuntimeException('This aid handover is no longer pending.');
            }

            $database->prepare(
                "INSERT INTO distributions
                    (aid_request_id, beneficiary_id, item_id, quantity, distribution_type, source, distributed_by)
                 VALUES
                    (:aid, :beneficiary, :item, :quantity, 'request-based', 'officer-pool', :user)"
            )->execute([
                'aid' => $handover['aid_request_id'],
                'beneficiary' => $handover['beneficiary_id'],
                'item' => $handover['item_id'],
                'quantity' => $handover['quantity'],
                'user' => $userId,
            ]);
            $statement = $database->prepare("UPDATE goods_fulfillments SET status = 'distributed', distributed_at = NOW() WHERE id = :id AND status = 'pending-sso-handover'");
            $statement->execute(['id' => $handoverId]);
            if ($statement->rowCount() !== 1) {
                throw new RuntimeException('This aid handover is no longer pending.');
            }
            $statement = $database->prepare("UPDATE aid_requests SET status = 'distributed' WHERE id = :id AND status = 'approved'");
            $statement->execute(['id' => $handover['aid_request_id']]);
            if ($statement->rowCount() !== 1) {
                throw new RuntimeException('The approved request changed. Refresh and try again.');
            }
            $database->commit();

            $reference = 'FUL-' . str_pad((string) $handoverId, 4, '0', STR_PAD_LEFT);
            logActivity('SSO Handover', 'Completed final beneficiary delivery', $reference, 'distributed');
            $_SESSION['flash_success'] = 'Final delivery marked as distributed.';
            unset($_SESSION['csrf_token']);
            header('Location: dashboard.php?page=pending-handover');
            exit;
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            error_log($exception->getMessage());
            $errors[] = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'Unable to complete handover.';
        }
    }
}

try {
    $statement = $database->prepare(
        "SELECT f.id, COALESCE(f.lens_unit_identifier, CONCAT('FUL-', LPAD(f.id, 4, '0'))) reference,
                i.item_name, b.full_name, b.nic, b.address, so.full_name subject_name, f.status
         FROM goods_fulfillments f
         JOIN aid_requests ar ON ar.id = f.aid_request_id
         JOIN beneficiaries b ON b.id = ar.beneficiary_id
         JOIN inventory_items i ON i.id = ar.item_id
         JOIN users so ON so.id = f.subject_officer_id
         WHERE f.sso_id = :user
         ORDER BY CASE WHEN f.status = 'pending-sso-handover' THEN 0 ELSE 1 END, f.id DESC"
    );
    $statement->execute(['user' => $userId]);
    $rows = $statement->fetchAll();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $rows = [];
    $errors[] = 'Pending aid handovers are unavailable.';
}
$counts = array_count_values(array_column($rows, 'status'));
?>
<!doctype html>
<html lang="<?= htmlspecialchars(widmsLanguage(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(t('Pending Aid Handover'), ENT_QUOTES, 'UTF-8') ?> | WIDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css?v=85" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/../../includes/social-service-officer-sidebar.php'; ?>
<div class="admin-shell">
    <header class="topbar"><h1><?= htmlspecialchars(t('Pending Aid Handover'), ENT_QUOTES, 'UTF-8') ?></h1></header>
    <main class="dashboard-content">
        <?php if ($success !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if ($errors !== []): ?><div class="alert alert-danger"><?= htmlspecialchars(implode(' ', $errors), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <section class="handover-summary-grid">
            <article class="handover-summary-card pending"><p><?= htmlspecialchars(t('Pending Items'), ENT_QUOTES, 'UTF-8') ?></p><strong><?= (int) ($counts['pending-sso-handover'] ?? 0) ?></strong></article>
            <article class="handover-summary-card distributed"><p><?= htmlspecialchars(t('Distributed'), ENT_QUOTES, 'UTF-8') ?></p><strong><?= (int) ($counts['distributed'] ?? 0) ?></strong></article>
        </section>
        <section class="handover-list-card">
            <div class="handover-list-header"><h2><?= htmlspecialchars(t('Items Handed Over by Subject Officers'), ENT_QUOTES, 'UTF-8') ?></h2></div>
            <div class="handover-table-wrap">
                <table class="handover-table">
                    <thead><tr><th><?= htmlspecialchars(t('Reference'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Item'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Beneficiary'), ENT_QUOTES, 'UTF-8') ?></th><th>NIC</th><th><?= htmlspecialchars(t('Address'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Subject Officer'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Status'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Action'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead>
                    <tbody>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="8"><?= htmlspecialchars(t('No handovers assigned to you.'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr id="fulfillment-<?= (int) $row['id'] ?>">
                                <td><?= htmlspecialchars($row['reference'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($row['item_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($row['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($row['nic'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($row['address'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($row['subject_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars(ucwords(str_replace('-', ' ', $row['status'])), ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <?php if ($row['status'] === 'pending-sso-handover'): ?>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="handover_id" value="<?= (int) $row['id'] ?>">
                                            <button class="approve-button"><?= htmlspecialchars(t('Mark Distributed'), ENT_QUOTES, 'UTF-8') ?></button>
                                        </form>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
<script src="assets/js/admin-dashboard.js?v=26"></script>
</body>
</html>
