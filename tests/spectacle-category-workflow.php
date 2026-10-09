<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/spectacle-categories.php';
require_once __DIR__ . '/../includes/spectacle-camps.php';

$db = database();
$categories = widmsSpectacleCategories($db);
$expected = ['Reading Only', 'Distance Only', 'Bifocal', 'Cylinder Bifocal', 'Cylinder Distance', 'Child'];
if (array_slice(array_column($categories, 'name'), 0, 6) !== $expected) {
    throw new RuntimeException('The six initial spectacle types are missing or out of order.');
}

$translations = require __DIR__ . '/../includes/spectacle-category-i18n.php';
foreach (['si', 'ta'] as $language) {
    foreach (['Spectacle Type', ...$expected] as $label) {
        if (empty($translations[$language][$label])) {
            throw new RuntimeException("Missing {$language} translation for {$label}.");
        }
    }
}

$itemId = (int) $db->query("SELECT id FROM inventory_items WHERE LOWER(item_name)='spectacles' LIMIT 1")->fetchColumn();
if ($itemId > 0) {
    $balances = widmsSpectacleCategoryBalances($db, $itemId);
    if (!isset($balances[$categories[0]['id']])) {
        throw new RuntimeException('Historical spectacles were not assigned a category balance.');
    }
    if (!widmsSpectacleCategoryInUse($db, $categories[0]['id'])) {
        throw new RuntimeException('A spectacle type used by historical stock must be protected from deletion.');
    }
}

$campId = (int) $db->query('SELECT id FROM spectacle_camps WHERE conducted_at IS NOT NULL LIMIT 1')->fetchColumn();
if ($campId > 0) {
    foreach (scBalances($db, $campId) as $categoryId => $balance) {
        if ((int) $categoryId < 1 || !array_key_exists('available', $balance)) {
            throw new RuntimeException('Camp stock did not load by spectacle type.');
        }
    }
}

echo "PASS: spectacle type defaults and central/camp balances load.\n";
