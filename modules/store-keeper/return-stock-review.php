<?php
declare(strict_types=1);

requireRole('store-keeper');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/activity.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_once __DIR__ . '/../../includes/ui-messages.php';

$activePage = 'return-stock-review';
$database = database();
$errors = [];
$success = (string) ($_SESSION['flash_success'] ?? '');
unset($_SESSION['flash_success']);
$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $returnId = filter_input(INPUT_POST, 'return_id', FILTER_VALIDATE_INT);
    $decision = (string) ($_POST['decision'] ?? '');
    $note = trim((string) ($_POST['stock_review_note'] ?? ''));
    if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? '')) || !$returnId || !in_array($decision, ['accept', 'reject'], true)) {
        $errors[] = t('Invalid return review or expired session.');
    } elseif ($decision === 'reject' && ($note === '' || mb_strlen($note) > 500)) {
        $errors[] = t('Enter a rejection reason of no more than 500 characters.');
    } else {
        try {
            $database->beginTransaction();
            $statement = $database->prepare(
                "SELECT ret.id, ret.quantity, ret.processed_by, distribution.item_id
                 FROM item_returns ret
                 JOIN distributions distribution ON distribution.id = ret.distribution_id
                 WHERE ret.id = :id AND ret.stock_review_status = 'pending'
                   AND ret.restore_to = 'central-stock' AND ret.item_condition = 'good'
                 FOR UPDATE"
            );
            $statement->execute(['id' => $returnId]);
            $record = $statement->fetch();
            if (!$record) {
                throw new RuntimeException(t('This return has already been reviewed or is unavailable.'));
            }
            if ($decision === 'accept') {
                $statement = $database->prepare('UPDATE inventory_items SET quantity = quantity + :quantity WHERE id = :item_id');
                $statement->execute(['quantity' => (int) $record['quantity'], 'item_id' => (int) $record['item_id']]);
                if ($statement->rowCount() !== 1) {
                    throw new RuntimeException(t('The item stock could not be updated.'));
                }
            }
            $statement = $database->prepare(
                'UPDATE item_returns SET stock_review_status = :status, stock_reviewed_by = :reviewer,
                    stock_reviewed_at = NOW(), stock_review_note = :note WHERE id = :id AND stock_review_status = "pending"'
            );
            $statement->execute([
                'status' => $decision === 'accept' ? 'accepted' : 'rejected',
                'reviewer' => $userId,
                'note' => $note !== '' ? $note : null,
                'id' => $returnId,
            ]);
            if ($statement->rowCount() !== 1) {
                throw new RuntimeException(t('This return has already been reviewed or is unavailable.'));
            }
            notifyUser($database, (int) $record['processed_by'], 'return-review-result-' . $returnId,
                'Return stock review', $decision === 'accept' ? 'Return accepted' : 'Return rejected',
                'RET-' . str_pad((string) $returnId, 4, '0', STR_PAD_LEFT)
                    . ($decision === 'accept' ? ' was accepted into Central Stock.' : ' was rejected by the Store Keeper. Reason: ' . $note),
                'dashboard.php?page=return-history');
            $database->commit();
            logActivity('Returns', $decision === 'accept' ? 'Accepted good return into Central Stock' : 'Rejected good return for Central Stock',
                'RET-' . str_pad((string) $returnId, 4, '0', STR_PAD_LEFT), $note);
            $_SESSION['flash_success'] = $decision === 'accept'
                ? t('Return accepted and Central Stock updated.')
                : t('Return rejected. Central Stock was not changed.');
            unset($_SESSION['csrf_token']);
            header('Location: dashboard.php?page=return-stock-review');
            exit;
        } catch (Throwable $exception) {
            if ($database->inTransaction()) $database->rollBack();
            error_log($exception->getMessage());
            $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : t('Unable to review this return.');
        }
    }
}

