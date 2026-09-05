USE widms;

-- This marker makes the destructive dummy-data reset run exactly once.
CREATE TABLE IF NOT EXISTS widms_data_migrations (
    migration_key VARCHAR(120) PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @reset_item_dummy_data := NOT EXISTS (
    SELECT 1 FROM widms_data_migrations
    WHERE migration_key='2026-09-item-eligibility-redesign'
);

SET FOREIGN_KEY_CHECKS=0;
DELETE FROM contact_lens_unit_history WHERE @reset_item_dummy_data=1;
DELETE FROM contact_lens_units WHERE @reset_item_dummy_data=1;
DELETE FROM contact_lens_bulk_order_items WHERE @reset_item_dummy_data=1;
DELETE FROM contact_lens_bulk_orders WHERE @reset_item_dummy_data=1;
DELETE FROM contact_lens_order_stock_matches WHERE @reset_item_dummy_data=1;
DELETE FROM contact_lens_order_history WHERE @reset_item_dummy_data=1;
DELETE FROM contact_lens_orders WHERE @reset_item_dummy_data=1;
DELETE FROM contact_lens_stock WHERE @reset_item_dummy_data=1;
DELETE FROM goods_fulfillments WHERE @reset_item_dummy_data=1;
DELETE FROM goods_request_aid_requests WHERE @reset_item_dummy_data=1;
DELETE FROM item_returns WHERE @reset_item_dummy_data=1;
DELETE FROM vision_camp_handovers WHERE @reset_item_dummy_data=1;
DELETE FROM distributions WHERE @reset_item_dummy_data=1;
DELETE FROM goods_requests WHERE @reset_item_dummy_data=1;
DELETE FROM aid_requests WHERE @reset_item_dummy_data=1;
DELETE FROM pool_allocations WHERE @reset_item_dummy_data=1;
DELETE FROM officer_pools WHERE @reset_item_dummy_data=1;
DELETE FROM division_inventory WHERE @reset_item_dummy_data=1;
DELETE FROM supplier_payments WHERE @reset_item_dummy_data=1;
DELETE FROM stock_receipts WHERE @reset_item_dummy_data=1;
DELETE FROM supplier_authorized_items WHERE @reset_item_dummy_data=1;
DELETE FROM eligibility_rules WHERE @reset_item_dummy_data=1;
DELETE FROM inventory_items WHERE @reset_item_dummy_data=1;
DELETE FROM item_categories WHERE @reset_item_dummy_data=1;
SET FOREIGN_KEY_CHECKS=1;

INSERT IGNORE INTO widms_data_migrations (migration_key)
VALUES ('2026-09-item-eligibility-redesign');

-- Each disability may offer many items, each with its own restriction period.
CREATE TABLE IF NOT EXISTS disability_aid_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    disability_type_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    restriction_months INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_disability_aid_item (disability_type_id,item_id),
    CONSTRAINT fk_disability_aid_type FOREIGN KEY (disability_type_id) REFERENCES disability_types(id),
    CONSTRAINT fk_disability_aid_inventory FOREIGN KEY (item_id) REFERENCES inventory_items(id),
    CONSTRAINT fk_disability_aid_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A previously distributed item blocks itself plus any explicitly selected related items.
CREATE TABLE IF NOT EXISTS disability_item_prohibitions (
    disability_aid_item_id INT UNSIGNED NOT NULL,
    prohibited_item_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (disability_aid_item_id,prohibited_item_id),
    CONSTRAINT fk_item_prohibition_rule FOREIGN KEY (disability_aid_item_id) REFERENCES disability_aid_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_item_prohibition_item FOREIGN KEY (prohibited_item_id) REFERENCES inventory_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
