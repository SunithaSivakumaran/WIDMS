<?php

declare(strict_types=1);

requireRole('subject-officer');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/activity.php';

$activePage = 'eligibility-rules';
$db = database();
$errors = [];
$success = (string) ($_SESSION['flash_success'] ?? '');
unset($_SESSION['flash_success']);

function eligibilityRulesRedirect(string $message): never
{
    $_SESSION['flash_success'] = $message;
    unset($_SESSION['csrf_token']);
    header('Location: dashboard.php?page=eligibility-rules');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } else {
        try {
            $action = (string) ($_POST['action'] ?? '');
            if ($action !== 'delete-rule') {
                throw new RuntimeException('Invalid eligibility rule action.');
            }

            $ruleId = filter_input(INPUT_POST, 'rule_id', FILTER_VALIDATE_INT);
            if (!$ruleId) {
                throw new RuntimeException('Select a valid eligibility rule.');
            }

            $db->beginTransaction();
            $statement = $db->prepare(
                'SELECT item_id FROM disability_aid_items WHERE id=:id FOR UPDATE'
            );
            $statement->execute(['id' => $ruleId]);
            $itemId = (int) $statement->fetchColumn();
            if (!$itemId) {
                throw new RuntimeException('The selected rule no longer exists.');
            }

            // Permanently remove the rule while retaining inventory referenced by historical records.
            $db->prepare('DELETE FROM disability_aid_items WHERE id=:id')
                ->execute(['id' => $ruleId]);

            $statement = $db->prepare(
                'SELECT COUNT(*) FROM disability_aid_items WHERE item_id=:item'
            );
            $statement->execute(['item' => $itemId]);
            if (!(int) $statement->fetchColumn()) {
                try {
                    $db->prepare('DELETE FROM inventory_items WHERE id=:item')
                        ->execute(['item' => $itemId]);
                } catch (PDOException $exception) {
                    if ($exception->getCode() !== '23000') {
                        throw $exception;
                    }
                }
            }

            $db->commit();
            logActivity(
                'Disability Aid Configuration',
                'Deleted eligibility rule',
                'RULE-' . $ruleId,
                'deleted'
            );
            eligibilityRulesRedirect('Eligibility rule deleted permanently.');
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log($exception->getMessage());
            $errors[] = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'Unable to delete the eligibility rule.';
        }
    }
}

try {
    // Keep the listing query on this page so the builder remains focused on data entry.
    $rules = $db->query(
        "SELECT dai.id,
                dai.item_id,
                dai.restriction_months,
                dt.name AS disability_name,
                i.item_name,
                i.variety,
                GROUP_CONCAT(blocked.item_name ORDER BY blocked.item_name SEPARATOR ', ') AS prohibited_names
         FROM disability_aid_items dai
         JOIN disability_types dt ON dt.id=dai.disability_type_id
         JOIN inventory_items i ON i.id=dai.item_id
         LEFT JOIN disability_item_prohibitions p ON p.disability_aid_item_id=dai.id
         LEFT JOIN inventory_items blocked ON blocked.id=p.prohibited_item_id
         WHERE dai.status='active'
         GROUP BY dai.id
         ORDER BY dt.name, i.item_name"
    )->fetchAll();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $rules = [];
    $errors[] = 'Eligibility rules are unavailable. Run the latest database migration.';
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars(widmsLanguage(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Configured Eligibility Rules | WIDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/../../includes/subject-officer-sidebar.php'; ?>
<div class="admin-shell">
    <header class="topbar"><h1>Configured Eligibility Rules</h1></header>
    <main class="dashboard-content master-config-page configured-eligibility-page">
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($errors): ?>
            <div class="alert alert-danger"><?= htmlspecialchars(implode(' ', $errors), ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <!-- Keep rule review and maintenance separate from the creation form. -->
        <section class="admin-data-card eligibility-rules-component standalone-rules-component">
            <div class="admin-data-header eligibility-rules-header">
                <div>
                    <h2>Configured Eligibility Rules</h2>
                    <p>Review existing rules or open one to change all of its details.</p>
                </div>
                <a class="admin-primary-action eligibility-builder-link" href="dashboard.php?page=item-categories">Build New Eligibility Rule</a>
            </div>
            <div class="admin-data-table-wrap">
                <table class="admin-data-table eligibility-rules-table">
                    <thead>
                    <tr>
                        <th>Disability</th>
                        <th>Item</th>
                        <th>Probation</th>
                        <th>Other Prohibited Items</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$rules): ?>
                        <tr><td colspan="5" class="admin-empty-row">No aid eligibility rules configured.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rules as $rule): ?>
                            <?php $years = (int) $rule['restriction_months'] >= 12 && (int) $rule['restriction_months'] % 12 === 0; ?>
                            <tr>
                                <td><?= htmlspecialchars($rule['disability_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($rule['item_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <small><?= htmlspecialchars($rule['variety'], ENT_QUOTES, 'UTF-8') ?></small>
                                </td>
                                <td>
                                    <?= $years ? (int) $rule['restriction_months'] / 12 : (int) $rule['restriction_months'] ?>
                                    <span><?= $years ? 'year(s)' : 'month(s)' ?></span>
                                </td>
                                <td><?= htmlspecialchars($rule['prohibited_names'] ?: 'None', ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <div class="rule-row-actions">
                                        <a class="outline-action" href="dashboard.php?page=edit-aid-rule&amp;rule_id=<?= (int) $rule['id'] ?>">Edit</a>
                                        <form method="post" onsubmit="return confirm('<?= htmlspecialchars(t('Delete this eligibility rule permanently?'), ENT_QUOTES, 'UTF-8') ?>')">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="action" value="delete-rule">
                                            <input type="hidden" name="rule_id" value="<?= (int) $rule['id'] ?>">
                                            <button class="reject-button" type="submit">Delete</button>
                                        </form>
                                    </div>
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
<script src="assets/js/admin-dashboard.js"></script>
</body>
</html>
