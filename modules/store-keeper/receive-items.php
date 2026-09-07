<?php
declare(strict_types=1);

requireRole('store-keeper');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/activity.php';

$activePage = $requestedPage === 'receive-items' ? 'receive-items' : 'dashboard';
$errors = [];
$success = (string) ($_SESSION['flash_success'] ?? '');
unset($_SESSION['flash_success']);
$suppliers = [];
$inventoryItems = [];
$receipts = [];
$values = [
    'supplier_id' => '', 'item_id' => '', 'quantity' => '', 'power_sign' => '+', 'power_value' => '', 'unit_cost' => '',
    'bill_number' => '', 'received_date' => date('Y-m-d'), 'payment_status' => 'unpaid', 'check_number' => '', 'paid_amount' => '',
];

try {
    $suppliers = database()->query("SELECT s.id, s.company_name, COUNT(sai.item_id) AS authorized_item_count FROM suppliers s JOIN supplier_authorized_items sai ON sai.supplier_id = s.id WHERE s.status = 'active' AND sai.status = 'active' AND (sai.valid_from IS NULL OR (sai.valid_from<=CURDATE() AND (sai.validity_period IS NULL OR sai.validity_unit IS NULL OR (sai.validity_unit='months' AND CURDATE()<DATE_ADD(sai.valid_from,INTERVAL sai.validity_period MONTH)) OR (sai.validity_unit='years' AND CURDATE()<DATE_ADD(sai.valid_from,INTERVAL sai.validity_period YEAR))))) GROUP BY s.id, s.company_name ORDER BY s.company_name")->fetchAll();
    $inventoryItems = database()->query("SELECT i.id, i.item_name, i.variety, i.quantity, GROUP_CONCAT(CASE WHEN s.status='active' AND sai.status='active' AND (sai.valid_from IS NULL OR (sai.valid_from<=CURDATE() AND (sai.validity_period IS NULL OR sai.validity_unit IS NULL OR (sai.validity_unit='months' AND CURDATE()<DATE_ADD(sai.valid_from,INTERVAL sai.validity_period MONTH)) OR (sai.validity_unit='years' AND CURDATE()<DATE_ADD(sai.valid_from,INTERVAL sai.validity_period YEAR))))) THEN sai.supplier_id END) supplier_ids FROM inventory_items i LEFT JOIN supplier_authorized_items sai ON sai.item_id=i.id LEFT JOIN suppliers s ON s.id=sai.supplier_id GROUP BY i.id ORDER BY i.item_name, i.variety")->fetchAll();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $errors[] = 'Stock receiving is not installed. Import database/migration_stock_receiving.sql.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errors === []) {
    foreach (array_keys($values) as $field) {
        $values[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    $supplierId = filter_var($values['supplier_id'], FILTER_VALIDATE_INT);
    $itemId = filter_var($values['item_id'], FILTER_VALIDATE_INT);
    $quantity = filter_var($values['quantity'], FILTER_VALIDATE_INT);
    $powerValue = $values['power_value'] === '' ? null : filter_var($values['power_value'], FILTER_VALIDATE_FLOAT);
    $power = $powerValue === null ? null : (float) ($values['power_sign'] === '-' ? -$powerValue : $powerValue);
    $powerCount = filter_var((string) ($_POST['power_count'] ?? ''), FILTER_VALIDATE_INT);
    $powerEntries = json_decode((string) ($_POST['power_entries'] ?? '[]'), true);
    if (!is_array($powerEntries)) $powerEntries = [];
    if ($powerEntries !== []) $powerCount = array_sum(array_map(static fn($entry): int => (int) ($entry['count'] ?? 0), $powerEntries));
    if ($power === null && isset($powerEntries[0]['power'])) $power = ((string) ($powerEntries[0]['sign'] ?? '+') === '-' ? -1 : 1) * (float) $powerEntries[0]['power'];
    if ($powerCount !== false && $powerCount > 0) $quantity = $powerCount;
    $unitCost = filter_var($values['unit_cost'], FILTER_VALIDATE_FLOAT);
    $paymentStatus = $values['payment_status'];
    $checkNumber = trim((string) ($_POST['check_number'] ?? ''));
    $paidAmount = $values['paid_amount'] === '' ? 0.0 : filter_var($values['paid_amount'], FILTER_VALIDATE_FLOAT);

    if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) $errors[] = 'Your session expired. Refresh the page and try again.';
    if (!$supplierId) $errors[] = 'Select a supplier.';
    if (!$itemId) $errors[] = 'Select an item.';
    if (!in_array($values['power_sign'], ['+', '-'], true)) $errors[] = 'Select a valid power sign.';
    if (!$quantity || $quantity < 1) $errors[] = 'Quantity must be at least 1.';
    if ($powerValue !== null && ($powerValue === false || $powerValue < 0 || $powerValue > 99.99)) $errors[] = 'Enter a valid power between 0 and 99.99.';
    if ($unitCost === false || $unitCost < 0) $errors[] = 'Enter a valid unit cost.';
    if ($values['bill_number'] === '' || mb_strlen($values['bill_number']) > 100) $errors[] = 'Enter a valid bill or invoice number.';
    $date = DateTimeImmutable::createFromFormat('Y-m-d', $values['received_date']);
    if (!$date || $date->format('Y-m-d') !== $values['received_date']) $errors[] = 'Select a valid received date.';
    if (!in_array($paymentStatus, ['fully-paid', 'partially-paid', 'unpaid'], true)) $errors[] = 'Select a valid payment status.';
    if ($paymentStatus !== 'unpaid' && ($checkNumber === '' || mb_strlen($checkNumber) > 100)) $errors[] = 'Enter the check number for this payment.';

    $totalCost = round((float) $quantity * (float) $unitCost, 2);
    if ($paymentStatus === 'fully-paid') {
        $paidAmount = $totalCost;
    } elseif ($paymentStatus === 'unpaid') {
        $paidAmount = 0.0;
    } elseif ($paidAmount === false || $paidAmount <= 0 || $paidAmount >= $totalCost) {
        $errors[] = 'For a partial payment, the paid amount must be greater than zero and less than the total cost.';
    }
    $balance = round($totalCost - (float) $paidAmount, 2);

    if ($errors === []) {
        try {
            $connection = database();
            $connection->beginTransaction();

            $supplierCheck = $connection->prepare("SELECT s.id FROM suppliers s JOIN supplier_authorized_items sai ON sai.supplier_id=s.id AND sai.item_id=:item_id WHERE s.id=:supplier_id AND s.status='active' AND sai.status='active' AND (sai.valid_from IS NULL OR (sai.valid_from<=CURDATE() AND (sai.validity_period IS NULL OR sai.validity_unit IS NULL OR (sai.validity_unit='months' AND CURDATE()<DATE_ADD(sai.valid_from,INTERVAL sai.validity_period MONTH)) OR (sai.validity_unit='years' AND CURDATE()<DATE_ADD(sai.valid_from,INTERVAL sai.validity_period YEAR)))))");
            $supplierCheck->execute(['supplier_id' => $supplierId, 'item_id' => $itemId]);
            $itemCheck = $connection->prepare('SELECT id, item_name FROM inventory_items WHERE id = :id FOR UPDATE');
            $itemCheck->execute(['id' => $itemId]);
            $itemRow = $itemCheck->fetch();
            if (!$supplierCheck->fetch() || !$itemRow) throw new RuntimeException('The supplier is inactive or is not authorized for the selected item.');
            $isPowerItem = (bool) preg_match('/contact\s*lens|spectacles?|\bspecs\b/i', (string) $itemRow['item_name']);
            if ($isPowerItem && $power === null) throw new RuntimeException('Enter the signed power for Contact Lens or Spectacles stock.');
            if ($isPowerItem && (!$powerCount || $powerCount < 1)) throw new RuntimeException('Enter the number of lenses or spectacles for this power.');
            if ($isPowerItem && $powerEntries !== []) {
                $entryTotal = array_sum(array_map(static fn($entry): int => (int) ($entry['count'] ?? 0), $powerEntries));
                if ($entryTotal !== (int) $quantity) throw new RuntimeException('Power counts must add up exactly to the total quantity.');
            }
            if ($isPowerItem) $quantity = $powerCount;
            if (!$isPowerItem) $power = null;

            $receipt = $connection->prepare(
                'INSERT INTO stock_receipts
                 (supplier_id, item_id, quantity, power, power_breakdown, unit_cost, total_cost, bill_number, received_date, payment_status, check_number, paid_amount, balance_amount, received_by)
                 VALUES (:supplier_id, :item_id, :quantity, :power, :power_breakdown, :unit_cost, :total_cost, :bill_number, :received_date, :payment_status, :check_number, :paid_amount, :balance_amount, :received_by)'
            );
            $receipt->execute([
                'supplier_id' => $supplierId, 'item_id' => $itemId, 'quantity' => $quantity,
                'power' => $power,
                'power_breakdown' => $powerEntries !== [] ? json_encode($powerEntries, JSON_THROW_ON_ERROR) : null,
                'unit_cost' => number_format((float) $unitCost, 2, '.', ''),
                'total_cost' => number_format($totalCost, 2, '.', ''),
                'bill_number' => $values['bill_number'], 'received_date' => $values['received_date'],
                'payment_status' => $paymentStatus, 'check_number' => $paymentStatus === 'unpaid' ? null : $checkNumber,
                'paid_amount' => number_format((float) $paidAmount, 2, '.', ''),
                'balance_amount' => number_format($balance, 2, '.', ''), 'received_by' => $_SESSION['user_id'],
            ]);
            $receiptId = (int) $connection->lastInsertId();
            $stock = $connection->prepare('UPDATE inventory_items i LEFT JOIN item_categories c ON c.name=i.category AND c.status=\'active\' SET i.quantity=i.quantity+:quantity,i.category_id=COALESCE(i.category_id,c.id) WHERE i.id=:id');
            $stock->execute(['quantity' => $quantity, 'id' => $itemId]);
            $connection->commit();
            logActivity('Inventory', sprintf('Received %d item%s into central stock', $quantity, $quantity === 1 ? '' : 's'), 'BAT-' . str_pad((string)$receiptId,4,'0',STR_PAD_LEFT), 'done');

            $_SESSION['flash_success'] = sprintf('Receipt BAT-%04d recorded. %d item%s added to stock immediately.', $receiptId, $quantity, $quantity === 1 ? '' : 's');
            unset($_SESSION['csrf_token']);
            header('Location: dashboard.php?page=receive-items');
            exit;
        } catch (Throwable $exception) {
            if (isset($connection) && $connection->inTransaction()) $connection->rollBack();
            error_log($exception->getMessage());
            $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : ($exception instanceof PDOException && $exception->getCode() === '23000' ? 'That bill or invoice number has already been recorded.' : 'Unable to record the receipt. Please try again.');
        }
    }
}

if ($errors === []) {
    try {
        $receipts = database()->query(
            'SELECT r.id, r.quantity, r.total_cost, r.paid_amount, r.balance_amount, r.bill_number, r.received_date,
                    r.payment_status, r.power, s.company_name, i.item_name, i.variety
             FROM stock_receipts r JOIN suppliers s ON s.id = r.supplier_id JOIN inventory_items i ON i.id = r.item_id
             ORDER BY r.id DESC LIMIT 20'
        )->fetchAll();
    } catch (PDOException $exception) {
        error_log($exception->getMessage());
        $errors[] = 'Recent receipts could not be loaded.';
    }
}

$statusLabels = ['fully-paid' => 'Fully Paid', 'partially-paid' => 'Partially Paid', 'unpaid' => 'Outstanding — Not Yet Paid'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receive Aid | WIDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css?v=26" rel="stylesheet">
</head>
<body class="store-page store-receive-page">
<?php require __DIR__ . '/../../includes/store-keeper-sidebar.php'; ?>
<div class="admin-shell">
    <header class="topbar"><div class="d-flex align-items-center gap-3"><button type="button" class="menu-button" id="menu-button" aria-label="Open navigation">☰</button><h1>Receive Aid</h1></div><div class="topbar-actions"><button class="notification-button" type="button" aria-label="Notifications">●</button></div></header>
    <main class="dashboard-content receive-page">
        <?php if ($success !== ''): ?><div class="alert alert-success" role="status"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if ($errors !== []): ?><div class="alert alert-danger" role="alert"><ul class="mb-0 ps-3"><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></div><?php endif; ?>

        <section class="receive-card receive-entry-card">
            <div class="receive-card-header receive-entry-header"><div class="receive-entry-title"><span class="receive-entry-symbol" aria-hidden="true">＋</span><div><h2>Record Received Aid</h2><p>Register supplier deliveries and add approved aid to central stock.</p></div></div></div>
            <form method="post" action="dashboard.php?page=receive-items" id="receipt-form" class="receive-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="receive-form-grid">
                    <label>Supplier Company<select name="supplier_id" id="supplier_id" required><option value="">Select supplier first</option><?php foreach ($suppliers as $supplier): ?><?php $hasAuthorizedItems = (int) $supplier['authorized_item_count'] > 0; ?><option value="<?= (int) $supplier['id'] ?>" <?= !$hasAuthorizedItems ? 'disabled' : '' ?> <?= (string) $supplier['id'] === $values['supplier_id'] ? 'selected' : '' ?>><?= htmlspecialchars($supplier['company_name'] . ($hasAuthorizedItems ? '' : ' — no allocated items'), ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select><small>Suppliers without allocated items must be configured in Supplier Configuration.</small></label>
                    <label>Item<select name="item_id" id="item_id" required disabled><option value="">Select a supplier first</option><?php foreach ($inventoryItems as $item): ?><option value="<?= (int) $item['id'] ?>" data-suppliers=",<?= htmlspecialchars((string)$item['supplier_ids'], ENT_QUOTES, 'UTF-8') ?>," <?= (string) $item['id'] === $values['item_id'] ? 'selected' : '' ?>><?= htmlspecialchars($item['item_name'] . ($item['variety'] !== '' ? ' — ' . $item['variety'] : '') . ' (stock: ' . $item['quantity'] . ')', ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select><small id="item-help">Choose a supplier to load its authorized items.</small></label>
                    <label><span id="quantity-label">Quantity</span><input type="number" min="1" name="quantity" id="quantity" value="<?= htmlspecialchars($values['quantity'], ENT_QUOTES, 'UTF-8') ?>" required><small id="quantity-help">Number of items received.</small></label>
                    <label id="power-field" hidden>Power<select name="power_sign" id="power_sign" aria-label="Power sign"><option value="+" <?= $values['power_sign'] === '+' ? 'selected' : '' ?>>+</option><option value="-" <?= $values['power_sign'] === '-' ? 'selected' : '' ?>>−</option></select><input type="number" min="0" max="99.99" step="0.01" name="power_value" id="power_value" value="<?= htmlspecialchars($values['power_value'], ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. 2.00"><small>Enter the prescription power for this batch.</small></label>
                    <label>Unit Cost (Rs)<input type="number" min="0" step="0.01" name="unit_cost" id="unit_cost" value="<?= htmlspecialchars($values['unit_cost'], ENT_QUOTES, 'UTF-8') ?>" required></label>
                    <label>Total Cost (Rs)<input type="text" id="total_cost" value="0.00" readonly></label>
                    <label>Bill / Invoice Number<input type="text" maxlength="100" name="bill_number" placeholder="e.g. BILL-2026-0041" value="<?= htmlspecialchars($values['bill_number'], ENT_QUOTES, 'UTF-8') ?>" required></label>
                    <label>Date Received<input type="date" name="received_date" value="<?= htmlspecialchars($values['received_date'], ENT_QUOTES, 'UTF-8') ?>" required></label>
                    <label>Payment Status<select name="payment_status" id="payment_status" required><option value="fully-paid" <?= $values['payment_status'] === 'fully-paid' ? 'selected' : '' ?>>Fully Paid</option><option value="partially-paid" <?= $values['payment_status'] === 'partially-paid' ? 'selected' : '' ?>>Partially Paid</option><option value="unpaid" <?= $values['payment_status'] === 'unpaid' ? 'selected' : '' ?>>Not Yet Paid</option></select></label>
                    <label id="paid-amount-field">Amount Paid (Rs)<input type="number" min="0.01" step="0.01" name="paid_amount" id="paid_amount" value="<?= htmlspecialchars($values['paid_amount'], ENT_QUOTES, 'UTF-8') ?>"><small>Enter the amount already paid to the supplier.</small></label>
                    <label>Balance Due (Rs)<input type="text" id="balance_amount" value="0.00" readonly></label>
                </div>
                <div class="receive-form-actions"><button type="submit" class="record-receipt-button">Record</button></div>
            </form>
        </section>

    </main>
</div>
<script src="assets/js/admin-dashboard.js"></script><script src="assets/js/receive-items.js?v=13"></script>
</body></html>
