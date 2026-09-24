USE widms;

CREATE TABLE IF NOT EXISTS notification_reads (
    user_id INT UNSIGNED NOT NULL,
    notification_key VARCHAR(160) NOT NULL,
    read_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, notification_key),
    CONSTRAINT fk_notification_read_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
