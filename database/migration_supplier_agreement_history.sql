USE widms;

-- Keep every supplier/product agreement as a separate historical row. The
-- former composite primary key prevented a product from being contracted
-- again after its previous agreement was deactivated or expired.
SET @has_agreement_id := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'supplier_authorized_items'
      AND COLUMN_NAME = 'id'
);
SET @agreement_history_sql := IF(
    @has_agreement_id = 0,
    'ALTER TABLE supplier_authorized_items ADD INDEX idx_supplier_authorized_supplier_item (supplier_id, item_id), DROP PRIMARY KEY, ADD COLUMN id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST',
    'SELECT 1'
);
PREPARE agreement_history_statement FROM @agreement_history_sql;
EXECUTE agreement_history_statement;
DEALLOCATE PREPARE agreement_history_statement;

