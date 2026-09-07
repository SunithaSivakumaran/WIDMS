USE widms;

-- Vision-aid receipts can be recorded by prescription power.  NULL keeps
-- ordinary aid receipts unchanged; Contact Lens and Spectacles use this field.
ALTER TABLE stock_receipts
    ADD COLUMN IF NOT EXISTS power DECIMAL(5,2) NULL AFTER quantity;

-- The nullable column is sufficient for recording and querying power batches.
