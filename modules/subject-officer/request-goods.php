<?php
declare(strict_types=1);

requireRole('subject-officer');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/activity.php';
require_once __DIR__ . '/../../includes/optical-stock.php';
require_once __DIR__ . '/../../includes/spectacle-categories.php';

// Approved beneficiary needs are now selected together in the bundle card page.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $approvedRequestId = filter_input(INPUT_GET, 'aid_request_id', FILTER_VALIDATE_INT);
    if ($approvedRequestId) {
        header('Location: dashboard.php?page=approved-aid-bundles&request_id=' . $approvedRequestId . '#aid-request-' . $approvedRequestId);
        exit;
    }
}

$activePage = 'request-goods';
$database = database();
$errors = [];
$success = (string) ($_SESSION['flash_success'] ?? '');
unset($_SESSION['flash_success']);

$linkedAidRequestId = filter_input(INPUT_POST, 'linked_aid_request_id', FILTER_VALIDATE_INT)
    ?: (filter_input(INPUT_GET, 'aid_request_id', FILTER_VALIDATE_INT) ?: 0);
$linkedAidRequest = null;
$postedLines = is_array($_POST['lines'] ?? null) ? array_values($_POST['lines']) : [];
$justification = trim((string) ($_POST['justification'] ?? ''));

try {
    $inventoryItems = $database->query(
        "SELECT i.id, i.item_name, i.variety, i.quantity, c.name AS category_name
         FROM inventory_items i
         LEFT JOIN item_categories c ON c.id = i.category_id
         ORDER BY i.item_name, i.variety, i.id"
    )->fetchAll();
    $inventoryItems = array_values(array_filter(
        $inventoryItems,
        static fn(array $item): bool => !widmsIsSpectacleItem((string) $item['item_name']) && !widmsIsOpticalItem(
            (string) $item['item_name'],
            (string) $item['variety'],
            (string) ($item['category_name'] ?? '')
        )
    ));
    $targets = $database->query(
        "SELECT u.id AS sso_id, u.full_name AS sso_name, u.username,
                ds.id AS division_id, ds.name AS division_name,
                d.id AS district_id, d.name AS district_name
         FROM users u
         JOIN ds_divisions ds ON ds.id = u.ds_division_id AND ds.status = 'active'
         JOIN districts d ON d.id = ds.district_id AND d.status = 'active'
         WHERE u.role = 'social-service-officer' AND u.status = 'active'
         ORDER BY d.name, ds.name, u.full_name"
    )->fetchAll();
    if ($linkedAidRequestId > 0) {
        $linkedStatement = $database->prepare(
            "SELECT ar.id, ar.item_id, ar.quantity, ar.prescribed_power,
                    b.full_name AS beneficiary_name, b.ds_division_id,
                    i.item_name, i.variety, i.quantity AS available_stock,
                    c.name AS category_name,
                    ds.district_id, ds.name AS division_name, d.name AS district_name
             FROM aid_requests ar
             JOIN beneficiaries b ON b.id = ar.beneficiary_id
             JOIN inventory_items i ON i.id = ar.item_id
             JOIN item_categories c ON c.id = i.category_id
             JOIN ds_divisions ds ON ds.id = b.ds_division_id
             JOIN districts d ON d.id = ds.district_id
             WHERE ar.id = :id AND ar.status = 'approved'
               AND NOT EXISTS (SELECT 1 FROM distributions x WHERE x.aid_request_id = ar.id)
               AND NOT EXISTS (
                   SELECT 1 FROM goods_request_aid_requests gar
                   JOIN goods_requests goods ON goods.id = gar.goods_request_id
                   WHERE gar.aid_request_id = ar.id AND goods.status <> 'rejected'
               )
             LIMIT 1"
        );
        $linkedStatement->execute(['id' => $linkedAidRequestId]);
        $linkedAidRequest = $linkedStatement->fetch() ?: null;
        if ($linkedAidRequest && (widmsIsSpectacleItem((string) $linkedAidRequest['item_name']) || widmsIsOpticalItem(
            (string) $linkedAidRequest['item_name'],
            (string) $linkedAidRequest['variety'],
            (string) $linkedAidRequest['category_name']
        ))) {
            header('Location: dashboard.php?page=approved-aid-bundles&request_id=' . (int) $linkedAidRequest['id'] . '#aid-request-' . (int) $linkedAidRequest['id']);
            exit;
        }
    }
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $inventoryItems = [];
    $targets = [];
    $errors[] = 'Stock quota request details are unavailable.';
}

