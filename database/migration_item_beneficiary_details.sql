USE widms;

-- A migration may be rerun after a partial failure, so each column is added only when it is missing.
SET @has_column := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'disability_aid_items' AND COLUMN_NAME = 'beneficiary_field_label');
SET @statement := IF(@has_column = 0, 'ALTER TABLE disability_aid_items ADD COLUMN beneficiary_field_label VARCHAR(100) NULL AFTER restriction_months', 'SELECT 1');
PREPARE migration_statement FROM @statement;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @has_column := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'disability_aid_items' AND COLUMN_NAME = 'beneficiary_field_type');
SET @statement := IF(@has_column = 0, "ALTER TABLE disability_aid_items ADD COLUMN beneficiary_field_type ENUM('text','number') NOT NULL DEFAULT 'text' AFTER beneficiary_field_label", 'SELECT 1');
PREPARE migration_statement FROM @statement;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

-- Give existing Contact Lens rules an editable configuration default; the SSO form still reads only the database setting.
UPDATE inventory_items
SET item_name = 'Contact Lens'
WHERE LOWER(item_name) = 'cantact lens';

UPDATE disability_aid_items dai
JOIN inventory_items i ON i.id = dai.item_id
SET dai.beneficiary_field_label = 'Prescription Power',
    dai.beneficiary_field_type = 'number'
WHERE dai.beneficiary_field_label IS NULL
  AND LOWER(CONCAT(i.item_name, ' ', i.variety)) LIKE '%lens%';

-- Store the configured label with each request so historic request tables remain understandable after configuration changes.
SET @has_column := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aid_requests' AND COLUMN_NAME = 'beneficiary_detail_label');
SET @statement := IF(@has_column = 0, 'ALTER TABLE aid_requests ADD COLUMN beneficiary_detail_label VARCHAR(100) NULL AFTER prescribed_power', 'SELECT 1');
PREPARE migration_statement FROM @statement;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @has_column := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aid_requests' AND COLUMN_NAME = 'beneficiary_detail_value');
SET @statement := IF(@has_column = 0, 'ALTER TABLE aid_requests ADD COLUMN beneficiary_detail_value VARCHAR(255) NULL AFTER beneficiary_detail_label', 'SELECT 1');
PREPARE migration_statement FROM @statement;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

-- An item can require more than one value from a beneficiary. These definitions
-- are managed by the Subject Officer and replace the former single-field limit.
CREATE TABLE IF NOT EXISTS disability_aid_item_fields (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    disability_aid_item_id INT UNSIGNED NOT NULL,
    field_label VARCHAR(100) NOT NULL,
    field_type ENUM('text','number','date','image','pdf') NOT NULL DEFAULT 'text',
    display_order TINYINT UNSIGNED NOT NULL DEFAULT 1,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_disability_aid_item_field (disability_aid_item_id, field_label),
    CONSTRAINT fk_disability_aid_item_field_rule FOREIGN KEY (disability_aid_item_id)
        REFERENCES disability_aid_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Existing installations may have created the table before document support.
ALTER TABLE disability_aid_item_fields
    MODIFY COLUMN field_type ENUM('text','number','date','image','pdf') NOT NULL DEFAULT 'text';

-- Preserve every existing one-field configuration as the first editable field.
INSERT IGNORE INTO disability_aid_item_fields(disability_aid_item_id, field_label, field_type, display_order)
SELECT id, beneficiary_field_label, beneficiary_field_type, 1
FROM disability_aid_items
WHERE beneficiary_field_label IS NOT NULL AND beneficiary_field_label <> '';

SET @has_column := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aid_requests' AND COLUMN_NAME = 'beneficiary_details_json');
SET @statement := IF(@has_column = 0, 'ALTER TABLE aid_requests ADD COLUMN beneficiary_details_json TEXT NULL AFTER beneficiary_detail_value', 'SELECT 1');
PREPARE migration_statement FROM @statement;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
