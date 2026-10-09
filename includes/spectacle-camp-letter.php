<?php
declare(strict_types=1);

require_once __DIR__.'/spectacle-camps.php';

/** The wording follows the first A5 example; officers may tailor it for their camp. */
function scLetterDefaults(): array
{
    return [
        'reference'=>'ද.ප.ස/ස.සේ. 09/02',
        'greeting'=>'මහත්මයාණෙනි / මහත්මියනි,',
        'subject'=>'ඇස් කණ්ණාඩි ප්‍රදානය කිරීම.',
        'opening'=>'දකුණු පළාත් සමාජ සුබසාධන දෙපාර්තමේන්තුව මඟින් පවත්වන ලද අක්ෂි සායනයට සහභාගී වූ ඔබ වෙනුවෙන් ඇස් කණ්ණාඩි යුගලයක් ප්‍රදානය කිරීමට කටයුතු කර ඇති බව සතුටින් දන්වමි.',
        'schedule'=>'02. එම ඇස් කණ්ණාඩි යුගලය {{date}} දින {{time}} ට {{place}} දී ඔබට ලබා දීමට කටයුතු සූදානම් කර ඇත.',
        'collection'=>'03. එම අවස්ථාවට සහභාගී වී ඉහත සඳහන් ඔබට හිමි අංකයට අනුව එදිනට එම ලේඛනයේ අත්සන් කර, අදාළ ඇස් කණ්ණාඩි යුගලය ලබා ගන්නා ලෙස කාරුණිකව දන්වමි. තවද නියමිත වේලාවට පමණක්ම ඇස් කණ්ණාඩි නිකුත් කරනු ලැබේ.',
        'bring'=>'04. ඔබ පැමිණෙන විට කරුණාකර මෙම ලිපිය සහ ජාතික හැඳුනුම්පත රැගෙන එන ලෙස කාරුණිකව දන්වමි.',
        'signer_name'=>'',
        'signer_title'=>'පරිපාලන නිලධාරී,',
        'signer_department'=>'සමාජ සුබසාධන, පරිවාස හා ළමාරක්ෂක සේවා දෙපාර්තමේන්තුව, දකුණු පළාත.',
    ];
}

function scLetterValidate(array $input): array
{
    $limits=['reference'=>100,'greeting'=>150,'subject'=>200,'opening'=>750,'schedule'=>750,'collection'=>900,'bring'=>750,'signer_name'=>150,'signer_title'=>150,'signer_department'=>300];
    $validated=[];
    foreach ($limits as $field=>$limit) {
        $validated[$field]=scText($input,$field,$limit,$field!=='signer_name');
    }
    foreach (['{{date}}','{{time}}','{{place}}'] as $token) {
        if (!str_contains($validated['schedule'],$token)) {
            throw new DomainException('Keep the '.$token.' placeholder in the distribution schedule so each letter is filled automatically.');
        }
    }
    return $validated;
}

function scLetterTemplate(PDO $db, int $campId): array
{
    $json=scQuery($db,'SELECT content_json FROM spectacle_camp_letter_templates WHERE camp_id=?',[$campId])->fetchColumn();
    $saved=is_string($json)?json_decode($json,true):null;
    return array_replace(scLetterDefaults(),is_array($saved)?array_intersect_key($saved,scLetterDefaults()):[]);
}

function scSaveLetterTemplate(PDO $db, int $campId, int $actorId, array $fields): void
{
    $content=scLetterValidate($fields);
    scQuery($db,'INSERT INTO spectacle_camp_letter_templates (camp_id,content_json,updated_by) VALUES (?,?,?) ON DUPLICATE KEY UPDATE content_json=VALUES(content_json),updated_by=VALUES(updated_by),updated_at=CURRENT_TIMESTAMP',
        [$campId,json_encode($content,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$actorId]);
}

/** Returns approved people in registration order, with one printable schedule per type. */
function scLetterRecipients(PDO $db, int $campId): array
{
    $people=scQuery($db,'SELECT id,full_name,address,status,spectacle_category_id FROM spectacle_camp_participants WHERE camp_id=? ORDER BY id',[$campId])->fetchAll(PDO::FETCH_ASSOC);
    $schedules=scQuery($db,"SELECT spectacle_category_id,planned_distribution_date,planned_distribution_time,planned_distribution_place
        FROM spectacle_camp_stock_requests WHERE camp_id=? AND status IN ('approved','released') ORDER BY id DESC",[$campId])->fetchAll(PDO::FETCH_ASSOC);
    $byCategory=[];
    foreach ($schedules as $schedule) {
        $categoryId=(int)$schedule['spectacle_category_id'];
        if ($categoryId>0 && !isset($byCategory[$categoryId])) $byCategory[$categoryId]=$schedule;
    }
    $recipients=[];
    foreach ($people as $index=>$person) {
        $categoryId=(int)($person['spectacle_category_id']??0);
        if ($person['status']!=='approved' || !isset($byCategory[$categoryId])) continue;
        $person['registration_number']=$index+1;
        $person['schedule']=$byCategory[$categoryId];
        $recipients[]=$person;
    }
    return $recipients;
}

function scLetterMerge(string $template, array $recipient): string
{
    $schedule=$recipient['schedule'];
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',(string)$schedule['planned_distribution_date']);
    $time=(string)($schedule['planned_distribution_time']??'');
    $timeLabel='................................';
    if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)/',$time,$parts)) {
        $hour=(int)$parts[1];
        $timeLabel=($hour<12?'පෙ.ව. ':'ප.ව. ').(($hour%12)?:12).'.'.$parts[2];
    }
    return strtr($template,[
        '{{date}}'=>$date?$date->format('Y.m.d'):(string)$schedule['planned_distribution_date'],
        '{{time}}'=>$timeLabel,
        '{{place}}'=>(string)($schedule['planned_distribution_place']?:'................................'),
        '{{number}}'=>(string)$recipient['registration_number'],
    ]);
}
