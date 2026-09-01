USE widms;

-- Add the direct references shown in the approved ER model without replacing the
-- existing workflow columns used by the application.
ALTER TABLE goods_requests ADD COLUMN IF NOT EXISTS aid_request_id INT UNSIGNED NULL AFTER id;
ALTER TABLE vision_camps ADD COLUMN IF NOT EXISTS social_service_officer_id INT UNSIGNED NULL AFTER requested_by;
ALTER TABLE contact_lens_orders ADD COLUMN IF NOT EXISTS original_power DECIMAL(5,2) NULL AFTER beneficiary_id;
ALTER TABLE contact_lens_orders ADD COLUMN IF NOT EXISTS power_changed TINYINT(1) NOT NULL DEFAULT 0 AFTER current_power;
ALTER TABLE contact_lens_orders ADD COLUMN IF NOT EXISTS stock_check_result VARCHAR(255) NULL AFTER power_changed;
ALTER TABLE officer_pools ADD COLUMN IF NOT EXISTS ds_division_id INT UNSIGNED NULL AFTER officer_id;
ALTER TABLE officer_pools ADD COLUMN IF NOT EXISTS returned INT UNSIGNED NOT NULL DEFAULT 0 AFTER reused;
ALTER TABLE correction_requests ADD COLUMN IF NOT EXISTS stock_receipt_id INT UNSIGNED NULL AFTER id;
ALTER TABLE aid_requests ADD COLUMN IF NOT EXISTS prescribed_power DECIMAL(5,2) NULL AFTER disability_notes;
ALTER TABLE aid_requests ADD COLUMN IF NOT EXISTS goods_request_ref INT UNSIGNED NULL AFTER prescribed_power;
ALTER TABLE aid_requests MODIFY status ENUM('pending','approved','goods-requested','rejected','distributed') NOT NULL DEFAULT 'pending';
ALTER TABLE goods_requests ADD COLUMN IF NOT EXISTS released_to_subject_id INT UNSIGNED NULL AFTER dispatched_by;
ALTER TABLE goods_requests ADD COLUMN IF NOT EXISTS received_at DATETIME NULL AFTER dispatched_at;

