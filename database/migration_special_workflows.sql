USE widms;

CREATE TABLE IF NOT EXISTS item_returns (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, 
 distribution_id INT UNSIGNED NOT NULL,
 quantity INT UNSIGNED NOT NULL,
 item_condition ENUM('good','damaged','unusable') NOT NULL,
 reusable TINYINT(1) NOT NULL, 
 restore_to ENUM('officer-pool','central-stock','removed') NOT NULL,
 processed_by INT UNSIGNED NOT NULL, processed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_return_distribution FOREIGN KEY(distribution_id) REFERENCES distributions(id),
 CONSTRAINT fk_return_processor FOREIGN KEY(processed_by) REFERENCES users(id), 
 INDEX idx_return_distribution(distribution_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vision_camps (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, ds_division_id INT UNSIGNED NOT NULL, supplier_id INT UNSIGNED NOT NULL,
 justification VARCHAR(1000) NULL, 
 proposed_date DATE NULL, people_identified INT UNSIGNED NULL, attended_count INT UNSIGNED NULL,
 distributed_count INT UNSIGNED NOT NULL DEFAULT 0, handed_over_count INT UNSIGNED NOT NULL DEFAULT 0,
 stage ENUM('awaiting-vendor-approval','vendor-approved','awaiting-goods-release','distribution-in-progress','completed','rejected') NOT NULL DEFAULT 'awaiting-vendor-approval',
 rejection_reason VARCHAR(500) NULL, 
 requested_by INT UNSIGNED NOT NULL, 
 vendor_reviewed_by INT UNSIGNED NULL,
 vendor_reviewed_at DATETIME NULL, 
 goods_released_by INT UNSIGNED NULL, 
 goods_released_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, 
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_camp_ds FOREIGN KEY(ds_division_id) REFERENCES ds_divisions(id), 
 CONSTRAINT fk_camp_supplier FOREIGN KEY(supplier_id) REFERENCES suppliers(id),
 CONSTRAINT fk_camp_requester FOREIGN KEY(requested_by) REFERENCES users(id), 
 CONSTRAINT fk_camp_vendor_reviewer FOREIGN KEY(vendor_reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_camp_release_reviewer FOREIGN KEY(goods_released_by) REFERENCES users(id) ON DELETE SET NULL, 
 INDEX idx_camp_stage(stage)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE vision_camps ADD COLUMN IF NOT EXISTS justification VARCHAR(1000) NULL AFTER supplier_id;

CREATE TABLE IF NOT EXISTS contact_lens_stock (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, 
 power DECIMAL(5,2) NOT NULL UNIQUE, 
 quantity INT UNSIGNED NOT NULL DEFAULT 0,
 supplier_id INT UNSIGNED NULL, 
 last_received DATE NULL, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_lens_stock_supplier FOREIGN KEY(supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_lens_orders (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, 
 beneficiary_id INT UNSIGNED NOT NULL, 
 requested_power DECIMAL(5,2) NOT NULL,
 current_power DECIMAL(5,2) NULL, status ENUM('pending','approved','rejected','issued','procurement-required') NOT NULL DEFAULT 'pending',
 rejection_reason VARCHAR(500) NULL, 
 submitted_by INT UNSIGNED NOT NULL, reviewed_by INT UNSIGNED NULL, 
 reviewed_at DATETIME NULL,
 issued_by INT UNSIGNED NULL, 
 issued_at DATETIME NULL, 
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_lens_order_beneficiary FOREIGN KEY(beneficiary_id) REFERENCES beneficiaries(id),
 CONSTRAINT fk_lens_order_submitter FOREIGN KEY(submitted_by) REFERENCES users(id), 
 CONSTRAINT fk_lens_order_reviewer FOREIGN KEY(reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_lens_order_issuer FOREIGN KEY(issued_by) REFERENCES users(id) ON DELETE SET NULL, INDEX idx_lens_order_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE distributions MODIFY source ENUM('officer-pool','vision-camp') NOT NULL DEFAULT 'officer-pool';

CREATE TABLE IF NOT EXISTS vision_camp_handovers (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, 
 camp_id INT UNSIGNED NOT NULL, 
 beneficiary_id INT UNSIGNED NOT NULL,
 item_id INT UNSIGNED NOT NULL, 
 quantity INT UNSIGNED NOT NULL DEFAULT 1, 
 officer_id INT UNSIGNED NOT NULL,
 status ENUM('pending','distributed') NOT NULL DEFAULT 'pending', 
 handed_by INT UNSIGNED NOT NULL,
 handed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, 
 distributed_at DATETIME NULL,
 CONSTRAINT fk_handover_camp FOREIGN KEY(camp_id) REFERENCES vision_camps(id), 
 CONSTRAINT fk_handover_beneficiary FOREIGN KEY(beneficiary_id) REFERENCES beneficiaries(id),
 CONSTRAINT fk_handover_item FOREIGN KEY(item_id) REFERENCES inventory_items(id), 
 CONSTRAINT fk_handover_officer FOREIGN KEY(officer_id) REFERENCES users(id),
 CONSTRAINT fk_handover_subject FOREIGN KEY(handed_by) REFERENCES users(id), 
 INDEX idx_handover_officer_status(officer_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
