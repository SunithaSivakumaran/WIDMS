-- Pool stock belongs to the DS Division, not to the officer currently assigned.
-- Keep officer_pools as a legacy audit snapshot; future writes use division_pools.
CREATE TABLE IF NOT EXISTS division_pools (
    ds_division_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    allocated INT UNSIGNED NOT NULL DEFAULT 0,
    distributed INT UNSIGNED NOT NULL DEFAULT 0,
    reused INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (ds_division_id, item_id),
    CONSTRAINT fk_division_pool_division FOREIGN KEY (ds_division_id) REFERENCES ds_divisions(id),
    CONSTRAINT fk_division_pool_item FOREIGN KEY (item_id) REFERENCES inventory_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Explicit pool division wins; older rows fall back to the officer assignment.
-- SET rather than increment makes retry after an interrupted migration safe.
INSERT INTO division_pools (ds_division_id, item_id, allocated, distributed, reused)
SELECT COALESCE(p.ds_division_id, u.ds_division_id), p.item_id,
       SUM(p.allocated), SUM(p.distributed), SUM(p.reused)
FROM officer_pools p
JOIN users u ON u.id = p.officer_id
WHERE COALESCE(p.ds_division_id, u.ds_division_id) IS NOT NULL
GROUP BY COALESCE(p.ds_division_id, u.ds_division_id), p.item_id
ON DUPLICATE KEY UPDATE
    allocated = VALUES(allocated),
    distributed = VALUES(distributed),
    reused = VALUES(reused);

ALTER TABLE pool_allocations ADD COLUMN IF NOT EXISTS ds_division_id INT UNSIGNED NULL AFTER officer_id;
UPDATE pool_allocations pa
JOIN users u ON u.id = pa.officer_id
SET pa.ds_division_id = u.ds_division_id
WHERE pa.ds_division_id IS NULL;

ALTER TABLE distributions ADD COLUMN IF NOT EXISTS ds_division_id INT UNSIGNED NULL AFTER beneficiary_id;
UPDATE distributions d
JOIN beneficiaries b ON b.id = d.beneficiary_id
SET d.ds_division_id = b.ds_division_id
WHERE d.ds_division_id IS NULL;
