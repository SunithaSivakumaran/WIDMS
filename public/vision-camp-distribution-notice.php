<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/permissions.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/spectacle-camps.php';

requireLogin();
requireRole('subject-officer');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');

try {
    if (($_SERVER['REQUEST_METHOD']??'GET')!=='GET') throw new DomainException('This notice is read-only.');
    $campId=filter_var($_GET['camp_id']??null,FILTER_VALIDATE_INT);
    $participantId=filter_var($_GET['participant_id']??null,FILTER_VALIDATE_INT);
    if (!$campId || !$participantId) throw new DomainException('Select a registered participant.');
    $db=database();
    $actor=scActor($db,(int)$_SESSION['user_id']);
    $camp=scCamp($db,$campId,$actor);
    if ($camp['status']!=='completed') throw new DomainException('Complete the camp before printing notices.');
    $person=scQuery($db,'SELECT p.*,category.name spectacle_type,ds.name division_name,d.name district_name
        FROM spectacle_camp_participants p
        JOIN spectacle_categories category ON category.id=p.spectacle_category_id
        JOIN ds_divisions ds ON ds.id=p.ds_division_id
        JOIN districts d ON d.id=p.district_id
        WHERE p.id=? AND p.camp_id=? AND p.status=\'approved\' LIMIT 1',[$participantId,$campId])->fetch(PDO::FETCH_ASSOC);
    if (!$person) throw new DomainException('Only selected beneficiaries can receive a distribution notice.');
    $schedule=scQuery($db,"SELECT planned_distribution_date,planned_distribution_time,planned_distribution_place
        FROM spectacle_camp_stock_requests
        WHERE camp_id=? AND spectacle_category_id=? AND status IN ('approved','released')
        ORDER BY id DESC LIMIT 1",[$campId,$person['spectacle_category_id']])->fetch(PDO::FETCH_ASSOC);
    if (!$schedule) throw new DomainException('The camp stock request must be approved before printing this notice.');
} catch (DomainException $error) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit($error->getMessage());
} catch (Throwable $error) {
    error_log('Vision Camp distribution notice failed: '.$error->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Unable to prepare the distribution notice.');
}

$escape=static fn(mixed $value):string=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$plannedDate=DateTimeImmutable::createFromFormat('!Y-m-d',(string)$schedule['planned_distribution_date']);
$dateLabel=$plannedDate?$plannedDate->format('Y.m.d'):(string)$schedule['planned_distribution_date'];
$plannedTime=(string)($schedule['planned_distribution_time']??'');
$distributionPlace=trim((string)($schedule['planned_distribution_place']??''));
$placeLabel=$distributionPlace!=='' ? $distributionPlace.' දී' : $person['division_name'].' ප්‍රාදේශීය ලේකම් කොට්ඨාසයේදී';
$timeLabel='................................';
if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)/',$plannedTime,$timeParts)) {
    $hour=(int)$timeParts[1];
    $timeLabel=($hour<12?'පෙ.ව. ':'ප.ව. ').(($hour%12)?:12).'.'.$timeParts[2];
}
$issuedOn=(new DateTimeImmutable('now',new DateTimeZone('Asia/Colombo')))->format('Y.m.d');
header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="si"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>VC-<?= (int)$campId ?> / SCP-<?= (int)$participantId ?> — ඇස් කණ්ණාඩි ප්‍රදානය</title>
<style>
@page { size:A4 portrait; margin:0; }
* { box-sizing:border-box; }
body { margin:0; background:#e9eef2; color:#162f40; font-family:"Iskoola Pota","Noto Sans Sinhala",Arial,sans-serif; }
.toolbar { display:flex; justify-content:center; gap:10px; padding:16px; font:600 13px Arial,sans-serif; }
.toolbar a,.toolbar button { padding:9px 14px; border:1px solid #236dab; border-radius:6px; background:#fff; color:#145e9b; text-decoration:none; cursor:pointer; }
.toolbar button { background:#1768a9; color:#fff; }
.sheet { width:210mm; height:297mm; margin:0 auto 25px; background:#fff; box-shadow:0 8px 30px #72839155; }
.notice { height:148.5mm; padding:5mm 10mm 3mm; overflow:hidden; border-bottom:1px dashed #c7d0d5; display:flex; flex-direction:column; }
.postal-half { position:relative; height:148.5mm; padding:10mm 11mm; }
.postal-stamp { position:absolute; top:12mm; right:13mm; width:27mm; height:24mm; border:1px dashed #a5b2ba; display:grid; place-items:center; color:#8a9aa4; font-size:7pt; text-align:center; }
.postal-address { position:absolute; top:51mm; left:82mm; width:110mm; min-height:47mm; padding:5mm 6mm; color:#162f40; font-size:11pt; line-height:1.6; overflow-wrap:anywhere; }
.postal-address small { display:block; margin-bottom:3mm; color:#526876; font-size:8pt; }
.postal-address strong { font-size:12pt; }
.postal-return { position:absolute; left:11mm; bottom:11mm; max-width:70mm; color:#526876; font-size:7.5pt; line-height:1.45; }
.postal-fold { position:absolute; top:0; left:11mm; right:11mm; border-top:1px dashed #b8c5cc; }
.letterhead { border-bottom:1px solid #333; text-align:center; color:#111; }
.letterhead-marks { display:grid; grid-template-columns:22mm 1fr 22mm; align-items:center; height:15mm; }
.letterhead-mark { width:16mm; height:14mm; justify-self:center; display:grid; place-items:center; color:#333; }
.letterhead-mark.flag { font:20pt/1 Arial,sans-serif; }
.letterhead-mark.seal { border:1.2px double #555; border-radius:50%; width:13mm; height:13mm; font-size:5pt; line-height:1.1; text-align:center; }
.letterhead-mark img { width:14mm; height:14mm; object-fit:contain; }
.letterhead h1 { margin:0; font-size:8pt; line-height:1.25; font-weight:700; }
.letterhead .tamil { margin:0; font-size:6.3pt; line-height:1.2; }
.letterhead .english { margin:.2mm 0 0; font:600 6.3pt/1.2 Arial,sans-serif; }
.office-address { display:flex; justify-content:space-between; gap:3mm; margin:1mm 0 .8mm; color:#333; font-size:5.5pt; line-height:1.2; }
.reference { display:grid; grid-template-columns:1.15fr .9fr .7fr; border-bottom:1px solid #333; color:#111; font-size:7.2pt; }
.reference span { min-height:7mm; padding:1mm 2mm; border-right:1px solid #888; display:flex; align-items:center; gap:2mm; }
.reference span:last-child { border-right:0; }
.reference small { display:block; min-width:14mm; font-size:5.7pt; line-height:1.15; }
.recipient { margin-top:4mm; color:#111; font-size:8.5pt; line-height:1.25; }
.recipient-name { display:inline-block; min-width:55mm; padding-bottom:.4mm; border-bottom:1px dotted #777; font-weight:600; }
.salutation { margin:1.2mm 0 0; font-size:8pt; }
h2 { margin:2mm 0 2mm; color:#111; font-size:9.2pt; text-decoration:underline; text-underline-offset:2px; }
.body-copy { margin:0; color:#111; font-size:7.6pt; line-height:1.42; text-align:justify; }
.body-copy p { margin:1.4mm 0; }
.body-copy strong { font-weight:700; text-decoration:underline dotted; text-underline-offset:2px; }
.signoff { margin-top:auto; padding:2mm 0 1.2mm; color:#111; font-size:7.5pt; line-height:1.35; }
.signoff .signature { display:block; width:53mm; margin-bottom:1mm; border-bottom:1px dotted #777; }
.footer { display:grid; grid-template-columns:repeat(5,1fr); gap:1mm; border-top:1px solid #444; padding-top:1mm; color:#333; font:5.3pt/1.2 Arial,sans-serif; }
.footer span { min-height:3mm; border-right:1px solid #aaa; }
.footer span:last-child { border-right:0; }
@media print { body { background:#fff; print-color-adjust:exact; -webkit-print-color-adjust:exact; } .toolbar { display:none; } .sheet { margin:0; box-shadow:none; } .notice { border-bottom:0; } }
</style></head><body>
<nav class="toolbar"><a href="dashboard.php?page=spectacle-camp-participants&amp;camp_id=<?= (int)$campId ?>">← Registered Participants</a><button type="button" onclick="window.print()">Print / Save PDF</button></nav>
<main class="sheet"><section class="notice" aria-label="සිංහල ඇස් කණ්ණාඩි ප්‍රදාන දැනුම්දීම">
    <header class="letterhead">
        <div class="letterhead-marks"><div class="letterhead-mark flag" aria-label="ශ්‍රී ලංකා ධජය">🇱🇰</div><div class="letterhead-mark seal" aria-label="ශ්‍රී ලංකා">ශ්‍රී<br>ලංකා</div><div class="letterhead-mark"><img src="assets/images/client-logo.jpeg" alt="SWPCS"></div></div>
        <h1>සමාජ සුභසාධන, පරිවාස හා ළමාරක්ෂක සේවා දෙපාර්තමේන්තුව - දකුණු පළාත</h1>
        <p class="tamil">சமூக நலன்புரி, நன்னடத்தை, சிறுவர் பராமரிப்புச் சேவைகள் திணைக்களம் - தென் மாகாணம்</p>
        <p class="english">DEPARTMENT OF SOCIAL WELFARE, PROBATION &amp; CHILDCARE SERVICES - SOUTHERN PROVINCE</p>
        <div class="office-address"><span>5 වන මහල, දිස්ත්‍රික් ලේකම් කාර්යාලය, ගාල්ල.</span><span>5th Floor, District Secretariat Office, Galle.</span></div>
    </header>
    <div class="reference"><span><small>මගේ අංකය<br>My No.</small>SWPCS/05/07/02</span><span><small>ඔබේ අංකය<br>Your No.</small></span><span><small>දිනය<br>Date</small><?= $escape($issuedOn) ?></span></div>
    <div class="recipient"><span class="recipient-name"><?= $escape($person['full_name']) ?></span><br><span class="salutation">මහත්මයාණෙනි / මහත්මියනි,</span></div>
    <h2>ඇස් කණ්ණාඩි ප්‍රදානය කිරීම.</h2>
    <div class="body-copy">
        <p>දකුණු පළාත් සමාජ සුභසාධන දෙපාර්තමේන්තුව මගින් පවත්වන ලද අක්ෂි සායනයට සහභාගී වූ ඔබ වෙනුවෙන් ඇස් කණ්ණාඩි යුගලයක් ප්‍රදානය කිරීමට කටයුතු කර ඇති බව සතුටින් දන්වමි.</p>
        <p>02. එම ඇස් කණ්ණාඩි යුගලය <strong><?= $escape($dateLabel) ?></strong> දින <strong><?= $escape($timeLabel) ?></strong> ට <strong><?= $escape($placeLabel) ?></strong> ඔබට ලබා දීමට කටයුතු සූදානම් කර ඇත.</p>
        <p>03. එම අවස්ථාවට සහභාගී වී ඉහත සඳහන් ඔබට හිමි අංකයට අනුව මෙම ලේඛනයේ අත්සන් කර, අදාළ ඇස් කණ්ණාඩි යුගලය ලබා ගන්නා ලෙස කාරුණිකව දන්වමි. තවද නියමිත වේලාවට පමණක්ම ඇස් කණ්ණාඩි නිකුත් කරනු ලැබේ.</p>
        <p>04. ඔබ පැමිණෙන විට කරුණාකර මෙම ලිපිය සහ ජාතික හැඳුනුම්පත රැගෙන එන ලෙස කාරුණිකව දන්වමි.</p>
    </div>
    <div class="signoff"><span class="signature" aria-label="අත්සන"></span>පරිපාලන නිලධාරී,<br>සමාජ සුභසාධන පරිවාස හා ළමාරක්ෂක සේවා දෙපාර්තමේන්තුව,<br>දකුණු පළාත.</div>
    <div class="footer"><span>කොමසාරිස්<br>Commissioner</span><span>කාර්යාලය<br>Office</span><span>ගණකාධිකාරී<br>Accountant</span><span>ෆැක්ස්<br>Fax</span><span>විද්‍යුත් තැපෑල<br>E-mail</span></div>
</section><section class="postal-half" aria-label="තැපැල් ලිපිනය"><div class="postal-fold"></div><div class="postal-stamp">මුද්දරය<br>Stamp</div><div class="postal-address"><small>ලැබිය යුතු තැන / To</small><strong><?= $escape($person['full_name']) ?></strong><br><?= nl2br($escape($person['address'])) ?></div><div class="postal-return">ආපසු ලිපිනය / From<br>සමාජ සුභසාධන, පරිවාස හා ළමාරක්ෂක සේවා දෙපාර්තමේන්තුව<br>දකුණු පළාත</div></section></main>
</body></html>
