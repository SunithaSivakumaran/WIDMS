<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/activity.php';

if (!in_array((string) ($_SESSION['role'] ?? ''), ['admin', 'subject-officer'], true)) {
    http_response_code(403);
    exit('You do not have permission to manage suppliers.');
}

$activePage = 'suppliers';
$errors = [];
$success = (string) ($_SESSION['flash_success'] ?? '');
unset($_SESSION['flash_success']);
$suppliers = [];
$inventoryItems = [];
$balances = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } elseif ($action === 'create-supplier') {
        $company = trim((string) ($_POST['company_name'] ?? ''));
        $contact = trim((string) ($_POST['contact_person'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));
        if (mb_strlen($company) < 2 || mb_strlen($company) > 150) $errors[] = 'Enter a valid company name.';
        if ($contact !== '' && mb_strlen($contact) > 120) $errors[] = 'Contact person is too long.';
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid supplier email.';
        if ($phone !== '' && !preg_match('/^[0-9+()\-\s]{7,25}$/', $phone)) $errors[] = 'Enter a valid supplier phone number.';
        if (mb_strlen($address) > 255) $errors[] = 'Address is too long.';
        if ($errors === []) {
            try {
                $statement = database()->prepare('INSERT INTO suppliers (company_name, contact_person, email, phone, address, created_by) VALUES (:company, :contact, :email, :phone, :address, :user_id)');
                $statement->execute(['company' => $company, 'contact' => $contact ?: null, 'email' => $email ?: null, 'phone' => $phone ?: null, 'address' => $address ?: null, 'user_id' => $_SESSION['user_id']]);
                $supplierId = (int) database()->lastInsertId();
                logActivity('Suppliers', 'Registered supplier — ' . $company, 'SUP-' . $supplierId, 'done');
                $_SESSION['flash_success'] = 'Supplier registered successfully.';
                unset($_SESSION['csrf_token']);
                header('Location: dashboard.php?page=suppliers'); exit;
            } catch (PDOException $exception) {
                error_log($exception->getMessage());
                $errors[] = $exception->getCode() === '23000' ? 'A supplier with that company name already exists.' : 'Unable to register the supplier. Import database/migration_supplier_workflow.sql first.';
            }
        }
    } elseif ($action === 'authorize-item') {
        $supplierId = filter_input(INPUT_POST, 'supplier_id', FILTER_VALIDATE_INT);
        $itemId = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
        if (!$supplierId || !$itemId) $errors[] = 'Select a supplier and an item.';
        if ($errors === []) {
            try {
                $statement = database()->prepare('INSERT INTO supplier_authorized_items (supplier_id, item_id, authorized_by) VALUES (:supplier, :item, :user)');
                $statement->execute(['supplier' => $supplierId, 'item' => $itemId, 'user' => $_SESSION['user_id']]);
                logActivity('Suppliers', 'Authorized an inventory item for a supplier', 'SUP-' . $supplierId, 'done');
                $_SESSION['flash_success'] = 'Item authorization added.';
                unset($_SESSION['csrf_token']);
                header('Location: dashboard.php?page=suppliers'); exit;
            } catch (PDOException $exception) {
                error_log($exception->getMessage());
                $errors[] = $exception->getCode() === '23000' ? 'That supplier is already authorized for the selected item.' : 'Unable to authorize the item.';
            }
        }
    } elseif ($action === 'toggle-status') {
        $supplierId = filter_input(INPUT_POST, 'supplier_id', FILTER_VALIDATE_INT);
        $status = (string) ($_POST['status'] ?? '');
        if (!$supplierId || !in_array($status, ['active', 'inactive'], true)) $errors[] = 'Invalid supplier status update.';
        if ($errors === []) {
            $statement = database()->prepare('UPDATE suppliers SET status = :status WHERE id = :id');
            $statement->execute(['status' => $status, 'id' => $supplierId]);
            logActivity('Suppliers', ucfirst($status) . ' supplier account', 'SUP-' . $supplierId, 'done');
            $_SESSION['flash_success'] = 'Supplier status updated.';
            unset($_SESSION['csrf_token']);
            header('Location: dashboard.php?page=suppliers'); exit;
        }
    }
}

try {
    $suppliers = database()->query("SELECT s.*, GROUP_CONCAT(CONCAT(i.item_name, IF(i.variety = '', '', CONCAT(' — ', i.variety))) ORDER BY i.item_name SEPARATOR ', ') AS authorized_items FROM suppliers s LEFT JOIN supplier_authorized_items sai ON sai.supplier_id = s.id LEFT JOIN inventory_items i ON i.id = sai.item_id GROUP BY s.id ORDER BY s.company_name")->fetchAll();
    $inventoryItems = database()->query('SELECT id, item_name, variety FROM inventory_items ORDER BY item_name, variety')->fetchAll();
    $balances = database()->query('SELECT s.id, s.company_name, COALESCE(SUM(r.total_cost), 0) invoiced, COALESCE(SUM(r.paid_amount), 0) paid, COALESCE(SUM(r.balance_amount), 0) balance FROM suppliers s LEFT JOIN stock_receipts r ON r.supplier_id = s.id GROUP BY s.id ORDER BY s.company_name')->fetchAll();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $errors[] = 'Supplier workflow is unavailable. Import database/migration_supplier_workflow.sql.';
}

