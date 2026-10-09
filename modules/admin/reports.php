<?php

declare(strict_types=1);


/* =========================================================
   ACCESS CONTROL
   Both Admin and Subject Officer can access this page
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
   DATABASE
   ========================================================= */

require_once __DIR__ . '/../../config/database.php';


/* =========================================================
   PAGE SETTINGS
   ========================================================= */

$activePage = 'reports';

$type = (string) ($_GET['report'] ?? '');
$export = (string) ($_GET['export'] ?? '');


/* =========================================================
   REPORT DEFINITIONS
   ========================================================= */

$reports = [

    /* =====================================================
       INVENTORY REPORT
       ===================================================== */

    'inventory' => [

        'Inventory Report',

        'Current stock levels by item, category and variety',

        [
            'Item',
            'Category',
            'Variety',
            'In Stock'
        ],

        "
        SELECT
            i.item_name AS Item,
            COALESCE(c.name, i.category) AS Category,
            i.variety AS Variety,
            i.quantity AS `In Stock`

        FROM inventory_items i

        LEFT JOIN item_categories c
            ON c.id = i.category_id

        ORDER BY
            i.item_name,
            i.variety
        "
    ],


    /* =====================================================
       DISTRIBUTION REPORT
       ===================================================== */

    'distribution' => [

        'Distribution Report',

        'All distributions by date, region and distributor',

        [
            'Reference',
            'Beneficiary',
            'Item',
            'Qty',
            'Type',
            'Source',
            'Officer',
            'Date'
        ],

        "
        SELECT

            CONCAT(
                'DIST-',
                LPAD(d.id, 4, '0')
            ) AS Reference,

            b.full_name AS Beneficiary,

            CONCAT(
                i.item_name,
                IF(
                    i.variety = '',
                    '',
                    CONCAT(' / ', i.variety)
                )
            ) AS Item,

            d.quantity AS Qty,

            d.distribution_type AS Type,

            d.source AS Source,

            COALESCE((SELECT GROUP_CONCAT(u.full_name SEPARATOR ', ') FROM users u
                      WHERE u.ds_division_id = p.ds_division_id AND u.role = 'social-service-officer' AND u.status = 'active'), 'Unassigned') AS Officer,

            d.distributed_at AS Date

        FROM distributions d

        JOIN beneficiaries b
            ON b.id = d.beneficiary_id

        JOIN inventory_items i
            ON i.id = d.item_id

        JOIN users u
            ON u.id = d.distributed_by

        ORDER BY d.id DESC
        "
    ],


    /* =====================================================
       BENEFICIARY REPORT
       ===================================================== */

    'beneficiary' => [

        'Beneficiary Report',

        'Beneficiary geography and latest distribution',

        [
            'Beneficiary',
            'NIC',
            'District',
            'DS Division',
            'Status',
            'Last Distribution'
        ],

        "
        SELECT

            b.full_name AS Beneficiary,

            b.nic AS NIC,

            d.name AS District,

            ds.name AS `DS Division`,

            b.status AS Status,

            MAX(x.distributed_at) AS `Last Distribution`

        FROM beneficiaries b

        JOIN districts d
            ON d.id = b.district_id

        JOIN ds_divisions ds
            ON ds.id = b.ds_division_id

        LEFT JOIN distributions x
            ON x.beneficiary_id = b.id

        GROUP BY
            b.id,
            b.full_name,
            b.nic,
            d.name,
            ds.name,
            b.status

        ORDER BY b.full_name
        "
    ],


    /* =====================================================
       SOCIAL SERVICE OFFICER POOL REPORT
       ===================================================== */

    'pools' => [

        'Social Service Officer Pool Report',

        'Quota versus distributed and remaining',

        [
            'Officer',
            'DS Division',
            'Item',
            'Allocated',
            'Distributed',
            'Reused',
            'Remaining'
        ],

        "
        SELECT

            u.full_name AS Officer,

            ds.name AS `DS Division`,

            CONCAT(
                i.item_name,
                IF(
                    i.variety = '',
                    '',
                    CONCAT(' / ', i.variety)
                )
            ) AS Item,

            p.allocated AS Allocated,

            p.distributed AS Distributed,

            p.reused AS Reused,

            (
                p.allocated
                - p.distributed
                + p.reused
            ) AS Remaining

        FROM division_pools p

        LEFT JOIN ds_divisions ds
            ON ds.id = p.ds_division_id

        JOIN inventory_items i
            ON i.id = p.item_id

        ORDER BY
            ds.name,
            i.item_name
        "
    ],


    /* =====================================================
       PROCUREMENT REPORT
       ===================================================== */

    'procurement' => [

        'Procurement Cost Report',

        'Supplier invoices, payments and balances',

        [
            'Batch',
            'Supplier',
            'Bill',
            'Total',
            'Paid',
            'Balance',
            'Received'
        ],

        "
        SELECT

            CONCAT(
                'BAT-',
                LPAD(r.id, 4, '0')
            ) AS Batch,

            s.company_name AS Supplier,

            r.bill_number AS Bill,

            r.total_cost AS Total,

            r.paid_amount AS Paid,

            r.balance_amount AS Balance,

            r.received_date AS Received

        FROM stock_receipts r

        JOIN suppliers s
            ON s.id = r.supplier_id

        ORDER BY r.id DESC
        "
    ],


    /* =====================================================
       REQUEST STATUS REPORT
       ===================================================== */

    'requests' => [

        'Request Status Report',

        'Aid request pipeline and turnaround',

        [
            'Request',
            'Beneficiary',
            'Item',
            'Submitted By',
            'Status',
            'Submitted',
            'Reviewed'
        ],

        "
        SELECT

            CONCAT(
                'AR-',
                LPAD(ar.id, 4, '0')
            ) AS Request,

            b.full_name AS Beneficiary,

            CONCAT(
                i.item_name,
                IF(
                    i.variety = '',
                    '',
                    CONCAT(' / ', i.variety)
                )
            ) AS Item,

            u.full_name AS `Submitted By`,

            ar.status AS Status,

            ar.created_at AS Submitted,

            ar.reviewed_at AS Reviewed

        FROM aid_requests ar

        JOIN beneficiaries b
            ON b.id = ar.beneficiary_id

        JOIN inventory_items i
            ON i.id = ar.item_id

        JOIN users u
            ON u.id = ar.submitted_by

        ORDER BY ar.id DESC
        "
    ],


    /* =====================================================
       RETURN & REUSE REPORT
       ===================================================== */

    'returns' => [

        'Return & Reuse Report',

        'Returns with condition and destination',

        [
            'Return',
            'Distribution',
            'Beneficiary',
            'Item',
            'Qty',
            'Condition',
            'Reusable',
            'Destination',
            'Date'
        ],

        "
        SELECT

            CONCAT(
                'RET-',
                LPAD(r.id, 4, '0')
            ) AS `Return`,

            CONCAT(
                'DIST-',
                LPAD(r.distribution_id, 4, '0')
            ) AS Distribution,

            b.full_name AS Beneficiary,

            i.item_name AS Item,

            r.quantity AS Qty,

            r.item_condition AS `Condition`,

            IF(
                r.reusable,
                'Yes',
                'No'
            ) AS Reusable,

            r.restore_to AS Destination,

            r.stock_review_status AS Stock_Status,

            r.processed_at AS Date

        FROM item_returns r

        JOIN distributions x
            ON x.id = r.distribution_id

        JOIN beneficiaries b
            ON b.id = x.beneficiary_id

        JOIN inventory_items i
            ON i.id = x.item_id

        ORDER BY r.id DESC
        "
    ],


    /* =====================================================
       AUDIT LOG REPORT
       ===================================================== */

    'audit' => [

        'Audit Log Report',

        'Full recorded system activity',

        [
            'Timestamp',
            'User',
            'Role',
            'Module',
            'Action',
            'Reference',
            'Status'
        ],

        "
        SELECT

            a.created_at AS Timestamp,

            COALESCE(
                u.full_name,
                'System'
            ) AS User,

            a.role AS Role,

            a.module AS Module,

            a.action AS Action,

            a.record_reference AS Reference,

            a.status AS Status

        FROM activity_logs a

        LEFT JOIN users u
            ON u.id = a.user_id

        ORDER BY a.id DESC
        "
    ]

];


