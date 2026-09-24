<?php
declare(strict_types=1);

requireRole('subject-officer');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/activity.php';
require_once __DIR__ . '/../../includes/optical-stock.php';
require_once __DIR__ . '/../../includes/ui-messages.php';

$activePage = 'optical-aid-requests';
$database = database();
$userId = (int) $_SESSION['user_id'];
$errors = [];
$success = (string) ($_SESSION['flash_success'] ?? '');
$justification = trim((string) ($_POST['justification'] ?? ''));
$highlightRequestId = filter_input(INPUT_GET, 'request_id', FILTER_VALIDATE_INT) ?: 0;
unset($_SESSION['flash_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedIds = array_values(array_unique(array_filter(array_map(
        static fn(mixed $value): int => (int) filter_var($value, FILTER_VALIDATE_INT),
        is_array($_POST['request_ids'] ?? null) ? $_POST['request_ids'] : []
    ))));

    if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = t('Your session expired. Please refresh the page and try again.');
    }
    if ($selectedIds === [] || count($selectedIds) > 50) {
        $errors[] = t('Select between 1 and 50 optical requests.');
    }
    if (mb_strlen($justification) < 10 || mb_strlen($justification) > 1000) {
        $errors[] = t('Provide a justification between 10 and 1000 characters.');
    }

    if ($errors === []) {
        sort($selectedIds, SORT_NUMERIC);
        try {
            $database->beginTransaction();
            do {
                $batchReference = 'GRB-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(8)));
                $statement = $database->prepare('SELECT 1 FROM goods_requests WHERE request_batch_ref = :reference LIMIT 1');
                $statement->execute(['reference' => $batchReference]);
            } while ($statement->fetchColumn());

            $requestStatement = $database->prepare(
                "SELECT ar.id, ar.item_id, ar.quantity, ar.prescribed_power,
                        b.ds_division_id, i.item_name, i.variety, c.name AS category_name
                 FROM aid_requests ar
                 JOIN beneficiaries b ON b.id = ar.beneficiary_id
                 JOIN inventory_items i ON i.id = ar.item_id
                 JOIN item_categories c ON c.id = i.category_id
                 JOIN users submitter ON submitter.id = ar.submitted_by
                 WHERE ar.id = :id
                   AND (ar.submitted_by = :user_id OR submitter.role IN ('social-service-officer', 'admin'))
                   AND ar.status = 'approved'
                   AND ar.prescribed_power IS NOT NULL
                   AND NOT EXISTS (SELECT 1 FROM distributions d WHERE d.aid_request_id = ar.id)
                   AND NOT EXISTS (
                       SELECT 1
                       FROM goods_request_aid_requests link
                       JOIN goods_requests goods ON goods.id = link.goods_request_id
                       WHERE link.aid_request_id = ar.id AND goods.status <> 'rejected'
                   )
                 FOR UPDATE"
            );
            $insertGoods = $database->prepare(
                "INSERT INTO goods_requests
                    (aid_request_id, request_batch_ref, item_id, quantity,
                     destination_ds_division_id, destination_sso_id, justification, requested_by)
                 VALUES
                    (:aid_request_id, :batch_reference, :item_id, :quantity,
                     :division_id, NULL, :justification, :requested_by)"
            );
            $insertLink = $database->prepare(
                'INSERT INTO goods_request_aid_requests (goods_request_id, aid_request_id)
                 VALUES (:goods_request_id, :aid_request_id)'
            );

            foreach ($selectedIds as $requestId) {
                $requestStatement->execute(['id' => $requestId, 'user_id' => $userId]);
                $request = $requestStatement->fetch();
                if (!$request || !widmsIsOpticalItem(
                    (string) $request['item_name'],
                    (string) $request['variety'],
                    (string) $request['category_name']
                )) {
                    throw new RuntimeException(t('One selected request is no longer available for optical stock processing.'));
                }

                $available = widmsOpticalPowerAvailable(
                    $database,
                    (int) $request['item_id'],
                    (float) $request['prescribed_power'],
                    true
                );
                if ($available < (int) $request['quantity']) {
                    throw new RuntimeException(sprintf(
                        t('Power %s has only %d units available; %d are required.'),
                        sprintf('%+.2f', (float) $request['prescribed_power']),
                        $available,
                        (int) $request['quantity']
                    ));
                }

                $insertGoods->execute([
                    'aid_request_id' => (int) $request['id'],
                    'batch_reference' => $batchReference,
                    'item_id' => (int) $request['item_id'],
                    'quantity' => (int) $request['quantity'],
                    'division_id' => (int) $request['ds_division_id'],
                    'justification' => $justification,
                    'requested_by' => $userId,
                ]);
                $insertLink->execute([
                    'goods_request_id' => (int) $database->lastInsertId(),
                    'aid_request_id' => (int) $request['id'],
                ]);
            }

            $database->commit();
            logActivity(
                'Optical Aid Requests',
                'Submitted ' . count($selectedIds) . ' power-matched optical request(s) for Admin approval',
                $batchReference,
                'pending'
            );
            $_SESSION['flash_success'] = 'Power-matched optical goods request submitted for Admin approval.';
            unset($_SESSION['csrf_token']);
            header('Location: dashboard.php?page=my-goods-requests#' . rawurlencode(strtolower($batchReference)));
            exit;
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            error_log($exception->getMessage());
            $errors[] = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : t('Unable to submit the optical goods request.');
        }
    }
}

