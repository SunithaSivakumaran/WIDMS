<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../config/database.php';

// Legacy dummy-data cleanup scripts are intentionally excluded: automatic
// installation must never erase live stock, requests, returns, or rules.
$migrations = [
    'schema.sql',
    'migration_registration_requests.sql',
    'migration_stock_receiving.sql',
    'migration_supplier_workflow.sql',
    'migration_correction_requests.sql',
    'migration_intermediate_payment_corrections.sql',
    'migration_user_profiles.sql',
    'migration_activity_log.sql',
    'migration_master_data.sql',
    'migration_registration_geography.sql',
    'migration_sso_assignment_management.sql',
    'migration_beneficiaries.sql',
    'migration_goods_workflow.sql',
    'migration_aid_distribution.sql',
    'migration_special_workflows.sql',
    'migration_er_alignment.sql',
    'migration_contact_lens_bulk_workflow.sql',
    'migration_southern_geography.sql',
    'migration_galle_service_divisions.sql',
    'migration_aid_request_drafts.sql',
    'migration_optional_beneficiary_nic.sql',
    'migration_vision_camp_delivery_workflow.sql',
    'migration_disability_types.sql',
    'migration_beneficiary_identification.sql',
    'migration_item_eligibility_schema.sql',
    'migration_service_division_gn_optional.sql',
    'migration_item_beneficiary_details.sql',
    'migration_backfill_request_eligibility.sql',
    'migration_supplier_validity.sql',
    'migration_supplier_deactivation_reason.sql',
    'migration_supplier_item_validity.sql',
    'migration_supplier_product_deactivation.sql',
    'migration_supplier_agreement_history.sql',
    'migration_vision_impairment_defaults.sql',
    'migration_stock_receipt_power.sql',
    'migration_stock_receipt_power_breakdown.sql',
    'migration_stock_receipt_check_number.sql',
    'migration_unique_payment_references.sql',
    'migration_direct_aid_requests.sql',
    'migration_user_notifications.sql',
    'migration_notification_reads.sql',
    'migration_multi_division_goods_requests.sql',
    'migration_optical_power_workflow.sql',
    'migration_returnable_items.sql',
    'migration_admin_direct_distribution.sql',
    'migration_admin_direct_releases.sql',
    'migration_default_settings.sql',
    'migration_registration_sms.sql',
    'migration_registration_salary_number.sql',
    'migration_schema_structure_alignment.sql',
    'migration_schema_column_order.sql',
    'migration_required_user_phone.sql',
    'migration_notification_sms_queue.sql',
];

if (!preg_match('/\A[A-Za-z0-9_]{1,64}\z/D', DB_NAME)) {
    throw new RuntimeException('WIDMS_DB_NAME must contain only letters, numbers, and underscores (maximum 64 characters).');
}
if (in_array(strtolower(DB_NAME), ['mysql', 'information_schema', 'performance_schema', 'sys'], true)) {
    throw new RuntimeException('WIDMS_DB_NAME cannot be a database reserved for the database server.');
}

// Validate every file before creating or changing a database. In particular,
// no migration may switch away from the configured database.
$preparedMigrations = [];
foreach ($migrations as $file) {
    $path = __DIR__ . '/' . $file;
    if (!is_file($path)) {
        throw new RuntimeException("Missing migration: $file");
    }
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException("Cannot read migration: $file");
    }
    $sql = preg_replace('/^[ \t]*USE[ \t]+`?widms`?[ \t]*;[ \t]*\r?$/mi', '', $sql);
    if ($sql === null || preg_match('/^[ \t]*USE[ \t]+/mi', $sql)) {
        throw new RuntimeException("Migration $file contains an unsupported database switch; refusing to execute it.");
    }
    $preparedMigrations[$file] = $sql;
}
if (isset($argv[1]) && $argv[1] !== '--check') {
    throw new RuntimeException('Unknown option. Use --check for a read-only migration-file check.');
}
if (($argv[1] ?? '') === '--check') {
    echo count($preparedMigrations) . " migration files validated; no database changes made.\n";
    exit(0);
}

$serverDsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', DB_HOST, DB_PORT);
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $server = new PDO($serverDsn, DB_USER, DB_PASS, $options);
    $server->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $db = database();
} catch (PDOException $exception) {
    throw new RuntimeException(
        'Cannot create/connect to WIDMS database. Check that MariaDB is running and the configured user has CREATE DATABASE permission.',
        0,
        $exception
    );
}

$db->exec(
    'CREATE TABLE IF NOT EXISTS widms_schema_migrations (
        migration_file VARCHAR(191) NOT NULL PRIMARY KEY,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);
$applied = array_fill_keys($db->query('SELECT migration_file FROM widms_schema_migrations')->fetchAll(PDO::FETCH_COLUMN), true);
$record = $db->prepare('INSERT INTO widms_schema_migrations (migration_file) VALUES (:file)');

if (isset($applied['schema.sql'])) {
    bootstrapFirstAdministrator($db);
}

foreach ($migrations as $file) {
    if (isset($applied[$file])) {
        echo "Already applied $file\n";
        continue;
    }

    try {
        $db->exec($preparedMigrations[$file]);
        $record->execute(['file' => $file]);
        echo "Applied $file\n";
    } catch (Throwable $exception) {
        throw new RuntimeException("Migration failed in $file: " . $exception->getMessage(), 0, $exception);
    }

    if ($file === 'schema.sql') {
        bootstrapFirstAdministrator($db);
    }
}

// Also recover an install interrupted immediately after schema.sql.
bootstrapFirstAdministrator($db);
echo "WIDMS schema is current.\n";

function bootstrapFirstAdministrator(PDO $db): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    if ((int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn() !== 0) {
        return;
    }

    // First-install accounts also need genuine contact details. Never use a
    // placeholder or silently reuse another user's phone number.
    require_once __DIR__ . '/../includes/sms.php';
    $phone = trim((string) (getenv('WIDMS_ADMIN_PHONE') ?: ''));
    if ($phone === '' && function_exists('stream_isatty') && stream_isatty(STDIN)) {
        echo 'First administrator mobile number (07XXXXXXXX or +947XXXXXXXX): ';
        $phone = trim((string) fgets(STDIN));
    }
    if (widmsSmsPhone($phone) === null || strlen($phone) > 25) {
        throw new RuntimeException('A valid first administrator mobile number is required. Set WIDMS_ADMIN_PHONE and rerun database/migrate.php.');
    }
    $password = bin2hex(random_bytes(16));
    $insert = $db->prepare(
        "INSERT INTO users (full_name, username, phone, password_hash, role, status)
         VALUES ('System Administrator', :username, :phone, :password_hash, 'admin', 'active')"
    );
    $insert->execute([
        'username' => 'admin@widms.gov',
        'phone' => $phone,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);
    echo "First administrator created. Username: admin@widms.gov\n";
    echo "One-time generated password: $password\n";
    echo "Save this password now; it will not be shown again.\n";
}