CREATE TABLE IF NOT EXISTS vision_camp_beneficiaries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    camp_id INT UNSIGNED NOT NULL,
    beneficiary_id INT UNSIGNED NULL,
    full_name VARCHAR(150) NOT NULL,
    nic VARCHAR(20) NOT NULL,
    address VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_camp_beneficiary_camp FOREIGN KEY (camp_id) REFERENCES vision_camps(id) ON DELETE CASCADE,
    CONSTRAINT fk_camp_beneficiary_record FOREIGN KEY (beneficiary_id) REFERENCES beneficiaries(id) ON DELETE SET NULL,
    UNIQUE KEY uq_camp_beneficiary_nic (camp_id, nic),
    INDEX idx_camp_beneficiary_record (beneficiary_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vision_camp_attendees (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    camp_id INT UNSIGNED NOT NULL,
    beneficiary_id INT UNSIGNED NULL,
    full_name VARCHAR(150) NOT NULL,
    nic VARCHAR(20) NOT NULL,
    outcome VARCHAR(120) NULL,
    lens_power DECIMAL(5,2) NULL,
    retest_power DECIMAL(5,2) NULL,
    lens_status ENUM('not-required','pending','available','ordered','issued') NOT NULL DEFAULT 'not-required',
    reason VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_camp_attendee_camp FOREIGN KEY (camp_id) REFERENCES vision_camps(id) ON DELETE CASCADE,
    CONSTRAINT fk_camp_attendee_beneficiary FOREIGN KEY (beneficiary_id) REFERENCES beneficiaries(id) ON DELETE SET NULL,
    UNIQUE KEY uq_camp_attendee_nic (camp_id, nic),
    INDEX idx_camp_attendee_lens_status (lens_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lens_units (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    camp_id INT UNSIGNED NOT NULL,
    power DECIMAL(5,2) NOT NULL,
    status ENUM('available','reserved','issued','damaged','returned') NOT NULL DEFAULT 'available',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_lens_unit_camp FOREIGN KEY (camp_id) REFERENCES vision_camps(id) ON DELETE CASCADE,
    INDEX idx_lens_unit_camp_status (camp_id, status),
    INDEX idx_lens_unit_power (power)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lens_unit_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    unit_id INT UNSIGNED NOT NULL,
    event VARCHAR(150) NOT NULL,
    performed_by INT UNSIGNED NULL,
    notes VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lens_history_unit FOREIGN KEY (unit_id) REFERENCES lens_units(id) ON DELETE CASCADE,
    CONSTRAINT fk_lens_history_user FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_lens_history_unit_date (unit_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lens_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    camp_id INT UNSIGNED NOT NULL,
    attendee_id INT UNSIGNED NULL,
    beneficiary_name VARCHAR(150) NOT NULL,
    nic VARCHAR(20) NOT NULL,
    original_power DECIMAL(5,2) NOT NULL,
    new_power DECIMAL(5,2) NOT NULL,
    reason VARCHAR(500) NOT NULL,
    status ENUM('pending','approved','rejected','fulfilled') NOT NULL DEFAULT 'pending',
    reviewed_by INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lens_request_camp FOREIGN KEY (camp_id) REFERENCES vision_camps(id) ON DELETE CASCADE,
    CONSTRAINT fk_lens_request_attendee FOREIGN KEY (attendee_id) REFERENCES vision_camp_attendees(id) ON DELETE SET NULL,
    CONSTRAINT fk_lens_request_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_lens_request_status (status),
    INDEX idx_lens_request_nic (nic)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_lens_order_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    event VARCHAR(150) NOT NULL,
    performed_by INT UNSIGNED NULL,
    notes VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_history_order FOREIGN KEY (order_id) REFERENCES contact_lens_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_history_user FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_order_history_order_date (order_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Resolves the ER model's many-to-many stock matching relationship explicitly.
CREATE TABLE IF NOT EXISTS contact_lens_order_stock_matches (
    order_id INT UNSIGNED NOT NULL,
    lens_stock_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    matched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    matched_by INT UNSIGNED NULL,
    PRIMARY KEY (order_id, lens_stock_id),
    CONSTRAINT fk_lens_match_order FOREIGN KEY (order_id) REFERENCES contact_lens_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_lens_match_stock FOREIGN KEY (lens_stock_id) REFERENCES contact_lens_stock(id),
    CONSTRAINT fk_lens_match_user FOREIGN KEY (matched_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS goods_request_aid_requests (
    goods_request_id INT UNSIGNED NOT NULL,
    aid_request_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (goods_request_id, aid_request_id),
    UNIQUE KEY uq_goods_batch_aid_request (aid_request_id),
    CONSTRAINT fk_goods_batch_request FOREIGN KEY (goods_request_id) REFERENCES goods_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_goods_batch_aid FOREIGN KEY (aid_request_id) REFERENCES aid_requests(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS goods_fulfillments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    goods_request_id INT UNSIGNED NOT NULL,
    aid_request_id INT UNSIGNED NOT NULL UNIQUE,
    subject_officer_id INT UNSIGNED NOT NULL,
    sso_id INT UNSIGNED NULL,
    lens_unit_identifier VARCHAR(40) NULL UNIQUE,
    status ENUM('with-subject-officer','pending-sso-handover','distributed') NOT NULL DEFAULT 'with-subject-officer',
    handed_to_sso_at DATETIME NULL,
    distributed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_fulfillment_goods FOREIGN KEY (goods_request_id) REFERENCES goods_requests(id),
    CONSTRAINT fk_fulfillment_aid FOREIGN KEY (aid_request_id) REFERENCES aid_requests(id),
    CONSTRAINT fk_fulfillment_subject FOREIGN KEY (subject_officer_id) REFERENCES users(id),
    CONSTRAINT fk_fulfillment_sso FOREIGN KEY (sso_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_fulfillment_subject_status (subject_officer_id,status),
    INDEX idx_fulfillment_sso_status (sso_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
