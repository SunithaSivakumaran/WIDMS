USE widms;
SET @aid_admin := (SELECT id FROM users WHERE role='admin' ORDER BY id LIMIT 1);

INSERT INTO item_categories(name,distribution_type,returnable,status,created_by) VALUES
 ('Mobility Aid','request-based',0,'active',@aid_admin),
 ('Vision Aid','request-based',0,'active',@aid_admin),
 ('Hearing Aid','request-based',0,'active',@aid_admin)
ON DUPLICATE KEY UPDATE distribution_type='request-based',status='active';

INSERT INTO inventory_items(item_name,category,variety,quantity) VALUES
 ('Contact Lens','Vision Aid','Prescription',0),
 ('Wheelchair','Mobility Aid','Standard',0),
 ('Hearing Aid','Hearing Aid','Behind-the-ear',0),
 ('Crutches','Mobility Aid','Adjustable',0),
 ('Tricycle','Mobility Aid','Heavy-Duty',0)
ON DUPLICATE KEY UPDATE category=VALUES(category);

UPDATE inventory_items i JOIN item_categories c ON
 (i.item_name='Contact Lens' AND c.name='Vision Aid') OR
 (i.item_name='Wheelchair' AND c.name='Mobility Aid') OR
 (i.item_name='Hearing Aid' AND c.name='Hearing Aid') OR
 (i.item_name IN ('Crutches','Tricycle') AND c.name='Mobility Aid')
SET i.category_id=c.id;

INSERT INTO eligibility_rules(category_id,restriction_months,allow_multiple,status,updated_by)
SELECT id,0,0,'active',@aid_admin FROM item_categories WHERE name IN ('Mobility Aid','Vision Aid','Hearing Aid')
ON DUPLICATE KEY UPDATE status='active',updated_by=VALUES(updated_by);
