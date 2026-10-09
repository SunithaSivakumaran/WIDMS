<?php
declare(strict_types=1);

requireRole('admin');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/activity.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_once __DIR__ . '/../../includes/optical-stock.php';
require_once __DIR__ . '/../../includes/spectacle-categories.php';

$activePage = 'goods-requests';
$database = database();
$errors = [];
$success = (string) ($_SESSION['flash_success'] ?? '');
unset($_SESSION['flash_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $batchReference = trim((string) ($_POST['batch_reference'] ?? ''));
    $legacyRequestId = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
    $decision = (string) ($_POST['decision'] ?? '');
    $reason = trim((string) ($_POST['rejection_reason'] ?? ''));

    if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Your session expired. Please refresh the page and try again.';
    }
    if (!in_array($decision, ['approved', 'rejected'], true)) {
        $errors[] = 'Invalid decision.';
    }
    if ($batchReference === '' && !$legacyRequestId) {
        $errors[] = 'Select a stock quota request.';
    }
    if ($batchReference !== '' && !preg_match('/^[A-Za-z0-9-]{1,40}$/', $batchReference)) {
        $errors[] = 'Invalid quota batch reference.';
    }
    if ($decision === 'rejected' && $reason === '') {
        $errors[] = 'A rejection reason is required.';
    }
    if (mb_strlen($reason) > 500) {
        $errors[] = 'The rejection reason must not exceed 500 characters.';
    }

    if (!$errors) {
        try {
            $database->beginTransaction();
            $sql = "SELECT g.*, i.item_name, i.variety, ds.name AS division_name,
                           d.name AS district_name, target.full_name AS sso_name,
                           (SELECT COUNT(*) FROM goods_request_aid_requests link
                            WHERE link.goods_request_id = g.id) AS linked_needs
                    FROM goods_requests g
                    JOIN inventory_items i ON i.id = g.item_id
                    JOIN ds_divisions ds ON ds.id = g.destination_ds_division_id
                    JOIN districts d ON d.id = ds.district_id
                    LEFT JOIN users target ON target.id = g.destination_sso_id
                    WHERE " . ($batchReference !== ''
                        ? "g.request_batch_ref = :reference"
                        : "g.id = :request_id AND g.request_batch_ref IS NULL") . "
                      AND g.status = 'pending-admin-approval'
                    ORDER BY g.id FOR UPDATE";
            $requestStatement = $database->prepare($sql);
            $requestStatement->execute($batchReference !== ''
                ? ['reference' => $batchReference]
                : ['request_id' => $legacyRequestId]);
            $batchRows = $requestStatement->fetchAll();
            if ($batchRows === []) {
                throw new RuntimeException('This quota request was already reviewed or does not exist.');
            }

            $requesterIds = array_unique(array_map('intval', array_column($batchRows, 'requested_by')));
            if (count($requesterIds) !== 1) {
                throw new RuntimeException('This quota batch has inconsistent requester details.');
            }

            if ($decision === 'approved') {
                $requestedByItem = [];
                foreach ($batchRows as $row) {
                    $itemId = (int) $row['item_id'];
                    $requestedByItem[$itemId] = ($requestedByItem[$itemId] ?? 0) + (int) $row['quantity'];
                }
                ksort($requestedByItem, SORT_NUMERIC);

                $stockLock = $database->prepare('SELECT quantity FROM inventory_items WHERE id = :item FOR UPDATE');
                $reservedLock = $database->prepare(
                    "SELECT id, quantity FROM goods_requests
                     WHERE item_id = :item AND status = 'approved-awaiting-dispatch'
                     ORDER BY id FOR UPDATE"
                );
                foreach ($requestedByItem as $itemId => $requestedQuantity) {
                    $stockLock->execute(['item' => $itemId]);
                    $centralStock = $stockLock->fetchColumn();
                    if ($centralStock === false) {
                        throw new RuntimeException('One of the requested stock items no longer exists.');
                    }
                    $reservedLock->execute(['item' => $itemId]);
                    $reserved = array_sum(array_map('intval', $reservedLock->fetchAll(PDO::FETCH_COLUMN, 1)));
                    $available = max(0, (int) $centralStock - $reserved);
                    if ($available < $requestedQuantity) {
                        $matching = array_values(array_filter($batchRows, static fn(array $row): bool => (int) $row['item_id'] === $itemId));
                        throw new RuntimeException((string) ($matching[0]['item_name'] ?? 'Requested item') . ' exceeds unreserved Central Stock. Available for approval: ' . $available . '.');
                    }
                }

                $linkedPowerStatement = $database->prepare(
                    "SELECT ar.item_id, ar.spectacle_category_id,
                            i.item_name, i.variety, c.name AS category_name
                     FROM goods_request_aid_requests link
                     JOIN aid_requests ar ON ar.id = link.aid_request_id
                     JOIN inventory_items i ON i.id = ar.item_id
                     JOIN item_categories c ON c.id = i.category_id
                     WHERE link.goods_request_id = :goods_request_id"
                );
                foreach ($batchRows as $row) {
                    $linkedPowerStatement->execute(['goods_request_id' => (int) $row['id']]);
                    foreach ($linkedPowerStatement->fetchAll() as $linkedRequest) {
                        if (widmsIsSpectacleItem((string) $linkedRequest['item_name'])) {
                            $categoryId = (int) ($linkedRequest['spectacle_category_id'] ?? 0);
                            $categoryBalances = widmsSpectacleCategoryBalances(
                                $database,
                                (int) $linkedRequest['item_id']
                            );
                            if ($categoryId < 1 || ($categoryBalances[$categoryId] ?? -1) < 0) {
                                throw new RuntimeException('The selected spectacle type is no longer available in Central Stock.');
                            }
                            continue;
                        }
                    }
                }

                $targetLock = $database->prepare(
                    "SELECT id FROM users
                     WHERE id = :sso AND role = 'social-service-officer'
                       AND status = 'active' AND ds_division_id = :division
                     FOR UPDATE"
                );
                foreach ($batchRows as $row) {
                    $ssoId = (int) ($row['destination_sso_id'] ?? 0);
                    if ($ssoId < 1) {
                        if ((int) $row['linked_needs'] > 0) {
                            continue;
                        }
                        throw new RuntimeException('Every general quota allocation must have a receiving Social Service Officer.');
                    }
                    $targetLock->execute(['sso' => $ssoId, 'division' => (int) $row['destination_ds_division_id']]);
                    if (!$targetLock->fetchColumn()) {
                        throw new RuntimeException('A selected SSO is no longer active in the assigned DS Division. Reject this batch and request a replacement.');
                    }
                }
            }

            $status = $decision === 'approved' ? 'approved-awaiting-dispatch' : 'rejected';
            $update = $database->prepare(
                "UPDATE goods_requests SET status = :status, rejection_reason = :reason,
                     approved_by = :admin, approved_at = NOW()
                 WHERE id = :id AND status = 'pending-admin-approval'"
            );
            foreach ($batchRows as $row) {
                $update->execute([
                    'status' => $status,
                    'reason' => $decision === 'rejected' ? $reason : null,
                    'admin' => (int) $_SESSION['user_id'],
                    'id' => (int) $row['id'],
                ]);
                if ($update->rowCount() !== 1) {
                    throw new RuntimeException('This quota request changed while it was being reviewed.');
                }
            }

            $firstId = (int) $batchRows[0]['id'];
            $displayReference = $batchReference !== '' ? $batchReference : 'GR-' . str_pad((string) $firstId, 4, '0', STR_PAD_LEFT);
            $decisionLabel = $decision === 'approved' ? 'approved' : 'rejected';
            $totalUnits = array_sum(array_map('intval', array_column($batchRows, 'quantity')));

            if ($decision === 'approved') {
                $storeKeepers=$database->query("SELECT id FROM users WHERE role='store-keeper' AND status='active'")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($storeKeepers as $storeKeeperId) notifyUser(
                    $database,(int)$storeKeeperId,
                    'goods-release-'.$firstId.'-'.$storeKeeperId,
                    'Approved aid bundle ready for release',
                    $displayReference.' is ready for Store Keeper release',
                    $totalUnits.' units approved for the requesting Subject Officer.',
                    'dashboard.php?page=approved-dispatches#goods-request-'.$firstId
                );
                $allocationsBySso = [];
                foreach ($batchRows as $row) {
                    $ssoId = (int) $row['destination_sso_id'];
                    if ($ssoId < 1 || (int) $row['linked_needs'] > 0) {
                        continue;
                    }
                    if (!isset($allocationsBySso[$ssoId])) {
                        $allocationsBySso[$ssoId] = ['first_id' => (int) $row['id'], 'lines' => 0, 'units' => 0];
                    }
                    $allocationsBySso[$ssoId]['lines']++;
                    $allocationsBySso[$ssoId]['units'] += (int) $row['quantity'];
                }
                foreach ($allocationsBySso as $ssoId => $allocation) {
                    notifyUser(
                        $database,
                        $ssoId,
                        'stock-quota-approved-' . preg_replace('/[^A-Za-z0-9-]/', '', $displayReference) . '-' . $ssoId,
                        'Stock quota approved',
                        $displayReference . ' allocated to your division',
                        $allocation['lines'] . ' allocations - ' . $allocation['units'] . ' total units; awaiting Store Keeper release.',
                        'dashboard.php?page=assigned-stock-quotas#goods-request-' . $allocation['first_id']
                    );
                }
            }
            $beneficiaryBatch = false;
            foreach ($batchRows as $row) {
                if ((int) $row['linked_needs'] > 0 || $row['aid_request_id'] !== null) {
                    $beneficiaryBatch = true;
                    break;
                }
            }
            $subjectHistoryPage = $beneficiaryBatch ? 'my-beneficiary-requests' : 'my-goods-requests';
            $adminHistoryPage = $beneficiaryBatch ? 'reviewed-beneficiary-requests' : 'reviewed-stock-quota-requests';
            notifyUser(
                $database,
                $requesterIds[0],
                'stock-quota-decision-' . preg_replace('/[^A-Za-z0-9-]/', '', $displayReference),
                'Stock quota request update',
                $displayReference . ' ' . $decisionLabel,
                count($batchRows) . ' allocations - ' . $totalUnits . ' total units',
                'dashboard.php?page=' . $subjectHistoryPage . '#goods-request-' . $firstId
            );

            $database->commit();
            logActivity('Stock Quota Requests', ucfirst($decisionLabel) . ' stock quota request', $displayReference, $decisionLabel);
            $_SESSION['flash_success'] = 'Stock quota request ' . $decisionLabel . '.';
            unset($_SESSION['csrf_token']);
            header('Location: dashboard.php?page=' . $adminHistoryPage . '#stock-quota-' . $firstId);
            exit;
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            error_log($exception->getMessage());
            $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to review the stock quota request.';
        }
    }
}

