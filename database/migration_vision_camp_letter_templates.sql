-- One editable A5 letter body per camp. Beneficiary details are never stored here.
CREATE TABLE IF NOT EXISTS spectacle_camp_letter_templates (
    camp_id INT UNSIGNED NOT NULL PRIMARY KEY,
    content_json LONGTEXT NOT NULL,
    updated_by INT UNSIGNED NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sclt_camp FOREIGN KEY (camp_id) REFERENCES spectacle_camps(id),
    CONSTRAINT fk_sclt_user FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
