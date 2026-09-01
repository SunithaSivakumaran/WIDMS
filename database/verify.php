<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
require_once __DIR__.'/../config/database.php';
$db=database();$failures=[];
$tables=['users','suppliers','inventory_items','stock_receipts','supplier_payments','activity_logs','districts','ds_divisions','gn_divisions','item_categories','eligibility_rules','beneficiaries','beneficiary_registration_requests','goods_requests','goods_request_aid_requests','goods_fulfillments','division_inventory','officer_pools','pool_allocations','aid_requests','distributions','item_returns','vision_camps','vision_camp_beneficiaries','vision_camp_attendees','vision_camp_handovers','lens_units','lens_unit_history','lens_requests','contact_lens_stock','contact_lens_orders','contact_lens_order_history','contact_lens_order_stock_matches','contact_lens_bulk_orders','contact_lens_bulk_order_items','contact_lens_units','contact_lens_unit_history','correction_requests'];
foreach($tables as $table){try{$db->query("SELECT 1 FROM `$table` LIMIT 1");echo "[OK] $table\n";}catch(PDOException $e){$failures[]="Missing/unreadable table: $table";}}
$columns=[
 'goods_requests'=>['aid_request_id','released_to_subject_id','received_at'],
 'vision_camps'=>['social_service_officer_id'],
 'contact_lens_orders'=>['original_power','power_changed','stock_check_result'],
 'officer_pools'=>['ds_division_id','returned'],
 'correction_requests'=>['stock_receipt_id'],
 'aid_requests'=>['prescribed_power','goods_request_ref'],
];
foreach($columns as $table=>$requiredColumns){
 $available=$db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
 foreach($requiredColumns as $column){
  if(!in_array($column,$available,true)){$failures[]="Missing ER column: $table.$column";}else echo "[OK] $table.$column\n";
 }
}
$checks=[
 'Officer pool balances are non-negative'=>"SELECT COUNT(*) FROM officer_pools WHERE allocated-distributed+reused<0",
 'Division inventory is non-negative'=>"SELECT COUNT(*) FROM division_inventory WHERE quantity<0",
 'Central inventory is non-negative'=>"SELECT COUNT(*) FROM inventory_items WHERE quantity<0",
 'Issued aid requests have distributions'=>"SELECT COUNT(*) FROM aid_requests ar LEFT JOIN distributions d ON d.aid_request_id=ar.id WHERE ar.status='distributed' AND d.id IS NULL",
 'Batched aid requests have goods links'=>"SELECT COUNT(*) FROM aid_requests ar LEFT JOIN goods_request_aid_requests ga ON ga.aid_request_id=ar.id WHERE ar.status='goods-requested' AND ga.aid_request_id IS NULL",
 'Dispatched goods have named Subject Officers'=>"SELECT COUNT(*) FROM goods_requests WHERE status='dispatched' AND released_to_subject_id IS NULL",
 'SSO handovers have an assigned SSO'=>"SELECT COUNT(*) FROM goods_fulfillments WHERE status='pending-sso-handover' AND sso_id IS NULL",
 'Contact lens fulfillments have precise matches'=>"SELECT COUNT(*) FROM goods_fulfillments f JOIN aid_requests ar ON ar.id=f.aid_request_id JOIN inventory_items i ON i.id=ar.item_id JOIN item_categories c ON c.id=i.category_id WHERE LOWER(CONCAT(i.item_name,' ',i.variety,' ',c.name)) LIKE '%contact%lens%' AND (ar.prescribed_power IS NULL OR f.lens_unit_identifier IS NULL)",
 'Distributed handovers have beneficiary history'=>"SELECT COUNT(*) FROM vision_camp_handovers h LEFT JOIN distributions d ON d.beneficiary_id=h.beneficiary_id AND d.item_id=h.item_id AND d.source='vision-camp' AND d.distributed_at>=h.handed_at WHERE h.status='distributed' AND d.id IS NULL",
];
foreach($checks as $label=>$sql){$count=(int)$db->query($sql)->fetchColumn();if($count){$failures[]="$label: $count violation(s)";}else echo "[OK] $label\n";}
$reportQueries=[
 'Inventory report'=>"SELECT i.item_name FROM inventory_items i LEFT JOIN item_categories c ON c.id=i.category_id LIMIT 1",
 'Distribution report'=>"SELECT d.id FROM distributions d JOIN beneficiaries b ON b.id=d.beneficiary_id JOIN inventory_items i ON i.id=d.item_id JOIN users u ON u.id=d.distributed_by LIMIT 1",
 'Beneficiary report'=>"SELECT b.id FROM beneficiaries b JOIN districts d ON d.id=b.district_id JOIN ds_divisions ds ON ds.id=b.ds_division_id LEFT JOIN distributions x ON x.beneficiary_id=b.id GROUP BY b.id LIMIT 1",
 'Officer pool report'=>"SELECT p.officer_id FROM officer_pools p JOIN users u ON u.id=p.officer_id LEFT JOIN ds_divisions ds ON ds.id=u.ds_division_id JOIN inventory_items i ON i.id=p.item_id LIMIT 1",
 'Procurement report'=>"SELECT r.id FROM stock_receipts r JOIN suppliers s ON s.id=r.supplier_id LIMIT 1",
 'Request report'=>"SELECT ar.id FROM aid_requests ar JOIN beneficiaries b ON b.id=ar.beneficiary_id JOIN inventory_items i ON i.id=ar.item_id JOIN users u ON u.id=ar.submitted_by LIMIT 1",
 'Return report'=>"SELECT r.id FROM item_returns r JOIN distributions x ON x.id=r.distribution_id JOIN beneficiaries b ON b.id=x.beneficiary_id JOIN inventory_items i ON i.id=x.item_id LIMIT 1",
 'Audit report'=>"SELECT a.id FROM activity_logs a LEFT JOIN users u ON u.id=a.user_id LIMIT 1",
];
foreach($reportQueries as $label=>$sql){try{$db->query($sql);echo "[OK] $label query\n";}catch(PDOException $e){$failures[]="$label query failed: ".$e->getMessage();}}
if($failures){foreach($failures as $failure)fwrite(STDERR,"[FAIL] $failure\n");exit(1);}echo "WIDMS database verification passed.\n";
