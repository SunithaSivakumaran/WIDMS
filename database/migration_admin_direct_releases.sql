USE widms;

CREATE TABLE IF NOT EXISTS admin_direct_releases (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    aid_request_id INT UNSIGNED NOT NULL UNIQUE,
    admin_id INT UNSIGNED NOT NULL,
    status ENUM('awaiting-store-keeper','released-to-admin','distributed') NOT NULL DEFAULT 'awaiting-store-keeper',
    released_by INT UNSIGNED NULL,
    released_at DATETIME NULL,
    distributed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_admin_direct_release_request FOREIGN KEY (aid_request_id) REFERENCES aid_requests(id),
    CONSTRAINT fk_admin_direct_release_admin FOREIGN KEY (admin_id) REFERENCES users(id),
    CONSTRAINT fk_admin_direct_release_keeper FOREIGN KEY (released_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_admin_direct_release_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
