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
require_once __DIR__ . '/../../includes/admin-approval-tabs.php';


/*
|--------------------------------------------------------------------------
| 3. PAGE CONFIGURATION
|--------------------------------------------------------------------------
*/

$pendingView = (string) ($_GET['view'] ?? '') === 'pending';
$activePage = $pendingView ? 'pending-approvals' : 'item-requests';

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
| 5. PROCESS ADMIN DECISION
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
                        ],
                        (int) $request['id']
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

            header('Location: dashboard.php?page=item-requests' . ($pendingView ? '&view=pending' : ''));

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

$rows = array_values(array_filter(
    $rows,
    static fn(array $row): bool => $pendingView
        ? ($row['status'] ?? '') === 'pending'
        : !in_array(($row['status'] ?? ''), ['pending', 'draft'], true)
));
$pendingItemRequests = (int) $db->query("SELECT COUNT(*) FROM aid_requests WHERE status='pending'")->fetchColumn();
$pendingUserRegistrations = 0;
$pendingStockReleases = 0;
$pendingCorrectionRequests = 0;
try {
    $pendingUserRegistrations = (int) $db
        ->query("SELECT COUNT(*) FROM registration_requests WHERE status='pending'")
        ->fetchColumn();
    $pendingStockReleases = (int) $db
        ->query("SELECT COUNT(*) FROM goods_requests WHERE status='pending-admin-approval'")
        ->fetchColumn();
    $pendingCorrectionRequests = (int) $db
        ->query("SELECT COUNT(*) FROM correction_requests WHERE status='pending'")
        ->fetchColumn();
} catch (PDOException $e) {
    error_log($e->getMessage());
}

?>


<!doctype html>

<html lang="<?= htmlspecialchars(widmsLanguage(), ENT_QUOTES, 'UTF-8') ?>">

<head>

    <meta charset="utf-8">


    <meta
        name="viewport"
        content="width=device-width,initial-scale=1"
    >


    <title><?= htmlspecialchars(t($pendingView ? 'Pending Aid Requests' : 'Reviewed Aid Requests'), ENT_QUOTES, 'UTF-8') ?> | WIDMS</title>


    <!-- Bootstrap CSS -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- WIDMS Main CSS -->

    <link
        href="assets/css/admin-dashboard.css?v=65"
        rel="stylesheet"
    >

</head>


