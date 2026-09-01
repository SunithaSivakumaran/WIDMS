USE widms;

CREATE TABLE IF NOT EXISTS goods_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    destination_ds_division_id INT UNSIGNED NOT NULL,
    justification VARCHAR(1000) NOT NULL,
    status ENUM('pending-admin-approval','approved-awaiting-dispatch','dispatched','rejected') NOT NULL DEFAULT 'pending-admin-approval',
    rejection_reason VARCHAR(500) NULL,
    requested_by INT UNSIGNED NOT NULL,
    approved_by INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    dispatched_by INT UNSIGNED NULL,
    dispatched_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_goods_item FOREIGN KEY (item_id) REFERENCES inventory_items(id),
    CONSTRAINT fk_goods_destination FOREIGN KEY (destination_ds_division_id) REFERENCES ds_divisions(id),
    CONSTRAINT fk_goods_requester FOREIGN KEY (requested_by) REFERENCES users(id),
    CONSTRAINT fk_goods_approver FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_goods_dispatcher FOREIGN KEY (dispatched_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_goods_status (status),
    INDEX idx_goods_destination (destination_ds_division_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS division_inventory (
    ds_division_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (ds_division_id,item_id),
    CONSTRAINT fk_divstock_ds FOREIGN KEY (ds_division_id) REFERENCES ds_divisions(id),
    CONSTRAINT fk_divstock_item FOREIGN KEY (item_id) REFERENCES inventory_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS officer_pools (
    officer_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    allocated INT UNSIGNED NOT NULL DEFAULT 0,
    distributed INT UNSIGNED NOT NULL DEFAULT 0,
    reused INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (officer_id,item_id),
    CONSTRAINT fk_pool_officer FOREIGN KEY (officer_id) REFERENCES users(id),
    CONSTRAINT fk_pool_item FOREIGN KEY (item_id) REFERENCES inventory_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pool_allocations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    officer_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    allocated_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_allocation_officer FOREIGN KEY (officer_id) REFERENCES users(id),
    CONSTRAINT fk_allocation_item FOREIGN KEY (item_id) REFERENCES inventory_items(id),
    CONSTRAINT fk_allocation_user FOREIGN KEY (allocated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
