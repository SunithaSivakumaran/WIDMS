<?php
declare(strict_types=1);

if (!in_array((string) ($_SESSION['role'] ?? ''), ['store-keeper', 'subject-officer', 'admin'], true)) {
    http_response_code(403);
    exit('You do not have permission to access this page.');
}
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/payment-status-control.php';

$itemId = filter_input(INPUT_GET, 'item_id', FILTER_VALIDATE_INT) ?: 0;
$itemName = '';
$rows = [];

if ($itemId > 0) {
    $db = database();
    $query = $db->prepare('SELECT item_name FROM inventory_items WHERE id = ?');
    $query->execute([$itemId]);
    $itemName = (string) ($query->fetchColumn() ?: '');

    if ($itemName !== '') {
        $query = $db->prepare(
            "SELECT r.id, r.quantity, r.received_date, r.total_cost, r.paid_amount, r.balance_amount,
                    r.bill_number, r.payment_status, s.company_name
             FROM stock_receipts r
             JOIN suppliers s ON s.id = r.supplier_id
             JOIN inventory_items i ON i.id = r.item_id
             WHERE r.item_id = ? AND r.stock_destination='central'
             ORDER BY CASE r.payment_status WHEN 'unpaid' THEN 1 WHEN 'partially-paid' THEN 2 WHEN 'fully-paid' THEN 3 ELSE 4 END, r.received_date ASC, r.id ASC"
        );
        $query->execute([$itemId]);
        $rows = $query->fetchAll();
    }
}

$labels = [
    'fully-paid' => t('Fully Paid'),
    'partially-paid' => t('Partially Paid'),
    'unpaid' => t('Outstanding — Not Yet Paid'),
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(t('Recorded Aid Receipts'), ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="assets/css/admin-dashboard.css?v=42">
</head>
<body class="store-page widms-unified-ui">
<main class="dashboard-content">
    <section class="admin-data-card">
        <div class="admin-data-header">
            <div>
                <h2><?= htmlspecialchars(t('Recorded Aid Receipts'), ENT_QUOTES, 'UTF-8') ?></h2>
                <p><?= $itemName !== '' ? htmlspecialchars($itemName, ENT_QUOTES, 'UTF-8') : htmlspecialchars(t('Aid item not found.'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
        <div class="admin-data-table-wrap store-table-card">
            <table class="admin-data-table receipt-history-table">
                <thead><tr><th><?= t('Batch') ?></th><th><?= t('Date') ?></th><th><?= t('Supplier') ?></th><th><?= t('Bill') ?></th><th><?= t('Total') ?></th><th><?= t('Paid') ?></th><th><?= t('Due') ?></th><th><?= t('Status') ?></th></tr></thead>
                <tbody>
                <?php if (!$rows): ?><tr><td colspan="8" class="admin-empty-row"><?= htmlspecialchars($itemName !== '' ? t('No receipt history available.') : t('Aid item not found.'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                <?php else: foreach ($rows as $row): ?>
                    <tr>
                        <td>BAT-<?= str_pad((string) $row['id'], 4, '0', STR_PAD_LEFT) ?></td>
                        <td><?= htmlspecialchars($row['received_date'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($row['company_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($row['bill_number'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>Rs <?= number_format((float) $row['total_cost'], 2) ?></td>
                        <td>Rs <?= number_format((float) $row['paid_amount'], 2) ?></td>
                        <td>Rs <?= number_format((float) $row['balance_amount'], 2) ?></td>
                        <td><?= renderPaymentStatusControl((string) $row['payment_status'], (string) ($labels[$row['payment_status']] ?? $row['payment_status'])) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
