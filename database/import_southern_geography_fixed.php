<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| WIDMS - Southern Province Geography Importer
|--------------------------------------------------------------------------
|
| Purpose:
|   Import the complete Southern Province administrative hierarchy into
|   the existing WIDMS geography tables:
|
|       districts
|           -> ds_divisions
|               -> gn_divisions
|
| Source:
|   Open Admin Data Sri Lanka GitHub static dataset
|   https://github.com/open-admin-data/sri-lanka-administrative-divisions
|
| Southern Province source ID:
|   LK3
|
| Expected hierarchy:
|   - 3 Districts
|   - 50 Divisional Secretariat Divisions
|   - 2,121 Grama Niladhari Divisions
|
| This script is intentionally idempotent:
|   - Missing records are inserted.
|   - Matching existing records are reused and keep their IDs.
|   - Southern Province rows absent from the source are removed.
|   - Foreign keys prevent removal when a legacy row is still in use.
|
| Run from the WIDMS project root:
|
|   php database/import_southern_geography_fixed.php
|
|--------------------------------------------------------------------------
*/

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../config/database.php';


/*
|--------------------------------------------------------------------------
| Configuration
|--------------------------------------------------------------------------
*/

const SOUTHERN_PROVINCE_ID = 'LK3';

const SOURCE_DISTRICTS =
    'https://raw.githubusercontent.com/open-admin-data/sri-lanka-administrative-divisions/main/data/all-district.json';

const SOURCE_DSD =
    'https://raw.githubusercontent.com/open-admin-data/sri-lanka-administrative-divisions/main/data/all-dsd.json';

const SOURCE_GND =
    'https://raw.githubusercontent.com/open-admin-data/sri-lanka-administrative-divisions/main/data/all-gnd.json';


/*
|--------------------------------------------------------------------------
| Expected Southern Province counts
|--------------------------------------------------------------------------
|
| These checks stop the import if the downloaded source does not contain
| the complete hierarchy expected for Southern Province.
|--------------------------------------------------------------------------
*/

const EXPECTED_DISTRICTS = 3;
const EXPECTED_DSD = 50;
const EXPECTED_GND = 2121;

const EXPECTED_BY_DISTRICT = [
    'Galle' => [
        'dsd' => 22,
        'gnd' => 895,
    ],
    'Matara' => [
        'dsd' => 16,
        'gnd' => 650,
    ],
    'Hambantota' => [
        'dsd' => 12,
        'gnd' => 576,
    ],
];


/*
|--------------------------------------------------------------------------
| Download JSON
|--------------------------------------------------------------------------
|
| Uses cURL when available and falls back to file_get_contents().
|--------------------------------------------------------------------------
*/

