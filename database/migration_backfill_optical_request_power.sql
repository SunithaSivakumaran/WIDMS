-- Older Contact Lens requests kept the signed prescription in the configured aid
-- detail field but did not copy it into the indexed prescribed_power column.
-- Only unambiguous numeric values are copied; missing details remain NULL.
UPDATE aid_requests AS ar
JOIN inventory_items AS i ON i.id = ar.item_id
JOIN item_categories AS c ON c.id = i.category_id
SET ar.prescribed_power = CAST(TRIM(ar.beneficiary_detail_value) AS DECIMAL(5,2))
WHERE ar.prescribed_power IS NULL
  AND LOWER(TRIM(ar.beneficiary_detail_label)) IN ('power', 'prescription power', 'prescribed power')
  AND TRIM(ar.beneficiary_detail_value) REGEXP '^[+-]?[0-9]{1,3}([.][0-9]{1,2})?$'
  AND LOWER(TRIM(i.item_name)) REGEXP 'contact[[:space:]]*lens';
