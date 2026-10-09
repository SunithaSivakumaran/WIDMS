<?php

declare(strict_types=1);


/* =========================================================
   ACCESS CONTROL
   Admin + Subject Officer
   ========================================================= */

$allowedRoles = [
    'admin',
    'subject-officer'
];

$currentRole = (string) ($_SESSION['role'] ?? '');

if (!in_array($currentRole, $allowedRoles, true)) {
    http_response_code(403);
    exit('You do not have permission to access this page.');
}


/* =========================================================
   REQUIRED FILES
   ========================================================= */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/activity.php';


/* =========================================================
   PAGE SETTINGS
   ========================================================= */

$activePage = 'officer-pools';

$errors = [];

$success = (string) ($_SESSION['flash_success'] ?? '');

unset($_SESSION['flash_success']);


/* =========================================================
   HANDLE FORM SUBMISSIONS
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = (string) ($_POST['action'] ?? '');


    /* -----------------------------------------------------
       CSRF VALIDATION
       ----------------------------------------------------- */

    if (
        !verifyCsrfToken(
            (string) ($_POST['csrf_token'] ?? '')
        )
    ) {
        $errors[] = 'Your session expired.';
    }


    /* -----------------------------------------------------
       PROCESS ACTION
       ----------------------------------------------------- */

    if (!$errors) {

        try {

            /* =================================================
               ASSIGN OFFICER TO DS DIVISION
               ================================================= */

            if ($action === 'assign-division') {

                $officer = filter_input(
                    INPUT_POST,
                    'officer_id',
                    FILTER_VALIDATE_INT
                );

                $dsDivision = filter_input(
                    INPUT_POST,
                    'ds_division_id',
                    FILTER_VALIDATE_INT
                );


                if (!$officer || !$dsDivision) {

                    throw new RuntimeException(
                        'Select an officer and DS Division.'
                    );
                }


                /* Get district belonging to selected DS Division */

                $geo = database()->prepare(
                    "
                    SELECT district_id

                    FROM ds_divisions

                    WHERE
                        id = :id
                        AND status = 'active'
                    "
                );

                $geo->execute([
                    'id' => $dsDivision
                ]);


                $district = $geo->fetchColumn();


                if (!$district) {

                    throw new RuntimeException(
                        'Selected division is unavailable.'
                    );
                }


                /* Update officer */

                $statement = database()->prepare(
                    "
                    UPDATE users

                    SET
                        ds_division_id = :ds,
                        district_id = :district,
                        division = (
                            SELECT name
                            FROM ds_divisions
                            WHERE id = :ds2
                        )

                    WHERE
                        id = :officer
                        AND role = 'social-service-officer'
                    "
                );


                $statement->execute([
                    'ds'       => $dsDivision,
                    'district' => $district,
                    'ds2'      => $dsDivision,
                    'officer'  => $officer
                ]);


                if (!$statement->rowCount()) {

                    throw new RuntimeException(
                        'Officer assignment was not changed.'
                    );
                }


                /* Log activity */

                logActivity(
                    'Officer Pools',
                    'Assigned Social Service Officer to a division',
                    'USR-' . $officer
                );


                $message = 'Officer division assigned.';
            }


            /* =================================================
               ALLOCATE STOCK TO OFFICER POOL
               ================================================= */

            elseif ($action === 'allocate-stock') {

                $officer = filter_input(
                    INPUT_POST,
                    'officer_id',
                    FILTER_VALIDATE_INT
                );

                $item = filter_input(
                    INPUT_POST,
                    'item_id',
                    FILTER_VALIDATE_INT
                );

                $quantity = filter_input(
                    INPUT_POST,
                    'quantity',
                    FILTER_VALIDATE_INT
                );


                if (
                    !$officer ||
                    !$item ||
                    !$quantity ||
                    $quantity < 1
                ) {

                    throw new RuntimeException(
                        'Select an officer, item, and valid quantity.'
                    );
                }


                $db = database();

                $db->beginTransaction();


                /* ---------------------------------------------
                   Find officer's assigned DS Division
                   --------------------------------------------- */

                $officerStatement = $db->prepare(
                    "
                    SELECT ds_division_id

                    FROM users

                    WHERE
                        id = :id
                        AND role = 'social-service-officer'
                        AND status = 'active'

                    FOR UPDATE
                    "
                );


                $officerStatement->execute([
                    'id' => $officer
                ]);


                $dsDivision =
                    $officerStatement->fetchColumn();


                if (!$dsDivision) {

                    throw new RuntimeException(
                        'Officer must be assigned to a DS Division first.'
                    );
                }


                /* ---------------------------------------------
                   Check available division stock
                   --------------------------------------------- */

                $stockStatement = $db->prepare(
                    "
                    SELECT quantity

                    FROM division_inventory

                    WHERE
                        ds_division_id = :ds
                        AND item_id = :item

                    FOR UPDATE
                    "
                );


                $stockStatement->execute([
                    'ds'   => $dsDivision,
                    'item' => $item
                ]);


                $available =
                    (int) $stockStatement->fetchColumn();


                if ($available < $quantity) {

                    throw new RuntimeException(
                        "Insufficient division stock. Available: {$available}."
                    );
                }


                /* ---------------------------------------------
                   Reduce division inventory
                   --------------------------------------------- */

                $reduceStock = $db->prepare(
                    "
                    UPDATE division_inventory

                    SET quantity = quantity - :quantity

                    WHERE
                        ds_division_id = :ds
                        AND item_id = :item
                    "
                );


                $reduceStock->execute([
                    'quantity' => $quantity,
                    'ds'       => $dsDivision,
                    'item'     => $item
                ]);


                /* ---------------------------------------------
                   Add/update officer pool
                   --------------------------------------------- */

                $poolStatement = $db->prepare(
                    "
                    INSERT INTO division_pools
                    (
                        ds_division_id,
                        item_id,
                        allocated
                    )

                    VALUES
                    (
                        :division,
                        :item,
                        :quantity
                    )

                    ON DUPLICATE KEY UPDATE
                        allocated =
                            allocated + VALUES(allocated)
                    "
                );


                $poolStatement->execute([
                    'division' => $dsDivision,
                    'item'     => $item,
                    'quantity' => $quantity
                ]);


                /* ---------------------------------------------
                   Record allocation history
                   --------------------------------------------- */

                $allocationStatement = $db->prepare(
                    "
                    INSERT INTO pool_allocations
                    (
                        officer_id,
                        ds_division_id,
                        item_id,
                        quantity,
                        allocated_by
                    )

                    VALUES
                    (
                        :officer,
                        :division,
                        :item,
                        :quantity,
                        :allocated_by
                    )
                    "
                );


                $allocationStatement->execute([
                    'officer'     => $officer,
                    'division'    => $dsDivision,
                    'item'        => $item,
                    'quantity'    => $quantity,
                    'allocated_by' =>
                        (int) $_SESSION['user_id']
                ]);


                /* ---------------------------------------------
                   Complete transaction
                   --------------------------------------------- */

                $db->commit();


                logActivity(
                    'Officer Pools',
                    "Allocated {$quantity} item(s) to officer pool",
                    'USR-' . $officer
                );


                $message =
                    'Stock allocated to officer pool.';
            }


            /* =================================================
               INVALID ACTION
               ================================================= */

            else {

                throw new RuntimeException(
                    'Invalid pool action.'
                );
            }


            /* =================================================
               SUCCESS MESSAGE
               ================================================= */

            $_SESSION['flash_success'] =
                $message;

            unset($_SESSION['csrf_token']);


            header(
                'Location: dashboard.php?page=officer-pools'
            );

            exit;

        } catch (Throwable $e) {


            /* Roll back transaction if necessary */

            if (
                isset($db) &&
                $db->inTransaction()
            ) {
                $db->rollBack();
            }


            error_log($e->getMessage());


            $errors[] =
                $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'Unable to update officer pool.';
        }
    }
}


