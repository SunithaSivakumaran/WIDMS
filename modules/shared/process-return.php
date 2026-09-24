<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/activity.php';
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

if (!$isHistory && $_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'set-returnable') {
    $itemId = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT)
        ?: filter_var($_POST['item_id'] ?? null, FILTER_VALIDATE_INT);
    $returnable = (string) ($_POST['is_returnable'] ?? '') === '1' ? 1 : 0;
    if (!$isSubject || !verifyCsrfToken((string) ($_POST['csrf_token'] ?? '')) || !$itemId) {
        $errors[] = t('Invalid returnable item setting.');
    } else {
        try {
            $statement = $database->prepare('UPDATE inventory_items SET is_returnable = :returnable WHERE id = :item_id');
            $statement->execute(['returnable' => $returnable, 'item_id' => $itemId]);
            if ($statement->rowCount() === 0) {
                $check = $database->prepare('SELECT id FROM inventory_items WHERE id = :item_id');
                $check->execute(['item_id' => $itemId]);
                if (!$check->fetchColumn()) {
                    throw new RuntimeException(t('The selected aid item is unavailable.'));
                }
            }
            logActivity('Returns', 'Updated item returnability', 'ITEM-' . $itemId, $returnable ? 'enabled' : 'disabled');
            $_SESSION['flash_success'] = t('Returnable item setting saved.');
            unset($_SESSION['csrf_token']);
            header('Location: dashboard.php?page=returns#returnable-items');
            exit;
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : t('Unable to save item setting.');
        }
    }
}

