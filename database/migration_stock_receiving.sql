USE widms;

CREATE TABLE IF NOT EXISTS suppliers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(150) NOT NULL UNIQUE,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(100) NOT NULL,
    variety VARCHAR(100) NOT NULL DEFAULT '',
    quantity INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_inventory_item_variety (item_name, variety)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_receipts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_cost DECIMAL(12,2) NOT NULL,
    total_cost DECIMAL(14,2) NOT NULL,
    bill_number VARCHAR(100) NOT NULL UNIQUE,
    received_date DATE NOT NULL,
    payment_status ENUM('fully-paid', 'partially-paid', 'unpaid') NOT NULL,
    paid_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    balance_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    received_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_receipt_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    CONSTRAINT fk_receipt_item FOREIGN KEY (item_id) REFERENCES inventory_items(id),
    CONSTRAINT fk_receipt_user FOREIGN KEY (received_by) REFERENCES users(id),
    INDEX idx_receipt_date (received_date),
    INDEX idx_receipt_payment (payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO suppliers (company_name) VALUES
('ABC Medical Co. Ltd'),
('Vision Care Co. Ltd'),
('HealthTech Pvt Ltd')
ON DUPLICATE KEY UPDATE company_name = VALUES(company_name);

INSERT INTO inventory_items (item_name, variety) VALUES
('Wheelchair', 'Standard'),
('Glasses', 'Standard'),
('Hearing Aid', 'Behind-the-ear'),
('Crutches', 'Adjustable')
ON DUPLICATE KEY UPDATE item_name = VALUES(item_name);