/* =========================================================
   LOAD DIVISIONS, STOCK ITEMS AND POOL DATA
   ========================================================= */

try {


    /* -----------------------------------------------------
       DS DIVISIONS
       ----------------------------------------------------- */

    $divisions = database()->query(
        "
        SELECT DISTINCT
            ds.id,
            ds.district_id,

            CONCAT(
                d.name,
                ' — ',
                ds.name
            ) AS name

        FROM ds_divisions ds

        JOIN districts d
            ON d.id = ds.district_id
        JOIN users u
            ON u.ds_division_id = ds.id
           AND u.role = 'social-service-officer'
           AND u.status = 'active'

        ORDER BY name
        "
    )->fetchAll(PDO::FETCH_ASSOC);

    $districts = database()->query(
        "SELECT DISTINCT d.id, d.name
         FROM districts d
         JOIN ds_divisions ds ON ds.district_id = d.id
         JOIN users u ON u.ds_division_id = ds.id
         WHERE u.role = 'social-service-officer' AND u.status = 'active'
         ORDER BY d.name"
    )->fetchAll(PDO::FETCH_ASSOC);

    $stockItems = database()->query(
        "SELECT DISTINCT i.id, i.item_name, i.variety
         FROM inventory_items i
         JOIN division_pools p ON p.item_id = i.id
         JOIN ds_divisions ds ON ds.id = p.ds_division_id
         JOIN users u ON u.ds_division_id = ds.id
         WHERE u.role = 'social-service-officer' AND u.status = 'active'
         ORDER BY i.item_name, i.variety"
    )->fetchAll(PDO::FETCH_ASSOC);


    /* -----------------------------------------------------
       OFFICER POOL BALANCES
       ----------------------------------------------------- */

    $rows = database()->query(
        "
        SELECT
            active_ssos.officer_id,
            active_ssos.full_name,
            active_ssos.ds_division_id,
            d.id AS district_id,
            ds.name AS ds_name,
            d.name AS district_name,
            p.item_id,
            i.item_name,
            i.variety,
            p.allocated,
            p.distributed,
            p.reused,
            p.updated_at AS pool_updated_at,
            GREATEST(COALESCE(CAST(p.allocated AS SIGNED) - CAST(p.distributed AS SIGNED) + CAST(p.reused AS SIGNED), 0), 0) AS remaining
        FROM (
            SELECT ds_division_id, MIN(id) AS officer_id,
                   GROUP_CONCAT(full_name ORDER BY full_name SEPARATOR ', ') AS full_name
            FROM users
            WHERE role = 'social-service-officer' AND status = 'active'
              AND ds_division_id IS NOT NULL
            GROUP BY ds_division_id
        ) active_ssos
        JOIN ds_divisions ds
            ON ds.id = active_ssos.ds_division_id
        JOIN districts d
            ON d.id = ds.district_id
        LEFT JOIN division_pools p
            ON p.ds_division_id = ds.id
        LEFT JOIN inventory_items i
            ON i.id = p.item_id
        ORDER BY
            d.name,
            ds.name,
            i.item_name
        "
    )->fetchAll(PDO::FETCH_ASSOC);


} catch (PDOException $e) {

    error_log($e->getMessage());

    $divisions = [];
    $districts = [];
    $stockItems = [];
    $rows = [];

    $errors[] =
        'Officer pools are unavailable.';
}


