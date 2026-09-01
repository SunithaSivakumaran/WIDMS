USE widms;

SET @spectacles_admin := (SELECT id FROM users WHERE role='admin' ORDER BY id LIMIT 1);

INSERT INTO item_categories(name,distribution_type,returnable,status,created_by)
VALUES ('Vision Aid','request-based',0,'active',@spectacles_admin)
ON DUPLICATE KEY UPDATE distribution_type='request-based',status='active';

INSERT INTO inventory_items(item_name,category,variety,quantity)
VALUES ('Spectacles','Vision Aid','Prescription',0)
ON DUPLICATE KEY UPDATE category='Vision Aid';

UPDATE inventory_items i JOIN item_categories c ON c.name='Vision Aid'
SET i.category_id=c.id WHERE i.item_name='Spectacles';
