USE widms;

-- Preserve the existing legacy migration ledger's structure without running
-- retired cleanup migrations or copying/deleting any recorded migration keys.
CREATE TABLE IF NOT EXISTS widms_data_migrations (
    migration_key VARCHAR(120) NOT NULL PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The original disability-types migration inherited the database collation.
-- Make the existing deployment's definition explicit for fresh installations.
ALTER TABLE disability_types
    DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
    MODIFY COLUMN name VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
    MODIFY COLUMN status ENUM('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active';
