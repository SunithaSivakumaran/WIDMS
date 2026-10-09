USE widms;

-- New spectacles-only workflow. Legacy lens/camp records remain untouched.
CREATE TABLE IF NOT EXISTS spectacle_camps (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 district_id INT UNSIGNED NOT NULL, ds_division_id INT UNSIGNED NOT NULL,
 item_id INT UNSIGNED NOT NULL, requested_by INT UNSIGNED NOT NULL,
 estimated_participants INT UNSIGNED NOT NULL, camp_date DATE NOT NULL, camp_time TIME NOT NULL,
 remarks VARCHAR(1000) NOT NULL DEFAULT '',
 status ENUM('pending','approved','rejected','completed') NOT NULL DEFAULT 'pending',
 reviewed_by INT UNSIGNED NULL, reviewed_at DATETIME NULL, decision_reason VARCHAR(500) NULL,
 conducted_at DATETIME NULL, completed_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (district_id) REFERENCES districts(id), FOREIGN KEY (ds_division_id) REFERENCES ds_divisions(id),
 FOREIGN KEY (item_id) REFERENCES inventory_items(id), FOREIGN KEY (requested_by) REFERENCES users(id),
 FOREIGN KEY (reviewed_by) REFERENCES users(id),
 CHECK (estimated_participants > 0), INDEX idx_sc_owner (requested_by,status), INDEX idx_sc_division (ds_division_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS spectacle_camp_participants (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, camp_id INT UNSIGNED NOT NULL,
 full_name VARCHAR(150) NOT NULL, nic VARCHAR(20) NULL, elder_card_number VARCHAR(50) NULL,
 address VARCHAR(255) NOT NULL, phone VARCHAR(25) NULL,
 district_id INT UNSIGNED NOT NULL, ds_division_id INT UNSIGNED NOT NULL, gn_division_id INT UNSIGNED NOT NULL,
 status ENUM('registered','approved','rejected') NOT NULL DEFAULT 'registered',
 power DECIMAL(5,2) NULL, prescription_details VARCHAR(1000) NOT NULL DEFAULT '',
 rejection_reason VARCHAR(500) NULL, recorded_by INT UNSIGNED NOT NULL,
 decided_by INT UNSIGNED NULL, decided_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (camp_id) REFERENCES spectacle_camps(id), FOREIGN KEY (district_id) REFERENCES districts(id),
 FOREIGN KEY (ds_division_id) REFERENCES ds_divisions(id), FOREIGN KEY (gn_division_id) REFERENCES gn_divisions(id),
 FOREIGN KEY (recorded_by) REFERENCES users(id), FOREIGN KEY (decided_by) REFERENCES users(id),
 UNIQUE KEY uq_scp_nic (camp_id,nic), UNIQUE KEY uq_scp_elder (camp_id,elder_card_number),
 CHECK (COALESCE(TRIM(nic),'') <> '' OR COALESCE(TRIM(elder_card_number),'') <> ''),
 CHECK (status <> 'rejected' OR COALESCE(TRIM(rejection_reason),'') <> ''),
 CHECK (status <> 'approved' OR power IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS spectacle_camp_receipts (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, camp_id INT UNSIGNED NOT NULL,
 power DECIMAL(5,2) NOT NULL, quantity INT UNSIGNED NOT NULL,
 reference_code VARCHAR(100) NOT NULL, received_date DATE NOT NULL,
 received_by INT UNSIGNED NOT NULL, remarks VARCHAR(500) NOT NULL DEFAULT '',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (camp_id) REFERENCES spectacle_camps(id), FOREIGN KEY (received_by) REFERENCES users(id),
 CHECK (quantity > 0), UNIQUE KEY uq_scr_ref_power (camp_id,reference_code,power)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS spectacle_camp_stock_requests (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, camp_id INT UNSIGNED NOT NULL,
 requested_by INT UNSIGNED NOT NULL, power DECIMAL(5,2) NOT NULL, quantity INT UNSIGNED NOT NULL,
 approved_quantity INT UNSIGNED NULL, planned_distribution_date DATE NOT NULL,
 remarks VARCHAR(500) NOT NULL DEFAULT '',
 status ENUM('pending','approved','rejected','released') NOT NULL DEFAULT 'pending',
 reviewed_by INT UNSIGNED NULL, reviewed_at DATETIME NULL, decision_reason VARCHAR(500) NULL,
 released_by INT UNSIGNED NULL, released_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (camp_id) REFERENCES spectacle_camps(id), FOREIGN KEY (requested_by) REFERENCES users(id),
 FOREIGN KEY (reviewed_by) REFERENCES users(id), FOREIGN KEY (released_by) REFERENCES users(id),
 CHECK (quantity > 0), CHECK (approved_quantity IS NULL OR (approved_quantity > 0 AND approved_quantity <= quantity)),
 INDEX idx_scsr_status (status,camp_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS spectacle_camp_transfers (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, camp_id INT UNSIGNED NOT NULL,
 power DECIMAL(5,2) NOT NULL, quantity INT UNSIGNED NOT NULL, sso_id INT UNSIGNED NOT NULL,
 transferred_by INT UNSIGNED NOT NULL, transfer_date DATE NOT NULL, remarks VARCHAR(500) NOT NULL DEFAULT '',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (camp_id) REFERENCES spectacle_camps(id), FOREIGN KEY (sso_id) REFERENCES users(id),
 FOREIGN KEY (transferred_by) REFERENCES users(id), CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS spectacle_camp_distributions (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, camp_id INT UNSIGNED NOT NULL,
 participant_id INT UNSIGNED NOT NULL UNIQUE, power DECIMAL(5,2) NOT NULL, quantity INT UNSIGNED NOT NULL DEFAULT 1,
 source ENUM('subject-officer','social-service-officer') NOT NULL,
 distributed_by INT UNSIGNED NOT NULL, distribution_date DATE NOT NULL,
 remarks VARCHAR(500) NOT NULL DEFAULT '', created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (camp_id) REFERENCES spectacle_camps(id), FOREIGN KEY (participant_id) REFERENCES spectacle_camp_participants(id),
 FOREIGN KEY (distributed_by) REFERENCES users(id), CHECK (quantity = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS spectacle_camp_events (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, camp_id INT UNSIGNED NOT NULL,
 actor_id INT UNSIGNED NOT NULL, action VARCHAR(50) NOT NULL, details VARCHAR(1000) NOT NULL,
 request_token CHAR(32) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (camp_id) REFERENCES spectacle_camps(id), FOREIGN KEY (actor_id) REFERENCES users(id),
 UNIQUE KEY uq_sce_submission (actor_id,request_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
