-- Contact Lens stock is tracked by quantity, not prescription power. Remove
-- only its obsolete built-in Power field. Optional beneficiary-information
-- fields, including a user-configured Power field, remain available.
UPDATE disability_aid_items dai
JOIN inventory_items item ON item.id = dai.item_id
SET dai.beneficiary_field_label = NULL,
    dai.beneficiary_field_type = 'text'
WHERE LOWER(TRIM(item.item_name)) REGEXP '^contact[[:space:]]*lens(es)?$'
  AND LOWER(TRIM(dai.beneficiary_field_label)) IN ('power', 'prescription power', 'prescribed power');

DELETE field FROM disability_aid_item_fields field
JOIN disability_aid_items dai ON dai.id = field.disability_aid_item_id
JOIN inventory_items item ON item.id = dai.item_id
WHERE LOWER(TRIM(item.item_name)) REGEXP '^contact[[:space:]]*lens(es)?$'
  AND field.is_system = 1
  AND LOWER(TRIM(field.field_label)) IN ('power', 'prescription power', 'prescribed power');
