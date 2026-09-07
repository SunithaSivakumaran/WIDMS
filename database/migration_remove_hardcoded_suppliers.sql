USE widms;

-- Remove only the unused demo suppliers that were previously inserted by
-- seed.sql and migration_stock_receiving.sql. Suppliers registered through
-- the application, and any supplier with operational history, are preserved.
DELETE s
FROM suppliers s
WHERE s.company_name IN (
    'ABC Medical Co. Ltd',
    'Vision Care Co. Ltd',
    'HealthTech Pvt Ltd'
)
  AND s.created_by IS NULL
  AND s.contact_person IS NULL
  AND s.email IS NULL
  AND s.phone IS NULL
  AND s.address IS NULL
  AND s.valid_from IS NULL
  AND s.validity_period IS NULL
  AND s.validity_unit IS NULL
  AND NOT EXISTS (SELECT 1 FROM supplier_authorized_items sai WHERE sai.supplier_id = s.id)
  AND NOT EXISTS (SELECT 1 FROM stock_receipts sr WHERE sr.supplier_id = s.id)
  AND NOT EXISTS (SELECT 1 FROM supplier_payments sp WHERE sp.supplier_id = s.id)
  AND NOT EXISTS (SELECT 1 FROM vision_camps vc WHERE vc.supplier_id = s.id)
  AND NOT EXISTS (SELECT 1 FROM contact_lens_bulk_orders clbo WHERE clbo.supplier_id = s.id)
  AND NOT EXISTS (SELECT 1 FROM contact_lens_stock cls WHERE cls.supplier_id = s.id);
