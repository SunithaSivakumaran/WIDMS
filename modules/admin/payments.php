<?php
declare(strict_types=1);
requireRole('admin');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/activity.php';
$activePage = 'payments';
$errors = [];
$success = (string) ($_SESSION['flash_success'] ?? ''); unset($_SESSION['flash_success']);
$openReceipts = $payments = $balances = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receiptId = filter_input(INPUT_POST, 'receipt_id', FILTER_VALIDATE_INT);
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $checkNumber = trim((string) ($_POST['check_number'] ?? ''));
    $checkDate = trim((string) ($_POST['check_date'] ?? ''));
    $paymentDate = trim((string) ($_POST['payment_date'] ?? ''));
    $notes = trim((string) ($_POST['notes'] ?? ''));
    if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) $errors[] = 'Your session expired. Refresh and try again.';
    if (!$receiptId) $errors[] = 'Select an outstanding procurement batch.';
    if ($amount === false || $amount <= 0) $errors[] = 'Enter a payment amount greater than zero.';
    foreach ([['Check date', $checkDate, false], ['Payment date', $paymentDate, true]] as [$label, $value, $required]) {
        if ($required && $value === '') { $errors[] = "$label is required."; continue; }
        if ($value !== '') { $date = DateTimeImmutable::createFromFormat('Y-m-d', $value); if (!$date || $date->format('Y-m-d') !== $value) $errors[] = "$label is invalid."; }
    }
    if (mb_strlen($checkNumber) > 100 || mb_strlen($notes) > 2000) $errors[] = 'Payment reference or notes are too long.';
    if ($errors === []) {
        try {
            $db = database(); $db->beginTransaction();
            $select = $db->prepare('SELECT id, supplier_id, balance_amount FROM stock_receipts WHERE id = :id FOR UPDATE');
            $select->execute(['id' => $receiptId]); $receipt = $select->fetch();
            if (!$receipt || (float) $receipt['balance_amount'] <= 0) throw new RuntimeException('The selected batch has no outstanding balance.');
            if ((float) $amount > (float) $receipt['balance_amount']) throw new RuntimeException('Payment cannot exceed the outstanding batch balance.');
            $newBalance = round((float) $receipt['balance_amount'] - (float) $amount, 2);
            $insert = $db->prepare('INSERT INTO supplier_payments (supplier_id, receipt_id, amount, check_number, check_date, payment_date, notes, recorded_by) VALUES (:supplier, :receipt, :amount, :check_number, :check_date, :payment_date, :notes, :user)');
            $insert->execute(['supplier'=>$receipt['supplier_id'],'receipt'=>$receiptId,'amount'=>number_format((float)$amount,2,'.',''),'check_number'=>$checkNumber?:null,'check_date'=>$checkDate?:null,'payment_date'=>$paymentDate,'notes'=>$notes?:null,'user'=>$_SESSION['user_id']]);
            $update = $db->prepare('UPDATE stock_receipts SET paid_amount = paid_amount + :amount, balance_amount = :balance, payment_status = :status WHERE id = :id');
            $update->execute(['amount'=>number_format((float)$amount,2,'.',''),'balance'=>number_format($newBalance,2,'.',''),'status'=>$newBalance <= 0 ? 'fully-paid' : 'partially-paid','id'=>$receiptId]);
            $paymentId = (int) $db->lastInsertId(); $db->commit();
            logActivity('Payments', sprintf('Recorded supplier payment of Rs %.2f', (float)$amount), 'PAY-' . str_pad((string)$paymentId,4,'0',STR_PAD_LEFT), 'done');
            $_SESSION['flash_success'] = sprintf('Payment PAY-%04d recorded successfully.', $paymentId); unset($_SESSION['csrf_token']);
            header('Location: dashboard.php?page=payments'); exit;
        } catch (Throwable $exception) {
            if (isset($db) && $db->inTransaction()) $db->rollBack(); error_log($exception->getMessage());
            $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to record payment. Import database/migration_supplier_workflow.sql first.';
        }
    }
}

