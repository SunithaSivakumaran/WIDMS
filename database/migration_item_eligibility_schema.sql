-- Schema-only replacement for the retired item-eligibility reset migration.
-- Never delete inventory, aid requests, distributions, or user-created rules
-- as part of an automatic install/upgrade.

CREATE TABLE IF NOT EXISTS disability_aid_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    disability_type_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    restriction_months INT UNSIGNED NOT NULL DEFAULT 0,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_disability_aid_item (disability_type_id,item_id),
    CONSTRAINT fk_disability_aid_type FOREIGN KEY (disability_type_id) REFERENCES disability_types(id),
    CONSTRAINT fk_disability_aid_inventory FOREIGN KEY (item_id) REFERENCES inventory_items(id),
    CONSTRAINT fk_disability_aid_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS disability_item_prohibitions (
    disability_aid_item_id INT UNSIGNED NOT NULL,
    prohibited_item_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (disability_aid_item_id,prohibited_item_id),
    CONSTRAINT fk_item_prohibition_rule FOREIGN KEY (disability_aid_item_id) REFERENCES disability_aid_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_item_prohibition_item FOREIGN KEY (prohibited_item_id) REFERENCES inventory_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
