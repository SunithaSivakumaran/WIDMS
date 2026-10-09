<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/spectacle-camps.php';
require_once __DIR__.'/../includes/spectacle-camp-letter.php';
require_once __DIR__.'/../includes/spectacle-camp-participant-export.php';
require_once __DIR__.'/../includes/notification-sms.php';

function campCheck(bool $ok,string $message): void { if(!$ok)throw new RuntimeException($message); }
$printTemplate=file_get_contents(__DIR__.'/../public/vision-camp-participants-export.php');
campCheck($printTemplate!==false && !str_contains($printTemplate,'Spectacles required by type') && !str_contains($printTemplate,'signature-line') && str_contains($printTemplate,'<td class="signature"></td>'),'Printable participants must show only the participant table with blank signature cells.');
$db=database();
// All workflow writes below go to connection-local schema clones, never live tables.
$tables=['users','districts','ds_divisions','gn_divisions','inventory_items','disability_types','disability_aid_items','disability_item_prohibitions','item_returns','user_notifications','notification_sms_outbox','activity_logs',
    'registration_requests','aid_requests','goods_requests','correction_requests','beneficiaries','distributions',
    'spectacle_categories','stock_receipt_spectacle_lines','spectacle_camps','spectacle_camp_participants','spectacle_camp_receipts','spectacle_camp_stock_requests','spectacle_camp_letter_templates','spectacle_camp_transfers','spectacle_camp_distributions','spectacle_camp_events'];
