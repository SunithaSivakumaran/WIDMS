USE widms;

-- This separate marker handles databases where the earlier item reset already ran.
CREATE TABLE IF NOT EXISTS widms_data_migrations (
    migration_key VARCHAR(120) PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @clear_default_disabilities := NOT EXISTS (
    SELECT 1 FROM widms_data_migrations
    WHERE migration_key='2026-09-clear-default-disabilities'
);

-- Remove dependent rules first while preserving users, beneficiaries, and geography.
DELETE p FROM disability_item_prohibitions p
JOIN disability_aid_items dai ON dai.id=p.disability_aid_item_id
WHERE @clear_default_disabilities=1;

DELETE FROM disability_aid_items WHERE @clear_default_disabilities=1;
DELETE FROM disability_types WHERE @clear_default_disabilities=1;

INSERT IGNORE INTO widms_data_migrations (migration_key)
VALUES ('2026-09-clear-default-disabilities');
