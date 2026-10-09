<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/activity.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_once __DIR__ . '/../../includes/ui-messages.php';

$role = (string) ($_SESSION['role'] ?? '');
if (!in_array($role, ['subject-officer', 'social-service-officer'], true)) {
    http_response_code(403);
    exit('Access denied.');
}

$isSubject = $role === 'subject-officer';
$isHistory = (string) ($_GET['page'] ?? '') === 'return-history';
$activePage = $isHistory ? 'return-history' : ($isSubject ? 'returns' : 'process-return');
$sidebar = $isSubject
    ? __DIR__ . '/../../includes/subject-officer-sidebar.php'
    : __DIR__ . '/../../includes/social-service-officer-sidebar.php';
$userId = (int) $_SESSION['user_id'];
$database = database();
$errors = [];
$success = (string) ($_SESSION['flash_success'] ?? '');
unset($_SESSION['flash_success']);

if (!$isHistory && $_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === '') {
    $distributionId = filter_input(INPUT_POST, 'distribution_id', FILTER_VALIDATE_INT)
        ?: filter_var($_POST['distribution_id'] ?? null, FILTER_VALIDATE_INT);
    $selectedItemId = filter_input(INPUT_POST, 'aid_item_id', FILTER_VALIDATE_INT)
        ?: filter_var($_POST['aid_item_id'] ?? null, FILTER_VALIDATE_INT);
    $selectedBeneficiaryId = filter_input(INPUT_POST, 'beneficiary_id', FILTER_VALIDATE_INT)
        ?: filter_var($_POST['beneficiary_id'] ?? null, FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT)
        ?: filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);
    $condition = (string) ($_POST['condition'] ?? '');
    $returnedByName = trim((string) ($_POST['returned_by_name'] ?? ''));
    if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = t('Your session expired. Please refresh the page and try again.');
    }
    if (!$distributionId || !$selectedItemId || !$selectedBeneficiaryId || !$quantity || $quantity < 1 || !in_array($condition, ['good', 'damaged', 'unusable'], true)) {
        $errors[] = t('Complete all return details.');
    }
    if ($returnedByName === '' || mb_strlen($returnedByName) > 150) {
        $errors[] = t('Enter the name of the person who returned the item.');
    }

    if ($errors === []) {
        try {
            $database->beginTransaction();
            // SSO returns follow the beneficiary's assigned division, not the original issuer.
            // Subject Officers can receive returnable aid from every division.
            $statement = $database->prepare(
                'SELECT distribution.id, distribution.item_id, distribution.beneficiary_id, distribution.quantity,
                        item.is_returnable,
                        (SELECT COALESCE(SUM(ret.quantity), 0)
                         FROM item_returns ret WHERE ret.distribution_id = distribution.id
                           AND ret.stock_review_status <> \'rejected\') AS already_returned
                 FROM distributions distribution
                 JOIN inventory_items item ON item.id = distribution.item_id
                 JOIN beneficiaries beneficiary ON beneficiary.id = distribution.beneficiary_id
                 LEFT JOIN aid_requests aid_request ON aid_request.id = distribution.aid_request_id
                 JOIN users receiver ON receiver.id = :user_id
                    AND receiver.role = :actor_role AND receiver.status = "active"
                 WHERE distribution.id = :id
                   AND distribution.distributed_at IS NOT NULL
                   AND (aid_request.status = \'distributed\'
                        OR (distribution.aid_request_id IS NULL AND distribution.distribution_type = \'direct\'))
                   AND (:is_subject = 1 OR receiver.ds_division_id = beneficiary.ds_division_id)
                 FOR UPDATE'
            );
            $statement->execute(['id' => $distributionId, 'user_id' => $userId, 'actor_role' => $role, 'is_subject' => $isSubject ? 1 : 0]);
            $distribution = $statement->fetch();
            if (!$distribution || (int) $distribution['is_returnable'] !== 1
                || (int) $distribution['item_id'] !== (int) $selectedItemId
                || (int) $distribution['beneficiary_id'] !== (int) $selectedBeneficiaryId) {
                throw new RuntimeException(t('This returnable distribution is unavailable to you.'));
            }
            $outstanding = (int) $distribution['quantity'] - (int) $distribution['already_returned'];
            if ($quantity > $outstanding) {
                throw new RuntimeException(t('Return exceeds the outstanding issued quantity.'));
            }

            $restoreTo = $condition !== 'good'
                ? 'removed'
                : ($isSubject ? 'central-stock' : 'officer-pool');
            $statement = $database->prepare(
                'INSERT INTO item_returns
                    (distribution_id, quantity, returned_by_name, item_condition, reusable, restore_to, stock_review_status, processed_by)
                 VALUES
                    (:distribution_id, :quantity, :returned_by_name, :item_condition, :reusable, :restore_to, :stock_review_status, :processed_by)'
            );
            $statement->execute([
                'distribution_id' => $distributionId,
                'quantity' => $quantity,
                'returned_by_name' => $returnedByName,
                'item_condition' => $condition,
                'reusable' => $condition === 'good' ? 1 : 0,
                'restore_to' => $restoreTo,
                'stock_review_status' => $restoreTo === 'central-stock' ? 'pending' : 'accepted',
                'processed_by' => $userId,
            ]);
            $returnId = (int) $database->lastInsertId();

            if ($restoreTo === 'central-stock') {
                $keepers = $database->query("SELECT id FROM users WHERE role = 'store-keeper' AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($keepers as $keeperId) {
                    notifyUser($database, (int) $keeperId, 'return-review-' . $returnId,
                        'Return awaiting stock acceptance', 'Good return awaiting acceptance',
                        'RET-' . str_pad((string) $returnId, 4, '0', STR_PAD_LEFT) . ' needs review before Central Stock is updated.',
                        'dashboard.php?page=return-stock-review');
                }
            } elseif ($restoreTo === 'officer-pool') {
                $statement = $database->prepare(
                    "SELECT ds_division_id FROM users
                     WHERE id = :user_id AND role = 'social-service-officer' AND status = 'active'
                     FOR UPDATE"
                );
                $statement->execute(['user_id' => $userId]);
                $divisionId = $statement->fetchColumn();
                if (!$divisionId) {
                    throw new RuntimeException(t('An active DS Division assignment is required to restore your pool.'));
                }
                $statement = $database->prepare(
                    'INSERT INTO division_pools
                        (ds_division_id, item_id, allocated, reused)
                     VALUES (:division_id, :item_id, 0, :quantity)
                     ON DUPLICATE KEY UPDATE reused = reused + VALUES(reused)'
                );
                $statement->execute([
                    'division_id' => (int) $divisionId,
                    'item_id' => $distribution['item_id'],
                    'quantity' => $quantity,
                ]);
            }

            $database->commit();
            logActivity(
                'Returns',
                ($restoreTo === 'central-stock' ? 'Submitted good return for Store Keeper acceptance: ' : 'Processed return of ') . $quantity . ' unit(s) to ' . $restoreTo,
                'RET-' . str_pad((string) $returnId, 4, '0', STR_PAD_LEFT),
                $restoreTo
            );
            $_SESSION['flash_success'] = $restoreTo === 'central-stock'
                ? t('Return recorded. Central Stock will update after Store Keeper acceptance.')
                : t('Return processed and stock destination updated.');
            unset($_SESSION['csrf_token']);
            header('Location: dashboard.php?page=return-history#return-' . $returnId);
            exit;
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            error_log($exception->getMessage());
            $errors[] = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : t('Unable to process return.');
        }
    }
}

try {
    $statement = $database->prepare(
        'SELECT distribution.id, distribution.item_id, distribution.beneficiary_id, distribution.quantity,
                distribution.quantity - (
                    SELECT COALESCE(SUM(ret.quantity), 0)
                    FROM item_returns ret WHERE ret.distribution_id = distribution.id
                      AND ret.stock_review_status <> \'rejected\'
                ) AS outstanding,
                beneficiary.full_name, beneficiary.nic, beneficiary.elders_card_number,
                item.item_name, item.variety
         FROM distributions distribution
         JOIN beneficiaries beneficiary ON beneficiary.id = distribution.beneficiary_id
         JOIN inventory_items item ON item.id = distribution.item_id
         LEFT JOIN aid_requests aid_request ON aid_request.id = distribution.aid_request_id
         JOIN users receiver ON receiver.id = :user_id
            AND receiver.role = :actor_role AND receiver.status = "active"
         WHERE item.is_returnable = 1
           AND distribution.distributed_at IS NOT NULL
           AND (aid_request.status = \'distributed\'
                OR (distribution.aid_request_id IS NULL AND distribution.distribution_type = \'direct\'))
           AND (:is_subject = 1 OR receiver.ds_division_id = beneficiary.ds_division_id)
         HAVING outstanding > 0
         ORDER BY distribution.id DESC'
    );
    $statement->execute(['user_id' => $userId, 'actor_role' => $role, 'is_subject' => $isSubject ? 1 : 0]);
    $issued = $statement->fetchAll();
    $issuedAidItems = [];
    $issuedBeneficiaries = [];
    foreach ($issued as $record) {
        $issuedAidItems[(int) $record['item_id']] = widmsAidItemName((string) $record['item_name'])
            . ((string) $record['variety'] !== '' ? ' — ' . $record['variety'] : '');
        $beneficiaryIdentifier = trim((string) ($record['nic'] ?: $record['elders_card_number'] ?: ''));
        $issuedBeneficiaries[(int) $record['item_id']][(int) $record['beneficiary_id']] = [
            'label' => (string) $record['full_name']
                . ($beneficiaryIdentifier !== '' ? ' — ' . $beneficiaryIdentifier : '')
                . ' (#' . $record['beneficiary_id'] . ')',
        ];
    }

    $statement = $database->prepare(
        'SELECT ret.*, beneficiary.full_name, item.item_name, item.variety
         FROM item_returns ret
         JOIN distributions distribution ON distribution.id = ret.distribution_id
         JOIN beneficiaries beneficiary ON beneficiary.id = distribution.beneficiary_id
         JOIN inventory_items item ON item.id = distribution.item_id
         JOIN users viewer ON viewer.id = :user_id AND viewer.status = "active"
         WHERE (:subject_history = 1 AND ret.processed_by = :processor_id)
            OR (:division_history = 0 AND viewer.ds_division_id = distribution.ds_division_id)
         ORDER BY ret.id DESC'
    );
    $statement->execute(['user_id' => $userId, 'processor_id' => $userId,
        'subject_history' => $isSubject ? 1 : 0, 'division_history' => $isSubject ? 1 : 0]);
    $returns = $statement->fetchAll();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $issued = $returns = $issuedAidItems = $issuedBeneficiaries = [];
    $errors[] = t('Return workflow is unavailable.');
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars(widmsLanguage(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars(t($isHistory ? 'Return History' : 'Return Management'), ENT_QUOTES, 'UTF-8') ?> | SWPCS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/admin-dashboard.css') ?>" rel="stylesheet">
</head>
<body class="return-page-body">
<?php require $sidebar; ?>
<div class="admin-shell">
    <header class="topbar"><div class="d-flex align-items-center gap-3"><button class="menu-button" id="menu-button" type="button" aria-label="<?= htmlspecialchars(t('Open navigation'), ENT_QUOTES, 'UTF-8') ?>">&#9776;</button><h1><?= htmlspecialchars(t($isHistory ? 'Return History' : 'Return Management'), ENT_QUOTES, 'UTF-8') ?></h1></div></header>
    <main class="dashboard-content return-management-page widms-unified-ui">
        <?php renderSuccessMessage($success); ?>
        <?php if ($errors): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars(implode(' ', $errors), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if (!$isHistory): ?>
        <section class="admin-data-card return-workflow-card return-process-card">
            <div class="return-workflow-toolbar"><a class="outline-action" href="dashboard.php?page=return-history"><?= htmlspecialchars(t('View Return History'), ENT_QUOTES, 'UTF-8') ?></a></div>
            <form method="post" class="return-workflow-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="return-workflow-filter-row">
                    <label><span class="return-workflow-label"><?= htmlspecialchars(t('Aid Item'), ENT_QUOTES, 'UTF-8') ?> <span class="required-mark" aria-hidden="true">*</span></span>
                        <select name="aid_item_id" id="return-aid-item" required>
                            <option value=""><?= htmlspecialchars(t('Select an aid item'), ENT_QUOTES, 'UTF-8') ?></option>
                            <?php foreach ($issuedAidItems as $itemId => $itemLabel): ?>
                                <option value="<?= $itemId ?>"><?= htmlspecialchars($itemLabel, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="return-beneficiary-field"><label for="return-beneficiary-choice" class="return-workflow-label"><?= htmlspecialchars(t('Beneficiary'), ENT_QUOTES, 'UTF-8') ?> <span class="required-mark" aria-hidden="true">*</span></label>
                        <div class="return-beneficiary-combobox">
                            <input type="text" id="return-beneficiary-choice" role="combobox" aria-autocomplete="list" aria-haspopup="listbox" aria-controls="return-beneficiary-options" aria-expanded="false" placeholder="<?= htmlspecialchars(t('Search beneficiary by name or ID'), ENT_QUOTES, 'UTF-8') ?>" autocomplete="off" required data-invalid-selection="<?= htmlspecialchars(t('Select a beneficiary from the suggestions.'), ENT_QUOTES, 'UTF-8') ?>">
                            <button type="button" class="return-beneficiary-toggle" aria-label="<?= htmlspecialchars(t('Show beneficiaries'), ENT_QUOTES, 'UTF-8') ?>" aria-controls="return-beneficiary-options" aria-expanded="false"><span aria-hidden="true"></span></button>
                            <div id="return-beneficiary-options" class="return-beneficiary-options" role="listbox" hidden>
                                <?php foreach ($issuedBeneficiaries as $itemId => $beneficiaries): ?>
                                    <?php foreach ($beneficiaries as $beneficiaryId => $beneficiaryDetails): ?>
                                        <button type="button" id="return-beneficiary-option-<?= $itemId ?>-<?= $beneficiaryId ?>" role="option" aria-selected="false" data-item-id="<?= $itemId ?>" data-beneficiary-id="<?= $beneficiaryId ?>"><?= htmlspecialchars($beneficiaryDetails['label'], ENT_QUOTES, 'UTF-8') ?></button>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                                <span class="return-beneficiary-empty" hidden><?= htmlspecialchars(t('No matching beneficiaries'), ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        </div>
                        <input type="hidden" name="beneficiary_id" id="return-beneficiary-id" value="">
                    </div>
                </div>
                <div class="return-workflow-fields">
                    <label><span class="return-workflow-label"><?= htmlspecialchars(t('Distribution Record'), ENT_QUOTES, 'UTF-8') ?> <span class="required-mark" aria-hidden="true">*</span></span>
                        <select name="distribution_id" required>
                            <option value=""><?= htmlspecialchars(t('Select issued item'), ENT_QUOTES, 'UTF-8') ?></option>
                            <?php foreach ($issued as $record): ?>
                                <option value="<?= (int) $record['id'] ?>" data-item-id="<?= (int) $record['item_id'] ?>" data-beneficiary-id="<?= (int) $record['beneficiary_id'] ?>" data-outstanding="<?= (int) $record['outstanding'] ?>">DIST-<?= str_pad((string) $record['id'], 4, '0', STR_PAD_LEFT) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span class="return-workflow-label"><?= htmlspecialchars(t('Quantity'), ENT_QUOTES, 'UTF-8') ?> <span class="required-mark" aria-hidden="true">*</span></span><input type="number" name="quantity" min="1" value="" required></label>
                    <label><span class="return-workflow-label"><?= htmlspecialchars(t('Returned By'), ENT_QUOTES, 'UTF-8') ?> <span class="required-mark" aria-hidden="true">*</span></span><input type="text" name="returned_by_name" list="return-person-names" maxlength="150" required autocomplete="off" placeholder="<?= htmlspecialchars(t('Name of person returning the item'), ENT_QUOTES, 'UTF-8') ?>"></label>
                    <datalist id="return-person-names">
                        <?php foreach (array_unique(array_column($issued, 'full_name')) as $personName): ?>
                            <option value="<?= htmlspecialchars((string) $personName, ENT_QUOTES, 'UTF-8') ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <label><span class="return-workflow-label"><?= htmlspecialchars(t('Condition'), ENT_QUOTES, 'UTF-8') ?> <span class="required-mark" aria-hidden="true">*</span></span>
                        <select name="condition" required>
                            <option value=""><?= htmlspecialchars(t('Select condition'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="good"><?= htmlspecialchars(t('Good'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="damaged"><?= htmlspecialchars(t('Damaged'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="unusable"><?= htmlspecialchars(t('Unusable'), ENT_QUOTES, 'UTF-8') ?></option>
                        </select>
                    </label>
                    <div class="return-workflow-destination"><small><?= htmlspecialchars(t('Good items restore to'), ENT_QUOTES, 'UTF-8') ?></small><strong><?= htmlspecialchars(t($isSubject ? 'Central Stock' : 'My Officer Pool'), ENT_QUOTES, 'UTF-8') ?></strong><span><?= htmlspecialchars(t($isSubject ? 'Central Stock updates only after Store Keeper acceptance.' : 'Damaged or unusable items are recorded as removed.'), ENT_QUOTES, 'UTF-8') ?></span></div>
                </div>
                <div class="return-workflow-footer"><button class="admin-primary-action" type="submit" <?= $issued === [] ? 'disabled' : '' ?>><?= htmlspecialchars(t('Process Return'), ENT_QUOTES, 'UTF-8') ?></button></div>
            </form>
        </section>

        <?php else: ?>
        <section class="admin-data-card return-workflow-card return-history-card">
            <div class="return-history-filters" role="search" aria-label="<?= htmlspecialchars(t('Filter return history'), ENT_QUOTES, 'UTF-8') ?>">
                <label for="return-history-search"><span><?= htmlspecialchars(t('Search'), ENT_QUOTES, 'UTF-8') ?></span><input id="return-history-search" type="search" placeholder="<?= htmlspecialchars(t('Search return ID, beneficiary or aid item'), ENT_QUOTES, 'UTF-8') ?>"></label>
                <label for="return-history-condition"><span><?= htmlspecialchars(t('Condition'), ENT_QUOTES, 'UTF-8') ?></span><select id="return-history-condition"><option value=""><?= htmlspecialchars(t('All conditions'), ENT_QUOTES, 'UTF-8') ?></option><option value="good"><?= htmlspecialchars(t('Good'), ENT_QUOTES, 'UTF-8') ?></option><option value="damaged"><?= htmlspecialchars(t('Damaged'), ENT_QUOTES, 'UTF-8') ?></option><option value="unusable"><?= htmlspecialchars(t('Unusable'), ENT_QUOTES, 'UTF-8') ?></option></select></label>
            </div>
            <div class="admin-data-table-wrap"><table class="admin-data-table">
                <thead><tr><th><?= htmlspecialchars(t('Return ID'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Beneficiary'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Returned By'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Item'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Quantity'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Condition'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Stock Status'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Restored To'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Date'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead>
                <tbody>
                <?php if ($returns === []): ?><tr><td colspan="9" class="admin-empty-row"><?= htmlspecialchars(t('No returns recorded yet.'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                <?php else: foreach ($returns as $return): ?>
                    <tr id="return-<?= (int) $return['id'] ?>" data-condition="<?= htmlspecialchars((string) $return['item_condition'], ENT_QUOTES, 'UTF-8') ?>" class="return-history-row"><td><strong>RET-<?= str_pad((string) $return['id'], 4, '0', STR_PAD_LEFT) ?></strong></td><td><?= htmlspecialchars((string) $return['full_name'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string) ($return['returned_by_name'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars(widmsAidItemName((string) $return['item_name']) . ((string) $return['variety'] !== '' ? ' — ' . $return['variety'] : ''), ENT_QUOTES, 'UTF-8') ?></td><td><?= (int) $return['quantity'] ?></td><td><span class="return-condition-pill return-condition-<?= htmlspecialchars((string) $return['item_condition'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t(ucfirst((string) $return['item_condition'])), ENT_QUOTES, 'UTF-8') ?></span></td><td><span class="return-review-pill return-review-<?= htmlspecialchars((string) $return['stock_review_status'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t(match ($return['stock_review_status']) {'pending' => 'Pending Store Keeper', 'rejected' => 'Rejected', default => 'Accepted'}), ENT_QUOTES, 'UTF-8') ?></span><?php if ($return['stock_review_note']): ?><small><?= htmlspecialchars((string) $return['stock_review_note'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?></td><td><?= htmlspecialchars(t($return['stock_review_status'] === 'rejected' ? 'Not restocked' : ($return['stock_review_status'] === 'pending' ? 'Awaiting Central Stock' : match ($return['restore_to']) {'officer-pool' => 'My Officer Pool', 'central-stock' => 'Central Stock', default => 'Removed / Disposal'})), ENT_QUOTES, 'UTF-8') ?></td><td><?= date('d M Y, H:i', strtotime((string) $return['processed_at'])) ?></td></tr>
                <?php endforeach; ?>
                    <tr id="return-history-no-results" hidden><td colspan="9" class="admin-empty-row"><?= htmlspecialchars(t('No returns match these filters.'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table></div>
        </section>
        <?php endif; ?>
    </main>
</div>
<script src="assets/js/admin-dashboard.js"></script>
<?php if (!$isHistory): ?>
<script>
(() => {
    const form = document.querySelector('.return-workflow-form');
    const distribution = form?.querySelector('select[name="distribution_id"]');
    const aidItem = document.getElementById('return-aid-item');
    const beneficiary = document.getElementById('return-beneficiary-choice');
    const beneficiaryId = document.getElementById('return-beneficiary-id');
    const beneficiaryList = document.getElementById('return-beneficiary-options');
    const beneficiaryToggle = form?.querySelector('.return-beneficiary-toggle');
    const beneficiaryEmpty = form?.querySelector('.return-beneficiary-empty');
    const returnedBy = form?.querySelector('input[name="returned_by_name"]');
    const quantity = form?.querySelector('input[name="quantity"]');
    const condition = form?.querySelector('select[name="condition"]');
    if (!form || !distribution || !beneficiary || !beneficiaryId || !beneficiaryList || !beneficiaryToggle || !beneficiaryEmpty || !aidItem || !returnedBy || !quantity || !condition) return;

    const beneficiaryOptions = [...beneficiaryList.querySelectorAll('[role="option"]')];
    const beneficiaryCombobox = beneficiary.closest('.return-beneficiary-combobox');
    let activeOption = -1;
    const distributionOptions = [...distribution.options].slice(1).map(option => option.cloneNode(true));
    const resetSelect = (select, options, predicate) => {
        const placeholder = select.options[0];
        select.replaceChildren(placeholder, ...options.filter(predicate).map(option => option.cloneNode(true)));
        select.value = '';
    };
    const filterDistributions = () => {
        resetSelect(distribution, distributionOptions, option =>
            option.dataset.itemId === aidItem.value && option.dataset.beneficiaryId === beneficiaryId.value
        );
        distribution.disabled = !beneficiaryId.value;
        quantity.value = '';
        quantity.removeAttribute('max');
        quantity.disabled = true;
        returnedBy.value = '';
        condition.value = '';
    };
    const visibleBeneficiaries = () => beneficiaryOptions.filter(option => !option.hidden);
    const closeBeneficiaries = () => {
        beneficiaryList.hidden = true;
        beneficiary.setAttribute('aria-expanded', 'false');
        beneficiaryToggle.setAttribute('aria-expanded', 'false');
        beneficiary.removeAttribute('aria-activedescendant');
        activeOption = -1;
        beneficiaryOptions.forEach(option => option.classList.remove('is-active'));
    };
    const openBeneficiaries = (showAll = false) => {
        if (!aidItem.value) return;
        const search = showAll ? '' : beneficiary.value.trim().toLocaleLowerCase();
        beneficiaryOptions.forEach(option => {
            option.hidden = option.dataset.itemId !== aidItem.value || !option.textContent.toLocaleLowerCase().includes(search);
        });
        beneficiaryEmpty.hidden = visibleBeneficiaries().length !== 0;
        beneficiaryList.hidden = false;
        beneficiary.setAttribute('aria-expanded', 'true');
        beneficiaryToggle.setAttribute('aria-expanded', 'true');
        activeOption = -1;
        beneficiary.removeAttribute('aria-activedescendant');
        beneficiaryOptions.forEach(option => option.classList.remove('is-active'));
    };
    const activateOption = index => {
        const options = visibleBeneficiaries();
        if (!options.length) return;
        activeOption = (index + options.length) % options.length;
        beneficiaryOptions.forEach(option => option.classList.remove('is-active'));
        options[activeOption].classList.add('is-active');
        beneficiary.setAttribute('aria-activedescendant', options[activeOption].id);
        options[activeOption].scrollIntoView({block: 'nearest'});
    };
    const chooseBeneficiary = option => {
        beneficiary.value = option.textContent;
        beneficiaryId.value = option.dataset.beneficiaryId;
        beneficiary.setCustomValidity('');
        beneficiaryOptions.forEach(candidate => candidate.setAttribute('aria-selected', candidate === option ? 'true' : 'false'));
        filterDistributions();
        beneficiary.focus();
        closeBeneficiaries();
    };
    const filterBeneficiaries = () => {
        beneficiary.value = '';
        beneficiaryId.value = '';
        beneficiary.setCustomValidity('');
        beneficiary.disabled = !aidItem.value;
        beneficiaryToggle.disabled = !aidItem.value;
        beneficiaryOptions.forEach(option => option.setAttribute('aria-selected', 'false'));
        closeBeneficiaries();
        filterDistributions();
    };
    aidItem.addEventListener('change', filterBeneficiaries);
    beneficiary.addEventListener('focus', () => openBeneficiaries(Boolean(beneficiaryId.value)));
    beneficiary.addEventListener('input', () => {
        beneficiary.setCustomValidity('');
        if (beneficiaryId.value) {
            beneficiaryId.value = '';
            filterDistributions();
        }
        openBeneficiaries();
    });
    beneficiary.addEventListener('keydown', event => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (beneficiaryList.hidden) openBeneficiaries(Boolean(beneficiaryId.value));
            const direction = event.key === 'ArrowDown' ? 1 : -1;
            activateOption(activeOption < 0 && direction < 0 ? visibleBeneficiaries().length - 1 : activeOption + direction);
        } else if (event.key === 'Enter' && !beneficiaryList.hidden && activeOption >= 0) {
            event.preventDefault();
            chooseBeneficiary(visibleBeneficiaries()[activeOption]);
        } else if (event.key === 'Escape') {
            closeBeneficiaries();
        }
    });
    beneficiaryToggle.addEventListener('click', () => {
        if (!beneficiaryList.hidden) {
            closeBeneficiaries();
        } else {
            beneficiary.focus();
            openBeneficiaries(true);
        }
    });
    beneficiaryOptions.forEach(option => option.addEventListener('click', () => chooseBeneficiary(option)));
    document.addEventListener('pointerdown', event => {
        if (!beneficiaryCombobox.contains(event.target)) closeBeneficiaries();
    });
    form.addEventListener('submit', event => {
        const match = beneficiaryOptions.find(option => option.dataset.itemId === aidItem.value
            && option.dataset.beneficiaryId === beneficiaryId.value
            && option.textContent === beneficiary.value);
        if (!match) {
            beneficiary.setCustomValidity(beneficiary.dataset.invalidSelection);
            event.preventDefault();
            beneficiary.reportValidity();
        }
    });
    distribution.addEventListener('change', () => {
        const outstanding = Number(distribution.selectedOptions[0]?.dataset.outstanding || 0);
        if (outstanding > 0) {
            quantity.max = String(outstanding);
            quantity.value = String(outstanding);
            quantity.disabled = false;
        } else {
            quantity.value = '';
            quantity.removeAttribute('max');
            quantity.disabled = true;
        }
    });
    filterBeneficiaries();
})();
</script>
<?php else: ?>
<script>
(() => {
    const search = document.getElementById('return-history-search');
    const condition = document.getElementById('return-history-condition');
    const rows = [...document.querySelectorAll('.return-history-row')];
    const noResults = document.getElementById('return-history-no-results');
    if (!search || !condition || !rows.length || !noResults) return;
    const applyFilters = () => {
        const term = search.value.trim().toLocaleLowerCase();
        let visible = 0;
        rows.forEach(row => {
            row.hidden = (condition.value !== '' && row.dataset.condition !== condition.value)
                || (term !== '' && !row.textContent.toLocaleLowerCase().includes(term));
            if (!row.hidden) visible++;
        });
        noResults.hidden = visible !== 0;
    };
    search.addEventListener('input', applyFilters);
    condition.addEventListener('change', applyFilters);
})();
</script>
<?php endif; ?>
</body>
</html>
