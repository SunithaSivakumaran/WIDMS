USE widms;

-- Reasons provide an auditable explanation when access is refused or removed.
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS deactivation_reason VARCHAR(500) NULL AFTER status,
    ADD COLUMN IF NOT EXISTS deactivated_by INT UNSIGNED NULL AFTER deactivation_reason,
    ADD COLUMN IF NOT EXISTS deactivated_at DATETIME NULL AFTER deactivated_by;

ALTER TABLE registration_requests
    ADD COLUMN IF NOT EXISTS rejection_reason VARCHAR(500) NULL AFTER ds_division_id;

SET @has_deactivator_fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='users' AND CONSTRAINT_NAME='fk_user_deactivator');
SET @deactivator_fk_sql := IF(@has_deactivator_fk=0,'ALTER TABLE users ADD CONSTRAINT fk_user_deactivator FOREIGN KEY (deactivated_by) REFERENCES users(id) ON DELETE SET NULL','SELECT 1');
PREPARE deactivator_fk FROM @deactivator_fk_sql;
EXECUTE deactivator_fk;
DEALLOCATE PREPARE deactivator_fk;
