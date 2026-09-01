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
    'supplier_id' => '', 'item_id' => '', 'quantity' => '', 'unit_cost' => '',
    'bill_number' => '', 'received_date' => date('Y-m-d'), 'payment_status' => 'unpaid', 'paid_amount' => '',
];

try {
    $suppliers = database()->query("SELECT s.id, s.company_name, COUNT(sai.item_id) AS authorized_item_count FROM suppliers s LEFT JOIN supplier_authorized_items sai ON sai.supplier_id = s.id WHERE s.status = 'active' GROUP BY s.id, s.company_name ORDER BY s.company_name")->fetchAll();
    $inventoryItems = database()->query("SELECT i.id, i.item_name, i.variety, i.quantity, GROUP_CONCAT(sai.supplier_id) supplier_ids FROM inventory_items i LEFT JOIN supplier_authorized_items sai ON sai.item_id=i.id GROUP BY i.id ORDER BY i.item_name, i.variety")->fetchAll();
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
    $unitCost = filter_var($values['unit_cost'], FILTER_VALIDATE_FLOAT);
    $paymentStatus = $values['payment_status'];
    $paidAmount = $values['paid_amount'] === '' ? 0.0 : filter_var($values['paid_amount'], FILTER_VALIDATE_FLOAT);

    if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) $errors[] = 'Your session expired. Refresh the page and try again.';
    if (!$supplierId) $errors[] = 'Select a supplier.';
    if (!$itemId) $errors[] = 'Select an item.';
    if (!$quantity || $quantity < 1) $errors[] = 'Quantity must be at least 1.';
    if ($unitCost === false || $unitCost < 0) $errors[] = 'Enter a valid unit cost.';
    if ($values['bill_number'] === '' || mb_strlen($values['bill_number']) > 100) $errors[] = 'Enter a valid bill or invoice number.';
    $date = DateTimeImmutable::createFromFormat('Y-m-d', $values['received_date']);
    if (!$date || $date->format('Y-m-d') !== $values['received_date']) $errors[] = 'Select a valid received date.';
    if (!in_array($paymentStatus, ['fully-paid', 'partially-paid', 'unpaid'], true)) $errors[] = 'Select a valid payment status.';

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

            $supplierCheck = $connection->prepare("SELECT s.id FROM suppliers s JOIN supplier_authorized_items sai ON sai.supplier_id=s.id AND sai.item_id=:item_id WHERE s.id=:supplier_id AND s.status='active'");
            $supplierCheck->execute(['supplier_id' => $supplierId, 'item_id' => $itemId]);
            $itemCheck = $connection->prepare('SELECT id FROM inventory_items WHERE id = :id FOR UPDATE');
            $itemCheck->execute(['id' => $itemId]);
            if (!$supplierCheck->fetch() || !$itemCheck->fetch()) throw new RuntimeException('The supplier is inactive or is not authorized for the selected item.');

            $receipt = $connection->prepare(
                'INSERT INTO stock_receipts
                 (supplier_id, item_id, quantity, unit_cost, total_cost, bill_number, received_date, payment_status, paid_amount, balance_amount, received_by)
                 VALUES (:supplier_id, :item_id, :quantity, :unit_cost, :total_cost, :bill_number, :received_date, :payment_status, :paid_amount, :balance_amount, :received_by)'
            );
            $receipt->execute([
                'supplier_id' => $supplierId, 'item_id' => $itemId, 'quantity' => $quantity,
                'unit_cost' => number_format((float) $unitCost, 2, '.', ''),
                'total_cost' => number_format($totalCost, 2, '.', ''),
                'bill_number' => $values['bill_number'], 'received_date' => $values['received_date'],
                'payment_status' => $paymentStatus,
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
                    r.payment_status, s.company_name, i.item_name, i.variety
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
    <title>Receive Items | WIDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css" rel="stylesheet">
</head>
<body class="store-page store-receive-page">
<?php require __DIR__ . '/../../includes/store-keeper-sidebar.php'; ?>
<div class="admin-shell">
    <header class="topbar"><div class="d-flex align-items-center gap-3"><button type="button" class="menu-button" id="menu-button" aria-label="Open navigation">☰</button><h1>Receive Items</h1></div><div class="topbar-actions"><button class="notification-button" type="button" aria-label="Notifications">●</button></div></header>
    <main class="dashboard-content receive-page">
        <?php if ($success !== ''): ?><div class="alert alert-success" role="status"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if ($errors !== []): ?><div class="alert alert-danger" role="alert"><ul class="mb-0 ps-3"><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></div><?php endif; ?>

        <section class="receive-card">
            <div class="receive-card-header"><h2>Record Received Items</h2></div>
            <form method="post" action="dashboard.php?page=receive-items" id="receipt-form" class="receive-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="receive-form-grid">
                    <label>Supplier Company<select name="supplier_id" id="supplier_id" required><option value="">Select supplier first</option><?php foreach ($suppliers as $supplier): ?><?php $hasAuthorizedItems = (int) $supplier['authorized_item_count'] > 0; ?><option value="<?= (int) $supplier['id'] ?>" <?= !$hasAuthorizedItems ? 'disabled' : '' ?> <?= (string) $supplier['id'] === $values['supplier_id'] ? 'selected' : '' ?>><?= htmlspecialchars($supplier['company_name'] . ($hasAuthorizedItems ? '' : ' — no authorized items'), ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select><small>Suppliers without authorized items must be configured in Supplier Management.</small></label>
                    <label>Item<select name="item_id" id="item_id" required disabled><option value="">Select a supplier first</option><?php foreach ($inventoryItems as $item): ?><option value="<?= (int) $item['id'] ?>" data-suppliers=",<?= htmlspecialchars((string)$item['supplier_ids'], ENT_QUOTES, 'UTF-8') ?>," <?= (string) $item['id'] === $values['item_id'] ? 'selected' : '' ?>><?= htmlspecialchars($item['item_name'] . ($item['variety'] !== '' ? ' — ' . $item['variety'] : '') . ' (stock: ' . $item['quantity'] . ')', ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select><small id="item-help">Choose a supplier to load its authorized items.</small></label>
                    <label>Quantity<input type="number" min="1" name="quantity" id="quantity" value="<?= htmlspecialchars($values['quantity'], ENT_QUOTES, 'UTF-8') ?>" required></label>
                    <label>Unit Cost (Rs)<input type="number" min="0" step="0.01" name="unit_cost" id="unit_cost" value="<?= htmlspecialchars($values['unit_cost'], ENT_QUOTES, 'UTF-8') ?>" required></label>
                    <label>Total Cost (Rs)<input type="text" id="total_cost" value="0.00" readonly></label>
                    <label>Bill / Invoice Number<input type="text" maxlength="100" name="bill_number" placeholder="e.g. BILL-2026-0041" value="<?= htmlspecialchars($values['bill_number'], ENT_QUOTES, 'UTF-8') ?>" required></label>
                    <label>Date Received<input type="date" name="received_date" value="<?= htmlspecialchars($values['received_date'], ENT_QUOTES, 'UTF-8') ?>" required></label>
                    <label>Payment Status<select name="payment_status" id="payment_status" required><option value="fully-paid" <?= $values['payment_status'] === 'fully-paid' ? 'selected' : '' ?>>Fully Paid</option><option value="partially-paid" <?= $values['payment_status'] === 'partially-paid' ? 'selected' : '' ?>>Partially Paid</option><option value="unpaid" <?= $values['payment_status'] === 'unpaid' ? 'selected' : '' ?>>Not Yet Paid</option></select></label>
                    <label id="paid-amount-field">Amount Paid (Rs)<input type="number" min="0.01" step="0.01" name="paid_amount" id="paid_amount" value="<?= htmlspecialchars($values['paid_amount'], ENT_QUOTES, 'UTF-8') ?>"><small>Enter the amount already paid to the supplier.</small></label>
                    <label>Balance Due (Rs)<input type="text" id="balance_amount" value="0.00" readonly></label>
                </div>
                <button type="submit" class="record-receipt-button">Record Receipt — Add to Stock Immediately</button>
            </form>
        </section>

        <section class="receive-card recent-receipts-card">
            <div class="receive-card-header"><h2>Recent Receipts</h2></div>
            <div class="receipt-table-wrap"><table class="receipt-table"><thead><tr><th>Batch ID</th><th>Item</th><th>Qty</th><th>Supplier</th><th>Bill No</th><th>Total Cost</th><th>Paid</th><th>Balance</th><th>Payment Status</th></tr></thead>
                <tbody><?php if ($receipts === []): ?><tr><td colspan="9" class="empty-table">No receipts recorded yet.</td></tr><?php else: foreach ($receipts as $receipt): ?><tr>
                    <td>BAT-<?= str_pad((string) $receipt['id'], 4, '0', STR_PAD_LEFT) ?></td><td><?= htmlspecialchars($receipt['item_name'] . ($receipt['variety'] !== '' ? ' (' . $receipt['variety'] . ')' : ''), ENT_QUOTES, 'UTF-8') ?></td><td><?= (int) $receipt['quantity'] ?></td><td><?= htmlspecialchars($receipt['company_name'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($receipt['bill_number'], ENT_QUOTES, 'UTF-8') ?></td><td>Rs <?= number_format((float) $receipt['total_cost'], 2) ?></td><td>Rs <?= number_format((float) $receipt['paid_amount'], 2) ?></td><td>Rs <?= number_format((float) $receipt['balance_amount'], 2) ?></td><td><span class="payment-badge <?= $receipt['payment_status'] ?>"><?= htmlspecialchars($statusLabels[$receipt['payment_status']], ENT_QUOTES, 'UTF-8') ?></span></td>
                </tr><?php endforeach; endif; ?></tbody>
            </table></div>
            <p class="stock-table-note">ⓘ Every batch above is already included in central stock, regardless of payment status.</p>
        </section>
    </main>
</div>
<script src="assets/js/admin-dashboard.js"></script><script src="assets/js/receive-items.js"></script>
</body></html>
