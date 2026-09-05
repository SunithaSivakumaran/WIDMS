USE widms;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(120) NOT NULL UNIQUE,
    phone VARCHAR(25) NULL,
    division VARCHAR(120) NULL,
    profile_image VARCHAR(255) NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'subject-officer', 'store-keeper', 'social-service-officer') NOT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    deactivation_reason VARCHAR(500) NULL,
    deactivated_by INT UNSIGNED NULL,
    deactivated_at DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role (role),
    INDEX idx_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS registration_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(120) NOT NULL,
    phone VARCHAR(25) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('subject-officer', 'store-keeper', 'social-service-officer') NOT NULL,
    division VARCHAR(120) NULL,
    district_id INT UNSIGNED NULL,
    ds_division_id INT UNSIGNED NULL,
    rejection_reason VARCHAR(500) NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    reviewed_by INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    email_status ENUM('not-sent', 'sent', 'failed') NOT NULL DEFAULT 'not-sent',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_registration_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_registration_status (status),
    INDEX idx_registration_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS suppliers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(150) NOT NULL UNIQUE,
    contact_person VARCHAR(120) NULL,
    email VARCHAR(150) NULL,
    phone VARCHAR(25) NULL,
    address VARCHAR(255) NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(100) NOT NULL,
    category VARCHAR(100) NOT NULL DEFAULT 'General Aid',
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

CREATE TABLE IF NOT EXISTS supplier_authorized_items (
    supplier_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    authorized_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (supplier_id, item_id),
    CONSTRAINT fk_supplier_item_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    CONSTRAINT fk_supplier_item_item FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_supplier_item_user FOREIGN KEY (authorized_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS correction_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    record_reference VARCHAR(100) NOT NULL,
    error_type ENUM('wrong-quantity', 'wrong-supplier', 'wrong-date', 'wrong-cost', 'wrong-item', 'other') NOT NULL,
    current_value TEXT NOT NULL,
    proposed_correction TEXT NOT NULL,
    request_reason TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    admin_reason TEXT NULL,
    submitted_by INT UNSIGNED NOT NULL,
    reviewed_by INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_correction_submitter FOREIGN KEY (submitted_by) REFERENCES users(id),
    CONSTRAINT fk_correction_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_correction_status (status),
    INDEX idx_correction_submitter (submitted_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value VARCHAR(255) NOT NULL,
    setting_type ENUM('integer', 'boolean', 'string') NOT NULL,
    setting_group ENUM('general', 'notification') NOT NULL,
    description VARCHAR(255) NOT NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_settings_group (setting_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    role VARCHAR(50) NOT NULL,
    module VARCHAR(80) NOT NULL,
    action VARCHAR(500) NOT NULL,
    record_reference VARCHAR(100) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'done',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_activity_user_date (user_id, created_at),
    INDEX idx_activity_date (created_at),
    INDEX idx_activity_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
