<?php

declare(strict_types=1);

/**
 * An eligibility rule must not be removed once its aid item has entered a
 * registration, request, stock, distribution, or other operational record.
 * Discover foreign-key references so new workflow tables are protected too.
 */
function aidItemHasRecordedUse(PDO $database, int $itemId): bool
{
    if ($itemId < 1) {
        throw new InvalidArgumentException('Select a valid aid item.');
    }

    $stock = $database->prepare('SELECT quantity FROM inventory_items WHERE id = :item');
    $stock->execute(['item' => $itemId]);
    $quantity = $stock->fetchColumn();
    if ($quantity === false) {
        throw new RuntimeException('The aid item no longer exists.');
    }
    if ((int) $quantity > 0) {
        return true;
    }

    static $referenceCache = [];
    $connectionId = spl_object_id($database);
    if (!isset($referenceCache[$connectionId])) {
        $referenceCache[$connectionId] = $database->query(
            "SELECT DISTINCT TABLE_NAME, COLUMN_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND REFERENCED_TABLE_SCHEMA = DATABASE()
               AND REFERENCED_TABLE_NAME = 'inventory_items'
               AND REFERENCED_COLUMN_NAME = 'id'
               AND TABLE_NAME <> 'disability_aid_items'"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    foreach ($referenceCache[$connectionId] as $reference) {
        $table = (string) $reference['TABLE_NAME'];
        $column = (string) $reference['COLUMN_NAME'];
        if (!preg_match('/\A[A-Za-z0-9_]+\z/D', $table)
            || !preg_match('/\A[A-Za-z0-9_]+\z/D', $column)) {
            throw new RuntimeException('Aid-item reference metadata is invalid.');
        }
        $statement = $database->prepare(
            'SELECT 1 FROM `' . $table . '` WHERE `' . $column . '` = :item LIMIT 1'
        );
        $statement->execute(['item' => $itemId]);
        if ($statement->fetchColumn() !== false) {
            return true;
        }
    }

    return false;
}
