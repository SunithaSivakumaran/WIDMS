<?php
declare(strict_types=1);

// The defaults work for local XAMPP. A deployment can override them without
// editing application files (including when testing migration on a separate DB).
function widmsDatabaseSetting(string $name, string $default): string
{
    $value = getenv($name);
    return $value === false ? $default : $value;
}

define('DB_HOST', widmsDatabaseSetting('WIDMS_DB_HOST', '127.0.0.1'));
define('DB_PORT', widmsDatabaseSetting('WIDMS_DB_PORT', '3306'));
define('DB_NAME', widmsDatabaseSetting('WIDMS_DB_NAME', 'widms'));
define('DB_USER', widmsDatabaseSetting('WIDMS_DB_USER', 'root'));
define('DB_PASS', widmsDatabaseSetting('WIDMS_DB_PASS', ''));

function database(): PDO
{
    static $connection = null;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_PORT,
        DB_NAME
    );

    $connection = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $connection;
}
