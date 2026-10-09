<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/spectacle-camp-participant-export.php';

requireLogin();
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') throw new DomainException('This export is read-only.');
    $campId = filter_var($_GET['camp_id'] ?? null, FILTER_VALIDATE_INT);
    $format = (string) ($_GET['format'] ?? '');
    if (!$campId || !in_array($format, ['print', 'xlsx'], true)) throw new DomainException('Choose a valid Vision Camp export.');
    $db = database();
    $actor = scActor($db, (int) $_SESSION['user_id']);
    $data = scParticipantExportData($db, $campId, $actor);
} catch (DomainException $error) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit($error->getMessage());
} catch (Throwable $error) {
    error_log('Vision Camp participant export failed: ' . $error->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Unable to prepare the Vision Camp export.');
}

if ($format === 'xlsx') {
    try {
        $file = scParticipantWorkbook($data);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="VC-' . $campId . '-participants.xlsx"');
        header('Content-Length: ' . filesize($file));
        try { readfile($file); } finally { unlink($file); }
        exit;
    } catch (Throwable $error) {
        error_log('Vision Camp workbook failed: ' . $error->getMessage());
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('Unable to create the Excel workbook.');
    }
}

$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$camp = $data['camp'];
header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>VC-<?= (int) $campId ?> Registered Participants</title>
<style>
@page { size:A3 landscape; margin:12mm; }
* { box-sizing:border-box; }
body { margin:0; padding:28px; background:#eef3f7; font:13px/1.45 Arial,sans-serif; color:#19364b; }
.sheet { max-width:1800px; margin:auto; padding:26px; background:#fff; border:1px solid #d1dfe9; border-top:5px solid #2175ae; border-radius:10px; box-shadow:0 10px 30px rgba(19,54,79,.08); }
.toolbar { display:flex; gap:10px; margin-bottom:22px; }
.toolbar a,.toolbar button { border:1px solid #236dab; border-radius:7px; padding:9px 15px; background:#fff; color:#145e9b; font:600 13px Arial,sans-serif; text-decoration:none; cursor:pointer; }
.toolbar button { background:#1768a9; color:#fff; }
.eyebrow { display:block; margin-bottom:4px; color:#4f7793; font-size:11px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; }
h1 { margin:0 0 16px; font-size:23px; line-height:1.2; }
.meta { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:20px; }
.meta span { padding:7px 11px; border:1px solid #d6e4ee; border-radius:6px; background:#f4f9fc; }
.meta strong { color:#466780; font-weight:700; }
table { width:100%; border-collapse:collapse; table-layout:fixed; }
th,td { border:1px solid #b3c8d7; padding:9px 8px; vertical-align:top; overflow-wrap:anywhere; }
th { background:#e8f3f9; color:#173f5d; font-size:12px; text-align:left; }
td { min-height:48px; }
tbody tr:nth-child(even) { background:#f7fafc; }
td small { display:block; margin-top:4px; color:#617e93; }
.decision { display:inline-block; padding:4px 7px; border-radius:999px; font-size:11px; font-weight:700; }
.decision.approved { background:#e3f5eb; color:#116442; }
.decision.rejected { background:#fde9e9; color:#a42f2f; }
tr { break-inside:avoid; }
thead { display:table-header-group; }
.signature { height:54px; }
.people th:nth-child(1) { width:12%; }.people th:nth-child(2) { width:7%; }.people th:nth-child(3) { width:11%; }
.people th:nth-child(4) { width:16%; }.people th:nth-child(5) { width:9%; }.people th:nth-child(6) { width:10%; }
.people th:nth-child(7) { width:10%; }.people th:nth-child(8) { width:9%; }.people th:nth-child(9) { width:9%; }.people th:nth-child(10) { width:7%; }
@media print { body { padding:0; background:#fff; print-color-adjust:exact; -webkit-print-color-adjust:exact; } .sheet { max-width:none; padding:0; border:0; border-radius:0; box-shadow:none; } .toolbar { display:none; } .meta { margin-bottom:15px; } }
</style></head><body><main class="sheet">
<nav class="toolbar"><a href="dashboard.php?page=spectacle-camp-participants&amp;camp_id=<?= (int) $campId ?>">&larr; Registered Participants</a><button type="button" onclick="window.print()">Print / Save PDF</button></nav>
<span class="eyebrow">Vision Camp VC-<?= (int) $campId ?></span>
<h1>Registered Participants</h1>
<div class="meta"><span><strong>Location:</strong> <?= $escape($camp['division_name']) ?>, <?= $escape($camp['district_name']) ?></span><span><strong>Camp date:</strong> <?= $escape($camp['camp_date']) ?></span><span><strong>Completed:</strong> <?= $escape($camp['completed_at']) ?></span></div>
<table class="people"><thead><tr><th>Participant</th><th>Gender</th><th>NIC / Elder Card</th><th>Address</th><th>GN Division</th><th>Phone Number</th><th>Spectacle Type</th><th>Status</th><th>Rejection Reason</th><th>Signature</th></tr></thead><tbody>
<?php foreach ($data['participants'] as $person): ?>
<tr><td><?= $escape($person['full_name']) ?><small>SCP-<?= (int) $person['id'] ?></small></td><td><?= $escape(ucfirst((string) $person['gender'])) ?></td><td><?= $escape($person['nic'] ?: $person['elder_card_number']) ?></td><td><?= $escape($person['address']) ?></td><td><?= $escape($person['gn_name']) ?></td><td><?= $escape($person['phone'] ?: '—') ?></td><td><?= $escape($person['spectacle_type'] ?: '—') ?></td><td><span class="decision <?= $person['status'] === 'approved' ? 'approved' : 'rejected' ?>"><?= $escape($person['status'] === 'approved' ? 'Selected for spectacles' : ucfirst((string) $person['status'])) ?></span></td><td><?= $escape($person['rejection_reason'] ?: '—') ?></td><td class="signature"></td></tr>
<?php endforeach; ?>
</tbody></table>
</main></body></html>
