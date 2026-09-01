USE widms;

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
