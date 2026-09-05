USE widms;

-- Service divisions can receive an SSO but must not increase the official DS Division count.
ALTER TABLE ds_divisions
    ADD COLUMN IF NOT EXISTS division_type ENUM('official','service-centre') NOT NULL DEFAULT 'official' AFTER name;

SET @service_division_creator := (
    SELECT id FROM users ORDER BY (role='admin') DESC, id ASC LIMIT 1
);

-- Insertions are idempotent so rerunning the migration cannot create duplicate divisions.
INSERT INTO ds_divisions (district_id, name, division_type, status, created_by)
SELECT d.id, service_area.name, 'service-centre', 'active', @service_division_creator
FROM districts d
CROSS JOIN (
    SELECT 'Suraliya Sewana Center' AS name
    UNION ALL
    SELECT 'Senda Arana Elder Home'
) service_area
WHERE d.name='Galle'
  AND @service_division_creator IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM ds_divisions existing
      WHERE existing.district_id=d.id AND existing.name=service_area.name
  );

-- Existing matching records are classified correctly if they were entered manually.
UPDATE ds_divisions ds
JOIN districts d ON d.id=ds.district_id
SET ds.division_type='service-centre', ds.status='active'
WHERE d.name='Galle'
  AND ds.name IN ('Suraliya Sewana Center','Senda Arana Elder Home');
