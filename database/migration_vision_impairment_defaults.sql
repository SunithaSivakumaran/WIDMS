USE widms;

-- Protect the permanent vision configuration while leaving officer-added
-- beneficiary fields editable.
ALTER TABLE disability_types
    ADD COLUMN IF NOT EXISTS is_system TINYINT(1) NOT NULL DEFAULT 0 AFTER status;
ALTER TABLE disability_aid_items
    ADD COLUMN IF NOT EXISTS is_system TINYINT(1) NOT NULL DEFAULT 0 AFTER restriction_months;
ALTER TABLE disability_aid_item_fields
    ADD COLUMN IF NOT EXISTS is_system TINYINT(1) NOT NULL DEFAULT 0 AFTER display_order;

SET @system_user_id := (
    SELECT id FROM users
    WHERE role IN ('admin', 'subject-officer')
    ORDER BY FIELD(role, 'admin', 'subject-officer'), id
    LIMIT 1
);

-- Preserve an existing vision configuration by normalizing its name.
SET @vision_id := (
    SELECT id FROM disability_types
    WHERE LOWER(TRIM(name)) = 'vision impairment'
    ORDER BY id LIMIT 1
);
SET @legacy_vision_id := (
    SELECT id FROM disability_types
    WHERE LOWER(TRIM(name)) IN ('vision problem', 'visual impairment')
    ORDER BY id LIMIT 1
);
UPDATE disability_types
SET name = 'Vision Impairment'
WHERE id = @legacy_vision_id AND @vision_id IS NULL;
INSERT INTO disability_types(name, status, is_system, created_by)
SELECT 'Vision Impairment', 'active', 1, @system_user_id
WHERE NOT EXISTS (
    SELECT 1 FROM disability_types WHERE LOWER(TRIM(name)) = 'vision impairment'
);
SET @vision_id := (
    SELECT id FROM disability_types
    WHERE LOWER(TRIM(name)) = 'vision impairment'
    ORDER BY id LIMIT 1
);
UPDATE disability_types SET name='Vision Impairment', status='active', is_system=1
WHERE id=@vision_id;

-- Keep existing beneficiary records linked to the normalized disability name.
UPDATE beneficiaries
SET disability='Vision Impairment'
WHERE LOWER(TRIM(disability)) IN ('vision problem', 'visual impairment');
UPDATE beneficiary_registration_requests
SET disability='Vision Impairment'
WHERE LOWER(TRIM(disability)) IN ('vision problem', 'visual impairment');

INSERT INTO item_categories(name, distribution_type, returnable, status, created_by)
VALUES('Configured Disability Aid', 'request-based', 0, 'active', @system_user_id)
ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id), status='active';
SET @vision_category_id := (
    SELECT id FROM item_categories WHERE name='Configured Disability Aid' LIMIT 1
);

-- Reuse current records so historic requests keep their item identifiers.
SET @contact_lens_item_id := (
    SELECT id FROM inventory_items
    WHERE LOWER(TRIM(item_name)) IN ('contact lens', 'cantact lens')
    ORDER BY FIELD(LOWER(TRIM(item_name)), 'contact lens', 'cantact lens'), id
    LIMIT 1
);
INSERT INTO inventory_items(item_name, category, category_id, variety, quantity)
SELECT 'Contact Lens', 'Configured Disability Aid', @vision_category_id, '', 0
WHERE @contact_lens_item_id IS NULL;
SET @contact_lens_item_id := (
    SELECT id FROM inventory_items
    WHERE LOWER(TRIM(item_name)) IN ('contact lens', 'cantact lens')
    ORDER BY FIELD(LOWER(TRIM(item_name)), 'contact lens', 'cantact lens'), id
    LIMIT 1
);
UPDATE inventory_items
SET item_name='Contact Lens', category='Configured Disability Aid', category_id=@vision_category_id
WHERE id=@contact_lens_item_id;