if (!$isHistory && $_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') !== 'set-returnable') {
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
            $statement = $database->prepare(
                'SELECT distribution.id, distribution.item_id, distribution.beneficiary_id, distribution.quantity,
                        item.is_returnable,
                        (SELECT COALESCE(SUM(ret.quantity), 0)
                         FROM item_returns ret WHERE ret.distribution_id = distribution.id) AS already_returned
                 FROM distributions distribution
                 JOIN inventory_items item ON item.id = distribution.item_id
                 WHERE distribution.id = :id
                   AND (
                       distribution.distributed_by = :user_id
                       OR (:is_subject = 1 AND EXISTS (
                           SELECT 1 FROM users issuer
                           WHERE issuer.id = distribution.distributed_by
                             AND issuer.role IN ("social-service-officer", "admin")
                       ))
                   )
                 FOR UPDATE'
            );
            $statement->execute(['id' => $distributionId, 'user_id' => $userId, 'is_subject' => $isSubject ? 1 : 0]);
            $distribution = $statement->fetch();
            if (!$distribution || (int) $distribution['is_returnable'] !== 1
                || (int) $distribution['item_id'] !== (int) $selectedItemId
                || (int) $distribution['beneficiary_id'] !== (int) $selectedBeneficiaryId) {
                throw new RuntimeException(t('This returnable distribution is unavailable in your issue history.'));
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
                    (distribution_id, quantity, returned_by_name, item_condition, reusable, restore_to, processed_by)
                 VALUES
                    (:distribution_id, :quantity, :returned_by_name, :item_condition, :reusable, :restore_to, :processed_by)'
            );
            $statement->execute([
                'distribution_id' => $distributionId,
                'quantity' => $quantity,
                'returned_by_name' => $returnedByName,
                'item_condition' => $condition,
                'reusable' => $condition === 'good' ? 1 : 0,
                'restore_to' => $restoreTo,
                'processed_by' => $userId,
            ]);
            $returnId = (int) $database->lastInsertId();

            if ($restoreTo === 'central-stock') {
                $statement = $database->prepare(
                    'UPDATE inventory_items SET quantity = quantity + :quantity WHERE id = :item_id'
                );
                $statement->execute(['quantity' => $quantity, 'item_id' => $distribution['item_id']]);
                if ($statement->rowCount() !== 1) {
                    throw new RuntimeException(t('The item stock could not be updated.'));
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
                    'INSERT INTO officer_pools
                        (officer_id, ds_division_id, item_id, allocated, reused)
                     VALUES (:officer_id, :division_id, :item_id, 0, :quantity)
                     ON DUPLICATE KEY UPDATE
                        ds_division_id = VALUES(ds_division_id),
                        reused = reused + VALUES(reused)'
                );
                $statement->execute([
                    'officer_id' => $userId,
                    'division_id' => (int) $divisionId,
                    'item_id' => $distribution['item_id'],
                    'quantity' => $quantity,
                ]);
            }

            $database->commit();
            logActivity(
                'Returns',
                'Processed return of ' . $quantity . ' unit(s) to ' . $restoreTo,
                'RET-' . str_pad((string) $returnId, 4, '0', STR_PAD_LEFT),
                $restoreTo
            );
            $_SESSION['flash_success'] = t('Return processed and stock destination updated.');
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
    $returnableItems = [];
    if ($isSubject) {
        $returnableItems = $database->query(
            'SELECT id, item_name, variety, is_returnable
             FROM inventory_items ORDER BY item_name, variety, id'
        )->fetchAll();
    }
    $statement = $database->prepare(
        'SELECT distribution.id, distribution.item_id, distribution.beneficiary_id, distribution.quantity,
                distribution.quantity - (
                    SELECT COALESCE(SUM(ret.quantity), 0)
                    FROM item_returns ret WHERE ret.distribution_id = distribution.id
                ) AS outstanding,
                beneficiary.full_name, beneficiary.nic,
                item.item_name, item.variety
         FROM distributions distribution
         JOIN beneficiaries beneficiary ON beneficiary.id = distribution.beneficiary_id
         JOIN inventory_items item ON item.id = distribution.item_id
         WHERE item.is_returnable = 1
           AND (
               distribution.distributed_by = :user_id
               OR (:is_subject = 1 AND EXISTS (
                   SELECT 1 FROM users issuer
                   WHERE issuer.id = distribution.distributed_by
                     AND issuer.role IN ("social-service-officer", "admin")
               ))
           )
         HAVING outstanding > 0
         ORDER BY distribution.id DESC'
    );
    $statement->execute(['user_id' => $userId, 'is_subject' => $isSubject ? 1 : 0]);
    $issued = $statement->fetchAll();
    $issuedAidItems = [];
    $issuedBeneficiaries = [];
    foreach ($issued as $record) {
        $issuedAidItems[(int) $record['item_id']] = (string) $record['item_name']
            . ((string) $record['variety'] !== '' ? ' — ' . $record['variety'] : '');
        $issuedBeneficiaries[(int) $record['item_id']][(int) $record['beneficiary_id']] = [
            'name' => (string) $record['full_name'],
            'label' => (string) $record['full_name'] . ((string) ($record['nic'] ?? '') !== ''
                ? ' — ' . $record['nic'] : ' — #' . $record['beneficiary_id']),
        ];
    }

    $statement = $database->prepare(
        'SELECT ret.*, beneficiary.full_name, item.item_name, item.variety
         FROM item_returns ret
         JOIN distributions distribution ON distribution.id = ret.distribution_id
         JOIN beneficiaries beneficiary ON beneficiary.id = distribution.beneficiary_id
         JOIN inventory_items item ON item.id = distribution.item_id
         WHERE ret.processed_by = :user_id
         ORDER BY ret.id DESC LIMIT 100'
    );
    $statement->execute(['user_id' => $userId]);
    $returns = $statement->fetchAll();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $issued = $returns = $returnableItems = $issuedAidItems = $issuedBeneficiaries = [];
    $errors[] = t('Return workflow is unavailable.');
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars(widmsLanguage(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars(t($isHistory ? 'Return History' : 'Return Management'), ENT_QUOTES, 'UTF-8') ?> | WIDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css?v=93" rel="stylesheet">
</head>
<body class="return-page-body">
<?php require $sidebar; ?>
<div class="admin-shell">
    <header class="topbar"><div class="d-flex align-items-center gap-3"><button class="menu-button" id="menu-button" type="button" aria-label="<?= htmlspecialchars(t('Open navigation'), ENT_QUOTES, 'UTF-8') ?>">&#9776;</button><h1><?= htmlspecialchars(t($isHistory ? 'Return History' : 'Return Management'), ENT_QUOTES, 'UTF-8') ?></h1></div></header>
    <main class="dashboard-content return-management-page widms-unified-ui">
        <?php renderSuccessMessage($success); ?>
        <?php if ($errors): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars(implode(' ', $errors), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if (!$isHistory): ?>
        <section class="admin-data-card return-workflow-card">
            <div class="admin-data-header"><div><h2><?= htmlspecialchars(t('Process Return'), ENT_QUOTES, 'UTF-8') ?></h2><small><?= htmlspecialchars(t('Only items marked returnable can be processed. Good items return to your role-specific stock; damaged items are excluded.'), ENT_QUOTES, 'UTF-8') ?></small></div><a class="outline-action" href="dashboard.php?page=return-history"><?= htmlspecialchars(t('View Return History'), ENT_QUOTES, 'UTF-8') ?></a></div>
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
                    <label><span class="return-workflow-label"><?= htmlspecialchars(t('Beneficiary'), ENT_QUOTES, 'UTF-8') ?> <span class="required-mark" aria-hidden="true">*</span></span>
                        <select name="beneficiary_id" id="return-beneficiary-choice" required>
                            <option value=""><?= htmlspecialchars(t('Select beneficiary'), ENT_QUOTES, 'UTF-8') ?></option>
                            <?php foreach ($issuedBeneficiaries as $itemId => $beneficiaries): ?>
                                <?php foreach ($beneficiaries as $beneficiaryId => $beneficiaryDetails): ?>
                                    <option value="<?= $beneficiaryId ?>" data-item-id="<?= $itemId ?>" data-name="<?= htmlspecialchars($beneficiaryDetails['name'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($beneficiaryDetails['label'], ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <div class="return-workflow-fields">
                    <label><span class="return-workflow-label"><?= htmlspecialchars(t('Distribution Record'), ENT_QUOTES, 'UTF-8') ?> <span class="required-mark" aria-hidden="true">*</span></span>
                        <select name="distribution_id" required>
                            <option value=""><?= htmlspecialchars(t('Select issued item'), ENT_QUOTES, 'UTF-8') ?></option>
                            <?php foreach ($issued as $record): ?>
                                <option value="<?= (int) $record['id'] ?>" data-item-id="<?= (int) $record['item_id'] ?>" data-beneficiary-id="<?= (int) $record['beneficiary_id'] ?>" data-outstanding="<?= (int) $record['outstanding'] ?>"><?= htmlspecialchars((string) $record['full_name'], ENT_QUOTES, 'UTF-8') ?> — DIST-<?= str_pad((string) $record['id'], 4, '0', STR_PAD_LEFT) ?> (<?= (int) $record['outstanding'] ?> <?= htmlspecialchars(t('outstanding'), ENT_QUOTES, 'UTF-8') ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span class="return-workflow-label"><?= htmlspecialchars(t('Quantity'), ENT_QUOTES, 'UTF-8') ?> <span class="required-mark" aria-hidden="true">*</span></span><input type="number" name="quantity" min="1" value="1" required></label>
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
                    <div class="return-workflow-destination"><small><?= htmlspecialchars(t('Good items restore to'), ENT_QUOTES, 'UTF-8') ?></small><strong><?= htmlspecialchars(t($isSubject ? 'Central Stock' : 'My Officer Pool'), ENT_QUOTES, 'UTF-8') ?></strong><span><?= htmlspecialchars(t('Damaged or unusable items are recorded as removed.'), ENT_QUOTES, 'UTF-8') ?></span></div>
                </div>
                <div class="return-workflow-footer"><button class="admin-primary-action" type="submit" <?= $issued === [] ? 'disabled' : '' ?>><?= htmlspecialchars(t('Process Return'), ENT_QUOTES, 'UTF-8') ?></button></div>
            </form>
        </section>

        <?php if ($isSubject): ?>
        <section class="admin-data-card return-workflow-card" id="returnable-items">
            <div class="admin-data-header"><div><h2><?= htmlspecialchars(t('Returnable Aid Items'), ENT_QUOTES, 'UTF-8') ?></h2><small><?= htmlspecialchars(t('Choose which aid items may be returned after distribution.'), ENT_QUOTES, 'UTF-8') ?></small></div></div>
            <div class="admin-data-table-wrap"><table class="admin-data-table">
                <thead><tr><th><?= htmlspecialchars(t('Aid Item'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Returnable'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Action'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead>
                <tbody>
                <?php if ($returnableItems === []): ?><tr><td colspan="3" class="admin-empty-row"><?= htmlspecialchars(t('No aid items available.'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                <?php else: foreach ($returnableItems as $item): ?>
                    <tr><td><strong><?= htmlspecialchars((string) $item['item_name'], ENT_QUOTES, 'UTF-8') ?></strong><?php if ((string) $item['variety'] !== ''): ?><small><?= htmlspecialchars((string) $item['variety'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?></td><td><span class="goods-status-pill <?= (int) $item['is_returnable'] === 1 ? 'is-approved' : 'is-pending' ?>"><?= htmlspecialchars(t((int) $item['is_returnable'] === 1 ? 'Yes' : 'No'), ENT_QUOTES, 'UTF-8') ?></span></td><td><form method="post" class="returnable-item-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="set-returnable"><input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>"><input type="hidden" name="is_returnable" value="<?= (int) $item['is_returnable'] === 1 ? 0 : 1 ?>"><button type="submit" class="outline-action"><?= htmlspecialchars(t((int) $item['is_returnable'] === 1 ? 'Disable Returns' : 'Enable Returns'), ENT_QUOTES, 'UTF-8') ?></button></form></td></tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table></div>
        </section>
        <?php endif; ?>

        <?php else: ?>
        <section class="admin-data-card return-workflow-card">
            <div class="admin-data-header"><div><h2><?= htmlspecialchars(t('Return History'), ENT_QUOTES, 'UTF-8') ?></h2><small><?= htmlspecialchars(t('Only returns processed by you are shown here.'), ENT_QUOTES, 'UTF-8') ?></small></div><a class="outline-action" href="dashboard.php?page=<?= $isSubject ? 'returns' : 'process-return' ?>"><?= htmlspecialchars(t('Process Return'), ENT_QUOTES, 'UTF-8') ?></a></div>
            <div class="admin-data-table-wrap"><table class="admin-data-table">
                <thead><tr><th><?= htmlspecialchars(t('Return ID'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Beneficiary'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Returned By'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Item'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Quantity'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Condition'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Restored To'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Date'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead>
                <tbody>
                <?php if ($returns === []): ?><tr><td colspan="8" class="admin-empty-row"><?= htmlspecialchars(t('No returns recorded yet.'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                <?php else: foreach ($returns as $return): ?>
                    <tr id="return-<?= (int) $return['id'] ?>"><td><strong>RET-<?= str_pad((string) $return['id'], 4, '0', STR_PAD_LEFT) ?></strong></td><td><?= htmlspecialchars((string) $return['full_name'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string) ($return['returned_by_name'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string) $return['item_name'] . ((string) $return['variety'] !== '' ? ' — ' . $return['variety'] : ''), ENT_QUOTES, 'UTF-8') ?></td><td><?= (int) $return['quantity'] ?></td><td><?= htmlspecialchars(t(ucfirst((string) $return['item_condition'])), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars(t(match ($return['restore_to']) {'officer-pool' => 'My Officer Pool', 'central-stock' => 'Central Stock', default => 'Removed / Disposal'}), ENT_QUOTES, 'UTF-8') ?></td><td><?= date('d M Y, H:i', strtotime((string) $return['processed_at'])) ?></td></tr>
                <?php endforeach; endif; ?>
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
    const distribution = document.querySelector('.return-workflow-form select[name="distribution_id"]');
    const aidItem = document.getElementById('return-aid-item');
    const beneficiary = document.getElementById('return-beneficiary-choice');
    const returnedBy = document.querySelector('.return-workflow-form input[name="returned_by_name"]');
    const quantity = document.querySelector('.return-workflow-form input[name="quantity"]');
    if (!distribution || !beneficiary || !aidItem || !returnedBy || !quantity) return;

    const beneficiaryOptions = [...beneficiary.options].slice(1).map(option => option.cloneNode(true));
    const distributionOptions = [...distribution.options].slice(1).map(option => option.cloneNode(true));
    const resetSelect = (select, options, predicate) => {
        const placeholder = select.options[0];
        select.replaceChildren(placeholder, ...options.filter(predicate).map(option => option.cloneNode(true)));
        select.value = '';
    };
    const updateQuantity = () => {
        const outstanding = Number(distribution.selectedOptions[0]?.dataset.outstanding || 0);
        if (outstanding > 0) quantity.max = String(outstanding);
        else quantity.removeAttribute('max');
        quantity.value = '1';
    };
    const filterDistributions = () => {
        resetSelect(distribution, distributionOptions, option =>
            option.dataset.itemId === aidItem.value && option.dataset.beneficiaryId === beneficiary.value
        );
        distribution.disabled = !beneficiary.value;
        if (distribution.options.length === 2) distribution.selectedIndex = 1;
        updateQuantity();
    };
    const filterBeneficiaries = () => {
        resetSelect(beneficiary, beneficiaryOptions, option => option.dataset.itemId === aidItem.value);
        beneficiary.disabled = !aidItem.value;
        returnedBy.value = '';
        filterDistributions();
    };
    aidItem.addEventListener('change', filterBeneficiaries);
    beneficiary.addEventListener('change', () => {
        returnedBy.value = beneficiary.selectedOptions[0]?.dataset.name || '';
        filterDistributions();
    });
    distribution.addEventListener('change', updateQuantity);
    filterBeneficiaries();
})();
</script>
<?php endif; ?>
</body>
</html>
