<?php
declare(strict_types=1);

if (!in_array((string) ($_SESSION['role'] ?? ''), ['store-keeper', 'subject-officer', 'admin'], true)) {
    http_response_code(403);
    exit('You do not have permission to access this page.');
}
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/payment-status-control.php';

$canRecordPayments = !hasRole('subject-officer');
$activePage = hasRole('subject-officer') ? 'current-stock' : 'receipt-history';
$errors = [];
$success = (string) ($_SESSION['flash_success'] ?? '');
$aidOptions = [];
$selectedAidName = trim((string) ($_GET['aid'] ?? ''));
$returnToCurrentStock = (string) ($_GET['from'] ?? '') === 'current-stock';
unset($_SESSION['flash_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canRecordPayments) {
        http_response_code(403);
        exit('You do not have permission to record payments.');
    }

    $receiptId = filter_input(INPUT_POST, 'receipt_id', FILTER_VALIDATE_INT);
    $method = (string) ($_POST['payment_method'] ?? '');
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $checkNumber = trim((string) ($_POST['check_number'] ?? ''));
    $paymentDate = trim((string) ($_POST['payment_date'] ?? ''));

    if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) $errors[] = t('Session expired.');
    if (!$receiptId || !in_array($method, ['fully-paid', 'partially-paid'], true)) $errors[] = t('Select payment method.');
    if ($amount === false || $amount <= 0) $errors[] = t('Enter a payment amount greater than zero.');
    if ($checkNumber === '') $errors[] = t('Check number is required.');
    if ($paymentDate === '') $errors[] = t('Payment date is required.');

    if ($errors === []) {
        try {
            $db = database();
            $db->beginTransaction();
            $select = $db->prepare('SELECT id, supplier_id, balance_amount FROM stock_receipts WHERE id = :id FOR UPDATE');
            $select->execute(['id' => $receiptId]);
            $receipt = $select->fetch();
            if (!$receipt || (float) $receipt['balance_amount'] <= 0) throw new RuntimeException(t('No outstanding balance.'));

            $balance = (float) $receipt['balance_amount'];
            if ($method === 'fully-paid') $amount = $balance;
            if ((float) $amount > $balance) throw new RuntimeException(t('Payment exceeds due amount.'));

            $insert = $db->prepare('INSERT INTO supplier_payments (supplier_id, receipt_id, amount, check_number, payment_date, recorded_by) VALUES (:supplier, :receipt, :amount, :check_number, :payment_date, :user)');
            $insert->execute([
                'supplier' => $receipt['supplier_id'],
                'receipt' => $receiptId,
                'amount' => number_format((float) $amount, 2, '.', ''),
                'check_number' => $checkNumber,
                'payment_date' => $paymentDate,
                'user' => $_SESSION['user_id'],
            ]);
            $newBalance = round($balance - (float) $amount, 2);
            $update = $db->prepare('UPDATE stock_receipts SET paid_amount = paid_amount + :amount, balance_amount = :balance, payment_status = :status WHERE id = :id');
            $update->execute([
                'amount' => number_format((float) $amount, 2, '.', ''),
                'balance' => number_format($newBalance, 2, '.', ''),
                'status' => $newBalance <= 0 ? 'fully-paid' : 'partially-paid',
                'id' => $receiptId,
            ]);
            $db->commit();
            $_SESSION['flash_success'] = t('Payment recorded successfully.');
            header('Location: dashboard.php?page=receipt-history');
            exit;
        } catch (Throwable $exception) {
            if (isset($db) && $db->inTransaction()) $db->rollBack();
            $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : t('Unable to record payment.');
        }
    }
}

