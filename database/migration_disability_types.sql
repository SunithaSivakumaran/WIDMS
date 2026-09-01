USE widms;

CREATE TABLE IF NOT EXISTS disability_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_disability_type_name (name),
    CONSTRAINT fk_disability_type_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

SET @disability_admin := (SELECT id FROM users WHERE role='admin' ORDER BY id LIMIT 1);

INSERT INTO disability_types (name, created_by) VALUES
    ('Mobility Impairment - Upper Limb', @disability_admin),
    ('Mobility Impairment - Lower Limb', @disability_admin),
    ('Visual Impairment', @disability_admin),
    ('Hearing Impairment', @disability_admin),
    ('Speech and Language Impairment', @disability_admin),
    ('Intellectual Disability', @disability_admin),
    ('Autism Spectrum Disorder', @disability_admin),
    ('Multiple Disabilities', @disability_admin),
    ('Other', @disability_admin)
ON DUPLICATE KEY UPDATE name = VALUES(name);
