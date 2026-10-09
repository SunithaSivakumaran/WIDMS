<?php
declare(strict_types=1);

// Data-only repair for an existing inventory item; never runs during migration.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../config/database.php';

$options = getopt('', ['months:', 'apply']);
$apply = array_key_exists('apply', $options);
$months = filter_var($options['months'] ?? null, FILTER_VALIDATE_INT);
if ($apply && ($months === false || $months < 0 || $months > 1200)) {
    throw new InvalidArgumentException('Pass --months=0 (or another approved waiting period) with --apply.');
}

$db = database();
try {
    $db->beginTransaction();
    $item = $db->query(
        "SELECT id, item_name, variety, quantity
         FROM inventory_items
         WHERE LOWER(TRIM(item_name)) = 'crutches' AND TRIM(variety) = ''
         FOR UPDATE"
    )->fetchAll();
    if (count($item) !== 1) {
        throw new RuntimeException('Expected exactly one existing, unvaried crutches item.');
    }
    $disability = $db->query(
        "SELECT id, name FROM disability_types
         WHERE LOWER(TRIM(name)) = 'mobility impairment' AND status = 'active'
         FOR UPDATE"
    )->fetchAll();
    if (count($disability) !== 1) {
        throw new RuntimeException('Expected exactly one active Mobility Impairment disability type.');
    }

    $itemId = (int) $item[0]['id'];
    $disabilityId = (int) $disability[0]['id'];
    $rule = $db->prepare('SELECT id, disability_type_id, status FROM disability_aid_items WHERE item_id = :item FOR UPDATE');
    $rule->execute(['item' => $itemId]);
    $existingRules = $rule->fetchAll();
    if ($existingRules !== []) {
        $db->rollBack();
        echo "Crutches item #$itemId already has a configuration rule; no data changed.\n";
        exit(0);
    }

    if (!$apply) {
        $db->rollBack();
        echo "Crutches item #$itemId (stock {$item[0]['quantity']}) has no rule. Proposed disability: {$disability[0]['name']} (#$disabilityId). No data changed.\n";
        exit(0);
    }

    $creatorId = (int) $db->query(
        "SELECT id FROM users WHERE role = 'subject-officer' AND status = 'active' ORDER BY id LIMIT 1"
    )->fetchColumn();
    if ($creatorId < 1) {
        throw new RuntimeException('An active Subject Officer is required to attribute the configuration.');
    }

    $insert = $db->prepare(
        "INSERT INTO disability_aid_items
            (disability_type_id, item_id, restriction_months, is_system, status, created_by)
         VALUES (:disability, :item, :months, 0, 'active', :creator)"
    );
    $insert->execute([
        'disability' => $disabilityId,
        'item' => $itemId,
        'months' => $months,
        'creator' => $creatorId,
    ]);
    $newRuleId = (int) $db->lastInsertId();
    $db->commit();
    echo "Added rule #$newRuleId for existing crutches item #$itemId; no inventory or schema changes.\n";
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $exception;
}
