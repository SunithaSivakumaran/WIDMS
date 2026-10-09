<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../config/database.php';

$db = database();
$missing = $db->query(
    "SELECT i.id, i.item_name, i.variety, i.quantity, i.category,
            (SELECT COUNT(*) FROM aid_requests ar WHERE ar.item_id = i.id) AS request_count,
            (SELECT COUNT(*) FROM stock_receipts sr WHERE sr.item_id = i.id) AS receipt_count,
            (SELECT COUNT(*) FROM distributions d WHERE d.item_id = i.id) AS distribution_count
     FROM inventory_items i
     WHERE NOT EXISTS (SELECT 1 FROM disability_aid_items dai WHERE dai.item_id = i.id AND dai.status = 'active')
     ORDER BY i.item_name, i.variety, i.id"
)->fetchAll();
$unmatchedRequests = $db->query(
    "SELECT ar.item_id, i.item_name, i.variety, b.disability, COUNT(*) AS request_count,
            GROUP_CONCAT(DISTINCT dt.id ORDER BY dt.id) AS matching_disability_type_ids
     FROM aid_requests ar
     JOIN inventory_items i ON i.id = ar.item_id
     JOIN beneficiaries b ON b.id = ar.beneficiary_id
     LEFT JOIN disability_types dt
       ON LOWER(TRIM(dt.name)) COLLATE utf8mb4_unicode_ci = LOWER(TRIM(b.disability)) COLLATE utf8mb4_unicode_ci
      AND dt.status = 'active'
     WHERE NOT EXISTS (
         SELECT 1 FROM disability_aid_items dai
         WHERE dai.item_id = ar.item_id AND dai.disability_type_id = dt.id AND dai.status = 'active'
     )
     GROUP BY ar.item_id, i.item_name, i.variety, b.disability
     ORDER BY ar.item_id, b.disability"
)->fetchAll();
$disabilities = $db->query(
    "SELECT id, name, status, is_system FROM disability_types ORDER BY id"
)->fetchAll();
$existingRules = $db->query(
    "SELECT dai.id, dai.item_id, i.item_name, dai.disability_type_id,
            dt.name AS disability_name, dai.restriction_months, dai.status,
            dai.created_by
     FROM disability_aid_items dai
     JOIN inventory_items i ON i.id = dai.item_id
     JOIN disability_types dt ON dt.id = dai.disability_type_id
     WHERE dai.item_id IN (
         SELECT i2.id FROM inventory_items i2
         WHERE NOT EXISTS (SELECT 1 FROM disability_aid_items active_rule WHERE active_rule.item_id = i2.id AND active_rule.status = 'active')
     ) OR LOWER(TRIM(dt.name)) = 'mobility impairment'
     ORDER BY dai.item_id, dai.id"
)->fetchAll();

echo json_encode([
    'items_without_active_configuration' => $missing,
    'requested_item_disability_pairs_without_active_rule' => $unmatchedRequests,
    'disability_types' => $disabilities,
    'existing_rules_for_missing_items_and_mobility' => $existingRules,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), "\n";