foreach($tables as $table) {
    $db->exec('CREATE TEMPORARY TABLE sc_fixture_'.$table.' LIKE '.$table);
    $db->exec('CREATE TEMPORARY TABLE '.$table.' LIKE sc_fixture_'.$table);
}
foreach([1=>'admin',2=>'subject-officer',3=>'store-keeper',4=>'social-service-officer',5=>'subject-officer',6=>'social-service-officer'] as $id=>$role) {
    scQuery($db,"INSERT INTO users (id,full_name,username,phone,password_hash,role,status,ds_division_id) VALUES (?,? ,?,'0771234567','test-no-password',?,'active',?)",[$id,'Test '.$role,'camp-test-'.$id,$role,$id===4?1:($id===6?2:null)]);
}
$db->exec("INSERT INTO districts (id,name,created_by) VALUES (1,'Test district',1);
 INSERT INTO districts (id,name,created_by) VALUES (2,'Other district',1);
 INSERT INTO ds_divisions (id,district_id,name,created_by) VALUES (1,1,'Camp division',1),(2,1,'Other division',1),(3,2,'Outside division',1);
 INSERT INTO gn_divisions (id,ds_division_id,name,created_by) VALUES (1,1,'Camp GN',1),(2,2,'Other GN',1),(3,3,'Outside GN',1);
 INSERT INTO inventory_items (id,item_name,quantity) VALUES (1,'Spectacles',100),(2,'Contact Lens',200);
 INSERT INTO disability_types (id,name,created_by) VALUES (1,'Vision Impairment',1);
 INSERT INTO disability_aid_items (id,disability_type_id,item_id,is_system,created_by) VALUES (1,1,1,1,1);
 INSERT INTO spectacle_categories (id,name,display_order) VALUES (1,'Reading Only',1),(2,'Distance Only',2)");
$run=static function(int $actor,string $action,array $input=[]) use($db):int {
    return scPerform($db,$actor,$action,$input+['request_token'=>bin2hex(random_bytes(16))]);
};
$deny=static function(callable $call,string $label) use($db):void {
    $before=(int)$db->query('SELECT COUNT(*) FROM spectacle_camp_events')->fetchColumn();
    try {$call();throw new RuntimeException('Unexpected authorization/validation success: '.$label);}
    catch(DomainException|PDOException $expected) {}
    campCheck(!$db->inTransaction(),'Failed action left transaction open.');
    campCheck((int)$db->query('SELECT COUNT(*) FROM spectacle_camp_events')->fetchColumn()===$before,'Failed action wrote an event.');
};
$create=['district_id'=>'1','ds_division_id'=>'1','estimated_participants'=>'10','camp_date'=>scToday(),'camp_time'=>'09:00'];
campCheck(scCampScheduleTime(['camp_hour'=>'12','camp_minute'=>'05','camp_period'=>'AM'])==='00:05'
    && scCampScheduleTime(['camp_hour'=>'12','camp_minute'=>'05','camp_period'=>'PM'])==='12:05'
    && scCampScheduleTime(['camp_hour'=>'01','camp_minute'=>'05','camp_period'=>'PM'])==='13:05','Translated camp time selections must save as 24-hour times.');
campCheck(scSelectedClockTime(['planned_distribution_hour'=>'01','planned_distribution_minute'=>'45','planned_distribution_period'=>'PM'],'planned_distribution','planned_distribution_time')==='13:45','Translated distribution time must save as a 24-hour time.');
$deny(fn()=>scCampScheduleTime(['camp_hour'=>'13','camp_minute'=>'05','camp_period'=>'AM']),'Invalid camp hour');
$deny(fn()=>scCampScheduleTime(['camp_hour'=>'01','camp_minute'=>'60','camp_period'=>'PM']),'Invalid camp minute');
$deny(fn()=>scCampScheduleTime(['camp_hour'=>'01','camp_minute'=>'05','camp_period'=>'night']),'Invalid camp time of day');
$deny(fn()=>$run(1,'create-camp',$create),'Admin creates camp');
$token=bin2hex(random_bytes(16));
$camp=$run(2,'create-camp',$create+['request_token'=>$token]);
campCheck($run(2,'create-camp',$create+['request_token'=>$token])===$camp,'Repeated create not idempotent.');
campCheck((int)$db->query('SELECT COUNT(*) FROM spectacle_camps')->fetchColumn()===1,'Camp duplicated.');
campCheck(scQuery($db,'SELECT target_url FROM user_notifications WHERE user_id=1 AND notification_key=?',['sc-'.$camp.'-requested'])->fetchColumn()==='dashboard.php?page=pending-approvals&tab=vision-camps#vision-camp-request-'.$camp,'Admin notification does not link to the approval card.');
$base=['camp_id'=>$camp];
campCheck((int)scCamp($db,$camp,scActor($db,5))['id']===$camp,'Another Subject Officer cannot read camp history.');
$deny(fn()=>scCamp($db,$camp,scActor($db,6)),'Other division SSO reads camp');
$deny(fn()=>$run(2,'conduct-camp',$base),'Conduct before approval');
$deny(fn()=>$run(1,'decide-camp',$base+['decision'=>'rejected']),'Camp rejection without reason');
$deny(fn()=>$run(5,'conduct-camp',$base),'Other Subject Officer');
$run(1,'decide-camp',$base+['decision'=>'approved']);
$deny(fn()=>$run(1,'conduct-camp',$base),'Admin conducts Subject Officer camp');
$deny(fn()=>scCamp($db,$camp,scActor($db,3)),'Store Keeper views camp after approval but before it is conducted');
campCheck((int)$db->query('SELECT COUNT(*) FROM user_notifications WHERE user_id=3')->fetchColumn()===0,'Camp approval notified Store Keeper before stock workflow.');
campCheck((int)scQuery($db,"SELECT COUNT(*) FROM user_notifications WHERE notification_key=? AND user_id=2",['sc-'.$camp.'-decision'])->fetchColumn()===1,'Requester decision notification missing.');
campCheck(scQuery($db,'SELECT target_url FROM user_notifications WHERE user_id=? AND notification_key=?',[2,'sc-'.$camp.'-decision'])->fetchColumn()==='dashboard.php?page=my-spectacle-camps#vision-camp-row-'.$camp,'Requester camp notification does not link to its My Camps row.');
campCheck((int)$db->query('SELECT COUNT(*) FROM user_notifications WHERE user_id=5')->fetchColumn()===0,'Another Subject Officer received camp notifications.');
$run(5,'conduct-camp',$base);
$deny(fn()=>$run(2,'conduct-camp',$base),'Conduct the same camp twice');
campCheck((int)scCamp($db,$camp,scActor($db,3))['id']===$camp,'Store Keeper cannot access conducted camp for stock receiving.');
$participant=$base+['full_name'=>'Test participant','elder_card_number'=>'ELDER-1','address'=>'Test address','district_id'=>'1','ds_division_id'=>'1','gn_division_id'=>'1'];
$deny(fn()=>$run(5,'request-stock-batch',$base+['planned_distribution_date'=>scToday()]),'Stock batch requires an approved participant and stock');
$deny(fn()=>$run(2,'save-participant',$participant),'Non-conducting Subject Officer registers a participant');
$deny(fn()=>$run(5,'save-participant',array_replace($participant,['elder_card_number'=>''])),'Both identifiers missing');
$deny(fn()=>$run(5,'save-participant',array_replace($participant,['gn_division_id'=>'2'])),'Wrong GN');
$run(5,'save-participant',$participant); $p1=(int)$db->query('SELECT MAX(id) FROM spectacle_camp_participants')->fetchColumn();
$deny(fn()=>$run(5,'save-participant',$participant),'Duplicate elder card');
$run(5,'save-participant',array_replace($participant,['elder_card_number'=>'ELDER-2'])); $p2=(int)$db->query('SELECT MAX(id) FROM spectacle_camp_participants')->fetchColumn();
$run(5,'save-participant',array_replace($participant,['elder_card_number'=>'ELDER-3'])); $p3=(int)$db->query('SELECT MAX(id) FROM spectacle_camp_participants')->fetchColumn();
$deny(fn()=>$run(2,'decide-participant',$base+['participant_id'=>$p3,'decision'=>'rejected','reason'=>'Not allowed']),'Non-conducting Subject Officer reviews a participant');
$deny(fn()=>$run(5,'decide-participant',$base+['participant_id'=>$p3,'decision'=>'rejected']),'Missing participant rejection reason');
$run(5,'decide-participant',$base+['participant_id'=>$p3,'decision'=>'rejected','reason'=>'Spectacles not needed']);
foreach([$p1,$p2] as $p) $run(5,'decide-participant',$base+['participant_id'=>$p,'decision'=>'approved','spectacle_category_id'=>'1','prescription_details'=>'Recorded prescription']);
$deny(fn()=>$run(3,'receive-stock',$base+['spectacle_category_id'=>'1','quantity'=>'2','reference_code'=>'TOO-EARLY','received_date'=>scToday()]),'Receipt before camp completion');
$deny(fn()=>scParticipantExportData($db,$camp,scActor($db,5)),'Export before camp completion');
$deny(fn()=>$run(2,'complete-camp',$base),'Non-conducting officer completes camp');
$run(5,'complete-camp',$base);
$exportData=scParticipantExportData($db,$camp,scActor($db,2));
campCheck(count($exportData['participants'])===3 && array_sum(array_column($exportData['totals'],'quantity'))===2,'Camp export totals must count selected participants only.');
campCheck(($exportData['totals'][0]['type']??'')==='Reading Only' && ($exportData['totals'][0]['quantity']??0)===2,'Camp export has the wrong spectacle-type quantity.');
$deny(fn()=>scParticipantExportData($db,$camp,scActor($db,3)),'Store Keeper exports participant identities');
$workbookPath=scParticipantWorkbook($exportData);
try {
    $workbook=new ZipArchive();
    campCheck($workbook->open($workbookPath)===true,'Excel workbook cannot be opened.');
    campCheck(str_contains((string)$workbook->getFromName('xl/workbook.xml'),'Spectacle Totals') && str_contains((string)$workbook->getFromName('xl/workbook.xml'),'Participants'),'Excel workbook sheets are missing.');
    campCheck(str_contains((string)$workbook->getFromName('xl/worksheets/sheet1.xml'),'Reading Only') && str_contains((string)$workbook->getFromName('xl/worksheets/sheet1.xml'),'<v>2</v>'),'Excel spectacle-type totals are missing.');
    campCheck(str_contains((string)$workbook->getFromName('xl/worksheets/sheet2.xml'),'Signature') && str_contains((string)$workbook->getFromName('xl/worksheets/sheet2.xml'),'SCP-'.$p1),'Excel participant and signature columns are missing.');
    foreach (['[Content_Types].xml','_rels/.rels','xl/workbook.xml','xl/_rels/workbook.xml.rels','xl/worksheets/sheet1.xml','xl/worksheets/sheet2.xml'] as $part) {
        $document=new DOMDocument();
        campCheck($document->loadXML((string)$workbook->getFromName($part)),'Invalid XML in Excel workbook: '.$part);
    }
    $workbook->close();
} finally { unlink($workbookPath); }
campCheck(scQuery($db,'SELECT target_url FROM user_notifications WHERE user_id=? AND notification_key=?',[3,'sc-'.$camp.'-completed'])->fetchColumn()==='dashboard.php?page=receive-items&camp_id='.$camp,'Camp completion did not send Store Keeper to receipt entry.');
$deny(fn()=>$run(5,'save-participant',array_replace($participant,['elder_card_number'=>'AFTER-COMPLETE'])),'Register after camp completion');
$distribution=$base+['participant_id'=>$p1,'distribution_date'=>scToday()];
$deny(fn()=>$run(5,'decide-participant',$base+['participant_id'=>$p1,'decision'=>'rejected','reason'=>'Not allowed']),'Already reviewed participant cannot be changed');
$deny(fn()=>$run(5,'distribute',$distribution),'Distribution before stock release');
$deny(fn()=>$run(5,'transfer-stock',$base+['power'=>'2','quantity'=>'1','sso_id'=>'4','transfer_date'=>scToday()]),'Transfer before stock release');
$deny(fn()=>$run(2,'distribute',$distribution),'Distribution before stock release');
$deny(fn()=>$run(2,'receive-stock',$base+['power'=>'2','quantity'=>'2','reference_code'=>'R1','received_date'=>scToday()]),'Subject receives store stock');
$run(3,'receive-stock',$base+['spectacle_category_id'=>'1','quantity'=>'2','reference_code'=>'R1','received_date'=>scToday()]);
$campReceiptId=(int)$db->query('SELECT MAX(id) FROM spectacle_camp_receipts')->fetchColumn();
campCheck(scQuery($db,'SELECT target_url FROM user_notifications WHERE user_id=? AND notification_key=?',[5,'sc-'.$camp.'-received-'.$campReceiptId])->fetchColumn()==='dashboard.php?page=spectacle-camps#vision-camp-row-'.$camp,'Receipt notification does not link the non-owning conductor to the All Vision Camps row.');
campCheck(!scQuery($db,'SELECT id FROM user_notifications WHERE user_id=? AND notification_key=?',[2,'sc-'.$camp.'-received-'.$campReceiptId])->fetchColumn(),'Receipt notified the requester instead of the conductor.');
$deny(fn()=>$run(3,'receive-stock',$base+['spectacle_category_id'=>'1','quantity'=>'2','reference_code'=>'R2','received_date'=>scToday()]),'Duplicate camp supplier quantity');
campCheck(scCampReceiptNeed($db,$camp)===[],'Completed camp still asks Store Keeper for already received stock.');
$deny(fn()=>$run(2,'request-stock-batch',$base+['planned_distribution_date'=>scToday()]),'Non-conducting officer requests camp stock');
$deny(fn()=>$run(5,'request-stock',$base+['spectacle_category_id'=>'1','quantity'=>'2','planned_distribution_date'=>scToday()]),'Retired single-type request endpoint');
$deny(fn()=>$run(5,'request-stock-batch',$base+['planned_distribution_date'=>scToday(),'planned_distribution_time'=>scNowTime()]),'Camp stock batch without distribution place');
$deny(fn()=>$run(5,'request-stock-batch',$base+['planned_distribution_date'=>scToday(),'planned_distribution_place'=>'Camp community hall']),'Camp stock batch without distribution time');
$run(5,'request-stock-batch',$base+['planned_distribution_date'=>scToday(),'planned_distribution_time'=>scNowTime(),'planned_distribution_place'=>'Camp community hall']);
campCheck(scQuery($db,'SELECT planned_distribution_place FROM spectacle_camp_stock_requests WHERE camp_id=? ORDER BY id DESC LIMIT 1',[$camp])->fetchColumn()==='Camp community hall','Camp batch venue was not saved.');
campCheck(scCampRequestNeed($db,$camp)===[],'Camp stock already requested remains available to request again.');
$deny(fn()=>$run(5,'request-stock-batch',$base+['planned_distribution_date'=>scToday()]),'Duplicate camp stock batch');
$request=(int)$db->query('SELECT MAX(id) FROM spectacle_camp_stock_requests')->fetchColumn();
$batchRef=(string)scQuery($db,'SELECT batch_ref FROM spectacle_camp_stock_requests WHERE id=?',[$request])->fetchColumn();
campCheck(scQuery($db,'SELECT target_url FROM user_notifications WHERE user_id=1 AND notification_key=?',['sc-'.$camp.'-stock-request-batch-'.$batchRef])->fetchColumn()==='dashboard.php?page=spectacle-camp-stock-approvals','Admin stock-batch notification does not link to the approval queue.');
$stock=$base+['batch_ref'=>$batchRef];
$deny(fn()=>$run(3,'release-stock-batch',$stock),'Release before Admin approval');
$deny(fn()=>$run(1,'decide-stock-batch',$stock+['decision'=>'invalid']),'Invalid batch decision');
$run(1,'decide-stock-batch',$stock+['decision'=>'approved']);
campCheck((int)scQuery($db,'SELECT COUNT(*) FROM user_notifications WHERE user_id=? AND notification_key=?',[5,'sc-'.$camp.'-stock-decision-batch-'.$batchRef])->fetchColumn()===1,'Stock batch decision did not notify the Subject Officer who submitted it.');
$run(3,'release-stock-batch',$stock);
campCheck((int)scQuery($db,'SELECT COUNT(*) FROM user_notifications WHERE user_id=? AND notification_key=?',[5,'sc-'.$camp.'-released-batch-'.$batchRef])->fetchColumn()===1,'Stock batch release did not notify the Subject Officer who submitted it.');
campCheck(scQuery($db,'SELECT target_url FROM user_notifications WHERE user_id=? AND notification_key=?',[5,'sc-'.$camp.'-released-batch-'.$batchRef])->fetchColumn()==='dashboard.php?page=spectacle-camps#vision-camp-row-'.$camp,'Stock release notification opens camp overview instead of the related row.');
$deny(fn()=>$run(3,'release-stock-batch',$stock),'Duplicate batch release');
$deny(fn()=>$run(6,'distribute',$distribution),'Other division SSO');
$deny(fn()=>$run(2,'distribute',array_replace($distribution,['participant_id'=>$p3])),'Rejected participant distribution');
$run(5,'distribute',$distribution);
$deny(fn()=>$run(2,'distribute',$distribution),'Duplicate distribution');
$transfer=$base+['spectacle_category_id'=>'1','quantity'=>'1','sso_id'=>'4','transfer_date'=>scToday()];
$deny(fn()=>$run(2,'transfer-stock',array_replace($transfer,['sso_id'=>'6'])),'Transfer to another division');
$deny(fn()=>$run(2,'transfer-stock',array_replace($transfer,['quantity'=>'2'])),'Transfer more than remaining');
$run(5,'transfer-stock',$transfer);
campCheck(scQuery($db,'SELECT target_url FROM user_notifications WHERE user_id=? AND notification_key LIKE ? ORDER BY id DESC LIMIT 1',[4,'sc-'.$camp.'-transfer-%'])->fetchColumn()==='dashboard.php?page=spectacle-camps#vision-camp-row-'.$camp,'SSO camp transfer notification opens overview instead of its camp row.');
$run(4,'distribute',array_replace($distribution,['participant_id'=>$p2]));
$balance=scBalances($db,$camp,4)[1];
campCheck($balance['received']===2 && $balance['subject']===0 && $balance['sso']===0 && $balance['store']===0,'Stock ledger does not balance.');
campCheck((int)$db->query('SELECT COUNT(*) FROM spectacle_camp_participants')->fetchColumn()===3,'Rejected participant lost.');
campCheck((int)$db->query("SELECT quantity FROM inventory_items WHERE item_name='Spectacles'")->fetchColumn()===100,'Ordinary stock was changed.');
campCheck((int)$db->query("SELECT quantity FROM inventory_items WHERE item_name='Contact Lens'")->fetchColumn()===200,'Contact-lens stock was changed.');
campCheck((int)$db->query('SELECT COUNT(*) FROM user_notifications WHERE user_id=6')->fetchColumn()===0,'Unrelated officers notified.');
campCheck((int)$db->query('SELECT COUNT(*) FROM user_notifications')->fetchColumn()===(int)$db->query('SELECT COUNT(*) FROM notification_sms_outbox')->fetchColumn(),'A system event lacks its SMS.');
$sent=0;
$summary=widmsProcessNotificationSms($db,100,static function(string $phone,string $message)use(&$sent):array{
    campCheck($phone==='0771234567' && str_contains($message,'SWPCS VC-'),'Incorrect camp SMS.');
    campCheck(!str_contains($message,'Test participant'),'Participant identity leaked in SMS.');
    $sent++;return ['status'=>'sent','reference'=>'fixture-'.$sent];
});
campCheck($summary['sent']>0,'Camp SMS worker did not send the mocked messages.');
campCheck(widmsProcessNotificationSms($db,100,static function(){throw new RuntimeException('Duplicate send');})['sent']===0,'SMS sent twice.');

// Combined registration/decision, and repeat-issue periods (fixture tables only).
$combinedCamp=$run(2,'create-camp',$create);
$run(1,'decide-camp',['camp_id'=>$combinedCamp,'decision'=>'approved']);
$run(2,'conduct-camp',['camp_id'=>$combinedCamp]);
$combined=array_replace($participant,['camp_id'=>$combinedCamp,'elder_card_number'=>'COMBINED-1','gender'=>'female','decision'=>'approved','spectacle_category_id'=>'1']);
$beforeParticipants=(int)$db->query('SELECT COUNT(*) FROM spectacle_camp_participants')->fetchColumn();
$deny(fn()=>$run(2,'save-participant',array_replace($combined,['decision'=>'rejected','reason'=>'   '])),'Combined rejection without reason');
$deny(fn()=>$run(2,'save-participant',array_replace($combined,['gender'=>'invalid'])),'Invalid gender');
campCheck((int)$db->query('SELECT COUNT(*) FROM spectacle_camp_participants')->fetchColumn()===$beforeParticipants,'Failed combined save left a partial participant.');
$run(2,'save-participant',$combined);
$saved=scQuery($db,'SELECT * FROM spectacle_camp_participants WHERE camp_id=? AND elder_card_number=?',[$combinedCamp,'COMBINED-1'])->fetch();
campCheck($saved['status']==='approved' && $saved['gender']==='female' && $saved['decided_at']!==null,'Combined selected decision was not saved.');
campCheck($saved['power']===null,'Participant registration must not require or invent power.');
$deny(fn()=>$run(2,'save-participant',$combined),'Same-camp duplicate selected participant');
$anotherCamp=$run(2,'create-camp',$create);
$run(1,'decide-camp',['camp_id'=>$anotherCamp,'decision'=>'approved']);
$run(2,'conduct-camp',['camp_id'=>$anotherCamp]);
$deny(fn()=>$run(2,'save-participant',array_replace($combined,['camp_id'=>$anotherCamp])),'Selected participant registered in another camp before distribution');
scQuery($db,"INSERT INTO beneficiaries (id,district_id,ds_division_id,gn_division_id,full_name,nic,elders_card_number,date_of_birth,gender,address,disability,approved_by,approved_at) VALUES (902,2,3,3,'Outside beneficiary','900000003V','OUTSIDE-ELDER','1990-01-01','female','Outside address','Vision Impairment',1,NOW())");
try {
    $run(2,'save-participant',array_replace($combined,['camp_id'=>$anotherCamp,'nic'=>'900000003V','elder_card_number'=>'']));
    throw new RuntimeException('A beneficiary from another division was registered in the camp.');
} catch (RuntimeException $exception) {
    campCheck(str_contains($exception->getMessage(),'Other district') && str_contains($exception->getMessage(),'Outside division'),'Cross-division camp error must name the recorded District and DS Division.');
}
try {
    assertBeneficiaryIdentityDivision($db,'','OUTSIDE-ELDER',1);
    throw new RuntimeException('An Elder Card from another division was accepted.');
} catch (RuntimeException $exception) {
    campCheck(str_contains($exception->getMessage(),'Other district') && str_contains($exception->getMessage(),'Outside division'),'Cross-division Elder Card error must name the recorded location.');
}
scQuery($db,"INSERT INTO spectacle_camp_participants (camp_id,full_name,elder_card_number,address,district_id,ds_division_id,gn_division_id,status,rejection_reason,recorded_by) VALUES (?,'Historic conflicting participant','OUTSIDE-ELDER','Camp address',1,1,1,'rejected','Historic record',2)",[$anotherCamp]);
try {
    assertBeneficiaryRecordDivision($db,902);
    throw new RuntimeException('An existing cross-division record was not caught before distribution.');
} catch (RuntimeException $exception) {
    campCheck(str_contains($exception->getMessage(),'Test district') && str_contains($exception->getMessage(),'Camp division'),'Distribution check must report the conflicting recorded location.');
}
scQuery($db,"INSERT INTO beneficiaries (id,district_id,ds_division_id,gn_division_id,full_name,date_of_birth,gender,address,disability,approved_by,approved_at) VALUES (903,2,3,3,'Unidentified beneficiary','1990-01-01','female','Outside address','Vision Impairment',1,NOW())");
try {
    assertBeneficiaryRecordDivision($db,903,1);
    throw new RuntimeException('A direct request edit moved an unidentified beneficiary to another division.');
} catch (RuntimeException $exception) {
    campCheck(str_contains($exception->getMessage(),'Other district') && str_contains($exception->getMessage(),'Outside division'),'Editing an existing beneficiary without identification must preserve its recorded division.');
}
$outsideCamp=$run(2,'create-camp',array_replace($create,['district_id'=>'2','ds_division_id'=>'3']));
$run(1,'decide-camp',['camp_id'=>$outsideCamp,'decision'=>'approved']);
$run(2,'conduct-camp',['camp_id'=>$outsideCamp]);
$run(2,'save-participant',array_replace($combined,['camp_id'=>$outsideCamp,'district_id'=>'2','ds_division_id'=>'3','gn_division_id'=>'3','nic'=>'','elder_card_number'=>'OUTSIDE-CAMP']));
try {
    $run(2,'save-participant',array_replace($combined,['camp_id'=>$anotherCamp,'nic'=>'','elder_card_number'=>'OUTSIDE-CAMP']));
    throw new RuntimeException('A participant from another camp division was registered.');
} catch (RuntimeException $exception) {
    campCheck(str_contains($exception->getMessage(),'Other district') && str_contains($exception->getMessage(),'Outside division'),'Cross-division camp participant error must name the recorded location.');
}
$emptyCamp=$run(2,'create-camp',$create);
$run(1,'decide-camp',['camp_id'=>$emptyCamp,'decision'=>'approved']);
$run(2,'conduct-camp',['camp_id'=>$emptyCamp]);
$run(2,'complete-camp',['camp_id'=>$emptyCamp]);
campCheck(scQuery($db,'SELECT status FROM spectacle_camps WHERE id=?',[$emptyCamp])->fetchColumn()==='completed','A conducted camp with no undecided participants cannot be completed.');
$db->exec("INSERT INTO beneficiaries(id,district_id,ds_division_id,gn_division_id,full_name,nic,date_of_birth,gender,address,disability,approved_by,approved_at) VALUES (901,1,1,1,'Aid request fixture','900000002V','1990-01-01','female','Fixture address','Vision Impairment',1,NOW());
 INSERT INTO aid_requests(beneficiary_id,item_id,quantity,disability_notes,submitted_by) VALUES (901,2,1,'Test',2)");
$withOrdinaryAid=array_replace($combined,['camp_id'=>$anotherCamp,'nic'=>'900000002V','elder_card_number'=>'']);
$deny(fn()=>$run(2,'save-participant',$withOrdinaryAid),'Active ordinary aid request bypass');
$db->exec("UPDATE aid_requests SET status='rejected' WHERE beneficiary_id=901;
 INSERT INTO disability_aid_items(id,disability_type_id,item_id,restriction_months,is_system,created_by) VALUES (2,1,2,12,0,1);
 INSERT INTO disability_item_prohibitions(disability_aid_item_id,prohibited_item_id) VALUES (2,1);
 INSERT INTO distributions(beneficiary_id,item_id,quantity,distribution_type,distributed_by,distributed_at) VALUES (901,2,1,'direct',2,NOW())");
$deny(fn()=>$run(2,'save-participant',$withOrdinaryAid),'Prohibited related item waiting period bypass');
$distributionId=(int)$db->query('SELECT MAX(id) FROM distributions')->fetchColumn();
scQuery($db,"INSERT INTO item_returns(distribution_id,quantity,item_condition,reusable,restore_to,processed_by,stock_review_status) VALUES (?,1,'good',1,'officer-pool',2,'accepted')",[$distributionId]);
$run(2,'save-participant',$withOrdinaryAid);
$deny(fn()=>$run(2,'save-participant',$withOrdinaryAid),'Same-camp duplicate NIC');
$run(2,'save-participant',array_replace($combined,['elder_card_number'=>'COMBINED-2','decision'=>'rejected','reason'=>'Prescription not required']));
campCheck(scQuery($db,'SELECT rejection_reason FROM spectacle_camp_participants WHERE camp_id=? AND elder_card_number=?',[$combinedCamp,'COMBINED-2'])->fetchColumn()==='Prescription not required','Combined rejection reason missing.');
$legacy=array_replace($participant,['camp_id'=>$combinedCamp,'elder_card_number'=>'COMBINED-3']);
$run(2,'save-participant',$legacy);
$legacyId=(int)scQuery($db,'SELECT id FROM spectacle_camp_participants WHERE camp_id=? AND elder_card_number=?',[$combinedCamp,'COMBINED-3'])->fetchColumn();
$beforeParticipants=(int)$db->query('SELECT COUNT(*) FROM spectacle_camp_participants')->fetchColumn();
$run(2,'save-participant',array_replace($combined,['participant_id'=>$legacyId,'elder_card_number'=>'COMBINED-3']));
campCheck((int)$db->query('SELECT COUNT(*) FROM spectacle_camp_participants')->fetchColumn()===$beforeParticipants,'Existing participant was duplicated.');
$db->exec('UPDATE disability_aid_items SET restriction_months=12 WHERE item_id=1');
$waiting=scSpectacleWaitingPeriod($db,1,'','ELDER-1');
campCheck(!$waiting['eligible'] && $waiting['last_received']===scToday(),'Earlier camp distribution not checked.');
$deny(fn()=>$run(2,'save-participant',array_replace($combined,['elder_card_number'=>'ELDER-1'])),'Reissue period bypass at selection');
$deny(fn()=>$run(2,'save-participant',array_replace($combined,['elder_card_number'=>'ELDER-1','decision'=>'rejected','reason'=>'Not selected'])),'Reissue period bypass at registration');
campCheck(scSpectacleWaitingPeriod($db,1,'','ELDER-1',$waiting['eligible_on'])['eligible'],'Eligibility must resume on expiry date.');
$run(2,'complete-camp',['camp_id'=>$combinedCamp]);
$db->exec("INSERT INTO beneficiaries(id,district_id,ds_division_id,gn_division_id,full_name,nic,elders_card_number,date_of_birth,gender,address,disability,approved_by,approved_at) VALUES (900,1,1,1,'Prior spectacle fixture','900000001V','OLD-900','1990-01-01','female','Fixture address','Vision Impairment',1,NOW());
INSERT INTO distributions(beneficiary_id,item_id,quantity,distribution_type,distributed_by,distributed_at) VALUES (900,1,1,'direct',1,NOW())");
campCheck(!scSpectacleWaitingPeriod($db,1,'900000001V','')['eligible'],'Ordinary distribution NIC history ignored.');
campCheck(!scSpectacleWaitingPeriod($db,1,'','OLD-900')['eligible'],'Ordinary distribution Elder Card history ignored.');
$db->exec('UPDATE disability_aid_items SET restriction_months=0 WHERE item_id=1');
campCheck(scSpectacleWaitingPeriod($db,1,'900000001V','')['eligible'],'Zero configured period should not invent a restriction.');

// Optional isolated page rendering against the fixture database.
if (in_array($argv[1]??'', ['--render','--preview'],true)) {
    require_once __DIR__.'/../includes/auth.php';
    require_once __DIR__.'/../includes/permissions.php';
    require_once __DIR__.'/../includes/ui-messages.php';
    set_error_handler(static function(int $severity,string $message,string $file,int $line):never { throw new ErrorException($message,0,$severity,$file,$line); });
    $render=static function():string { ob_start(); require __DIR__.'/../modules/shared/spectacle-camps.php'; return (string)ob_get_clean(); };
    $enabledIssues=static function(string $html):int {
        $previous=libxml_use_internal_errors(true);
        $doc=new DOMDocument(); $doc->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors(); libxml_use_internal_errors($previous);
        return (new DOMXPath($doc))->query('//form[@class="sc-distribute-row"]//button[not(@disabled)]')->length;
    };
    $campListRow=static function(string $html,int $id):string {
        $previous=libxml_use_internal_errors(true);
        $doc=new DOMDocument(); $doc->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors(); libxml_use_internal_errors($previous);
        $node=(new DOMXPath($doc))->query('//tr[@data-sc-row][td[contains(@class,"sc-camp-location")]/strong[normalize-space()="VC-'.$id.'"]]')->item(0);
        return $node ? (string)$doc->saveHTML($node) : '';
    };
    $_SESSION['user_id']=2; $_SESSION['role']='subject-officer'; $_SESSION['full_name']='Camp Fixture';
    $_GET=['page'=>'my-spectacle-camps','camp_id'=>$combinedCamp,'tab'=>'distribution']; $_POST=[]; $_SERVER['REQUEST_METHOD']='GET';
    $html=$render();
    campCheck($enabledIssues($html)===0 && str_contains($html,'Awaiting stock'),'Distribution enabled before stock receipt.');
    campCheck(scCampTimeLabel('15:07:49')==='3:07 PM' && scCampTimeLabel('2026-09-25 08:00:00')==='8:00 AM','Camp times must use AM/PM without seconds.');
campCheck(scCampDateLabel('2026-09-25 15:07:49')==='25 Sep 2026','Camp date formatting is incorrect.');
    campCheck(scClockTime(['distribution_time'=>'09:30'],'distribution_time')==='09:30','Distribution time validation rejected a valid time.');
    $run(3,'receive-stock',['camp_id'=>$combinedCamp,'spectacle_category_id'=>'1','quantity'=>2,'reference_code'=>'QUANTITY-ONLY','received_date'=>scToday()]);
    // Simulate a historical power-tagged receipt in the isolated clone; it must still count.
    scQuery($db,'UPDATE spectacle_camp_receipts SET power=3.50 WHERE camp_id=?',[$combinedCamp]);
    campCheck(scBalances($db,$combinedCamp)[1]['received']===2,'Historical stock was excluded from the category pool.');
    campCheck($enabledIssues($render())===0,'Store receipt incorrectly enables Subject Officer distribution.');
    $_GET['tab']='stock';
    $html=$render();
    campCheck(str_contains($html,'page=vision-camp-stock-requests&amp;camp_id='.$combinedCamp),'Camp Stock tab does not link to the dedicated request page.');
    $_GET=['page'=>'vision-camp-stock-requests','camp_id'=>$combinedCamp];
    $renderStockPage=static function():string { ob_start(); require __DIR__.'/../modules/subject-officer/vision-camp-stock-requests.php'; return (string)ob_get_clean(); };
    $html=$renderStockPage();
    campCheck(str_contains($html,'class="camp-request-form"') && str_contains($html,'name="action" value="request-stock-batch"') && str_contains($html,'name="camp_id" value="'.$combinedCamp.'"') && str_contains($html,'name="planned_distribution_hour"') && str_contains($html,'name="planned_distribution_minute"') && str_contains($html,'name="planned_distribution_period"') && str_contains($html,'name="planned_distribution_place"'),'Conducting officer lacks a dated, timed and located camp-level stock request after receipt.');
    campCheck(!str_contains($html,'camp-request-count') && !str_contains($html,'camp-request-note') && !str_contains($html,'camp-request-empty') && !str_contains($html,'View My Camps'),'Camp stock request cards should not show the extra summary, warning, or history links.');
    $_GET=['page'=>'my-spectacle-camps','camp_id'=>$combinedCamp,'tab'=>'distribution'];
    $_GET['tab']='distribution';
    $plannedTime=scNowTime();
    $run(2,'request-stock-batch',['camp_id'=>$combinedCamp,'planned_distribution_date'=>scToday(),'planned_distribution_time'=>$plannedTime,'planned_distribution_place'=>'Combined camp hall']);
    campCheck(substr((string)scQuery($db,'SELECT planned_distribution_time FROM spectacle_camp_stock_requests WHERE camp_id=? ORDER BY id DESC LIMIT 1',[$combinedCamp])->fetchColumn(),0,5)===$plannedTime,'Planned distribution time was not saved with the camp batch.');
    $html=$renderStockPage();
    campCheck(!str_contains($html,'id="camp-'.$combinedCamp.'"') && !str_contains($html,'class="camp-request-form"') && !str_contains($html,'class="camp-request-section"'),'Submitted camp request remains as a ready-to-submit card or leaves an empty panel.');
    $_GET=['page'=>'my-spectacle-camps'];
    $html=$render();
    campCheck(str_contains($html,'data-sc-stock-status="pending"') && str_contains($html,'sc-stock-status-pending'),'My Camps does not show the submitted stock request status.');
    $_GET=['page'=>'spectacle-camp-participants','camp_id'=>$combinedCamp];
    campCheck(!str_contains($render(),'sc-letter-print-link') && scLetterRecipients($db,$combinedCamp)===[],'A5 beneficiary letters appeared before Admin stock approval.');
    $batchRef=(string)scQuery($db,'SELECT batch_ref FROM spectacle_camp_stock_requests WHERE camp_id=? ORDER BY id DESC LIMIT 1',[$combinedCamp])->fetchColumn();
    campCheck(strlen($batchRef)===32,'Camp request did not create a batch reference.');
    $_SESSION['user_id']=1; $_SESSION['role']='admin'; $_GET=['page'=>'spectacle-camp-stock-approvals'];
    $adminStockCards=$render();
    campCheck(str_contains($adminStockCards,'Planned Distribution') && str_contains($adminStockCards,scCampTimeLabel($plannedTime)) && str_contains($adminStockCards,'Combined camp hall'),'Admin stock approval card does not show the requested distribution time and place.');
    $run(1,'decide-stock-batch',['camp_id'=>$combinedCamp,'batch_ref'=>$batchRef,'decision'=>'approved']);
    $_SESSION['user_id']=2; $_SESSION['role']='subject-officer';
    $_GET=['page'=>'my-spectacle-camps'];
    $html=$render();
    $requestRow=$campListRow($html,$combinedCamp);
    campCheck(str_contains($requestRow,'data-sc-stock-status="approved"') && str_contains($requestRow,'sc-stock-status-approved') && !str_contains($requestRow,'sc-action-distribute'),'Distribution action appeared before Store Keeper release.');
    $_GET=['page'=>'spectacle-camp-participants','camp_id'=>$combinedCamp];
    $approvedParticipants=$render();
    campCheck(str_contains($approvedParticipants,'class="outline-action sc-letter-edit-link"') && str_contains($approvedParticipants,'class="outline-action sc-letter-print-link"') && !str_contains($approvedParticipants,'sc-participant-notice-link'),'Approved stock must show one common A5 letter editor and print action above the table, not per-row print links.');
    $_GET=['camp_id'=>$combinedCamp];
    $renderLetter=static function():string { ob_start(); require __DIR__.'/../public/vision-camp-a5-letter.php'; return (string)ob_get_clean(); };
    $noticeHtml=$renderLetter();
    $letterRecipients=scLetterRecipients($db,$combinedCamp);
    campCheck(count($letterRecipients)===2 && substr_count($noticeHtml,'<article class="a5-sheet"')===2 && str_contains($noticeHtml,'size:A5 portrait'),'Printing must create one A5 page for each eligible beneficiary.');
    foreach ($letterRecipients as $recipient) {
        $number=(int)$recipient['registration_number'];
        campCheck(str_contains($noticeHtml,'<strong>'.$number.'. '.htmlspecialchars($recipient['full_name'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</strong>') && str_contains($noticeHtml,htmlspecialchars($recipient['address'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')),'A5 letter omitted a registration-order number, name, or postal address.');
    }
    campCheck(str_contains($noticeHtml,'class="postal-address"') && str_contains($noticeHtml,'margin-top:auto'),'Recipient postal addresses are not at the bottom-left of each A5 page.');
    campCheck(str_contains($noticeHtml,'class="letterhead-marks"') && str_contains($noticeHtml,'class="tamil"') && str_contains($noticeHtml,'මගේ අංකය') && str_contains($noticeHtml,'ඔබේ අංකය') && str_contains($noticeHtml,'පරිපාලන නිලධාරී'),'A5 letter no longer follows the supplied three-language letter format.');
    $plannedHour=(int)substr($plannedTime,0,2);
    $sinhalaTime=($plannedHour<12?'පෙ.ව. ':'ප.ව. ').(($plannedHour%12)?:12).'.'.substr($plannedTime,3,2);
    campCheck(str_contains($noticeHtml,(new DateTimeImmutable(scToday()))->format('Y.m.d')) && str_contains($noticeHtml,$sinhalaTime) && str_contains($noticeHtml,'Combined camp hall'),'A5 letter lacks its saved distribution date, time, or place.');
    $_GET=['camp_id'=>$combinedCamp,'edit'=>'1'];
    $editorHtml=$renderLetter();
    campCheck(str_contains($editorHtml,'Save common letter') && str_contains($editorHtml,'fields[schedule]') && str_contains($editorHtml,'{{date}}'),'The shared letter editor is missing.');
    $custom=scLetterDefaults(); $custom['subject']='සංස්කරණය කළ ලිපිය.';
    $deny(fn()=>scLetterValidate(array_replace($custom,['schedule'=>'Distribution date only'])),'Letter without automatic schedule placeholders');
    scSaveLetterTemplate($db,$combinedCamp,2,$custom);
    $_GET=['camp_id'=>$combinedCamp];
    campCheck(str_contains($renderLetter(),'සංස්කරණය කළ ලිපිය.'),'Saved common wording was not used for all A5 letters.');
    $_GET=['page'=>'my-spectacle-camps'];
    campCheck(scQuery($db,'SELECT target_url FROM user_notifications WHERE user_id=? AND notification_key=?',[3,'sc-'.$combinedCamp.'-release-needed-batch-'.$batchRef])->fetchColumn()==='dashboard.php?page=spectacle-camp-releases','Store Keeper batch release notification points to the wrong page.');
    $_SESSION['user_id']=3; $_SESSION['role']='store-keeper'; $_GET=['page'=>'spectacle-camp-releases','camp_id'=>$combinedCamp];
    $html=$render();
    campCheck(str_contains($html,'class="sc-approval-card sc-release-card"') && str_contains($html,'data-sc-approval-search') && str_contains($html,'Release camp stock batch') && str_contains($html,'value="release-stock-batch"') && !str_contains($html,'class="aid-request-list-table sc-table"'),'Store Keeper must see a searchable release card for the approved camp batch.');
    campCheck(str_contains($html,scCampTimeLabel($plannedTime)) && str_contains($html,'Combined camp hall'),'Store Keeper release card does not show the planned distribution time and place.');
    campCheck(!str_contains($html,'href="dashboard.php?page=spectacle-camps&amp;camp_id='),'Store Keeper release queue still links to camp details.');
    campCheck(!str_contains($html,'class="sc-camp-navigation"'),'Store Keeper can still open camp details through the release queue.');
    $_SESSION['user_id']=2; $_SESSION['role']='subject-officer'; $_GET=['page'=>'my-spectacle-camps','camp_id'=>$combinedCamp,'tab'=>'distribution'];
    campCheck($enabledIssues($render())===0,'Approval without release enables distribution.');
    $run(3,'release-stock-batch',['camp_id'=>$combinedCamp,'batch_ref'=>$batchRef]);
    $futureClock=(new DateTimeImmutable('now',new DateTimeZone('Asia/Colombo')))->modify('+5 minutes');
    if ($futureClock->format('Y-m-d')===scToday()) {
        scQuery($db,'UPDATE spectacle_camp_stock_requests SET planned_distribution_time=? WHERE camp_id=? AND batch_ref=?',[$futureClock->format('H:i'),$combinedCamp,$batchRef]);
        $_GET=['page'=>'my-spectacle-camps'];
        $futureTimeRow=$campListRow($render(),$combinedCamp);
        campCheck(str_contains($futureTimeRow,'class="admin-primary-action sc-action-distribute" disabled'),'Future distribution time enabled the camp action early.');
        $deny(fn()=>$run(2,'distribute',['camp_id'=>$combinedCamp,'participant_id'=>$saved['id'],'distribution_date'=>scToday(),'distribution_time'=>scNowTime()]),'Distribution before planned time');
        scQuery($db,'UPDATE spectacle_camp_stock_requests SET planned_distribution_time=? WHERE camp_id=? AND batch_ref=?',[$plannedTime,$combinedCamp,$batchRef]);
    }
    $tomorrow=(new DateTimeImmutable(scToday()))->modify('+1 day')->format('Y-m-d');
    scQuery($db,'UPDATE spectacle_camp_stock_requests SET planned_distribution_date=? WHERE camp_id=? AND batch_ref=?',[$tomorrow,$combinedCamp,$batchRef]);
    $_SESSION['user_id']=2; $_SESSION['role']='subject-officer'; $_GET=['page'=>'spectacle-camps'];
    $html=$render();
    $requestRow=$campListRow($html,$combinedCamp);
    campCheck(str_contains($requestRow,'data-sc-stock-status="released"') && str_contains($requestRow,'sc-stock-status-released') && str_contains($requestRow,'class="admin-primary-action sc-action-distribute" disabled'),'Future-dated released stock did not show a disabled distribution button in All Vision Camps.');
    campCheck(str_contains($requestRow,'class="sc-stock-dates"') && str_contains($requestRow,'class="sc-distribution-date"') && str_contains($requestRow,scCampDateLabel($tomorrow)) && str_contains($requestRow,'Combined camp hall'),'Release and distribution schedule are missing from their respective status sections.');
    $_GET=['page'=>'my-spectacle-camps','camp_id'=>$combinedCamp,'tab'=>'distribution'];
    $html=$render();
    campCheck($enabledIssues($html)===0,'Future-dated released stock enabled beneficiary distribution.');
    $_GET=['page'=>'spectacle-camp-participants','camp_id'=>$combinedCamp,'from'=>'my-spectacle-camps'];
    $html=$render();
    campCheck(str_contains($html,'class="sc-participant-distribute-form"') && str_contains($html,'<button type="submit" class="admin-primary-action" disabled'),'Future-dated stock enabled distribution on Registered Participants.');
    $deny(fn()=>$run(2,'distribute',['camp_id'=>$combinedCamp,'participant_id'=>$saved['id'],'distribution_date'=>scToday()]),'Distribution before planned date');
    $deny(fn()=>$run(2,'transfer-stock',['camp_id'=>$combinedCamp,'spectacle_category_id'=>(string)$saved['spectacle_category_id'],'quantity'=>'1','sso_id'=>'4','transfer_date'=>scToday()]),'Transfer before planned date');
    scQuery($db,'UPDATE spectacle_camp_stock_requests SET planned_distribution_date=? WHERE camp_id=? AND batch_ref=?',[scToday(),$combinedCamp,$batchRef]);
    $_GET=['page'=>'my-spectacle-camps'];
    $html=$render();
    $requestRow=$campListRow($html,$combinedCamp);
    campCheck(str_contains($requestRow,'class="admin-primary-action sc-action-distribute" href="dashboard.php?page=spectacle-camp-participants&amp;camp_id='.$combinedCamp.'&amp;from=my-spectacle-camps') && !str_contains($requestRow,'class="admin-primary-action sc-action-distribute" disabled'),'My Camps distribution button did not open Registered Participants on the planned date.');
    campCheck(str_contains($requestRow,'Distribution time') && str_contains($requestRow,scCampTimeLabel($plannedTime)),'My Camps does not display the planned distribution time.');
    campCheck(str_contains($requestRow,'id="vision-camp-row-'.$combinedCamp.'" class="admin-notification-target"'),'My Camps row has no notification target.');
    campCheck(str_contains($requestRow,'Pending Distribution') && str_contains($requestRow,'0 / 2'),'Camp row did not show pending distribution progress.');
    campCheck(strpos($requestRow,'class="sc-distribution-date"')<strpos($requestRow,'class="sc-combined-status sc-combined-stock-status"'),'Distribution date must sit beneath distribution status, before stock release details.');
    $_GET=['page'=>'spectacle-camps'];
    $allCampRow=$campListRow($render(),$combinedCamp);
    campCheck(str_contains($allCampRow,'page=spectacle-camp-participants&amp;camp_id='.$combinedCamp.'&amp;from=spectacle-camps'),'All Vision Camps distribution button did not open Registered Participants.');
    $_GET=['page'=>'spectacle-camp-participants','camp_id'=>$combinedCamp,'from'=>'my-spectacle-camps'];
    $html=$render();
    campCheck(substr_count($html,'class="sc-participant-distribute-form"')===2 && substr_count($html,'<button type="submit" class="admin-primary-action" >Distribute</button>')===2,'Registered Participants did not enable each approved beneficiary handover.');
    $_GET=['page'=>'my-spectacle-camps','camp_id'=>$combinedCamp,'tab'=>'distribution'];
    $html=$render();
    campCheck($enabledIssues($html)===2,'Released stock did not enable selected beneficiary row actions.');
    campCheck(str_contains($html,'name="distribution_date" form="sc-distribute-') && str_contains($html,'name="distribution_time" form="sc-distribute-') && str_contains($html,'name="csrf_token"'),'Row date/time controls or CSRF token missing.');
    $_SESSION['user_id']=4; $_SESSION['role']='social-service-officer'; $_GET['page']='spectacle-camps';
    campCheck($enabledIssues($render())===0,'SSO may not use stock held by the Subject Officer.');
    $_SESSION['user_id']=2; $_SESSION['role']='subject-officer';
    $actualTime=scNowTime();
    $run(2,'distribute',['camp_id'=>$combinedCamp,'participant_id'=>$saved['id'],'distribution_date'=>scToday(),'distribution_time'=>$actualTime]);
    campCheck(substr((string)scQuery($db,'SELECT distribution_time FROM spectacle_camp_distributions WHERE participant_id=?',[$saved['id']])->fetchColumn(),0,5)===$actualTime,'Beneficiary handover time was not saved.');
    campCheck($enabledIssues($render())===1,'Remaining released stock should allow the second selected beneficiary.');
    $_GET=['page'=>'my-spectacle-camps'];
    $requestRow=$campListRow($render(),$combinedCamp);
    campCheck(str_contains($requestRow,'In Distribution') && str_contains($requestRow,'1 / 2'),'Camp row did not show partial distribution progress.');
    $_GET=['page'=>'spectacle-camp-participants','camp_id'=>$combinedCamp];
    $html=$render();
    campCheck(str_contains($html,'In Distribution') && substr_count($html,'class="sc-participant-distribute-form"')===1 && str_contains($html,'data-sc-status="distributed"'),'Registered Participants did not update after the first handover.');
    $run(2,'distribute',['camp_id'=>$combinedCamp,'participant_id'=>$legacyId,'distribution_date'=>scToday()]);
    campCheck($enabledIssues($render())===0,'Exhausted stock did not disable distribution.');
    $_GET=['page'=>'my-spectacle-camps'];
    $requestRow=$campListRow($render(),$combinedCamp);
    campCheck(str_contains($requestRow,'class="sc-badge sc-distributed">Distributed</span>') && str_contains($requestRow,'2 / 2') && !str_contains($requestRow,'sc-action-distribute'),'Camp row did not finish distribution after all approved participants were served.');
    $_GET=['page'=>'spectacle-camp-participants','camp_id'=>$combinedCamp];
    $html=$render();
    campCheck(str_contains($html,'class="sc-badge sc-distributed">Distributed</span>') && !str_contains($html,'class="sc-participant-distribute-form"'),'Fully distributed participant page still offers a handover.');
    $campTranslations=require __DIR__.'/../includes/spectacle-camp-i18n.php';
    foreach(['si','ta'] as $language) foreach(['All Vision Camps','Distribute','History','Stock available to you','Awaiting stock','Spectacles distributed successfully.','Camp stock is tracked by quantity. Receiving stock does not release it.','Distribute is available after stock release and its planned distribution date, or after stock is transferred to you.','Available on the planned distribution date','Available on the planned distribution date:'] as $label) {
        campCheck(isset($campTranslations[$language][$label]) && $campTranslations[$language][$label]!==$label,'Missing camp translation: '.$language.' '.$label);
    }
    foreach ([1=>'admin',2=>'subject-officer',4=>'social-service-officer',5=>'subject-officer'] as $testActor=>$testRole) {
        foreach(['overview','participants','stock','distribution','history'] as $testTab) {
            $_SESSION['user_id']=$testActor; $_SESSION['role']=$testRole; $_SESSION['full_name']='Camp Fixture';
            $_GET=['page'=>'spectacle-camps','camp_id'=>(string)$camp,'tab'=>$testTab]; $_POST=[]; $_SERVER['REQUEST_METHOD']='GET';
            $html=$render();
            campCheck(str_contains($html,'</html>') && str_contains($html,'Camp Fixture'),'Camp workspace did not render.');
            campCheck(!str_contains($html,'Unable to load Vision Camps'),'Camp workspace returned a load error.');
            campCheck(!str_contains($html,'Vision Camp not found or access denied.'),'Camp history access denied.');
            campCheck(!str_contains($html,'sc-page-tools'),'Removed banner is still present.');
            campCheck(!str_contains($html,'name="power"') && !str_contains($html,'Prescription Power'),'Camp still displays a power field or column.');
            if ($testTab==='stock') campCheck(str_contains($html,'class="aid-request-list-table sc-table"') && !str_contains($html,'sc-request-card'),'Camp stock requests must use a table, not cards.');
            if ($testTab==='history') campCheck(str_contains($html,'class="sc-camp-spectacle-status"') && str_contains($html,'class="sc-badge sc-distributed">Distributed'),'Fully distributed camp lacks its green spectacle-status badge.');
            if ($testActor===5 && $testTab==='overview') {
                campCheck(str_contains($html,'Conducted By') && str_contains($html,'Test subject-officer'),'Shared camp must name its conducting officer.');
                campCheck(str_contains($html,'<section class="sc-camp-navigation"><div class="sc-camp-heading"><div class="sc-camp-identity">'),'Completed camp still shows the back link.');
            }
        }
    }
    $_SESSION['user_id']=5; $_SESSION['role']='subject-officer';
    $_GET=['page'=>'spectacle-camps'];
    $html=$render();
    campCheck(str_contains($html,'VC-'.$camp) && str_contains($html,'camp-test-5'),'Shared history must show the conducting Subject Officer.');
    campCheck(str_contains($html,'class="sc-plain-camp-list"') && str_contains($html,'Camp / Location'),'All Vision Camps must use the plain compact table.');
    campCheck(str_contains($html,'data-sc-camp-status-filter') && str_contains($html,'data-sc-camp-district-filter') && str_contains($html,'data-sc-distribution-status-filter'),'Subject Officer All Vision Camps needs camp, district, and distribution filters.');
    campCheck(preg_match('/<tr data-sc-row[^>]*>.*?VC-'.(int)$camp.'<\/strong>.*?<\/tr>/s',$html,$completedRow)===1,'Completed camp row is missing.');
    campCheck(str_contains($completedRow[0],'class="sc-camp-location"') && str_contains($completedRow[0],'class="sc-camp-when"') && str_contains($completedRow[0],'>Time</small>') && str_contains($completedRow[0],'>Date</small>'),'Location or labeled date/time layout is missing.');
    campCheck(str_contains($completedRow[0],'class="sc-actor-username">camp-test-2') && str_contains($completedRow[0],'class="sc-actor-username">camp-test-5'),'Requester or conductor username is missing from its role-stacked cell.');
    campCheck(str_contains($completedRow[0],'class="sc-camp-completed-at"') && str_contains($completedRow[0],'class="sc-combined-status"'),'Completed time or combined status is missing.');
    campCheck(str_contains($completedRow[0],'class="sc-participant-approved"') && str_contains($completedRow[0],'class="sc-participant-rejected"'),'Participant counts are not split into colored lines.');
    $_SESSION['user_id']=1; $_SESSION['role']='admin';
    $html=$render();
    campCheck(str_contains($html,'Camp History') && str_contains($html,'class="sc-plain-camp-list"') && str_contains($html,'data-sc-camp-status-filter') && str_contains($html,'data-sc-camp-district-filter') && str_contains($html,'data-sc-distribution-status-filter'),'Admin Camp History or its filters are missing.');
    campCheck(str_contains($html,'page=spectacle-camp-participants&amp;camp_id='.$camp) && str_contains($html,'View registered participants') && !str_contains($html,'sc-action-details'),'Admin Camp History must open participants instead of details.');
    $_GET=['page'=>'spectacle-camp-participants','camp_id'=>(string)$camp];
    $html=$render();
    campCheck(str_contains($html,'Registered Participants') && str_contains($html,'SCP-') && str_contains($html,'Camp History') && !str_contains($html,'Access denied.'),'Admin cannot read registered participants from Camp History.');
    campCheck(!str_contains($html,'class="sc-participant-distribute-form"'),'Admin participant view must remain read-only.');
    $_SESSION['user_id']=5; $_SESSION['role']='subject-officer';
    $_GET=['page'=>'spectacle-camp-new'];
    $html=$render();
    campCheck(str_contains($html,'class="aid-form-card"') && str_contains($html,'aid-request-form sc-camp-request-form'),'Camp form does not reuse Aid Request styles.');
    campCheck(substr_count($html,'<fieldset>')===3,'Camp form grouping is missing.');
    campCheck(str_contains($html,'data-sc-district') && str_contains($html,'data-sc-division'),'Location filters are missing.');
    campCheck(str_contains($html,'name="csrf_token"') && str_contains($html,'name="request_token"'),'Camp submission protection is missing.');
    campCheck(str_contains($html,'name="camp_hour"') && str_contains($html,'name="camp_minute"') && str_contains($html,'name="camp_period"')
        && !str_contains($html,'type="time" name="camp_time"'),'New Camp must use the translatable time selector instead of a native time picker.');
    $conductCamp=$run(2,'create-camp',$create);
    $run(1,'decide-camp',['camp_id'=>$conductCamp,'decision'=>'approved']);
    $_SESSION['user_id']=2; $_SESSION['role']='subject-officer';
    $_GET=['page'=>'spectacle-camps']; $_POST=[];
    $html=$render();
    campCheck(str_contains($html,'class="sc-conduct-form is-compact"') && str_contains($html,'name="camp_id" value="'.$conductCamp.'"'),'Camp list does not offer a compact conduct action.');
    $_GET=['page'=>'spectacle-camps','camp_id'=>$conductCamp];
    $html=$render();
    campCheck(str_contains($html,'sc-conduct-panel'),'Camp details do not offer conduct action.');
    $_SESSION['user_id']=5;
    $html=$render();
    campCheck(str_contains($html,'class="sc-conduct-form"'),'Another Subject Officer cannot see the shared conduct action.');
    $run(2,'conduct-camp',['camp_id'=>$conductCamp]);
    $_SESSION['user_id']=2;
    $html=$render();
    campCheck(str_contains($html,'<section class="sc-camp-navigation"><div class="sc-camp-heading"><a class="outline-action"'),'Active camp detail lost its back link.');
    campCheck(str_contains($html,'data-sc-complete-form') && str_contains($html,'id="sc-complete-dialog"'),'Camp completion confirmation dialog is missing.');
    require_once __DIR__.'/../includes/form-submissions.php';
    $protectedHtml=widmsAddFormSubmissionFields($html);
    $previous=libxml_use_internal_errors(true);
    $document=new DOMDocument(); $document->loadHTML('<?xml encoding="UTF-8"'.$protectedHtml);
    libxml_clear_errors(); libxml_use_internal_errors($previous);
    $xpath=new DOMXPath($document);
    campCheck($xpath->query('//form[@data-sc-complete-form and @method="post"]//input[@name="widms_submission_token"]')->length>0,
        'Completion form lost its one-time submission token.');
    $_SESSION['user_id']=3; $_SESSION['role']='store-keeper'; $_GET=['page'=>'spectacle-camp-releases'];
    $html=$render();
    campCheck(!str_contains($html,'class="sc-camp-navigation"') && !str_contains($html,'Camp Stock &amp; History') && str_contains($html,'No approved camp stock requests to release.'),'Store Keeper still sees camp history or released batches.');
    $_SESSION['role']='subject-officer';
    $_SESSION['user_id']=2; $_GET=['page'=>'spectacle-camps','camp_id'=>$conductCamp,'tab'=>'participants'];
    $html=$render();
    campCheck(!str_contains($html,'class="sc-conduct-form"') && str_contains($html,'value="save-participant"'),'Conducted camp does not open participant registration.');
    campCheck(!str_contains($html,'<table'),'Participant registration must not include a table.');
    campCheck(substr_count($html,'value="save-participant"')===1 && !str_contains($html,'value="decide-participant"'),'Participant forms are not merged.');
    $_GET=['page'=>'spectacle-camp-register','camp_id'=>$conductCamp];
    $html=$render();
    foreach (['full_name','gender','nic','elder_card_number','phone','address','gn_division_id','decision','reason'] as $field) campCheck(str_contains($html,'name="'.$field.'"'),'Separate participant form field missing: '.$field);
    campCheck(str_contains($html,'Camp Beneficiary Registration') && str_contains($html,'sc-beneficiary-card'),'Separate registration form page missing.');
    $decisionPosition=strpos($html,'data-sc-decision');
    $typePosition=strpos($html,'data-sc-approved-section');
    $reasonPosition=strpos($html,'data-sc-rejected-section');
    campCheck($decisionPosition!==false && $typePosition!==false && $reasonPosition!==false && $decisionPosition<$typePosition && $typePosition<$reasonPosition,'Decision must appear before conditional spectacle type and rejection reason.');
    campCheck(str_contains($html,'data-sc-approved-section hidden') && str_contains($html,'data-sc-rejected-section hidden'),'Conditional fields must start hidden until a decision is selected.');
    campCheck(!str_contains($html,'<table') && !str_contains($html,'name="power"'),'Registration still displays a table or power field.');
    campCheck(str_contains($html,'page=spectacle-camp-participants&amp;camp_id='.$conductCamp),'Registered participants button does not open the dedicated table page.');
    $_SESSION['camp_notice']='Participant saved. You can add another beneficiary.';
    $html=$render();
    campCheck(str_contains($html,'widms-success-message') && str_contains($html,'Participant saved.'),'Standard participant success message missing.');
    $_GET=['page'=>'spectacle-camp-participants','camp_id'=>$combinedCamp];
    $html=$render();
    campCheck(str_contains($html,'Registered Participants') && str_contains($html,'Spectacle Type') && str_contains($html,'Reading Only'),'Dedicated participant page lacks the spectacle-type table.');
    campCheck(str_contains($html,'class="outline-action sc-participant-back" href="dashboard.php?page=spectacle-camps"'),'Participant page lacks a return button to All Vision Camps.');
    campCheck(str_contains($html,'data-sc-search') && str_contains($html,'data-sc-status-filter') && str_contains($html,'data-sc-status="distributed"') && str_contains($html,'data-sc-status="rejected"'),'Participant search or status filter is missing.');
    campCheck(!str_contains($html,'<option value="registered">') && !str_contains($html,'<th scope="col">Spectacle Status</th>'),'Registered filter or repeated spectacle-status column remains.');
    campCheck(str_contains($html,'class="sc-camp-spectacle-status"') && str_contains($html,'class="sc-badge sc-distributed">Distributed'),'Camp spectacle status is not shown as a separate summary badge.');
    campCheck(str_contains($html,'class="sc-badge sc-distributed"') && str_contains($html,'class="sc-badge sc-rejected"'),'Distributed and rejected participant status badges are missing.');
    campCheck(!str_contains($html,'value="save-participant"'),'Dedicated participant list must not contain the registration form.');
    campCheck(str_contains($html,'format=print') && str_contains($html,'format=xlsx') && str_contains($html,'Print with signatures') && str_contains($html,'Download Excel'),'Completed camp participant exports are missing.');
    $_GET['from']='my-spectacle-camps';
    $html=$render();
    campCheck(str_contains($html,'class="outline-action sc-participant-back" href="dashboard.php?page=my-spectacle-camps"'),'My Camps participant link does not return to My Camps.');
    $_SESSION['user_id']=5;
    $html=$render();
    campCheck(str_contains($html,'Registered Participants') && str_contains($html,'Reading Only') && str_contains($html,'href="dashboard.php?page=spectacle-camps"'),'Another Subject Officer cannot view the shared participant table or return to All Vision Camps.');
    $_GET=['page'=>'spectacle-camp-register','camp_id'=>$conductCamp];
    $html=$render();
    campCheck(!str_contains($html,'value="save-participant"') && str_contains($html,'Only the Subject Officer who conducted this approved camp can register participants.'),'A non-conducting Subject Officer can open the registration form.');
    $_GET=['page'=>'spectacle-camp-participants','camp_id'=>$conductCamp];
    $html=$render();
    campCheck(!str_contains($html,'format=print') && !str_contains($html,'format=xlsx'),'Participant exports appeared before camp completion.');
    http_response_code(200); $_SESSION['user_id']=2;
    $futureCamp=$run(2,'create-camp',array_replace($create,['camp_date'=>(new DateTimeImmutable(scToday()))->modify('+1 day')->format('Y-m-d')]));
    $run(1,'decide-camp',['camp_id'=>$futureCamp,'decision'=>'approved']);
    $deny(fn()=>$run(2,'conduct-camp',['camp_id'=>$futureCamp]),'Conduct before scheduled date');
    $_GET=['page'=>'spectacle-camps','camp_id'=>$futureCamp];
    $html=$render();
    campCheck(str_contains($html,'disabled title=') && str_contains($html,'Available on the scheduled camp date:'),'Future camp action lacks date guidance.');
    $otherCamp=$run(5,'create-camp',$create);
    $run(1,'decide-camp',['camp_id'=>$otherCamp,'decision'=>'approved']);
    $run(5,'conduct-camp',['camp_id'=>$otherCamp]);
    $_GET=['page'=>'my-spectacle-camps'];
    $html=$render();
    campCheck(str_contains($html,'VC-'.$conductCamp) && !str_contains($html,'VC-'.$otherCamp.'</strong>'),'My Camps ownership filter failed.');
    campCheck(str_contains($html,'class="sc-plain-camp-list"') && str_contains($html,'Camp / Location'),'My Camps must use the same plain compact table.');
    campCheck(str_contains($html,'data-sc-camp-status-filter') && str_contains($html,'data-sc-stock-status-filter') && str_contains($html,'data-sc-distribution-status-filter') && !str_contains($html,'data-sc-camp-stage-button') && str_contains($html,'class="sc-camp-date"'),'My Camps dropdown filters or one-line date are missing.');
    campCheck(str_contains($html,'sc-action-participants') && str_contains($html,'page=spectacle-camp-participants&amp;camp_id='.$conductCamp),'My Camps lacks a direct registered-participants action.');
    campCheck(str_contains($html,'sc-complete-form is-compact') && str_contains($html,'sc-complete-button'),'My Camps lacks its completion action.');
    campCheck(str_contains($html,'name="camp_id" value="'.$futureCamp.'"') && str_contains($html,'Conduct Camp'),'My Camps conduct button missing.');
    campCheck(!str_contains($html,'sc-action-details'),'My Camps still shows View details links.');
    $_GET=['page'=>'spectacle-camps'];
    $html=$render();
    campCheck(str_contains($html,'VC-'.$otherCamp.'</strong>') && str_contains($html,'Conducted By') && str_contains($html,'Conducted At') && str_contains($html,'data-sc-stock-status-filter'),'Camp History must include all officers, conduct details and stock status filter.');
    campCheck(!str_contains($html,'sc-action-details') && str_contains($html,'sc-action-participants'),'All Vision Camps should show participant actions without View details links.');
    $_SESSION['user_id']=5;
    $_GET=['page'=>'spectacle-camp-participants','camp_id'=>$camp];
    $html=$render();
    campCheck(!str_contains($html,'value="save-participant"') && str_contains($html,'Registered Participants'),'Completed camp participant view is not read-only.');
    $_SESSION['user_id']=2;
    $_GET=['page'=>'spectacle-camps'];
    $html=$render();
    $conductor=(string)scQuery($db,"SELECT u.username FROM spectacle_camp_events e JOIN users u ON u.id=e.actor_id WHERE e.camp_id=? AND e.action='conduct-camp'",[$otherCamp])->fetchColumn();
    campCheck(str_contains($html,htmlspecialchars($conductor,ENT_QUOTES,'UTF-8')),'Conducting officer username missing.');
    $_GET=['page'=>'my-spectacle-camps','camp_id'=>$otherCamp];
    $html=$render();
    campCheck(str_contains($html,'Vision Camp not found or access denied.'),'My Camps exposes another officer detail.');
    http_response_code(200);
    $pendingCamp=$run(2,'create-camp',$create+['remarks'=>'<script>fixture</script>']);
    $_SESSION['user_id']=1; $_SESSION['role']='admin';
    $_GET=['page'=>'pending-approvals','tab'=>'vision-camps']; $_POST=[];
    $renderApproval=static function():string { ob_start(); require __DIR__.'/../modules/admin/pending-approvals.php'; return (string)ob_get_clean(); };
    $html=$renderApproval();
    campCheck(str_contains($html,'id="vision-camp-request-'.$pendingCamp.'"'),'Pending camp approval card missing.');
    campCheck(str_contains($html,'class="sc-approval-card-list"') && str_contains($html,'data-sc-approval-search') && !str_contains($html,'class="aid-request-list-table sc-table"'),'Vision Camp approvals must use searchable request cards.');
    campCheck(!str_contains($html,'id="vision-camp-request-'.$camp.'"'),'Reviewed camp remains in pending queue.');
    campCheck(str_contains($html,'value="approved" class="approve-button"') && str_contains($html,'value="rejected" class="reject-button"'),'Standard approval buttons missing.');
    campCheck(str_contains($html,'&lt;script&gt;fixture&lt;/script&gt;') && !str_contains($html,'<script>fixture</script>'),'Card remarks are not escaped.');
    campCheck(str_contains($html,'data-approval-count="vision-camps"') && str_contains($html,'Vision Camp Requests'),'Vision Camp sidebar queue missing.');
    campCheck(!str_contains($html,'class="approval-tabs"'),'Removed approval tabs are still visible.');
    campCheck(!str_contains($html,'page=spectacle-camp-approvals'),'Separate camp approval sidebar link remains.');
    $_SERVER['REQUEST_METHOD']='POST';
    $_POST=['action'=>'decide-camp','camp_id'=>$pendingCamp,'decision'=>'approved','csrf_token'=>'invalid','request_token'=>bin2hex(random_bytes(16))];
    $html=$renderApproval();
    campCheck(str_contains($html,'Your session expired.'),'Approval POST did not reject invalid CSRF token.');
    campCheck(scQuery($db,'SELECT status FROM spectacle_camps WHERE id=?',[$pendingCamp])->fetchColumn()==='pending','Invalid CSRF changed the camp.');
    $_POST=['action'=>'decide-camp','camp_id'=>$pendingCamp,'decision'=>'rejected','reason'=>'','csrf_token'=>csrfToken(),'request_token'=>bin2hex(random_bytes(16))];
    $html=$renderApproval();
    campCheck(scQuery($db,'SELECT status FROM spectacle_camps WHERE id=?',[$pendingCamp])->fetchColumn()==='pending','Rejection without reason changed camp.');
    $run(1,'decide-camp',['camp_id'=>$pendingCamp,'decision'=>'rejected','reason'=>'Schedule unavailable']);
    $_POST=[]; $_SERVER['REQUEST_METHOD']='GET';
    $html=$renderApproval();
    campCheck(str_contains($html,'No pending Vision Camp requests.'),'Reviewed card did not leave the approval queue.');
    if (($argv[1]??'')==='--preview') {
        $previewDirectory=dirname(__DIR__).'/.codex-camp-preview';
        if (!is_dir($previewDirectory)) mkdir($previewDirectory,0700,true);
        $_SESSION['user_id']=2; $_SESSION['role']='subject-officer';
        $_GET=['page'=>'spectacle-camp-new'];
        $html=$render();
        $base='file:///'.str_replace(' ','%20',str_replace('\\','/',realpath(__DIR__.'/../public'))).'/';
        $html=str_replace('<head>','<head><base href="'.$base.'">',$html);
        // Preview is static: don't poll real notifications or use interactive forms.
        $html=preg_replace('#<script\b[^>]*>.*?</script>#s','',$html);
        file_put_contents($previewDirectory.'/new-camp.html',$html);
        echo "Static fixture preview: $previewDirectory/new-camp.html\n";
    }
    session_destroy();
}
$typeCamp=$run(2,'create-camp',array_replace($create,['camp_hour'=>'01','camp_minute'=>'05','camp_period'=>'PM']));
campCheck(substr((string)scQuery($db,'SELECT camp_time FROM spectacle_camps WHERE id=?',[$typeCamp])->fetchColumn(),0,5)==='13:05','Camp submission did not save the translated time selection.');
$run(1,'decide-camp',['camp_id'=>$typeCamp,'decision'=>'approved']);
$run(2,'conduct-camp',['camp_id'=>$typeCamp]);
foreach ([1=>'EXPORT-TYPE-1',2=>'EXPORT-TYPE-2'] as $categoryId=>$identifier) {
    $run(2,'save-participant',array_replace($combined,['camp_id'=>$typeCamp,'elder_card_number'=>$identifier,'spectacle_category_id'=>(string)$categoryId]));
}
$run(2,'complete-camp',['camp_id'=>$typeCamp]);
$typeTotals=scParticipantExportData($db,$typeCamp,scActor($db,5))['totals'];
campCheck(($typeTotals[0]['quantity']??0)===1 && ($typeTotals[1]['quantity']??0)===1,'Excel totals must keep each spectacle type separate.');
$run(3,'receive-stock',['camp_id'=>$typeCamp,'spectacle_category_id'=>1,'quantity'=>1,'reference_code'=>'BATCH-TYPE-1','received_date'=>scToday()]);
$run(3,'receive-stock',['camp_id'=>$typeCamp,'spectacle_category_id'=>2,'quantity'=>1,'reference_code'=>'BATCH-TYPE-2','received_date'=>scToday()]);
$deny(fn()=>$run(5,'request-stock-batch',['camp_id'=>$typeCamp,'planned_distribution_date'=>scToday()]),'Non-conducting officer requests a camp batch');
$run(2,'request-stock-batch',['camp_id'=>$typeCamp,'planned_distribution_date'=>scToday(),'planned_distribution_time'=>scNowTime(),'planned_distribution_place'=>'Type camp hall']);
$batchLines=scQuery($db,'SELECT batch_ref,spectacle_category_id,quantity,status,planned_distribution_place FROM spectacle_camp_stock_requests WHERE camp_id=? ORDER BY spectacle_category_id',[$typeCamp])->fetchAll(PDO::FETCH_ASSOC);
campCheck(count($batchLines)===2 && $batchLines[0]['batch_ref']===$batchLines[1]['batch_ref'] && (int)$batchLines[0]['quantity']===1 && (int)$batchLines[1]['quantity']===1,'One camp submission did not create a two-type stock batch.');
campCheck($batchLines[0]['planned_distribution_place']==='Type camp hall' && $batchLines[1]['planned_distribution_place']==='Type camp hall','All batch lines must share the distribution venue.');
if (function_exists('scApprovalCardList')) {
    $cardRows=scQuery($db,"SELECT r.*,category.name spectacle_category_name,d.name district_name,ds.name division_name,u.username requester_username,u.role requester_role FROM spectacle_camp_stock_requests r JOIN spectacle_camps c ON c.id=r.camp_id JOIN spectacle_categories category ON category.id=r.spectacle_category_id JOIN districts d ON d.id=c.district_id JOIN ds_divisions ds ON ds.id=c.ds_division_id JOIN users u ON u.id=r.requested_by WHERE r.camp_id=? AND r.status='pending' ORDER BY r.id",[$typeCamp])->fetchAll(PDO::FETCH_ASSOC);
    ob_start(); scApprovalCardList($cardRows,'stock'); $stockCards=(string)ob_get_clean();
    campCheck(substr_count($stockCards,'data-sc-approval-card')===1 && str_contains($stockCards,'Reading Only') && str_contains($stockCards,'Distance Only') && str_contains($stockCards,'Type camp hall') && str_contains($stockCards,'value="decide-stock-batch"') && str_contains($stockCards,'value="approved"') && str_contains($stockCards,'value="rejected"'),'Admin stock approval did not show one actionable card with the distribution venue for the full camp batch.');
}
$typeBatchRef=(string)$batchLines[0]['batch_ref'];
$deny(fn()=>$run(3,'release-stock-batch',['camp_id'=>$typeCamp,'batch_ref'=>$typeBatchRef]),'Store Keeper releases an unapproved batch');
$run(1,'decide-stock-batch',['camp_id'=>$typeCamp,'batch_ref'=>$typeBatchRef,'decision'=>'approved']);
campCheck((int)scQuery($db,"SELECT COUNT(*) FROM spectacle_camp_stock_requests WHERE camp_id=? AND batch_ref=? AND status='approved' AND approved_quantity=quantity",[$typeCamp,$typeBatchRef])->fetchColumn()===2,'Admin approval did not approve the whole camp batch.');
$run(3,'release-stock-batch',['camp_id'=>$typeCamp,'batch_ref'=>$typeBatchRef]);
campCheck((int)scQuery($db,"SELECT COUNT(*) FROM spectacle_camp_stock_requests WHERE camp_id=? AND batch_ref=? AND status='released'",[$typeCamp,$typeBatchRef])->fetchColumn()===2,'Store Keeper did not release the whole camp batch.');
$tomorrow=(new DateTimeImmutable(scToday()))->modify('+1 day')->format('Y-m-d');
scQuery($db,'UPDATE spectacle_camp_stock_requests SET planned_distribution_date=? WHERE camp_id=? AND spectacle_category_id=2',[$tomorrow,$typeCamp]);
campCheck(scSubjectDistributableQuantity($db,$typeCamp,1,scToday())===1 && scSubjectDistributableQuantity($db,$typeCamp,2,scToday())===0,'Planned-date gate did not separate spectacle types in the same camp batch.');
$type2Participant=(int)scQuery($db,'SELECT id FROM spectacle_camp_participants WHERE camp_id=? AND spectacle_category_id=2',[$typeCamp])->fetchColumn();
$deny(fn()=>$run(2,'distribute',['camp_id'=>$typeCamp,'participant_id'=>$type2Participant,'distribution_date'=>scToday()]),'Future-dated spectacle type distributed early');
scQuery($db,'UPDATE spectacle_camp_stock_requests SET planned_distribution_date=? WHERE camp_id=? AND spectacle_category_id=2',[scToday(),$typeCamp]);
echo "Vision Camp workflow passed: roles, approvals, registration, quantity-only stock, release-gated row actions, transfers, translations, history and SMS; no live records or messages changed.\n";
