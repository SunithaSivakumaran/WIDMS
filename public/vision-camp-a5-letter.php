<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/permissions.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/spectacle-camp-letter.php';

requireLogin();
requireRole('subject-officer');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');

$escape=static fn(mixed $value):string=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$campId=filter_var($_GET['camp_id']??null,FILTER_VALIDATE_INT);
$editing=($_GET['edit']??'')==='1';
$error=''; $saved=false; $template=[]; $recipients=[];
try {
    if (!$campId) throw new DomainException('Select a Vision Camp.');
    $db=database();
    $actor=scActor($db,(int)$_SESSION['user_id']);
    $camp=scCamp($db,$campId,$actor);
    if ($camp['status']!=='completed') throw new DomainException('Complete the camp before preparing letters.');
    $recipients=scLetterRecipients($db,$campId);
    if ($recipients===[]) throw new DomainException('Admin must approve the camp stock request before letters can be prepared.');
    $template=scLetterTemplate($db,$campId);
    if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
        if (!$editing || !verifyCsrfToken((string)($_POST['csrf_token']??''))) throw new DomainException('Invalid or expired letter form. Refresh and try again.');
        $fields=$_POST['fields']??null;
        if (!is_array($fields)) throw new DomainException('Enter the letter wording.');
        $template=array_replace($template,array_intersect_key($fields,$template));
        $template=scLetterValidate($fields);
        scSaveLetterTemplate($db,$campId,(int)$actor['id'],$template);
        header('Location: vision-camp-a5-letter.php?camp_id='.$campId.'&edit=1&saved=1',true,303);
        exit;
    }
    if (($_SERVER['REQUEST_METHOD']??'GET')!=='GET') throw new DomainException('Invalid letter action.');
    $saved=($_GET['saved']??'')==='1';
} catch (DomainException $exception) {
    $error=$exception->getMessage();
} catch (Throwable $exception) {
    error_log('Vision Camp A5 letter failed: '.$exception->getMessage());
    $error='Unable to prepare the A5 letter. Run the latest database migration.';
}
if ($error!=='' && (!$editing || $template===[])) http_response_code(403);
$issuedOn=(new DateTimeImmutable('now',new DateTimeZone('Asia/Colombo')))->format('Y.m.d');
?>
<!doctype html>
<html lang="si"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>VC-<?= (int)$campId ?> — ඇස් කණ්ණාඩි ප්‍රදාන ලිපි</title>
<style>
@page { size:A5 portrait; margin:0; }
* { box-sizing:border-box; }
body { margin:0; background:#e9eef3; color:#17384e; font-family:"Iskoola Pota","Noto Sans Sinhala",Arial,sans-serif; }
.toolbar { display:flex; align-items:center; justify-content:center; flex-wrap:wrap; gap:9px; padding:14px; font:600 13px Arial,sans-serif; }
.toolbar a,.toolbar button,.editor-actions button { padding:9px 14px; border:1px solid #1768a9; border-radius:6px; background:#fff; color:#145e9b; text-decoration:none; cursor:pointer; font:inherit; }
.toolbar button,.editor-actions button { background:#1768a9; color:#fff; }
.message { width:min(800px,calc(100% - 24px)); margin:16px auto; padding:12px 15px; border-radius:7px; background:#fff; border:1px solid #d4a6a6; color:#a22727; }
.message.success { border-color:#9bd5ba; color:#12694b; }
.editor { width:min(820px,calc(100% - 24px)); margin:12px auto 28px; padding:24px; border:1px solid #c9d8e4; border-radius:10px; background:#fff; box-shadow:0 5px 20px #163d6012; }
.editor h1 { margin:0 0 6px; font-size:22px; }
.editor p { color:#526e82; font-size:13px; line-height:1.5; }
.editor form { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; margin-top:18px; }
.editor label { display:grid; gap:6px; color:#294c68; font-size:13px; font-weight:700; }
.editor label.wide,.editor-actions { grid-column:1/-1; }
.editor input,.editor textarea { width:100%; min-height:40px; padding:9px 11px; border:1px solid #bacddd; border-radius:6px; background:#fff; color:#17384e; font:inherit; font-weight:400; }
.editor textarea { min-height:80px; resize:vertical; line-height:1.5; }
.editor label:focus-within input,.editor label:focus-within textarea { outline:2px solid #b9dff5; }
.editor-actions { display:flex; justify-content:flex-end; }
.a5-sheet { width:148mm; height:210mm; margin:0 auto 18px; padding:4mm 6mm 4mm; display:flex; flex-direction:column; background:#fff; color:#111; box-shadow:0 6px 24px #435e7440; page-break-after:always; break-after:page; }
.a5-sheet:last-child { page-break-after:auto; break-after:auto; }
.letterhead { border-bottom:1px solid #333; text-align:center; }
.letterhead-marks { display:grid; grid-template-columns:18mm 1fr 18mm; align-items:center; height:13mm; }
.letterhead-mark { justify-self:center; display:grid; place-items:center; width:15mm; height:12mm; }
.letterhead-mark.flag { font:18pt/1 Arial,sans-serif; }
.letterhead-mark.seal { width:11mm; height:11mm; border:1px double #555; border-radius:50%; color:#333; font-size:4.5pt; line-height:1.1; }
.letterhead-mark img { width:12mm; height:12mm; object-fit:contain; }
.letterhead h1 { margin:0; font-size:6.4pt; line-height:1.25; }
.letterhead .tamil { margin:.2mm 0; font-size:5pt; line-height:1.15; }
.letterhead .english { margin:.2mm 0; font:600 5.1pt/1.15 Arial,sans-serif; }
.office-address { display:flex; justify-content:space-between; gap:2mm; margin:1mm 0; font-size:4.7pt; }
.reference { display:grid; grid-template-columns:1.2fr .8fr .7fr; border-bottom:1px solid #555; font-size:6.5pt; }
.reference span { min-height:6.5mm; padding:1mm; display:flex; align-items:center; gap:1mm; border-right:1px solid #aaa; }
.reference span:last-child { border-right:0; }
.reference small { font-size:4.6pt; line-height:1.1; }
.recipient { margin:8mm 0 1.5mm; font-size:8pt; }
.recipient strong { display:block; width:max-content; min-width:45mm; max-width:100%; padding-bottom:.4mm; border-bottom:1px dotted #777; overflow-wrap:anywhere; }
.recipient span { display:block; margin-top:1.6mm; }
.a5-sheet h2 { margin:2mm 0; font-size:9pt; text-decoration:underline; text-underline-offset:2px; }
.body-copy { font-size:7.5pt; line-height:1.42; text-align:justify; overflow-wrap:anywhere; }
.body-copy p { margin:2mm 0; }
.body-copy p.schedule { font-weight:600; }
.signoff { margin-top:6mm; font-size:7.5pt; line-height:1.4; }
.signoff span { display:block; }
.postal-address { margin-top:auto; align-self:flex-start; width:80mm; padding:3mm 2mm 2mm; border-top:1px dotted #8b969d; font-size:8pt; line-height:1.45; overflow-wrap:anywhere; }
.postal-address small { display:block; color:#515e66; font-size:6pt; }
.postal-address strong { display:block; font-size:8pt; }
.footer { display:grid; grid-template-columns:repeat(5,1fr); gap:.5mm; border-top:1px solid #555; padding-top:1mm; font:4.5pt/1.15 Arial,sans-serif; }
.footer span { border-right:1px solid #aaa; }
.footer span:last-child { border-right:0; }
@media(max-width:650px) { .editor { padding:15px; } .editor form { grid-template-columns:1fr; } .a5-sheet { margin-left:0; transform-origin:top left; } }
@media print { body { background:#fff; print-color-adjust:exact; -webkit-print-color-adjust:exact; } .toolbar,.message,.editor { display:none !important; } .a5-sheet { margin:0; box-shadow:none; } }
</style></head><body>
<nav class="toolbar"><a href="dashboard.php?page=spectacle-camp-participants&amp;camp_id=<?= (int)$campId ?>">← Registered Participants</a><?php if ($error===''): ?><a href="vision-camp-a5-letter.php?camp_id=<?= (int)$campId ?>&amp;edit=1">Edit common letter</a><a href="vision-camp-a5-letter.php?camp_id=<?= (int)$campId ?>">Preview all letters</a><?php if (!$editing): ?><button type="button" onclick="window.print()">Print all A5 letters</button><?php endif; ?><?php endif; ?></nav>
<?php if ($error!==''): ?><div class="message" role="alert"><?= $escape($error) ?></div><?php endif; ?>
<?php if ($saved): ?><div class="message success" role="status">Common A5 letter saved. The printed copies now use this wording.</div><?php endif; ?>
<?php if ($editing && $template!==[]): ?>
<main class="editor"><h1>Edit common A5 letter</h1><p>This wording is shared by all approved beneficiaries in VC-<?= (int)$campId ?>. Keep <code>{{date}}</code>, <code>{{time}}</code> and <code>{{place}}</code> in the schedule paragraph; they are filled from the approved stock request. The registration number, name and postal address are filled separately for each person.</p>
<form method="post" action="vision-camp-a5-letter.php?camp_id=<?= (int)$campId ?>&amp;edit=1">
<input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
<?php foreach (['reference'=>'Our reference','greeting'=>'Greeting','subject'=>'Subject','opening'=>'Opening paragraph','schedule'=>'Distribution date, time and place paragraph','collection'=>'Collection instructions','bring'=>'What to bring','signer_name'=>'Signatory name (optional)','signer_title'=>'Signatory title','signer_department'=>'Department / province'] as $field=>$label): $long=in_array($field,['opening','schedule','collection','bring','signer_department'],true); ?>
<label class="<?= $long?'wide':'' ?>"><?= $escape($label) ?><?php if ($long): ?><textarea name="fields[<?= $escape($field) ?>]" maxlength="<?= in_array($field,['opening','schedule','bring'],true)?750:($field==='collection'?900:300) ?>" rows="<?= $field==='collection'?4:3 ?>" required><?= $escape($template[$field]) ?></textarea><?php else: ?><input type="text" name="fields[<?= $escape($field) ?>]" maxlength="<?= $field==='reference'?100:($field==='subject'?200:150) ?>" value="<?= $escape($template[$field]) ?>" <?= $field==='signer_name'?'':'required' ?>><?php endif; ?></label>
<?php endforeach; ?><div class="editor-actions"><button type="submit">Save common letter</button></div></form></main>
<?php elseif ($error===''): ?>
<main aria-label="A5 distribution letters">
<?php foreach ($recipients as $recipient): $number=(int)$recipient['registration_number']; ?>
<article class="a5-sheet" aria-label="Letter <?= $number ?> for <?= $escape($recipient['full_name']) ?>">
    <header class="letterhead">
        <div class="letterhead-marks"><div class="letterhead-mark flag" aria-label="ශ්‍රී ලංකා ධජය">🇱🇰</div><div class="letterhead-mark seal" aria-label="ශ්‍රී ලංකා">ශ්‍රී<br>ලංකා</div><div class="letterhead-mark"><img src="assets/images/client-logo.jpeg" alt="SWPCS"></div></div>
        <h1>සමාජ සුභසාධන, පරිවාස හා ළමාරක්ෂක සේවා දෙපාර්තමේන්තුව - දකුණු පළාත</h1>
        <p class="tamil">சமூக நலன்புரி, நன்னடத்தை, சிறுவர் பராமரிப்புச் சேவைகள் திணைக்களம் - தென் மாகாணம்</p>
        <p class="english">DEPARTMENT OF SOCIAL WELFARE, PROBATION &amp; CHILDCARE SERVICES - SOUTHERN PROVINCE</p>
        <div class="office-address"><span>5 වන මහල, දිස්ත්‍රික් ලේකම් කාර්යාලය, ගාල්ල.</span><span>5th Floor, District Secretariat Office, Galle.</span></div>
    </header>
    <div class="reference"><span><small>මගේ අංකය<br>My No.</small><?= $escape($template['reference']) ?></span><span><small>ඔබේ අංකය<br>Your No.</small></span><span><small>දිනය<br>Date</small><?= $escape($issuedOn) ?></span></div>
    <div class="recipient"><strong><?= $number ?>. <?= $escape($recipient['full_name']) ?></strong><span><?= $escape($template['greeting']) ?></span></div>
    <h2><?= $escape($template['subject']) ?></h2>
    <div class="body-copy"><p><?= nl2br($escape(scLetterMerge($template['opening'],$recipient))) ?></p><p class="schedule"><?= nl2br($escape(scLetterMerge($template['schedule'],$recipient))) ?></p><p><?= nl2br($escape(scLetterMerge($template['collection'],$recipient))) ?></p><p><?= nl2br($escape(scLetterMerge($template['bring'],$recipient))) ?></p></div>
    <div class="signoff"><?php if ($template['signer_name']!==''): ?><span><?= $escape($template['signer_name']) ?></span><?php endif; ?><span><?= $escape($template['signer_title']) ?></span><span><?= nl2br($escape($template['signer_department'])) ?></span></div>
    <div class="postal-address"><small>ලැබිය යුතු තැන / To</small><strong><?= $number ?>. <?= $escape($recipient['full_name']) ?></strong><?= nl2br($escape($recipient['address'])) ?></div>
    <div class="footer"><span>කොමසාරිස්<br>Commissioner</span><span>කාර්යාලය<br>Office</span><span>ගණකාධිකාරී<br>Accountant</span><span>ෆැක්ස්<br>Fax</span><span>විද්‍යුත් තැපෑල<br>E-mail</span></div>
</article>
<?php endforeach; ?></main>
<?php endif; ?>
</body></html>
