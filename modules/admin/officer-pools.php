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
                    INSERT INTO officer_pools
                    (
                        officer_id,
                        item_id,
                        allocated
                    )

                    VALUES
                    (
                        :officer,
                        :item,
                        :quantity
                    )

                    ON DUPLICATE KEY UPDATE
                        allocated =
                            allocated + VALUES(allocated)
                    "
                );


                $poolStatement->execute([
                    'officer'  => $officer,
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
                        item_id,
                        quantity,
                        allocated_by
                    )

                    VALUES
                    (
                        :officer,
                        :item,
                        :quantity,
                        :allocated_by
                    )
                    "
                );


                $allocationStatement->execute([
                    'officer'     => $officer,
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
   LOAD OFFICERS, DIVISIONS, STOCK AND POOL DATA
   ========================================================= */

try {


    /* -----------------------------------------------------
       SOCIAL SERVICE OFFICERS
       ----------------------------------------------------- */

    $officers = database()->query(
        "
        SELECT
            id,
            full_name,
            ds_division_id

        FROM users

        WHERE
            role = 'social-service-officer'
            AND status = 'active'

        ORDER BY full_name
        "
    )->fetchAll(PDO::FETCH_ASSOC);


    /* -----------------------------------------------------
       DS DIVISIONS
       ----------------------------------------------------- */

    $divisions = database()->query(
        "
        SELECT
            ds.id,

            CONCAT(
                d.name,
                ' — ',
                ds.name
            ) AS name

        FROM ds_divisions ds

        JOIN districts d
            ON d.id = ds.district_id

        WHERE ds.status = 'active'

        ORDER BY
            d.name,
            ds.name
        "
    )->fetchAll(PDO::FETCH_ASSOC);


    /* -----------------------------------------------------
       AVAILABLE DIVISION STOCK
       ----------------------------------------------------- */

    $divisionStock = database()->query(
        "
        SELECT
            di.item_id,
            di.ds_division_id,
            di.quantity,
            i.item_name,
            i.variety,
            ds.name AS division_name

        FROM division_inventory di

        JOIN inventory_items i
            ON i.id = di.item_id

        JOIN ds_divisions ds
            ON ds.id = di.ds_division_id

        WHERE di.quantity > 0

        ORDER BY
            ds.name,
            i.item_name
        "
    )->fetchAll(PDO::FETCH_ASSOC);


    /* -----------------------------------------------------
       OFFICER POOL BALANCES
       ----------------------------------------------------- */

    $rows = database()->query(
        "
        SELECT

            u.full_name,

            ds.name AS ds_name,

            d.name AS district_name,

            i.item_name,

            i.variety,

            p.allocated,

            p.distributed,

            p.reused,

            (
                p.allocated
                - p.distributed
                + p.reused
            ) AS remaining,

            u.status

        FROM officer_pools p

        JOIN users u
            ON u.id = p.officer_id

        LEFT JOIN ds_divisions ds
            ON ds.id = u.ds_division_id

        LEFT JOIN districts d
            ON d.id = u.district_id

        JOIN inventory_items i
            ON i.id = p.item_id

        ORDER BY
            u.full_name,
            i.item_name
        "
    )->fetchAll(PDO::FETCH_ASSOC);


} catch (PDOException $e) {

    error_log($e->getMessage());

    $officers = [];
    $divisions = [];
    $divisionStock = [];
    $rows = [];

    $errors[] =
        'Officer pools are unavailable.';
}


/* =========================================================
   SUMMARY TOTALS
   ========================================================= */

$totals = [

    'officers' =>
        count($officers),

    'allocated' =>
        array_sum(
            array_column(
                $rows,
                'allocated'
            )
        ),

    'distributed' =>
        array_sum(
            array_column(
                $rows,
                'distributed'
            )
        ),

    'remaining' =>
        array_sum(
            array_column(
                $rows,
                'remaining'
            )
        )

];

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
        Social Service Officer Pools | WIDMS
    </title>


    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <link
        href="assets/css/admin-dashboard.css"
        rel="stylesheet"
    >

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
                Social Service Officer Pools
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


        <!-- =================================================
             SUMMARY CARDS
             ================================================= -->

        <section class="operation-summary-grid">


            <?php

            $summaryCards = [

                [
                    'Total Officers',
                    $totals['officers']
                ],

                [
                    'Total Allocated',
                    $totals['allocated']
                ],

                [
                    'Total Distributed',
                    $totals['distributed']
                ],

                [
                    'Total Remaining',
                    $totals['remaining']
                ]

            ];

            ?>


            <?php
            foreach (
                $summaryCards
                as [$label, $value]
            ):
            ?>


                <article class="operation-summary-card">


                    <span>
                        &#128230;
                    </span>


                    <p>

                        <?= htmlspecialchars(
                            $label,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </p>


                    <strong>

                        <?= number_format(
                            (int) $value
                        ) ?>

                    </strong>


                    <small>
                        Live pool total
                    </small>


                </article>


            <?php endforeach; ?>


        </section>


        <!-- =================================================
             ADMIN / SUBJECT OFFICER ACTION FORMS
             ================================================= -->

        


        <!-- =================================================
             OFFICER POOL BALANCE TABLE
             ================================================= -->

        <section
            class="admin-data-card pool-table-card"
        >


            <div class="admin-data-header">


                <h2>
                    Officer Pool Balances
                </h2>


            </div>


            <div class="admin-data-table-wrap">


                <table
                    class="admin-data-table officer-pools-table"
                >


                    <thead>


                        <tr>

                            <th>Officer</th>

                            <th>DS Division</th>

                            <th>District</th>

                            <th>Item</th>

                            <th>Allocated</th>

                            <th>Distributed</th>

                            <th>Reused</th>

                            <th>Remaining</th>

                            <th>Stock Level</th>

                            <th>Status</th>

                        </tr>


                    </thead>


                    <tbody>


                        <?php if (!$rows): ?>


                            <tr>


                                <td
                                    colspan="10"
                                    class="admin-empty-row"
                                >

                                    No officer pool allocations available.

                                </td>


                            </tr>


                        <?php else: ?>


                            <?php foreach ($rows as $row): ?>


                                <?php

                                $remaining =
                                    (int) $row['remaining'];


                                if ($remaining === 0) {

                                    $stockLevel = 'Empty';

                                } elseif ($remaining < 5) {

                                    $stockLevel = 'Low';

                                } else {

                                    $stockLevel = 'OK';
                                }

                                ?>


                                <tr>


                                    <td>

                                        <strong>

                                            <?= htmlspecialchars(
                                                $row['full_name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </strong>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $row['ds_name']
                                            ?: 'Unassigned',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $row['district_name']
                                            ?: '—',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $row['item_name']
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

                                        <?= htmlspecialchars(
                                            $stockLevel,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            ucfirst(
                                                (string) $row['status']
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>


                                </tr>


                            <?php endforeach; ?>


                        <?php endif; ?>


                    </tbody>


                </table>


            </div>


        </section>


    </main>


</div>


<script src="assets/js/admin-dashboard.js"></script>


</body>

</html>