/* =========================================================
   SUMMARY TOTALS
   ========================================================= */

$lowStockThreshold = 10;
try {
    $configuredThreshold = database()->query(
        "SELECT setting_value FROM system_settings WHERE setting_key = 'low_stock_threshold' LIMIT 1"
    )->fetchColumn();
    if ($configuredThreshold !== false) {
        $lowStockThreshold = max(0, (int) $configuredThreshold);
    }
} catch (PDOException $e) {
    error_log('Officer pool low-stock threshold unavailable: ' . $e->getMessage());
}

$selectedDivision = filter_input(INPUT_GET, 'division_id', FILTER_VALIDATE_INT) ?: 0;
$selectedDistrict = filter_input(INPUT_GET, 'district_id', FILTER_VALIDATE_INT) ?: 0;
$selectedItem = filter_input(INPUT_GET, 'item_id', FILTER_VALIDATE_INT) ?: 0;
$selectedPool = filter_input(INPUT_GET, 'pool_id', FILTER_VALIDATE_INT) ?: 0;
$selectedItem = $selectedPool > 0 ? $selectedItem : 0;
$selectedLevel = (string) ($_GET['stock'] ?? 'all');
if (!in_array($selectedLevel, ['all', 'low', 'healthy'], true)) {
    $selectedLevel = 'all';
}
$search = trim((string) ($_GET['search'] ?? ''));
$search = substr($search, 0, 100);

