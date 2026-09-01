USE widms;

UPDATE inventory_items i
JOIN item_categories c ON LOWER(c.name)=LOWER(i.category) AND c.status='active'
SET i.category_id=c.id
WHERE i.category_id IS NULL;

CREATE TABLE IF NOT EXISTS aid_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    beneficiary_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    disability_notes VARCHAR(500) NOT NULL,
    notes VARCHAR(1000) NULL,
    medical_officer_approved TINYINT(1) NOT NULL DEFAULT 0,
    grama_niladhari_approved TINYINT(1) NOT NULL DEFAULT 0,
    social_services_approved TINYINT(1) NOT NULL DEFAULT 0,
    divisional_secretary_approved TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('pending','approved','rejected','distributed') NOT NULL DEFAULT 'pending',
    rejection_reason VARCHAR(500) NULL,
    submitted_by INT UNSIGNED NOT NULL,
    reviewed_by INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_aid_request_beneficiary FOREIGN KEY (beneficiary_id) REFERENCES beneficiaries(id),
    CONSTRAINT fk_aid_request_item FOREIGN KEY (item_id) REFERENCES inventory_items(id),
    CONSTRAINT fk_aid_request_submitter FOREIGN KEY (submitted_by) REFERENCES users(id),
    CONSTRAINT fk_aid_request_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_aid_request_status (status),
    INDEX idx_aid_request_beneficiary (beneficiary_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS distributions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    aid_request_id INT UNSIGNED NULL UNIQUE,
    beneficiary_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    distribution_type ENUM('direct','request-based') NOT NULL,
    source ENUM('officer-pool') NOT NULL DEFAULT 'officer-pool',
    notes VARCHAR(1000) NULL,
    distributed_by INT UNSIGNED NOT NULL,
    distributed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_distribution_request FOREIGN KEY (aid_request_id) REFERENCES aid_requests(id),
    CONSTRAINT fk_distribution_beneficiary FOREIGN KEY (beneficiary_id) REFERENCES beneficiaries(id),
    CONSTRAINT fk_distribution_item FOREIGN KEY (item_id) REFERENCES inventory_items(id),
    CONSTRAINT fk_distribution_officer FOREIGN KEY (distributed_by) REFERENCES users(id),
    INDEX idx_distribution_beneficiary (beneficiary_id),
    INDEX idx_distribution_date (distributed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