$availableRequests = [];
$unavailableCount = 0;
try {
    $statement = $database->prepare(
        "SELECT ar.id, ar.quantity, ar.prescribed_power, ar.created_at,
                b.full_name AS beneficiary_name, b.nic,
                i.id AS item_id, i.item_name, i.variety,
                c.name AS category_name, ds.name AS division_name, d.name AS district_name,
                submitter.full_name AS submitter_name, submitter.role AS submitter_role
         FROM aid_requests ar
         JOIN beneficiaries b ON b.id = ar.beneficiary_id
         JOIN inventory_items i ON i.id = ar.item_id
         JOIN item_categories c ON c.id = i.category_id
         JOIN users submitter ON submitter.id = ar.submitted_by
         JOIN ds_divisions ds ON ds.id = b.ds_division_id
         JOIN districts d ON d.id = ds.district_id
         WHERE (ar.submitted_by = :user_id OR submitter.role IN ('social-service-officer', 'admin'))
           AND ar.status = 'approved'
           AND ar.prescribed_power IS NOT NULL
           AND NOT EXISTS (SELECT 1 FROM distributions distribution WHERE distribution.aid_request_id = ar.id)
           AND NOT EXISTS (
               SELECT 1
               FROM goods_request_aid_requests link
               JOIN goods_requests goods ON goods.id = link.goods_request_id
               WHERE link.aid_request_id = ar.id AND goods.status <> 'rejected'
           )
         ORDER BY ar.created_at, ar.id"
    );
    $statement->execute(['user_id' => $userId]);
    $balancesByItem = [];
    foreach ($statement->fetchAll() as $request) {
        if (!widmsIsOpticalItem(
            (string) $request['item_name'],
            (string) $request['variety'],
            (string) $request['category_name']
        )) {
            continue;
        }
        $itemId = (int) $request['item_id'];
        $balancesByItem[$itemId] ??= widmsOpticalPowerBalances($database, $itemId);
        $available = max(0, (int) ($balancesByItem[$itemId][widmsPowerKey((float) $request['prescribed_power'])] ?? 0));
        $request['power_available'] = $available;
        if ($available >= (int) $request['quantity']) {
            $availableRequests[] = $request;
        } else {
            $unavailableCount++;
        }
    }
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $errors[] = t('Optical aid requests are temporarily unavailable.');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars(t('Power-Matched Optical Requests'), ENT_QUOTES, 'UTF-8') ?> | WIDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css?v=92" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/../../includes/subject-officer-sidebar.php'; ?>
<div class="admin-shell">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="menu-button" id="menu-button" type="button" aria-label="<?= htmlspecialchars(t('Open navigation'), ENT_QUOTES, 'UTF-8') ?>">&#9776;</button>
            <div><h1><?= htmlspecialchars(t('Power-Matched Optical Requests'), ENT_QUOTES, 'UTF-8') ?></h1><p><?= htmlspecialchars(t('Select approved spectacles and contact-lens requests only when their exact signed power is in Central Stock.'), ENT_QUOTES, 'UTF-8') ?></p></div>
        </div>
    </header>
    <main class="dashboard-content optical-request-page widms-unified-ui">
        <?php renderSuccessMessage($success); ?>
        <?php if ($errors): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars(implode(' ', $errors), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

        <section class="admin-data-card optical-request-card">
            <div class="admin-data-header">
                <div><h2><?= htmlspecialchars(t('Available Power Matches'), ENT_QUOTES, 'UTF-8') ?></h2><p><?= htmlspecialchars(t('Each selected request keeps its prescribed power through approval, release, and beneficiary distribution.'), ENT_QUOTES, 'UTF-8') ?></p></div>
                <span class="fulfillment-count"><?= count($availableRequests) ?> <?= htmlspecialchars(t('available'), ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <?php if ($unavailableCount > 0): ?>
                <div class="optical-stock-note"><?= htmlspecialchars(sprintf(t('%d approved optical request(s) are hidden because the exact power is not currently in stock.'), $unavailableCount), ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="post" class="optical-request-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="admin-data-table-wrap">
                    <table class="admin-data-table optical-request-table">
                        <thead><tr>
                            <th class="optical-select-column"><?= htmlspecialchars(t('Select'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(t('Request'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(t('Beneficiary'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(t('Submitted By'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(t('Aid Item'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(t('Prescribed Power'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(t('Required'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(t('Exact-Power Stock'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(t('Location'), ENT_QUOTES, 'UTF-8') ?></th>
                        </tr></thead>
                        <tbody>
                        <?php if ($availableRequests === []): ?>
                            <tr><td colspan="9" class="admin-empty-row"><?= htmlspecialchars(t('No approved optical requests currently have an exact power match in Central Stock.'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                        <?php else: foreach ($availableRequests as $request): ?>
                            <?php $requestId = (int) $request['id']; ?>
                            <tr id="aid-request-<?= $requestId ?>" class="admin-notification-target" tabindex="-1">
                                <td><input type="checkbox" name="request_ids[]" value="<?= $requestId ?>" aria-label="<?= htmlspecialchars(t('Select request') . ' AR-' . str_pad((string) $requestId, 4, '0', STR_PAD_LEFT), ENT_QUOTES, 'UTF-8') ?>" <?= $highlightRequestId === $requestId ? 'checked' : '' ?>></td>
                                <td><strong>AR-<?= str_pad((string) $requestId, 4, '0', STR_PAD_LEFT) ?></strong></td>
                                <td><strong><?= htmlspecialchars((string) $request['beneficiary_name'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars((string) ($request['nic'] ?: t('No NIC')), ENT_QUOTES, 'UTF-8') ?></small></td>
                                <td><strong><?= htmlspecialchars((string) $request['submitter_name'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars(t(ucwords(str_replace('-', ' ', (string) $request['submitter_role']))), ENT_QUOTES, 'UTF-8') ?></small></td>
                                <td><?= htmlspecialchars((string) $request['item_name'] . ((string) $request['variety'] !== '' ? ' — ' . $request['variety'] : ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="optical-power-badge"><?= sprintf('%+.2f', (float) $request['prescribed_power']) ?></span></td>
                                <td><?= number_format((int) $request['quantity']) ?></td>
                                <td><span class="optical-stock-available"><?= number_format((int) $request['power_available']) ?> <?= htmlspecialchars(t('available'), ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><?= htmlspecialchars($request['district_name'] . ' / ' . $request['division_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($availableRequests): ?>
                    <div class="optical-request-submit">
                        <label>
                            <span><?= htmlspecialchars(t('Request Justification'), ENT_QUOTES, 'UTF-8') ?> *</span>
                            <textarea name="justification" minlength="10" maxlength="1000" required placeholder="<?= htmlspecialchars(t('Explain why these power-matched items should be released to you for beneficiary distribution.'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($justification, ENT_QUOTES, 'UTF-8') ?></textarea>
                        </label>
                        <button class="admin-primary-action" type="submit"><?= htmlspecialchars(t('Submit Selected for Admin Approval'), ENT_QUOTES, 'UTF-8') ?> &rarr;</button>
                    </div>
                <?php endif; ?>
            </form>
        </section>
    </main>
</div>
<script src="assets/js/admin-dashboard.js"></script>
</body>
</html>
