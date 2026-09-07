USE widms;

-- Keep supplier deactivation decisions accountable without changing existing records.
ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS deactivation_reason VARCHAR(500) NULL AFTER status;
ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS deactivated_at DATETIME NULL AFTER deactivation_reason;
