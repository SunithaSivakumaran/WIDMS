<?php
declare(strict_types=1);

// Local credentials stay out of version control, like the SMTP configuration.
$settings = [
    'enabled' => filter_var(getenv('WIDMS_SMS_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    'user_id' => getenv('WIDMS_SMS_USER_ID') ?: '',
    'password' => getenv('WIDMS_SMS_PASSWORD') ?: '',
];
$localFile = __DIR__ . '/sms.local.php';
if (is_file($localFile)) {
    $local = require $localFile;
    if (is_array($local)) {
        $settings = array_replace($settings, $local);
    }
}
return $settings;
