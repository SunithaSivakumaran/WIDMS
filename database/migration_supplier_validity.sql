USE widms;

-- A nullable migration preserves any historical supplier records. New supplier
-- registrations require these fields at application level.
ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS valid_from DATE NULL AFTER address;
ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS validity_period SMALLINT UNSIGNED NULL AFTER valid_from;
ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS validity_unit ENUM('months', 'years') NULL AFTER validity_period;
