<?php
declare(strict_types=1);

// Build a disposable reference database through migrate.php; never alter the source.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
require_once __DIR__ . '/../config/database.php';

function schemaSnapshot(PDO $db, string $schema): array
{
    $result = [];
    $queries = [
        'table' => 'SELECT TABLE_NAME, TABLE_TYPE, ENGINE, TABLE_COLLATION, CREATE_OPTIONS, TABLE_COMMENT
                    FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME',
        'columns' => 'SELECT TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA,
                            CHARACTER_SET_NAME, COLLATION_NAME, COLUMN_COMMENT, GENERATION_EXPRESSION
                     FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, COLUMN_NAME',
        'indexes' => 'SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, COLLATION, SUB_PART, INDEX_TYPE
                     FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX',
        'foreign_keys' => 'SELECT k.TABLE_NAME, k.CONSTRAINT_NAME, k.COLUMN_NAME, k.ORDINAL_POSITION,
                                 k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.UPDATE_RULE, r.DELETE_RULE
                          FROM information_schema.KEY_COLUMN_USAGE k
                          JOIN information_schema.REFERENTIAL_CONSTRAINTS r
                            ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
                          WHERE k.CONSTRAINT_SCHEMA = ? ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION',
        'checks' => 'SELECT TABLE_NAME, CONSTRAINT_NAME, CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS
                    WHERE CONSTRAINT_SCHEMA = ? ORDER BY TABLE_NAME, CONSTRAINT_NAME',
        'views' => 'SELECT TABLE_NAME, VIEW_DEFINITION, CHECK_OPTION, IS_UPDATABLE, SECURITY_TYPE
                    FROM information_schema.VIEWS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME',
    ];
    foreach ($queries as $kind => $sql) {
        $query = $db->prepare($sql);
        $query->execute([$schema]);
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $table = $row['TABLE_NAME'];
            unset($row['TABLE_NAME']);
            // Normalize database-qualified view definitions, not operational data.
            if (isset($row['VIEW_DEFINITION'])) {
                $row['VIEW_DEFINITION'] = str_replace('`' . $schema . '`.', '`DATABASE`.', $row['VIEW_DEFINITION']);
            }
            $result[$table][$kind][] = $row;
        }
    }
    return $result;
}

$source = database();
$reference = 'widms_migration_check_' . bin2hex(random_bytes(8));
$exists = $source->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
$exists->execute([$reference]);
if ((int) $exists->fetchColumn() !== 0 || $reference === DB_NAME) {
    throw new RuntimeException('Refusing to use an existing database for migration comparison.');
}
$environment = getenv();
$environment['WIDMS_DB_NAME'] = $reference;
// Synthetic contact only inside the disposable fixture; no notification is sent.
$environment['WIDMS_ADMIN_PHONE'] = '0700000000';
$exitCode = 1;
try {
    $process = proc_open([PHP_BINARY, __DIR__ . '/migrate.php'],
        [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes, __DIR__, $environment);
    if (!is_resource($process)) { throw new RuntimeException('Could not start migration runner.'); }
    fclose($pipes[0]);
    // Do not print the disposable administrator password produced on first install.
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    if ($status !== 0) {
        throw new RuntimeException('Fresh migration failed: ' . $errors);
    }
    unset($output);
    echo "Fresh database built using database/migrate.php.\n";
    $live = schemaSnapshot($source, DB_NAME);
    $fresh = schemaSnapshot($source, $reference);
    $differences = 0;
    foreach (array_unique(array_merge(array_keys($live), array_keys($fresh))) as $table) {
        if (!isset($fresh[$table])) {
            echo "[MISSING MIGRATION] $table exists only in the current database.\n";
            $differences++;
        } elseif (!isset($live[$table])) {
            echo "[MISSING LIVE TABLE] $table exists only in fresh migrations.\n";
            $differences++;
        } else {
            foreach (array_unique(array_merge(array_keys($live[$table]), array_keys($fresh[$table]))) as $kind) {
                if (($live[$table][$kind] ?? []) !== ($fresh[$table][$kind] ?? [])) {
                    echo "[DIFFERENCE] $table / $kind\n";
                    echo '  Current: ' . json_encode($live[$table][$kind] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
                    echo '  Migrated: ' . json_encode($fresh[$table][$kind] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
                    $differences++;
                }
            }
        }
    }
    // These are not used by WIDMS, but must not be silently missed in an audit.
    foreach (['TRIGGERS' => ['TRIGGER_SCHEMA', 'TRIGGER_NAME'], 'ROUTINES' => ['ROUTINE_SCHEMA', 'ROUTINE_NAME'], 'EVENTS' => ['EVENT_SCHEMA', 'EVENT_NAME']] as $kind => [$schemaColumn, $nameColumn]) {
        $query = $source->prepare("SELECT $nameColumn FROM information_schema.$kind WHERE $schemaColumn = ?");
        $query->execute([DB_NAME]);
        foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $name) {
            echo "[REVIEW REQUIRED] $kind: $name needs a definition-level migration audit.\n";
            $differences++;
        }
    }
    echo count($live) . ' current tables/views; ' . count($fresh) . " migrated tables/views; $differences difference(s).\n";
    $exitCode = $differences === 0 ? 0 : 1;
} finally {
    // The random name was checked absent before migrate.php created it. This
    // cleanup is limited to our disposable reference, never the user's database.
    if (preg_match('/\Awidms_migration_check_[a-f0-9]{16}\z/', $reference) && $reference !== DB_NAME) {
        $source->exec('DROP DATABASE IF EXISTS `' . $reference . '`');
        echo "Disposable comparison database removed. Source database was not modified.\n";
    }
}
exit($exitCode);
