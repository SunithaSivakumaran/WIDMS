<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
require_once __DIR__.'/../config/database.php';
$db=database();$failures=[];
$tables=[
 'users','registration_requests','system_settings','user_notifications','notification_reads',
 'suppliers','supplier_authorized_items','inventory_items','stock_receipts','supplier_payments',
 'activity_logs','districts','ds_divisions','gn_divisions','item_categories','eligibility_rules',
 'disability_types','disability_aid_items','disability_item_prohibitions','disability_aid_item_fields',
 'beneficiaries','beneficiary_registration_requests','goods_requests','goods_request_aid_requests',
 'goods_fulfillments','division_inventory','officer_pools','division_pools','pool_allocations','aid_requests',
 'admin_direct_releases',
 'distributions','item_returns','vision_camps','vision_camp_beneficiaries','vision_camp_attendees',
 'vision_camp_handovers','lens_units','lens_unit_history','lens_requests','contact_lens_stock',
 'contact_lens_orders','contact_lens_order_history','contact_lens_order_stock_matches',
 'contact_lens_bulk_orders','contact_lens_bulk_order_items','contact_lens_units',
 'contact_lens_unit_history','correction_requests','widms_schema_migrations','widms_data_migrations',
 'notification_sms_state','notification_sms_outbox','notification_sms_candidates'
 ,'spectacle_camps','spectacle_camp_participants','spectacle_camp_receipts','spectacle_camp_stock_requests','spectacle_camp_letter_templates',
 'spectacle_camp_transfers','spectacle_camp_distributions','spectacle_camp_events'
];
foreach($tables as $table){try{$db->query("SELECT 1 FROM `$table` LIMIT 1");echo "[OK] $table\n";}catch(PDOException $e){$failures[]="Missing/unreadable table: $table";}}
$columns=[
 'spectacle_camp_participants'=>['gender'],
 'stock_receipts'=>['stock_destination','vision_camp_id','ds_division_id'],
 'spectacle_camp_receipts'=>['stock_receipt_id'],
 'spectacle_camp_stock_requests'=>['batch_ref','planned_distribution_time','planned_distribution_place'],
 'users'=>['salary_number','email'],
 'registration_requests'=>['sms_status','sms_error_code','sms_gateway_reference','sms_sent_at','salary_number'],
 'goods_requests'=>['aid_request_id','released_to_subject_id','received_at','allocated_to_sso_by'],
 'goods_fulfillments'=>['handed_to_sso_by'],
 'vision_camps'=>['social_service_officer_id'],
 'contact_lens_orders'=>['original_power','power_changed','stock_check_result'],
 'officer_pools'=>['ds_division_id','returned'],
 'division_pools'=>['ds_division_id','item_id','allocated','distributed','reused'],
 'pool_allocations'=>['ds_division_id'],
 'distributions'=>['ds_division_id'],
 'correction_requests'=>['stock_receipt_id'],
 'aid_requests'=>['prescribed_power','goods_request_ref'],
 'inventory_items'=>['is_returnable','can_sso_distribute'],
 'item_returns'=>['returned_by_name','stock_review_status','stock_reviewed_by','stock_reviewed_at','stock_review_note'],
];
foreach($columns as $table=>$requiredColumns){
 $available=$db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
 foreach($requiredColumns as $column){
  if(!in_array($column,$available,true)){$failures[]="Missing ER column: $table.$column";}else echo "[OK] $table.$column\n";
 }
}
$checks=[
 'Every user has a phone number'=>"SELECT COUNT(*) FROM users WHERE phone IS NULL OR NOT (phone REGEXP '[0-9]' AND CHAR_LENGTH(TRIM(phone))>=7)",
 'Every registration has a phone number'=>"SELECT COUNT(*) FROM registration_requests WHERE phone IS NULL OR NOT (phone REGEXP '[0-9]' AND CHAR_LENGTH(TRIM(phone))>=7)",
 'Officer pool balances are non-negative'=>"SELECT COUNT(*) FROM officer_pools WHERE allocated-distributed+reused<0",
 'Division pool balances are non-negative'=>"SELECT COUNT(*) FROM division_pools WHERE allocated-distributed+reused<0",
 'Division inventory is non-negative'=>"SELECT COUNT(*) FROM division_inventory WHERE quantity<0",
 'Central inventory is non-negative'=>"SELECT COUNT(*) FROM inventory_items WHERE quantity<0",
 'Issued aid requests have distributions'=>"SELECT COUNT(*) FROM aid_requests ar LEFT JOIN distributions d ON d.aid_request_id=ar.id WHERE ar.status='distributed' AND d.id IS NULL",
 'Batched aid requests have goods links'=>"SELECT COUNT(*) FROM aid_requests ar LEFT JOIN goods_request_aid_requests ga ON ga.aid_request_id=ar.id WHERE ar.status='goods-requested' AND ga.aid_request_id IS NULL",
 'Dispatched goods have named Subject Officers'=>"SELECT COUNT(*) FROM goods_requests WHERE status='dispatched' AND released_to_subject_id IS NULL",
 'SSO handovers have an assigned SSO'=>"SELECT COUNT(*) FROM goods_fulfillments WHERE status='pending-sso-handover' AND sso_id IS NULL",
 'Contact lens fulfillments have unit references'=>"SELECT COUNT(*) FROM goods_fulfillments f JOIN aid_requests ar ON ar.id=f.aid_request_id JOIN inventory_items i ON i.id=ar.item_id WHERE LOWER(TRIM(i.item_name)) REGEXP '^contact[[:space:]]*lens(es)?$' AND f.lens_unit_identifier IS NULL",
 'Admin direct distributions have linked history'=>"SELECT COUNT(*) FROM admin_direct_releases adr JOIN aid_requests ar ON ar.id=adr.aid_request_id LEFT JOIN distributions d ON d.aid_request_id=ar.id AND d.distribution_type='direct' WHERE adr.status='distributed' AND (ar.status<>'distributed' OR d.id IS NULL)",
 'Distributed handovers have beneficiary history'=>"SELECT COUNT(*) FROM vision_camp_handovers h LEFT JOIN distributions d ON d.beneficiary_id=h.beneficiary_id AND d.item_id=h.item_id AND d.source='vision-camp' AND d.distributed_at>=h.handed_at WHERE h.status='distributed' AND d.id IS NULL",
];
foreach($checks as $label=>$sql){$count=(int)$db->query($sql)->fetchColumn();if($count){$failures[]="$label: $count violation(s)";}else echo "[OK] $label\n";}
$reportQueries=[
 'Inventory report'=>"SELECT i.item_name FROM inventory_items i LEFT JOIN item_categories c ON c.id=i.category_id LIMIT 1",
 'Distribution report'=>"SELECT d.id FROM distributions d JOIN beneficiaries b ON b.id=d.beneficiary_id JOIN inventory_items i ON i.id=d.item_id JOIN users u ON u.id=d.distributed_by LIMIT 1",
 'Beneficiary report'=>"SELECT b.id FROM beneficiaries b JOIN districts d ON d.id=b.district_id JOIN ds_divisions ds ON ds.id=b.ds_division_id LEFT JOIN distributions x ON x.beneficiary_id=b.id GROUP BY b.id LIMIT 1",
 'Officer pool report'=>"SELECT p.ds_division_id FROM division_pools p JOIN ds_divisions ds ON ds.id=p.ds_division_id JOIN inventory_items i ON i.id=p.item_id LIMIT 1",
 'Division pool overview'=>"SELECT ds.id, ds.name, d.name AS district_name, active_ssos.full_name, p.item_id, GREATEST(COALESCE(p.allocated-p.distributed+p.reused,0),0) AS remaining FROM (SELECT ds_division_id, GROUP_CONCAT(full_name ORDER BY full_name SEPARATOR ', ') AS full_name FROM users WHERE role='social-service-officer' AND status='active' AND ds_division_id IS NOT NULL GROUP BY ds_division_id) active_ssos JOIN ds_divisions ds ON ds.id=active_ssos.ds_division_id JOIN districts d ON d.id=ds.district_id LEFT JOIN division_pools p ON p.ds_division_id=ds.id LIMIT 1",
 'Procurement report'=>"SELECT r.id FROM stock_receipts r JOIN suppliers s ON s.id=r.supplier_id LIMIT 1",
 'Request report'=>"SELECT ar.id FROM aid_requests ar JOIN beneficiaries b ON b.id=ar.beneficiary_id JOIN inventory_items i ON i.id=ar.item_id JOIN users u ON u.id=ar.submitted_by LIMIT 1",
 'Return report'=>"SELECT r.id FROM item_returns r JOIN distributions x ON x.id=r.distribution_id JOIN beneficiaries b ON b.id=x.beneficiary_id JOIN inventory_items i ON i.id=x.item_id LIMIT 1",
 'Audit report'=>"SELECT a.id FROM activity_logs a LEFT JOIN users u ON u.id=a.user_id LIMIT 1",
];
foreach($reportQueries as $label=>$sql){try{$db->query($sql);echo "[OK] $label query\n";}catch(PDOException $e){$failures[]="$label query failed: ".$e->getMessage();}}
if($failures){foreach($failures as $failure)fwrite(STDERR,"[FAIL] $failure\n");exit(1);}echo "WIDMS database verification passed.\n";
