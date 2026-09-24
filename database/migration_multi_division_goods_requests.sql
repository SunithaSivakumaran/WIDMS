USE widms;

-- A single Subject Officer submission can contain several item/division lines.
-- Each line remains a normal goods_requests record so the established Admin
-- approval and Store Keeper dispatch workflow stays backwards compatible.
ALTER TABLE goods_requests
    ADD COLUMN IF NOT EXISTS request_batch_ref VARCHAR(40) NULL AFTER aid_request_id,
    ADD COLUMN IF NOT EXISTS destination_sso_id INT UNSIGNED NULL AFTER destination_ds_division_id,
    ADD COLUMN IF NOT EXISTS allocated_to_sso_at DATETIME NULL AFTER received_at;

UPDATE goods_requests
SET request_batch_ref = CONCAT('GRB-', LPAD(id, 6, '0'))
WHERE request_batch_ref IS NULL OR request_batch_ref = '';

CREATE INDEX IF NOT EXISTS idx_goods_request_batch_ref
    ON goods_requests (request_batch_ref);

CREATE INDEX IF NOT EXISTS idx_goods_destination_sso
    ON goods_requests (destination_sso_id);

SET @goods_sso_fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'goods_requests'
      AND CONSTRAINT_NAME = 'fk_goods_destination_sso'
);
SET @goods_sso_fk_sql := IF(
    @goods_sso_fk_exists = 0,
    'ALTER TABLE goods_requests ADD CONSTRAINT fk_goods_destination_sso FOREIGN KEY (destination_sso_id) REFERENCES users(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE goods_sso_fk_statement FROM @goods_sso_fk_sql;
EXECUTE goods_sso_fk_statement;
DEALLOCATE PREPARE goods_sso_fk_statement;