<body<?= $pendingView ? ' class="admin-aid-pending-page"' : '' ?>>


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


            <h1><?= htmlspecialchars(t($pendingView ? 'Pending Aid Requests' : 'Reviewed Aid Requests'), ENT_QUOTES, 'UTF-8') ?></h1>


        </div>


    </header>



    <!-- ================================================================
         MAIN PAGE CONTENT
    ================================================================= -->

    <main
        class="dashboard-content admin-operation-page admin-item-requests-page<?= $pendingView ? ' admin-correction-review-page aid-request-card-page' : '' ?>"
    >


        <!-- ============================================================
             SUCCESS MESSAGE
        ============================================================= -->

        <?php renderSuccessMessage($success); ?>

        <?php if ($pendingView): ?>
        <?php renderAdminApprovalTabs([
            'registrations' => $pendingUserRegistrations,
            'aid' => $pendingItemRequests,
            'stock' => $pendingStockReleases,
            'corrections' => $pendingCorrectionRequests,
        ], 'aid'); ?>
        <?php endif; ?>



        <!-- ============================================================
             ERROR MESSAGE
        ============================================================= -->

        <?php if ($errors): ?>

            <div class="alert alert-danger" role="alert">

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

        <?php if ($pendingView): ?>
        <section class="admin-data-card admin-correction-review-card" aria-label="<?= htmlspecialchars(t('Pending aid requests'), ENT_QUOTES, 'UTF-8') ?>">
            <div class="admin-correction-list">
                <?php if (!$rows): ?>
                    <div class="empty-corrections"><strong><?= htmlspecialchars(t('No pending aid requests'), ENT_QUOTES, 'UTF-8') ?></strong><span><?= htmlspecialchars(t('New aid requests will appear here for approval.'), ENT_QUOTES, 'UTF-8') ?></span></div>
                <?php else: foreach ($rows as $r): ?>
                    <?php
                    $requestDetails = json_decode((string) ($r['beneficiary_details_json'] ?? ''), true);
                    if ((!is_array($requestDetails) || !$requestDetails) && !empty($r['beneficiary_detail_label']) && $r['beneficiary_detail_value'] !== null) {
                        $requestDetails = [['label' => $r['beneficiary_detail_label'], 'type' => 'text', 'value' => $r['beneficiary_detail_value'], 'display_value' => $r['beneficiary_detail_value']]];
                    }
                    $identification = !empty($r['nic'])
                        ? 'NIC · ' . $r['nic']
                        : (!empty($r['elders_card_number']) ? t("Elders' ID") . ' · ' . $r['elders_card_number'] : '—');
                    $aidLabel = $r['item_name'] . ($r['variety'] ? ' — ' . $r['variety'] : '') . ' × ' . (int) $r['quantity'];
                    ?>
                    <article id="aid-request-<?= (int) $r['id'] ?>" class="admin-correction-item admin-notification-target" tabindex="-1">
                        <div class="correction-summary">
                            <div>
                                <div class="correction-reference-line">
                                    <strong>AR-<?= str_pad((string) $r['id'], 4, '0', STR_PAD_LEFT) ?></strong>
                                    <span><?= htmlspecialchars($r['full_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <p class="correction-submission-meta"><?= htmlspecialchars(t('Submitted by'), ENT_QUOTES, 'UTF-8') ?> <strong><?= htmlspecialchars($r['submitter_name'], ENT_QUOTES, 'UTF-8') ?></strong> · <?= date('d-m-Y, H:i', strtotime($r['created_at'])) ?></p>
                            </div>
                            <span class="correction-status pending"><?= htmlspecialchars(t('Pending'), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <dl class="correction-details aid-request-card-details">
                            <div class="aid-card-requested"><dt><?= htmlspecialchars(t('Aid Requested'), ENT_QUOTES, 'UTF-8') ?></dt><dd><?= htmlspecialchars($aidLabel, ENT_QUOTES, 'UTF-8') ?><?php if (is_array($requestDetails) && $requestDetails): ?><button type="button" class="request-extra-info-button" data-request-extra-info="<?= htmlspecialchars(json_encode($requestDetails, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?>" data-dialog-title="<?= htmlspecialchars(t('Beneficiary Details'), ENT_QUOTES, 'UTF-8') ?>" data-close-label="<?= htmlspecialchars(t('Close'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t('View details'), ENT_QUOTES, 'UTF-8') ?></button><?php endif; ?></dd></div>
                            <div><dt><?= htmlspecialchars(t('Identification'), ENT_QUOTES, 'UTF-8') ?></dt><dd><?= htmlspecialchars($identification, ENT_QUOTES, 'UTF-8') ?></dd></div>
                            <div><dt><?= htmlspecialchars(t('Age'), ENT_QUOTES, 'UTF-8') ?></dt><dd><?= (int) $r['age'] ?></dd></div>
                            <div><dt><?= htmlspecialchars(t('District'), ENT_QUOTES, 'UTF-8') ?></dt><dd><?= htmlspecialchars($r['district_name'], ENT_QUOTES, 'UTF-8') ?></dd></div>
                            <div><dt><?= htmlspecialchars(t('DS Division'), ENT_QUOTES, 'UTF-8') ?></dt><dd><?= htmlspecialchars($r['division_name'], ENT_QUOTES, 'UTF-8') ?></dd></div>
                            <div class="aid-card-address"><dt><?= htmlspecialchars(t('Address'), ENT_QUOTES, 'UTF-8') ?></dt><dd><?= htmlspecialchars($r['address'], ENT_QUOTES, 'UTF-8') ?></dd></div>
                            <div class="aid-card-approvals">
                                <dt><?= htmlspecialchars(t('Official Approvals'), ENT_QUOTES, 'UTF-8') ?></dt>
                                <dd>
                                    <ul class="aid-card-approval-list" aria-label="<?= htmlspecialchars(t('Official approval status'), ENT_QUOTES, 'UTF-8') ?>">
                                        <?php foreach ([
                                            ['medical_officer_approved', '🩺', 'Government Medical Officer'],
                                            ['grama_niladhari_approved', '🏡', 'Grama Niladhari'],
                                            ['social_services_approved', '🏛', 'Social Services Officer'],
                                            ['divisional_secretary_approved', '📋', 'Divisional Secretary'],
                                        ] as [$approvalField, $approvalIcon, $approvalLabel]): ?>
                                            <?php $isApproved = !empty($r[$approvalField]); ?>
                                            <li class="<?= $isApproved ? 'is-approved' : 'is-missing' ?>">
                                                <span aria-hidden="true"><?= $isApproved ? $approvalIcon : '×' ?></span>
                                                <div><strong><?= htmlspecialchars(t($approvalLabel), ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars(t($isApproved ? 'Approved' : 'Not approved'), ENT_QUOTES, 'UTF-8') ?></small></div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </dd>
                            </div>
                        </dl>
                        <form method="post" class="admin-decision-form goods-decision-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="request_id" value="<?= (int) $r['id'] ?>">
                            <label><?= htmlspecialchars(t('Admin note'), ENT_QUOTES, 'UTF-8') ?><textarea name="rejection_reason" rows="2" placeholder="<?= htmlspecialchars(t('Required when rejecting the request'), ENT_QUOTES, 'UTF-8') ?>"></textarea></label>
                            <div class="correction-decision-footer">
                                <small><?= htmlspecialchars(t('Review the beneficiary, requested aid, and official approvals before making a decision.'), ENT_QUOTES, 'UTF-8') ?></small>
                                <div class="correction-decision-actions">
                                    <button type="submit" name="decision" value="approve" class="approve-button"><?= htmlspecialchars(t('Approve'), ENT_QUOTES, 'UTF-8') ?></button>
                                    <button type="submit" name="decision" value="reject" class="reject-button"><?= htmlspecialchars(t('Reject'), ENT_QUOTES, 'UTF-8') ?></button>
                                </div>
                            </div>
                        </form>
                    </article>
                <?php endforeach; endif; ?>
            </div>
        </section>
        <?php else: ?>
        <section class="admin-data-card">


            <!-- ========================================================
                 TABLE CONTAINER
            ========================================================= -->

            <!-- The legend keeps Admin approval icons consistent with SSO and Subject Officer request tables. -->
            <!-- Match the SSO approval guide so every reviewing role sees one responsive design. -->
            <div class="approval-legend approval-legend-standalone" aria-label="<?= htmlspecialchars(t('Approval icon meanings'), ENT_QUOTES, 'UTF-8') ?>">
                <strong class="approval-legend-title"><?= htmlspecialchars(t('Approval guide'), ENT_QUOTES, 'UTF-8') ?></strong>
                <span>🩺 <?= htmlspecialchars(t('Government Medical Officer'), ENT_QUOTES, 'UTF-8') ?></span><span>🏡 <?= htmlspecialchars(t('Grama Niladhari'), ENT_QUOTES, 'UTF-8') ?></span>
                <span>🏛 <?= htmlspecialchars(t('Social Services Officer'), ENT_QUOTES, 'UTF-8') ?></span><span>📋 <?= htmlspecialchars(t('Divisional Secretary'), ENT_QUOTES, 'UTF-8') ?></span><span>❌ <?= htmlspecialchars(t('Not approved'), ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <div class="admin-data-table-wrap">

                <div class="identification-guide" aria-label="<?= htmlspecialchars(t('Identification guide'), ENT_QUOTES, 'UTF-8') ?>">
                    <strong><?= htmlspecialchars(t('Identification guide'), ENT_QUOTES, 'UTF-8') ?></strong>
                    <span class="identification-guide-nic">NIC</span>
                    <span class="identification-guide-elder"><?= htmlspecialchars(t("Elders' Identity Card"), ENT_QUOTES, 'UTF-8') ?></span>
                </div>


                <table
                    class="admin-data-table item-requests-table aid-request-list-table"
                >


                    <!-- =================================================
                         TABLE HEADINGS
                    ================================================== -->

                    <thead>

                        <tr>
                            <th><?= htmlspecialchars(t('ID'), ENT_QUOTES, 'UTF-8') ?></th>

                            <th><?= htmlspecialchars(t('Beneficiary'), ENT_QUOTES, 'UTF-8') ?></th>

                            <th><?= htmlspecialchars(t('Identification'), ENT_QUOTES, 'UTF-8') ?></th>

                            <th><?= htmlspecialchars(t('Age'), ENT_QUOTES, 'UTF-8') ?></th>

                            <th><?= htmlspecialchars(t('Address'), ENT_QUOTES, 'UTF-8') ?></th>

                            <th><?= htmlspecialchars(t('District'), ENT_QUOTES, 'UTF-8') ?></th>

                            <th><?= htmlspecialchars(t('DS Division'), ENT_QUOTES, 'UTF-8') ?></th>

                            <th><?= htmlspecialchars(t('Aid Requested'), ENT_QUOTES, 'UTF-8') ?></th>

                            <th><?= htmlspecialchars(t('Approvals'), ENT_QUOTES, 'UTF-8') ?></th>

                            <th><?= htmlspecialchars(t('Submitted By'), ENT_QUOTES, 'UTF-8') ?></th>

                            <th><?= htmlspecialchars(t('Date'), ENT_QUOTES, 'UTF-8') ?></th>

                            <th><?= htmlspecialchars(t('Status'), ENT_QUOTES, 'UTF-8') ?></th>

                            <th><?= htmlspecialchars(t('Action'), ENT_QUOTES, 'UTF-8') ?></th>
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
                                colspan="13"
                                class="admin-empty-row"
                            >

                                <?= htmlspecialchars(t('No aid distribution requests available.'), ENT_QUOTES, 'UTF-8') ?>

                            </td>

                        </tr>


                    <?php else: ?>


                        <?php foreach ($rows as $r): ?>


                            <tr id="aid-request-<?= (int) $r['id'] ?>" class="admin-notification-target" tabindex="-1">
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


                            <td class="request-identification-cell">
                                <?php if (!empty($r['nic'])): ?>
                                    <span class="request-identification-value identification-nic"><small>NIC</small><?= htmlspecialchars($r['nic'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                                <?php if (!empty($r['elders_card_number'])): ?>
                                    <span class="request-identification-value identification-elder"><small><?= htmlspecialchars(t("Elders' ID"), ENT_QUOTES, 'UTF-8') ?></small><?= htmlspecialchars($r['elders_card_number'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                                <?php if (empty($r['nic']) && empty($r['elders_card_number'])): ?>—<?php endif; ?>
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


                            <td class="request-aid-cell">
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

                                <?php $requestDetails=json_decode((string)($r['beneficiary_details_json']??''),true);if((!is_array($requestDetails)||!$requestDetails)&&!empty($r['beneficiary_detail_label'])&&$r['beneficiary_detail_value']!==null)$requestDetails=[['label'=>$r['beneficiary_detail_label'],'type'=>'text','value'=>$r['beneficiary_detail_value'],'display_value'=>$r['beneficiary_detail_value']]];if(is_array($requestDetails)&&$requestDetails): ?>
                                    <button type="button" class="request-extra-info-button" data-request-extra-info="<?= htmlspecialchars(json_encode($requestDetails,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),ENT_QUOTES,'UTF-8') ?>" data-dialog-title="<?= htmlspecialchars(t('Beneficiary Details'),ENT_QUOTES,'UTF-8') ?>" data-close-label="<?= htmlspecialchars(t('Close'),ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars(t('View details'),ENT_QUOTES,'UTF-8') ?></button>
                                <?php endif; ?>
                            </td>


                            <td class="request-approvals-cell">
                                <span class="approval-icon-set" title="<?= htmlspecialchars(t('Official Approvals'), ENT_QUOTES, 'UTF-8') ?>">
                                    <?= $r['medical_officer_approved'] ? '🩺' : '❌' ?>
                                    <?= $r['grama_niladhari_approved'] ? '🏡' : '❌' ?>
                                    <?= $r['social_services_approved'] ? '🏛' : '❌' ?>
                                    <?= $r['divisional_secretary_approved'] ? '📋' : '❌' ?>
                                </span>
                            </td>


                            <td class="request-submitter-cell">
                                <?= htmlspecialchars(
                                    $r['submitter_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>


                            <td class="request-submitted-cell">
                                <?= date(
                                    'd-m-Y',
                                    strtotime($r['created_at'])
                                ) ?>
                            </td>


                            <td class="request-status-cell">
                                <span
                                    class="request-status-pill
                                    status-<?= htmlspecialchars(
                                        $r['status'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                >
                                    <?= htmlspecialchars(
                                        t(ucwords(str_replace('-', ' ', $r['status']))),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>
                            </td>


                            <td class="request-action-cell">

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

                                        <input type="hidden" name="rejection_reason" value="">

                                        <button
                                            type="submit"
                                            name="decision"
                                            value="approve"
                                            class="approve-button"
                                        >
                                            <?= htmlspecialchars(t('Approve'), ENT_QUOTES, 'UTF-8') ?>
                                        </button>

                                        <button
                                            type="button"
                                            class="reject-button"
                                            data-reason-trigger
                                            data-submit-name="decision"
                                            data-submit-value="reject"
                                            data-reason-field="rejection_reason"
                                            data-dialog-title="<?= htmlspecialchars(t('Reject aid request'), ENT_QUOTES, 'UTF-8') ?>"
                                            data-dialog-confirm="<?= htmlspecialchars(t('Reject request'), ENT_QUOTES, 'UTF-8') ?>"
                                            data-reason-label="<?= htmlspecialchars(t('Reason for rejection'), ENT_QUOTES, 'UTF-8') ?>"
                                            data-reason-required="<?= htmlspecialchars(t('Enter a reason before rejecting this request.'), ENT_QUOTES, 'UTF-8') ?>"
                                            data-cancel-label="<?= htmlspecialchars(t('Cancel'), ENT_QUOTES, 'UTF-8') ?>"
                                        >
                                            <?= htmlspecialchars(t('Reject'), ENT_QUOTES, 'UTF-8') ?>
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
        <?php endif; ?>


    </main>


</div>



<!-- ==================================================================
     WIDMS SHARED JAVASCRIPT
=================================================================== -->

<script src="assets/js/admin-dashboard.js?v=17"></script>


</body>

</html>