function downloadJson(string $url): array
{
    $body = false;

    /*
    |--------------------------------------------------------------------------
    | Try cURL first
    |--------------------------------------------------------------------------
    */
    if (function_exists('curl_init')) {

        $maxAttempts = 3;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {

            $curl = curl_init($url);

            curl_setopt_array(
                $curl,
                [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,

                    // Time allowed to establish connection
                    CURLOPT_CONNECTTIMEOUT => 30,

                    // Allow up to 10 minutes for large GN dataset
                    CURLOPT_TIMEOUT => 600,

                    CURLOPT_USERAGENT =>
                        'WIDMS-Southern-Geography-Importer/1.0',

                    CURLOPT_HTTPHEADER => [
                        'Accept: application/json',
                    ],

                    // Helps with some HTTPS connection issues
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                ]
            );

            $body = curl_exec($curl);

            $error = curl_error($curl);

            $status = (int) curl_getinfo(
                $curl,
                CURLINFO_HTTP_CODE
            );

            curl_close($curl);

            /*
            |--------------------------------------------------------------------------
            | Successful download
            |--------------------------------------------------------------------------
            */
            if (
                $body !== false
                &&
                $status >= 200
                &&
                $status < 300
            ) {
                break;
            }

            /*
            |--------------------------------------------------------------------------
            | Final attempt failed
            |--------------------------------------------------------------------------
            */
            if ($attempt === $maxAttempts) {

                if ($body === false) {
                    throw new RuntimeException(
                        'Unable to download geography data after ' .
                        $maxAttempts .
                        ' attempts: ' .
                        $error
                    );
                }

                throw new RuntimeException(
                    'Geography source returned HTTP ' .
                    $status .
                    '.'
                );
            }

            echo
                "Download failed. Retrying (" .
                ($attempt + 1) .
                "/" .
                $maxAttempts .
                ")...\n";

            sleep(3);
        }

    } else {

        /*
        |--------------------------------------------------------------------------
        | Fallback if cURL is unavailable
        |--------------------------------------------------------------------------
        */
        $context = stream_context_create(
            [
                'http' => [
                    'method' => 'GET',

                    // Allow 10 minutes
                    'timeout' => 600,

                    'header' =>
                        "Accept: application/json\r\n" .
                        "User-Agent: WIDMS-Southern-Geography-Importer/1.0\r\n",
                ],
            ]
        );

        $body = @file_get_contents(
            $url,
            false,
            $context
        );

        if ($body === false) {
            throw new RuntimeException(
                'Unable to download geography data. ' .
                'Enable the PHP cURL extension or check your internet connection.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Convert JSON into PHP array
    |--------------------------------------------------------------------------
    */
    $decoded = json_decode(
        (string) $body,
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    if (!is_array($decoded)) {
        throw new RuntimeException(
            'The geography source returned invalid JSON.'
        );
    }

    return $decoded;
}


/*
|--------------------------------------------------------------------------
| Extract records from API response
|--------------------------------------------------------------------------
|
| The helper accepts both:
|
|   { "data": [ ... ] }
|
| and:
|
|   { "data": { "district": [ ... ] } }
|
| response shapes.
|--------------------------------------------------------------------------
*/

function extractRecords(
    array $payload,
    string $level
): array {

    if (
        isset($payload['data'][$level])
        &&
        is_array($payload['data'][$level])
    ) {
        return $payload['data'][$level];
    }

    if (
        isset($payload['data'])
        &&
        is_array($payload['data'])
        &&
        array_is_list($payload['data'])
    ) {
        return $payload['data'];
    }

    if (
        isset($payload[$level])
        &&
        is_array($payload[$level])
    ) {
        return $payload[$level];
    }

    if (array_is_list($payload)) {
        return $payload;
    }

    throw new RuntimeException(
        'Unexpected API response format for level: ' . $level
    );
}


/*
|--------------------------------------------------------------------------
| Read Source Data
|--------------------------------------------------------------------------
*/

echo "Downloading Southern Province geography...\n";

$districtRecords = extractRecords(
    downloadJson(SOURCE_DISTRICTS),
    'district'
);

$dsdRecords = extractRecords(
    downloadJson(SOURCE_DSD),
    'dsd'
);

$gndRecords = extractRecords(
    downloadJson(SOURCE_GND),
    'gnd'
);


/*
|--------------------------------------------------------------------------
| Keep only Southern Province districts
|--------------------------------------------------------------------------
*/

$southernDistricts = array_values(
    array_filter(
        $districtRecords,
        static fn (array $row): bool =>
            (string) ($row['parent']['id'] ?? '')
                === SOUTHERN_PROVINCE_ID
    )
);

$districtExternalIds = [];

foreach ($southernDistricts as $district) {

    $externalId = (string) (
        $district['id'] ?? ''
    );

    if ($externalId !== '') {
        $districtExternalIds[$externalId] = true;
    }
}


/*
|--------------------------------------------------------------------------
| Keep only DS Divisions belonging to Southern Province districts
|--------------------------------------------------------------------------
*/

$southernDsd = array_values(
    array_filter(
        $dsdRecords,
        static fn (array $row): bool =>
            isset(
                $districtExternalIds[
                    (string) ($row['parent']['id'] ?? '')
                ]
            )
    )
);

$dsdExternalIds = [];

foreach ($southernDsd as $dsd) {

    $externalId = (string) (
        $dsd['id'] ?? ''
    );

    if ($externalId !== '') {
        $dsdExternalIds[$externalId] = true;
    }
}


/*
|--------------------------------------------------------------------------
| Keep only GN Divisions belonging to Southern Province DS Divisions
|--------------------------------------------------------------------------
*/

$southernGnd = array_values(
    array_filter(
        $gndRecords,
        static fn (array $row): bool =>
            isset(
                $dsdExternalIds[
                    (string) ($row['parent']['id'] ?? '')
                ]
            )
    )
);


/*
|--------------------------------------------------------------------------
| Validate Complete Source Dataset
|--------------------------------------------------------------------------
*/

if (count($southernDistricts) !== EXPECTED_DISTRICTS) {
    throw new RuntimeException(
        'Source validation failed: expected ' .
        EXPECTED_DISTRICTS .
        ' Southern Province districts, received ' .
        count($southernDistricts) .
        '.'
    );
}

if (count($southernDsd) !== EXPECTED_DSD) {
    throw new RuntimeException(
        'Source validation failed: expected ' .
        EXPECTED_DSD .
        ' Southern Province DS Divisions, received ' .
        count($southernDsd) .
        '.'
    );
}

if (count($southernGnd) !== EXPECTED_GND) {
    throw new RuntimeException(
        'Source validation failed: expected ' .
        EXPECTED_GND .
        ' Southern Province GN Divisions, received ' .
        count($southernGnd) .
        '.'
    );
}


/*
|--------------------------------------------------------------------------
| Validate district-by-district counts
|--------------------------------------------------------------------------
*/

$districtNameByExternalId = [];

foreach ($southernDistricts as $district) {

    $externalId = (string) (
        $district['id'] ?? ''
    );

    $name = trim(
        (string) (
            $district['name']['en'] ?? ''
        )
    );

    if ($externalId === '' || $name === '') {
        throw new RuntimeException(
            'A Southern Province district record is missing its ID or name.'
        );
    }

    $districtNameByExternalId[$externalId] = $name;
}

$dsdCountByDistrict = [];
$dsdDistrictByExternalId = [];

foreach ($southernDsd as $dsd) {

    $externalId = (string) (
        $dsd['id'] ?? ''
    );

    $districtExternalId = (string) (
        $dsd['parent']['id'] ?? ''
    );

    $districtName =
        $districtNameByExternalId[$districtExternalId]
        ?? null;

    if ($externalId === '' || $districtName === null) {
        throw new RuntimeException(
            'A DS Division has an invalid district relationship.'
        );
    }

    $dsdDistrictByExternalId[$externalId] =
        $districtName;

    $dsdCountByDistrict[$districtName] =
        ($dsdCountByDistrict[$districtName] ?? 0)
        + 1;
}

$gndCountByDistrict = [];

foreach ($southernGnd as $gnd) {

    $dsdExternalId = (string) (
        $gnd['parent']['id'] ?? ''
    );

    $districtName =
        $dsdDistrictByExternalId[$dsdExternalId]
        ?? null;

    if ($districtName === null) {
        throw new RuntimeException(
            'A GN Division has an invalid DS Division relationship.'
        );
    }

    $gndCountByDistrict[$districtName] =
        ($gndCountByDistrict[$districtName] ?? 0)
        + 1;
}

foreach (
    EXPECTED_BY_DISTRICT
    as $districtName => $expected
) {

    $actualDsd =
        $dsdCountByDistrict[$districtName]
        ?? 0;

    $actualGnd =
        $gndCountByDistrict[$districtName]
        ?? 0;

    if ($actualDsd !== $expected['dsd']) {
        throw new RuntimeException(
            $districtName .
            ': expected ' .
            $expected['dsd'] .
            ' DS Divisions, received ' .
            $actualDsd .
            '.'
        );
    }

    if ($actualGnd !== $expected['gnd']) {
        throw new RuntimeException(
            $districtName .
            ': expected ' .
            $expected['gnd'] .
            ' GN Divisions, received ' .
            $actualGnd .
            '.'
        );
    }
}

echo "Source validation passed:\n";

foreach (EXPECTED_BY_DISTRICT as $name => $expected) {
    echo '  - ' .
        $name .
        ': ' .
        $expected['dsd'] .
        ' DS, ' .
        $expected['gnd'] .
        " GN\n";
}

echo "\n";


/*
|--------------------------------------------------------------------------
| Connect to WIDMS Database
|--------------------------------------------------------------------------
*/

$db = database();


/*
|--------------------------------------------------------------------------
| Find an Admin User for created_by
|--------------------------------------------------------------------------
|
| Existing WIDMS geography tables require created_by.
|--------------------------------------------------------------------------
*/

$createdBy = $db->query(
    "SELECT id
     FROM users
     WHERE role='admin'
     ORDER BY
        CASE WHEN status='active' THEN 0 ELSE 1 END,
        id
     LIMIT 1"
)->fetchColumn();

if (!$createdBy) {
    throw new RuntimeException(
        'No Admin user exists. Create an Admin account before importing geography.'
    );
}

$createdBy = (int) $createdBy;


/*
|--------------------------------------------------------------------------
| Prepared Statements
|--------------------------------------------------------------------------
*/

/* District */
$findDistrict = $db->prepare(
    'SELECT id
     FROM districts
     WHERE name=:name
     LIMIT 1'
);

$insertDistrict = $db->prepare(
    "INSERT INTO districts(
        name,
        status,
        created_by
     )
     VALUES(
        :name,
        'active',
        :created_by
     )"
);

$activateDistrict = $db->prepare(
    "UPDATE districts
     SET status='active'
     WHERE id=:id"
);


/* DS Division */
$findDsd = $db->prepare(
    'SELECT id
     FROM ds_divisions
     WHERE district_id=:district
       AND name=:name
     LIMIT 1'
);

$insertDsd = $db->prepare(
    "INSERT INTO ds_divisions(
        district_id,
        name,
        status,
        created_by
     )
     VALUES(
        :district,
        :name,
        'active',
        :created_by
     )"
);

$activateDsd = $db->prepare(
    "UPDATE ds_divisions
     SET status='active'
     WHERE id=:id"
);


/* GN Division */
$findGnd = $db->prepare(
    'SELECT id
     FROM gn_divisions
     WHERE ds_division_id=:ds
       AND name=:name
     LIMIT 1'
);

$insertGnd = $db->prepare(
    "INSERT INTO gn_divisions(
        ds_division_id,
        name,
        status,
        created_by
     )
     VALUES(
        :ds,
        :name,
        'active',
        :created_by
     )"
);

$activateGnd = $db->prepare(
    "UPDATE gn_divisions
     SET status='active'
     WHERE id=:id"
);


/*
|--------------------------------------------------------------------------
| Import Counters
|--------------------------------------------------------------------------
*/

$inserted = [
    'districts' => 0,
    'dsd' => 0,
    'gnd' => 0,
];

$existing = [
    'districts' => 0,
    'dsd' => 0,
    'gnd' => 0,
];

$removed = [
    'dsd' => 0,
    'gnd' => 0,
];


/*
|--------------------------------------------------------------------------
| Import Geography
|--------------------------------------------------------------------------
*/

try {

    $db->beginTransaction();

    $db->exec(
        'CREATE TEMPORARY TABLE widms_geography_keep_dsd (
            id INT UNSIGNED PRIMARY KEY
        ) ENGINE=MEMORY'
    );
    $db->exec(
        'CREATE TEMPORARY TABLE widms_geography_keep_gnd (
            id INT UNSIGNED PRIMARY KEY
        ) ENGINE=MEMORY'
    );

    $keepDsd = $db->prepare(
        'INSERT INTO widms_geography_keep_dsd(id) VALUES(:id)'
    );
    $keepGnd = $db->prepare(
        'INSERT INTO widms_geography_keep_gnd(id) VALUES(:id)'
    );


    /*
    |--------------------------------------------------------------------------
    | Import Districts
    |--------------------------------------------------------------------------
    */

    $districtDatabaseIdByExternalId = [];

    foreach ($southernDistricts as $district) {

        $externalId = (string) $district['id'];

        $name = trim(
            (string) ($district['name']['en'] ?? '')
        );

        if ($name === '') {
            throw new RuntimeException(
                'A district has an empty English name.'
            );
        }

        $findDistrict->execute([
            'name' => $name,
        ]);

        $databaseId =
            $findDistrict->fetchColumn();

        if ($databaseId) {

            $databaseId = (int) $databaseId;

            $activateDistrict->execute([
                'id' => $databaseId,
            ]);

            $existing['districts']++;

        } else {

            $insertDistrict->execute([
                'name' => $name,
                'created_by' => $createdBy,
            ]);

            $databaseId =
                (int) $db->lastInsertId();

            $inserted['districts']++;
        }

        $districtDatabaseIdByExternalId[
            $externalId
        ] = $databaseId;
    }


    /*
    |--------------------------------------------------------------------------
    | Import Divisional Secretariat Divisions
    |--------------------------------------------------------------------------
    */

    $dsdDatabaseIdByExternalId = [];

    foreach ($southernDsd as $dsd) {

        $externalId =
            (string) $dsd['id'];

        $parentExternalId =
            (string) ($dsd['parent']['id'] ?? '');

        $name = trim(
            (string) ($dsd['name']['en'] ?? '')
        );

        $districtDatabaseId =
            $districtDatabaseIdByExternalId[
                $parentExternalId
            ]
            ?? null;

        if (!$districtDatabaseId) {
            throw new RuntimeException(
                'Unable to resolve parent district for DS Division: ' .
                $name
            );
        }

        if ($name === '') {
            throw new RuntimeException(
                'A DS Division has an empty English name.'
            );
        }

        $findDsd->execute([
            'district' => $districtDatabaseId,
            'name' => $name,
        ]);

        $databaseId =
            $findDsd->fetchColumn();

        if ($databaseId) {

            $databaseId = (int) $databaseId;

            $activateDsd->execute([
                'id' => $databaseId,
            ]);

            $existing['dsd']++;

        } else {

            $insertDsd->execute([
                'district' => $districtDatabaseId,
                'name' => $name,
                'created_by' => $createdBy,
            ]);

            $databaseId =
                (int) $db->lastInsertId();

            $inserted['dsd']++;
        }

        $dsdDatabaseIdByExternalId[
            $externalId
        ] = $databaseId;

        $keepDsd->execute(['id' => $databaseId]);
    }


    /*
    |--------------------------------------------------------------------------
    | Import Grama Niladhari Divisions
    |--------------------------------------------------------------------------
    */

    foreach ($southernGnd as $gnd) {

        $parentExternalId =
            (string) ($gnd['parent']['id'] ?? '');

        $name = trim(
            (string) ($gnd['name']['en'] ?? '')
        );

        $dsdDatabaseId =
            $dsdDatabaseIdByExternalId[
                $parentExternalId
            ]
            ?? null;

        if (!$dsdDatabaseId) {
            throw new RuntimeException(
                'Unable to resolve parent DS Division for GN Division: ' .
                $name
            );
        }

        if ($name === '') {
            throw new RuntimeException(
                'A GN Division has an empty English name.'
            );
        }

        $findGnd->execute([
            'ds' => $dsdDatabaseId,
            'name' => $name,
        ]);

        $databaseId =
            $findGnd->fetchColumn();

        if ($databaseId) {

            $databaseId = (int) $databaseId;

            $activateGnd->execute([
                'id' => $databaseId,
            ]);

            $existing['gnd']++;

        } else {

            $insertGnd->execute([
                'ds' => $dsdDatabaseId,
                'name' => $name,
                'created_by' => $createdBy,
            ]);

            $databaseId =
                (int) $db->lastInsertId();

            $inserted['gnd']++;
        }

        $keepGnd->execute(['id' => $databaseId]);
    }


    /*
    |--------------------------------------------------------------------------
    | Remove legacy Southern Province rows absent from the source
    |--------------------------------------------------------------------------
    |
    | Child rows are removed first. If an obsolete row is referenced by WIDMS,
    | its foreign key causes the transaction to roll back instead of silently
    | deleting or disconnecting operational data.
    |--------------------------------------------------------------------------
    */

    $deleteLegacyGnd = $db->prepare(
        "DELETE gn
         FROM gn_divisions gn
         JOIN ds_divisions ds ON ds.id=gn.ds_division_id
         JOIN districts d ON d.id=ds.district_id
         LEFT JOIN widms_geography_keep_gnd keep_row ON keep_row.id=gn.id
         WHERE d.name IN ('Galle','Matara','Hambantota')
           AND keep_row.id IS NULL"
    );
    $deleteLegacyGnd->execute();
    $removed['gnd'] = $deleteLegacyGnd->rowCount();

    $deleteLegacyDsd = $db->prepare(
        "DELETE ds
         FROM ds_divisions ds
         JOIN districts d ON d.id=ds.district_id
         LEFT JOIN widms_geography_keep_dsd keep_row ON keep_row.id=ds.id
         WHERE d.name IN ('Galle','Matara','Hambantota')
           AND ds.name NOT IN ('Suraliya Sewana Center','Senda Arana Elder Home')
           AND keep_row.id IS NULL"
    );
    $deleteLegacyDsd->execute();
    $removed['dsd'] = $deleteLegacyDsd->rowCount();


    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $db->commit();

} catch (Throwable $exception) {

    if ($db->inTransaction()) {
        $db->rollBack();
    }

    throw $exception;
}


