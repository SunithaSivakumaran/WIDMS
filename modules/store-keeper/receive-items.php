<?php
declare(strict_types=1);

requireRole('store-keeper');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/activity.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/optical-stock.php';
require_once __DIR__ . '/../../includes/spectacle-camps.php';
require_once __DIR__ . '/../../includes/spectacle-categories.php';

$activePage = $requestedPage === 'receive-items' ? 'receive-items' : 'dashboard';
$errors = [];
$success = (string) ($_SESSION['flash_success'] ?? '');
unset($_SESSION['flash_success']);
$suppliers = [];
$inventoryItems = [];
$spectacleCategories = [];
$conductedCamps = [];
$receipts = [];
$requestedCampId = filter_input(INPUT_GET, 'camp_id', FILTER_VALIDATE_INT) ?: 0;
$values = [
    'stock_destination' => $requestedCampId ? 'vision-camp' : 'central', 'camp_id' => $requestedCampId ? (string)$requestedCampId : '',
    'supplier_id' => '', 'item_id' => '', 'quantity' => '', 'unit_cost' => '', 'bulk_total_cost' => '',
    'bill_number' => '', 'received_date' => scToday(), 'payment_status' => 'unpaid', 'check_number' => '', 'paid_amount' => '',
];

try {
    $suppliers = database()->query("SELECT s.id, s.company_name, COUNT(sai.item_id) AS authorized_item_count FROM suppliers s JOIN supplier_authorized_items sai ON sai.supplier_id = s.id WHERE s.status = 'active' AND sai.status = 'active' AND (sai.valid_from IS NULL OR (sai.valid_from<=CURDATE() AND (sai.validity_period IS NULL OR sai.validity_unit IS NULL OR (sai.validity_unit='months' AND CURDATE()<DATE_ADD(sai.valid_from,INTERVAL sai.validity_period MONTH)) OR (sai.validity_unit='years' AND CURDATE()<DATE_ADD(sai.valid_from,INTERVAL sai.validity_period YEAR))))) GROUP BY s.id, s.company_name ORDER BY s.company_name")->fetchAll();
    $inventoryItems = database()->query("SELECT i.id, i.item_name, i.variety, i.quantity, GROUP_CONCAT(CASE WHEN s.status='active' AND sai.status='active' AND (sai.valid_from IS NULL OR (sai.valid_from<=CURDATE() AND (sai.validity_period IS NULL OR sai.validity_unit IS NULL OR (sai.validity_unit='months' AND CURDATE()<DATE_ADD(sai.valid_from,INTERVAL sai.validity_period MONTH)) OR (sai.validity_unit='years' AND CURDATE()<DATE_ADD(sai.valid_from,INTERVAL sai.validity_period YEAR))))) THEN sai.supplier_id END) supplier_ids FROM inventory_items i LEFT JOIN supplier_authorized_items sai ON sai.item_id=i.id LEFT JOIN suppliers s ON s.id=sai.supplier_id GROUP BY i.id ORDER BY i.item_name, i.variety")->fetchAll();
    $spectacleCategories = widmsSpectacleCategories(database(), false);
    $conductedCamps = database()->query("SELECT c.id,c.item_id,c.ds_division_id,c.camp_date,c.completed_at,ds.name division_name,d.name district_name,u.full_name requester_name,i.item_name
        FROM spectacle_camps c JOIN ds_divisions ds ON ds.id=c.ds_division_id JOIN districts d ON d.id=c.district_id
        JOIN users u ON u.id=c.requested_by JOIN inventory_items i ON i.id=c.item_id
        WHERE c.status='completed' AND c.conducted_at IS NOT NULL ORDER BY c.camp_date DESC,c.id DESC")->fetchAll();
    $conductedCamps = array_values(array_filter(array_map(static function(array $campOption): array {
        $campOption['needed_types'] = scCampReceiptNeed(database(), (int)$campOption['id']);
        return $campOption;
    }, $conductedCamps), static fn(array $campOption): bool => $campOption['needed_types'] !== []));
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $errors[] = 'Stock receiving is not installed. Import database/migration_stock_receiving.sql.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errors === []) {
    foreach (array_keys($values) as $field) {
        $values[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    $supplierId = filter_var($values['supplier_id'], FILTER_VALIDATE_INT);
    $destination = in_array($values['stock_destination'], ['central','vision-camp'], true) ? $values['stock_destination'] : '';
    $campId = filter_var($values['camp_id'], FILTER_VALIDATE_INT);
    $selectedCamp = null;
    foreach ($conductedCamps as $campOption) if ((int)$campOption['id'] === (int)$campId) $selectedCamp = $campOption;
    $itemId = $destination === 'vision-camp' && $selectedCamp ? (int)$selectedCamp['item_id'] : filter_var($values['item_id'], FILTER_VALIDATE_INT);
    $selectedItem = null;
    foreach ($inventoryItems as $itemOption) {
        if ((int) $itemOption['id'] === (int) $itemId) {
            $selectedItem = $itemOption;
            break;
        }
    }
    $isSpectacles = $selectedItem && widmsIsSpectacleItem((string) $selectedItem['item_name']);
    $spectacleLines = [];
    $spectacleLineInput = is_array($_POST['spectacle_lines'] ?? null) ? $_POST['spectacle_lines'] : [];
    if ($isSpectacles) {
        foreach ($spectacleCategories as $category) {
            $categoryId = $category['id'];
            $line = is_array($spectacleLineInput[$categoryId] ?? null) ? $spectacleLineInput[$categoryId] : [];
            if (($line['selected'] ?? '') !== '1') continue;
            if ($destination === 'central' && $category['status'] !== 'active') {
                $errors[] = 'Select an active spectacle type for Central Stock.';
                continue;
            }
            $rawQuantity = trim((string) ($line['quantity'] ?? ''));
            $rawUnitCost = trim((string) ($line['unit_cost'] ?? ''));
            $lineQuantity = filter_var($rawQuantity, FILTER_VALIDATE_INT);
            $lineUnitCost = filter_var($rawUnitCost, FILTER_VALIDATE_FLOAT);
            if ($lineQuantity === false || $lineQuantity < 1 || $lineUnitCost === false || $lineUnitCost < 0) {
                $errors[] = 'Enter a valid quantity and unit price for each selected spectacle type.';
                continue;
            }
            $spectacleLines[] = [
                'category_id' => $categoryId,
                'quantity' => (int) $lineQuantity,
                'unit_cost' => round((float) $lineUnitCost, 2),
            ];
        }
    }
    $quantity = filter_var($values['quantity'], FILTER_VALIDATE_INT);
    if ($isSpectacles) {
        $quantity = array_sum(array_column($spectacleLines, 'quantity'));
    }
    $unitCost = $values['unit_cost'] === '' ? false : filter_var($values['unit_cost'], FILTER_VALIDATE_FLOAT);
    $bulkTotalCost = $values['bulk_total_cost'] === '' ? false : filter_var($values['bulk_total_cost'], FILTER_VALIDATE_FLOAT);
    $paymentStatus = $values['payment_status'];
    $checkNumber = trim((string) ($_POST['check_number'] ?? ''));
    $paidAmount = $values['paid_amount'] === '' ? 0.0 : filter_var($values['paid_amount'], FILTER_VALIDATE_FLOAT);

    if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) $errors[] = 'Your session expired. Refresh the page and try again.';
    if ($destination === '') $errors[] = 'Select where this stock will be stored.';
    if ($destination === 'vision-camp' && (!$campId || !$selectedCamp)) $errors[] = 'Select a completed Vision Camp with spectacles awaiting receipt.';
    if ($destination === 'vision-camp' && $selectedCamp && $isSpectacles) {
        $postedQuantities=[];
        foreach ($spectacleLines as $line) $postedQuantities[(int)$line['category_id']] = (int)$line['quantity'];
        ksort($postedQuantities,SORT_NUMERIC);
        if ($postedQuantities !== $selectedCamp['needed_types']) $errors[] = 'Spectacle quantities must match the selected participants still awaiting stock.';
    }
    if (!$supplierId) $errors[] = 'Select a supplier.';
    if (!$itemId) $errors[] = 'Select an item.';
    if ($isSpectacles && $spectacleLines === []) $errors[] = 'Select at least one spectacle type and enter its quantity and unit price.';
    if (!$quantity || $quantity < 1) $errors[] = 'Quantity must be at least 1.';
    if ($isSpectacles) {
        // Each category may have a different unit price.
    } elseif ($destination === 'vision-camp') {
        if ($bulkTotalCost === false || $bulkTotalCost <= 0) $errors[] = 'Enter the total price for the entire bulk.';
    } elseif ($unitCost === false || $unitCost < 0) $errors[] = 'Enter a valid unit cost.';
    if ($values['bill_number'] === '' || mb_strlen($values['bill_number']) > 100) $errors[] = 'Enter a valid bill or invoice number.';
    $date = DateTimeImmutable::createFromFormat('Y-m-d', $values['received_date']);
    if (!$date || $date->format('Y-m-d') !== $values['received_date']) $errors[] = 'Select a valid received date.';
    elseif ($values['received_date'] > scToday()) $errors[] = 'Received date cannot be in the future.';
    elseif ($destination === 'vision-camp' && $selectedCamp && $values['received_date'] < $selectedCamp['camp_date']) $errors[] = 'Camp stock cannot be received before the camp date.';
    elseif ($destination === 'vision-camp' && $selectedCamp && $values['received_date'] < substr((string)$selectedCamp['completed_at'],0,10)) $errors[] = 'Camp stock cannot be received before the camp is completed.';
    if (!in_array($paymentStatus, ['fully-paid', 'partially-paid', 'unpaid'], true)) $errors[] = 'Select a valid payment status.';
    if ($paymentStatus !== 'unpaid' && ($checkNumber === '' || mb_strlen($checkNumber) > 100)) $errors[] = 'Enter the check number for this payment.';

    $totalCost = $isSpectacles
        ? round(array_sum(array_map(
            static fn(array $line): float => $line['quantity'] * $line['unit_cost'],
            $spectacleLines
        )), 2)
        : ($destination === 'vision-camp' ? round((float)$bulkTotalCost, 2) : round((float) $quantity * (float) $unitCost, 2));
    if (($isSpectacles || $destination === 'vision-camp') && $quantity) $unitCost = round($totalCost / (int)$quantity, 2);
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

            $campRow = null;
            if ($destination === 'vision-camp') {
                $campCheck = $connection->prepare("SELECT c.*,ds.name division_name FROM spectacle_camps c JOIN ds_divisions ds ON ds.id=c.ds_division_id WHERE c.id=? AND c.status='completed' AND c.conducted_at IS NOT NULL FOR UPDATE");
                $campCheck->execute([$campId]);
                $campRow = $campCheck->fetch(PDO::FETCH_ASSOC);
                if (!$campRow) throw new RuntimeException('Select a completed Vision Camp.');
                if ($values['received_date'] < substr((string)$campRow['completed_at'],0,10)) throw new RuntimeException('Camp stock cannot be received before the camp is completed.');
                $lockedNeed=scCampReceiptNeed($connection,(int)$campRow['id']);
                $submittedNeed=[];
                foreach ($spectacleLines as $line) $submittedNeed[(int)$line['category_id']]=(int)$line['quantity'];
                ksort($submittedNeed,SORT_NUMERIC);
                if ($lockedNeed===[] || $submittedNeed!==$lockedNeed) throw new RuntimeException('Camp spectacle counts changed. Refresh the page before receiving stock.');
                $itemId = (int)$campRow['item_id'];
            }

            $supplierCheck = $connection->prepare("SELECT s.id FROM suppliers s JOIN supplier_authorized_items sai ON sai.supplier_id=s.id AND sai.item_id=:item_id WHERE s.id=:supplier_id AND s.status='active' AND sai.status='active' AND (sai.valid_from IS NULL OR (sai.valid_from<=CURDATE() AND (sai.validity_period IS NULL OR sai.validity_unit IS NULL OR (sai.validity_unit='months' AND CURDATE()<DATE_ADD(sai.valid_from,INTERVAL sai.validity_period MONTH)) OR (sai.validity_unit='years' AND CURDATE()<DATE_ADD(sai.valid_from,INTERVAL sai.validity_period YEAR)))))");
            $supplierCheck->execute(['supplier_id' => $supplierId, 'item_id' => $itemId]);
            $itemCheck = $connection->prepare('SELECT id, item_name FROM inventory_items WHERE id = :id FOR UPDATE');
            $itemCheck->execute(['id' => $itemId]);
            $itemRow = $itemCheck->fetch();
            if (!$supplierCheck->fetch() || !$itemRow) throw new RuntimeException('The supplier is inactive or is not authorized for the selected item.');

            $receipt = $connection->prepare(
                'INSERT INTO stock_receipts
                 (supplier_id, item_id, stock_destination, vision_camp_id, ds_division_id, quantity, unit_cost, total_cost, bill_number, received_date, payment_status, check_number, paid_amount, balance_amount, received_by)
                 VALUES (:supplier_id, :item_id, :stock_destination, :vision_camp_id, :ds_division_id, :quantity, :unit_cost, :total_cost, :bill_number, :received_date, :payment_status, :check_number, :paid_amount, :balance_amount, :received_by)'
            );
            $receipt->execute([
                'supplier_id' => $supplierId, 'item_id' => $itemId, 'quantity' => $quantity,
                'stock_destination' => $destination, 'vision_camp_id' => $campRow ? (int)$campRow['id'] : null,
                'ds_division_id' => $campRow ? (int)$campRow['ds_division_id'] : null,
                'unit_cost' => number_format((float) $unitCost, 2, '.', ''),
                'total_cost' => number_format($totalCost, 2, '.', ''),
                'bill_number' => $values['bill_number'], 'received_date' => $values['received_date'],
                'payment_status' => $paymentStatus, 'check_number' => $paymentStatus === 'unpaid' ? null : $checkNumber,
                'paid_amount' => number_format((float) $paidAmount, 2, '.', ''),
                'balance_amount' => number_format($balance, 2, '.', ''), 'received_by' => $_SESSION['user_id'],
            ]);
            $receiptId = (int) $connection->lastInsertId();
            if ($isSpectacles) {
                $addLine = $connection->prepare(
                    'INSERT INTO stock_receipt_spectacle_lines
                     (stock_receipt_id, spectacle_category_id, quantity, unit_cost, total_cost)
                     VALUES (?, ?, ?, ?, ?)'
                );
                foreach ($spectacleLines as $line) {
                    $addLine->execute([
                        $receiptId,
                        $line['category_id'],
                        $line['quantity'],
                        number_format($line['unit_cost'], 2, '.', ''),
                        number_format($line['quantity'] * $line['unit_cost'], 2, '.', ''),
                    ]);
                }
            }
            if ($campRow) {
                $campReceipt = $connection->prepare("INSERT INTO spectacle_camp_receipts (stock_receipt_id,camp_id,quantity,reference_code,received_date,received_by,remarks) VALUES (?,?,?,?,?,?,'Supplier delivery')");
                $campReceipt->execute([$receiptId,(int)$campRow['id'],$quantity,'BAT-'.str_pad((string)$receiptId,4,'0',STR_PAD_LEFT),$values['received_date'],(int)$_SESSION['user_id']]);
                $eventToken = trim((string)($_POST['request_token'] ?? ''));
                if (!preg_match('/^[a-f0-9]{32}$/D',$eventToken)) throw new RuntimeException('Invalid submission token. Refresh the page.');
                scQuery($connection,'INSERT INTO spectacle_camp_events (camp_id,actor_id,action,details,request_token) VALUES (?,?,?,?,?)',[(int)$campRow['id'],(int)$_SESSION['user_id'],'receive-supplier-stock','Received '.$quantity.' spectacles under BAT-'.str_pad((string)$receiptId,4,'0',STR_PAD_LEFT),$eventToken]);
                $conductorId=(int)scQuery($connection,"SELECT actor_id FROM spectacle_camp_events WHERE camp_id=? AND action='conduct-camp' ORDER BY id LIMIT 1",[(int)$campRow['id']])->fetchColumn();
                if ($conductorId<1) throw new RuntimeException('The conducting Subject Officer could not be identified.');
                scNotify($connection,$campRow,'received-'.$receiptId,'Supplier stock for your camp is now in the camp store. Submit a stock request for Admin approval.',null,$conductorId);
            } else {
                $stock = $connection->prepare('UPDATE inventory_items i LEFT JOIN item_categories c ON c.name=i.category AND c.status=\'active\' SET i.quantity=i.quantity+:quantity,i.category_id=COALESCE(i.category_id,c.id) WHERE i.id=:id');
                $stock->execute(['quantity' => $quantity, 'id' => $itemId]);
            }
            $connection->commit();
            logActivity('Inventory', sprintf('Received %d item%s into %s', $quantity, $quantity === 1 ? '' : 's', $campRow ? 'Vision Camp stock' : 'Central Stock'), 'BAT-' . str_pad((string)$receiptId,4,'0',STR_PAD_LEFT), 'done');

            $_SESSION['flash_success'] = $campRow
                ? sprintf('Receipt BAT-%04d recorded. %d spectacles added to VC-%d for %s.', $receiptId, $quantity, (int)$campRow['id'], $campRow['division_name'])
                : sprintf('Receipt BAT-%04d recorded. %d item%s added to Central Stock.', $receiptId, $quantity, $quantity === 1 ? '' : 's');
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
<html lang="<?= htmlspecialchars(widmsLanguage(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(t('Receive Aid'), ENT_QUOTES, 'UTF-8') ?> | SWPCS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/admin-dashboard.css') ?>" rel="stylesheet">
    <link href="assets/css/vision-camp-receiving.css?v=1" rel="stylesheet">
</head>
<body class="store-page store-receive-page">
<?php require __DIR__ . '/../../includes/store-keeper-sidebar.php'; ?>
<div class="admin-shell">
    <header class="topbar"><div class="d-flex align-items-center gap-3"><button type="button" class="menu-button" id="menu-button" aria-label="<?= htmlspecialchars(t('Open navigation'), ENT_QUOTES, 'UTF-8') ?>">☰</button><h1><?= htmlspecialchars(t('Receive Aid'), ENT_QUOTES, 'UTF-8') ?></h1></div><div class="topbar-actions"><button class="notification-button" type="button" aria-label="<?= htmlspecialchars(t('Notifications'), ENT_QUOTES, 'UTF-8') ?>">●</button></div></header>
    <main class="dashboard-content receive-page">
        <?php if ($success !== ''): ?><div class="alert alert-success" role="status"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if ($errors !== []): ?><div class="alert alert-danger" role="alert"><ul class="mb-0 ps-3"><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></div><?php endif; ?>

        <section class="receive-card receive-entry-card">
            <div class="receive-card-header receive-entry-header"><div class="receive-entry-title"><span class="receive-entry-symbol" aria-hidden="true">＋</span><div><h2><?= htmlspecialchars(t('Record Received Aid'),ENT_QUOTES,'UTF-8') ?></h2><p><?= htmlspecialchars(t('Record one supplier delivery for Central Stock or a completed Vision Camp.'),ENT_QUOTES,'UTF-8') ?></p></div></div><a class="outline-action" href="dashboard.php?page=receipt-history"><?= htmlspecialchars(t('Receipt & Payment History'),ENT_QUOTES,'UTF-8') ?></a></div>
            <form method="post" action="dashboard.php?page=receive-items" id="receipt-form" class="receive-form" data-total-spectacles="<?= htmlspecialchars(t('Total Number of Spectacles'),ENT_QUOTES,'UTF-8') ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="request_token" value="<?= bin2hex(random_bytes(16)) ?>">
                <div class="receive-form-grid">
                    <label><?= htmlspecialchars(t('Stock Destination'),ENT_QUOTES,'UTF-8') ?><select name="stock_destination" id="stock_destination" required><option value="central" <?= $values['stock_destination']==='central'?'selected':'' ?>><?= htmlspecialchars(t('Central Stock'),ENT_QUOTES,'UTF-8') ?></option><option value="vision-camp" <?= $values['stock_destination']==='vision-camp'?'selected':'' ?>><?= htmlspecialchars(t('Vision Camp Stock'),ENT_QUOTES,'UTF-8') ?></option></select><small><?= htmlspecialchars(t('Camp stock remains separate from Central Stock.'),ENT_QUOTES,'UTF-8') ?></small></label>
                    <label id="camp-field" hidden><?= htmlspecialchars(t('Completed Vision Camp'),ENT_QUOTES,'UTF-8') ?><select name="camp_id" id="camp_id"><option value=""><?= htmlspecialchars(t('Select completed Vision Camp'),ENT_QUOTES,'UTF-8') ?></option><?php foreach($conductedCamps as $campOption): ?><option value="<?= (int)$campOption['id'] ?>" data-item-id="<?= (int)$campOption['item_id'] ?>" data-needs="<?= htmlspecialchars(json_encode($campOption['needed_types'],JSON_THROW_ON_ERROR),ENT_QUOTES,'UTF-8') ?>" data-completed-date="<?= htmlspecialchars(substr((string)$campOption['completed_at'],0,10),ENT_QUOTES,'UTF-8') ?>" data-division="<?= htmlspecialchars($campOption['district_name'].' / '.$campOption['division_name'],ENT_QUOTES,'UTF-8') ?>" <?= (string)$campOption['id']===$values['camp_id']?'selected':'' ?>>VC-<?= (int)$campOption['id'] ?> — <?= htmlspecialchars($campOption['division_name'].' — '.$campOption['camp_date'].' — '.$campOption['requester_name'],ENT_QUOTES,'UTF-8') ?></option><?php endforeach; ?></select><small><?= htmlspecialchars(t('Only completed camps with selected participants awaiting spectacles are listed.'),ENT_QUOTES,'UTF-8') ?></small></label>
                    <label id="camp-division-field" hidden><?= htmlspecialchars(t('District / DS Division'),ENT_QUOTES,'UTF-8') ?><input id="camp_division" type="text" readonly value=""><small><?= htmlspecialchars(t('Stock is assigned to this camp division.'),ENT_QUOTES,'UTF-8') ?></small></label>
                    <label><?= htmlspecialchars(t('Supplier Company'), ENT_QUOTES, 'UTF-8') ?><select name="supplier_id" id="supplier_id" required><option value=""><?= htmlspecialchars(t('Select supplier first'), ENT_QUOTES, 'UTF-8') ?></option><?php foreach ($suppliers as $supplier): ?><?php $hasAuthorizedItems = (int) $supplier['authorized_item_count'] > 0; ?><option value="<?= (int) $supplier['id'] ?>" <?= !$hasAuthorizedItems ? 'disabled' : '' ?> <?= (string) $supplier['id'] === $values['supplier_id'] ? 'selected' : '' ?>><?= htmlspecialchars($supplier['company_name'] . ($hasAuthorizedItems ? '' : ' — ' . t('no allocated items')), ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select><small><?= htmlspecialchars(t('Suppliers without allocated items must be configured in Supplier Configuration.'), ENT_QUOTES, 'UTF-8') ?></small></label>
                    <label><?= htmlspecialchars(t('Item'), ENT_QUOTES, 'UTF-8') ?><select name="item_id" id="item_id" required disabled><option value=""><?= htmlspecialchars(t('Select a supplier first'), ENT_QUOTES, 'UTF-8') ?></option><?php foreach ($inventoryItems as $item): ?><option value="<?= (int) $item['id'] ?>" data-suppliers=",<?= htmlspecialchars((string)$item['supplier_ids'], ENT_QUOTES, 'UTF-8') ?>," data-optical="<?= widmsIsOpticalItem((string)$item['item_name']) ? 'lens' : (widmsIsSpectacleItem((string)$item['item_name']) ? 'spectacles' : '') ?>" <?= (string) $item['id'] === $values['item_id'] ? 'selected' : '' ?>><?= htmlspecialchars(widmsAidItemName((string)$item['item_name']) . ($item['variety'] !== '' ? ' — ' . $item['variety'] : '') . ' (' . t('stock') . ': ' . $item['quantity'] . ')', ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select><small id="item-help"><?= htmlspecialchars(t('Choose a supplier to load its authorized items.'), ENT_QUOTES, 'UTF-8') ?></small></label>
                    <label><span id="quantity-label"><?= htmlspecialchars(t('Quantity'), ENT_QUOTES, 'UTF-8') ?></span><input type="number" min="1" name="quantity" id="quantity" value="<?= htmlspecialchars($values['quantity'], ENT_QUOTES, 'UTF-8') ?>" required><small id="quantity-help"><?= htmlspecialchars(t('Number of items received.'), ENT_QUOTES, 'UTF-8') ?></small></label>
                    <fieldset id="spectacle-lines-field" class="spectacle-receipt-lines" hidden>
                        <legend><?= htmlspecialchars(t('Spectacle Types'), ENT_QUOTES, 'UTF-8') ?></legend>
                        <p><?= htmlspecialchars(t('For a completed Vision Camp, the selected types and quantities are fixed by participant decisions. Enter each type\'s unit price.'), ENT_QUOTES, 'UTF-8') ?></p>
                        <div class="spectacle-receipt-head"><span><?= htmlspecialchars(t('Type'), ENT_QUOTES, 'UTF-8') ?></span><span><?= htmlspecialchars(t('Quantity'), ENT_QUOTES, 'UTF-8') ?></span><span><?= htmlspecialchars(t('Unit Price (Rs)'), ENT_QUOTES, 'UTF-8') ?></span><span><?= htmlspecialchars(t('Line Total (Rs)'), ENT_QUOTES, 'UTF-8') ?></span></div>
                        <?php foreach ($spectacleCategories as $category): $postedLine = is_array($_POST['spectacle_lines'][$category['id']] ?? null) ? $_POST['spectacle_lines'][$category['id']] : []; ?>
                            <div class="spectacle-receipt-line">
                                <label class="spectacle-receipt-type"><input type="checkbox" name="spectacle_lines[<?= $category['id'] ?>][selected]" value="1" data-category-id="<?= $category['id'] ?>" data-category-status="<?= htmlspecialchars($category['status'], ENT_QUOTES, 'UTF-8') ?>" <?= ($postedLine['selected'] ?? '') === '1' ? 'checked' : '' ?>><input type="hidden" name="spectacle_lines[<?= $category['id'] ?>][selected]" value="1" data-camp-selected disabled><strong><?= htmlspecialchars(t($category['name']), ENT_QUOTES, 'UTF-8') ?></strong></label>
                                <input type="number" min="1" step="1" name="spectacle_lines[<?= $category['id'] ?>][quantity]" value="<?= htmlspecialchars((string) ($postedLine['quantity'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($category['name'] . ' ' . t('Quantity'), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="number" min="0" step="0.01" name="spectacle_lines[<?= $category['id'] ?>][unit_cost]" value="<?= htmlspecialchars((string) ($postedLine['unit_cost'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($category['name'] . ' ' . t('Unit Price (Rs)'), ENT_QUOTES, 'UTF-8') ?>">
                                <output>0.00</output>
                            </div>
                        <?php endforeach; ?>
                        <div class="spectacle-receipt-total"><strong><?= htmlspecialchars(t('Total spectacle price'), ENT_QUOTES, 'UTF-8') ?></strong><output id="spectacle-receipt-total">Rs 0.00</output></div>
                    </fieldset>
                    <label id="unit-cost-field"><?= htmlspecialchars(t('Unit Cost (Rs)'), ENT_QUOTES, 'UTF-8') ?><input type="number" min="0" step="0.01" name="unit_cost" id="unit_cost" value="<?= htmlspecialchars($values['unit_cost'], ENT_QUOTES, 'UTF-8') ?>" required></label>
                    <label id="bulk-cost-field" hidden><?= htmlspecialchars(t('Total Bulk Price (Rs)'),ENT_QUOTES,'UTF-8') ?><input type="number" min="0.01" step="0.01" name="bulk_total_cost" id="bulk_total_cost" value="<?= htmlspecialchars($values['bulk_total_cost'],ENT_QUOTES,'UTF-8') ?>"><small><?= htmlspecialchars(t('Enter the price for the entire delivered bulk.'),ENT_QUOTES,'UTF-8') ?></small></label>
                    <label id="total-cost-field"><?= htmlspecialchars(t('Total Cost (Rs)'), ENT_QUOTES, 'UTF-8') ?><input type="text" id="total_cost" value="0.00" readonly></label>
                    <label><?= htmlspecialchars(t('Bill / Invoice Number'), ENT_QUOTES, 'UTF-8') ?><input type="text" maxlength="100" name="bill_number" placeholder="<?= htmlspecialchars(t('e.g. BILL-2026-0041'), ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($values['bill_number'], ENT_QUOTES, 'UTF-8') ?>" required></label>
                    <label><?= htmlspecialchars(t('Date Received'), ENT_QUOTES, 'UTF-8') ?><input type="date" name="received_date" id="received_date" value="<?= htmlspecialchars($values['received_date'], ENT_QUOTES, 'UTF-8') ?>" required></label>
                    <label><?= htmlspecialchars(t('Payment Status'), ENT_QUOTES, 'UTF-8') ?><select name="payment_status" id="payment_status" required><option value="fully-paid" <?= $values['payment_status'] === 'fully-paid' ? 'selected' : '' ?>><?= htmlspecialchars(t('Fully Paid'), ENT_QUOTES, 'UTF-8') ?></option><option value="partially-paid" <?= $values['payment_status'] === 'partially-paid' ? 'selected' : '' ?>><?= htmlspecialchars(t('Partially Paid'), ENT_QUOTES, 'UTF-8') ?></option><option value="unpaid" <?= $values['payment_status'] === 'unpaid' ? 'selected' : '' ?>><?= htmlspecialchars(t('Not Yet Paid'), ENT_QUOTES, 'UTF-8') ?></option></select></label>
                    <label id="paid-amount-field"><?= htmlspecialchars(t('Amount Paid (Rs)'), ENT_QUOTES, 'UTF-8') ?><input type="number" min="0.01" step="0.01" name="paid_amount" id="paid_amount" value="<?= htmlspecialchars($values['paid_amount'], ENT_QUOTES, 'UTF-8') ?>"><small><?= htmlspecialchars(t('Enter the amount already paid to the supplier.'), ENT_QUOTES, 'UTF-8') ?></small></label>
                    <label><?= htmlspecialchars(t('Balance Due (Rs)'), ENT_QUOTES, 'UTF-8') ?><input type="text" id="balance_amount" value="0.00" readonly></label>
                </div>
                <div class="receive-form-actions"><button type="submit" class="record-receipt-button"><?= htmlspecialchars(t('Record'), ENT_QUOTES, 'UTF-8') ?></button></div>
            </form>
        </section>

    </main>
</div>
<script src="assets/js/admin-dashboard.js"></script><script src="assets/js/receive-items.js?v=<?= filemtime(__DIR__ . '/../../public/assets/js/receive-items.js') ?>"></script>
</body></html>
