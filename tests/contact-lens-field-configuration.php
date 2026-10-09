<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$rows = database()->query(
    "SELECT item.item_name, rule.beneficiary_field_label, field.field_label, field.is_system
     FROM disability_aid_items rule
     JOIN inventory_items item ON item.id = rule.item_id
     LEFT JOIN disability_aid_item_fields field ON field.disability_aid_item_id = rule.id
     WHERE LOWER(TRIM(item.item_name)) IN ('contact lens', 'spectacles')"
)->fetchAll(PDO::FETCH_ASSOC);

$contactLensFound = false;
$spectacleTypeFound = false;
foreach ($rows as $row) {
    if (strcasecmp((string) $row['item_name'], 'Contact Lens') === 0) {
        $contactLensFound = true;
        if (in_array(mb_strtolower(trim((string) $row['beneficiary_field_label'])), ['power', 'prescription power', 'prescribed power'], true)
            || ((int) $row['is_system'] === 1 && in_array(mb_strtolower(trim((string) $row['field_label'])), ['power', 'prescription power', 'prescribed power'], true))) {
            throw new RuntimeException('Contact Lens must not have a built-in Power field.');
        }
    }
    if (strcasecmp((string) $row['item_name'], 'Spectacles') === 0 && (string) $row['field_label'] === 'Spectacle Type') {
        $spectacleTypeFound = true;
    }
}
if (!$contactLensFound || !$spectacleTypeFound) {
    throw new RuntimeException('Expected built-in vision aid configuration was not found.');
}

echo "Contact Lens built-in field configuration: OK\n";
