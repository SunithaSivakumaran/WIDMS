USE widms;

-- Remove the unwanted legacy/demo item rules first; their prohibition links cascade automatically.
DELETE dai
FROM disability_aid_items dai
JOIN inventory_items i ON i.id = dai.item_id
WHERE i.item_name IN ('Crutches', 'Glasses', 'Hearing Aid', 'Wheelchair');

-- Permanently remove only unused demo inventory. Operational history is deliberately never deleted by a migration.
DELETE i
FROM inventory_items i
WHERE i.item_name IN ('Crutches', 'Glasses', 'Hearing Aid', 'Wheelchair')
  AND NOT EXISTS (SELECT 1 FROM aid_requests ar WHERE ar.item_id = i.id)
  AND NOT EXISTS (SELECT 1 FROM distributions d WHERE d.item_id = i.id)
  AND NOT EXISTS (SELECT 1 FROM goods_requests gr WHERE gr.item_id = i.id)
  AND NOT EXISTS (SELECT 1 FROM stock_receipts sr WHERE sr.item_id = i.id)
  AND NOT EXISTS (SELECT 1 FROM division_inventory di WHERE di.item_id = i.id)
  AND NOT EXISTS (SELECT 1 FROM officer_pools op WHERE op.item_id = i.id)
  AND NOT EXISTS (SELECT 1 FROM pool_allocations pa WHERE pa.item_id = i.id);