try {
    $statement = $database->query(
        "SELECT ret.id, ret.quantity, ret.processed_at, ret.stock_review_status,
                ret.stock_reviewed_at, ret.stock_review_note, ret.returned_by_name,
                item.item_name, item.variety, spectacle_type.name AS spectacle_category_name,
                beneficiary.full_name AS beneficiary_name,
                processor.full_name AS received_by, reviewer.full_name AS reviewed_by
         FROM item_returns ret
         JOIN distributions distribution ON distribution.id = ret.distribution_id
         LEFT JOIN aid_requests aid_request ON aid_request.id = distribution.aid_request_id
         LEFT JOIN spectacle_categories spectacle_type ON spectacle_type.id = aid_request.spectacle_category_id
         JOIN inventory_items item ON item.id = distribution.item_id
         JOIN beneficiaries beneficiary ON beneficiary.id = distribution.beneficiary_id
         JOIN users processor ON processor.id = ret.processed_by
         LEFT JOIN users reviewer ON reviewer.id = ret.stock_reviewed_by
         WHERE ret.restore_to = 'central-stock' AND ret.item_condition = 'good'
         ORDER BY (ret.stock_review_status = 'pending') DESC, ret.id DESC"
    );
    $returns = $statement->fetchAll();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $returns = [];
    $errors[] = t('Return review history is unavailable. Run database migrations.');
}
$pendingCount = count(array_filter($returns, static fn(array $row): bool => $row['stock_review_status'] === 'pending'));
?>
<!doctype html>
<html lang="<?= htmlspecialchars(widmsLanguage(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars(t('Return Stock Review'), ENT_QUOTES, 'UTF-8') ?> | SWPCS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/admin-dashboard.css') ?>" rel="stylesheet">
</head>
<body class="store-page return-page-body">
<?php require __DIR__ . '/../../includes/store-keeper-sidebar.php'; ?>
<div class="admin-shell"><header class="topbar"><div class="d-flex align-items-center gap-3"><button class="menu-button" id="menu-button" type="button" aria-label="<?= htmlspecialchars(t('Open navigation'), ENT_QUOTES, 'UTF-8') ?>">&#9776;</button><h1><?= htmlspecialchars(t('Return Stock Review'), ENT_QUOTES, 'UTF-8') ?></h1></div></header>
<main class="dashboard-content return-management-page widms-unified-ui">
    <?php renderSuccessMessage($success); ?>
    <?php if ($errors): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars(implode(' ', $errors), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <section class="admin-data-card return-workflow-card return-history-card return-stock-review-card">
        <div class="return-history-filters" role="search" aria-label="<?= htmlspecialchars(t('Filter return history'), ENT_QUOTES, 'UTF-8') ?>">
            <label for="return-review-search"><span><?= htmlspecialchars(t('Search'), ENT_QUOTES, 'UTF-8') ?></span><input id="return-review-search" type="search" placeholder="<?= htmlspecialchars(t('Search return ID, beneficiary or aid item'), ENT_QUOTES, 'UTF-8') ?>"></label>
            <label for="return-review-status"><span><?= htmlspecialchars(t('Status'), ENT_QUOTES, 'UTF-8') ?></span><select id="return-review-status"><option value=""><?= htmlspecialchars(t('All statuses'), ENT_QUOTES, 'UTF-8') ?></option><option value="pending"><?= htmlspecialchars(t('Pending'), ENT_QUOTES, 'UTF-8') ?></option><option value="accepted"><?= htmlspecialchars(t('Accepted'), ENT_QUOTES, 'UTF-8') ?></option><option value="rejected"><?= htmlspecialchars(t('Rejected'), ENT_QUOTES, 'UTF-8') ?></option><option value="legacy"><?= htmlspecialchars(t('Previously Restocked'), ENT_QUOTES, 'UTF-8') ?></option></select></label>
            <span class="return-review-count"><?= $pendingCount ?> <?= htmlspecialchars(t('pending'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="admin-data-table-wrap"><table class="admin-data-table"><thead><tr>
            <th><?= htmlspecialchars(t('Return ID'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Beneficiary'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Aid Item'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Quantity'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Received By'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Date'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Status'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Action'), ENT_QUOTES, 'UTF-8') ?></th>
        </tr></thead><tbody>
            <?php if (!$returns): ?><tr><td colspan="8" class="admin-empty-row"><?= htmlspecialchars(t('No returns recorded yet.'), ENT_QUOTES, 'UTF-8') ?></td></tr><?php endif; ?>
            <?php foreach ($returns as $return): $status = (string) $return['stock_review_status']; $legacy = $status === 'accepted' && $return['stock_reviewed_at'] === null && $return['stock_reviewed_by'] === null; $displayStatus = $legacy ? 'legacy' : $status; ?>
                <tr class="return-review-row" data-status="<?= htmlspecialchars($displayStatus, ENT_QUOTES, 'UTF-8') ?>">
                    <td><strong>RET-<?= str_pad((string) $return['id'], 4, '0', STR_PAD_LEFT) ?></strong></td>
                    <td><?= htmlspecialchars((string) $return['beneficiary_name'], ENT_QUOTES, 'UTF-8') ?><small><?= htmlspecialchars((string) $return['returned_by_name'], ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars(t('returned this item'), ENT_QUOTES, 'UTF-8') ?></small></td>
                    <td><?= htmlspecialchars(widmsAidItemName((string) $return['item_name']) . ((string) $return['variety'] !== '' ? ' — ' . $return['variety'] : ''), ENT_QUOTES, 'UTF-8') ?><?php if ($return['spectacle_category_name'] !== null): ?><small><?= htmlspecialchars(t('Spectacle Type'), ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars(t((string) $return['spectacle_category_name']), ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?></td>
                    <td><?= (int) $return['quantity'] ?></td><td><?= htmlspecialchars((string) $return['received_by'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= date('d M Y, H:i', strtotime((string) $return['processed_at'])) ?></td>
                    <td><span class="return-review-pill return-review-<?= htmlspecialchars($displayStatus, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t($legacy ? 'Previously Restocked' : ucfirst($status)), ENT_QUOTES, 'UTF-8') ?></span><?php if ($legacy): ?><small><?= htmlspecialchars(t('Recorded before Store Keeper review was required.'), ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?><?php if ($return['stock_reviewed_at']): ?><small><?= htmlspecialchars((string) ($return['reviewed_by'] ?? ''), ENT_QUOTES, 'UTF-8') ?> · <?= date('d M Y, H:i', strtotime((string) $return['stock_reviewed_at'])) ?></small><?php endif; ?><?php if ($return['stock_review_note']): ?><small><?= htmlspecialchars((string) $return['stock_review_note'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?></td>
                    <td><?php if ($status === 'pending'): ?><div class="return-review-actions">
                        <form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="return_id" value="<?= (int) $return['id'] ?>"><input type="hidden" name="decision" value="accept"><button class="admin-primary-action" type="submit"><?= htmlspecialchars(t('Accept'), ENT_QUOTES, 'UTF-8') ?></button></form>
                        <form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="return_id" value="<?= (int) $return['id'] ?>"><input type="hidden" name="stock_review_note" value=""><button class="return-review-reject" type="button" data-reason-trigger data-submit-name="decision" data-submit-value="reject" data-reason-field="stock_review_note" data-dialog-title="<?= htmlspecialchars(t('Reject returned stock') . ' RET-' . str_pad((string) $return['id'], 4, '0', STR_PAD_LEFT), ENT_QUOTES, 'UTF-8') ?>" data-dialog-confirm="<?= htmlspecialchars(t('Confirm rejection'), ENT_QUOTES, 'UTF-8') ?>" data-reason-label="<?= htmlspecialchars(t('Reason for rejection'), ENT_QUOTES, 'UTF-8') ?>" data-reason-required="<?= htmlspecialchars(t('Enter a reason before rejecting this return.'), ENT_QUOTES, 'UTF-8') ?>" data-cancel-label="<?= htmlspecialchars(t('Cancel'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t('Reject'), ENT_QUOTES, 'UTF-8') ?></button></form>
                    </div><?php else: ?>—<?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($returns): ?><tr id="return-review-no-results" hidden><td colspan="8" class="admin-empty-row"><?= htmlspecialchars(t('No returns match these filters.'), ENT_QUOTES, 'UTF-8') ?></td></tr><?php endif; ?>
        </tbody></table></div>
    </section>
</main></div>
<script src="assets/js/admin-dashboard.js"></script>
<script src="assets/js/admin-reason-dialog.js?v=<?= filemtime(__DIR__ . '/../../public/assets/js/admin-reason-dialog.js') ?>"></script>
<script>(()=>{const search=document.getElementById('return-review-search'),status=document.getElementById('return-review-status'),rows=[...document.querySelectorAll('.return-review-row')],empty=document.getElementById('return-review-no-results');if(!search||!status||!empty)return;const filter=()=>{let visible=0;for(const row of rows){row.hidden=!!((status.value&&row.dataset.status!==status.value)||(search.value.trim()&&!row.textContent.toLocaleLowerCase().includes(search.value.trim().toLocaleLowerCase())));if(!row.hidden)visible++}empty.hidden=visible!==0};search.addEventListener('input',filter);status.addEventListener('change',filter)})();</script>
</body></html>