SET @spectacles_item_id := (
    SELECT id FROM inventory_items
    WHERE LOWER(TRIM(item_name)) IN ('spectacles', 'specs', 'spectaculars')
    ORDER BY FIELD(LOWER(TRIM(item_name)), 'spectacles', 'specs', 'spectaculars'), id
    LIMIT 1
);
INSERT INTO inventory_items(item_name, category, category_id, variety, quantity)
SELECT 'Spectacles', 'Configured Disability Aid', @vision_category_id, '', 0
WHERE @spectacles_item_id IS NULL;
SET @spectacles_item_id := (
    SELECT id FROM inventory_items
    WHERE LOWER(TRIM(item_name)) IN ('spectacles', 'specs', 'spectaculars')
    ORDER BY FIELD(LOWER(TRIM(item_name)), 'spectacles', 'specs', 'spectaculars'), id
    LIMIT 1
);
UPDATE inventory_items
SET item_name='Spectacles', category='Configured Disability Aid', category_id=@vision_category_id
WHERE id=@spectacles_item_id;

INSERT INTO disability_aid_items(
    disability_type_id, item_id, restriction_months,
    beneficiary_field_label, beneficiary_field_type,
    is_system, status, created_by
)
SELECT @vision_id, @contact_lens_item_id, 0, 'Power', 'number', 1, 'active', @system_user_id
WHERE NOT EXISTS (
    SELECT 1 FROM disability_aid_items
    WHERE disability_type_id=@vision_id AND item_id=@contact_lens_item_id
);
INSERT INTO disability_aid_items(
    disability_type_id, item_id, restriction_months,
    beneficiary_field_label, beneficiary_field_type,
    is_system, status, created_by
)
SELECT @vision_id, @spectacles_item_id, 0, 'Power', 'number', 1, 'active', @system_user_id
WHERE NOT EXISTS (
    SELECT 1 FROM disability_aid_items
    WHERE disability_type_id=@vision_id AND item_id=@spectacles_item_id
);

UPDATE disability_aid_items
SET beneficiary_field_label='Power', beneficiary_field_type='number', is_system=1, status='active'
WHERE disability_type_id=@vision_id
  AND item_id IN (@contact_lens_item_id, @spectacles_item_id);

SET @contact_lens_rule_id := (
    SELECT id FROM disability_aid_items
    WHERE disability_type_id=@vision_id AND item_id=@contact_lens_item_id LIMIT 1
);
SET @spectacles_rule_id := (
    SELECT id FROM disability_aid_items
    WHERE disability_type_id=@vision_id AND item_id=@spectacles_item_id LIMIT 1
);

-- If both legacy labels exist, retain only the canonical Power definition.
DELETE legacy_field
FROM disability_aid_item_fields legacy_field
JOIN disability_aid_item_fields power_field
  ON power_field.disability_aid_item_id=legacy_field.disability_aid_item_id
 AND LOWER(TRIM(power_field.field_label))='power'
WHERE legacy_field.disability_aid_item_id IN (@contact_lens_rule_id, @spectacles_rule_id)
  AND LOWER(TRIM(legacy_field.field_label))='prescription power';

UPDATE disability_aid_item_fields
SET field_label='Power', field_type='number', display_order=1, is_system=1
WHERE disability_aid_item_id IN (@contact_lens_rule_id, @spectacles_rule_id)
  AND LOWER(TRIM(field_label)) IN ('power', 'prescription power');

INSERT INTO disability_aid_item_fields(
    disability_aid_item_id, field_label, field_type, display_order, is_system
)
SELECT @contact_lens_rule_id, 'Power', 'number', 1, 1
WHERE NOT EXISTS (
    SELECT 1 FROM disability_aid_item_fields
    WHERE disability_aid_item_id=@contact_lens_rule_id AND LOWER(TRIM(field_label))='power'
);
INSERT INTO disability_aid_item_fields(
    disability_aid_item_id, field_label, field_type, display_order, is_system
)
SELECT @spectacles_rule_id, 'Power', 'number', 1, 1
WHERE NOT EXISTS (
    SELECT 1 FROM disability_aid_item_fields
    WHERE disability_aid_item_id=@spectacles_rule_id AND LOWER(TRIM(field_label))='power'
);