$sidebar = $_SESSION['role'] === 'admin' ? __DIR__ . '/admin-sidebar.php' : __DIR__ . '/subject-officer-sidebar.php';
?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Supplier Management | WIDMS</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="assets/css/admin-dashboard.css" rel="stylesheet">
    </head>
    <body>
        <?php require $sidebar; ?>
        <div class="admin-shell">
            <header class="topbar">
                <div class="d-flex align-items-center gap-3">
                    <button type="button" class="menu-button" id="menu-button" aria-label="Open navigation">&#9776;</button>
                    <h1>Supplier Management</h1>
                </div>
            </header>
        <main class="dashboard-content supplier-workflow-page">

            <?php if ($success !== ''): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php endif; ?>

            <?php if ($errors !== []): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">

                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>

                </ul>
            </div>
            <?php endif; ?>

            <div class="supplier-workflow-grid">
                <section class="admin-data-card">
                    <div class="admin-data-header">
                        <h2>Register Supplier</h2>
                    </div>
                    <form method="post" class="supplier-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="create-supplier">
                        <div class="supplier-form-grid">
                            <label>
                                Company Name *
                                <input name="company_name" maxlength="150" required>
                            </label>
                            <label>
                                Contact Person
                                <input name="contact_person" maxlength="120">
                            </label>
                            <label>
                                Email
                                <input type="email" name="email" maxlength="150">
                            </label>
                            <label>
                                Phone
                                <input type="tel" name="phone" maxlength="25">
                            </label>
                            <label class="full">
                                Address
                                <textarea name="address" rows="2" maxlength="255"></textarea>
                            </label>
                        </div>
                        <button class="admin-primary-action" type="submit">Register Supplier</button>
                    </form>
                </section>
                <section class="admin-data-card">
                    <div class="admin-data-header">
                        <h2>Authorize Supplier Item</h2>
                    </div>
                    <form method="post" class="supplier-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="authorize-item">
                        <label>
                            Supplier
                            <select name="supplier_id" required>
                                <option value="">Select supplier</option>

                                <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?= (int)$supplier['id'] ?>">
                                    <?= htmlspecialchars($supplier['company_name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                                <?php endforeach; ?>

                            </select>
                        </label>
                        <label>
                            Authorized Item
                            <select name="item_id" required>
                                <option value="">Select item</option>

                                <?php foreach ($inventoryItems as $item): ?>
                                <option value="<?= (int)$item['id'] ?>">
                                    <?= htmlspecialchars($item['item_name'] . ($item['variety'] ? ' — '.$item['variety'] : ''), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                                <?php endforeach; ?>
                            
                            </select>
                        </label>
                        <button class="admin-primary-action" type="submit">Add Authorization</button>
                    </form>
                </section>
            </div>
            <div class="supplier-management-grid supplier-results-grid">
                <section class="admin-data-card">
                    <div class="admin-data-header">
                        <h2>Registered Supplier Companies</h2>
                    </div>
                    <div class="admin-data-table-wrap">
                        <table class="admin-data-table suppliers-table">
                            <thead>
                                <tr>
                                    <th>Company</th>
                                    <th>Contact</th>
                                    <th>Phone</th>
                                    <th>Authorized Items</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                
                                <?php if ($suppliers === []): ?>
                                <tr>
                                    <td colspan="6" class="admin-empty-row">
                                        No registered suppliers available.
                                    </td>
                                </tr>
                                <?php else: 
                                foreach ($suppliers as $supplier): ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <?= htmlspecialchars($supplier['company_name'], ENT_QUOTES, 'UTF-8') ?>
                                        </strong>
                                        <small>
                                            <?= htmlspecialchars((string)$supplier['email'], ENT_QUOTES, 'UTF-8') ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars((string)$supplier['contact_person'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars((string)$supplier['phone'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($supplier['authorized_items'] ?: 'None', ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td>
                                        <?= ucfirst($supplier['status']) ?>
                                    </td>
                                    <td>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="action" value="toggle-status">
                                            <input type="hidden" name="supplier_id" value="<?= (int)$supplier['id'] ?>">
                                            <input type="hidden" name="status" value="<?= $supplier['status'] === 'active' ? 'inactive' : 'active' ?>">
                                            <button class="admin-outline-action" type="submit">
                                                <?= $supplier['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; endif; ?>
                                
                            </tbody>
                        </table>
                    </div>
                </section>
                <section class="admin-data-card">
                    <div class="admin-data-header">
                        <h2>Supplier Balance Summary</h2>
                    </div>
                    <div class="supplier-balance-list">
                        <?php if ($balances === []): ?>
                            <p>No supplier balances available.</p>
                            <?php else: foreach ($balances as $balance): ?>
                                <article>
                                    <div>
                                        <strong>
                                            <?= htmlspecialchars($balance['company_name'], ENT_QUOTES, 'UTF-8') ?>
                                        </strong>
                                        <small>
                                            Invoiced: Rs <?= number_format((float)$balance['invoiced'], 2) ?> · Paid: Rs <?= number_format((float)$balance['paid'], 2) ?>
                                        </small>
                                    </div>
                                    <b>Rs <?= number_format((float)$balance['balance'], 2) ?></b>
                                </article><?php endforeach; endif; ?>
                    </div>
                </section>
            </div>
        </main>
        </div>
        <script src="assets/js/admin-dashboard.js"></script>
    </body>
</html>
