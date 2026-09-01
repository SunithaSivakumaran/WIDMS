USE widms;
ALTER TABLE vision_camp_beneficiaries ADD COLUMN IF NOT EXISTS outcome ENUM('pending','distributed','handed-over','rejected') NOT NULL DEFAULT 'pending' AFTER address;
ALTER TABLE vision_camp_beneficiaries ADD COLUMN IF NOT EXISTS rejection_reason VARCHAR(500) NULL AFTER outcome;
ALTER TABLE vision_camp_beneficiaries ADD COLUMN IF NOT EXISTS processed_by INT UNSIGNED NULL AFTER rejection_reason;
ALTER TABLE vision_camp_beneficiaries ADD COLUMN IF NOT EXISTS processed_at DATETIME NULL AFTER processed_by;
ALTER TABLE vision_camp_handovers MODIFY beneficiary_id INT UNSIGNED NULL;
ALTER TABLE vision_camp_handovers MODIFY item_id INT UNSIGNED NULL;
ALTER TABLE vision_camp_handovers ADD COLUMN IF NOT EXISTS camp_beneficiary_id INT UNSIGNED NULL AFTER camp_id;
