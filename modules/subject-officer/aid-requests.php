<?php

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| 1. ACCESS CONTROL
|--------------------------------------------------------------------------
| Only Subject Officers can access this page.
|--------------------------------------------------------------------------
*/

requireRole('subject-officer');


/*
|--------------------------------------------------------------------------
| 2. DATABASE CONNECTION
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../../config/database.php';


/*
|--------------------------------------------------------------------------
| 3. PAGE CONFIGURATION
|--------------------------------------------------------------------------
*/

$activePage = 'aid-requests';

$errors = [];


/*
|--------------------------------------------------------------------------
| 4. LOAD AID REQUESTS
|--------------------------------------------------------------------------
|
| This page displays aid requests submitted by Social Service Officers.
|
| Draft requests are excluded.
|
| Requests are ordered:
|
| 1. Pending
| 2. Approved
| 3. Rejected
| 4. Other statuses
|
|--------------------------------------------------------------------------
*/

try {

    $rows = database()->query(
        "SELECT
            ar.*,

            b.full_name,
            b.nic,
            b.elders_card_number,
            b.date_of_birth,
            b.address,

            d.name AS district_name,
            ds.name AS division_name,

            i.item_name,
            i.variety,

            u.full_name AS sso_name

         FROM aid_requests ar

         JOIN beneficiaries b
            ON b.id = ar.beneficiary_id

         JOIN districts d
            ON d.id = b.district_id

         JOIN ds_divisions ds
            ON ds.id = b.ds_division_id

         JOIN inventory_items i
            ON i.id = ar.item_id

         JOIN users u
            ON u.id = ar.submitted_by

         WHERE ar.status <> 'draft'

           AND u.role = 'social-service-officer'

         ORDER BY

            CASE ar.status

                WHEN 'pending' THEN 0

                WHEN 'approved' THEN 1

                WHEN 'rejected' THEN 2

                ELSE 3

            END,

            ar.id DESC"
    )->fetchAll();

} catch (PDOException $e) {

    /*
    |--------------------------------------------------------------------------
    | Database Error
    |--------------------------------------------------------------------------
    */

    error_log(
        $e->getMessage()
    );

    $rows = [];

    $errors[] =
        'Aid requests are unavailable.';
}


/*
|--------------------------------------------------------------------------
| 5. CALCULATE BENEFICIARY AGE
|--------------------------------------------------------------------------
|
| Receives the beneficiary date of birth and returns their current age.
|--------------------------------------------------------------------------
*/

function monitorAge(string $dob): int
{
    return (int) (
        new DateTimeImmutable($dob)
    )
        ->diff(
            new DateTimeImmutable('today')
        )
        ->y;
}

?>


<!doctype html>

<html>

<head>

    <meta charset="utf-8">


    <meta
        name="viewport"
        content="width=device-width,initial-scale=1"
    >


    <title>
        Aid Requests Monitor
    </title>


    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- WIDMS Main CSS -->

    <link
        href="assets/css/admin-dashboard.css?v=4"
        rel="stylesheet"
    >

</head>


<body>


<?php

/*
|--------------------------------------------------------------------------
| 6. SUBJECT OFFICER SIDEBAR
|--------------------------------------------------------------------------
*/

require __DIR__ .
    '/../../includes/subject-officer-sidebar.php';

?>


