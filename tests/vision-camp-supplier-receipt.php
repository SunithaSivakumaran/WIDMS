<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/permissions.php';
require_once __DIR__.'/../includes/notification-sms.php';
require_once __DIR__.'/../includes/spectacle-camps.php';
$db=database();
foreach (['users','suppliers','supplier_authorized_items','inventory_items','districts','ds_divisions','gn_divisions',
    'spectacle_categories','spectacle_camps','spectacle_camp_participants','spectacle_camp_stock_requests','spectacle_camp_transfers','spectacle_camp_distributions','stock_receipts','stock_receipt_spectacle_lines','spectacle_camp_receipts','spectacle_camp_events',
    'user_notifications','notification_sms_outbox','activity_logs'] as $table) {
    $db->exec('CREATE TEMPORARY TABLE supplier_fixture_'.$table.' LIKE '.$table);
    $db->exec('CREATE TEMPORARY TABLE '.$table.' LIKE supplier_fixture_'.$table);
}
$db->exec("INSERT INTO users(id,full_name,username,phone,password_hash,role,status) VALUES
    (1,'Keeper Fixture','store-fixture','0771111111','fixture','store-keeper','active'),
    (2,'Conducting Officer Fixture','subject-fixture','0772222222','fixture','subject-officer','active'),
    (3,'Requesting Officer Fixture','requester-fixture','0773333333','fixture','subject-officer','active');
    INSERT INTO districts(id,name,created_by) VALUES (1,'Fixture district',2);
    INSERT INTO ds_divisions(id,district_id,name,created_by) VALUES (1,1,'Fixture division',2);
    INSERT INTO gn_divisions(id,ds_division_id,name,created_by) VALUES (1,1,'Fixture GN',2);
    INSERT INTO inventory_items(id,item_name,quantity) VALUES (1,'Spectacles',9);
    INSERT INTO spectacle_categories(id,name,display_order) VALUES (1,'Reading Only',1),(2,'Distance Only',2);
    INSERT INTO suppliers(id,company_name,status) VALUES (1,'Fixture Supplier','active');
    INSERT INTO supplier_authorized_items(supplier_id,item_id,authorized_by,status) VALUES (1,1,2,'active');
    INSERT INTO spectacle_camps(id,district_id,ds_division_id,item_id,requested_by,estimated_participants,camp_date,camp_time,status,conducted_at,completed_at)
    VALUES (1,1,1,1,3,10,CURDATE(),'09:00','completed',NOW(),NOW());
    INSERT INTO spectacle_camp_events(camp_id,actor_id,action,details,request_token) VALUES (1,2,'conduct-camp','Camp conducted','11111111111111111111111111111111');
    INSERT INTO spectacle_camp_participants(camp_id,full_name,elder_card_number,address,district_id,ds_division_id,gn_division_id,status,spectacle_category_id,recorded_by)
    VALUES (1,'Reading beneficiary','R-1','Fixture',1,1,1,'approved',1,2),
           (1,'Distance beneficiary 1','D-1','Fixture',1,1,1,'approved',2,2),
           (1,'Distance beneficiary 2','D-2','Fixture',1,1,1,'approved',2,2),
           (1,'Distance beneficiary 3','D-3','Fixture',1,1,1,'approved',2,2)");

$_SESSION['user_id']=1; $_SESSION['role']='store-keeper'; $_SESSION['full_name']='Keeper Fixture';
$_SESSION['csrf_token']=bin2hex(random_bytes(32));
$_SERVER['REQUEST_METHOD']='POST';
$_POST=[
    'csrf_token'=>$_SESSION['csrf_token'],'request_token'=>bin2hex(random_bytes(16)),
    'stock_destination'=>'vision-camp','camp_id'=>'1','supplier_id'=>'1','item_id'=>'1',
    'quantity'=>'4','power_sign'=>'+','power_value'=>'','power_entries'=>'[]',
    'spectacle_lines'=>[1=>['selected'=>'1','quantity'=>'1','unit_cost'=>'300.25'],2=>['selected'=>'1','quantity'=>'3','unit_cost'=>'250.00']],
    'power_count'=>'','unit_cost'=>'','bulk_total_cost'=>'',
    'bill_number'=>'CAMP-FIXTURE-1','received_date'=>scToday(),
    'payment_status'=>'partially-paid','check_number'=>'CAMP-CHECK-1','paid_amount'=>'250.25',
];
$requestedPage='receive-items';
register_shutdown_function(static function() use($db):void {
    $receipt=$db->query('SELECT * FROM stock_receipts')->fetch(PDO::FETCH_ASSOC);
    $campReceipt=$db->query('SELECT * FROM spectacle_camp_receipts')->fetch(PDO::FETCH_ASSOC);
    $lines=$db->query('SELECT spectacle_category_id,quantity,unit_cost FROM stock_receipt_spectacle_lines ORDER BY spectacle_category_id')->fetchAll(PDO::FETCH_ASSOC);
    $inventory=(int)$db->query('SELECT quantity FROM inventory_items WHERE id=1')->fetchColumn();
    $remainingNeed=scCampReceiptNeed($db,1);
    $notice=$db->query('SELECT target_url,message FROM user_notifications WHERE user_id=2')->fetch(PDO::FETCH_ASSOC);
    $wrongOfficerNotice=(int)$db->query('SELECT COUNT(*) FROM user_notifications WHERE user_id=3')->fetchColumn();
    $sms=$db->query('SELECT id,message,status FROM notification_sms_outbox WHERE user_id=2')->fetch(PDO::FETCH_ASSOC);
    $okay=$receipt && $campReceipt && (int)$receipt['vision_camp_id']===1
        && $receipt['stock_destination']==='vision-camp' && (int)$receipt['quantity']===4
        && (float)$receipt['total_cost']===1050.25 && (float)$receipt['paid_amount']===250.25
        && (float)$receipt['balance_amount']===800.0
        && (int)$campReceipt['stock_receipt_id']===(int)$receipt['id']
        && (int)$campReceipt['quantity']===4 && $campReceipt['power']===null
        && count($lines)===2 && (int)$lines[0]['quantity']===1 && (int)$lines[1]['quantity']===3
        && $inventory===9 && $remainingNeed===[] && is_array($notice) && $wrongOfficerNotice===0
        && $notice['target_url']==='dashboard.php?page=spectacle-camps#vision-camp-row-1'
        && is_array($sms) && str_contains($sms['message'],'VC-1') && $sms['status']==='queued';
    if (!$okay) { fwrite(STDERR,"Camp supplier receipt integration failed.\n"); exit(1); }
    $sent=widmsProcessNotificationSms($db,10,static function(string $phone,string $message):array {
        if ($phone!=='0772222222' || !str_contains($message,'VC-1')) throw new RuntimeException('Camp SMS targeted the wrong Subject Officer.');
        return ['status'=>'sent','reference'=>'fixture-camp-1'];
    });
    if ($sent['sent']!==1 || $db->query('SELECT status FROM notification_sms_outbox WHERE user_id=2')->fetchColumn()!=='sent') {
        fwrite(STDERR,"Camp supplier receipt SMS dispatch simulation failed.\n"); exit(1);
    }
    echo "Camp supplier receipt passed: payable batch, camp stock, conductor notification and mocked SMS; Central Stock unchanged.\n";
});
ob_start();
require __DIR__.'/../modules/store-keeper/receive-items.php';