try {
    $rows = database()->query("SELECT r.id, r.quantity, r.received_date, r.total_cost, r.paid_amount, r.balance_amount, r.bill_number, r.payment_status, s.company_name, i.item_name
        FROM stock_receipts r
        JOIN suppliers s ON s.id = r.supplier_id
        JOIN inventory_items i ON i.id = r.item_id
        ORDER BY CASE r.payment_status WHEN 'unpaid' THEN 1 WHEN 'partially-paid' THEN 2 WHEN 'fully-paid' THEN 3 ELSE 4 END, r.received_date ASC, r.id ASC")->fetchAll();
    $aidOptions = database()->query("SELECT DISTINCT item_name FROM inventory_items WHERE TRIM(item_name) <> '' ORDER BY item_name")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $exception) {
    $rows = [];
    $errors[] = t('Unable to load receipts.');
}

if ($aidOptions === []) {
    $aidOptions = array_values(array_unique(array_map(static fn(array $row): string => (string) $row['item_name'], $rows)));
    natcasesort($aidOptions);
    $aidOptions = array_values($aidOptions);
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
    <title><?= htmlspecialchars(t('Receipt History'), ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="assets/css/admin-dashboard.css?v=44">
</head>
<body class="store-page store-receipt-history-page widms-unified-ui">
<?php if (hasRole('admin')): ?>
    <?php require __DIR__ . '/../../includes/admin-sidebar.php'; ?>
<?php elseif (hasRole('subject-officer')): ?>
    <?php require __DIR__ . '/../../includes/subject-officer-sidebar.php'; ?>
<?php else: ?>
    <?php require __DIR__ . '/../../includes/store-keeper-sidebar.php'; ?>
<?php endif; ?>
<div class="admin-shell">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button type="button" class="menu-button" id="menu-button" aria-label="Open navigation">&#9776;</button>
            <h1><?= htmlspecialchars(t('Receipt History'), ENT_QUOTES, 'UTF-8') ?></h1>
        </div>
        <div class="topbar-actions">
            <label class="search-box">
                <svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="11" cy="11" r="6"></circle><path d="m16 16 4 4"></path></svg>
                <input type="search" placeholder="<?= htmlspecialchars(t('Search anything...'), ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars(t('Search this page'), ENT_QUOTES, 'UTF-8') ?>">
            </label>
            <button class="notification-button" type="button" aria-label="Notifications">
                <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path><path d="M10 21h4"></path></svg>
            </button>
        </div>
    </header>
    <main class="dashboard-content admin-operation-page">
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></div><?php endif; ?>

        <section class="admin-data-card">
            <div class="receipt-history-filter-bar receipt-history-filter-bar-standalone" role="search" aria-label="<?= htmlspecialchars(t('Receipt filters'), ENT_QUOTES, 'UTF-8') ?>">
                    <?php if ($returnToCurrentStock): ?>
                        <a class="outline-action receipt-history-back-button" href="dashboard.php?page=current-stock"><span aria-hidden="true">&larr;</span> <?= htmlspecialchars(t('Back to Current Stock'), ENT_QUOTES, 'UTF-8') ?></a>
                    <?php endif; ?>
                    <label class="receipt-filter-field">
                        <span><?= htmlspecialchars(t('Status'), ENT_QUOTES, 'UTF-8') ?></span>
                        <select id="receipt-status-filter" aria-label="<?= htmlspecialchars(t('Filter by payment status'), ENT_QUOTES, 'UTF-8') ?>">
                            <option value=""><?= htmlspecialchars(t('All statuses'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="unpaid"><?= htmlspecialchars(t('Outstanding — Not Yet Paid'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="partially-paid"><?= htmlspecialchars(t('Partially Paid'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="fully-paid"><?= htmlspecialchars(t('Fully Paid'), ENT_QUOTES, 'UTF-8') ?></option>
                        </select>
                    </label>
                    <label class="receipt-filter-field">
                        <span><?= htmlspecialchars(t('Aid Type'), ENT_QUOTES, 'UTF-8') ?></span>
                        <select id="receipt-aid-filter" aria-label="<?= htmlspecialchars(t('Filter by aid'), ENT_QUOTES, 'UTF-8') ?>">
                            <option value=""><?= htmlspecialchars(t('All aids'), ENT_QUOTES, 'UTF-8') ?></option>
                            <?php foreach ($aidOptions as $aidOption): ?><option value="<?= htmlspecialchars($aidOption, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedAidName === (string) $aidOption ? 'selected' : '' ?>><?= htmlspecialchars($aidOption, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                        </select>
                    </label>
                    <button type="button" class="outline-action receipt-clear-filters" id="receipt-clear-filters" hidden><?= htmlspecialchars(t('Clear filters'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
            <div class="admin-data-table-wrap store-table-card">
                <table class="admin-data-table receipt-history-table">
                    <thead><tr><th><?= t('Batch') ?></th><th><?= t('Aid') ?></th><th><?= t('Qty') ?></th><th><?= t('Date') ?></th><th><?= t('Supplier') ?></th><th><?= t('Bill') ?></th><th><?= t('Total') ?></th><th><?= t('Paid') ?></th><th><?= t('Due') ?></th><th><?= t('Status') ?></th><th><?= t('Action') ?></th></tr></thead>
                    <tbody>
                    <?php if (!$rows): ?><tr><td colspan="11" class="admin-empty-row"><?= htmlspecialchars(t('No receipt history available.'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                    <?php else: foreach ($rows as $row): ?>
                        <tr data-aid-name="<?= htmlspecialchars($row['item_name'], ENT_QUOTES, 'UTF-8') ?>">
                            <td>BAT-<?= str_pad((string) $row['id'], 4, '0', STR_PAD_LEFT) ?></td>
                            <td><?= htmlspecialchars($row['item_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= (int) $row['quantity'] ?></td>
                            <td><?= htmlspecialchars($row['received_date'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['company_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['bill_number'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>Rs <?= number_format((float) $row['total_cost'], 2) ?></td>
                            <td>Rs <?= number_format((float) $row['paid_amount'], 2) ?></td>
                            <td>Rs <?= number_format((float) $row['balance_amount'], 2) ?></td>
                            <td><?= renderPaymentStatusControl((string) $row['payment_status'], (string) ($labels[$row['payment_status']] ?? $row['payment_status'])) ?></td>
                            <td><?php if (!$canRecordPayments): ?><span class="paid-label"><?= htmlspecialchars(t('View only'), ENT_QUOTES, 'UTF-8') ?></span><?php elseif ((float) $row['balance_amount'] > 0): ?><button type="button" class="admin-primary-action receipt-pay-button" data-pay data-id="<?= (int) $row['id'] ?>" data-balance="<?= (float) $row['balance_amount'] ?>"><?= htmlspecialchars(t('Pay'), ENT_QUOTES, 'UTF-8') ?></button><?php else: ?><span class="paid-label"><?= htmlspecialchars(t('Paid'), ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    <?php if ($rows): ?><tr id="receipt-filter-empty" hidden><td colspan="11" class="admin-empty-row"><?= htmlspecialchars(t('No receipts match the selected filter.'), ENT_QUOTES, 'UTF-8') ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>

<div class="payment-modal" id="payment-modal" hidden>
    <div class="payment-modal-card">
        <button type="button" class="payment-modal-close" data-close-pay><?= htmlspecialchars(t('Cancel'), ENT_QUOTES, 'UTF-8') ?></button>
        <h2><?= htmlspecialchars(t('Record Payment'), ENT_QUOTES, 'UTF-8') ?></h2>
        <p><?= htmlspecialchars(t('Enter payment details.'), ENT_QUOTES, 'UTF-8') ?></p>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="receipt_id" id="pay-receipt-id">
            <label><?= t('Payment Method') ?><select name="payment_method" id="payment-method" required><option value="partially-paid"><?= t('Partially Paid') ?></option><option value="fully-paid"><?= t('Fully Paid') ?></option></select></label>
            <label><?= t('Amount Paid (Rs)') ?><input type="number" name="amount" id="pay-amount" min="0.01" step="0.01" required></label>
            <label id="pay-due-field"><?= t('Due Payment (Rs)') ?><input type="text" id="pay-due" readonly></label>
            <label><?= t('Check Number') ?> <span class="required-mark">*</span><input type="text" name="check_number" required></label>
            <label><?= t('Payment Date') ?><input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required></label>
            <div class="payment-modal-actions"><button class="admin-primary-action record-receipt-button" type="submit"><?= t('Record Payment') ?></button></div>
        </form>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('payment-modal');
    const receiptId = document.getElementById('pay-receipt-id');
    const amount = document.getElementById('pay-amount');
    const due = document.getElementById('pay-due');
    const dueField = document.getElementById('pay-due-field');
    const method = document.getElementById('payment-method');
    let balance = 0;
    const update = () => {
        const fullyPaid = method.value === 'fully-paid';
        dueField.hidden = fullyPaid;
        amount.value = fullyPaid ? balance.toFixed(2) : '';
        due.value = `Rs ${Math.max(0, balance - (Number(amount.value) || 0)).toFixed(2)}`;
    };
    document.querySelectorAll('[data-pay]').forEach(button => button.addEventListener('click', () => {
        balance = Number(button.dataset.balance) || 0;
        receiptId.value = button.dataset.id;
        amount.max = balance;
        method.value = 'partially-paid';
        update();
        modal.hidden = false;
        amount.focus();
    }));
    method.addEventListener('change', update);
    amount.addEventListener('input', () => { due.value = `Rs ${Math.max(0, balance - (Number(amount.value) || 0)).toFixed(2)}`; });
    document.querySelector('[data-close-pay]').addEventListener('click', () => { modal.hidden = true; });
    modal.addEventListener('click', event => { if (event.target === modal) modal.hidden = true; });

    const statusFilter = document.getElementById('receipt-status-filter');
    const aidFilter = document.getElementById('receipt-aid-filter');
    const clearFilters = document.getElementById('receipt-clear-filters');
    const receiptRows = Array.from(document.querySelectorAll('.receipt-history-table tbody tr')).filter(row => !row.querySelector('.admin-empty-row'));
    const filterEmpty = document.getElementById('receipt-filter-empty');
    const applyReceiptFilters = () => {
        const selectedStatus = statusFilter?.value || '';
        const selectedAid = aidFilter?.value || '';
        const searchTerm = document.querySelector('.topbar .search-box input')?.value.trim().toLowerCase() || '';
        let visibleRows = 0;
        receiptRows.forEach(row => {
            const statusBadge = row.querySelector('.payment-badge');
            const matchesStatus = selectedStatus === '' || statusBadge?.classList.contains(selectedStatus);
            const matchesAid = selectedAid === '' || row.dataset.aidName === selectedAid;
            const matchesSearch = searchTerm === '' || row.textContent.toLowerCase().includes(searchTerm);
            row.hidden = !(matchesStatus && matchesAid && matchesSearch);
            if (!row.hidden) visibleRows += 1;
        });
        if (filterEmpty) filterEmpty.hidden = visibleRows > 0 || receiptRows.length === 0;
        if (clearFilters) clearFilters.hidden = selectedStatus === '' && selectedAid === '' && searchTerm === '';
    };
    statusFilter?.addEventListener('change', applyReceiptFilters);
    aidFilter?.addEventListener('change', applyReceiptFilters);
    document.querySelector('.topbar')?.addEventListener('input', event => {
        if (event.target.matches('.search-box input')) applyReceiptFilters();
    });
    clearFilters?.addEventListener('click', () => {
        statusFilter.value = '';
        aidFilter.value = '';
        const searchInput = document.querySelector('.topbar .search-box input');
        if (searchInput) searchInput.value = '';
        applyReceiptFilters();
    });
    applyReceiptFilters();

});
</script>
<script src="assets/js/admin-dashboard.js"></script>
</body>
</html>
