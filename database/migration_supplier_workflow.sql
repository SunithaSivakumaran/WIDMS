USE widms;

ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS contact_person VARCHAR(120) NULL AFTER company_name;
ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS email VARCHAR(150) NULL AFTER contact_person;
ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS phone VARCHAR(25) NULL AFTER email;
ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS address VARCHAR(255) NULL AFTER phone;
ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS valid_from DATE NULL AFTER address;
ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS validity_period SMALLINT UNSIGNED NULL AFTER valid_from;
ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS validity_unit ENUM('months', 'years') NULL AFTER validity_period;
ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS deactivation_reason VARCHAR(500) NULL AFTER status;
ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS deactivated_at DATETIME NULL AFTER deactivation_reason;
ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS created_by INT UNSIGNED NULL AFTER status;
ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

CREATE TABLE IF NOT EXISTS supplier_authorized_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    valid_from DATE NULL,
    validity_period SMALLINT UNSIGNED NULL,
    validity_unit ENUM('months', 'years') NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    deactivation_reason VARCHAR(500) NULL,
    deactivated_at DATETIME NULL,
    authorized_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_supplier_authorized_supplier_item (supplier_id, item_id),
    CONSTRAINT fk_supplier_item_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    CONSTRAINT fk_supplier_item_item FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_supplier_item_user FOREIGN KEY (authorized_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE supplier_authorized_items ADD COLUMN IF NOT EXISTS valid_from DATE NULL AFTER item_id;
ALTER TABLE supplier_authorized_items ADD COLUMN IF NOT EXISTS validity_period SMALLINT UNSIGNED NULL AFTER valid_from;
ALTER TABLE supplier_authorized_items ADD COLUMN IF NOT EXISTS validity_unit ENUM('months', 'years') NULL AFTER validity_period;
ALTER TABLE supplier_authorized_items ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive') NOT NULL DEFAULT 'active' AFTER validity_unit;
ALTER TABLE supplier_authorized_items ADD COLUMN IF NOT EXISTS deactivation_reason VARCHAR(500) NULL AFTER status;
ALTER TABLE supplier_authorized_items ADD COLUMN IF NOT EXISTS deactivated_at DATETIME NULL AFTER deactivation_reason;

CREATE TABLE IF NOT EXISTS supplier_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT UNSIGNED NOT NULL,
    receipt_id INT UNSIGNED NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    check_number VARCHAR(100) NULL,
    check_date DATE NULL,
    payment_date DATE NOT NULL,
    notes TEXT NULL,
    recorded_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payment_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    CONSTRAINT fk_payment_receipt FOREIGN KEY (receipt_id) REFERENCES stock_receipts(id),
    CONSTRAINT fk_payment_user FOREIGN KEY (recorded_by) REFERENCES users(id),
    INDEX idx_supplier_payment_supplier (supplier_id),
    INDEX idx_supplier_payment_receipt (receipt_id),
    INDEX idx_supplier_payment_date (payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
