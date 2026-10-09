<?php
declare(strict_types=1);

requireRole('social-service-officer');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/i18n.php';

$activePage = 'sso-distribution-history';
$userId = (int) $_SESSION['user_id'];
$search = mb_substr(trim((string) ($_GET['search'] ?? '')), 0, 100);
$itemId = filter_var($_GET['item_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$rows = [];
$historyItems = [];
$error = '';

foreach (['dateFrom', 'dateTo'] as $key) {
    $value = $$key;
    if ($value !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)
        || !checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4)))) {
        $$key = '';
    }
}

try {
    $db = database();
    $itemQuery = $db->prepare(
        "SELECT DISTINCT i.id, i.item_name, i.variety
         FROM distributions d
         JOIN users officer ON officer.id = :officer_id
             AND officer.role = 'social-service-officer' AND officer.status = 'active'
         JOIN inventory_items i ON i.id = d.item_id
         WHERE d.distributed_by = officer.id AND d.ds_division_id = officer.ds_division_id
         ORDER BY i.item_name, i.variety"
    );
    $itemQuery->execute(['officer_id' => $userId]);
    $historyItems = $itemQuery->fetchAll(PDO::FETCH_ASSOC);

    $conditions = [
        "d.distributed_by = officer.id",
        "d.ds_division_id = officer.ds_division_id",
    ];
    $params = ['officer_id' => $userId];
    if ($search !== '') {
        $searchConditions = [
            'b.full_name LIKE :search_name',
            'b.nic LIKE :search_nic',
            'b.elders_card_number LIKE :search_elder',
            'i.item_name LIKE :search_item',
            'i.variety LIKE :search_variety',
            'CAST(d.id AS CHAR) LIKE :search_id',
        ];
        foreach (['search_name', 'search_nic', 'search_elder', 'search_item', 'search_variety', 'search_id'] as $key) {
            $params[$key] = '%' . $search . '%';
        }
        if (preg_match('/^DIST-0*(\d+)$/iD', $search, $reference)) {
            $searchConditions[] = 'd.id = :reference_id';
            $params['reference_id'] = (int) $reference[1];
        }
        $conditions[] = '(' . implode(' OR ', $searchConditions) . ')';
    }
    if ($itemId > 0) {
        $conditions[] = 'd.item_id = :item_id';
        $params['item_id'] = $itemId;
    }
    if ($dateFrom !== '') {
        $conditions[] = 'd.distributed_at >= :date_from';
        $params['date_from'] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo !== '') {
        $conditions[] = 'd.distributed_at < :date_to';
        $params['date_to'] = (new DateTimeImmutable($dateTo))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
    }

    $query = $db->prepare(
        "SELECT d.id, d.item_id, d.quantity, d.distribution_type, d.distributed_at,
                b.full_name, b.nic, b.elders_card_number, i.item_name, i.variety
         FROM distributions d
         JOIN users officer ON officer.id = :officer_id
             AND officer.role = 'social-service-officer' AND officer.status = 'active'
         JOIN beneficiaries b ON b.id = d.beneficiary_id
         JOIN inventory_items i ON i.id = d.item_id
         WHERE " . implode(' AND ', $conditions) . "
         ORDER BY d.distributed_at DESC, d.id DESC"
    );
    $query->execute($params);
    $rows = $query->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
    error_log('Unable to load SSO distribution history: ' . $exception->getMessage());
    $error = 'Distribution history is temporarily unavailable.';
}

$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="<?= $escape(widmsLanguage()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $escape(t('Distribution History')) ?> | SWPCS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/admin-dashboard.css') ?>" rel="stylesheet">
    <link href="assets/css/sso-distribution-history.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/sso-distribution-history.css') ?>" rel="stylesheet">
</head>
<body class="distribution-page-body sso-distribution-history-page widms-unified-ui">
<?php require __DIR__ . '/../../includes/social-service-officer-sidebar.php'; ?>
<div class="admin-shell">
    <header class="topbar"><div class="d-flex align-items-center gap-3"><button class="menu-button" id="menu-button" type="button" aria-label="<?= $escape(t('Open navigation')) ?>">&#9776;</button><h1><?= $escape(t('Distribution History')) ?></h1></div></header>
    <main class="dashboard-content distribution-page">
        <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= $escape($error) ?></div><?php endif; ?>
        <section class="admin-data-card">
            <form method="get" class="sso-history-filters" role="search">
                <input type="hidden" name="page" value="sso-distribution-history">
                <label><span><?= $escape(t('Search')) ?></span><input type="search" name="search" value="<?= $escape($search) ?>" placeholder="Search beneficiary, item or reference" maxlength="100"></label>
                <label><span><?= $escape(t('Aid Item')) ?></span><select name="item_id"><option value="">All aid items</option><?php foreach ($historyItems as $item): ?><option value="<?= (int) $item['id'] ?>" <?= $itemId === (int) $item['id'] ? 'selected' : '' ?>><?= $escape(widmsAidItemName((string) $item['item_name']) . ((string) $item['variety'] !== '' ? ' — ' . $item['variety'] : '')) ?></option><?php endforeach; ?></select></label>
                <label><span>From date</span><input type="date" name="date_from" value="<?= $escape($dateFrom) ?>"></label>
                <label><span>To date</span><input type="date" name="date_to" value="<?= $escape($dateTo) ?>"></label>
                <div class="sso-history-filter-actions"><button class="admin-primary-action" type="submit">Filter</button><a class="outline-action" href="dashboard.php?page=sso-distribution-history">Clear</a></div>
            </form>
            <div class="admin-data-table-wrap sso-history-table-wrap">
                <table class="admin-data-table sso-history-table">
                    <thead><tr><th><?= $escape(t('Reference')) ?></th><th><?= $escape(t('Beneficiary')) ?></th><th><?= $escape(t('Aid Item')) ?></th><th><?= $escape(t('Type')) ?></th><th><?= $escape(t('Quantity')) ?></th><th>Source</th><th><?= $escape(t('Date')) ?> / <?= $escape(t('Time')) ?></th></tr></thead>
                    <tbody>
                        <?php if ($rows === []): ?><tr><td colspan="7" class="admin-empty-row">No distributions match these filters.</td></tr><?php endif; ?>
                        <?php foreach ($rows as $row): ?>
                            <tr id="distribution-<?= (int) $row['id'] ?>">
                                <td><strong>DIST-<?= str_pad((string) $row['id'], 4, '0', STR_PAD_LEFT) ?></strong></td>
                                <td><strong><?= $escape($row['full_name']) ?></strong><small><?= $escape($row['nic'] ?: ($row['elders_card_number'] ?: '—')) ?></small></td>
                                <td><?= $escape(widmsAidItemName((string) $row['item_name']) . ((string) $row['variety'] !== '' ? ' — ' . $row['variety'] : '')) ?></td>
                                <td><?= $escape(ucwords(str_replace('-', ' ', (string) $row['distribution_type']))) ?></td>
                                <td><?= (int) $row['quantity'] ?></td>
                                <td>My Pool Quota</td>
                                <td><?= $escape(date('d M Y, h:i A', strtotime((string) $row['distributed_at']))) ?></td>
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