/* =========================================================
   LOAD REPORT DATA
   ========================================================= */

$rows = [];

$error = '';

if (isset($reports[$type])) {

    try {

        $rows = database()
            ->query($reports[$type][3])
            ->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {

        error_log($e->getMessage());

        $error = 'Unable to generate this report.';
    }
}


/* =========================================================
   CSV EXPORT
   ========================================================= */

if (
    $export === 'csv'
    && isset($reports[$type])
) {

    header(
        'Content-Type: text/csv; charset=UTF-8'
    );

    header(
        'Content-Disposition: attachment; filename="SWPCS-'
        . ucfirst($type)
        . '-Report-'
        . date('Y-m-d')
        . '.csv"'
    );


    $output = fopen(
        'php://output',
        'wb'
    );


    /*
     * UTF-8 BOM for Excel
     */
    fwrite(
        $output,
        "\xEF\xBB\xBF"
    );


    /*
     * Column headings
     */
    fputcsv(
        $output,
        $reports[$type][2]
    );


    /*
     * Report rows
     */
    foreach ($rows as $row) {

        fputcsv(
            $output,
            array_values($row)
        );
    }


    fclose($output);

    exit;
}


/* =========================================================
   PDF EXPORT
   ========================================================= */

if (
    $export === 'pdf'
    && isset($reports[$type])
) {

    /*
     * Composer autoloader
     */
    $autoload =
        __DIR__
        . '/../../vendor/autoload.php';


    if (!is_file($autoload)) {

        exit(
            'Dompdf is not installed. '
            . 'Run: composer require dompdf/dompdf'
        );
    }


    require_once $autoload;


    /* =====================================================
       REPORT INFORMATION
       ===================================================== */

    $reportTitle =
        $reports[$type][0];

    $reportDescription =
        $reports[$type][1];

    $columns =
        $reports[$type][2];


    /* =====================================================
       CREATE PDF HTML
       ===================================================== */

    $html = '

    <!doctype html>

    <html>

    <head>

        <meta charset="UTF-8">

        <style>

            @page {
                margin: 25px 25px 35px 25px;
            }

            body {
                font-family: DejaVu Sans, sans-serif;
                font-size: 9px;
                color: #1f2937;
            }

            .report-header {
                border-bottom: 2px solid #5e9bd3;
                padding-bottom: 12px;
                margin-bottom: 14px;
            }

            .system-name {
                font-size: 11px;
                color: #64748b;
                margin-bottom: 5px;
            }

            .report-title {
                margin: 0;
                font-size: 21px;
                color: #1e4f7a;
            }

            .report-description {
                margin-top: 5px;
                color: #64748b;
                font-size: 10px;
            }

            .report-details {
                width: 100%;
                margin-bottom: 15px;
                font-size: 9px;
                color: #475569;
            }

            .report-details td {
                border: none;
                padding: 2px;
            }

            .text-right {
                text-align: right;
            }

            .report-table {
                width: 100%;
                border-collapse: collapse;
                table-layout: auto;
            }

            .report-table thead {
                display: table-header-group;
            }

            .report-table th {
                background-color: #e7f1fb;
                color: #15324b;
                border: 1px solid #b8c9d9;
                padding: 7px 5px;
                text-align: left;
                font-weight: bold;
            }

            .report-table td {
                border: 1px solid #d6e0e8;
                padding: 6px 5px;
                vertical-align: top;
                word-wrap: break-word;
            }

            .report-table tr:nth-child(even) {
                background-color: #f8fafc;
            }

            .empty-row {
                text-align: center;
                padding: 25px;
                color: #64748b;
            }

            .footer {
                position: fixed;
                bottom: -22px;
                left: 0;
                right: 0;
                text-align: center;
                font-size: 8px;
                color: #94a3b8;
                border-top: 1px solid #e2e8f0;
                padding-top: 5px;
            }

        </style>

    </head>

    <body>


        <div class="report-header">

            <div class="system-name">
                Welfare Inventory and Distribution Management System
            </div>

            <h1 class="report-title">'
                . htmlspecialchars(
                    $reportTitle,
                    ENT_QUOTES,
                    'UTF-8'
                )
                . '
            </h1>

            <div class="report-description">'
                . htmlspecialchars(
                    $reportDescription,
                    ENT_QUOTES,
                    'UTF-8'
                )
                . '
            </div>

        </div>


        <table class="report-details">

            <tr>

                <td>

                    <strong>
                        Generated:
                    </strong>

                    '
                    . date('d M Y h:i A')
                    . '

                </td>

                <td class="text-right">

                    <strong>
                        Generated By:
                    </strong>

                    '
                    . htmlspecialchars(
                        ucfirst(
                            str_replace(
                                '-',
                                ' ',
                                $currentRole
                            )
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    )
                    . '

                    &nbsp;&nbsp;

                    <strong>
                        Total Records:
                    </strong>

                    '
                    . count($rows)
                    . '

                </td>

            </tr>

        </table>


        <table class="report-table">

            <thead>

                <tr>';


    /* =====================================================
       PDF COLUMN HEADINGS
       ===================================================== */

    foreach ($columns as $column) {

        $html .=

            '<th>'
            . htmlspecialchars(
                $column,
                ENT_QUOTES,
                'UTF-8'
            )
            . '</th>';
    }


    $html .= '

                </tr>

            </thead>

            <tbody>';


    /* =====================================================
       PDF DATA
       ===================================================== */

    if (empty($rows)) {

        $html .= '

            <tr>

                <td
                    colspan="'
                    . count($columns)
                    . '"
                    class="empty-row"
                >

                    No records available for this report.

                </td>

            </tr>';

    } else {

        foreach ($rows as $row) {

            $html .= '<tr>';


            foreach ($row as $value) {

                $displayValue =
                    $value ?? '—';


                $html .=

                    '<td>'
                    . htmlspecialchars(
                        (string) $displayValue,
                        ENT_QUOTES,
                        'UTF-8'
                    )
                    . '</td>';
            }


            $html .= '</tr>';
        }
    }


    $html .= '

            </tbody>

        </table>


        <div class="footer">

            Generated by SWPCS

        </div>


    </body>

    </html>';


    /* =====================================================
       DOMPDF OPTIONS
       ===================================================== */

    $options =
        new \Dompdf\Options();


    $options->set(
        'isRemoteEnabled',
        true
    );


    /* =====================================================
       CREATE DOMPDF
       ===================================================== */

    $dompdf =
        new \Dompdf\Dompdf(
            $options
        );


    $dompdf->loadHtml(
        $html,
        'UTF-8'
    );


    /*
     * Landscape because some reports contain
     * many columns.
     */
    $dompdf->setPaper(
        'A4',
        'landscape'
    );


    $dompdf->render();


    /* =====================================================
       PDF FILE NAME
       ===================================================== */

    $filename =

        'SWPCS-'
        . ucfirst($type)
        . '-Report-'
        . date('Y-m-d')
        . '.pdf';


    /* =====================================================
       DOWNLOAD PDF
       ===================================================== */

    $dompdf->stream(
        $filename,
        [
            'Attachment' => true
        ]
    );


    exit;
}

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
        Reports | SWPCS
    </title>


    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <link
        href="assets/css/admin-dashboard.css"
        rel="stylesheet"
    >


    <style>

        @media print {

            .sidebar,
            .topbar,
            .reports-grid,
            .report-actions {

                display: none !important;
            }


            .admin-shell {

                margin: 0;
            }


            .dashboard-content {

                padding: 0;
                overflow: visible;
            }


            .report-preview {

                box-shadow: none;
            }
        }

    </style>

</head>


<body>


<?php

/* =========================================================
   LOAD CORRECT SIDEBAR BASED ON ROLE
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
                Reports
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

    <main class="dashboard-content reports-page">


        <!-- =================================================
             REPORT CARDS
             ================================================= -->

        <section class="reports-grid">


            <?php foreach ($reports as $key => $report): ?>


                <article class="report-card">


                    <span class="report-icon">

                        &#128202;

                    </span>


                    <h2>

                        <?= htmlspecialchars(
                            $report[0],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </h2>


                    <p>

                        <?= htmlspecialchars(
                            $report[1],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </p>


                    <div>


                        <!-- ===============================
                             GENERATE REPORT
                             =============================== -->

                        <a
                            class="admin-primary-action"
                            href="dashboard.php?page=reports&amp;report=<?= urlencode($key) ?>"
                        >

                            Generate

                        </a>


                        <!-- ===============================
                             PDF DOWNLOAD
                             =============================== -->

                        <a
                            class="outline-action"
                            href="dashboard.php?page=reports&amp;report=<?= urlencode($key) ?>&amp;export=pdf"
                        >

                            PDF

                        </a>


                        <!-- ===============================
                             CSV DOWNLOAD
                             =============================== -->

                        <a
                            class="outline-action"
                            href="dashboard.php?page=reports&amp;report=<?= urlencode($key) ?>&amp;export=csv"
                        >

                            CSV

                        </a>


                    </div>


                </article>


            <?php endforeach; ?>


        </section>


        <!-- =================================================
             GENERATED REPORT PREVIEW
             ================================================= -->

        <?php if (isset($reports[$type])): ?>


            <section
                class="admin-data-card report-preview"
                style="margin-top: 18px;"
            >


                <div class="admin-data-header">


                    <div>


                        <h2>

                            <?= htmlspecialchars(
                                $reports[$type][0],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </h2>


                        <small>

                            <?= htmlspecialchars(
                                $reports[$type][1],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </small>


                    </div>


                    <div class="report-actions">


                        <!-- PDF -->

                        <a
                            class="outline-action"
                            href="dashboard.php?page=reports&amp;report=<?= urlencode($type) ?>&amp;export=pdf"
                        >

                            Download PDF

                        </a>


                        <!-- CSV -->

                        <a
                            class="outline-action"
                            href="dashboard.php?page=reports&amp;report=<?= urlencode($type) ?>&amp;export=csv"
                        >

                            Download CSV

                        </a>


                        <!-- PRINT -->

                        <button
                            type="button"
                            class="outline-action"
                            onclick="window.print()"
                        >

                            Print

                        </button>


                    </div>


                </div>


                <!-- =========================================
                     ERROR MESSAGE
                     ========================================= -->

                <?php if ($error !== ''): ?>


                    <div class="alert alert-danger">

                        <?= htmlspecialchars(
                            $error,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>


                <?php endif; ?>


                <!-- =========================================
                     REPORT TABLE
                     ========================================= -->

                <div class="admin-data-table-wrap">


                    <table class="admin-data-table">


                        <thead>


                            <tr>


                                <?php
                                foreach (
                                    $reports[$type][2]
                                    as $column
                                ):
                                ?>


                                    <th>

                                        <?= htmlspecialchars(
                                            $column,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </th>


                                <?php endforeach; ?>


                            </tr>


                        </thead>


                        <tbody>


                            <?php if (empty($rows)): ?>


                                <tr>


                                    <td
                                        colspan="<?= count($reports[$type][2]) ?>"
                                        class="admin-empty-row"
                                    >

                                        No records available for this report.

                                    </td>


                                </tr>


                            <?php else: ?>


                                <?php foreach ($rows as $row): ?>


                                    <tr>


                                        <?php foreach ($row as $value): ?>


                                            <td>

                                                <?= htmlspecialchars(
                                                    (string) ($value ?? '—'),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                            </td>


                                        <?php endforeach; ?>


                                    </tr>


                                <?php endforeach; ?>


                            <?php endif; ?>


                        </tbody>


                    </table>


                </div>


            </section>


        <?php endif; ?>


    </main>


</div>


<script src="assets/js/admin-dashboard.js"></script>


</body>

</html>
