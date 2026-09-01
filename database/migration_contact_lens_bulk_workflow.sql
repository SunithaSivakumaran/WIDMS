USE widms;

CREATE TABLE IF NOT EXISTS contact_lens_bulk_orders (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_code VARCHAR(30) NOT NULL UNIQUE,
 supplier_id INT UNSIGNED NOT NULL,
 status ENUM('draft','pending-admin-approval','approved','rejected','partially-received','fully-received','completed') NOT NULL DEFAULT 'draft',
 rejection_reason VARCHAR(500) NULL,
 created_by INT UNSIGNED NOT NULL,
 submitted_at DATETIME NULL,
 reviewed_by INT UNSIGNED NULL,
 reviewed_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_cl_bulk_supplier FOREIGN KEY(supplier_id) REFERENCES suppliers(id),
 CONSTRAINT fk_cl_bulk_creator FOREIGN KEY(created_by) REFERENCES users(id),
 CONSTRAINT fk_cl_bulk_reviewer FOREIGN KEY(reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_cl_bulk_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_lens_bulk_order_items (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 bulk_order_id INT UNSIGNED NOT NULL,
 aid_request_id INT UNSIGNED NOT NULL UNIQUE,
 power DECIMAL(5,2) NOT NULL,
 quantity INT UNSIGNED NOT NULL DEFAULT 1,
 received_quantity INT UNSIGNED NOT NULL DEFAULT 0,
 CONSTRAINT fk_cl_bulk_item_order FOREIGN KEY(bulk_order_id) REFERENCES contact_lens_bulk_orders(id) ON DELETE CASCADE,
 CONSTRAINT fk_cl_bulk_item_request FOREIGN KEY(aid_request_id) REFERENCES aid_requests(id),
 INDEX idx_cl_bulk_item_power(bulk_order_id,power)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_lens_units (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 unit_code VARCHAR(40) NOT NULL UNIQUE,
 bulk_order_id INT UNSIGNED NOT NULL,
 bulk_order_item_id INT UNSIGNED NOT NULL,
 power DECIMAL(5,2) NOT NULL,
 status ENUM('available','reserved','pending-handover','distributed','returned-to-vendor') NOT NULL DEFAULT 'available',
 aid_request_id INT UNSIGNED NULL,
 assigned_by INT UNSIGNED NULL,
 assigned_at DATETIME NULL,
 sso_id INT UNSIGNED NULL,
 distributed_by INT UNSIGNED NULL,
 distributed_at DATETIME NULL,
 returned_at DATETIME NULL,
 return_reason VARCHAR(500) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_cl_unit_order FOREIGN KEY(bulk_order_id) REFERENCES contact_lens_bulk_orders(id),
 CONSTRAINT fk_cl_unit_item FOREIGN KEY(bulk_order_item_id) REFERENCES contact_lens_bulk_order_items(id),
 CONSTRAINT fk_cl_unit_request FOREIGN KEY(aid_request_id) REFERENCES aid_requests(id) ON DELETE SET NULL,
 CONSTRAINT fk_cl_unit_assigner FOREIGN KEY(assigned_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_cl_unit_sso FOREIGN KEY(sso_id) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_cl_unit_distributor FOREIGN KEY(distributed_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_cl_unit_match(power,status), INDEX idx_cl_unit_request(aid_request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_lens_unit_history (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 lens_unit_id INT UNSIGNED NOT NULL,
 event_type ENUM('received','assigned','handover','distributed','returned-to-vendor') NOT NULL,
 aid_request_id INT UNSIGNED NULL,
 power DECIMAL(5,2) NOT NULL,
 notes VARCHAR(500) NULL,
 performed_by INT UNSIGNED NOT NULL,
 event_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_cl_history_unit FOREIGN KEY(lens_unit_id) REFERENCES contact_lens_units(id),
 CONSTRAINT fk_cl_history_request FOREIGN KEY(aid_request_id) REFERENCES aid_requests(id) ON DELETE SET NULL,
 CONSTRAINT fk_cl_history_user FOREIGN KEY(performed_by) REFERENCES users(id),
 INDEX idx_cl_history_unit_date(lens_unit_id,event_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