$lowStockCount = 0;
$lowStockKeys = [];
$filteredRows = [];
$officerSummaries = [];
foreach ($rows as $row) {
    $hasAllocation = $row['item_id'] !== null;
    $remaining = (int) $row['remaining'];
    $level = !$hasAllocation ? 'none' : ($remaining <= $lowStockThreshold ? 'low' : 'healthy');
    $row['stock_level'] = $level;

    $poolId = (int) $row['ds_division_id'];
    if (!isset($officerSummaries[$poolId])) {
        $officerSummaries[$poolId] = [
            'pool_id' => $poolId,
            'full_name' => (string) $row['full_name'],
            'district_id' => (int) ($row['district_id'] ?? 0),
            'ds_division_id' => (int) ($row['ds_division_id'] ?? 0),
            'district_name' => (string) ($row['district_name'] ?? ''),
            'ds_name' => (string) ($row['ds_name'] ?? ''),
            'item_count' => 0,
            'remaining' => 0,
            'low_count' => 0,
            'pool_items' => [],
        ];
    }
    $officerSummaries[$poolId]['pool_items'][] = [
        'id' => $hasAllocation ? (string) $row['item_id'] : '',
        'level' => $level,
        'search' => $hasAllocation ? implode(' ', [(string) $row['item_name'], widmsAidItemName((string) $row['item_name']), (string) ($row['variety'] ?? '')]) : '',
    ];
    if ($hasAllocation) {
        $officerSummaries[$poolId]['item_count']++;
        $officerSummaries[$poolId]['remaining'] += $remaining;
        if ($level === 'low') {
            $officerSummaries[$poolId]['low_count']++;
        }
    }

    if ($level === 'low') {
        $lowStockKeys[(int) $row['ds_division_id'] . ':' . (int) $row['item_id']] = true;
    }

    if ($selectedDivision && (int) $row['ds_division_id'] !== $selectedDivision) {
        continue;
    }
    if ($selectedDistrict && (int) $row['district_id'] !== $selectedDistrict) {
        continue;
    }
    if ($selectedItem && (int) $row['item_id'] !== $selectedItem) {
        continue;
    }
    if ($selectedPool && (int) $row['ds_division_id'] !== $selectedPool) {
        continue;
    }
    if ($selectedLevel !== 'all' && $level !== $selectedLevel) {
        continue;
    }
    if ($search !== '' && stripos(
        implode(' ', [(string) $row['full_name'], (string) $row['ds_name'], (string) $row['district_name'], (string) $row['item_name'], widmsAidItemName((string) ($row['item_name'] ?? '')), (string) $row['variety']]),
        $search
    ) === false) {
        continue;
    }
    $filteredRows[] = $row;
}
$visiblePoolIds = array_fill_keys(array_map(static fn(array $row): int => (int) $row['ds_division_id'], $filteredRows), true);
$lowStockCount = count($lowStockKeys);
$visibleOfficers = $officerSummaries;
$selectedPoolDetails = $officerSummaries[$selectedPool] ?? null;
$detailRows = array_values(array_filter($filteredRows, static fn(array $row): bool => $row['item_id'] !== null));
$detailStockItems = $selectedPool > 0 ? array_values(array_filter($stockItems, static function(array $item) use ($rows, $selectedPool): bool {
    foreach ($rows as $row) if ((int)$row['ds_division_id'] === $selectedPool && (int)($row['item_id'] ?? 0) === (int)$item['id']) return true;
    return false;
})) : [];

