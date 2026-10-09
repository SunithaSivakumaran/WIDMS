-- Spectacles use a type, never a numeric prescription power. Contact Lens
-- retains its signed-power workflow. Existing spectacle records are assigned
-- Reading Only before their legacy power values are cleared separately.
CREATE TABLE IF NOT EXISTS spectacle_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    display_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_spectacle_category_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO spectacle_categories (name, display_order) VALUES
('Reading Only', 1),
('Distance Only', 2),
('Bifocal', 3),
('Cylinder Bifocal', 4),
('Cylinder Distance', 5),
('Child', 6);

ALTER TABLE aid_requests ADD COLUMN IF NOT EXISTS spectacle_category_id INT UNSIGNED NULL AFTER prescribed_power;
ALTER TABLE spectacle_camp_participants ADD COLUMN IF NOT EXISTS spectacle_category_id INT UNSIGNED NULL AFTER power;
ALTER TABLE spectacle_camp_receipts ADD COLUMN IF NOT EXISTS spectacle_category_id INT UNSIGNED NULL AFTER power;
ALTER TABLE spectacle_camp_stock_requests ADD COLUMN IF NOT EXISTS spectacle_category_id INT UNSIGNED NULL AFTER power;
ALTER TABLE spectacle_camp_transfers ADD COLUMN IF NOT EXISTS spectacle_category_id INT UNSIGNED NULL AFTER power;
ALTER TABLE spectacle_camp_distributions ADD COLUMN IF NOT EXISTS spectacle_category_id INT UNSIGNED NULL AFTER power;
ALTER TABLE spectacle_camp_receipts MODIFY COLUMN power DECIMAL(5,2) NULL;
ALTER TABLE spectacle_camp_stock_requests MODIFY COLUMN power DECIMAL(5,2) NULL;
ALTER TABLE spectacle_camp_transfers MODIFY COLUMN power DECIMAL(5,2) NULL;
ALTER TABLE spectacle_camp_distributions MODIFY COLUMN power DECIMAL(5,2) NULL;
ALTER TABLE spectacle_camp_receipts ADD UNIQUE KEY IF NOT EXISTS
    uq_camp_receipt_category (camp_id, reference_code, spectacle_category_id);

CREATE TABLE IF NOT EXISTS stock_receipt_spectacle_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stock_receipt_id INT UNSIGNED NOT NULL,
    spectacle_category_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_cost DECIMAL(12,2) NOT NULL,
    total_cost DECIMAL(14,2) NOT NULL,
    UNIQUE KEY uq_receipt_spectacle_category (stock_receipt_id, spectacle_category_id),
    CONSTRAINT fk_receipt_spectacle_receipt FOREIGN KEY (stock_receipt_id) REFERENCES stock_receipts(id),
    CONSTRAINT fk_receipt_spectacle_category FOREIGN KEY (spectacle_category_id) REFERENCES spectacle_categories(id),
    CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @reading_category_id := (SELECT id FROM spectacle_categories WHERE name = 'Reading Only' LIMIT 1);

UPDATE aid_requests ar
JOIN inventory_items i ON i.id = ar.item_id
SET ar.spectacle_category_id = @reading_category_id
WHERE LOWER(TRIM(i.item_name)) IN ('spectacles', 'spectacle', 'specs', 'glasses')
  AND ar.spectacle_category_id IS NULL;

UPDATE spectacle_camp_participants SET spectacle_category_id = @reading_category_id
WHERE status = 'approved' AND spectacle_category_id IS NULL;
UPDATE spectacle_camp_receipts SET spectacle_category_id = @reading_category_id
WHERE spectacle_category_id IS NULL;
UPDATE spectacle_camp_stock_requests SET spectacle_category_id = @reading_category_id
WHERE spectacle_category_id IS NULL;
UPDATE spectacle_camp_transfers SET spectacle_category_id = @reading_category_id
WHERE spectacle_category_id IS NULL;
UPDATE spectacle_camp_distributions SET spectacle_category_id = @reading_category_id
WHERE spectacle_category_id IS NULL;

INSERT INTO stock_receipt_spectacle_lines
    (stock_receipt_id, spectacle_category_id, quantity, unit_cost, total_cost)
SELECT r.id, @reading_category_id, r.quantity, r.unit_cost, r.total_cost
FROM stock_receipts r
JOIN inventory_items i ON i.id = r.item_id
WHERE LOWER(TRIM(i.item_name)) IN ('spectacles', 'spectacle', 'specs', 'glasses')
  AND NOT EXISTS (
      SELECT 1 FROM stock_receipt_spectacle_lines line
      WHERE line.stock_receipt_id = r.id
  );

-- The built-in Spectacles rule now exposes a category selector in the request
-- form. Contact Lens keeps its built-in numeric Power field.
UPDATE disability_aid_items dai
JOIN inventory_items i ON i.id = dai.item_id
SET dai.beneficiary_field_label = 'Spectacle Type',
    dai.beneficiary_field_type = 'text'
WHERE dai.is_system = 1
  AND LOWER(TRIM(i.item_name)) IN ('spectacles', 'spectacle', 'specs', 'glasses');

UPDATE disability_aid_item_fields field
JOIN disability_aid_items dai ON dai.id = field.disability_aid_item_id
JOIN inventory_items i ON i.id = dai.item_id
SET field.field_label = 'Spectacle Type',
    field.field_type = 'text'
WHERE field.is_system = 1
  AND LOWER(TRIM(i.item_name)) IN ('spectacles', 'spectacle', 'specs', 'glasses')
  AND LOWER(TRIM(field.field_label)) IN ('power', 'prescription power', 'prescribed power');
