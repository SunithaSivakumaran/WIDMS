USE widms;

-- Preserve existing pending/approved requests when an older installation did
-- not create the corresponding disability-to-aid rule at request time.
INSERT IGNORE INTO disability_aid_items
    (disability_type_id, item_id, restriction_months, is_system, status, created_by)
SELECT DISTINCT
    dt.id,
    ar.item_id,
    0,
    0,
    'active',
    COALESCE(ar.submitted_by, (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1))
FROM aid_requests ar
JOIN beneficiaries b ON b.id = ar.beneficiary_id
JOIN disability_types dt
    ON LOWER(TRIM(dt.name)) COLLATE utf8mb4_unicode_ci = LOWER(TRIM(b.disability)) COLLATE utf8mb4_unicode_ci
   AND dt.status = 'active'
LEFT JOIN disability_aid_items dai
    ON dai.disability_type_id = dt.id
   AND dai.item_id = ar.item_id
WHERE ar.item_id IS NOT NULL
  AND dai.id IS NULL
  AND COALESCE(ar.submitted_by, (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1)) IS NOT NULL;
