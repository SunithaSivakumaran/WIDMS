USE widms;

-- Each product supplied by a company can have its own agreement period.
ALTER TABLE supplier_authorized_items ADD COLUMN IF NOT EXISTS valid_from DATE NULL AFTER item_id;
ALTER TABLE supplier_authorized_items ADD COLUMN IF NOT EXISTS validity_period SMALLINT UNSIGNED NULL AFTER valid_from;
ALTER TABLE supplier_authorized_items ADD COLUMN IF NOT EXISTS validity_unit ENUM('months', 'years') NULL AFTER validity_period;

-- Preserve existing agreements by copying their company-level validity once.
UPDATE supplier_authorized_items sai
JOIN suppliers s ON s.id = sai.supplier_id
SET sai.valid_from = s.valid_from,
    sai.validity_period = s.validity_period,
    sai.validity_unit = s.validity_unit
WHERE sai.valid_from IS NULL
  AND sai.validity_period IS NULL
  AND sai.validity_unit IS NULL;
