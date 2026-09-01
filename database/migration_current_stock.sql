USE widms;

ALTER TABLE inventory_items ADD COLUMN IF NOT EXISTS category VARCHAR(100) NOT NULL DEFAULT 'General Aid' AFTER item_name;

UPDATE inventory_items SET category = 'Mobility Aid' WHERE item_name IN ('Wheelchair', 'Tricycle', 'Crutches');
UPDATE inventory_items SET category = 'Vision Aid' WHERE item_name IN ('Glasses', 'Spectacles');
UPDATE inventory_items SET category = 'Medical Aid' WHERE item_name = 'Hearing Aid';
