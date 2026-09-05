<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/eligibility.php';
require_once __DIR__.'/../config/database.php';
requireRole('social-service-officer');
header('Content-Type: application/json; charset=utf-8');

try{
    $nic=strtoupper(preg_replace('/\s+/','',(string)($_GET['nic']??'')));
    $card=strtoupper(trim((string)($_GET['elders_card']??'')));
    $item=filter_input(INPUT_GET,'item_id',FILTER_VALIDATE_INT);
    if($nic===''&&$card==='')throw new RuntimeException('Enter an NIC or Elders’ Identity Card number.');
    $conditions=[];$params=[];
    if($nic!==''){$conditions[]='nic=:nic';$params['nic']=$nic;}
    if($card!==''){$conditions[]='elders_card_number=:card';$params['card']=$card;}
    $db=database();$q=$db->prepare('SELECT id,full_name FROM beneficiaries WHERE '.implode(' OR ',$conditions).' LIMIT 1');$q->execute($params);$beneficiary=$q->fetch();
    if(!$beneficiary){echo json_encode(['found'=>false],JSON_UNESCAPED_UNICODE);exit;}
    $q=$db->prepare('SELECT i.item_name,i.variety,d.distributed_at FROM distributions d JOIN inventory_items i ON i.id=d.item_id WHERE d.beneficiary_id=:id ORDER BY d.distributed_at DESC');$q->execute(['id'=>$beneficiary['id']]);$history=$q->fetchAll();
    $language=widmsLanguage();$labels=['en'=>'Previously received','si'=>'මීට පෙර ලබාගත් අයිතම','ta'=>'முன்பு பெற்ற பொருட்கள்'];
    $result=['found'=>true,'beneficiary'=>$beneficiary['full_name'],'history'=>$history,'history_label'=>$labels[$language]??$labels['en']];
    if($item)$result['eligibility']=beneficiaryEligibility($db,(int)$beneficiary['id'],(int)$item);
    echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){http_response_code(422);echo json_encode(['error'=>$e instanceof RuntimeException?$e->getMessage():'Unable to check eligibility.'],JSON_UNESCAPED_UNICODE);}
