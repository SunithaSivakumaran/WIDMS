<?php
declare(strict_types=1);

if (!in_array((string) ($_SESSION['role'] ?? ''), ['store-keeper', 'subject-officer', 'admin'], true)) {
    http_response_code(403);
    exit('You do not have permission to access this page.');
}
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/i18n.php';

$id = (int) ($_GET['receipt_id'] ?? 0);
$receipt = null;
$payments = [];
$spectacleLines = [];
$db = database();

if ($id > 0) {
    $query = $db->prepare('SELECT r.id, r.bill_number, r.payment_status, r.paid_amount, r.check_number, r.received_date, r.total_cost, r.stock_destination, r.vision_camp_id,ds.name division_name,s.company_name FROM stock_receipts r JOIN suppliers s ON s.id=r.supplier_id LEFT JOIN ds_divisions ds ON ds.id=r.ds_division_id WHERE r.id=?');
    $query->execute([$id]);
    $receipt = $query->fetch();
    if ($receipt) {
        $lineQuery = $db->prepare('SELECT category.name, line.quantity, line.unit_cost, line.total_cost FROM stock_receipt_spectacle_lines line JOIN spectacle_categories category ON category.id = line.spectacle_category_id WHERE line.stock_receipt_id = ? ORDER BY category.display_order, category.name');
        $lineQuery->execute([$id]);
        $spectacleLines = $lineQuery->fetchAll();
        $query = $db->prepare('SELECT id, amount, check_number, payment_date FROM supplier_payments WHERE receipt_id = ? ORDER BY payment_date, id');
        $query->execute([$id]);
        $storedPayments = $query->fetchAll();
        $runningTotal = 0.0;

        // Older receipts kept their first payment on stock_receipts. If later
        // payments exist, add only the missing difference so it is not shown
        // twice and the payment timeline still adds up to the receipt total.
        $storedTotal = array_sum(array_map(static fn(array $payment): float => (float) $payment['amount'], $storedPayments));
        $legacyAmount = round((float) $receipt['paid_amount'] - $storedTotal, 2);
        if ($legacyAmount > 0) {
            $payments[] = [
                'id' => null,
                'amount' => $legacyAmount,
                'check_number' => $receipt['check_number'],
                'payment_date' => $receipt['received_date'],
                'payment_type_key' => $legacyAmount >= (float) $receipt['total_cost'] ? 'fully-paid' : 'partially-paid',
                'payment_type' => $legacyAmount >= (float) $receipt['total_cost'] ? t('Fully Paid') : t('Partially Paid'),
            ];
            $runningTotal = $legacyAmount;
        }

        foreach ($storedPayments as $payment) {
            $runningTotal += (float) $payment['amount'];
            $payments[] = [
                'id' => $payment['id'],
                'amount' => $payment['amount'],
                'check_number' => $payment['check_number'],
                'payment_date' => $payment['payment_date'],
                'payment_type_key' => $runningTotal >= (float) $receipt['total_cost'] ? 'fully-paid' : 'partially-paid',
                'payment_type' => $runningTotal >= (float) $receipt['total_cost'] ? t('Fully Paid') : t('Partially Paid'),
            ];
        }
        // Receipts with no supplier_payments row are entirely legacy records.
        if ((float) $receipt['paid_amount'] > 0 && !$payments) {
            $payments[] = [
                'id' => null,
                'amount' => $receipt['paid_amount'],
                'check_number' => $receipt['check_number'],
                'payment_date' => $receipt['received_date'],
                'payment_type_key' => $receipt['payment_status'] === 'fully-paid' ? 'fully-paid' : 'partially-paid',
                'payment_type' => $receipt['payment_status'] === 'fully-paid' ? t('Fully Paid') : t('Partially Paid'),
            ];
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(t('Payment History'), ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="assets/css/admin-dashboard.css?v=49">
</head>
<body class="store-page widms-unified-ui">
<main class="dashboard-content">
    <section class="admin-data-card receipt-payment-history-card">
        <div class="admin-data-header">
            <div>
                <h2><?= htmlspecialchars(t('Payment History'), ENT_QUOTES, 'UTF-8') ?></h2>
                <p><?= $receipt ? 'BAT-' . str_pad((string) $receipt['id'], 4, '0', STR_PAD_LEFT) . ' · ' . htmlspecialchars($receipt['company_name'], ENT_QUOTES, 'UTF-8') . ($receipt['stock_destination']==='vision-camp' ? ' · VC-'.(int)$receipt['vision_camp_id'].' · '.htmlspecialchars((string)$receipt['division_name'],ENT_QUOTES,'UTF-8') : ' · '.htmlspecialchars(t('Central Stock'),ENT_QUOTES,'UTF-8')) : htmlspecialchars(t('Receipt not found'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
        <?php if ($receipt): ?>
            <?php if ($spectacleLines): ?>
            <div class="admin-data-table-wrap store-table-card">
                <table class="admin-data-table payment-history-table">
                    <thead><tr><th><?= htmlspecialchars(t('Spectacle Type'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Quantity'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Unit Price (Rs)'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Total'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead>
                    <tbody><?php foreach ($spectacleLines as $line): ?><tr><td><?= htmlspecialchars(t((string) $line['name']), ENT_QUOTES, 'UTF-8') ?></td><td><?= (int) $line['quantity'] ?></td><td>Rs <?= number_format((float) $line['unit_cost'], 2) ?></td><td>Rs <?= number_format((float) $line['total_cost'], 2) ?></td></tr><?php endforeach; ?></tbody>
                </table>
            </div>
            <?php endif; ?>
            <div class="admin-data-table-wrap store-table-card">
                <table class="admin-data-table payment-history-table">
                    <thead><tr><th><?= t('Payment') ?></th><th><?= t('Check Number') ?></th><th><?= t('Payment Date') ?></th><th><?= t('Amount Paid') ?></th><th><?= t('Payment Type') ?></th><?php if (hasRole('store-keeper')): ?><th><?= t('Action') ?></th><?php endif; ?></tr></thead>
                    <tbody>
                    <?php if (!$payments): ?><tr><td colspan="<?= hasRole('store-keeper') ? 6 : 5 ?>" class="admin-empty-row"><?= htmlspecialchars(t('No payments recorded.'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                    <?php else: foreach ($payments as $payment): $typeClass = $payment['payment_type_key']; $paymentDate = new DateTimeImmutable((string) $payment['payment_date']); $today = new DateTimeImmutable('today'); $correctionAllowed = $paymentDate >= $today->modify('-7 days') && $paymentDate <= $today; ?>
                        <tr>
                            <td><?= $payment['id'] !== null ? 'PAY-' . str_pad((string) $payment['id'], 4, '0', STR_PAD_LEFT) : 'BAT-' . str_pad((string) $receipt['id'], 4, '0', STR_PAD_LEFT) . ' (' . htmlspecialchars(t('Initial payment'), ENT_QUOTES, 'UTF-8') . ')' ?></td>
                            <td><?= htmlspecialchars($payment['check_number'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($payment['payment_date'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>Rs <?= number_format((float) $payment['amount'], 2) ?></td>
                            <td><span class="status payment-type-<?= htmlspecialchars($typeClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($payment['payment_type'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <?php if (hasRole('store-keeper')): ?>
                                <?php $correctionReferenceType = $payment['id'] !== null ? 'payment' : 'batch'; $correctionReference = $payment['id'] !== null ? 'PAY-' . str_pad((string) $payment['id'], 4, '0', STR_PAD_LEFT) : 'BAT-' . str_pad((string) $receipt['id'], 4, '0', STR_PAD_LEFT); ?>
                                <td><?php if ($correctionAllowed): ?><a class="outline-action" href="dashboard.php?page=correction-requests&amp;reference_type=<?= $correctionReferenceType ?>&amp;record_reference=<?= rawurlencode($correctionReference) ?>&amp;error_type=wrong-payment-amount"><?= htmlspecialchars(t('Request correction'), ENT_QUOTES, 'UTF-8') ?></a><?php else: ?><span class="correction-window-closed"><?= htmlspecialchars(t('Correction window closed'), ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
