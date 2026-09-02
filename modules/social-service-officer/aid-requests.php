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

$activePage = $subject
    ? 'aid-distribution'
    : 'aid-requests';

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
    'prescribed_power' => '',
    'notes' => '',
];


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
| 8. PROCESS FORM SUBMISSION
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

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


    // District, DS Division and GN Division must be selected.
    if (!$district || !$ds || !$gn) {
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
    | Check official approvals
    |--------------------------------------------------------------------------
    | All four approvals are required when submitting.
    |
    | They are NOT required when saving a draft.
    |--------------------------------------------------------------------------
    */

    if (
        !$saveDraft &&
        !array_product(
            array_map('intval', $signoffs)
        )
    ) {
        $errors[] =
            'All four official approvals are required before submission.';
    }


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


            /*
            |--------------------------------------------------------------------------
            | 10.2 Load selected aid item
            |--------------------------------------------------------------------------
            */

            $q = $db->prepare(
                "SELECT
                    i.*,
                    c.name category_name,
                    c.distribution_type

                 FROM inventory_items i

                 JOIN item_categories c
                    ON c.id = i.category_id

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


            /*
            |--------------------------------------------------------------------------
            | 10.3 Determine whether prescription power is required
            |--------------------------------------------------------------------------
            |
            | Used for:
            | - Contact Lens
            | - Spectacles
            | - Glasses
            |--------------------------------------------------------------------------
            */

            $itemText = strtolower(
                $aid['item_name'] . ' ' .
                $aid['variety'] . ' ' .
                $aid['category_name']
            );


            $requiresPower =
                (
                    str_contains($itemText, 'contact') &&
                    str_contains($itemText, 'lens')
                )
                ||
                str_contains($itemText, 'spectacle')
                ||
                str_contains($itemText, 'glasses');


            $power = null;


            /*
            |--------------------------------------------------------------------------
            | Validate prescription power when required.
            |--------------------------------------------------------------------------
            */

            if ($requiresPower) {

                if (
                    $v['prescribed_power'] === '' ||
                    !is_numeric($v['prescribed_power'])
                ) {
                    throw new RuntimeException(
                        'Prescription power is required for the selected vision aid.'
                    );
                }


                $power = round(
                    (float) $v['prescribed_power'],
                    2
                );


                if (
                    $power < -30 ||
                    $power > 30
                ) {
                    throw new RuntimeException(
                        'Prescription power must be between -30.00 and +30.00.'
                    );
                }
            }


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
                        $item
                    );


                if (!$eligibility['eligible']) {
                    throw new RuntimeException(
                        $eligibility['reason']
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | 10.8 CHECK FOR EXISTING ACTIVE AID REQUEST
            |--------------------------------------------------------------------------
            |
            | Prevent multiple active requests for the same beneficiary
            | and same item category.
            |--------------------------------------------------------------------------
            */

            $q = $db->prepare(
                "SELECT COUNT(*)

                 FROM aid_requests ar

                 JOIN inventory_items i
                    ON i.id = ar.item_id

                 WHERE ar.beneficiary_id = :beneficiary

                   AND i.category_id = :category

                   AND ar.status IN (
                       'pending',
                       'approved',
                       'goods-requested'
                   )"
            );


            $q->execute([
                'beneficiary' =>
                    $beneficiary,

                'category' =>
                    $aid['category_id'],
            ]);


            if ($q->fetchColumn()) {

                throw new RuntimeException(
                    'An active request already exists for this beneficiary and aid category.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | 10.9 Determine aid request status
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

            $db->prepare(
                'INSERT INTO aid_requests(
                    beneficiary_id,
                    item_id,
                    quantity,
                    disability_notes,
                    prescribed_power,
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


            /*
            |--------------------------------------------------------------------------
            | Get automatically generated aid request ID.
            |--------------------------------------------------------------------------
            */

            $id =
                (int) $db->lastInsertId();


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

                $saveDraft
                    ? 'Saved aid request draft'
                    : 'Submitted aid request',

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
                $saveDraft

                    ? 'Aid request saved as draft.'

                    : 'Aid request submitted for Admin  approval.';


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
            ds.name

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

    $aidTypes = $db->query(
        "SELECT
            i.id,
            i.item_name,
            i.variety,
            COALESCE(c.name, i.category) category_name,

            (
                LOWER(
                    CONCAT(
                        i.item_name,
                        ' ',
                        i.variety,
                        ' ',
                        COALESCE(c.name, i.category)
                    )
                ) LIKE '%contact%lens%'

                OR LOWER(i.item_name)
                    IN ('spectacles','glasses')
            ) requires_power

         FROM inventory_items i

         LEFT JOIN item_categories c
            ON c.id = i.category_id

         WHERE i.item_name IN (
             'Contact Lens',
             'Spectacles',
             'Glasses',
             'Wheelchair',
             'Crutches',
             'Hearing Aid',
             'Tricycle'
         )

         ORDER BY FIELD(
             i.item_name,
             'Contact Lens',
             'Spectacles',
             'Glasses',
             'Wheelchair',
             'Crutches',
             'Hearing Aid',
             'Tricycle'
         ),
         i.variety"
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

<html>

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width,initial-scale=1"
    >

    <title>My Aid Requests</title>


    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- WIDMS main dashboard CSS -->

    <link
        href="assets/css/admin-dashboard.css"
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

        <h1>My Requests</h1>

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

        <section class="aid-form-card">


            <div class="aid-card-header">

                <h2>
                    📋 Submit New Aid Distribution Request
                </h2>

                <small>
                    All fields marked * are required
                </small>

            </div>


            <form
                method="post"
                class="aid-request-form"
                id="aid-request-form"
            >


                <!-- CSRF Security Token -->

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
                        📍 Location Details
                    </legend>


                    <div class="aid-form-grid three-columns">


                        <!-- District -->

                        <label>

                            District *

                            <select
                                id="district_id"
                                name="district_id"
                                required
                            >

                                <option value="">
                                    Select District
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

                            D.S. Division *

                            <select
                                id="ds_division_id"
                                name="ds_division_id"
                                required
                            >

                                <option value="">
                                    Select DS Division
                                </option>


                                <?php foreach ($dsDivisions as $r): ?>

                                    <option

                                        value="<?= (int) $r['id'] ?>"

                                        data-parent="<?= (int) $r['district_id'] ?>"

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

                            G.N. Division *

                            <select
                                id="gn_division_id"
                                name="gn_division_id"
                                required
                            >

                                <option value="">
                                    Select GN Division
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

                        </label>


                    </div>


                </fieldset>



                <!-- ====================================================
                     BENEFICIARY DETAILS
                ===================================================== -->

                <fieldset>


                    <legend>
                        👤 Beneficiary Details
                    </legend>


                    <div class="aid-form-grid two-columns">


                        <!-- Beneficiary Full Name -->

                        <label>

                            Full Name *

                            <input
                                name="full_name"
                                value="<?= old('full_name') ?>"
                                placeholder="As per NIC / Birth Certificate"
                                maxlength="150"
                                required
                            >

                        </label>


                        <!--
                            this is for identification
                        -->

                        <div class="aid-identification-block">

                            <label class="aid-identification-title">
                                Identification *
                            </label>

                            <div class="aid-identification-options">

                                <label class="aid-identification-choice">
                                    <input
                                        type="checkbox"
                                        id="use_nic"
                                        name="use_nic"
                                        <?= isset($_POST['use_nic']) ? 'checked' : '' ?>
                                    >
                                    <span>NIC</span>
                                </label>


                                <label class="aid-identification-choice">
                                    <input
                                        type="checkbox"
                                        id="use_elders_card"
                                        name="use_elders_card"
                                        <?= isset($_POST['use_elders_card']) ? 'checked' : '' ?>
                                    >
                                    <span>Elders' Identity Card</span>
                                </label>

                            </div>


                            <small
                                id="identification-error"
                                class="aid-identification-error"
                                hidden
                            >
                                Select at least one identification method.
                            </small>


                            <!-- NIC field -->
                            <label
                                id="nic-identification-field"
                                class="aid-identification-input"
                                hidden
                            >
                                NIC Number

                                <input
                                    type="text"
                                    id="nic"
                                    name="nic"
                                    value="<?= old('nic') ?>"
                                    maxlength="20"
                                    placeholder="e.g. 901234567V or 199012345678"
                                >
                            </label>


                            <!-- Elders' Identity Card field -->
                            <label
                                id="elders-card-identification-field"
                                class="aid-identification-input"
                                hidden
                            >
                                Elders' Identity Card Number

                                <input
                                    type="text"
                                    id="elders_card_number"
                                    name="elders_card_number"
                                    value="<?= old('elders_card_number') ?>"
                                    maxlength="30"
                                    placeholder="Enter Elders' Identity Card number"
                                >
                            </label>

                        </div>


                    <div class="aid-form-grid three-columns">


                        <!-- Date of Birth -->

                        <label>

                            Date of Birth *

                            <input
                                type="date"
                                name="date_of_birth"
                                value="<?= old('date_of_birth') ?>"
                                required
                            >

                        </label>


                        <!-- Gender -->

                        <label>

                            Gender *

                            <select
                                name="gender"
                                required
                            >

                                <option value="">
                                    Select
                                </option>


                                <?php

                                foreach (
                                    [
                                        'male' => 'Male',
                                        'female' => 'Female',
                                        'other' => 'Other'
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

                            Phone Number

                            <input
                                name="phone"
                                value="<?= old('phone') ?>"
                                placeholder="e.g. 077-1234567"
                                maxlength="25"
                            >

                        </label>


                    </div>


                    <!-- Address -->

                    <label class="full-field">

                        Address *

                        <textarea
                            name="address"
                            rows="2"
                            maxlength="255"
                            placeholder="Full residential address..."
                            required
                        ><?= old('address') ?></textarea>

                    </label>


                </fieldset>



                <!-- ====================================================
                     DISABILITY AND AID DETAILS
                ===================================================== -->

                <fieldset>


                    <legend>
                        ♿ Disability &amp; Aid Requested
                    </legend>


                    <div class="aid-form-grid two-columns">


                        <!-- Disability -->

                        <label>

                            Nature of Disability *

                            <select
                                name="disability_notes"
                                required
                            >

                                <option value="">
                                    Select disability
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
                                Managed by Admin in System Configuration
                            </small>

                        </label>


                        <!-- Aid Type -->

                        <label>

                            Aid Requested *

                            <select
                                name="item_id"
                                id="item_id"
                                required
                            >

                                <option value="">
                                    Select Aid Type
                                </option>


                                <?php foreach ($aidTypes as $i): ?>

                                    <option

                                        value="<?= (int) ($i['id'] ?? 0) ?>"

                                        data-requires-power="<?= (int) (
                                            $i['requires_power'] ?? 0
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

                            Quantity *

                            <input
                                type="number"
                                name="quantity"
                                min="1"
                                value="<?= old('quantity') ?>"
                                required
                            >

                        </label>


                        <!--
                            Prescription Power

                            This field is hidden unless the selected
                            aid requires prescription power.
                        -->

                        <label
                            id="power-field"
                            hidden
                        >

                            Prescription Power *

                            <input
                                type="number"
                                name="prescribed_power"
                                id="prescribed_power"
                                min="-30"
                                max="30"
                                step="0.01"
                                value="<?= old('prescribed_power') ?>"
                                placeholder="e.g. -2.00 or +1.50"
                            >

                        </label>


                    </div>


                    <!-- Additional notes -->

                    <label class="full-field">

                        Additional Notes

                        <textarea
                            name="notes"
                            rows="2"
                            maxlength="1000"
                            placeholder="Any additional information..."
                        ><?= old('notes') ?></textarea>

                    </label>


                </fieldset>



                <!-- ====================================================
                     OFFICIAL APPROVALS
                ===================================================== -->

                <fieldset>


                    <legend>
                        ✅ Official Approvals
                    </legend>


                    <p class="form-help">

                        Check each official who has approved
                        this application.

                    </p>


                    <div class="official-grid">


                        <!-- Government Medical Officer -->

                        <label>

                            <input
                                type="checkbox"
                                name="medical_officer"
                                <?= isset($_POST['medical_officer'])
                                    ? 'checked'
                                    : '' ?>
                            >

                            🩺 Government Medical Officer

                        </label>


                        <!-- Grama Niladhari -->

                        <label>

                            <input
                                type="checkbox"
                                name="grama_niladhari"
                                <?= isset($_POST['grama_niladhari'])
                                    ? 'checked'
                                    : '' ?>
                            >

                            🏡 Grama Niladhari

                        </label>


                        <!-- Social Services Officer -->

                        <label>

                            <input
                                type="checkbox"
                                name="social_services"
                                <?= isset($_POST['social_services'])
                                    ? 'checked'
                                    : '' ?>
                            >

                            🏛 Social Services Officer

                        </label>


                        <!-- Divisional Secretary -->

                        <label>

                            <input
                                type="checkbox"
                                name="divisional_secretary"
                                <?= isset($_POST['divisional_secretary'])
                                    ? 'checked'
                                    : '' ?>
                            >

                            📋 Divisional Secretary

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

                        Submit Aid Request

                    </button>


                    <!-- Save Draft -->

                    <button
                        name="submit_action"
                        value="draft"
                        class="outline-action"
                        formnovalidate
                    >

                        Save as Draft

                    </button>


                    <small>

                        Submitted requests are sent
                        to Admin for final approval

                    </small>


                </div>


            </form>


        </section>



        <!-- ============================================================
             SUBMITTED AID REQUESTS
        ============================================================= -->

        <section class="submitted-requests-card">


            <div class="submitted-header">


                <h2>
                    My Submitted Requests
                </h2>


                <div>


                    <!-- Search submitted requests -->

                    <input
                        id="request-search"
                        type="search"
                        placeholder="Search name or NIC..."
                    >


                    <!-- Filter by request status -->

                    <select id="request-status">

                        <option value="">
                            All Status
                        </option>

                        <option value="draft">
                            Draft
                        </option>

                        <option value="pending">
                            Pending
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



            <div class="submitted-table-wrap">


                <table
                    class="submitted-table"
                    id="submitted-requests-table"
                >


                    <thead>

                        <tr>

                            <th>ID</th>

                            <th>Beneficiary</th>

                            <th>NIC</th>

                            <th>Age</th>

                            <th>District</th>

                            <th>DS Division</th>

                            <th>Aid Requested</th>

                            <th>Approvals</th>

                            <th>Submitted</th>

                            <th>Status</th>

                            <th>Notes</th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if (!$requests): ?>


                        <tr>

                            <td colspan="11">
                                No requests yet.
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


                                    <!-- Prescription Power -->

                                    <?php if (
                                        $r['prescribed_power'] !== null
                                    ): ?>

                                        <small class="lens-power-row">

                                            Power:

                                            <?= sprintf(
                                                '%+.2f',
                                                (float) $r['prescribed_power']
                                            ) ?>

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
                                            : '◻' ?>

                                    </span>


                                    <span
                                        title="Grama Niladhari"
                                    >

                                        <?= $r['grama_niladhari_approved']
                                            ? '🏡'
                                            : '◻' ?>

                                    </span>


                                    <span
                                        title="Social Services Officer"
                                    >

                                        <?= $r['social_services_approved']
                                            ? '🏛'
                                            : '◻' ?>

                                    </span>


                                    <span
                                        title="Divisional Secretary"
                                    >

                                        <?= $r['divisional_secretary_approved']
                                            ? '📋'
                                            : '◻' ?>

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

                                        <?= ucwords(
                                            str_replace(
                                                '-',
                                                ' ',
                                                $r['status']
                                            )
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
     WIDMS SHARED DASHBOARD JAVASCRIPT
=================================================================== -->

<script src="assets/js/admin-dashboard.js"></script>


<!--
    Handles:
    - District / DS / GN filtering
    - NIC required behaviour
    - Prescription field behaviour
-->

<script src="assets/js/beneficiary-form.js"></script>



<script src="assets/js/aid-request-identification.js"></script>
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

    field =
        document.getElementById('power-field'),

    power =
        document.getElementById('prescribed_power');


function prescriptionField() {

    const required =
        item?.selectedOptions[0]
            ?.dataset.requiresPower === '1';


    field.hidden =
        !required;


    power.required =
        required;


    if (!required) {
        power.value = '';
    }
}


item?.addEventListener(
    'change',
    prescriptionField
);


prescriptionField();



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