<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/activity.php';

if (!in_array((string) ($_SESSION['role'] ?? ''), ['admin', 'subject-officer'], true)) {
    http_response_code(403);
    exit('You do not have permission to manage suppliers.');
}

$supplierPageMode = (string) ($supplierPageMode ?? 'configuration');
if (!in_array($supplierPageMode, ['configuration', 'details', 'balances'], true)) {
    $supplierPageMode = 'configuration';
}

$supplierPages = [
    'configuration' => ['page' => 'supplier-config', 'title' => 'Supplier Configuration'],
    'details' => ['page' => 'supplier-details', 'title' => 'Registered Supplier Details'],
    'balances' => ['page' => 'supplier-balances', 'title' => 'Supplier Balance Summary'],
];
$activePage = $supplierPages[$supplierPageMode]['page'];
$pageTitle = $supplierPages[$supplierPageMode]['title'];
$errors = [];
$success = (string) ($_SESSION['flash_success'] ?? '');
unset($_SESSION['flash_success']);
$suppliers = [];
$inventoryItems = [];
$balances = [];
$supplierAllocations = [];
$supplierValues = [
    'company_name' => '', 'contact_person' => '', 'email' => '', 'phone' => '', 'address' => '',
    'item_id' => '', 'valid_from' => date('Y-m-d'), 'validity_period' => '12', 'validity_unit' => 'months',
];