/*
|--------------------------------------------------------------------------
| Final Verification
|--------------------------------------------------------------------------
*/

$verifyDistricts = (int) $db->query(
    "SELECT COUNT(*)
     FROM districts
     WHERE status='active'
       AND name IN (
           'Galle',
           'Matara',
           'Hambantota'
       )"
)->fetchColumn();

$verifyDsd = (int) $db->query(
    "SELECT COUNT(*)
     FROM ds_divisions ds
     JOIN districts d
        ON d.id=ds.district_id
     WHERE ds.status='active'
       AND d.status='active'
       AND d.name IN (
           'Galle',
           'Matara',
           'Hambantota'
       )
       AND ds.name NOT IN ('Suraliya Sewana Center','Senda Arana Elder Home')"
)->fetchColumn();

$verifyGnd = (int) $db->query(
    "SELECT COUNT(*)
     FROM gn_divisions gn
     JOIN ds_divisions ds
        ON ds.id=gn.ds_division_id
     JOIN districts d
        ON d.id=ds.district_id
     WHERE gn.status='active'
       AND ds.status='active'
       AND d.status='active'
       AND d.name IN (
           'Galle',
           'Matara',
           'Hambantota'
       )"
)->fetchColumn();


/*
|--------------------------------------------------------------------------
| Result
|--------------------------------------------------------------------------
*/

