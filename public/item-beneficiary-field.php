<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
requireRole('subject-officer');

header('Content-Type: application/json; charset=utf-8');

try {
    $ruleId = filter_input(INPUT_GET, 'rule_id', FILTER_VALIDATE_INT);
    if (!$ruleId) {
        throw new RuntimeException('Select a valid eligibility rule.');
    }

    $query = database()->prepare(
        'SELECT beneficiary_field_label, beneficiary_field_type
         FROM disability_aid_items WHERE id = :id'
    );
    $query->execute(['id' => $ruleId]);
    $field = $query->fetch();
    if (!$field) {
        throw new RuntimeException('The eligibility rule no longer exists.');
    }

    echo json_encode($field, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(422);
    echo json_encode(['error' => 'Unable to load item information settings.']);
}
