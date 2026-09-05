<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| 1. ACCESS CONTROL
|--------------------------------------------------------------------------
| Only a Social Service Officer is allowed to create aid requests.
|--------------------------------------------------------------------------
*/

if (!hasRole('social-service-officer')) {
    http_response_code(403);
    exit('Only Social Service Officers can create aid requests.');
}


/*
|--------------------------------------------------------------------------
| 2. REQUIRED FILES
|--------------------------------------------------------------------------
| Load:
| - Database connection
| - Activity logging
| - Beneficiary eligibility checking
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/activity.php';
require_once __DIR__ . '/../../includes/eligibility.php';


/*
|--------------------------------------------------------------------------
| 3. DISABLE PAGE CACHING
|--------------------------------------------------------------------------
| Prevent the browser from showing outdated request information.
|--------------------------------------------------------------------------
*/

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');


/*
|--------------------------------------------------------------------------
| 4. BASIC PAGE VARIABLES
|--------------------------------------------------------------------------
*/

$subject = hasRole('subject-officer');

$showRequestForm = !$subject && (string) ($_GET['page'] ?? '') === 'new-aid-request';
$activePage = $subject ? 'aid-distribution' : ($showRequestForm ? 'new-aid-request' : 'aid-requests');

$db = database();

$userId = (int) $_SESSION['user_id'];

$errors = [];

$success = (string) ($_SESSION['flash_success'] ?? '');

unset($_SESSION['flash_success']);


/*
|--------------------------------------------------------------------------
| 5. FORM VALUES
|--------------------------------------------------------------------------
| These values are used to:
| - collect submitted POST data
| - refill the form if validation fails
|--------------------------------------------------------------------------
*/

$v = [
    'district_id' => '',
    'ds_division_id' => '',
    'gn_division_id' => '',
    'full_name' => '',
    // Identification values
    'nic' => '',
    'elders_card_number' => '',

    'date_of_birth' => '',
    'gender' => '',
    'phone' => '',
    'address' => '',
    'disability_notes' => '',
    'item_id' => '',
    'quantity' => '1',
    'beneficiary_detail_value' => '',
    'prescribed_power' => '',
    'notes' => '',
];

// Keep prior approval selections when an SSO reopens a request for correction.
$approvalSelections = [
    'medical_officer' => false,
    'grama_niladhari' => false,
    'social_services' => false,
    'divisional_secretary' => false,
];
$useNicSelected = false;
$useEldersCardSelected = false;

$editingRequestId = filter_input(INPUT_GET, 'edit_request_id', FILTER_VALIDATE_INT) ?: 0;


/*
|--------------------------------------------------------------------------
| 6. DEFAULT DISABILITY TYPES
|--------------------------------------------------------------------------
| These are used if the disability_types database table cannot be read.
|--------------------------------------------------------------------------
*/

$defaultDisabilityTypes = [
    'Mobility Impairment - Upper Limb',
    'Mobility Impairment - Lower Limb',
    'Visual Impairment',
    'Hearing Impairment',
    'Speech and Language Impairment',
    'Intellectual Disability',
    'Autism Spectrum Disorder',
    'Multiple Disabilities',
    'Other',
];


/*
|--------------------------------------------------------------------------
| 7. LOAD ACTIVE DISABILITY TYPES
|--------------------------------------------------------------------------
*/