try {
    $pendingRows = $database->query(
        "SELECT g.*, i.item_name, i.variety, i.quantity AS central_stock,
                GREATEST(0, i.quantity - COALESCE((SELECT SUM(r.quantity) FROM goods_requests r
                    WHERE r.item_id = g.item_id AND r.status = 'approved-awaiting-dispatch'), 0)) AS available_stock,
                (SELECT category.name
                 FROM goods_request_aid_requests link
                 JOIN aid_requests ar ON ar.id = link.aid_request_id
                 JOIN spectacle_categories category ON category.id = ar.spectacle_category_id
                 WHERE link.goods_request_id = g.id LIMIT 1) AS spectacle_category_name,
                (SELECT b.full_name FROM goods_request_aid_requests link JOIN aid_requests ar ON ar.id=link.aid_request_id JOIN beneficiaries b ON b.id=ar.beneficiary_id WHERE link.goods_request_id=g.id LIMIT 1) AS beneficiary_name,
                (SELECT COALESCE(NULLIF(b.nic,''),NULLIF(b.elders_card_number,'')) FROM goods_request_aid_requests link JOIN aid_requests ar ON ar.id=link.aid_request_id JOIN beneficiaries b ON b.id=ar.beneficiary_id WHERE link.goods_request_id=g.id LIMIT 1) AS beneficiary_identification,
                ds.name AS division_name, d.name AS district_name,
                requester.full_name AS requester_name, target.full_name AS sso_name
         FROM goods_requests g
         JOIN inventory_items i ON i.id = g.item_id
         JOIN ds_divisions ds ON ds.id = g.destination_ds_division_id
         JOIN districts d ON d.id = ds.district_id
         JOIN users requester ON requester.id = g.requested_by
         LEFT JOIN users target ON target.id = g.destination_sso_id
         WHERE g.status = 'pending-admin-approval'
         ORDER BY g.created_at, g.id"
    )->fetchAll();
    $approvalCounts = [
        'registrations' => (int) $database->query("SELECT COUNT(*) FROM registration_requests WHERE status = 'pending'")->fetchColumn(),
        'aid' => (int) $database->query("SELECT COUNT(*) FROM aid_requests WHERE status = 'pending'")->fetchColumn(),
        'stock' => (int) $database->query("SELECT COUNT(DISTINCT COALESCE(NULLIF(request_batch_ref, ''), CONCAT('GR-', id))) FROM goods_requests WHERE status = 'pending-admin-approval'")->fetchColumn(),
        'corrections' => (int) $database->query("SELECT COUNT(*) FROM correction_requests WHERE status = 'pending'")->fetchColumn(),
    ];
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $pendingRows = [];
    $approvalCounts = [];
    $errors[] = 'Stock quota workflow is unavailable.';
}