$itemMap = [];
foreach ($inventoryItems as $item) {
    $itemMap[(int) $item['id']] = $item;
}
$targetMap = [];
$districts = [];
$divisions = [];
foreach ($targets as $target) {
    $targetMap[(int) $target['sso_id']] = $target;
    $districts[(int) $target['district_id']] = $target['district_name'];
    $divisions[(int) $target['division_id']] = [
        'name' => $target['division_name'],
        'district_id' => (int) $target['district_id'],
    ];
}

if ($linkedAidRequestId > 0 && !$linkedAidRequest) {
    $errors[] = 'This approved aid request is unavailable, already fulfilled, or already included in a stock request.';
} elseif ($linkedAidRequest && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $matchingTarget = null;
    foreach ($targets as $target) {
        if ((int) $target['division_id'] === (int) $linkedAidRequest['ds_division_id']) {
            $matchingTarget = $target;
            break;
        }
    }
    if (!$matchingTarget) {
        $errors[] = 'No active Social Service Officer is assigned to the beneficiary DS Division.';
    } elseif ((int) $linkedAidRequest['available_stock'] < (int) $linkedAidRequest['quantity']) {
        $errors[] = sprintf(
            'Only %d units of %s are available in Central Stock; %d are required for this approved request.',
            (int) $linkedAidRequest['available_stock'],
            (string) $linkedAidRequest['item_name'],
            (int) $linkedAidRequest['quantity']
        );
    } else {
        $postedLines[] = [
            'item_id' => (int) $linkedAidRequest['item_id'],
            'sso_id' => (int) $matchingTarget['sso_id'],
            'quantity' => (int) $linkedAidRequest['quantity'],
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Your session expired. Please refresh the page and try again.';
    }
    if (!$postedLines) {
        $errors[] = 'Add at least one quota item before submitting.';
    }
    if (mb_strlen($justification) < 10 || mb_strlen($justification) > 1000) {
        $errors[] = 'Provide a justification between 10 and 1000 characters.';
    }

    $validatedLines = [];
    foreach ($postedLines as $index => $line) {
        if (!is_array($line)) {
            $errors[] = 'A stock quota request line is invalid.';
            continue;
        }

        $itemId = filter_var($line['item_id'] ?? null, FILTER_VALIDATE_INT);
        $ssoId = filter_var($line['sso_id'] ?? null, FILTER_VALIDATE_INT);
        $quantity = filter_var($line['quantity'] ?? null, FILTER_VALIDATE_INT);
        $lineNumber = $index + 1;

        if (!$itemId || !isset($itemMap[(int) $itemId])) {
            $errors[] = "Select a valid aid item for line {$lineNumber}.";
        }
        if (!$ssoId || !isset($targetMap[(int) $ssoId])) {
            $errors[] = "Select an active SSO and DS Division for line {$lineNumber}.";
        }
        if (!$quantity || $quantity < 1 || $quantity > 2000000000) {
            $errors[] = "Enter a valid quantity for line {$lineNumber}.";
        }
        if (!$itemId || !$ssoId || !$quantity || !isset($itemMap[(int) $itemId], $targetMap[(int) $ssoId])) {
            continue;
        }

        // Duplicate item/SSO lines are merged to keep approval and stock
        // accounting unambiguous.
        $key = (int) $ssoId . ':' . (int) $itemId;
        if (!isset($validatedLines[$key])) {
            $validatedLines[$key] = [
                'item_id' => (int) $itemId,
                'sso_id' => (int) $ssoId,
                'division_id' => (int) $targetMap[(int) $ssoId]['division_id'],
                'quantity' => 0,
            ];
        }
        $validatedLines[$key]['quantity'] += (int) $quantity;
        if ($validatedLines[$key]['quantity'] > 2000000000) {
            $errors[] = "The combined quantity for line {$lineNumber} is too large.";
        }
    }

    $itemTotals = [];
    foreach ($validatedLines as $line) {
        $itemId = $line['item_id'];
        $itemTotals[$itemId] = ($itemTotals[$itemId] ?? 0) + $line['quantity'];
    }
    ksort($itemTotals, SORT_NUMERIC);
    foreach ($itemTotals as $itemId => $total) {
        if ($total > (int) $itemMap[$itemId]['quantity']) {
            $errors[] = sprintf(t('Only %d units of %s are available in Central Stock.'), (int) $itemMap[$itemId]['quantity'], widmsAidItemName((string)$itemMap[$itemId]['item_name']));
        }
    }

    if ($linkedAidRequest) {
        if (count($validatedLines) !== 1) {
            $errors[] = 'This beneficiary stock request must contain exactly one matching allocation.';
        } else {
            $linkedLine = reset($validatedLines);
            if ((int) $linkedLine['item_id'] !== (int) $linkedAidRequest['item_id']
                || (int) $linkedLine['quantity'] !== (int) $linkedAidRequest['quantity']
                || (int) $linkedLine['division_id'] !== (int) $linkedAidRequest['ds_division_id']) {
                $errors[] = 'The item, quantity, and destination must match the approved aid request.';
            }
        }
    }

    if (!$errors && $validatedLines) {
        try {
            $database->beginTransaction();
            do {
                $batchReference = 'GRB-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(8)));
                $checkBatch = $database->prepare('SELECT 1 FROM goods_requests WHERE request_batch_ref = :reference LIMIT 1');
                $checkBatch->execute(['reference' => $batchReference]);
            } while ($checkBatch->fetchColumn());

            $lockItem = $database->prepare('SELECT id, quantity FROM inventory_items WHERE id = :item FOR UPDATE');
            $lockTarget = $database->prepare(
                "SELECT u.id FROM users u
                 JOIN ds_divisions ds ON ds.id = u.ds_division_id AND ds.status = 'active'
                 JOIN districts d ON d.id = ds.district_id AND d.status = 'active'
                 WHERE u.id = :sso AND u.role = 'social-service-officer'
                   AND u.status = 'active' AND u.ds_division_id = :division
                 FOR UPDATE"
            );
            $insert = $database->prepare(
                'INSERT INTO goods_requests
                    (aid_request_id, request_batch_ref, item_id, quantity, destination_ds_division_id,
                     destination_sso_id, justification, requested_by)
                 VALUES
                    (:aid_request, :batch, :item, :quantity, :division, :sso, :justification, :requester)'
            );
            $linkAidRequest = $database->prepare(
                'INSERT INTO goods_request_aid_requests (goods_request_id, aid_request_id)
                 VALUES (:goods_request, :aid_request)'
            );
            if ($linkedAidRequest) {
                $lockNeed = $database->prepare(
                    "SELECT ar.id, ar.item_id, ar.quantity, b.ds_division_id
                     FROM aid_requests ar
                     JOIN beneficiaries b ON b.id = ar.beneficiary_id
                     WHERE ar.id = :id AND ar.status = 'approved'
                       AND NOT EXISTS (SELECT 1 FROM distributions x WHERE x.aid_request_id = ar.id)
                       AND NOT EXISTS (
                           SELECT 1 FROM goods_request_aid_requests gar
                           JOIN goods_requests goods ON goods.id = gar.goods_request_id
                           WHERE gar.aid_request_id = ar.id AND goods.status <> 'rejected'
                       )
                     FOR UPDATE"
                );
                $lockNeed->execute(['id' => (int) $linkedAidRequest['id']]);
                $liveNeed = $lockNeed->fetch();
                if (!$liveNeed) {
                    throw new RuntimeException('This approved aid request was already fulfilled or included in another stock request.');
                }
                $onlyLine = reset($validatedLines);
                if ((int) $liveNeed['item_id'] !== (int) $onlyLine['item_id']
                    || (int) $liveNeed['quantity'] !== (int) $onlyLine['quantity']
                    || (int) $liveNeed['ds_division_id'] !== (int) $onlyLine['division_id']) {
                    throw new RuntimeException('The approved aid request changed. Review it and try again.');
                }
            }
            foreach ($itemTotals as $itemId => $total) {
                $lockItem->execute(['item' => $itemId]);
                $liveItem = $lockItem->fetch();
                if (!$liveItem || $total > (int) $liveItem['quantity']) {
                    throw new RuntimeException(t('Central Stock changed. Review the available quantity and try again.'));
                }
            }
            foreach ($validatedLines as $line) {
                $lockTarget->execute(['sso' => $line['sso_id'], 'division' => $line['division_id']]);
                if (!$lockTarget->fetchColumn()) {
                    throw new RuntimeException('An item or SSO assignment changed while this request was being submitted.');
                }
                $insert->execute([
                    'aid_request' => $linkedAidRequest ? (int) $linkedAidRequest['id'] : null,
                    'batch' => $batchReference,
                    'item' => $line['item_id'],
                    'quantity' => $line['quantity'],
                    'division' => $line['division_id'],
                    'sso' => $line['sso_id'],
                    'justification' => $justification,
                    'requester' => (int) $_SESSION['user_id'],
                ]);
                if ($linkedAidRequest) {
                    $linkAidRequest->execute([
                        'goods_request' => (int) $database->lastInsertId(),
                        'aid_request' => (int) $linkedAidRequest['id'],
                    ]);
                }
            }

            $database->commit();
            logActivity(
                'Stock Quota Requests',
                'Submitted ' . count($validatedLines) . ' goods allocation line(s) for Admin approval',
                $batchReference,
                'pending'
            );
            $_SESSION['flash_success'] = 'Quota request submitted for Admin approval.';
            unset($_SESSION['csrf_token']);
            header('Location: dashboard.php?page=' . ($linkedAidRequest ? 'my-beneficiary-requests' : 'my-goods-requests') . '#' . rawurlencode(strtolower($batchReference)));
            exit;
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            error_log($exception->getMessage());
            $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to submit the stock quota request.';
        }
    }
}

function goodsRequestItemOptions(array $items, mixed $selected = ''): void
{
    ?><option value=""><?= htmlspecialchars(t('Select an aid item'), ENT_QUOTES, 'UTF-8') ?></option><?php
    foreach ($items as $item) {
        $label = widmsAidItemName((string) $item['item_name']);
        if ((string) $item['variety'] !== '') {
            $label .= ' — ' . $item['variety'];
        }
        $stock = max(0, (int) $item['quantity']);
        $label .= ' (' . number_format($stock) . ' ' . t('available') . ')';
        ?><option value="<?= (int) $item['id'] ?>" data-stock="<?= $stock ?>" <?= $stock < 1 ? 'disabled' : '' ?> <?= (string) $selected === (string) $item['id'] ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option><?php
    }
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars(widmsLanguage(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars(t('New Goods Quota Request'), ENT_QUOTES, 'UTF-8') ?> | SWPCS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/../../includes/subject-officer-sidebar.php'; ?>
<div class="admin-shell">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="menu-button" id="menu-button">&#9776;</button>
            <h1><?= htmlspecialchars(t('Goods Quota Requests'), ENT_QUOTES, 'UTF-8') ?></h1>
        </div>
    </header>
    <main class="dashboard-content goods-allocation-page">
        <?php renderSuccessMessage($success); ?>
        <?php if ($errors): ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars(implode(' ', array_map('t', array_unique($errors))), ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <section class="aid-form-card quota-form-card">
            <div class="aid-card-header quota-form-header">
                <div>
                    <h2><?= htmlspecialchars(t('Create Quota Request'), ENT_QUOTES, 'UTF-8') ?></h2>
                    <small><?= htmlspecialchars(t('Allocate available welfare items to Social Service Officers.'), ENT_QUOTES, 'UTF-8') ?></small>
                </div>
                <a class="outline-action" href="dashboard.php?page=my-goods-requests"><?= htmlspecialchars(t('View Request History'), ENT_QUOTES, 'UTF-8') ?></a>
            </div>

            <?php if (!$inventoryItems || !$targets): ?>
                <div class="goods-form-unavailable"><?= htmlspecialchars(t(!$inventoryItems ? 'No inventory items are available.' : 'No active SSO division assignments are available.'), ENT_QUOTES, 'UTF-8') ?></div>
            <?php else: ?>
            <form method="post" class="aid-request-form quota-request-form" id="quota-request-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <?php if ($linkedAidRequest): ?>
                    <input type="hidden" name="linked_aid_request_id" value="<?= (int) $linkedAidRequest['id'] ?>">
                    <aside class="quota-linked-request" role="status">
                        <div>
                            <span><?= htmlspecialchars(t('Approved Aid Request'), ENT_QUOTES, 'UTF-8') ?></span>
                            <strong>AR-<?= str_pad((string) $linkedAidRequest['id'], 4, '0', STR_PAD_LEFT) ?> &middot; <?= htmlspecialchars((string) $linkedAidRequest['beneficiary_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <small><?= htmlspecialchars($linkedAidRequest['district_name'] . ' / ' . $linkedAidRequest['division_name'], ENT_QUOTES, 'UTF-8') ?></small>
                        </div>
                        <div class="quota-linked-stock">
                            <span><?= htmlspecialchars(widmsAidItemName((string) $linkedAidRequest['item_name']), ENT_QUOTES, 'UTF-8') ?></span>
                            <strong><?= number_format((int) $linkedAidRequest['available_stock']) ?> <?= htmlspecialchars(t('available'), ENT_QUOTES, 'UTF-8') ?></strong>
                            <small><?= number_format((int) $linkedAidRequest['quantity']) ?> <?= htmlspecialchars(t('required'), ENT_QUOTES, 'UTF-8') ?></small>
                        </div>
                    </aside>
                <?php endif; ?>

                <fieldset id="quota-editor">
                    <legend>📍 <?= htmlspecialchars(t('Quota Destination'), ENT_QUOTES, 'UTF-8') ?></legend>
                    <div class="aid-form-grid three-columns">
                        <label><?= htmlspecialchars(t('District'), ENT_QUOTES, 'UTF-8') ?> *
                            <select id="quota-district"><option value=""><?= htmlspecialchars(t('Select District'), ENT_QUOTES, 'UTF-8') ?></option>
                                <?php foreach ($districts as $id => $name): ?><option value="<?= (int) $id ?>"><?= htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                            </select>
                        </label>
                        <label><?= htmlspecialchars(t('D.S. Division'), ENT_QUOTES, 'UTF-8') ?> *
                            <select id="quota-division" disabled><option value=""><?= htmlspecialchars(t('Select DS Division'), ENT_QUOTES, 'UTF-8') ?></option>
                                <?php foreach ($divisions as $id => $division): ?><option value="<?= (int) $id ?>" data-district="<?= (int) $division['district_id'] ?>" hidden><?= htmlspecialchars((string) $division['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                            </select>
                        </label>
                        <label><?= htmlspecialchars(t('Receiving SSO'), ENT_QUOTES, 'UTF-8') ?> *
                            <select id="quota-sso" disabled><option value=""><?= htmlspecialchars(t('Select SSO'), ENT_QUOTES, 'UTF-8') ?></option>
                                <?php foreach ($targets as $target): ?><option value="<?= (int) $target['sso_id'] ?>" data-division="<?= (int) $target['division_id'] ?>" hidden><?= htmlspecialchars((string) $target['sso_name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>📦 <?= htmlspecialchars(t('Quota Item Details'), ENT_QUOTES, 'UTF-8') ?></legend>
                    <div class="aid-form-grid three-columns quota-item-grid">
                        <label><?= htmlspecialchars(t('Aid Item'), ENT_QUOTES, 'UTF-8') ?> *
                            <select id="quota-item"><?php goodsRequestItemOptions($inventoryItems); ?></select>
                        </label>
                        <label><?= htmlspecialchars(t('Available Central Stock'), ENT_QUOTES, 'UTF-8') ?>
                            <input id="quota-stock" type="text" value="—" readonly aria-live="polite">
                        </label>
                        <label><?= htmlspecialchars(t('Quota Quantity'), ENT_QUOTES, 'UTF-8') ?> *
                            <input id="quota-quantity" type="number" min="1" max="2000000000" step="1" value="1" aria-describedby="quota-quantity-error">
                            <span id="quota-quantity-error" class="quota-inline-error" role="alert" hidden></span>
                        </label>
                    </div>
                    <div class="quota-editor-actions">
                        <button type="button" class="outline-action" id="quota-cancel-edit" hidden><?= htmlspecialchars(t('Cancel Edit'), ENT_QUOTES, 'UTF-8') ?></button>
                        <button type="button" class="admin-primary-action" id="quota-add-item">+ <?= htmlspecialchars(t('Add Item'), ENT_QUOTES, 'UTF-8') ?></button>
                    </div>
                </fieldset>

                <section class="quota-items-section" id="quota-items-section" aria-labelledby="quota-items-title" hidden>
                    <h3 id="quota-items-title"><?= htmlspecialchars(t('Quota Items'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <div class="admin-data-table-wrap quota-items-wrap">
                        <table class="admin-data-table quota-items-table">
                            <thead><tr><th>#</th><th><?= htmlspecialchars(t('District / DS Division'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Receiving SSO'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Aid Item'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Available Stock'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Quantity'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Actions'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead>
                            <tbody id="quota-items-body"><tr class="quota-empty-row"><td colspan="7"><?= htmlspecialchars(t('No quota items added yet. Select a destination and item above.'), ENT_QUOTES, 'UTF-8') ?></td></tr></tbody>
                        </table>
                    </div>
                    <div id="quota-hidden-lines"></div>
                </section>

                <fieldset class="quota-justification-fieldset">
                    <legend>📝 <?= htmlspecialchars(t('Quota Justification'), ENT_QUOTES, 'UTF-8') ?> <span aria-hidden="true">*</span></legend>
                    <div class="quota-justification-content">
                        <label class="visually-hidden" for="quota-justification"><?= htmlspecialchars(t('Quota Justification'), ENT_QUOTES, 'UTF-8') ?></label>
                        <textarea id="quota-justification" name="justification" rows="3" minlength="10" maxlength="1000" required aria-describedby="quota-justification-help" placeholder="<?= htmlspecialchars(t('Explain why these items are required for the selected SSO divisions.'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($justification, ENT_QUOTES, 'UTF-8') ?></textarea>
                        <small id="quota-justification-help" class="form-field-help"><?= htmlspecialchars(t('This note will be visible to the Admin during approval.'), ENT_QUOTES, 'UTF-8') ?></small>
                    </div>
                </fieldset>

                <div class="goods-review-bar">
                    <aside class="goods-request-summary" aria-live="polite">
                        <span class="goods-summary-title"><?= htmlspecialchars(t('Request Summary'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="goods-summary-metric"><b id="goods-line-count">0</b><small><?= htmlspecialchars(t('Items'), ENT_QUOTES, 'UTF-8') ?></small></span>
                        <span class="goods-summary-metric"><b id="goods-division-count">0</b><small><?= htmlspecialchars(t('SSO Divisions'), ENT_QUOTES, 'UTF-8') ?></small></span>
                        <span class="goods-summary-metric"><b id="goods-unit-count">0</b><small><?= htmlspecialchars(t('Total Units'), ENT_QUOTES, 'UTF-8') ?></small></span>
                    </aside>
                    <button type="submit" class="admin-primary-action" id="quota-submit" disabled><?= htmlspecialchars(t('Submit for Admin Approval'), ENT_QUOTES, 'UTF-8') ?> <span aria-hidden="true">&#8594;</span></button>
                </div>
                <noscript><p class="alert alert-danger"><?= htmlspecialchars(t('JavaScript is required to add quota items.'), ENT_QUOTES, 'UTF-8') ?></p></noscript>
            </form>
            <?php endif; ?>
        </section>

    </main>
</div>

<script src="assets/js/admin-dashboard.js"></script>
<script>
(() => {
    const form = document.getElementById('quota-request-form');
    if (!form) return;
    const district = document.getElementById('quota-district');
    const division = document.getElementById('quota-division');
    const sso = document.getElementById('quota-sso');
    const item = document.getElementById('quota-item');
    const quantity = document.getElementById('quota-quantity');
    const stock = document.getElementById('quota-stock');
    const error = document.getElementById('quota-quantity-error');
    const add = document.getElementById('quota-add-item');
    const cancel = document.getElementById('quota-cancel-edit');
    const body = document.getElementById('quota-items-body');
    const tableSection = document.getElementById('quota-items-section');
    const hidden = document.getElementById('quota-hidden-lines');
    const submit = document.getElementById('quota-submit');
    const initialRows = <?= json_encode($postedLines, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR) ?>;
    const ui = <?= json_encode([
        'add' => '+ ' . t('Add Item'), 'update' => t('Update Item'),
        'edit' => t('Edit'), 'delete' => t('Delete'),
        'empty' => t('No quota items added yet. Select a destination and item above.'),
        'stock' => t('units'), 'select' => t('Complete the destination, item, and quantity first.'),
        'invalidQuantity' => t('Enter a whole-number quantity of at least 1.'),
        'only' => t('Only %d units are available in Central Stock.'),
        'duplicate' => t('This item is already added for this SSO. Edit the existing row.'),
        'addFirst' => t('Add at least one quota item before submitting.'),
        'finishEdit' => t('Finish or cancel the current edit before submitting.'),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR) ?>;
    const rows = [];
    let editIndex = -1;

    const option = (select, value) => [...select.options].find((entry) => entry.value === String(value));
    const selectedText = (select, value) => option(select, value)?.textContent?.trim() || '—';
    const stockFor = (id) => Number(option(item, id)?.dataset.stock || 0);
    const usedStock = (id, except = -1) => rows.reduce((total, row, index) => total + (index !== except && row.itemId === String(id) ? row.quantity : 0), 0);
    const showError = (message) => {
        error.textContent = message;
        error.hidden = !message;
        quantity.setAttribute('aria-invalid', message ? 'true' : 'false');
    };
    const filter = (select, parent, key) => {
        for (const entry of select.options) {
            if (!entry.value) continue;
            entry.hidden = entry.disabled = !parent || entry.dataset[key] !== parent;
        }
        select.disabled = !parent || ![...select.options].some((entry) => entry.value && !entry.disabled);
        if (option(select, select.value)?.disabled) select.value = '';
    };
    const updateStock = () => {
        const available = item.value ? stockFor(item.value) : null;
        stock.value = available === null ? '—' : `${available.toLocaleString()} ${ui.stock}`;
        const count = Number(quantity.value);
        if (available !== null && Number.isInteger(count) && count > 0 && count + usedStock(item.value, editIndex) > available) {
            showError(ui.only.replace('%d', Math.max(0, available - usedStock(item.value, editIndex)).toLocaleString()));
        } else {
            showError('');
        }
    };
    const resetEditor = (keepDestination = false) => {
        editIndex = -1;
        if (!keepDestination) {
            district.value = '';
            division.value = '';
            sso.value = '';
            filter(division, '', 'district');
            filter(sso, '', 'division');
        }
        item.value = '';
        quantity.value = '1';
        add.textContent = ui.add;
        cancel.hidden = true;
        showError('');
        updateStock();
        body.querySelector('.is-editing')?.classList.remove('is-editing');
    };
    const hiddenField = (name, value) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = String(value);
        hidden.appendChild(input);
    };
    const render = () => {
        tableSection.hidden = rows.length === 0;
        body.replaceChildren();
        hidden.replaceChildren();
        if (!rows.length) {
            const empty = document.createElement('tr');
            empty.className = 'quota-empty-row';
            const cell = document.createElement('td');
            cell.colSpan = 7;
            cell.textContent = ui.empty;
            empty.appendChild(cell);
            body.appendChild(empty);
        }
        rows.forEach((row, index) => {
            const tr = document.createElement('tr');
            if (index === editIndex) tr.className = 'is-editing';
            const values = [String(index + 1), `${selectedText(district, row.districtId)} / ${selectedText(division, row.divisionId)}`, selectedText(sso, row.ssoId), selectedText(item, row.itemId), stockFor(row.itemId).toLocaleString(), row.quantity.toLocaleString()];
            values.forEach((value) => { const td = document.createElement('td'); td.textContent = value; tr.appendChild(td); });
            const actions = document.createElement('td');
            const editButton = document.createElement('button');
            editButton.type = 'button'; editButton.className = 'outline-action'; editButton.textContent = ui.edit;
            editButton.dataset.action = 'edit'; editButton.dataset.index = String(index);
            const deleteButton = document.createElement('button');
            deleteButton.type = 'button'; deleteButton.className = 'quota-delete-button'; deleteButton.textContent = ui.delete;
            deleteButton.dataset.action = 'delete'; deleteButton.dataset.index = String(index);
            actions.append(editButton, deleteButton); tr.appendChild(actions); body.appendChild(tr);
            hiddenField(`lines[${index}][item_id]`, row.itemId);
            hiddenField(`lines[${index}][sso_id]`, row.ssoId);
            hiddenField(`lines[${index}][quantity]`, row.quantity);
        });
        document.getElementById('goods-line-count').textContent = String(rows.length);
        document.getElementById('goods-division-count').textContent = String(new Set(rows.map((row) => row.divisionId)).size);
        document.getElementById('goods-unit-count').textContent = rows.reduce((total, row) => total + row.quantity, 0).toLocaleString();
        submit.disabled = rows.length === 0;
    };

    district.addEventListener('change', () => {
        division.value = ''; sso.value = '';
        filter(division, district.value, 'district');
        filter(sso, '', 'division');
    });
    division.addEventListener('change', () => {
        sso.value = '';
        filter(sso, division.value, 'division');
    });
    item.addEventListener('change', updateStock);
    quantity.addEventListener('input', updateStock);
    add.addEventListener('click', () => {
        for (const select of [district, division, sso, item]) {
            if (!select.value) { select.focus(); select.setCustomValidity(ui.select); select.reportValidity(); select.setCustomValidity(''); return; }
        }
        const count = Number(quantity.value);
        if (!Number.isSafeInteger(count) || count < 1 || count > 2000000000) {
            showError(ui.invalidQuantity); quantity.focus(); return;
        }
        if (rows.some((row, index) => index !== editIndex && row.ssoId === sso.value && row.itemId === item.value)) {
            showError(ui.duplicate); quantity.focus(); return;
        }
        if (count + usedStock(item.value, editIndex) > stockFor(item.value)) {
            updateStock(); quantity.focus(); return;
        }
        const row = {districtId: district.value, divisionId: division.value, ssoId: sso.value, itemId: item.value, quantity: count};
        const wasEditing = editIndex >= 0;
        if (!wasEditing) rows.push(row); else rows[editIndex] = row;
        resetEditor(!wasEditing); render();
    });
    cancel.addEventListener('click', () => resetEditor());
    body.addEventListener('click', (event) => {
        const button = event.target.closest('button[data-action]');
        if (!button) return;
        const index = Number(button.dataset.index);
        if (button.dataset.action === 'delete') {
            rows.splice(index, 1);
            if (editIndex === index) resetEditor();
            else if (editIndex > index) editIndex--;
            render(); return;
        }
        const row = rows[index];
        editIndex = index;
        district.value = row.districtId;
        filter(division, district.value, 'district'); division.value = row.divisionId;
        filter(sso, division.value, 'division'); sso.value = row.ssoId;
        item.value = row.itemId; quantity.value = String(row.quantity);
        add.textContent = ui.update; cancel.hidden = false;
        updateStock(); render();
        document.getElementById('quota-editor').scrollIntoView({behavior: 'smooth', block: 'start'});
        district.focus({preventScroll: true});
    });
    form.addEventListener('submit', (event) => {
        if (!rows.length) { event.preventDefault(); showError(ui.addFirst); add.focus(); return; }
        if (editIndex >= 0) { event.preventDefault(); showError(ui.finishEdit); add.focus(); return; }
        const invalid = rows.find((row) => usedStock(row.itemId) > stockFor(row.itemId));
        if (invalid) {
            event.preventDefault(); item.value = invalid.itemId;
            updateStock();
            showError(ui.only.replace('%d', stockFor(invalid.itemId).toLocaleString()));
            quantity.focus();
        }
    });

    for (const posted of initialRows) {
        if (!posted || typeof posted !== 'object') continue;
        const target = option(sso, posted.sso_id);
        const selectedItem = option(item, posted.item_id);
        const selectedDivision = target ? option(division, target.dataset.division) : null;
        const count = Number(posted.quantity);
        if (target?.value && selectedItem?.value && selectedDivision?.value && Number.isSafeInteger(count) && count > 0) {
            rows.push({districtId: selectedDivision.dataset.district, divisionId: selectedDivision.value, ssoId: target.value, itemId: selectedItem.value, quantity: count});
        }
    }
    resetEditor(); render();
})();
</script>
</body>
</html>
