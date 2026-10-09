-- Link supplier/payment batches to their destination without moving camp stock
-- into Central Stock. Existing receipts remain Central Stock receipts.
ALTER TABLE stock_receipts
    ADD COLUMN IF NOT EXISTS stock_destination ENUM('central','vision-camp') NOT NULL DEFAULT 'central' AFTER item_id,
    ADD COLUMN IF NOT EXISTS vision_camp_id INT UNSIGNED NULL AFTER stock_destination,
    ADD COLUMN IF NOT EXISTS ds_division_id INT UNSIGNED NULL AFTER vision_camp_id;

ALTER TABLE spectacle_camp_receipts
    ADD COLUMN IF NOT EXISTS stock_receipt_id INT UNSIGNED NULL AFTER id;

SET @sql := (SELECT IF(COUNT(*)=0,
    'ALTER TABLE stock_receipts ADD INDEX idx_stock_receipt_destination (stock_destination, vision_camp_id)',
    'SELECT 1') FROM information_schema.statistics
    WHERE table_schema=DATABASE() AND table_name='stock_receipts' AND index_name='idx_stock_receipt_destination');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*)=0,
    'ALTER TABLE stock_receipts ADD CONSTRAINT fk_stock_receipt_camp FOREIGN KEY (vision_camp_id) REFERENCES spectacle_camps(id)',
    'SELECT 1') FROM information_schema.table_constraints
    WHERE constraint_schema=DATABASE() AND table_name='stock_receipts' AND constraint_name='fk_stock_receipt_camp');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*)=0,
    'ALTER TABLE stock_receipts ADD CONSTRAINT fk_stock_receipt_division FOREIGN KEY (ds_division_id) REFERENCES ds_divisions(id)',
    'SELECT 1') FROM information_schema.table_constraints
    WHERE constraint_schema=DATABASE() AND table_name='stock_receipts' AND constraint_name='fk_stock_receipt_division');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*)=0,
    'ALTER TABLE spectacle_camp_receipts ADD UNIQUE KEY uq_camp_supplier_receipt (stock_receipt_id)',
    'SELECT 1') FROM information_schema.statistics
    WHERE table_schema=DATABASE() AND table_name='spectacle_camp_receipts' AND index_name='uq_camp_supplier_receipt');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*)=0,
    'ALTER TABLE spectacle_camp_receipts ADD CONSTRAINT fk_camp_supplier_receipt FOREIGN KEY (stock_receipt_id) REFERENCES stock_receipts(id)',
    'SELECT 1') FROM information_schema.table_constraints
    WHERE constraint_schema=DATABASE() AND table_name='spectacle_camp_receipts' AND constraint_name='fk_camp_supplier_receipt');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
