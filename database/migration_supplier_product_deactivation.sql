USE widms;

-- Product agreements can be permanently deactivated independently of a company.
ALTER TABLE supplier_authorized_items ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive') NOT NULL DEFAULT 'active' AFTER validity_unit;
ALTER TABLE supplier_authorized_items ADD COLUMN IF NOT EXISTS deactivation_reason VARCHAR(500) NULL AFTER status;
ALTER TABLE supplier_authorized_items ADD COLUMN IF NOT EXISTS deactivated_at DATETIME NULL AFTER deactivation_reason;
