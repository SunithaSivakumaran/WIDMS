USE widms;

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