try {
    $openReceipts = database()->query("SELECT r.id,r.bill_number,r.balance_amount,s.company_name FROM stock_receipts r JOIN suppliers s ON s.id=r.supplier_id WHERE r.balance_amount > 0 ORDER BY r.received_date,r.id")->fetchAll();
    $balances = database()->query('SELECT s.company_name,COALESCE(SUM(r.total_cost),0) invoiced,COALESCE(SUM(r.paid_amount),0) paid,COALESCE(SUM(r.balance_amount),0) balance FROM suppliers s LEFT JOIN stock_receipts r ON r.supplier_id=s.id GROUP BY s.id ORDER BY s.company_name')->fetchAll();
    $payments = database()->query('SELECT p.id,p.amount,p.check_number,p.check_date,p.payment_date,s.company_name,r.id receipt_id,r.bill_number,u.full_name recorded_by FROM supplier_payments p JOIN suppliers s ON s.id=p.supplier_id JOIN stock_receipts r ON r.id=p.receipt_id JOIN users u ON u.id=p.recorded_by ORDER BY p.id DESC LIMIT 50')->fetchAll();
} catch (PDOException $exception) { error_log($exception->getMessage()); $errors[] = 'Payment workflow is unavailable. Import database/migration_supplier_workflow.sql.'; }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Supplier Payments | WIDMS</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="assets/css/admin-dashboard.css" rel="stylesheet"></head><body><?php require __DIR__.'/../../includes/admin-sidebar.php'; ?><div class="admin-shell"><header class="topbar"><div class="d-flex align-items-center gap-3"><button type="button" class="menu-button" id="menu-button" aria-label="Open navigation">&#9776;</button><h1>Supplier Payments</h1></div></header><main class="dashboard-content admin-operation-page payments-page">
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?><?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<section class="admin-data-card"><div class="admin-data-header"><h2>Supplier Balance Summary</h2></div><div class="supplier-balance-list"><?php if (!$balances): ?><p>No supplier balances available.</p><?php else: foreach ($balances as $balance): ?><article class="<?= (float)$balance['balance'] > 0 ? 'balance-outstanding' : 'balance-clear' ?>"><div><strong><?= htmlspecialchars($balance['company_name'], ENT_QUOTES, 'UTF-8') ?></strong><small>Invoiced: Rs <?= number_format((float)$balance['invoiced'], 2) ?> · Paid: Rs <?= number_format((float)$balance['paid'], 2) ?></small></div><b>Rs <?= number_format((float)$balance['balance'], 2) ?></b></article><?php endforeach; endif; ?></div></section>
<section class="admin-data-card payment-form-card"><div class="admin-data-header"><h2>Record Payment</h2></div><form method="post" class="payment-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><div class="payment-form-grid two-columns"><label>Outstanding Procurement Batch<select name="receipt_id" required><option value="">Select batch</option><?php foreach ($openReceipts as $receipt): ?><option value="<?= (int)$receipt['id'] ?>">BAT-<?= str_pad((string)$receipt['id'],4,'0',STR_PAD_LEFT) ?> · <?= htmlspecialchars($receipt['company_name'].' · '.$receipt['bill_number'],ENT_QUOTES,'UTF-8') ?> · Rs <?= number_format((float)$receipt['balance_amount'],2) ?></option><?php endforeach; ?></select></label><label>Amount Paid (Rs)<input type="number" name="amount" min="0.01" step="0.01" required></label></div><div class="payment-form-grid three-columns"><label>Check Number<input name="check_number" maxlength="100"></label><label>Check Date<input type="date" name="check_date"></label><label>Payment Date<input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required></label></div><label class="payment-full-field">Notes<textarea name="notes" rows="2" maxlength="2000"></textarea></label><button class="admin-primary-action payment-submit" type="submit">Record Payment</button></form></section>
<section class="admin-data-card operation-list-card"><div class="admin-data-header"><h2>Payment History</h2></div><div class="admin-data-table-wrap"><table class="admin-data-table payment-history-table"><thead><tr><th>Payment ID</th><th>Supplier</th><th>Batch</th><th>Bill No.</th><th>Amount</th><th>Check No.</th><th>Check Date</th><th>Payment Date</th><th>Recorded By</th></tr></thead><tbody><?php if (!$payments): ?><tr><td colspan="9" class="admin-empty-row">No payment history available.</td></tr><?php else: foreach ($payments as $payment): ?><tr><td>PAY-<?= str_pad((string)$payment['id'],4,'0',STR_PAD_LEFT) ?></td><td><?= htmlspecialchars($payment['company_name'],ENT_QUOTES,'UTF-8') ?></td><td>BAT-<?= str_pad((string)$payment['receipt_id'],4,'0',STR_PAD_LEFT) ?></td><td><?= htmlspecialchars($payment['bill_number'],ENT_QUOTES,'UTF-8') ?></td><td>Rs <?= number_format((float)$payment['amount'],2) ?></td><td><?= htmlspecialchars($payment['check_number']?:'—',ENT_QUOTES,'UTF-8') ?></td><td><?= htmlspecialchars($payment['check_date']?:'—',ENT_QUOTES,'UTF-8') ?></td><td><?= htmlspecialchars($payment['payment_date'],ENT_QUOTES,'UTF-8') ?></td><td><?= htmlspecialchars($payment['recorded_by'],ENT_QUOTES,'UTF-8') ?></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
</main></div><script src="assets/js/admin-dashboard.js"></script></body></html>