try {

    $disabilityTypes = $db->query(
        "SELECT name
         FROM disability_types
         WHERE status='active'
         ORDER BY name"
    )->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {

    error_log($e->getMessage());

    // Use default values if database values cannot be loaded.
    $disabilityTypes = $defaultDisabilityTypes;
}

/*
|--------------------------------------------------------------------------
| LOAD AN EDITABLE AID REQUEST
|--------------------------------------------------------------------------
| Only the owning SSO may reopen a Draft or Pending request. Reviewed and
| distributed requests stay immutable so their operational history is safe.
|--------------------------------------------------------------------------
*/
if ($showRequestForm && $editingRequestId) {
    $editRequest = $db->prepare(
        "SELECT ar.*, b.district_id, b.ds_division_id, b.gn_division_id,
                b.full_name, b.nic, b.elders_card_number, b.date_of_birth,
                b.gender, b.phone, b.address, b.disability
         FROM aid_requests ar
         JOIN beneficiaries b ON b.id = ar.beneficiary_id
         WHERE ar.id = :id AND ar.submitted_by = :user
           AND ar.status IN ('draft', 'pending')"
    );
    $editRequest->execute(['id' => $editingRequestId, 'user' => $userId]);
    $editRequest = $editRequest->fetch();

    if (!$editRequest) {
        $errors[] = 'This aid request can no longer be edited.';
        $editingRequestId = 0;
    } else {
        $v = [
            'district_id' => (string) $editRequest['district_id'],
            'ds_division_id' => (string) $editRequest['ds_division_id'],
            'gn_division_id' => (string) ($editRequest['gn_division_id'] ?? ''),
            'full_name' => (string) $editRequest['full_name'],
            'nic' => (string) ($editRequest['nic'] ?? ''),
            'elders_card_number' => (string) ($editRequest['elders_card_number'] ?? ''),
            'date_of_birth' => (string) $editRequest['date_of_birth'],
            'gender' => (string) $editRequest['gender'],
            'phone' => (string) ($editRequest['phone'] ?? ''),
            'address' => (string) $editRequest['address'],
            'disability_notes' => (string) $editRequest['disability'],
            'item_id' => (string) $editRequest['item_id'],
            'quantity' => (string) $editRequest['quantity'],
            'beneficiary_detail_value' => (string) ($editRequest['beneficiary_detail_value'] ?? ''),
            'prescribed_power' => (string) ($editRequest['prescribed_power'] ?? ''),
            'notes' => (string) ($editRequest['notes'] ?? ''),
        ];
        $approvalSelections = [
            'medical_officer' => (bool) $editRequest['medical_officer_approved'],
            'grama_niladhari' => (bool) $editRequest['grama_niladhari_approved'],
            'social_services' => (bool) $editRequest['social_services_approved'],
            'divisional_secretary' => (bool) $editRequest['divisional_secretary_approved'],
        ];
        $useNicSelected = $v['nic'] !== '';
        $useEldersCardSelected = $v['elders_card_number'] !== '';
    }
}


/*
|--------------------------------------------------------------------------
| 8. PROCESS FORM SUBMISSION
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['request_action'] ?? '') === 'delete') {
    $requestId = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
    if (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Your session expired.';
    } elseif (!$requestId) {
        $errors[] = 'Select a valid aid request.';
    } else {
        // Delete only the current SSO's unreviewed request, never an operational record.
        $deleteRequest = $db->prepare(
            "DELETE FROM aid_requests
             WHERE id = :id AND submitted_by = :user
               AND status IN ('draft', 'pending')"
        );
        $deleteRequest->execute(['id' => $requestId, 'user' => $userId]);
        if ($deleteRequest->rowCount() === 1) {
            logActivity('Aid Requests', 'Removed editable aid request', 'AR-'.str_pad((string) $requestId, 4, '0', STR_PAD_LEFT), 'removed');
            $_SESSION['flash_success'] = 'Aid request removed.';
            unset($_SESSION['csrf_token']);
            header('Location: dashboard.php?page=aid-requests');
            exit;
        }
        $errors[] = 'This aid request can no longer be removed.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['request_action'] ?? '') !== 'delete') {

    /*
    |--------------------------------------------------------------------------
    | 8.1 Collect form data
    |--------------------------------------------------------------------------
    */

    foreach (array_keys($v) as $key) {
        $v[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    /*
    |--------------------------------------------------------------------------
    | 8.1.1 Determine whether to use NIC or Elder's Card
    |--------------------------------------------------------------------------
    */

    $useNic = isset($_POST['use_nic']);
    $useEldersCard = isset($_POST['use_elders_card']);
    $useNicSelected = $useNic;
    $useEldersCardSelected = $useEldersCard;

    $nic = $useNic
        ? strtoupper(preg_replace('/\s+/', '', $v['nic']))
        : null;

    $eldersCardNumber = $useEldersCard
        ? strtoupper(trim($v['elders_card_number']))
        : null;


    /*
    |--------------------------------------------------------------------------
    | 8.2 Determine whether this is:
    | - Submit Aid Request
    | - Save as Draft
    |--------------------------------------------------------------------------
    */

    $saveDraft =
        ($_POST['submit_action'] ?? 'submit') === 'draft';


    /*
    |--------------------------------------------------------------------------
    | 8.3 Convert numeric IDs
    |--------------------------------------------------------------------------
    */

    $district = filter_var(
        $v['district_id'],
        FILTER_VALIDATE_INT
    );

    $ds = filter_var(
        $v['ds_division_id'],
        FILTER_VALIDATE_INT
    );

    $gn = filter_var(
        $v['gn_division_id'],
        FILTER_VALIDATE_INT
    );

    $item = filter_var(
        $v['item_id'],
        FILTER_VALIDATE_INT
    );

    $qty = filter_var(
        $v['quantity'],
        FILTER_VALIDATE_INT
    );


    


    /*
    |--------------------------------------------------------------------------
    | 8.5 Collect the four official approval checkboxes
    |--------------------------------------------------------------------------
    */

    $signoffs = [
        'medical_officer' =>
            isset($_POST['medical_officer']),

        'grama_niladhari' =>
            isset($_POST['grama_niladhari']),

        'social_services' =>
            isset($_POST['social_services']),

        'divisional_secretary' =>
            isset($_POST['divisional_secretary']),
    ];

    $approvalSelections = $signoffs;
    $editingRequestId = filter_input(INPUT_POST, 'edit_request_id', FILTER_VALIDATE_INT) ?: 0;


    /*
    |--------------------------------------------------------------------------
    | 9. SERVER-SIDE VALIDATION
    |--------------------------------------------------------------------------
    */


    // Validate CSRF security token.
    if (!verifyCsrfToken(
        (string) ($_POST['csrf_token'] ?? '')
    )) {
        $errors[] = 'Your session expired.';
    }


    // Service centres represent a fixed residential location and intentionally have no GN Division.
    $isServiceDivision = false;
    if ($district && $ds) {
        $divisionType = $db->prepare(
            "SELECT division_type
             FROM ds_divisions
             WHERE id = :ds AND district_id = :district AND status = 'active'"
        );
        $divisionType->execute([
            'ds' => $ds,
            'district' => $district,
        ]);
        $isServiceDivision = $divisionType->fetchColumn() === 'service-centre';
    }


    // GN Division remains mandatory for official DS Divisions only.
    if (!$district || !$ds || (!$isServiceDivision && !$gn)) {
        $errors[] =
            'Select District, D.S. Division, and G.N. Division.';
    }


    // Validate beneficiary full name.
    if (
        mb_strlen($v['full_name']) < 2 ||
        mb_strlen($v['full_name']) > 150
    ) {
        $errors[] = 'Enter a valid full name.';
    }


    /*
    |--------------------------------------------------------------------------
    | Validate NIC && Elder's Card
    |--------------------------------------------------------------------------
    |
    */
    if (!$useNic && !$useEldersCard) {
    $errors[] =
        'Select at least one identification method: NIC or Elder\'s Card.';
    }


    /* NIC validation */
    if ($useNic) {

        if ($nic === null || $nic === '') {

            $errors[] = 'Enter the beneficiary NIC number.';

        } elseif (
            !preg_match(
                '/^(?:[0-9]{9}[VX]|[0-9]{12})$/',
                $nic
            )
        ) {

            $errors[] = 'Enter a valid Sri Lankan NIC number.';
        }
    }


    /* Elder's Card validation */
    if ($useEldersCard) {

        if (
            $eldersCardNumber === null ||
            $eldersCardNumber === ''
        ) {

            $errors[] =
                'Enter the Elder\'s Card number.';

        } elseif (
            !preg_match(
                '/^[A-Z0-9\/\-]{4,30}$/',
                $eldersCardNumber
            )
        ) {

            $errors[] =
                'Enter a valid Elder\'s Identification Card number.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Validate date of birth
    |--------------------------------------------------------------------------
    */

    $dob = DateTimeImmutable::createFromFormat(
        'Y-m-d',
        $v['date_of_birth']
    );

    if (
        !$dob ||
        $dob->format('Y-m-d') !== $v['date_of_birth'] ||
        $dob > new DateTimeImmutable('today')
    ) {
        $errors[] =
            'Enter a valid date of birth.';
    }


    /*
    |--------------------------------------------------------------------------
    | Validate gender
    |--------------------------------------------------------------------------
    */

    if (
        !in_array(
            $v['gender'],
            ['male', 'female', 'other'],
            true
        )
    ) {
        $errors[] =
            'Select a gender.';
    }


    /*
    |--------------------------------------------------------------------------
    | Validate phone number
    |--------------------------------------------------------------------------
    | Phone is optional.
    |--------------------------------------------------------------------------
    */

    if (
        $v['phone'] !== '' &&
        !preg_match(
            '/^[0-9+()\-\s]{7,25}$/',
            $v['phone']
        )
    ) {
        $errors[] =
            'Enter a valid phone number.';
    }


    /*
    |--------------------------------------------------------------------------
    | Validate beneficiary address
    |--------------------------------------------------------------------------
    */

    if (
        mb_strlen($v['address']) < 5 ||
        mb_strlen($v['address']) > 255
    ) {
        $errors[] =
            'Enter a valid address.';
    }


    /*
    |--------------------------------------------------------------------------
    | Validate disability
    |--------------------------------------------------------------------------
    */

    if (
        !in_array(
            $v['disability_notes'],
            $disabilityTypes,
            true
        )
    ) {
        $errors[] =
            'Select a valid disability from the list.';
    }


    /*
    |--------------------------------------------------------------------------
    | Validate aid item and quantity
    |--------------------------------------------------------------------------
    */

    if (!$item || !$qty || $qty < 1) {
        $errors[] =
            'Select an aid type and valid quantity.';
    }


    /*
    |--------------------------------------------------------------------------
    | Validate additional notes
    |--------------------------------------------------------------------------
    */

    if (mb_strlen($v['notes']) > 1000) {
        $errors[] =
            'Notes cannot exceed 1000 characters.';
    }


    /*
    |--------------------------------------------------------------------------
    | Record official approvals
    |--------------------------------------------------------------------------
    | Approvals may be recorded when available, but they never block a request
    | from being submitted for review.
    |--------------------------------------------------------------------------
    */


    /*
    |--------------------------------------------------------------------------
    | 10. SAVE REQUEST IF VALIDATION PASSED
    |--------------------------------------------------------------------------
    */

    if (!$errors) {

        try {

            /*
            |--------------------------------------------------------------------------
            | Start database transaction
            |--------------------------------------------------------------------------
            */

            $db->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | 10.1 Validate geographical hierarchy
            |--------------------------------------------------------------------------
            | Check that:
            |
            | District
            |   ↓
            | DS Division
            |   ↓
            | GN Division
            |
            | actually belong together.
            |--------------------------------------------------------------------------
            */

            $division = $db->prepare(
                "SELECT division_type
                 FROM ds_divisions
                 WHERE id = :ds
                   AND district_id = :district
                   AND status = 'active'
                 FOR UPDATE"
            );
            $division->execute([
                'ds' => $ds,
                'district' => $district,
            ]);
            $divisionType = $division->fetchColumn();

            if (!$divisionType) {
                throw new RuntimeException(
                    'The selected location hierarchy is invalid.'
                );
            }

            // Never attach an arbitrary GN Division to a service-centre beneficiary.
            if ($divisionType === 'service-centre') {
                $gn = null;
            } else {
                $geo = $db->prepare(
                    "SELECT gn.id
                     FROM gn_divisions gn
                     JOIN ds_divisions ds
                        ON ds.id = gn.ds_division_id
                     JOIN districts d
                        ON d.id = ds.district_id

                     WHERE gn.id = :gn
                       AND ds.id = :ds
                       AND d.id = :district
                       AND gn.status = 'active'
                       AND ds.status = 'active'
                       AND d.status = 'active'"
                );

                $geo->execute([
                    'gn' => $gn,
                    'ds' => $ds,
                    'district' => $district,
                ]);


                if (!$geo->fetchColumn()) {
                    throw new RuntimeException(
                        'The selected location hierarchy is invalid.'
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | 10.2 Load selected aid item
            |--------------------------------------------------------------------------
            */

            $q = $db->prepare(
                "SELECT
                    i.*,
                    c.name category_name,
                    c.distribution_type,
                    dai.beneficiary_field_label,
                    dai.beneficiary_field_type

                 FROM inventory_items i

                 JOIN item_categories c
                    ON c.id = i.category_id

                 LEFT JOIN disability_aid_items dai
                    ON dai.item_id = i.id AND dai.status = 'active'

                 WHERE i.id = :id
                   AND c.status = 'active'"
            );

            $q->execute([
                'id' => $item
            ]);

            $aid = $q->fetch();


            /*
            |--------------------------------------------------------------------------
            | Only request-based aid types can be requested here.
            |--------------------------------------------------------------------------
            */

            if (
                !$aid ||
                $aid['distribution_type'] !== 'request-based'
            ) {
                throw new RuntimeException(
                    'Select a request-based aid type.'
                );
            }


            /* The Subject Officer, not a hard-coded item name, controls whether this value is required. */
            $detailLabel = trim((string) ($aid['beneficiary_field_label'] ?? ''));
            $detailType = (string) ($aid['beneficiary_field_type'] ?? 'text');
            $detailValue = trim($v['beneficiary_detail_value']);
            if ($detailLabel !== '') {
                if ($detailValue === '') {
                    throw new RuntimeException($detailLabel . ' is required for the selected aid item.');
                }
                if ($detailType === 'number' && !is_numeric($detailValue)) {
                    throw new RuntimeException($detailLabel . ' must be a number.');
                }
                if (mb_strlen($detailValue) > 255) {
                    throw new RuntimeException($detailLabel . ' must not exceed 255 characters.');
                }
            } else {
                $detailValue = null;
            }

            // Legacy power remains unused for newly configured items; their value is stored generically.
            $power = null;


            /*
            |--------------------------------------------------------------------------
            | 10.4 IDENTIFY EXISTING BENEFICIARY
            */

            $beneficiary = 0;

    $conditions = [];
    $params = [];


    /* Search by NIC */
    if ($useNic && $nic !== null) {

        $conditions[] = 'nic = :nic';
        $params['nic'] = $nic;
    }


    /* Search by Elder's Card */
    if (
        $useEldersCard &&
        $eldersCardNumber !== null
    ) {

        $conditions[] =
            'elders_card_number = :elders_card';

        $params['elders_card'] =
            $eldersCardNumber;
    }


    if ($conditions !== []) {

        $q = $db->prepare(
            'SELECT id, nic, elders_card_number
            FROM beneficiaries
            WHERE ' . implode(' OR ', $conditions) . '
            FOR UPDATE'
        );

        $q->execute($params);

        $matches = $q->fetchAll();


        if (count($matches) > 1) {

            throw new RuntimeException(
                'The NIC and Elder\'s Card belong to different beneficiary records.'
            );
        }


        if (count($matches) === 1) {

            $beneficiary =
                (int) $matches[0]['id'];
        }
    }


            /*
            |--------------------------------------------------------------------------
            | 10.5 EXISTING BENEFICIARY
            |--------------------------------------------------------------------------
            |
            | If the NIC was found, update the beneficiary information.
            |--------------------------------------------------------------------------
            */

            if ($beneficiary) {

                $db->prepare(
                    'UPDATE beneficiaries

                     SET
                        district_id = :district,
                        ds_division_id = :ds,
                        gn_division_id = :gn,
                        full_name = :name,
                        date_of_birth = :dob,
                        gender = :gender,
                        phone = :phone,
                        address = :address,
                        disability = :disability

                     WHERE id = :id'
                )->execute([

                    'district' =>
                        $district,

                    'ds' =>
                        $ds,

                    'gn' =>
                        $gn,

                    'name' =>
                        $v['full_name'],

                    'dob' =>
                        $v['date_of_birth'],

                    'gender' =>
                        $v['gender'],

                    'phone' =>
                        $v['phone'] ?: null,

                    'address' =>
                        $v['address'],

                    'disability' =>
                        $v['disability_notes'],

                    'id' =>
                        $beneficiary,
                ]);

            } else {

                /*
                |--------------------------------------------------------------------------
                | 10.6 NEW BENEFICIARY
                |--------------------------------------------------------------------------
                |
                | If no existing beneficiary was found,
                | create a new beneficiary.
                |
                | beneficiaries.id is created automatically by MySQL
                | because it is AUTO_INCREMENT.
                |--------------------------------------------------------------------------
                */

                $db->prepare(
                    'INSERT INTO beneficiaries(
                        district_id,
                        ds_division_id,
                        gn_division_id,
                        full_name,
                        nic,
                        elders_card_number,
                        date_of_birth,
                        gender,
                        phone,
                        address,
                        disability,
                        approved_by,
                        approved_at
                    )

                    VALUES(
                        :district,
                        :ds,
                        :gn,
                        :name,
                        :nic,
                        :elders_card,
                        :dob,
                        :gender,
                        :phone,
                        :address,
                        :disability,
                        :user,
                        NOW()
                    )'
                )->execute([

                    'district' =>
                        $district,

                    'ds' =>
                        $ds,

                    'gn' =>
                        $gn,

                    'name' =>
                        $v['full_name'],

                    'nic' => $nic,
                    'elders_card' => $eldersCardNumber,

                    'dob' =>
                        $v['date_of_birth'],

                    'gender' =>
                        $v['gender'],

                    'phone' =>
                        $v['phone'] ?: null,

                    'address' =>
                        $v['address'],

                    'disability' =>
                        $v['disability_notes'],

                    'user' =>
                        $userId,
                ]);


                /*
                |--------------------------------------------------------------------------
                | Get the automatically generated beneficiary primary key.
                |--------------------------------------------------------------------------
                */

                $beneficiary =
                    (int) $db->lastInsertId();
            }


            /*
            |--------------------------------------------------------------------------
            | 10.7 BENEFICIARY ELIGIBILITY CHECK
            |--------------------------------------------------------------------------
            |
            | Eligibility is checked only for submitted requests.
            |
            | Drafts are allowed without this check.
            |--------------------------------------------------------------------------
            */

            if (!$saveDraft) {

                $eligibility =
                    beneficiaryEligibility(
                        $db,
                        $beneficiary,
                        $item,
                        $editingRequestId
                    );


                if (!$eligibility['eligible']) {
                    throw new RuntimeException(
                        $eligibility['reason']
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | 10.8 Determine aid request status
            |--------------------------------------------------------------------------
            */

            $status =
                $saveDraft
                    ? 'draft'
                    : 'pending';


            /*
            |--------------------------------------------------------------------------
            | 10.10 CREATE AID REQUEST
            |--------------------------------------------------------------------------
            |
            | Notice:
            |
            | aid_requests does not store the NIC.
            |
            | It stores beneficiary_id.
            |
            | beneficiary_id points to beneficiaries.id.
            |--------------------------------------------------------------------------
            */

            if ($editingRequestId) {
                // Recheck ownership and state inside the transaction before overwriting the request.
                $editableRequest = $db->prepare(
                    "SELECT id FROM aid_requests
                     WHERE id = :id AND submitted_by = :user
                       AND status IN ('draft', 'pending')
                     FOR UPDATE"
                );
                $editableRequest->execute([
                    'id' => $editingRequestId,
                    'user' => $userId,
                ]);
                if (!$editableRequest->fetchColumn()) {
                    throw new RuntimeException('This aid request can no longer be edited.');
                }

                $updateRequest = $db->prepare(
                    "UPDATE aid_requests
                     SET beneficiary_id = :beneficiary, item_id = :item,
                         quantity = :qty, disability_notes = :disability,
                         prescribed_power = :power,
                         beneficiary_detail_label = :detail_label,
                         beneficiary_detail_value = :detail_value, notes = :notes,
                         medical_officer_approved = :medical,
                         grama_niladhari_approved = :gn,
                         social_services_approved = :social,
                         divisional_secretary_approved = :secretary,
                         status = :status
                     WHERE id = :id AND submitted_by = :user
                       AND status IN ('draft', 'pending')"
                );
                $updateRequest->execute([
                    'beneficiary' => $beneficiary,
                    'item' => $item,
                    'qty' => $qty,
                    'disability' => $v['disability_notes'],
                    'power' => $power,
                    'detail_label' => $detailLabel ?: null,
                    'detail_value' => $detailValue,
                    'notes' => $v['notes'] ?: null,
                    'medical' => (int) $signoffs['medical_officer'],
                    'gn' => (int) $signoffs['grama_niladhari'],
                    'social' => (int) $signoffs['social_services'],
                    'secretary' => (int) $signoffs['divisional_secretary'],
                    'status' => $status,
                    'id' => $editingRequestId,
                    'user' => $userId,
                ]);
                $id = $editingRequestId;
            } else {

            $db->prepare(
                'INSERT INTO aid_requests(
                    beneficiary_id,
                    item_id,
                    quantity,
                    disability_notes,
                    prescribed_power,
                    beneficiary_detail_label,
                    beneficiary_detail_value,
                    notes,
                    medical_officer_approved,
                    grama_niladhari_approved,
                    social_services_approved,
                    divisional_secretary_approved,
                    status,
                    submitted_by
                )

                VALUES(
                    :beneficiary,
                    :item,
                    :qty,
                    :disability,
                    :power,
                    :detail_label,
                    :detail_value,
                    :notes,
                    :medical,
                    :gn,
                    :social,
                    :secretary,
                    :status,
                    :user
                )'
            )->execute([

                'beneficiary' =>
                    $beneficiary,

                'item' =>
                    $item,

                'qty' =>
                    $qty,

                'disability' =>
                    $v['disability_notes'],

                'power' =>
                    $power,

                'detail_label' =>
                    $detailLabel ?: null,

                'detail_value' =>
                    $detailValue,

                'notes' =>
                    $v['notes'] ?: null,

                'medical' =>
                    (int) $signoffs['medical_officer'],

                'gn' =>
                    (int) $signoffs['grama_niladhari'],

                'social' =>
                    (int) $signoffs['social_services'],

                'secretary' =>
                    (int) $signoffs['divisional_secretary'],

                'status' =>
                    $status,

                'user' =>
                    $userId,
            ]);

            }


            /*
            |--------------------------------------------------------------------------
            | Get automatically generated aid request ID.
            |--------------------------------------------------------------------------
            */

            if (!$editingRequestId) {
                $id =
                    (int) $db->lastInsertId();
            }


            /*
            |--------------------------------------------------------------------------
            | Save all database operations.
            |--------------------------------------------------------------------------
            */

            $db->commit();


            /*
            |--------------------------------------------------------------------------
            | 10.11 RECORD ACTIVITY LOG
            |--------------------------------------------------------------------------
            */

            logActivity(
                'Aid Requests',

                $editingRequestId
                    ? 'Updated aid request'
                    : ($saveDraft
                    ? 'Saved aid request draft'
                    : 'Submitted aid request'),

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
            | 10.12 Create success message
            |--------------------------------------------------------------------------
            */

            $_SESSION['flash_success'] =
                $editingRequestId
                    ? 'Aid request updated.'
                    : ($saveDraft

                    ? 'Aid request saved as draft.'

                    : 'Aid request submitted for Admin approval.');


            unset($_SESSION['csrf_token']);


            /*
            |--------------------------------------------------------------------------
            | Redirect back to Aid Request page.
            |--------------------------------------------------------------------------
            */

            header(
                'Location: dashboard.php?page=' .
                (
                    $subject
                        ? 'aid-distribution'
                        : 'aid-requests'
                )
            );

            exit;

        } catch (Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | Something failed.
            |
            | Undo database changes made inside the transaction.
            |--------------------------------------------------------------------------
            */

            if ($db->inTransaction()) {
                $db->rollBack();
            }


            error_log(
                $e->getMessage()
            );


            $errors[] =
                $e instanceof RuntimeException

                    ? $e->getMessage()

                    : 'Unable to save aid request.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| 11. LOAD DATA REQUIRED BY THE PAGE
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | Load Southern Province districts.
    |--------------------------------------------------------------------------
    */

    $districts = $db->query(
        "SELECT id, name

         FROM districts

         WHERE status='active'

           AND name IN (
               'Galle',
               'Matara',
               'Hambantota'
           )

         ORDER BY FIELD(
             name,
             'Galle',
             'Matara',
             'Hambantota'
         )"
    )->fetchAll();


    /*
    |--------------------------------------------------------------------------
    | Load DS Divisions.
    |--------------------------------------------------------------------------
    */

    $dsDivisions = $db->query(
        "SELECT
            ds.id,
            ds.district_id,
            ds.name,
            ds.division_type

         FROM ds_divisions ds

         JOIN districts d
            ON d.id = ds.district_id

         WHERE ds.status='active'

           AND d.name IN (
               'Galle',
               'Matara',
               'Hambantota'
           )

         ORDER BY ds.name"
    )->fetchAll();


    /*
    |--------------------------------------------------------------------------
    | Load GN Divisions.
    |--------------------------------------------------------------------------
    */

    $gnDivisions = $db->query(
        "SELECT
            gn.id,
            gn.ds_division_id,
            gn.name

         FROM gn_divisions gn

         JOIN ds_divisions ds
            ON ds.id = gn.ds_division_id

         JOIN districts d
            ON d.id = ds.district_id

         WHERE gn.status='active'

           AND d.name IN (
               'Galle',
               'Matara',
               'Hambantota'
           )

         ORDER BY gn.name"
    )->fetchAll();


    /*
    |--------------------------------------------------------------------------
    | Load aid types.
    |
    | requires_power becomes true for:
    | - Contact Lens
    | - Spectacles
    | - Glasses
    |--------------------------------------------------------------------------
    */

    // Only items configured for an active disability appear in the request form.
    $aidTypes = $db->query(
        "SELECT i.id,i.item_name,i.variety,dt.name disability_name,
                dai.beneficiary_field_label,dai.beneficiary_field_type
         FROM disability_aid_items dai
         JOIN disability_types dt ON dt.id=dai.disability_type_id AND dt.status='active'
         JOIN inventory_items i ON i.id=dai.item_id
         WHERE dai.status='active'
         ORDER BY dt.name,i.item_name,i.variety"
    )->fetchAll();


    /*
    |--------------------------------------------------------------------------
    | Load aid requests submitted by the currently logged-in SSO.
    |--------------------------------------------------------------------------
    |
    | The beneficiary is joined using:
    |
    | aid_requests.beneficiary_id
    |             ↓
    | beneficiaries.id
    |--------------------------------------------------------------------------
    */

    $q = $db->prepare(
        'SELECT
            ar.*,
            b.full_name,
            b.nic,
            b.date_of_birth,
            d.name district_name,
            ds.name division_name,
            i.item_name,
            i.variety

         FROM aid_requests ar

         JOIN beneficiaries b
            ON b.id = ar.beneficiary_id

         JOIN districts d
            ON d.id = b.district_id

         JOIN ds_divisions ds
            ON ds.id = b.ds_division_id

         JOIN inventory_items i
            ON i.id = ar.item_id

         WHERE ar.submitted_by = :user

         ORDER BY ar.id DESC'
    );


    $q->execute([
        'user' => $userId
    ]);


    $requests =
        $q->fetchAll();

} catch (PDOException $e) {

    error_log(
        $e->getMessage()
    );


    $districts =
    $dsDivisions =
    $gnDivisions =
    $aidTypes =
    $requests = [];


    $errors[] =
        'Aid request workflow is unavailable.';
}


/*
|--------------------------------------------------------------------------
| 12. HELPER FUNCTIONS
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Return previously entered form value.
|--------------------------------------------------------------------------
*/

function old(string $key): string
{
    global $v;

    return htmlspecialchars(
        $v[$key] ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| Calculate beneficiary age from date of birth.
|--------------------------------------------------------------------------
*/

function requestAge(string $dob): int
{
    return (int) (
        new DateTimeImmutable($dob)
    )
        ->diff(
            new DateTimeImmutable('today')
        )
        ->y;
}


/*
|--------------------------------------------------------------------------
| Convert submitted date into readable text.
|
| Examples:
| Today
| 2 days ago
| 3 weeks ago
| 15 Aug 2026
|--------------------------------------------------------------------------
*/

function requestSubmitted(string $date): string
{
    $then =
        new DateTimeImmutable($date);

    $now =
        new DateTimeImmutable();

    $days =
        (int) $then
            ->diff($now)
            ->days;


    if ($days === 0) {
        return 'Today';
    }


    if ($days < 7) {

        return $days .
            ' day' .
            ($days === 1 ? '' : 's') .
            ' ago';
    }


    if ($days < 35) {

        $weeks =
            (int) floor($days / 7);

        return $weeks .
            ' week' .
            ($weeks === 1 ? '' : 's') .
            ' ago';
    }


    return $then->format('d M Y');
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

    <title><?= $showRequestForm ? 'New Aid Request' : 'My Aid Requests' ?> | WIDMS</title>


    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- WIDMS main dashboard CSS -->

    <link
        href="assets/css/admin-dashboard.css?v=11"
        rel="stylesheet"
    >

</head>


<body>


<?php

/*
|--------------------------------------------------------------------------
| 13. SIDEBAR
|--------------------------------------------------------------------------
*/

require $subject
    ? __DIR__ .
        '/../../includes/subject-officer-sidebar.php'

    : __DIR__ .
        '/../../includes/social-service-officer-sidebar.php';

?>


<div class="admin-shell">


    <!-- ================================================================
         PAGE HEADER
    ================================================================= -->

    <header class="topbar">

        <h1><?= $showRequestForm ? htmlspecialchars(t('New Aid Request'), ENT_QUOTES, 'UTF-8') : htmlspecialchars(t('My Aid Requests'), ENT_QUOTES, 'UTF-8') ?></h1>

    </header>


    <main class="dashboard-content aid-requests-page">


        <!-- ============================================================
             SUCCESS MESSAGE
        ============================================================= -->

        <?php if ($success): ?>

            <div class="alert alert-success">

                <?= htmlspecialchars($success) ?>

            </div>

        <?php endif; ?>


        <!-- ============================================================
             VALIDATION ERROR MESSAGES
        ============================================================= -->

        <?php if ($errors): ?>

            <div class="alert alert-danger">

                <ul class="mb-0">

                    <?php foreach ($errors as $e): ?>

                        <li>
                            <?= htmlspecialchars($e) ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>


        <!-- ============================================================
             NEW AID REQUEST FORM
        ============================================================= -->

        <?php if ($showRequestForm): ?>
        <section class="aid-form-card">


            <form
                method="post"
                class="aid-request-form"
                id="aid-request-form"
                data-identification-required="<?= htmlspecialchars(t("Please select NIC or Elders' Identity Card."), ENT_QUOTES, 'UTF-8') ?>"
                data-invalid-nic="<?= htmlspecialchars(t('Enter a valid Sri Lankan NIC.'), ENT_QUOTES, 'UTF-8') ?>"
                data-invalid-elders-card="<?= htmlspecialchars(t("Enter a valid Elders' Identity Card number."), ENT_QUOTES, 'UTF-8') ?>"
            >


                <!-- CSRF Security Token -->

                <?php if ($editingRequestId): ?>
                    <input type="hidden" name="edit_request_id" value="<?= (int) $editingRequestId ?>">
                <?php endif; ?>

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(csrfToken()) ?>"
                >


                <!-- ====================================================
                     LOCATION DETAILS
                ===================================================== -->

                <fieldset>


                    <legend>
                        📍 <?= htmlspecialchars(t('Location Details'), ENT_QUOTES, 'UTF-8') ?>
                    </legend>


                    <div class="aid-form-grid three-columns">


                        <!-- District -->

                        <label>

                            <?= htmlspecialchars(t('District'), ENT_QUOTES, 'UTF-8') ?> *

                            <select
                                id="district_id"
                                name="district_id"
                                required
                            >

                                <option value="">
                                    <?= htmlspecialchars(t('Select District'), ENT_QUOTES, 'UTF-8') ?>
                                </option>


                                <?php foreach ($districts as $r): ?>

                                    <option
                                        value="<?= (int) $r['id'] ?>"
                                        <?= $v['district_id'] == $r['id']
                                            ? 'selected'
                                            : '' ?>
                                    >

                                        <?= htmlspecialchars($r['name']) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </label>


                        <!-- DS Division -->

                        <label>

                            <?= htmlspecialchars(t('D.S. Division'), ENT_QUOTES, 'UTF-8') ?> *

                            <select
                                id="ds_division_id"
                                name="ds_division_id"
                                required
                            >

                                <option value="">
                                    <?= htmlspecialchars(t('Select DS Division'), ENT_QUOTES, 'UTF-8') ?>
                                </option>


                                <?php foreach ($dsDivisions as $r): ?>

                                    <option

                                        value="<?= (int) $r['id'] ?>"

                                        data-parent="<?= (int) $r['district_id'] ?>"

                                        data-service-division="<?= $r['division_type'] === 'service-centre' ? '1' : '0' ?>"

                                        <?= $v['ds_division_id'] == $r['id']
                                            ? 'selected'
                                            : '' ?>
                                    >

                                        <?= htmlspecialchars($r['name']) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </label>


                        <!-- GN Division -->

                        <label>

                            <?= htmlspecialchars(t('G.N. Division'), ENT_QUOTES, 'UTF-8') ?> <span id="gn-required-indicator">*</span>

                            <select
                                id="gn_division_id"
                                name="gn_division_id"
                                required
                            >

                                <option value="">
                                    <?= htmlspecialchars(t('Select GN Division'), ENT_QUOTES, 'UTF-8') ?>
                                </option>


                                <?php foreach ($gnDivisions as $r): ?>

                                    <option

                                        value="<?= (int) $r['id'] ?>"

                                        data-parent="<?= (int) $r['ds_division_id'] ?>"

                                        <?= $v['gn_division_id'] == $r['id']
                                            ? 'selected'
                                            : '' ?>
                                    >

                                        <?= htmlspecialchars($r['name']) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                            <span class="field-help" id="service-division-gn-notice" hidden>
                                <?= htmlspecialchars(t('GN Division is not applicable for service divisions.'), ENT_QUOTES, 'UTF-8') ?>
                            </span>

                        </label>


                    </div>


                </fieldset>



                <!-- ====================================================
                     BENEFICIARY DETAILS
                ===================================================== -->

                <fieldset>


                    <legend>
                        👤 <?= htmlspecialchars(t('Beneficiary Details'), ENT_QUOTES, 'UTF-8') ?>
                    </legend>


                    <div class="aid-form-grid two-columns">


                        <!-- Beneficiary Full Name -->

                        <label>

                            <?= htmlspecialchars(t('Full Name'), ENT_QUOTES, 'UTF-8') ?> *

                            <input
                                name="full_name"
                                value="<?= old('full_name') ?>"
                                placeholder="<?= htmlspecialchars(t('As per NIC / Birth Certificate'), ENT_QUOTES, 'UTF-8') ?>"
                                maxlength="150"
                                required
                            >

                        </label>


                        <!--
                            this is for identification
                        -->

                        <div class="aid-identification-block">

                            <label class="aid-identification-title">
                                <?= htmlspecialchars(t('Identification'), ENT_QUOTES, 'UTF-8') ?> *
                            </label>

                            <div class="aid-identification-options">

                                <label class="aid-identification-choice">
                                    <input
                                        type="checkbox"
                                        id="use_nic"
                                        name="use_nic"
                                        <?= $useNicSelected ? 'checked' : '' ?>
                                    >
                                    <span>NIC</span>
                                </label>


                                <label class="aid-identification-choice">
                                    <input
                                        type="checkbox"
                                        id="use_elders_card"
                                        name="use_elders_card"
                                        <?= $useEldersCardSelected ? 'checked' : '' ?>
                                    >
                                    <span><?= htmlspecialchars(t("Elders' Identity Card"), ENT_QUOTES, 'UTF-8') ?></span>
                                </label>

                            </div>


                            <small
                                id="identification-error"
                                class="aid-identification-error"
                                hidden
                            >
                                <?= htmlspecialchars(t('Select at least one identification method.'), ENT_QUOTES, 'UTF-8') ?>
                            </small>

                            <!-- Live history prevents an avoidable submission when probation is active. -->
                            <div id="eligibility-preview" class="eligibility-preview" aria-live="polite" hidden></div>


                            <!-- NIC field -->
                            <label
                                id="nic-identification-field"
                                class="aid-identification-input"
                                hidden
                            >
                                <?= htmlspecialchars(t('NIC Number'), ENT_QUOTES, 'UTF-8') ?>

                                <input
                                    type="text"
                                    id="nic"
                                    name="nic"
                                    value="<?= old('nic') ?>"
                                    maxlength="20"
                                    placeholder="<?= htmlspecialchars(t('e.g. 901234567V or 199012345678'), ENT_QUOTES, 'UTF-8') ?>"
                                >
                            </label>


                            <!-- Elders' Identity Card field -->
                            <label
                                id="elders-card-identification-field"
                                class="aid-identification-input"
                                hidden
                            >
                                <?= htmlspecialchars(t("Elders' Identity Card Number"), ENT_QUOTES, 'UTF-8') ?>

                                <input
                                    type="text"
                                    id="elders_card_number"
                                    name="elders_card_number"
                                    value="<?= old('elders_card_number') ?>"
                                    maxlength="30"
                                    placeholder="<?= htmlspecialchars(t("Enter Elders' Identity Card number"), ENT_QUOTES, 'UTF-8') ?>"
                                >
                            </label>

                        </div>

                    <!-- Close the identity row before starting the demographic fields. -->
                    </div>

                    <div class="aid-form-grid three-columns">


                        <!-- Date of Birth -->

                        <label>

                            <?= htmlspecialchars(t('Date of Birth'), ENT_QUOTES, 'UTF-8') ?> *

                            <input
                                type="date"
                                name="date_of_birth"
                                value="<?= old('date_of_birth') ?>"
                                required
                            >

                        </label>


                        <!-- Gender -->

                        <label>

                            <?= htmlspecialchars(t('Gender'), ENT_QUOTES, 'UTF-8') ?> *

                            <select
                                name="gender"
                                required
                            >

                                <option value="">
                                    <?= htmlspecialchars(t('Select'), ENT_QUOTES, 'UTF-8') ?>
                                </option>


                                <?php

                                foreach (
                                    [
                                        'male' => t('Male'),
                                        'female' => t('Female'),
                                        'other' => t('Other')
                                    ]
                                    as $k => $label
                                ):

                                ?>

                                    <option
                                        value="<?= $k ?>"

                                        <?= $v['gender'] === $k
                                            ? 'selected'
                                            : '' ?>
                                    >

                                        <?= $label ?>

                                    </option>

                                <?php endforeach; ?>


                            </select>

                        </label>


                        <!-- Phone -->

                        <label>

                            <?= htmlspecialchars(t('Phone Number'), ENT_QUOTES, 'UTF-8') ?>

                            <input
                                name="phone"
                                value="<?= old('phone') ?>"
                                placeholder="<?= htmlspecialchars(t('e.g. 077-1234567'), ENT_QUOTES, 'UTF-8') ?>"
                                maxlength="25"
                            >

                        </label>


                    </div>


                    <!-- Address -->

                    <label class="full-field">

                        <?= htmlspecialchars(t('Address'), ENT_QUOTES, 'UTF-8') ?> *

                        <textarea
                            name="address"
                            rows="2"
                            maxlength="255"
                            placeholder="<?= htmlspecialchars(t('Full residential address...'), ENT_QUOTES, 'UTF-8') ?>"
                            required
                        ><?= old('address') ?></textarea>

                    </label>


                </fieldset>



                <!-- ====================================================
                     DISABILITY AND AID DETAILS
                ===================================================== -->

                <fieldset>


                    <legend>
                        ♿ <?= htmlspecialchars(t('Disability & Aid Requested'), ENT_QUOTES, 'UTF-8') ?>
                    </legend>


                    <div class="aid-form-grid two-columns">


                        <!-- Disability -->

                        <label>

                            <?= htmlspecialchars(t('Nature of Disability'), ENT_QUOTES, 'UTF-8') ?> *

                            <select
                                name="disability_notes"
                                id="disability_notes"
                                required
                            >

                                <option value="">
                                    <?= htmlspecialchars(t('Select disability'), ENT_QUOTES, 'UTF-8') ?>
                                </option>


                                <?php foreach ($disabilityTypes as $d): ?>

                                    <option

                                        value="<?= htmlspecialchars(
                                            $d,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"

                                        <?= $v['disability_notes'] === $d
                                            ? 'selected'
                                            : '' ?>
                                    >

                                        <?= htmlspecialchars(
                                            $d,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>


                            </select>


                            <small class="form-field-help">
                                <?= htmlspecialchars(t('Managed by Admin in System Configuration'), ENT_QUOTES, 'UTF-8') ?>
                            </small>

                        </label>


                        <!-- Aid Type -->

                        <label>

                            <?= htmlspecialchars(t('Aid Requested'), ENT_QUOTES, 'UTF-8') ?> *

                            <select
                                name="item_id"
                                id="item_id"
                                required
                            >

                                <option value="">
                                    <?= htmlspecialchars(t('Select Aid Type'), ENT_QUOTES, 'UTF-8') ?>
                                </option>


                                <?php foreach ($aidTypes as $i): ?>

                                    <option

                                        value="<?= (int) ($i['id'] ?? 0) ?>"

                                        data-beneficiary-field-label="<?= htmlspecialchars($i['beneficiary_field_label'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                        data-beneficiary-field-type="<?= htmlspecialchars($i['beneficiary_field_type'] ?? 'text', ENT_QUOTES, 'UTF-8') ?>"

                                        data-disability="<?= htmlspecialchars(
                                            $i['disability_name'] ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"

                                        <?= $v['item_id'] ==
                                            ($i['id'] ?? null)
                                            ? 'selected'
                                            : '' ?>
                                    >

                                        <?= htmlspecialchars(

                                            ($i['item_name'] ?? '') .

                                            (
                                                !empty($i['variety'])
                                                    ? ' — ' . $i['variety']
                                                    : ''
                                            )

                                        ) ?>

                                    </option>

                                <?php endforeach; ?>


                            </select>

                        </label>


                    </div>


                    <div class="aid-form-grid two-columns">


                        <!-- Quantity -->

                        <label>

                            <?= htmlspecialchars(t('Quantity'), ENT_QUOTES, 'UTF-8') ?> *

                            <input
                                type="number"
                                name="quantity"
                                min="1"
                                value="<?= old('quantity') ?>"
                                required
                            >

                        </label>


                        <!-- The selected aid item decides whether this required beneficiary value is shown. -->

                        <label
                            id="beneficiary-detail-field"
                            hidden
                        >

                            <span id="beneficiary-detail-label"></span> *

                            <input
                                type="text"
                                name="beneficiary_detail_value"
                                id="beneficiary_detail_value"
                                maxlength="255"
                                value="<?= old('beneficiary_detail_value') ?>"
                            >

                        </label>


                    </div>


                    <!-- Additional notes -->

                    <label class="full-field">

                        <?= htmlspecialchars(t('Additional Notes'), ENT_QUOTES, 'UTF-8') ?>

                        <textarea
                            name="notes"
                            rows="2"
                            maxlength="1000"
                            placeholder="<?= htmlspecialchars(t('Any additional information...'), ENT_QUOTES, 'UTF-8') ?>"
                        ><?= old('notes') ?></textarea>

                    </label>


                </fieldset>



                <!-- ====================================================
                     OFFICIAL APPROVALS
                ===================================================== -->

                <fieldset>


                    <legend>
                        ✅ <?= htmlspecialchars(t('Official Approvals'), ENT_QUOTES, 'UTF-8') ?>
                    </legend>


                    <p class="form-help">

                        <?= htmlspecialchars(t('Check each official who has approved this application.'), ENT_QUOTES, 'UTF-8') ?>

                    </p>


                    <div class="official-grid">


                        <!-- Government Medical Officer -->

                        <label>

                            <input
                                type="checkbox"
                                name="medical_officer"
                                <?= $approvalSelections['medical_officer']
                                    ? 'checked'
                                    : '' ?>
                            >

                            🩺 <?= htmlspecialchars(t('Government Medical Officer'), ENT_QUOTES, 'UTF-8') ?>

                        </label>


                        <!-- Grama Niladhari -->

                        <label>

                            <input
                                type="checkbox"
                                name="grama_niladhari"
                                <?= $approvalSelections['grama_niladhari']
                                    ? 'checked'
                                    : '' ?>
                            >

                            🏡 <?= htmlspecialchars(t('Grama Niladhari'), ENT_QUOTES, 'UTF-8') ?>

                        </label>


                        <!-- Social Services Officer -->

                        <label>

                            <input
                                type="checkbox"
                                name="social_services"
                                <?= $approvalSelections['social_services']
                                    ? 'checked'
                                    : '' ?>
                            >

                            🏛 <?= htmlspecialchars(t('Social Services Officer'), ENT_QUOTES, 'UTF-8') ?>

                        </label>


                        <!-- Divisional Secretary -->

                        <label>

                            <input
                                type="checkbox"
                                name="divisional_secretary"
                                <?= $approvalSelections['divisional_secretary']
                                    ? 'checked'
                                    : '' ?>
                            >

                            📋 <?= htmlspecialchars(t('Divisional Secretary'), ENT_QUOTES, 'UTF-8') ?>

                        </label>


                    </div>


                </fieldset>



                <!-- ====================================================
                     FORM ACTIONS
                ===================================================== -->

                <div class="aid-form-actions">


                    <!-- Submit -->

                    <button
                        name="submit_action"
                        value="submit"
                        class="submit-aid-button"
                    >

                        <?= htmlspecialchars($editingRequestId ? t('Save Changes') : t('Submit Aid Request'), ENT_QUOTES, 'UTF-8') ?>

                    </button>


                    <!-- Save Draft -->

                    <button
                        name="submit_action"
                        value="draft"
                        class="outline-action"
                        formnovalidate
                    >

                        <?= htmlspecialchars(t('Save as Draft'), ENT_QUOTES, 'UTF-8') ?>

                    </button>


                </div>


            </form>


        </section>
        <?php endif; ?>



        <!-- ============================================================
             SUBMITTED AID REQUESTS
        ============================================================= -->

        <?php if (!$showRequestForm): ?>
        <!-- A standalone guide gives the approval symbols their own clear space above the request card. -->
        <div class="approval-legend approval-legend-standalone" aria-label="<?= htmlspecialchars(t('Approval icon meanings'), ENT_QUOTES, 'UTF-8') ?>">
            <strong class="approval-legend-title"><?= htmlspecialchars(t('Approval guide'), ENT_QUOTES, 'UTF-8') ?></strong>
            <span>🩺 <?= htmlspecialchars(t('Government Medical Officer'), ENT_QUOTES, 'UTF-8') ?></span><span>🏡 <?= htmlspecialchars(t('Grama Niladhari'), ENT_QUOTES, 'UTF-8') ?></span>
            <span>🏛 <?= htmlspecialchars(t('Social Services Officer'), ENT_QUOTES, 'UTF-8') ?></span><span>📋 <?= htmlspecialchars(t('Divisional Secretary'), ENT_QUOTES, 'UTF-8') ?></span><span>❌ <?= htmlspecialchars(t('Not approved'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <section class="submitted-requests-card">


            <div class="submitted-header">


                <h2>
                    <?= htmlspecialchars(t('My Submitted Requests'), ENT_QUOTES, 'UTF-8') ?>
                </h2>


                <div>

                    <!-- Keep the create action beside the request-list controls. -->
                    <a class="new-aid-request-button" href="dashboard.php?page=new-aid-request">
                        <?= htmlspecialchars(t('New Aid Request'), ENT_QUOTES, 'UTF-8') ?>
                    </a>


                    <!-- Search submitted requests -->

                    <input
                        id="request-search"
                        type="search"
                        placeholder="<?= htmlspecialchars(t('Search name or NIC...'), ENT_QUOTES, 'UTF-8') ?>"
                    >


                    <!-- Filter by request status -->

                    <select id="request-status">

                        <option value="">
                            <?= htmlspecialchars(t('All Status'), ENT_QUOTES, 'UTF-8') ?>
                        </option>

                        <option value="draft">
                            <?= htmlspecialchars(t('Draft'), ENT_QUOTES, 'UTF-8') ?>
                        </option>

                        <option value="pending">
                            <?= htmlspecialchars(t('Pending'), ENT_QUOTES, 'UTF-8') ?>
                        </option>

                        <option value="approved">
                            <?= htmlspecialchars(t('Approved'), ENT_QUOTES, 'UTF-8') ?>
                        </option>

                        <option value="rejected">
                            <?= htmlspecialchars(t('Rejected'), ENT_QUOTES, 'UTF-8') ?>
                        </option>

                        <option value="goods-requested">
                            <?= htmlspecialchars(t('Goods Requested'), ENT_QUOTES, 'UTF-8') ?>
                        </option>

                        <option value="distributed">
                            <?= htmlspecialchars(t('Distributed'), ENT_QUOTES, 'UTF-8') ?>
                        </option>

                    </select>


                </div>


            </div>



            <div class="submitted-table-wrap">


                <table
                    class="submitted-table"
                    id="submitted-requests-table"
                >


                    <thead>

                        <tr>

                            <th><?= htmlspecialchars(t('ID'), ENT_QUOTES, 'UTF-8') ?></th>

                            <th><?= htmlspecialchars(t('Beneficiary'), ENT_QUOTES, 'UTF-8') ?></th>

                            <th>NIC</th>

                            <th><?= htmlspecialchars(t('Age'), ENT_QUOTES, 'UTF-8') ?></th>

                            <th><?= htmlspecialchars(t('District'), ENT_QUOTES, 'UTF-8') ?></th>

                            <th><?= htmlspecialchars(t('DS Division'), ENT_QUOTES, 'UTF-8') ?></th>

                            <th><?= htmlspecialchars(t('Aid Requested'), ENT_QUOTES, 'UTF-8') ?></th>

                            <th><?= htmlspecialchars(t('Approvals'), ENT_QUOTES, 'UTF-8') ?></th>

                            <th><?= htmlspecialchars(t('Submitted'), ENT_QUOTES, 'UTF-8') ?></th>

                            <th><?= htmlspecialchars(t('Status'), ENT_QUOTES, 'UTF-8') ?></th>

                            <th><?= htmlspecialchars(t('Notes'), ENT_QUOTES, 'UTF-8') ?></th>

                            <th><?= htmlspecialchars(t('Action'), ENT_QUOTES, 'UTF-8') ?></th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if (!$requests): ?>


                        <tr>

                            <td colspan="12">
                                <?= htmlspecialchars(t('No requests yet.'), ENT_QUOTES, 'UTF-8') ?>
                            </td>

                        </tr>


                    <?php else: ?>


                        <?php foreach ($requests as $r): ?>


                            <tr
                                data-status="<?= htmlspecialchars(
                                    $r['status']
                                ) ?>"
                            >


                                <!-- Aid Request ID -->

                                <td>

                                    AR-<?= str_pad(
                                        (string) $r['id'],
                                        4,
                                        '0',
                                        STR_PAD_LEFT
                                    ) ?>

                                </td>


                                <!-- Beneficiary Name -->

                                <td>

                                    <strong>

                                        <?= htmlspecialchars(
                                            $r['full_name']
                                        ) ?>

                                    </strong>

                                </td>


                                <!-- Beneficiary NIC -->

                                <td>

                                    <?= htmlspecialchars(
                                        $r['nic'] ?: '—'
                                    ) ?>

                                </td>


                                <!-- Beneficiary Age -->

                                <td>

                                    <?= requestAge(
                                        $r['date_of_birth']
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


                                <!-- Aid Item -->

                                <td>

                                    <?= htmlspecialchars(

                                        $r['item_name'] .

                                        (
                                            $r['variety']
                                                ? ' (' .
                                                    $r['variety'] .
                                                    ')'
                                                : ''
                                        )

                                    ) ?>


                                    <!-- The submitted label/value comes from the Subject Officer's item configuration. -->
                                    <?php if (
                                        !empty($r['beneficiary_detail_label']) &&
                                        $r['beneficiary_detail_value'] !== null
                                    ): ?>

                                        <small class="request-beneficiary-detail">

                                            <?= htmlspecialchars($r['beneficiary_detail_label'], ENT_QUOTES, 'UTF-8') ?>:
                                            <?= htmlspecialchars($r['beneficiary_detail_value'], ENT_QUOTES, 'UTF-8') ?>

                                        </small>

                                    <?php endif; ?>


                                </td>


                                <!-- Official Approval Indicators -->

                                <td>


                                    <span
                                        title="Government Medical Officer"
                                    >

                                        <?= $r['medical_officer_approved']
                                            ? '🩺'
                                            : '❌' ?>

                                    </span>


                                    <span
                                        title="Grama Niladhari"
                                    >

                                        <?= $r['grama_niladhari_approved']
                                            ? '🏡'
                                            : '❌' ?>

                                    </span>


                                    <span
                                        title="Social Services Officer"
                                    >

                                        <?= $r['social_services_approved']
                                            ? '🏛'
                                            : '❌' ?>

                                    </span>


                                    <span
                                        title="Divisional Secretary"
                                    >

                                        <?= $r['divisional_secretary_approved']
                                            ? '📋'
                                            : '❌' ?>

                                    </span>


                                </td>


                                <!-- Submitted Date -->

                                <td>

                                    <?= requestSubmitted(
                                        $r['created_at']
                                    ) ?>

                                </td>


                                <!-- Request Status -->

                                <td>

                                    <span
                                        class="
                                            request-status-pill
                                            status-<?= htmlspecialchars(
                                                $r['status']
                                            ) ?>
                                        "
                                    >

                                        <?= htmlspecialchars(
                                            t(ucwords(str_replace('-', ' ', $r['status']))),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </span>

                                </td>


                                <!-- Notes / Admin Rejection Reason -->

                                <td>

                                    <?= htmlspecialchars(

                                        $r['rejection_reason']

                                            ?: (
                                                $r['notes']
                                                    ?: '—'
                                            )

                                    ) ?>

                                </td>

                                <!-- Editable requests expose corrections before review; operational requests stay read-only. -->
                                <td>
                                    <?php if (in_array($r['status'], ['draft', 'pending'], true)): ?>
                                        <div class="request-row-actions">
                                            <a class="outline-action" href="dashboard.php?page=new-aid-request&amp;edit_request_id=<?= (int) $r['id'] ?>"><?= htmlspecialchars(t('Edit'), ENT_QUOTES, 'UTF-8') ?></a>
                                            <form method="post" data-request-delete-form>
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="request_action" value="delete">
                                                <input type="hidden" name="request_id" value="<?= (int) $r['id'] ?>">
                                                <button class="request-delete-button" type="submit"><?= htmlspecialchars(t('Delete'), ENT_QUOTES, 'UTF-8') ?></button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span class="request-read-only">—</span>
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

        <!-- This dialog confirms permanent deletion without relying on a browser popup. -->
        <dialog class="aid-request-delete-dialog" id="aid-request-delete-dialog">
            <div class="aid-request-delete-card">
                <button class="aid-request-delete-close" type="button" aria-label="<?= htmlspecialchars(t('Close'), ENT_QUOTES, 'UTF-8') ?>">&times;</button>
                <span class="aid-request-delete-icon" aria-hidden="true">!</span>
                <h2><?= htmlspecialchars(t('Remove Aid Request'), ENT_QUOTES, 'UTF-8') ?></h2>
                <p><?= htmlspecialchars(t('This action permanently removes the request. Do you want to continue?'), ENT_QUOTES, 'UTF-8') ?></p>
                <div class="aid-request-delete-actions">
                    <button class="aid-request-delete-cancel" type="button"><?= htmlspecialchars(t('Cancel'), ENT_QUOTES, 'UTF-8') ?></button>
                    <button class="aid-request-delete-confirm" type="button"><?= htmlspecialchars(t('Delete'), ENT_QUOTES, 'UTF-8') ?></button>
                </div>
            </div>
        </dialog>


    </main>


</div>



<!-- ==================================================================
     WIDMS SHARED DASHBOARD JAVASCRIPT
=================================================================== -->

<script src="assets/js/admin-dashboard.js"></script>


<!--
    Handles:
    - District / DS / GN filtering
    - NIC required behaviour
    - Prescription field behaviour
-->

<script src="assets/js/beneficiary-form.js?v=2"></script>



<script src="assets/js/aid-request-identification.js"></script>
<script src="assets/js/aid-request-actions.js?v=1"></script>
<script>
/*
|--------------------------------------------------------------------------
| 14. PRESCRIPTION POWER FIELD
|--------------------------------------------------------------------------
| Show prescription power only when required by selected aid type.
|--------------------------------------------------------------------------
*/

const item =
    document.getElementById('item_id'),

    disability =
        document.getElementById('disability_notes'),

    field =
        document.getElementById('beneficiary-detail-field'),

    detailLabel =
        document.getElementById('beneficiary-detail-label'),

    detailValue =
        document.getElementById('beneficiary_detail_value');


function beneficiaryDetailField() {
    const selected = item?.selectedOptions[0];
    const label = selected?.dataset.beneficiaryFieldLabel || '';
    const type = selected?.dataset.beneficiaryFieldType || 'text';
    const required = label !== '';

    field.hidden = !required;
    detailValue.required = required;
    detailValue.type = type === 'number' ? 'number' : 'text';
    detailValue.step = type === 'number' ? 'any' : '';
    detailLabel.textContent = label;

    if (!required) detailValue.value = '';
}

// Keep the aid list synchronized with the configured disability-to-item rules.
function filterAidItems() {
    if (!item || !disability) return;
    [...item.options].slice(1).forEach(option => {
        const visible = disability.value !== '' && option.dataset.disability === disability.value;
        option.hidden = !visible;
        option.disabled = !visible;
    });
    if (item.selectedOptions[0]?.disabled) item.value = '';
    beneficiaryDetailField();
}


item?.addEventListener(
    'change',
    beneficiaryDetailField
);

disability?.addEventListener('change', filterAidItems);

filterAidItems();



/*
|--------------------------------------------------------------------------
| 15. SUBMITTED REQUEST SEARCH AND STATUS FILTER
|--------------------------------------------------------------------------
*/

const search =
        document.getElementById('request-search'),

    statusFilter =
        document.getElementById('request-status'),

    requestRows = [
        ...document.querySelectorAll(
            '#submitted-requests-table tbody tr[data-status]'
        )
    ];


/*
|--------------------------------------------------------------------------
| Filter rows based on:
|
| 1. Search text
| 2. Selected request status
|--------------------------------------------------------------------------
*/

function filterRequests() {

    const term =
            (search?.value || '')
                .toLowerCase(),

        status =
            statusFilter?.value || '';


    requestRows.forEach(row => {

        row.hidden = !(

            row.textContent
                .toLowerCase()
                .includes(term)

            &&

            (
                !status ||
                row.dataset.status === status
            )
        );

    });
}


/*
|--------------------------------------------------------------------------
| Run filtering whenever search text changes.
|--------------------------------------------------------------------------
*/

search?.addEventListener(
    'input',
    filterRequests
);


/*
|--------------------------------------------------------------------------
| Run filtering whenever request status changes.
|--------------------------------------------------------------------------
*/

statusFilter?.addEventListener(
    'change',
    filterRequests
);

</script>


</body>

</html>
