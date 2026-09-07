<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../config/database.php';
requireRole('subject-officer');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

try {
    $ruleId = filter_input(INPUT_GET, 'rule_id', FILTER_VALIDATE_INT);
    if (!$ruleId) {
        throw new RuntimeException('Select a valid eligibility rule.');
    }

    $query = database()->prepare(
        'SELECT field_label AS label, field_type AS type, is_system
         FROM disability_aid_item_fields
         WHERE disability_aid_item_id = :id
         ORDER BY display_order, id'
    );
    $query->execute(['id' => $ruleId]);
    $fields = $query->fetchAll();

    $rule = database()->prepare('SELECT id FROM disability_aid_items WHERE id = :id');
    $rule->execute(['id' => $ruleId]);
    if (!$rule->fetchColumn()) {
        throw new RuntimeException('The eligibility rule no longer exists.');
    }

    echo json_encode(['fields' => $fields], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(422);
    echo json_encode(['error' => 'Unable to load item information settings.']);
}
