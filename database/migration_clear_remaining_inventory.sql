USE widms;

-- This marker ensures the remaining dummy inventory is permanently cleared only once.
CREATE TABLE IF NOT EXISTS widms_data_migrations (
    migration_key VARCHAR(120) PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @clear_remaining_inventory := NOT EXISTS (
    SELECT 1 FROM widms_data_migrations
    WHERE migration_key='2026-09-clear-remaining-inventory'
);

-- Dependent dummy workflow records must be removed before their inventory parents.
SET FOREIGN_KEY_CHECKS=0;
DELETE FROM contact_lens_unit_history WHERE @clear_remaining_inventory=1;
DELETE FROM contact_lens_units WHERE @clear_remaining_inventory=1;
DELETE FROM contact_lens_bulk_order_items WHERE @clear_remaining_inventory=1;
DELETE FROM contact_lens_bulk_orders WHERE @clear_remaining_inventory=1;
DELETE FROM contact_lens_order_stock_matches WHERE @clear_remaining_inventory=1;
DELETE FROM contact_lens_order_history WHERE @clear_remaining_inventory=1;
DELETE FROM contact_lens_orders WHERE @clear_remaining_inventory=1;
DELETE FROM contact_lens_stock WHERE @clear_remaining_inventory=1;
DELETE FROM goods_fulfillments WHERE @clear_remaining_inventory=1;
DELETE FROM goods_request_aid_requests WHERE @clear_remaining_inventory=1;
DELETE FROM item_returns WHERE @clear_remaining_inventory=1;
DELETE FROM vision_camp_handovers WHERE @clear_remaining_inventory=1;
DELETE FROM distributions WHERE @clear_remaining_inventory=1;
DELETE FROM goods_requests WHERE @clear_remaining_inventory=1;
DELETE FROM aid_requests WHERE @clear_remaining_inventory=1;
DELETE FROM pool_allocations WHERE @clear_remaining_inventory=1;
DELETE FROM officer_pools WHERE @clear_remaining_inventory=1;
DELETE FROM division_inventory WHERE @clear_remaining_inventory=1;
DELETE FROM supplier_payments WHERE @clear_remaining_inventory=1;
DELETE FROM stock_receipts WHERE @clear_remaining_inventory=1;
DELETE FROM supplier_authorized_items WHERE @clear_remaining_inventory=1;
DELETE FROM disability_item_prohibitions WHERE @clear_remaining_inventory=1;
DELETE FROM disability_aid_items WHERE @clear_remaining_inventory=1;
DELETE FROM eligibility_rules WHERE @clear_remaining_inventory=1;
DELETE FROM inventory_items WHERE @clear_remaining_inventory=1;
DELETE FROM item_categories WHERE @clear_remaining_inventory=1;
SET FOREIGN_KEY_CHECKS=1;

INSERT IGNORE INTO widms_data_migrations (migration_key)
VALUES ('2026-09-clear-remaining-inventory');
