USE widms;
ALTER TABLE stock_receipts ADD COLUMN IF NOT EXISTS power_breakdown JSON NULL AFTER power;
