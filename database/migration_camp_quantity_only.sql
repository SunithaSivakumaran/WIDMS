-- Remove only the obsolete approved-participant power requirement.
-- Preserve old prescription values and all identity/rejection constraints.
-- MariaDB scopes check names by table; MySQL scopes them by schema.
SET @sc_power_check_drops = NULL;
SET @sc_power_check_lookup = IF(LOCATE('MariaDB', VERSION()) > 0,
    'SELECT GROUP_CONCAT(CONCAT(''DROP CONSTRAINT `'', REPLACE(CONSTRAINT_NAME, ''`'', ''``''), ''`'') SEPARATOR '', '') INTO @sc_power_check_drops FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=''spectacle_camp_participants'' AND LOWER(CHECK_CLAUSE) LIKE ''%power%'' AND LOWER(CHECK_CLAUSE) LIKE ''%approved%''',
    'SELECT GROUP_CONCAT(CONCAT(''DROP CHECK `'', REPLACE(tc.CONSTRAINT_NAME, ''`'', ''``''), ''`'') SEPARATOR '', '') INTO @sc_power_check_drops FROM information_schema.TABLE_CONSTRAINTS tc JOIN information_schema.CHECK_CONSTRAINTS cc ON cc.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA AND cc.CONSTRAINT_NAME=tc.CONSTRAINT_NAME WHERE tc.CONSTRAINT_SCHEMA=DATABASE() AND tc.TABLE_NAME=''spectacle_camp_participants'' AND tc.CONSTRAINT_TYPE=''CHECK'' AND LOWER(cc.CHECK_CLAUSE) LIKE ''%power%'' AND LOWER(cc.CHECK_CLAUSE) LIKE ''%approved%'''
);
PREPARE sc_power_check_statement FROM @sc_power_check_lookup;
EXECUTE sc_power_check_statement;
DEALLOCATE PREPARE sc_power_check_statement;
SET @sc_power_check_alter = IF(@sc_power_check_drops IS NULL, 'SELECT 1', CONCAT('ALTER TABLE spectacle_camp_participants ', @sc_power_check_drops));
PREPARE sc_power_check_statement FROM @sc_power_check_alter;
EXECUTE sc_power_check_statement;
DEALLOCATE PREPARE sc_power_check_statement;
