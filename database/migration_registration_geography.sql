USE widms;

ALTER TABLE registration_requests
    ADD COLUMN IF NOT EXISTS district_id INT UNSIGNED NULL AFTER division,
    ADD COLUMN IF NOT EXISTS ds_division_id INT UNSIGNED NULL AFTER district_id;

SET @has_registration_district_fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='registration_requests' AND CONSTRAINT_NAME='fk_registration_district');
SET @registration_district_fk_sql := IF(@has_registration_district_fk=0,'ALTER TABLE registration_requests ADD CONSTRAINT fk_registration_district FOREIGN KEY (district_id) REFERENCES districts(id) ON DELETE SET NULL','SELECT 1');
PREPARE registration_district_fk FROM @registration_district_fk_sql;
EXECUTE registration_district_fk;
DEALLOCATE PREPARE registration_district_fk;

SET @has_registration_ds_fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='registration_requests' AND CONSTRAINT_NAME='fk_registration_ds_division');
SET @registration_ds_fk_sql := IF(@has_registration_ds_fk=0,'ALTER TABLE registration_requests ADD CONSTRAINT fk_registration_ds_division FOREIGN KEY (ds_division_id) REFERENCES ds_divisions(id) ON DELETE SET NULL','SELECT 1');
PREPARE registration_ds_fk FROM @registration_ds_fk_sql;
EXECUTE registration_ds_fk;
DEALLOCATE PREPARE registration_ds_fk;
