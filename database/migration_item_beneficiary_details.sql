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
