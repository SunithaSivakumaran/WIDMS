<?php

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| 1. ACCESS CONTROL
|--------------------------------------------------------------------------
| Only users with the Admin role can access this page.
|--------------------------------------------------------------------------
*/

requireRole('admin');


/*
|--------------------------------------------------------------------------
| 2. REQUIRED FILES
|--------------------------------------------------------------------------
| Database connection
| Activity logging
| Beneficiary eligibility checking
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/activity.php';
require_once __DIR__ . '/../../includes/eligibility.php';


/*
|--------------------------------------------------------------------------
| 3. PAGE CONFIGURATION
|--------------------------------------------------------------------------
*/

$activePage = 'item-requests';

$errors = [];

$success = (string) (
    $_SESSION['flash_success'] ?? ''
);

unset($_SESSION['flash_success']);


/*
|--------------------------------------------------------------------------
| 4. DATABASE CONNECTION
|--------------------------------------------------------------------------
*/

$db = database();


/*
|--------------------------------------------------------------------------
| 5. CHECK OFFICIAL SIGN-OFFS BEFORE APPROVAL
|--------------------------------------------------------------------------
|
| This check runs only when:
|
| - The request method is POST
| - Admin clicked the Approve button
|
| All four official sign-offs must be completed before Admin approval:
|
| 1. Government Medical Officer
| 2. Grama Niladhari
| 3. Social Services Officer
| 4. Divisional Secretary
|
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    ($_POST['decision'] ?? '') === 'approve'
) {

    $signoff = $db->prepare(
        'SELECT
            medical_officer_approved
            + grama_niladhari_approved
            + social_services_approved
            + divisional_secretary_approved

         FROM aid_requests

         WHERE id=:id'
    );


    $signoff->execute([
        'id' => (int) (
            $_POST['request_id'] ?? 0
        )
    ]);


    if (
        (int) $signoff->fetchColumn() !== 4
    ) {

        $errors[] =
            'All four official sign-offs are required before Admin approval.';
    }
}


/*
|--------------------------------------------------------------------------
| 6. PROCESS ADMIN DECISION
|--------------------------------------------------------------------------
|
| Handles:
|
| - Approve
| - Reject
|
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    /*
    |--------------------------------------------------------------------------
    | Get submitted values
    |--------------------------------------------------------------------------
    */

    $id = filter_input(
        INPUT_POST,
        'request_id',
        FILTER_VALIDATE_INT
    );


    $decision = (string) (
        $_POST['decision'] ?? ''
    );


    $reason = trim(
        (string) (
            $_POST['rejection_reason'] ?? ''
        )
    );


    /*
    |--------------------------------------------------------------------------
    | Validate CSRF token
    |--------------------------------------------------------------------------
    */

    if (
        !verifyCsrfToken(
            (string) (
                $_POST['csrf_token'] ?? ''
            )
        )
    ) {

        $errors[] =
            'Your session expired.';
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Request ID and Decision
    |--------------------------------------------------------------------------
    */

    if (
        !$id
        ||
        !in_array(
            $decision,
            [
                'approve',
                'reject'
            ],
            true
        )
    ) {

        $errors[] =
            'Invalid decision.';
    }


    /*
    |--------------------------------------------------------------------------
    | Rejection requires a reason
    |--------------------------------------------------------------------------
    */

    if (
        $decision === 'reject'
        &&
        $reason === ''
    ) {

        $errors[] =
            'A rejection reason is required.';
    }


    /*
    |--------------------------------------------------------------------------
    | Continue only when validation passed
    |--------------------------------------------------------------------------
    */

    if (!$errors) {

        try {


            /*
            |--------------------------------------------------------------------------
            | Begin Database Transaction
            |--------------------------------------------------------------------------
            */

            $db->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | Lock and Load Pending Aid Request
            |--------------------------------------------------------------------------
            |
            | FOR UPDATE prevents another process from modifying
            | this request while Admin is reviewing it.
            |--------------------------------------------------------------------------
            */

            $stmt = $db->prepare(
                "SELECT *
                 FROM aid_requests
                 WHERE id=:id
                 AND status='pending'
                 FOR UPDATE"
            );


            $stmt->execute([
                'id' => $id
            ]);


            $request = $stmt->fetch();


            /*
            |--------------------------------------------------------------------------
            | Make sure request is still pending
            |--------------------------------------------------------------------------
            */

            if (!$request) {

                throw new RuntimeException(
                    'This request is no longer pending.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Re-check Beneficiary Eligibility Before Approval
            |--------------------------------------------------------------------------
            |
            | Eligibility is checked only when Admin approves.
            |--------------------------------------------------------------------------
            */

            if ($decision === 'approve') {

                $eligibility =
                    beneficiaryEligibility(
                        $db,
                        (int) $request[
                            'beneficiary_id'
                        ],
                        (int) $request[
                            'item_id'
                        ]
                    );


                if (
                    !$eligibility['eligible']
                ) {

                    throw new RuntimeException(
                        $eligibility['reason']
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Determine New Request Status
            |--------------------------------------------------------------------------
            */

            $status =
                $decision === 'approve'
                    ? 'approved'
                    : 'rejected';


            /*
            |--------------------------------------------------------------------------
            | Update Aid Request
            |--------------------------------------------------------------------------
            |
            | Stores:
            |
            | - New status
            | - Rejection reason when rejected
            | - Admin who reviewed it
            | - Review date/time
            |--------------------------------------------------------------------------
            */

            $update = $db->prepare(
                'UPDATE aid_requests

                 SET
                    status=:status,
                    rejection_reason=:reason,
                    reviewed_by=:admin,
                    reviewed_at=NOW()

                 WHERE id=:id'
            );


            $update->execute([

                'status' =>
                    $status,

                'reason' =>
                    $decision === 'reject'
                        ? $reason
                        : null,

                'admin' =>
                    $_SESSION['user_id'],

                'id' =>
                    $id
            ]);


            /*
            |--------------------------------------------------------------------------
            | Commit Transaction
            |--------------------------------------------------------------------------
            */

            $db->commit();


            /*
            |--------------------------------------------------------------------------
            | Write Activity Log
            |--------------------------------------------------------------------------
            */

            logActivity(
                'Aid Requests',
                ucfirst($status) .
                    ' aid request',
                'AR-' .
                    str_pad(
                        (string) $id,
                        4,
                        '0',
                        STR_PAD_LEFT
                    ),
                $status
            );


            /*
            |--------------------------------------------------------------------------
            | Success Message
            |--------------------------------------------------------------------------
            */

            $_SESSION['flash_success'] =
                'Aid request ' .
                $status .
                '.';


            /*
            |--------------------------------------------------------------------------
            | Clear CSRF Token
            |--------------------------------------------------------------------------
            */

            unset(
                $_SESSION['csrf_token']
            );


            /*
            |--------------------------------------------------------------------------
            | Redirect Back to Item Requests
            |--------------------------------------------------------------------------
            */

            header(
                'Location: dashboard.php?page=item-requests'
            );

            exit;


        } catch (Throwable $e) {


            /*
            |--------------------------------------------------------------------------
            | Roll Back Transaction
            |--------------------------------------------------------------------------
            */

            if ($db->inTransaction()) {

                $db->rollBack();
            }


            /*
            |--------------------------------------------------------------------------
            | Log Internal Error
            |--------------------------------------------------------------------------
            */

            error_log(
                $e->getMessage()
            );


            /*
            |--------------------------------------------------------------------------
            | Display Appropriate Error
            |--------------------------------------------------------------------------
            */

            $errors[] =
                $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'Unable to review aid request.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| 7. LOAD ALL AID DISTRIBUTION REQUESTS
|--------------------------------------------------------------------------
|
| Loads all non-draft requests.
|
| Also loads:
|
| - Beneficiary name
| - NIC
| - Age
| - District
| - DS Division
| - Aid item
| - Item variety
| - Submitter
| - Reviewer
|
| Ordering:
|
| 1. Pending
| 2. Approved
| 3. Everything else
|
|--------------------------------------------------------------------------
*/

try {

    $rows = $db->query(
        'SELECT
            ar.*,

            b.full_name,
            b.nic,
            b.elders_card_number,
            b.address,

            TIMESTAMPDIFF(
                YEAR,
                b.date_of_birth,
                CURDATE()
            ) AS age,

            d.name AS district_name,
            ds.name AS division_name,

            i.item_name,
            i.variety,

            u.full_name AS submitter_name,
            r.full_name AS reviewer_name

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

         LEFT JOIN users r
            ON r.id = ar.reviewed_by

         WHERE ar.status <> "draft"

         ORDER BY
            CASE ar.status
                WHEN "pending" THEN 0
                WHEN "approved" THEN 1
                ELSE 2
            END,
            ar.id DESC'
    )->fetchAll();

} catch (PDOException $e) {

    error_log($e->getMessage());

    $rows = [];

    $errors[] =
        'Aid requests are unavailable.';
}

?>


<!doctype html>

<html lang="en">

<head>

    <meta charset="utf-8">


    <meta
        name="viewport"
        content="width=device-width,initial-scale=1"
    >


    <title>
        Item Requests | WIDMS
    </title>


    <!-- Bootstrap CSS -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- WIDMS Main CSS -->

    <link
        href="assets/css/admin-dashboard.css"
        rel="stylesheet"
    >

</head>


<body>


<?php

/*
|--------------------------------------------------------------------------
| 8. ADMIN SIDEBAR
|--------------------------------------------------------------------------
*/

require __DIR__ .
    '/../../includes/admin-sidebar.php';

?>


<div class="admin-shell">


    <!-- ================================================================
         TOP BAR
    ================================================================= -->

    <header class="topbar">


        <div
            class="d-flex align-items-center gap-3"
        >


            <!-- Mobile Menu Button -->

            <button
                class="menu-button"
                id="menu-button"
            >
                &#9776;
            </button>


            <h1>
                Item Requests
            </h1>


        </div>


    </header>



    <!-- ================================================================
         MAIN PAGE CONTENT
    ================================================================= -->

    <main
        class="dashboard-content admin-operation-page"
    >


        <!-- ============================================================
             SUCCESS MESSAGE
        ============================================================= -->

        <?php if ($success): ?>

            <div class="alert alert-success">

                <?= htmlspecialchars(
                    $success,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

        <?php endif; ?>



        <!-- ============================================================
             ERROR MESSAGE
        ============================================================= -->

        <?php if ($errors): ?>

            <div class="alert alert-danger">

                <?= htmlspecialchars(
                    implode(' ', $errors),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

        <?php endif; ?>



        <!-- ============================================================
             AID DISTRIBUTION REQUESTS
        ============================================================= -->

        <section class="admin-data-card">


            <!-- ========================================================
                 CARD HEADER
            ========================================================= -->

            <div class="admin-data-header">

                <h2>
                    All Aid Distribution Requests
                </h2>

            </div>



            <!-- ========================================================
                 TABLE CONTAINER
            ========================================================= -->

            <div class="admin-data-table-wrap">


                <table
                    class="admin-data-table item-requests-table"
                >


                    <!-- =================================================
                         TABLE HEADINGS
                    ================================================== -->

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

                            <th>Approvals</th>

                            <th>Submitted By</th>

                            <th>Date</th>

                            <th>Status</th>

                            <th>Action</th>
                        </tr>

                    </thead>



                    <!-- =================================================
                         TABLE BODY
                    ================================================== -->

                    <tbody>


                    <?php if (!$rows): ?>


                        <!-- No requests available -->

                        <tr>

                            <td
                                colspan="14"
                                class="admin-empty-row"
                            >

                                No aid distribution requests available.

                            </td>

                        </tr>


                    <?php else: ?>


                        <?php foreach ($rows as $r): ?>


                            <tr>
                            <td>
                                AR-<?= str_pad(
                                    (string) $r['id'],
                                    4,
                                    '0',
                                    STR_PAD_LEFT
                                ) ?>
                            </td>


                            <td class="request-beneficiary-name">
                                <strong>
                                    <?= htmlspecialchars(
                                        $r['full_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </strong>
                            </td>


                            <td>
                                <?= htmlspecialchars(
                                    $r['nic'] ?: '—',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>


                            <td>
                                <?= htmlspecialchars(
                                    $r['elders_card_number'] ?: '—',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>


                            <td>
                                <?= (int) $r['age'] ?>
                            </td>


                            <td class="request-address">
                                <?= htmlspecialchars(
                                    $r['address'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>


                            <td>
                                <?= htmlspecialchars(
                                    $r['district_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>


                            <td>
                                <?= htmlspecialchars(
                                    $r['division_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>


                            <td>
                                <?= htmlspecialchars(
                                    $r['item_name'] .
                                    (
                                        $r['variety']
                                            ? ' — ' . $r['variety']
                                            : ''
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                                × <?= (int) $r['quantity'] ?>
                            </td>


                            <td>
                                <?= array_sum([
                                    (int) $r['medical_officer_approved'],
                                    (int) $r['grama_niladhari_approved'],
                                    (int) $r['social_services_approved'],
                                    (int) $r['divisional_secretary_approved']
                                ]) ?> / 4
                            </td>


                            <td>
                                <?= htmlspecialchars(
                                    $r['submitter_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>


                            <td>
                                <?= date(
                                    'd M Y',
                                    strtotime($r['created_at'])
                                ) ?>
                            </td>


                            <td>
                                <span
                                    class="request-status-pill
                                    status-<?= htmlspecialchars(
                                        $r['status'],
                                        ENT_QUOTES,
                                        'UTF-8'
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


                            <td>

                                <?php if ($r['status'] === 'pending'): ?>

                                    <form
                                        method="post"
                                        class="goods-decision-form"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= htmlspecialchars(
                                                csrfToken(),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="request_id"
                                            value="<?= (int) $r['id'] ?>"
                                        >

                                        <input
                                            name="rejection_reason"
                                            placeholder="Reason for rejection"
                                        >

                                        <button
                                            name="decision"
                                            value="approve"
                                            class="approve-button"
                                        >
                                            Approve
                                        </button>

                                        <button
                                            name="decision"
                                            value="reject"
                                            class="reject-button"
                                        >
                                            Reject
                                        </button>

                                    </form>

                                <?php else: ?>

                                    <?= htmlspecialchars(
                                        $r['rejection_reason'] ?: '—',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                <?php endif; ?>

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


</body>

</html>