echo "Southern Province geography import completed.\n\n";

echo "Inserted:\n";
echo '  Districts: ' .
    $inserted['districts'] .
    "\n";
echo '  DS Divisions: ' .
    $inserted['dsd'] .
    "\n";
echo '  GN Divisions: ' .
    $inserted['gnd'] .
    "\n\n";

echo "Already existed / reused:\n";
echo '  Districts: ' .
    $existing['districts'] .
    "\n";
echo '  DS Divisions: ' .
    $existing['dsd'] .
    "\n";
echo '  GN Divisions: ' .
    $existing['gnd'] .
    "\n\n";

echo "Removed legacy records not present in the source:\n";
echo '  DS Divisions: ' .
    $removed['dsd'] .
    "\n";
echo '  GN Divisions: ' .
    $removed['gnd'] .
    "\n\n";

echo "Active Southern Province records now in WIDMS:\n";
echo '  Districts: ' .
    $verifyDistricts .
    "\n";
echo '  DS Divisions: ' .
    $verifyDsd .
    "\n";
echo '  GN Divisions: ' .
    $verifyGnd .
    "\n";


/*
|--------------------------------------------------------------------------
| Warn about pre-existing duplicates / alternative spellings
|--------------------------------------------------------------------------
*/

if (
    $verifyDistricts !== EXPECTED_DISTRICTS
    ||
    $verifyDsd !== EXPECTED_DSD
    ||
    $verifyGnd !== EXPECTED_GND
) {

    echo "\nWARNING:\n";

    echo
        "The final database counts do not exactly match " .
        "3 districts / 50 DS / 2,121 GN.\n";

    echo
        "This usually means older geography rows with different spellings " .
        "already existed in the database.\n";

    echo
        "No existing rows were deleted because WIDMS may already reference " .
        "their IDs.\n";
}