<div class="admin-shell">


    <!-- ================================================================
         TOP BAR
    ================================================================= -->
    <header class="topbar">
        <div class="d-flex align-items-center gap-3">


            <!-- Mobile Menu Button -->

            <button
                class="menu-button"
                id="menu-button"
            >
                &#9776;
            </button>
            <h1>Item Request</h1>

        </div>
    </header> 



    <!-- ================================================================
         MAIN PAGE CONTENT
    ================================================================= -->

    <main class="dashboard-content">


        <!-- ============================================================
             ERROR MESSAGE
        ============================================================= -->

        <?php if ($errors): ?>

            <div class="alert alert-danger">

                <?= htmlspecialchars(
                    implode(' ', $errors)
                ) ?>

            </div>

        <?php endif; ?>



        <!-- ============================================================
             REQUESTS FROM SOCIAL SERVICE OFFICERS
        ============================================================= -->

        <section class="submitted-requests-card">


            <!-- ========================================================
                 TABLE HEADER / FILTER AREA
            ========================================================= -->

            <div class="submitted-header">


                <div>

                    <h2>
                        Requests from Social Service Officers
                    </h2>

                    <small>
                        Read-only — only Admin can approve or reject
                    </small>

                </div>


                <div>


                    <!-- Search requests -->

                    <input
                        id="monitor-search"
                        type="search"
                        placeholder="Search name, NIC or SSO..."
                    >


                    <!-- Filter by request status -->

                    <select id="monitor-status">

                        <option value="">
                            All Status
                        </option>

                        <option value="pending">
                            Pending Admin
                        </option>

                        <option value="approved">
                            Approved
                        </option>

                        <option value="rejected">
                            Rejected
                        </option>

                        <option value="goods-requested">
                            Goods Requested
                        </option>

                        <option value="distributed">
                            Distributed
                        </option>

                    </select>


                </div>


            </div>



            <!-- ========================================================
                 REQUEST TABLE
            ========================================================= -->

            <!-- The legend explains the same approval symbols used by every request review role. -->
            <!-- Match the SSO approval guide so every reviewing role sees one responsive design. -->
            <div class="approval-legend approval-legend-standalone" aria-label="<?= htmlspecialchars(t('Approval icon meanings'), ENT_QUOTES, 'UTF-8') ?>">
                <strong class="approval-legend-title"><?= htmlspecialchars(t('Approval guide'), ENT_QUOTES, 'UTF-8') ?></strong>
                <span>🩺 <?= htmlspecialchars(t('Government Medical Officer'), ENT_QUOTES, 'UTF-8') ?></span><span>🏡 <?= htmlspecialchars(t('Grama Niladhari'), ENT_QUOTES, 'UTF-8') ?></span>
                <span>🏛 <?= htmlspecialchars(t('Social Services Officer'), ENT_QUOTES, 'UTF-8') ?></span><span>📋 <?= htmlspecialchars(t('Divisional Secretary'), ENT_QUOTES, 'UTF-8') ?></span><span>❌ <?= htmlspecialchars(t('Not approved'), ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <div class="submitted-table-wrap">

                <table
                    class="submitted-table request-review-table"
                    id="monitor-table"
                >

                    <thead>

                        <tr>
                            <th>ID</th>
                            <th>Beneficiary</th>
                            <th>NIC</th>
                            <th>Elders' Identity Card</th>
                            <th>Age</th>
                            <th>Address</th>
                            <th>District</th>
                            <th>DS Division</th>
                            <th>Aid Requested</th>
                            <th>Beneficiary Information</th>
                            <th>Approvals</th>
                            <th>Submitted By</th>
                            <th>Status</th>
                            <th>Admin Note</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if (!$rows): ?>

                        <tr>
                            <td colspan="14">
                                No submitted aid requests.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($rows as $r): ?>

                            <tr
                                data-status="<?= htmlspecialchars(
                                    $r['status']
                                ) ?>"
                            >

                                <td>
                                    AR-<?= str_pad(
                                        (string) $r['id'],
                                        4,
                                        '0',
                                        STR_PAD_LEFT
                                    ) ?>
                                </td>


                                <!-- Beneficiary name only -->
                                <td class="request-beneficiary-name">

                                    <strong>
                                        <?= htmlspecialchars(
                                            $r['full_name']
                                        ) ?>
                                    </strong>

                                </td>


                                <!-- NIC -->
                                <td>

                                    <?= htmlspecialchars(
                                        $r['nic'] ?: '—'
                                    ) ?>

                                </td>


                                <!-- Elders' Identity Card -->
                                <td>

                                    <?= htmlspecialchars(
                                        $r['elders_card_number'] ?: '—'
                                    ) ?>

                                </td>


                                <!-- Age -->
                                <td>

                                    <?= monitorAge(
                                        $r['date_of_birth']
                                    ) ?>

                                </td>


                                <!-- Address is now separate -->
                                <td class="request-address">

                                    <?= htmlspecialchars(
                                        $r['address']
                                    ) ?>

                                </td>


                                <!-- District -->
                                <td>

                                    <?= htmlspecialchars(
                                        $r['district_name']
                                    ) ?>

                                </td>


                                <!-- DS Division -->
                                <td>

                                    <?= htmlspecialchars(
                                        $r['division_name']
                                    ) ?>

                                </td>


                                <!-- Aid -->
                                <td>

                                    <?= htmlspecialchars(
                                        $r['item_name'] .
                                        (
                                            $r['variety']
                                                ? ' — ' . $r['variety']
                                                : ''
                                        )
                                    ) ?>

                                </td>


                                <!-- The submitted field name/value is configured by the Subject Officer for this item. -->
                                <td>

                                    <?php if (!empty($r['beneficiary_detail_label']) && $r['beneficiary_detail_value'] !== null): ?>
                                        <small class="request-beneficiary-detail"><?= htmlspecialchars($r['beneficiary_detail_label'], ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars($r['beneficiary_detail_value'], ENT_QUOTES, 'UTF-8') ?></small>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>

                                </td>


                                <!-- Official approvals -->
                                <td>

                                    <span class="approval-icon-set" title="Official approvals">
                                        <?= $r['medical_officer_approved'] ? '🩺' : '❌' ?>
                                        <?= $r['grama_niladhari_approved'] ? '🏡' : '❌' ?>
                                        <?= $r['social_services_approved'] ? '🏛' : '❌' ?>
                                        <?= $r['divisional_secretary_approved'] ? '📋' : '❌' ?>
                                    </span>

                                </td>


                                <!-- SSO -->
                                <td>

                                    <?= htmlspecialchars(
                                        $r['sso_name']
                                    ) ?>

                                </td>


                                <!-- Status -->
                                <td>

                                    <span
                                        class="request-status-pill
                                        status-<?= htmlspecialchars(
                                            $r['status']
                                        ) ?>"
                                    >

                                        <?= ucwords(
                                            str_replace(
                                                '-',
                                                ' ',
                                                $r['status']
                                            )
                                        ) ?>

                                    </span>

                                </td>


                                <!-- Admin Note -->
                                <td>

                                    <?= htmlspecialchars(
                                        $r['rejection_reason']
                                            ?: '—'
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



<!-- ==================================================================
     WIDMS SHARED JAVASCRIPT
=================================================================== -->

<script src="assets/js/admin-dashboard.js"></script>



<script>

/*
|--------------------------------------------------------------------------
| 7. TABLE SEARCH AND STATUS FILTER
|--------------------------------------------------------------------------
|
| Filters the table using:
|
| - Search text
| - Request status
|
|--------------------------------------------------------------------------
*/


const search =
    document.getElementById(
        'monitor-search'
    )


const status =
    document.getElementById(
        'monitor-status'
    )


const rows = [
    ...document.querySelectorAll(
        '#monitor-table tbody tr[data-status]'
    )
]



/*
|--------------------------------------------------------------------------
| Filter Request Rows
|--------------------------------------------------------------------------
*/

function filterRows() {

    /*
     * Search value entered by the user.
     */
    const term =
        (search.value || '')
            .toLowerCase()


    /*
     * Selected request status.
     */
    const state =
        status.value


    /*
     * Check every table row.
     */
    rows.forEach(row => {

        /*
         * Display the row only when:
         *
         * 1. The row contains the search text.
         *
         * AND
         *
         * 2. The selected status matches
         *    the row's request status.
         */

        row.hidden = !(

            row.textContent
                .toLowerCase()
                .includes(term)

            &&

            (
                !state ||
                row.dataset.status === state
            )

        )

    })
}



/*
|--------------------------------------------------------------------------
| Search Event
|--------------------------------------------------------------------------
*/

search.addEventListener(
    'input',
    filterRows
)



/*
|--------------------------------------------------------------------------
| Status Filter Event
|--------------------------------------------------------------------------
*/

status.addEventListener(
    'change',
    filterRows
)

</script>


</body>

</html>