if (($_GET['export'] ?? '') === 'pdf') {
    if ($selectedPool > 0 && $selectedPoolDetails === null) {
        http_response_code(404);
        exit('SSO pool not found.');
    }
    $autoload = __DIR__ . '/../../vendor/autoload.php';
    if (!is_file($autoload)) {
        http_response_code(503);
        exit('PDF generator is unavailable. Run composer install.');
    }
    require_once $autoload;

    $poolPdfEscape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    $reportNow = new DateTimeImmutable('now', new DateTimeZone('Asia/Colombo'));
    $reportDate = $reportNow->format('d M Y, H:i');
    $reportTitle = $selectedPoolDetails ? $selectedPoolDetails['ds_name'] . ' - Division Pool Details' : 'DS Division Pools';
    $filterLabels = [];
    if ($search !== '') $filterLabels[] = 'Search: ' . $search;
    if ($selectedDistrict) {
        foreach ($districts as $district) {
            if ((int) $district['id'] === $selectedDistrict) $filterLabels[] = 'District: ' . $district['name'];
        }
    }
    if ($selectedDivision) {
        foreach ($divisions as $division) {
            if ((int) $division['id'] === $selectedDivision) $filterLabels[] = 'DS Division: ' . $division['name'];
        }
    }
    if ($selectedItem) {
        foreach ($stockItems as $stockItem) {
            if ((int) $stockItem['id'] === $selectedItem) $filterLabels[] = 'Stock item: ' . widmsAidItemName((string) $stockItem['item_name']) . ((string) $stockItem['variety'] !== '' ? ' - ' . $stockItem['variety'] : '');
        }
    }
    if ($selectedLevel !== 'all') $filterLabels[] = 'Stock level: ' . ucfirst($selectedLevel);
    $pdfOfficers = array_filter($officerSummaries, static fn(array $officer): bool => isset($visiblePoolIds[$officer['pool_id']]));

    ob_start();
    ?>
    <!doctype html><html><head><meta charset="UTF-8"><style>
        @page { margin: 24px; }
        body { font-family: DejaVu Sans, sans-serif; color: #18374f; font-size: 10px; }
        h1 { color: #155785; font-size: 19px; margin: 0 0 7px; }
        .meta { color: #4f6678; margin: 0 0 7px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th { background: #e8f2f8; color: #153e5d; text-align: left; }
        th, td { border: 1px solid #d2e0e9; padding: 8px 7px; vertical-align: top; }
        tr { page-break-inside: avoid; }
        .low { color: #aa2626; font-weight: bold; }
        .muted { color: #698095; }
    </style></head><body>
        <h1><?= $poolPdfEscape($reportTitle) ?></h1>
        <p class="meta">Report date: <?= $poolPdfEscape($reportDate) ?> (Sri Lanka time)</p>
        <?php if ($selectedPoolDetails): ?>
            <p class="meta">District: <?= $poolPdfEscape($selectedPoolDetails['district_name'] ?: 'Unassigned') ?> &nbsp; | &nbsp; Assigned SSO: <?= $poolPdfEscape($selectedPoolDetails['full_name']) ?></p>
        <?php endif; ?>
        <p class="meta">Filters: <?= $poolPdfEscape($filterLabels ? implode(' | ', $filterLabels) : 'None') ?></p>
        <?php if ($selectedPoolDetails): ?>
            <table><thead><tr><th>Aid item</th><th>Allocated</th><th>Distributed</th><th>Reused</th><th>Remaining</th><th>Stock level</th><th>Last updated</th></tr></thead><tbody>
                <?php if ($detailRows === []): ?><tr><td colspan="7">No pool aid items match this view.</td></tr><?php endif; ?>
                <?php foreach ($detailRows as $poolRow): ?>
                    <?php $poolRemaining = (int) $poolRow['remaining']; ?>
                    <tr>
                        <td><?= $poolPdfEscape(widmsAidItemName((string) $poolRow['item_name']) . ((string) $poolRow['variety'] !== '' ? ' - ' . $poolRow['variety'] : '')) ?></td>
                        <td><?= (int) $poolRow['allocated'] ?></td><td><?= (int) $poolRow['distributed'] ?></td><td><?= (int) $poolRow['reused'] ?></td><td><?= $poolRemaining ?></td>
                        <td class="<?= $poolRow['stock_level'] === 'low' ? 'low' : '' ?>"><?= $poolPdfEscape($poolRow['stock_level'] === 'low' ? ($poolRemaining === 0 ? 'Empty' : 'Low') : 'Healthy') ?></td>
                        <td><?= $poolPdfEscape($poolRow['pool_updated_at'] ?: '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody></table>
        <?php else: ?>
            <table><thead><tr><th>DS Division / District</th><th>Assigned SSO</th><th>Pool aid items</th><th>Deliverable units</th><th>Pool stock</th></tr></thead><tbody>
                <?php if ($pdfOfficers === []): ?><tr><td colspan="5">No divisions match these filters.</td></tr><?php endif; ?>
                <?php foreach ($pdfOfficers as $officer): ?>
                    <tr>
                        <td><?= $poolPdfEscape(($officer['ds_name'] ?: 'Unassigned') . ' / ' . ($officer['district_name'] ?: 'Unassigned')) ?></td><td><?= $poolPdfEscape($officer['full_name']) ?></td>
                        <td><?= (int) $officer['item_count'] ?></td><td><?= (int) $officer['remaining'] ?></td>
                        <td class="<?= $officer['low_count'] > 0 ? 'low' : '' ?>"><?= $poolPdfEscape($officer['item_count'] === 0 ? '—' : ($officer['low_count'] > 0 ? $officer['low_count'] . ' low item(s)' : 'Healthy')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody></table>
        <?php endif; ?>
    </body></html>
    <?php
    $pdfHtml = (string) ob_get_clean();
    $pdf = new \Dompdf\Dompdf(new \Dompdf\Options());
    $pdf->loadHtml($pdfHtml, 'UTF-8');
    $pdf->setPaper('A4', $selectedPoolDetails ? 'portrait' : 'landscape');
    $pdf->render();
    $pdf->stream('SWPCS-Division-Pool-' . ($selectedPoolDetails ? $selectedPool . '-' : '') . $reportNow->format('Y-m-d') . '.pdf', ['Attachment' => true]);
    exit;
}

$poolExportParams = ['page' => 'officer-pools', 'export' => 'pdf'];
if ($selectedPool > 0) $poolExportParams['pool_id'] = $selectedPool;
if ($selectedDistrict > 0) $poolExportParams['district_id'] = $selectedDistrict;
if ($selectedDivision > 0) $poolExportParams['division_id'] = $selectedDivision;
if ($selectedItem > 0) $poolExportParams['item_id'] = $selectedItem;
if ($selectedLevel !== 'all') $poolExportParams['stock'] = $selectedLevel;
if ($search !== '') $poolExportParams['search'] = $search;
$poolExportUrl = 'dashboard.php?' . http_build_query($poolExportParams);

?>

<!doctype html>

<html lang="en">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >


    <title>
        DS Division Pools | SWPCS
    </title>


    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <link
        href="assets/css/admin-dashboard.css"
        rel="stylesheet"
    >
    <link href="assets/css/officer-pools.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/officer-pools.css') ?>" rel="stylesheet">

</head>


<body>


<?php

/* =========================================================
   LOAD SIDEBAR BASED ON LOGGED-IN ROLE
   ========================================================= */

if ($currentRole === 'admin') {

    require __DIR__
        . '/../../includes/admin-sidebar.php';

} elseif ($currentRole === 'subject-officer') {

    require __DIR__
        . '/../../includes/subject-officer-sidebar.php';
}

?>


<div class="admin-shell">


    <!-- =====================================================
         TOP BAR
         ===================================================== -->

    <header class="topbar">


        <div class="d-flex align-items-center gap-3">


            <button
                type="button"
                class="menu-button"
                id="menu-button"
                aria-label="Open navigation"
            >
                &#9776;
            </button>


            <h1>
                <?= htmlspecialchars(t('DS Division Pools'), ENT_QUOTES, 'UTF-8') ?>
            </h1>


        </div>


        <div class="topbar-actions">


            <label class="search-box">


                <span aria-hidden="true">
                    &#128269;
                </span>


                <input
                    type="search"
                    placeholder="Search anything..."
                    aria-label="Search"
                >


            </label>


            <button
                class="notification-button"
                type="button"
                aria-label="Notifications"
            >
                &#128276;
            </button>


        </div>


    </header>


    <!-- =====================================================
         MAIN CONTENT
         ===================================================== -->

    <main
        class="dashboard-content officer-pools-workflow"
    >


        <!-- =================================================
             SUCCESS MESSAGE
             ================================================= -->

        <?php if ($success !== ''): ?>


            <div class="alert alert-success">

                <?= htmlspecialchars(
                    $success,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>


        <?php endif; ?>


        <!-- =================================================
             ERROR MESSAGE
             ================================================= -->

        <?php if ($errors): ?>


            <div class="alert alert-danger">

                <?= htmlspecialchars(
                    implode(
                        ' ',
                        $errors
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>


        <?php endif; ?>


        <section
            class="admin-data-card pool-table-card" id="pool-results"
        >
            <?php if ($selectedPool > 0): ?>
                <div class="pool-detail-heading">
                    <a href="dashboard.php?page=officer-pools#pool-results">&larr; All division pools</a>
                    <strong><?= htmlspecialchars($selectedPoolDetails['ds_name'] ?? 'Division not found', ENT_QUOTES, 'UTF-8') ?></strong>
                    <?php if ($selectedPoolDetails): ?>
                        <span><?= htmlspecialchars(($selectedPoolDetails['district_name'] ?: 'Unassigned') . ' · SSO: ' . $selectedPoolDetails['full_name'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                    <form class="pool-detail-filters" method="get" action="dashboard.php#pool-results">
                        <input type="hidden" name="page" value="officer-pools">
                        <input type="hidden" name="pool_id" value="<?= $selectedPool ?>">
                        <label>Stock item
                            <select name="item_id" onchange="this.form.submit()">
                                <option value="">All stock items</option>
                                <?php foreach ($detailStockItems as $item): ?>
                                    <option value="<?= (int)$item['id'] ?>" <?= $selectedItem === (int)$item['id'] ? 'selected' : '' ?>><?= htmlspecialchars(widmsAidItemName((string)$item['item_name']) . ((string)$item['variety'] !== '' ? ' - ' . $item['variety'] : ''), ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Stock level
                            <select name="stock" onchange="this.form.submit()">
                                <option value="all" <?= $selectedLevel === 'all' ? 'selected' : '' ?>>All levels</option>
                                <option value="low" <?= $selectedLevel === 'low' ? 'selected' : '' ?>>Low / empty</option>
                                <option value="healthy" <?= $selectedLevel === 'healthy' ? 'selected' : '' ?>>Healthy</option>
                            </select>
                        </label>
                        <?php if ($selectedItem || $selectedLevel !== 'all'): ?><a href="dashboard.php?page=officer-pools&amp;pool_id=<?= $selectedPool ?>#pool-results">Clear</a><?php endif; ?>
                    </form>
                    <a class="pool-pdf-button" href="<?= htmlspecialchars($poolExportUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Download PDF</a>
                </div>
            <?php else: ?>
            <form class="pool-overview-filters" id="pool-overview-filters" method="get" action="dashboard.php#pool-results">
                <input type="hidden" name="page" value="officer-pools">
                <label>Search
                    <input type="search" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Division, SSO, or aid item">
                </label>
                <label>District
                    <select name="district_id" id="pool-district-filter">
                        <option value="">All districts</option>
                        <?php foreach ($districts as $district): ?>
                            <option value="<?= (int) $district['id'] ?>" <?= $selectedDistrict === (int) $district['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $district['name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>DS Division
                    <select name="division_id" id="pool-division-filter">
                        <option value="">All divisions</option>
                        <?php foreach ($divisions as $division): ?>
                            <option value="<?= (int) $division['id'] ?>" data-district="<?= (int) $division['district_id'] ?>" <?= $selectedDivision === (int) $division['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $division['name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Stock level
                    <select name="stock">
                        <option value="all" <?= $selectedLevel === 'all' ? 'selected' : '' ?>>All levels</option>
                        <option value="low" <?= $selectedLevel === 'low' ? 'selected' : '' ?>>Low / empty</option>
                        <option value="healthy" <?= $selectedLevel === 'healthy' ? 'selected' : '' ?>>Healthy</option>
                    </select>
                </label>
                <?php if ($selectedPool): ?><input type="hidden" name="pool_id" value="<?= $selectedPool ?>"><?php endif; ?>
                <a id="pool-clear-filters" href="dashboard.php?page=officer-pools#pool-results">Clear</a>
                <a class="pool-pdf-button" id="pool-export-pdf" href="<?= htmlspecialchars($poolExportUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Download PDF</a>
            </form>
            <div class="pool-inline-alerts" aria-label="Pool stock alerts">
                <a href="dashboard.php?page=officer-pools&amp;stock=low#pool-results"><b><?= $lowStockCount ?></b> low-stock <?= $lowStockCount === 1 ? 'item' : 'items' ?></a>
            </div>
            <?php endif; ?>


            <div class="admin-data-table-wrap">

                <?php if ($selectedPool > 0): ?>


                <table
                    class="admin-data-table officer-pools-table"
                >


                    <thead>


                        <tr>

                            <th><?= htmlspecialchars(t('DS Division / District'), ENT_QUOTES, 'UTF-8') ?></th>

                            <th><?= htmlspecialchars(t('Assigned SSO'), ENT_QUOTES, 'UTF-8') ?></th>

                            <th>Item</th>

                            <th>Allocated</th>

                            <th>Distributed</th>

                            <th>Reused</th>

                            <th>Remaining</th>

                            <th>Stock Level</th>

                        </tr>


                    </thead>


                    <tbody>


                        <?php if (!$detailRows): ?>


                            <tr>


                                <td
                                    colspan="8"
                                    class="admin-empty-row"
                                >

                                    <?= $selectedPoolDetails ? 'No pool aid items match this view.' : 'Division not found.' ?>

                                </td>


                            </tr>


                        <?php else: ?>


                            <?php foreach ($detailRows as $row): ?>


                                <?php

                                $remaining =
                                    (int) $row['remaining'];


                                $stockLevel = $row['stock_level'] === 'low' ? ($remaining === 0 ? 'Empty' : 'Low') : 'Healthy';

                                ?>


                                <tr>


                                    <td>

                                        <span class="pool-division-name"><?= htmlspecialchars($row['ds_name'] ?: 'Unassigned', ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="pool-district-name"><?= htmlspecialchars($row['district_name'] ?: 'Unassigned', ENT_QUOTES, 'UTF-8') ?></span>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars($row['full_name'], ENT_QUOTES, 'UTF-8') ?>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            widmsAidItemName((string)$row['item_name'])
                                            . (
                                                $row['variety']
                                                ? ' — '
                                                    . $row['variety']
                                                : ''
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= (int) $row['allocated'] ?>

                                    </td>


                                    <td>

                                        <?= (int) $row['distributed'] ?>

                                    </td>


                                    <td>

                                        <?= (int) $row['reused'] ?>

                                    </td>


                                    <td>

                                        <?= $remaining ?>

                                    </td>


                                    <td>

                                        <span class="pool-level pool-level-<?= htmlspecialchars((string) $row['stock_level'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($stockLevel, ENT_QUOTES, 'UTF-8') ?></span>

                                    </td>


                                </tr>


                            <?php endforeach; ?>


                        <?php endif; ?>


                    </tbody>


                </table>

                <?php else: ?>
                <table class="admin-data-table officer-pools-table pool-officer-summary-table">
                    <thead>
                        <tr>
                            <th><?= htmlspecialchars(t('DS Division / District'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(t('Assigned SSO'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th>Pool Aid Items</th>
                            <th>Deliverable Units</th>
                            <th>Pool Stock</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($visibleOfficers === []): ?>
                            <tr><td colspan="6" class="admin-empty-row">No divisions match these filters.</td></tr>
                        <?php else: ?>
                            <tr id="pool-no-results" <?= $visiblePoolIds === [] ? '' : 'hidden' ?>><td colspan="6" class="admin-empty-row">No divisions match these filters.</td></tr>
                            <?php foreach ($visibleOfficers as $officer): ?>
                                <?php
                                $detailParams = ['page' => 'officer-pools', 'pool_id' => $officer['pool_id']];
                                if ($selectedLevel !== 'all') $detailParams['stock'] = $selectedLevel;
                                if ($selectedItem > 0) $detailParams['item_id'] = $selectedItem;
                                $detailUrl = 'dashboard.php?' . http_build_query($detailParams) . '#pool-results';
                                $poolLevel = $officer['item_count'] === 0 ? 'none' : ($officer['low_count'] > 0 ? 'low' : 'healthy');
                                $poolText = $poolLevel === 'none' ? '—' : ($poolLevel === 'low' ? $officer['low_count'] . ' low item(s)' : 'Healthy');
                                ?>
                                <tr class="pool-officer-row"
                                    data-district="<?= (int) $officer['district_id'] ?>"
                                    data-division="<?= (int) $officer['ds_division_id'] ?>"
                                    data-pool-items="<?= htmlspecialchars((string) json_encode($officer['pool_items'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>"
                                    data-search-base="<?= htmlspecialchars($officer['full_name'] . ' ' . $officer['district_name'] . ' ' . $officer['ds_name'], ENT_QUOTES, 'UTF-8') ?>"
                                    <?= isset($visiblePoolIds[$officer['pool_id']]) ? '' : 'hidden' ?>>
                                    <td><span class="pool-division-name"><?= htmlspecialchars($officer['ds_name'] ?: 'Unassigned', ENT_QUOTES, 'UTF-8') ?></span><span class="pool-district-name"><?= htmlspecialchars($officer['district_name'] ?: 'Unassigned', ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td><?= htmlspecialchars($officer['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= (int) $officer['item_count'] ?></td>
                                    <td><?= (int) $officer['remaining'] ?></td>
                                    <td><span class="pool-level pool-level-<?= $poolLevel ?>"><?= htmlspecialchars($poolText, ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td><a class="pool-detail-button" href="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>">View details</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php endif; ?>


            </div>


        </section>


    </main>


</div>


<script src="assets/js/admin-dashboard.js"></script>
<script>
(() => {
    const form = document.getElementById('pool-overview-filters');
    if (!form) return;
    const district = form.elements.namedItem('district_id');
    const division = form.elements.namedItem('division_id');
    const item = form.elements.namedItem('item_id');
    const level = form.elements.namedItem('stock');
    const search = form.elements.namedItem('search');
    const rows = [...document.querySelectorAll('.pool-officer-row')];
    const poolItems = new Map(rows.map(row => [row, JSON.parse(row.dataset.poolItems || '[]')]));
    const empty = document.getElementById('pool-no-results');
    const exportLink = document.getElementById('pool-export-pdf');
    const updateDivisions = () => {
        for (const option of division.options) {
            const matches = !district.value || !option.dataset.district || option.dataset.district === district.value;
            option.hidden = !matches;
            option.disabled = !matches;
        }
        if (division.selectedOptions[0]?.disabled) division.value = '';
    };
    const applyFilters = () => {
        const query = search.value.trim().toLocaleLowerCase();
        let shown = 0;
        for (const row of rows) {
            const commonMatches = !query || row.dataset.searchBase.toLocaleLowerCase().includes(query);
            const matchingItem = poolItems.get(row).some(poolItem =>
                (!item.value || poolItem.id === item.value)
                && (level.value === 'all' || poolItem.level === level.value)
                && (commonMatches || poolItem.search.toLocaleLowerCase().includes(query))
            );
            const matches = (!district.value || row.dataset.district === district.value)
                && (!division.value || row.dataset.division === division.value)
                && matchingItem;
            row.hidden = !matches;
            if (matches) shown++;
            const link = row.querySelector('.pool-detail-button');
            if (link) {
                const detailUrl = new URL(link.href);
                if (level.value === 'all') detailUrl.searchParams.delete('stock');
                else detailUrl.searchParams.set('stock', level.value);
                if (item.value) detailUrl.searchParams.set('item_id', item.value);
                else detailUrl.searchParams.delete('item_id');
                if (search.value.trim()) detailUrl.searchParams.set('search', search.value.trim());
                else detailUrl.searchParams.delete('search');
                link.href = detailUrl.pathname + detailUrl.search + '#pool-results';
            }
        }
        if (empty) empty.hidden = shown !== 0;
        const url = new URL(window.location.href);
        url.searchParams.set('page', 'officer-pools');
        for (const [key, value] of Object.entries({search: search.value.trim(), district_id: district.value, division_id: division.value, item_id: item.value, stock: level.value === 'all' ? '' : level.value})) {
            if (value) url.searchParams.set(key, value);
            else url.searchParams.delete(key);
        }
        if (exportLink) {
            const exportUrl = new URL(url.href);
            exportUrl.searchParams.set('export', 'pdf');
            exportLink.href = exportUrl.pathname + exportUrl.search;
        }
        history.replaceState(null, '', url.pathname + url.search + url.hash);
    };
    district.addEventListener('change', () => { updateDivisions(); applyFilters(); });
    for (const control of [division, item, level]) control.addEventListener('change', applyFilters);
    search.addEventListener('input', applyFilters);
    form.addEventListener('submit', event => { event.preventDefault(); applyFilters(); });
    document.getElementById('pool-clear-filters')?.addEventListener('click', event => {
        event.preventDefault();
        search.value = '';
        district.value = '';
        division.value = '';
        item.value = '';
        level.value = 'all';
        updateDivisions();
        applyFilters();
    });
    updateDivisions();
    applyFilters();
})();
</script>


</body>

</html>
