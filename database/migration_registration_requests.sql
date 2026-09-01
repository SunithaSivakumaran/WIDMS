USE widms;

ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(25) NULL AFTER username;
ALTER TABLE users ADD COLUMN IF NOT EXISTS division VARCHAR(120) NULL AFTER phone;

CREATE TABLE IF NOT EXISTS registration_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(120) NOT NULL,
    phone VARCHAR(25) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('subject-officer', 'store-keeper', 'social-service-officer') NOT NULL,
    division VARCHAR(120) NULL,
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