// Persist expiry as soon as the supplier workflow is opened. This keeps an
// agreement that passed its due date unavailable everywhere that checks the
// active status, while retaining its history for reporting.
try {
    database()->exec("UPDATE supplier_authorized_items SET status='inactive', deactivation_reason=COALESCE(NULLIF(deactivation_reason,''),'Validity period expired automatically.'), deactivated_at=COALESCE(deactivated_at,NOW()) WHERE status='active' AND valid_from IS NOT NULL AND validity_period IS NOT NULL AND ((validity_unit='months' AND CURDATE() >= DATE_ADD(valid_from, INTERVAL validity_period MONTH)) OR (validity_unit='years' AND CURDATE() >= DATE_ADD(valid_from, INTERVAL validity_period YEAR)))");
} catch (PDOException $exception) {
    error_log($exception->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } elseif ($action === 'create-supplier' && $supplierPageMode === 'configuration') {
        $company = trim((string) ($_POST['company_name'] ?? ''));
        $contact = trim((string) ($_POST['contact_person'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $validFrom = trim((string) ($_POST['valid_from'] ?? ''));
        $validityPeriod = (int) ($_POST['validity_period'] ?? 0);
        $validityUnit = (string) ($_POST['validity_unit'] ?? '');
        $supplierValues = ['company_name'=>$company, 'contact_person'=>$contact, 'email'=>$email, 'phone'=>$phone, 'address'=>$address, 'item_id'=>(string)$itemId, 'valid_from'=>$validFrom, 'validity_period'=>(string)$validityPeriod, 'validity_unit'=>$validityUnit];
        if (mb_strlen($company) < 2 || mb_strlen($company) > 150) $errors[] = 'Enter a valid company name.';
        if ($contact !== '' && mb_strlen($contact) > 120) $errors[] = 'Contact person is too long.';
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid supplier email.';
        if ($phone !== '' && !preg_match('/^[0-9+()\-\s]{7,25}$/', $phone)) $errors[] = 'Enter a valid supplier phone number.';
        if (mb_strlen($address) > 255) $errors[] = 'Address is too long.';
        if ($itemId < 1) $errors[] = 'Select an aid item for this supplier.';
        $startDate = DateTimeImmutable::createFromFormat('!Y-m-d', $validFrom);
        if (!$startDate || $startDate->format('Y-m-d') !== $validFrom) $errors[] = 'Enter a valid supplier period start date.';
        if (!in_array($validityUnit, ['months', 'years'], true)) $errors[] = 'Select months or years for the supplier validity period.';
        if ($validityPeriod < 1 || ($validityUnit === 'months' && $validityPeriod > 120) || ($validityUnit === 'years' && $validityPeriod > 20)) $errors[] = 'Enter a valid supplier period duration.';
        if ($errors === []) {
            try {
                $connection = database();
                $connection->beginTransaction();
                $findSupplier = $connection->prepare('SELECT id, company_name, status FROM suppliers WHERE company_name = :company LIMIT 1 FOR UPDATE');
                $findSupplier->execute(['company' => $company]);
                $existingSupplier = $findSupplier->fetch();
                $isExistingSupplier = $existingSupplier !== false;

                if ($isExistingSupplier) {
                    $supplierId = (int) $existingSupplier['id'];
                    if ($existingSupplier['status'] !== 'active') {
                        throw new RuntimeException('This company is permanently deactivated and cannot receive new products.');
                    }
                    $allocationCount = $connection->prepare("SELECT COUNT(*) FROM supplier_authorized_items WHERE supplier_id = :supplier AND status = 'active'");
                    $allocationCount->execute(['supplier' => $supplierId]);
                    if ((int) $allocationCount->fetchColumn() >= 2) {
                        throw new RuntimeException('This company already has the maximum of two active products.');
                    }
                } else {
                    $statement = $connection->prepare('INSERT INTO suppliers (company_name, contact_person, email, phone, address, valid_from, validity_period, validity_unit, created_by) VALUES (:company, :contact, :email, :phone, :address, :valid_from, :validity_period, :validity_unit, :user_id)');
                    $statement->execute(['company'=>$company, 'contact'=>$contact ?: null, 'email'=>$email ?: null, 'phone'=>$phone ?: null, 'address'=>$address ?: null, 'valid_from'=>$validFrom, 'validity_period'=>$validityPeriod, 'validity_unit'=>$validityUnit, 'user_id'=>$_SESSION['user_id']]);
                    $supplierId = (int) $connection->lastInsertId();
                }

                $itemCheck = $connection->prepare("SELECT i.id FROM inventory_items i WHERE i.id = :item AND NOT EXISTS (SELECT 1 FROM supplier_authorized_items sai WHERE sai.item_id = i.id AND sai.status = 'active') FOR UPDATE");
                $itemCheck->execute(['item' => $itemId]);
                if (!$itemCheck->fetchColumn()) throw new RuntimeException('An aid item is unavailable or is already allocated to another supplier.');
                $allocation = $connection->prepare('INSERT INTO supplier_authorized_items (supplier_id, item_id, valid_from, validity_period, validity_unit, authorized_by) VALUES (:supplier, :item, :valid_from, :validity_period, :validity_unit, :user)');
                $allocation->execute(['supplier'=>$supplierId, 'item'=>$itemId, 'valid_from'=>$validFrom, 'validity_period'=>$validityPeriod, 'validity_unit'=>$validityUnit, 'user'=>$_SESSION['user_id']]);
                $connection->commit();
                $savedCompanyName = $isExistingSupplier ? (string) $existingSupplier['company_name'] : $company;
                logActivity('Suppliers', ($isExistingSupplier ? 'Added a product agreement to supplier — ' : 'Registered supplier and allocated its first product — ') . $savedCompanyName, 'SUP-' . $supplierId, 'done');
                $_SESSION['flash_success'] = $isExistingSupplier
                    ? 'Product added to the existing supplier successfully.'
                    : 'Supplier and product allocation saved successfully.';
                unset($_SESSION['csrf_token']);
                header('Location: dashboard.php?page=supplier-config'); exit;
            } catch (Throwable $exception) {
                if (isset($connection) && $connection->inTransaction()) $connection->rollBack();
                error_log($exception->getMessage());
                $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : ($exception->getCode() === '23000' ? 'A supplier with that company name already exists.' : 'Unable to register the supplier and allocate its product.');
            }
        }
    } elseif ($action === 'deactivate-product' && $supplierPageMode === 'details') {
        $supplierId = filter_input(INPUT_POST, 'supplier_id', FILTER_VALIDATE_INT);
        $itemId = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
        $deactivationReason = trim((string) ($_POST['product_deactivation_reason'] ?? ''));
        if (!$supplierId || !$itemId) $errors[] = 'Invalid product deactivation request.';
        if ($deactivationReason === '') $errors[] = 'A product deactivation reason is required.';
        if (mb_strlen($deactivationReason) > 500) $errors[] = 'The product deactivation reason is too long.';
        if ($errors === []) {
            try {
                $connection = database();
                $connection->beginTransaction();
                $allocationId = filter_input(INPUT_POST, 'allocation_id', FILTER_VALIDATE_INT);
                if (!$allocationId) throw new RuntimeException('Invalid product agreement.');
                $findAgreement = $connection->prepare('SELECT sai.id, sai.status, s.company_name, i.item_name FROM supplier_authorized_items sai JOIN suppliers s ON s.id = sai.supplier_id JOIN inventory_items i ON i.id = sai.item_id WHERE sai.id = :allocation AND sai.supplier_id = :supplier AND sai.item_id = :item FOR UPDATE');
                $findAgreement->execute(['allocation' => $allocationId, 'supplier' => $supplierId, 'item' => $itemId]);
                $agreement = $findAgreement->fetch();
                if (!$agreement) throw new RuntimeException('Product agreement not found.');
                if ($agreement['status'] !== 'active') throw new RuntimeException('This product agreement is already permanently deactivated.');
                $statement = $connection->prepare("UPDATE supplier_authorized_items SET status = 'inactive', deactivation_reason = :reason, deactivated_at = NOW() WHERE id = :allocation");
                $statement->execute(['reason'=>$deactivationReason, 'allocation'=>$allocationId]);
                $connection->commit();
                logActivity('Suppliers', 'Permanently deactivated product agreement — ' . $agreement['company_name'] . ' / ' . $agreement['item_name'] . '. Reason: ' . $deactivationReason, 'SUP-' . $supplierId, 'done');
                $_SESSION['flash_success'] = 'Product agreement permanently deactivated successfully.';
                unset($_SESSION['csrf_token']);
                header('Location: dashboard.php?page=supplier-details'); exit;
            } catch (Throwable $exception) {
                if (isset($connection) && $connection->inTransaction()) $connection->rollBack();
                error_log($exception->getMessage());
                $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to deactivate the product. Run the latest migration.';
            }
        }
    }
}

try {
    $connection = database();
    $suppliers = $connection->query("SELECT s.*, SUM(CASE WHEN sai.status = 'active' THEN 1 ELSE 0 END) AS item_count FROM suppliers s LEFT JOIN supplier_authorized_items sai ON sai.supplier_id = s.id GROUP BY s.id ORDER BY s.company_name")->fetchAll();
    $allocationRows = $connection->query("SELECT sai.id, sai.supplier_id, sai.item_id, sai.valid_from, sai.validity_period, sai.validity_unit, sai.status, sai.deactivation_reason, sai.deactivated_at, i.item_name, i.variety, CASE WHEN sai.valid_from IS NULL OR sai.validity_period IS NULL OR sai.validity_unit IS NULL THEN NULL WHEN sai.validity_unit = 'years' THEN DATE_SUB(DATE_ADD(sai.valid_from, INTERVAL sai.validity_period YEAR), INTERVAL 1 DAY) ELSE DATE_SUB(DATE_ADD(sai.valid_from, INTERVAL sai.validity_period MONTH), INTERVAL 1 DAY) END AS valid_until, CASE WHEN sai.status = 'inactive' THEN 'inactive' WHEN sai.valid_from IS NOT NULL AND sai.valid_from > CURDATE() THEN 'scheduled' WHEN sai.valid_from IS NOT NULL AND sai.validity_period IS NOT NULL AND sai.validity_unit = 'years' AND CURDATE() >= DATE_ADD(sai.valid_from, INTERVAL sai.validity_period YEAR) THEN 'expired' WHEN sai.valid_from IS NOT NULL AND sai.validity_period IS NOT NULL AND sai.validity_unit = 'months' AND CURDATE() >= DATE_ADD(sai.valid_from, INTERVAL sai.validity_period MONTH) THEN 'expired' ELSE 'active' END AS allocation_status FROM supplier_authorized_items sai JOIN inventory_items i ON i.id = sai.item_id ORDER BY FIELD(sai.status, 'active', 'inactive'), i.item_name, i.variety")->fetchAll();
    foreach ($allocationRows as $allocationRow) $supplierAllocations[(int)$allocationRow['supplier_id']][] = $allocationRow;
    $inventoryItems = $connection->query("SELECT i.id, i.item_name, i.variety FROM inventory_items i WHERE NOT EXISTS (SELECT 1 FROM supplier_authorized_items sai WHERE sai.item_id = i.id AND sai.status = 'active') ORDER BY i.item_name, i.variety")->fetchAll();
    $balances = $connection->query('SELECT s.id, s.company_name, COALESCE(SUM(r.total_cost), 0) invoiced, COALESCE(SUM(r.paid_amount), 0) paid, COALESCE(SUM(r.balance_amount), 0) balance FROM suppliers s LEFT JOIN stock_receipts r ON r.supplier_id = s.id GROUP BY s.id ORDER BY s.company_name')->fetchAll();
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
        <title><?= htmlspecialchars(t($pageTitle), ENT_QUOTES, 'UTF-8') ?> | SWPCS</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="assets/css/admin-dashboard.css?v=37" rel="stylesheet">
    </head>
    <body>
        <?php require $sidebar; ?>
        <div class="admin-shell">
            <header class="topbar">
                <div class="d-flex align-items-center gap-3">
                    <button type="button" class="menu-button" id="menu-button" aria-label="Open navigation">&#9776;</button>
                    <h1><?= htmlspecialchars(t($pageTitle), ENT_QUOTES, 'UTF-8') ?></h1>
                </div>
            </header>
        <main class="dashboard-content supplier-workflow-page">

            <?php if ($success !== ''): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars(t($success), ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php endif; ?>

            <?php if ($errors !== []): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">

                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars(t($error), ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>

                </ul>
            </div>
            <?php endif; ?>

            <?php if ($supplierPageMode === 'configuration'): ?>
            <div class="supplier-workflow-grid">
                <section class="admin-data-card supplier-config-card supplier-register-allocation-card">
                    <div class="admin-data-header supplier-config-card-header">
                        <div class="supplier-config-title">
                            <span class="supplier-config-symbol" aria-hidden="true">+</span>
                            <div>
                                <h2><?= htmlspecialchars(t('Register Supplier & Allocate Product'), ENT_QUOTES, 'UTF-8') ?></h2>
                                <p><?= htmlspecialchars(t('Choose an existing company or type a new company name, then add its product agreement.'), ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        </div>
                    </div>
                    <form method="post" class="supplier-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="create-supplier">
                        <div class="supplier-form-grid">
                            <div class="full supplier-company-picker">
                                <label for="supplier-company-name"><?= htmlspecialchars(t('Company Name'), ENT_QUOTES, 'UTF-8') ?> <span class="supplier-required">*</span></label>
                                <div class="supplier-company-combobox" data-company-combobox>
                                    <input id="supplier-company-name" name="company_name" maxlength="150" value="<?= htmlspecialchars($supplierValues['company_name'], ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= htmlspecialchars(t('Select an existing company or type a new name'), ENT_QUOTES, 'UTF-8') ?>" autocomplete="off" role="combobox" aria-autocomplete="list" aria-controls="supplier-company-options" aria-expanded="false" required>
                                    <button class="supplier-company-toggle" type="button" aria-label="<?= htmlspecialchars(t('Show existing companies'), ENT_QUOTES, 'UTF-8') ?>" aria-controls="supplier-company-options" aria-expanded="false"><span aria-hidden="true">&#9662;</span></button>
                                    <div class="supplier-company-options" id="supplier-company-options" role="listbox" hidden>
                                    <?php foreach ($suppliers as $companyOption): ?>
                                        <?php if ($companyOption['status'] === 'active' && (int) $companyOption['item_count'] < 2): ?>
                                        <button type="button" role="option" data-company-option data-company-name="<?= htmlspecialchars($companyOption['company_name'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($companyOption['company_name'], ENT_QUOTES, 'UTF-8') ?></button>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                        <p class="supplier-company-empty" data-company-empty hidden><?= htmlspecialchars(t('No matching companies. Type the new company name to create it.'), ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                </div>
                                <small><?= htmlspecialchars(t('Select a suggestion to use an existing company, or continue typing to create a new company.'), ENT_QUOTES, 'UTF-8') ?></small>
                            </div>
                            <label>
                                <?= htmlspecialchars(t('Contact Person'), ENT_QUOTES, 'UTF-8') ?>
                                <input name="contact_person" maxlength="120" value="<?= htmlspecialchars($supplierValues['contact_person'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Full name">
                            </label>
                            <label>
                                Email
                                <input type="email" name="email" maxlength="150" value="<?= htmlspecialchars($supplierValues['email'], ENT_QUOTES, 'UTF-8') ?>" placeholder="name@company.lk">
                            </label>
                            <label>
                                Phone
                                <input type="tel" name="phone" maxlength="25" value="<?= htmlspecialchars($supplierValues['phone'], ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. 077 123 4567">
                            </label>
                            <label class="full">
                                Address
                                <textarea name="address" rows="3" maxlength="255" placeholder="Office or warehouse address"><?= htmlspecialchars($supplierValues['address'], ENT_QUOTES, 'UTF-8') ?></textarea>
                            </label>
                            <fieldset class="supplier-product-section full">
                                <legend>Product Agreement</legend>
                                <p><?= htmlspecialchars(t('Select the product supplied by this company.'), ENT_QUOTES, 'UTF-8') ?></p>
                                <div class="supplier-product-grid supplier-product-grid-single">
                                    <label>
                                        Aid Item <span class="supplier-required">*</span>
                                        <select name="item_id" required>
                                            <option value="">Select item</option>
                                            <?php foreach ($inventoryItems as $item): ?>
                                            <option value="<?= (int)$item['id'] ?>" <?= $supplierValues['item_id'] === (string)$item['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars(widmsAidItemName((string)$item['item_name']) . ($item['variety'] ? ' — ' . $item['variety'] : ''), ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                </div>
                            </fieldset>
                            <fieldset class="supplier-validity-section full">
                                <legend>Product Validity Period</legend>
                                <p>Set the start date and duration for this product agreement.</p>
                                <div class="supplier-validity-grid">
                                    <label>
                                        Start Date <span class="supplier-required">*</span>
                                        <input type="date" name="valid_from" value="<?= htmlspecialchars($supplierValues['valid_from'], ENT_QUOTES, 'UTF-8') ?>" required>
                                    </label>
                                    <label>
                                        Validity Duration <span class="supplier-required">*</span>
                                        <input type="number" name="validity_period" min="1" max="120" value="<?= htmlspecialchars($supplierValues['validity_period'], ENT_QUOTES, 'UTF-8') ?>" required>
                                    </label>
                                    <label>
                                        Period Unit <span class="supplier-required">*</span>
                                        <select name="validity_unit" required>
                                            <option value="months" <?= $supplierValues['validity_unit'] === 'months' ? 'selected' : '' ?>>Months</option>
                                            <option value="years" <?= $supplierValues['validity_unit'] === 'years' ? 'selected' : '' ?>>Years</option>
                                        </select>
                                    </label>
                                </div>
                            </fieldset>
                        </div>
                        <button class="admin-primary-action" type="submit"><?= htmlspecialchars(t('Save Supplier & Product'), ENT_QUOTES, 'UTF-8') ?></button>
                    </form>
                </section>
            </div>
            <?php endif; ?>

            <?php if ($supplierPageMode === 'details'): ?>
            <div class="supplier-management-grid supplier-results-grid">
                <section class="admin-data-card">
                    <div class="admin-data-table-wrap">
                        <table class="admin-data-table suppliers-table supplier-details-table">
                            <thead>
                                <tr>
                                    <th class="supplier-company-heading"><?= htmlspecialchars(t('Company'), ENT_QUOTES, 'UTF-8') ?></th>
                                    <th><?= htmlspecialchars(t('Contact Person'), ENT_QUOTES, 'UTF-8') ?></th>
                                    <th><?= htmlspecialchars(t('Email'), ENT_QUOTES, 'UTF-8') ?></th>
                                    <th><?= htmlspecialchars(t('Phone'), ENT_QUOTES, 'UTF-8') ?></th>
                                    <th><?= htmlspecialchars(t('Address'), ENT_QUOTES, 'UTF-8') ?></th>
                                    <th><?= htmlspecialchars(t('Products'), ENT_QUOTES, 'UTF-8') ?></th>
                                    <th><?= htmlspecialchars(t('Product Validity Period'), ENT_QUOTES, 'UTF-8') ?></th>
                                    <th><?= htmlspecialchars(t('Status'), ENT_QUOTES, 'UTF-8') ?></th>
                                    <th class="supplier-action-heading"><?= htmlspecialchars(t('Action'), ENT_QUOTES, 'UTF-8') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                
                                <?php if ($suppliers === []): ?>
                                <tr class="supplier-empty-card">
                                    <td colspan="9" class="admin-empty-row">
                                        No registered suppliers available.
                                    </td>
                                </tr>
                                <?php else: 
                                foreach ($suppliers as $supplier): ?>
                                <?php $allocations = $supplierAllocations[(int)$supplier['id']] ?? []; ?>
                                <?php if ($allocations === []) $allocations = [null]; ?>
                                <?php foreach ($allocations as $allocation): ?>
                                <tr class="<?= $supplier['status'] === 'inactive' ? 'supplier-row-inactive' : '' ?>">
                                    <td class="supplier-company-cell" data-label="<?= htmlspecialchars(t('Company'), ENT_QUOTES, 'UTF-8') ?>">
                                        <strong>
                                            <?= htmlspecialchars($supplier['company_name'], ENT_QUOTES, 'UTF-8') ?>
                                        </strong>
                                    </td>
                                    <td data-label="<?= htmlspecialchars(t('Contact Person'), ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars((string) ($supplier['contact_person'] ?: '—'), ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td data-label="<?= htmlspecialchars(t('Email'), ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars((string) ($supplier['email'] ?: '—'), ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td data-label="<?= htmlspecialchars(t('Phone'), ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars((string) ($supplier['phone'] ?: '—'), ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td data-label="<?= htmlspecialchars(t('Address'), ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars((string) ($supplier['address'] ?: '—'), ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td class="supplier-products-cell" data-label="<?= htmlspecialchars(t('Products'), ENT_QUOTES, 'UTF-8') ?>">
                                        <?php if ($allocation === null): ?>—<?php else: ?>
                                            <div class="supplier-product-entry">
                                                <div class="supplier-product-name-row">
                                                    <strong><?= htmlspecialchars(widmsAidItemName((string)$allocation['item_name']) . ($allocation['variety'] ? ' — ' . $allocation['variety'] : ''), ENT_QUOTES, 'UTF-8') ?></strong>
                                                </div>
                                                <?php if ($allocation['status'] === 'inactive'): ?>
                                                <small class="supplier-product-reason"><?= htmlspecialchars(t('Reason:'), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars((string)($allocation['deactivation_reason'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></small>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="supplier-period-cell" data-label="<?= htmlspecialchars(t('Product Validity Period'), ENT_QUOTES, 'UTF-8') ?>">
                                        <?php if ($allocation === null): ?>—<?php else: ?>
                                            <div class="supplier-product-period">
                                                <?php if (!empty($allocation['valid_from']) && !empty($allocation['valid_until'])): ?>
                                                    <strong><?= htmlspecialchars(date('d M Y', strtotime((string)$allocation['valid_from'])), ENT_QUOTES, 'UTF-8') ?></strong>
                                                    <small><?= htmlspecialchars(t('Due:'), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars(date('d M Y', strtotime((string)$allocation['valid_until'])), ENT_QUOTES, 'UTF-8') ?></small>
                                                <?php else: ?><span><?= htmlspecialchars(t('Not configured'), ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <?php $productStatus = $allocation !== null && $supplier['status'] === 'active' && $allocation['status'] === 'active' ? 'active' : 'inactive'; ?>
                                    <td data-label="<?= htmlspecialchars(t('Product Status'), ENT_QUOTES, 'UTF-8') ?>">
                                        <span class="supplier-validity-status supplier-validity-<?= $productStatus ?>">
                                            <?= htmlspecialchars(t($productStatus === 'active' ? 'Active' : 'Deactivated'), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td class="supplier-action-cell" data-label="<?= htmlspecialchars(t('Action'), ENT_QUOTES, 'UTF-8') ?>">
                                        <?php if ($allocation !== null && $supplier['status'] === 'active' && $allocation['status'] === 'active'): ?>
                                            <div class="supplier-row-actions">
                                                <form method="post" class="supplier-status-form">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="action" value="deactivate-product">
                                                    <input type="hidden" name="supplier_id" value="<?= (int)$supplier['id'] ?>">
                                                    <input type="hidden" name="allocation_id" value="<?= (int)$allocation['id'] ?>">
                                                    <input type="hidden" name="item_id" value="<?= (int)$allocation['item_id'] ?>">
                                                <input type="hidden" name="product_deactivation_reason" value="">
                                                <button class="supplier-deactivate-action" type="button"
                                                    data-reason-trigger data-submit-name="confirmed" data-submit-value="1"
                                                    data-dialog-title="<?= htmlspecialchars(t('Deactivate Product'), ENT_QUOTES, 'UTF-8') ?>"
                                                    data-dialog-confirm="<?= htmlspecialchars(t('Confirm deactivation'), ENT_QUOTES, 'UTF-8') ?>"
                                                    data-reason-label="<?= htmlspecialchars(t('Deactivation reason'), ENT_QUOTES, 'UTF-8') ?>"
                                                    data-reason-required="<?= htmlspecialchars(t('A product deactivation reason is required.'), ENT_QUOTES, 'UTF-8') ?>"
                                                    data-cancel-label="<?= htmlspecialchars(t('Cancel'), ENT_QUOTES, 'UTF-8') ?>"
                                                    data-reason-field="product_deactivation_reason"><?= htmlspecialchars(t('Deactivate'), ENT_QUOTES, 'UTF-8') ?></button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <span class="supplier-final-status"><?= htmlspecialchars(t('Permanently deactivated'), ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endforeach; endif; ?>
                                
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
            <?php endif; ?>

            <?php if ($supplierPageMode === 'balances'): ?>
            <div class="supplier-management-grid supplier-results-grid">
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
            <?php endif; ?>
        </main>
        </div>
        <script src="assets/js/admin-dashboard.js"></script>
        <?php if ($supplierPageMode === 'configuration'): ?>
        <script src="assets/js/supplier-company-picker.js?v=1"></script>
        <?php endif; ?>
        <?php if ($supplierPageMode === 'details'): ?>
        <script src="assets/js/admin-reason-dialog.js?v=2"></script>
        <?php endif; ?>
    </body>
</html>