$batches = [];
foreach ($pendingRows as $row) {
    $batchKey = trim((string) ($row['request_batch_ref'] ?? '')) ?: 'legacy-' . (int) $row['id'];
    if (!isset($batches[$batchKey])) {
        $batches[$batchKey] = [
            'reference' => trim((string) ($row['request_batch_ref'] ?? '')),
            'first_id' => (int) $row['id'],
            'requester_name' => (string) $row['requester_name'],
            'created_at' => (string) $row['created_at'],
            'justification' => (string) $row['justification'],
            'rows' => [],
        ];
    }
    $batches[$batchKey]['rows'][] = $row;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars(t('Stock Quota Requests'), ENT_QUOTES, 'UTF-8') ?> | SWPCS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css?v=72" rel="stylesheet">
</head>
<body class="admin-correction-page">
<?php require __DIR__ . '/../../includes/admin-sidebar.php'; ?>
<div class="admin-shell">
    <header class="topbar"><div class="d-flex align-items-center gap-3"><button type="button" class="menu-button" id="menu-button" aria-label="Open navigation">&#9776;</button><h1><?= htmlspecialchars(t('Stock Quota Requests'), ENT_QUOTES, 'UTF-8') ?></h1></div></header>
    <main class="dashboard-content admin-correction-review-page admin-stock-quota-page">
        <?php renderSuccessMessage($success); ?>
        <?php if ($errors): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars(implode(' ', array_map('t', array_unique($errors))), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

        <section class="admin-data-card admin-correction-review-card" aria-label="<?= htmlspecialchars(t('Pending Stock Quota Requests'), ENT_QUOTES, 'UTF-8') ?>">
            <div class="admin-correction-list stock-quota-card-list">
                <?php if ($batches === []): ?>
                    <div class="empty-corrections"><strong><?= htmlspecialchars(t('No pending stock quota requests'), ENT_QUOTES, 'UTF-8') ?></strong><span><?= htmlspecialchars(t('New quota requests will appear here for approval.'), ENT_QUOTES, 'UTF-8') ?></span></div>
                <?php else: foreach ($batches as $batch): ?>
                    <?php $lines = $batch['rows']; $displayReference = $batch['reference'] !== '' ? $batch['reference'] : 'GR-' . str_pad((string) $batch['first_id'], 4, '0', STR_PAD_LEFT); $totalUnits = array_sum(array_map('intval', array_column($lines, 'quantity'))); ?>
                    <article id="goods-request-<?= (int) $batch['first_id'] ?>" class="admin-correction-item stock-quota-card admin-notification-target" tabindex="-1">
                        <?php foreach (array_slice($lines, 1) as $line): ?><span id="goods-request-<?= (int) $line['id'] ?>" class="stock-quota-anchor" aria-hidden="true"></span><?php endforeach; ?>
                        <div class="correction-summary stock-quota-summary">
                            <div><div class="correction-reference-line"><strong><?= htmlspecialchars($displayReference, ENT_QUOTES, 'UTF-8') ?></strong><span><?= count($lines) ?> <?= htmlspecialchars(t(count($lines) === 1 ? 'Allocation' : 'Allocations'), ENT_QUOTES, 'UTF-8') ?></span><small><?= number_format($totalUnits) ?> <?= htmlspecialchars(t('Total Units'), ENT_QUOTES, 'UTF-8') ?></small></div><p class="correction-submission-meta"><?= htmlspecialchars(t('Requested by'), ENT_QUOTES, 'UTF-8') ?> <strong><?= htmlspecialchars($batch['requester_name'], ENT_QUOTES, 'UTF-8') ?></strong> &middot; <?= date('d M Y, H:i', strtotime($batch['created_at'])) ?></p></div>
                            <span class="correction-status pending"><?= htmlspecialchars(t('Pending'), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="stock-quota-justification"><span><?= htmlspecialchars(t('Quota Justification'), ENT_QUOTES, 'UTF-8') ?></span><p><?= nl2br(htmlspecialchars($batch['justification'], ENT_QUOTES, 'UTF-8')) ?></p></div>
                        <div class="stock-quota-lines-wrap"><table class="admin-data-table stock-quota-lines-table">
                            <thead><tr><th>#</th><th><?= htmlspecialchars(t('Beneficiary'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('NIC / Elder Card'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Aid Item'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Spectacle Type'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Available Stock'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Quantity'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('District / DS Division'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Receiving SSO'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Document'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead>
                            <tbody><?php foreach ($lines as $index => $line): ?><tr><td><?= $index + 1 ?></td><td><?= htmlspecialchars((string)($line['beneficiary_name']??'—'),ENT_QUOTES,'UTF-8') ?></td><td><?= htmlspecialchars((string)($line['beneficiary_identification']??'—'),ENT_QUOTES,'UTF-8') ?></td><td><strong><?= htmlspecialchars(widmsAidItemName((string) $line['item_name']), ENT_QUOTES, 'UTF-8') ?></strong><?php if ((string) $line['variety'] !== ''): ?><small><?= htmlspecialchars((string) $line['variety'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?></td><td><?= $line['spectacle_category_name'] !== null ? htmlspecialchars(t((string)$line['spectacle_category_name']), ENT_QUOTES, 'UTF-8') : '&mdash;' ?></td><td><?= number_format((int) $line['available_stock']) ?></td><td><strong><?= number_format((int) $line['quantity']) ?></strong></td><td><?= htmlspecialchars($line['district_name'] . ' / ' . $line['division_name'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string) ($line['sso_name'] ?: t('Subject Officer direct release')), ENT_QUOTES, 'UTF-8') ?></td><td><a class="outline-action" target="_blank" rel="noopener" href="dashboard.php?page=goods-request-document&amp;request_id=<?= (int) $line['id'] ?>&amp;print=1"><?= htmlspecialchars(t('View PDF'), ENT_QUOTES, 'UTF-8') ?></a></td></tr><?php endforeach; ?></tbody>
                        </table></div>
                        <form method="post" action="dashboard.php?page=goods-requests" class="admin-decision-form stock-quota-decision-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                            <?php if ($batch['reference'] !== ''): ?><input type="hidden" name="batch_reference" value="<?= htmlspecialchars($batch['reference'], ENT_QUOTES, 'UTF-8') ?>"><?php else: ?><input type="hidden" name="request_id" value="<?= (int) $batch['first_id'] ?>"><?php endif; ?>
                            <label><?= htmlspecialchars(t('Admin note'), ENT_QUOTES, 'UTF-8') ?><textarea name="rejection_reason" maxlength="500" rows="2" placeholder="<?= htmlspecialchars(t('Required when rejecting the request'), ENT_QUOTES, 'UTF-8') ?>"></textarea></label>
                            <div class="correction-decision-footer"><small><?= htmlspecialchars(t('Approval reserves Central Stock for every allocation in this quota batch.'), ENT_QUOTES, 'UTF-8') ?></small><div class="correction-decision-actions"><button class="approve-button" name="decision" value="approved" type="submit"><?= htmlspecialchars(t('Approve'), ENT_QUOTES, 'UTF-8') ?></button><button class="reject-button" name="decision" value="rejected" type="submit"><?= htmlspecialchars(t('Reject'), ENT_QUOTES, 'UTF-8') ?></button></div></div>
                        </form>
                    </article>
                <?php endforeach; endif; ?>
            </div>
        </section>
    </main>
</div>
<script src="assets/js/admin-dashboard.js"></script>
</body>
</html>
