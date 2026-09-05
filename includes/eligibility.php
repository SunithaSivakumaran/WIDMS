<?php
declare(strict_types=1);

/** Check item-specific probation rules and return the previous device in the result. */
function beneficiaryEligibility(PDO $db,int $beneficiaryId,int $itemId,int $excludedAidRequestId=0):array
{
    // Request-based distribution must not treat the approved request being fulfilled as a conflicting request.
    if($excludedAidRequestId===0&&($_POST['distribution_type']??'')==='request-based'){
        $excludedAidRequestId=filter_var($_POST['aid_request_id']??null,FILTER_VALIDATE_INT)?:0;
    }
    $q=$db->prepare("SELECT b.id,b.disability,i.item_name,i.variety,dai.id rule_id,dai.restriction_months
        FROM beneficiaries b
        -- Existing databases can have different collations for these two text columns.
        JOIN disability_types dt ON LOWER(dt.name) COLLATE utf8mb4_unicode_ci=LOWER(b.disability) COLLATE utf8mb4_unicode_ci AND dt.status='active'
        JOIN disability_aid_items dai ON dai.disability_type_id=dt.id AND dai.item_id=:item AND dai.status='active'
        JOIN inventory_items i ON i.id=dai.item_id
        WHERE b.id=:beneficiary AND b.status='active' LIMIT 1");
    $q->execute(['beneficiary'=>$beneficiaryId,'item'=>$itemId]);$requested=$q->fetch();
    if(!$requested)return ['eligible'=>false,'reason'=>'The selected item is not configured for this beneficiary disability.','item'=>null,'history'=>[]];

    // An unfinished request reserves the beneficiary, preventing parallel aid requests before a decision is made.
    $q=$db->prepare("SELECT ar.id,i.item_name,i.variety
        FROM aid_requests ar
        JOIN inventory_items i ON i.id=ar.item_id
        WHERE ar.beneficiary_id=:beneficiary
          AND ar.status IN ('pending','approved','goods-requested')
          AND ar.id<>:excluded_request
        ORDER BY ar.created_at DESC LIMIT 1");
    $q->execute(['beneficiary'=>$beneficiaryId,'excluded_request'=>$excludedAidRequestId]);$activeRequest=$q->fetch();
    if($activeRequest){
        $activeDevice=$activeRequest['item_name'].($activeRequest['variety']?' / '.$activeRequest['variety']:'');
        $language=function_exists('widmsLanguage')?widmsLanguage():'en';
        $templates=[
            'en'=>'This beneficiary already has an active request for %s. The selected item cannot be requested until it is completed or rejected.',
            'si'=>'මෙම ප්‍රතිලාභියා සඳහා %s සඳහා සක්‍රිය ඉල්ලීමක් දැනට ක්‍රියාවලියේ ඇත. එය සම්පූර්ණ හෝ ප්‍රතික්ෂේප කරන තෙක් තෝරාගත් අයිතමය ඉල්ලිය නොහැක.',
            'ta'=>'இந்தப் பயனாளருக்கு %s-க்கான செயலில் உள்ள கோரிக்கை தற்போது செயல்பாட்டில் உள்ளது. அது நிறைவேற்றப்படவோ அல்லது நிராகரிக்கப்படவோ செய்யும் வரை தேர்ந்தெடுத்த பொருளைக் கோர முடியாது.',
        ];
        return ['eligible'=>false,'reason'=>sprintf($templates[$language]??$templates['en'],$activeDevice),'item'=>$requested,'history'=>[],'active_request_id'=>(int)$activeRequest['id'],'active_item'=>$activeDevice];
    }

    // The source item's probation blocks itself and any explicitly selected related items.
    $q=$db->prepare("SELECT d.distributed_at,received.item_name,received.variety,source_rule.restriction_months
        FROM distributions d
        JOIN inventory_items received ON received.id=d.item_id
        JOIN beneficiaries b ON b.id=d.beneficiary_id
        -- Keep historic-distribution checks compatible with the configured disability collation.
        JOIN disability_types dt ON LOWER(dt.name) COLLATE utf8mb4_unicode_ci=LOWER(b.disability) COLLATE utf8mb4_unicode_ci
        JOIN disability_aid_items source_rule ON source_rule.disability_type_id=dt.id AND source_rule.item_id=d.item_id AND source_rule.status='active'
        LEFT JOIN disability_item_prohibitions blocked ON blocked.disability_aid_item_id=source_rule.id AND blocked.prohibited_item_id=:requested_item
        WHERE d.beneficiary_id=:beneficiary
          AND source_rule.restriction_months>0
          AND (d.item_id=:same_item OR blocked.prohibited_item_id IS NOT NULL)
        ORDER BY d.distributed_at DESC");
    $q->execute(['requested_item'=>$itemId,'same_item'=>$itemId,'beneficiary'=>$beneficiaryId]);$history=$q->fetchAll();
    $now=new DateTimeImmutable();
    foreach($history as $received){
        $eligibleOn=(new DateTimeImmutable((string)$received['distributed_at']))->modify('+'.(int)$received['restriction_months'].' months');
        if($eligibleOn>$now){
            $device=$received['item_name'].($received['variety']?' / '.$received['variety']:'');
            $receivedOn=(new DateTimeImmutable((string)$received['distributed_at']))->format('d M Y');
            $language=function_exists('widmsLanguage')?widmsLanguage():'en';
            $templates=[
                'en'=>'This beneficiary received %s on %s and cannot receive the selected item until %s.',
                'si'=>'මෙම ප්‍රතිලාභියා %2$s දින %1$s ලබාගෙන ඇති අතර %3$s දක්වා තෝරාගත් අයිතමය ලබාගත නොහැක.',
                'ta'=>'இந்தப் பயனாளர் %2$s அன்று %1$s பெற்றுள்ளார்; %3$s வரை தேர்ந்தெடுத்த பொருளைப் பெற முடியாது.',
            ];
            $reason=sprintf($templates[$language]??$templates['en'],$device,$receivedOn,$eligibleOn->format('d M Y'));
            return ['eligible'=>false,'reason'=>$reason,'item'=>$requested,'history'=>$history,'received_item'=>$device,'eligible_on'=>$eligibleOn->format('Y-m-d')];
        }
    }
    return ['eligible'=>true,'reason'=>$history?'The relevant probation period has ended.':'No conflicting previous device distribution exists.','item'=>$requested,'history'=>$history];
}
