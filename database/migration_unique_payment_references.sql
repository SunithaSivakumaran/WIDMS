USE widms;
-- Bill numbers are already unique on stock_receipts. Check numbers are unique
-- for recorded supplier payments; NULL legacy values remain allowed.
SET @sql := (SELECT IF(COUNT(*)=0, 'ALTER TABLE supplier_payments ADD UNIQUE KEY uq_supplier_payment_check_number (check_number)', 'SELECT 1') FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='supplier_payments' AND index_name='uq_supplier_payment_check_number'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(COUNT(*)=0, 'ALTER TABLE stock_receipts ADD UNIQUE KEY uq_stock_receipt_check_number (check_number)', 'SELECT 1') FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='stock_receipts' AND index_name='uq_stock_receipt_check_number'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
