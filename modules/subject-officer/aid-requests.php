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
require_once __DIR__ . '/../../includes/aid-request-details.php';


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

            u.username AS submitter_username,
            u.role AS submitter_role,
            CASE
                WHEN COALESCE(ret.returned_quantity, 0) > 0 THEN 'return'
                WHEN dist.distribution_type = 'direct' THEN 'direct-distribution'
                ELSE ar.status
            END AS display_status

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

         LEFT JOIN distributions dist
            ON dist.aid_request_id = ar.id

         LEFT JOIN (
            SELECT distribution_id, SUM(quantity) AS returned_quantity
            FROM item_returns
            GROUP BY distribution_id
         ) ret ON ret.distribution_id = dist.id

         WHERE ar.status <> 'draft'

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

function monitorAge(?string $dob): string
{
    if ($dob === null || $dob === '') {
        return '—';
    }
    return (string) (int) (
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
        href="assets/css/admin-dashboard.css?v=69"
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
            <h1>Aid Request</h1>

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
                        <?= htmlspecialchars(t('All Aid Requests'), ENT_QUOTES, 'UTF-8') ?>
                    </h2>

                    <small>
                        <?= htmlspecialchars(t('Includes SSO, Subject Officer, and Admin requests. Only Admin can approve or reject.'), ENT_QUOTES, 'UTF-8') ?>
                    </small>

                </div>


                <div>


                    <!-- Search requests -->

                    <input
                        id="monitor-search"
                        type="search"
                        placeholder="<?= htmlspecialchars(t('Search name, identification or submitter...'), ENT_QUOTES, 'UTF-8') ?>"
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

                        <option value="direct-distribution">
                            <?= htmlspecialchars(t('Direct Distribution'), ENT_QUOTES, 'UTF-8') ?>
                        </option>

                        <option value="return">
                            <?= htmlspecialchars(t('Return'), ENT_QUOTES, 'UTF-8') ?>
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

                <div class="identification-guide" aria-label="<?= htmlspecialchars(t('Identification guide'), ENT_QUOTES, 'UTF-8') ?>">
                    <strong><?= htmlspecialchars(t('Identification guide'), ENT_QUOTES, 'UTF-8') ?></strong>
                    <span class="identification-guide-nic">NIC</span>
                    <span class="identification-guide-elder"><?= htmlspecialchars(t("Elders' Identity Card"), ENT_QUOTES, 'UTF-8') ?></span>
                </div>

                <table
                    class="submitted-table request-review-table aid-request-list-table"
                    id="monitor-table"
                >

                    <thead>

                        <tr>
                            <th>ID</th>
                            <th>Beneficiary</th>
                            <th><?= htmlspecialchars(t('Identification'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th>Age</th>
                            <th>Address</th>
                            <th>District</th>
                            <th>DS Division</th>
                            <th>Aid Requested</th>
                            <th>Approvals</th>
                            <th>Submitted By</th>
                            <th>Status</th>
                            <th>Admin Note</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if (!$rows): ?>

                        <tr>
                            <td colspan="12">
                                No submitted aid requests.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($rows as $r): ?>

                            <tr id="aid-request-<?= (int) $r['id'] ?>" class="admin-notification-target aid-review-row<?= in_array($r['status'], ['approved', 'rejected'], true) ? ' is-' . htmlspecialchars($r['status'], ENT_QUOTES, 'UTF-8') : '' ?>"
                                tabindex="-1"
                                data-status="<?= htmlspecialchars(
                                    $r['display_status']
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


                                <!-- NIC appears first and Elder's ID second when both exist. -->
                                <td class="request-identification-cell">
                                    <?php if (!empty($r['nic'])): ?>
                                        <span class="request-identification-value identification-nic"><small>NIC</small><?= htmlspecialchars($r['nic'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($r['elders_card_number'])): ?>
                                        <span class="request-identification-value identification-elder"><small><?= htmlspecialchars(t("Elders' ID"), ENT_QUOTES, 'UTF-8') ?></small><?= htmlspecialchars($r['elders_card_number'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                    <?php if (empty($r['nic']) && empty($r['elders_card_number'])): ?>—<?php endif; ?>
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
                                <td class="request-aid-cell">

                                    <div class="request-aid-content">

                                    <strong class="request-aid-name">

                                    <?= htmlspecialchars(
                                        $r['item_name'] .
                                        (
                                            $r['variety']
                                                ? ' — ' . $r['variety']
                                                : ''
                                        )
                                    ) ?>

                                    </strong>

                                    <!-- Match the SSO layout by keeping details beneath the requested item. -->
                                    <?php $requestDetails=aidRequestDetails($r);if($requestDetails): ?>
                                        <button type="button" class="request-extra-info-button" data-request-extra-info="<?= htmlspecialchars(json_encode($requestDetails,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),ENT_QUOTES,'UTF-8') ?>" data-dialog-title="<?= htmlspecialchars(t('Beneficiary Details'),ENT_QUOTES,'UTF-8') ?>" data-close-label="<?= htmlspecialchars(t('Close'),ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars(t('View details'),ENT_QUOTES,'UTF-8') ?></button>
                                    <?php endif; ?>

                                    </div>

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


                                <!-- Submitter account and post -->
                                <td class="request-submitter-cell">

                                    <?= htmlspecialchars(
                                        $r['submitter_username'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                    <small class="request-submitter-role"><?= htmlspecialchars(t(match ($r['submitter_role']) {
                                        'admin' => 'Administrator',
                                        'subject-officer' => 'Subject Officer',
                                        default => 'Social Service Officer',
                                    }), ENT_QUOTES, 'UTF-8') ?></small>

                                </td>


                                <!-- Status -->
                                <td>

                                    <span
                                        class="request-status-pill
                                        status-<?= htmlspecialchars(
                                            $r['display_status']
                                        ) ?>"
                                    >

                                        <?= htmlspecialchars(t(ucwords(str_replace('-', ' ', $r['display_status']))), ENT_QUOTES, 'UTF-8') ?>

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

<script src="assets/js/admin-dashboard.js?v=17"></script>



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
