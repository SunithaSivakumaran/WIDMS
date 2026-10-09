<?php
declare(strict_types=1);

if (!in_array((string) ($_SESSION['role'] ?? ''), ['store-keeper', 'subject-officer', 'admin'], true)) {
    http_response_code(403);
    exit('You do not have permission to access this page.');
}
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/spectacle-categories.php';

$itemId = filter_input(INPUT_GET, 'item_id', FILTER_VALIDATE_INT) ?: 0;
$itemName = '';
$rows = [];
$isSpectacles = false;

if ($itemId > 0) {
    $db = database();
    $query = $db->prepare('SELECT item_name FROM inventory_items WHERE id = ?');
    $query->execute([$itemId]);
    $itemName = (string) ($query->fetchColumn() ?: '');
    if ($itemName !== '') {
        $isSpectacles = widmsIsSpectacleItem($itemName);
        $query = $db->prepare(
            "SELECT r.id, r.quantity, r.received_date, r.bill_number, s.company_name,
                    line.quantity AS spectacle_quantity, category.name AS spectacle_type
             FROM stock_receipts r
             JOIN suppliers s ON s.id = r.supplier_id
             LEFT JOIN stock_receipt_spectacle_lines line ON line.stock_receipt_id = r.id
             LEFT JOIN spectacle_categories category ON category.id = line.spectacle_category_id
             WHERE r.item_id = ?
             ORDER BY r.received_date ASC, r.id ASC, category.display_order ASC, category.name ASC"
        );
        $query->execute([$itemId]);
        $receiptRows = $query->fetchAll();
        foreach ($receiptRows as $receipt) {
            if ($isSpectacles) {
                $receipt['quantity'] = $receipt['spectacle_quantity'] !== null
                    ? (int) $receipt['spectacle_quantity'] : (int) $receipt['quantity'];
                $rows[] = $receipt;
                continue;
            }
            $rows[] = $receipt;
        }
    }
}

$hasPower = false; // Legacy power values are no longer part of Contact Lens stock handling.
$title = $hasPower ? t('Power Batch Details') : t('Batch Details');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="assets/css/admin-dashboard.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/admin-dashboard.css') ?>">
</head>
<body class="store-page widms-unified-ui">
<main class="dashboard-content">
    <section class="admin-data-card">
        <div class="admin-data-header">
            <div>
                <h2><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h2>
                <p><?= $itemName !== '' ? htmlspecialchars($itemName, ENT_QUOTES, 'UTF-8') : htmlspecialchars(t('Aid item not found.'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
        <div class="admin-data-table-wrap store-table-card">
            <table class="admin-data-table power-batch-details-table">
                <thead><tr><th><?= t('Batch') ?></th><?php if ($hasPower): ?><th><?= t('Power') ?></th><?php elseif ($isSpectacles): ?><th><?= htmlspecialchars(t('Spectacle Type'), ENT_QUOTES, 'UTF-8') ?></th><?php endif; ?><th><?= t('Quantity') ?></th><th><?= t('Date') ?></th><th><?= t('Supplier') ?></th><th><?= t('Bill') ?></th></tr></thead>
                <tbody>
                <?php if (!$rows): ?><tr><td colspan="<?= $hasPower || $isSpectacles ? 6 : 5 ?>" class="admin-empty-row"><?= htmlspecialchars($itemName !== '' ? t('No batches recorded.') : t('Aid item not found.'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                <?php else: foreach ($rows as $row): ?>
                    <tr>
                        <td>BAT-<?= str_pad((string) $row['id'], 4, '0', STR_PAD_LEFT) ?></td>
                        <?php if ($isSpectacles): ?><td><?= $row['spectacle_type'] !== null ? htmlspecialchars(t((string) $row['spectacle_type']), ENT_QUOTES, 'UTF-8') : '—' ?></td><?php endif; ?>
                        <?php if ($hasPower): ?><td><?= $row['power'] !== null ? htmlspecialchars(sprintf('%+.2f', (float) $row['power']), ENT_QUOTES, 'UTF-8') : '—' ?></td><?php endif; ?>
                        <td><?= (int) $row['quantity'] ?></td>
                        <td><?= htmlspecialchars($row['received_date'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($row['company_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($row['bill_number'], ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
