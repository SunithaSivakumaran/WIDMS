USE widms;
ALTER TABLE stock_receipts ADD COLUMN IF NOT EXISTS check_number VARCHAR(100) NULL AFTER payment_status;
