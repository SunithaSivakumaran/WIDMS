USE widms;

-- Returnability is item-specific. A shared category can contain both reusable
-- wheelchairs and non-returnable optical products.
ALTER TABLE inventory_items
    ADD COLUMN IF NOT EXISTS is_returnable TINYINT(1) NOT NULL DEFAULT 0 AFTER quantity;

ALTER TABLE item_returns
    ADD COLUMN IF NOT EXISTS returned_by_name VARCHAR(150) NULL AFTER quantity;
