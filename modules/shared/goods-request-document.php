<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

$role = (string) ($_SESSION['role'] ?? '');
$userId = (int) ($_SESSION['user_id'] ?? 0);
$allowedRoles = ['admin', 'subject-officer', 'store-keeper', 'social-service-officer'];
if (!in_array($role, $allowedRoles, true)) {
    http_response_code(403);
    exit('Access denied.');
}

$requestId = filter_input(INPUT_GET, 'request_id', FILTER_VALIDATE_INT)
    ?: filter_var($_GET['request_id'] ?? null, FILTER_VALIDATE_INT);
if (!$requestId) {
    http_response_code(400);
    exit('Invalid goods request.');
}

$statement = database()->prepare(
    "SELECT g.*, i.item_name, i.variety, c.name AS category_name,
            district.name AS district_name, ds.name AS division_name,
            requester.full_name AS requester_name, requester.role AS requester_role,
            target.full_name AS sso_name,
            approver.full_name AS approver_name,
            dispatcher.full_name AS dispatcher_name,
            recipient.full_name AS recipient_name,
            ar.id AS aid_request_id, ar.prescribed_power, ar.disability_notes,
            beneficiary.full_name AS beneficiary_name, beneficiary.nic,
            beneficiary.elders_card_number, beneficiary.address AS beneficiary_address,
            fulfillment.id AS fulfillment_id, fulfillment.lens_unit_identifier,
            fulfillment.status AS fulfillment_status,
            fulfillment.handed_to_sso_at, fulfillment.distributed_at AS fulfillment_distributed_at,
            distribution.distributed_at AS beneficiary_distributed_at,
            distributor.full_name AS beneficiary_distributor
     FROM goods_requests g
     JOIN inventory_items i ON i.id = g.item_id
     LEFT JOIN item_categories c ON c.id = i.category_id
     JOIN ds_divisions ds ON ds.id = g.destination_ds_division_id
     JOIN districts district ON district.id = ds.district_id
     JOIN users requester ON requester.id = g.requested_by
     LEFT JOIN users target ON target.id = g.destination_sso_id
     LEFT JOIN users approver ON approver.id = g.approved_by
     LEFT JOIN users dispatcher ON dispatcher.id = g.dispatched_by
     LEFT JOIN users recipient ON recipient.id = g.released_to_subject_id
     LEFT JOIN goods_request_aid_requests request_link ON request_link.goods_request_id = g.id
     LEFT JOIN aid_requests ar ON ar.id = request_link.aid_request_id
     LEFT JOIN beneficiaries beneficiary ON beneficiary.id = ar.beneficiary_id
     LEFT JOIN goods_fulfillments fulfillment
       ON fulfillment.goods_request_id = g.id AND fulfillment.aid_request_id = ar.id
     LEFT JOIN distributions distribution ON distribution.aid_request_id = ar.id
     LEFT JOIN users distributor ON distributor.id = distribution.distributed_by
     WHERE g.id = :request_id
     LIMIT 1"
);
$statement->execute(['request_id' => $requestId]);
$request = $statement->fetch();

if (!$request) {
    http_response_code(404);
    exit('Goods request not found.');
}

$authorized = $role === 'admin' || $role === 'store-keeper';
if ($role === 'subject-officer') {
    $authorized = (int) $request['requested_by'] === $userId
        || (int) ($request['released_to_subject_id'] ?? 0) === $userId;
} elseif ($role === 'social-service-officer') {
    $authorized = (int) ($request['destination_sso_id'] ?? 0) === $userId;
}
if (!$authorized) {
    http_response_code(403);
    exit('You are not allowed to view this request document.');
}

$statusLabels = [
    'pending-admin-approval' => 'Pending Admin Approval',
    'approved-awaiting-dispatch' => 'Approved - Awaiting Dispatch',
    'dispatched' => 'Dispatched',
    'rejected' => 'Rejected',
];
$documentReference = 'GR-' . str_pad((string) $requestId, 4, '0', STR_PAD_LEFT);
$backTargets = [
    'admin' => 'dashboard.php?page=reviewed-stock-quota-requests',
    'subject-officer' => 'dashboard.php?page=my-goods-requests',
    'store-keeper' => $request['status'] === 'dispatched'
        ? 'dashboard.php?page=recent-dispatches'
        : 'dashboard.php?page=approved-dispatches',
    'social-service-officer' => 'dashboard.php?page=assigned-stock-quotas',
];
$sidebarFiles = [
    'admin' => __DIR__ . '/../../includes/admin-sidebar.php',
    'subject-officer' => __DIR__ . '/../../includes/subject-officer-sidebar.php',
    'store-keeper' => __DIR__ . '/../../includes/store-keeper-sidebar.php',
    'social-service-officer' => __DIR__ . '/../../includes/social-service-officer-sidebar.php',
];
$activePage = '';

function requestDocumentDate(mixed $value): string
{
    return $value ? date('d M Y, H:i', strtotime((string) $value)) : '-';
}

if (isset($_GET['download']) || isset($_GET['print'])) {
    $autoload = __DIR__ . '/../../vendor/autoload.php';
    if (!is_file($autoload)) {
        http_response_code(503);
        exit('PDF library is unavailable. Run composer install.');
    }
    require_once $autoload;
    $escape = static fn(mixed $value): string => htmlspecialchars((string) ($value ?? '-'), ENT_QUOTES, 'UTF-8');
    $power = $request['prescribed_power'] !== null
        ? sprintf('%+.2f', (float) $request['prescribed_power']) : '-';
    $rows = [
        [t('Request Reference'), $documentReference],
        [t('Batch'), $request['request_batch_ref'] ?: '-'],
        [t('Status'), t($statusLabels[$request['status']] ?? (string) $request['status'])],
        [t('Aid Item'), trim((string) $request['item_name'] . ' ' . (string) $request['variety'])],
        [t('Quantity'), (string) $request['quantity']],
        [t('District / DS Division'), $request['district_name'] . ' / ' . $request['division_name']],
        [t('Prescribed Power'), $power],
        [t('Requested By'), $request['requester_name']],
        [t('Submitted'), requestDocumentDate($request['created_at'])],
        [t('Approved By'), $request['approver_name'] ?: '-'],
        [t('Approved'), requestDocumentDate($request['approved_at'])],
        [t('Dispatched By'), $request['dispatcher_name'] ?: '-'],
        [t('Dispatched Date'), requestDocumentDate($request['dispatched_at'])],
        [t('Released To'), $request['recipient_name'] ?: '-'],
    ];
    if ($request['aid_request_id']) {
        $rows[] = [t('Aid Request'), 'AR-' . str_pad((string) $request['aid_request_id'], 4, '0', STR_PAD_LEFT)];
        $rows[] = [t('Beneficiary'), $request['beneficiary_name']];
        $rows[] = [t('Identification'), $request['nic'] ?: $request['elders_card_number'] ?: '-'];
        $rows[] = [t('Signed Power'), $power];
        $rows[] = [t('Optical Unit ID'), $request['lens_unit_identifier'] ?: '-'];
        $rows[] = [t('Fulfillment Status'), ucwords(str_replace('-', ' ', (string) ($request['fulfillment_status'] ?: 'not released')))];
        $rows[] = [t('Distributed'), requestDocumentDate($request['beneficiary_distributed_at'] ?: $request['fulfillment_distributed_at'])];
    }
    $pdfHtml = '<!doctype html><html lang="' . $escape(widmsLanguage()) . '"><head><meta charset="utf-8"><style>'
        . 'body{font-family:DejaVu Sans,sans-serif;color:#123955;font-size:11px}'
        . 'h1{font-size:20px;margin:0;color:#075b92}h2{font-size:13px;margin:18px 0 8px;color:#075b92}'
        . '.brand{font-size:11px;font-weight:bold;letter-spacing:3px;color:#1474b8}'
        . '.head{border-bottom:3px solid #1474b8;padding-bottom:12px;margin-bottom:18px}'
        . 'table{width:100%;border-collapse:collapse}td{padding:7px 9px;border:1px solid #d8e4ec;vertical-align:top}'
        . 'td:first-child{width:32%;background:#f1f7fb;font-weight:bold}.note{padding:12px;border-left:3px solid #1474b8;background:#f1f7fb}'
        . 'footer{margin-top:20px;padding-top:9px;border-top:1px solid #d8e4ec;color:#607b90;font-size:9px}'
        . '</style></head><body><div class="head"><span class="brand">WIDMS</span><h1>'
        . $escape(t('Stock Quota and Beneficiary Release Record')) . '</h1><div>' . $escape($documentReference) . '</div></div>'
        . '<h2>' . $escape(t('Request Details')) . '</h2><table>';
    foreach ($rows as [$label, $value]) {
        $pdfHtml .= '<tr><td>' . $escape($label) . '</td><td>' . $escape($value) . '</td></tr>';
    }
    $pdfHtml .= '</table><h2>' . $escape(t('Justification')) . '</h2><div class="note">'
        . nl2br($escape($request['justification'])) . '</div>';
    if ((string) ($request['rejection_reason'] ?? '') !== '') {
        $pdfHtml .= '<h2>' . $escape(t('Admin Response')) . '</h2><div class="note">'
            . nl2br($escape($request['rejection_reason'])) . '</div>';
    }
    $pdfHtml .= '<footer>' . $escape(t('Generated')) . ': ' . date('d M Y, H:i')
        . ' &middot; ' . $escape(t('This document reflects the current WIDMS workflow record.'))
        . '</footer></body></html>';

    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', false);
    $options->set('isPhpEnabled', false);
    $options->set('defaultFont', 'DejaVu Sans');
    $pdf = new \Dompdf\Dompdf($options);
    $pdf->loadHtml($pdfHtml, 'UTF-8');
    $pdf->setPaper('A4');
    $pdf->render();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $documentReference . '.pdf"');
    echo $pdf->output();
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars(t('Goods Request Document'), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($documentReference, ENT_QUOTES, 'UTF-8') ?> | WIDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css" rel="stylesheet">
</head>
<body>
<?php require $sidebarFiles[$role]; ?>
<div class="admin-shell">
    <header class="topbar request-document-toolbar">
        <div class="d-flex align-items-center gap-3">
            <button class="menu-button" id="menu-button" type="button" aria-label="<?= htmlspecialchars(t('Open navigation'), ENT_QUOTES, 'UTF-8') ?>">&#9776;</button>
            <h1><?= htmlspecialchars(t('Goods Request Document'), ENT_QUOTES, 'UTF-8') ?></h1>
        </div>
        <div class="request-document-actions">
            <a class="outline-action" href="<?= htmlspecialchars($backTargets[$role], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t('Back'), ENT_QUOTES, 'UTF-8') ?></a>
            <button class="admin-primary-action" type="button" onclick="window.print()"><?= htmlspecialchars(t('Print / Save PDF'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
    </header>
    <main class="dashboard-content request-document-page">
        <article class="request-document-sheet">
            <header class="request-document-heading">
                <div>
                    <span class="request-document-brand">WIDMS</span>
                    <h2><?= htmlspecialchars(t('Stock Quota and Beneficiary Release Record'), ENT_QUOTES, 'UTF-8') ?></h2>
                    <p><?= htmlspecialchars(t('Official workflow record generated from the live system.'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div class="request-document-reference">
                    <small><?= htmlspecialchars(t('Request Reference'), ENT_QUOTES, 'UTF-8') ?></small>
                    <strong><?= htmlspecialchars($documentReference, ENT_QUOTES, 'UTF-8') ?></strong>
                    <span><?= htmlspecialchars(t($statusLabels[$request['status']] ?? (string) $request['status']), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </header>

            <section class="request-document-section">
                <h3><?= htmlspecialchars(t('Request Details'), ENT_QUOTES, 'UTF-8') ?></h3>
                <dl class="request-document-grid">
                    <div><dt><?= htmlspecialchars(t('Batch'), ENT_QUOTES, 'UTF-8') ?></dt><dd><?= htmlspecialchars((string) ($request['request_batch_ref'] ?: '-'), ENT_QUOTES, 'UTF-8') ?></dd></div>
                    <div><dt><?= htmlspecialchars(t('Aid Item'), ENT_QUOTES, 'UTF-8') ?></dt><dd><?= htmlspecialchars(trim((string) $request['item_name'] . ((string) $request['variety'] !== '' ? ' - ' . $request['variety'] : '')), ENT_QUOTES, 'UTF-8') ?></dd></div>
                    <div><dt><?= htmlspecialchars(t('Quantity'), ENT_QUOTES, 'UTF-8') ?></dt><dd><?= number_format((int) $request['quantity']) ?></dd></div>
                    <div><dt><?= htmlspecialchars(t('Prescribed Power'), ENT_QUOTES, 'UTF-8') ?></dt><dd><?= $request['prescribed_power'] !== null ? sprintf('%+.2f', (float) $request['prescribed_power']) : '-' ?></dd></div>
                    <div><dt><?= htmlspecialchars(t('District / DS Division'), ENT_QUOTES, 'UTF-8') ?></dt><dd><?= htmlspecialchars($request['district_name'] . ' / ' . $request['division_name'], ENT_QUOTES, 'UTF-8') ?></dd></div>
                    <div><dt><?= htmlspecialchars(t('Receiving Officer'), ENT_QUOTES, 'UTF-8') ?></dt><dd><?= htmlspecialchars((string) ($request['sso_name'] ?: $request['recipient_name'] ?: t('Subject Officer direct release')), ENT_QUOTES, 'UTF-8') ?></dd></div>
                </dl>
                <div class="request-document-note"><strong><?= htmlspecialchars(t('Justification'), ENT_QUOTES, 'UTF-8') ?></strong><p><?= nl2br(htmlspecialchars((string) $request['justification'], ENT_QUOTES, 'UTF-8')) ?></p></div>
            </section>

            <?php if ($request['aid_request_id']): ?>
                <section class="request-document-section">
                    <h3><?= htmlspecialchars(t('Beneficiary and Optical Details'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <dl class="request-document-grid">
                        <div><dt><?= htmlspecialchars(t('Aid Request'), ENT_QUOTES, 'UTF-8') ?></dt><dd>AR-<?= str_pad((string) $request['aid_request_id'], 4, '0', STR_PAD_LEFT) ?></dd></div>
                        <div><dt><?= htmlspecialchars(t('Beneficiary'), ENT_QUOTES, 'UTF-8') ?></dt><dd><?= htmlspecialchars((string) $request['beneficiary_name'], ENT_QUOTES, 'UTF-8') ?></dd></div>
                        <div><dt><?= htmlspecialchars(t('Identification'), ENT_QUOTES, 'UTF-8') ?></dt><dd><?= htmlspecialchars((string) ($request['nic'] ?: $request['elders_card_number'] ?: t('Not provided')), ENT_QUOTES, 'UTF-8') ?></dd></div>
                        <div><dt><?= htmlspecialchars(t('Signed Power'), ENT_QUOTES, 'UTF-8') ?></dt><dd><?= $request['prescribed_power'] !== null ? sprintf('%+.2f', (float) $request['prescribed_power']) : '-' ?></dd></div>
                        <div><dt><?= htmlspecialchars(t('Optical Unit ID'), ENT_QUOTES, 'UTF-8') ?></dt><dd><?= htmlspecialchars((string) ($request['lens_unit_identifier'] ?: '-'), ENT_QUOTES, 'UTF-8') ?></dd></div>
                        <div><dt><?= htmlspecialchars(t('Fulfillment Status'), ENT_QUOTES, 'UTF-8') ?></dt><dd><?= htmlspecialchars(t(ucwords(str_replace('-', ' ', (string) ($request['fulfillment_status'] ?: 'not released')))), ENT_QUOTES, 'UTF-8') ?></dd></div>
                    </dl>
                    <?php if ((string) $request['beneficiary_address'] !== ''): ?><div class="request-document-note"><strong><?= htmlspecialchars(t('Beneficiary Address'), ENT_QUOTES, 'UTF-8') ?></strong><p><?= htmlspecialchars((string) $request['beneficiary_address'], ENT_QUOTES, 'UTF-8') ?></p></div><?php endif; ?>
                </section>
            <?php endif; ?>

            <section class="request-document-section">
                <h3><?= htmlspecialchars(t('Approval and Release Trail'), ENT_QUOTES, 'UTF-8') ?></h3>
                <div class="request-document-timeline">
                    <div><span>1</span><strong><?= htmlspecialchars(t('Submitted'), ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars((string) $request['requester_name'], ENT_QUOTES, 'UTF-8') ?><br><?= requestDocumentDate($request['created_at']) ?></small></div>
                    <div><span>2</span><strong><?= htmlspecialchars(t($request['status'] === 'rejected' ? 'Reviewed' : 'Approved'), ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars((string) ($request['approver_name'] ?: '-'), ENT_QUOTES, 'UTF-8') ?><br><?= requestDocumentDate($request['approved_at']) ?></small></div>
                    <div><span>3</span><strong><?= htmlspecialchars(t('Released'), ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars((string) ($request['dispatcher_name'] ?: '-'), ENT_QUOTES, 'UTF-8') ?><br><?= requestDocumentDate($request['dispatched_at']) ?></small></div>
                    <div><span>4</span><strong><?= htmlspecialchars(t('Distributed'), ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars((string) ($request['beneficiary_distributor'] ?: '-'), ENT_QUOTES, 'UTF-8') ?><br><?= requestDocumentDate($request['beneficiary_distributed_at'] ?: $request['fulfillment_distributed_at']) ?></small></div>
                </div>
                <?php if ((string) ($request['rejection_reason'] ?? '') !== ''): ?><div class="request-document-note is-rejected"><strong><?= htmlspecialchars(t('Admin Response'), ENT_QUOTES, 'UTF-8') ?></strong><p><?= nl2br(htmlspecialchars((string) $request['rejection_reason'], ENT_QUOTES, 'UTF-8')) ?></p></div><?php endif; ?>
            </section>

            <footer class="request-document-footer">
                <span><?= htmlspecialchars(t('Generated'), ENT_QUOTES, 'UTF-8') ?>: <?= date('d M Y, H:i') ?></span>
                <span><?= htmlspecialchars(t('This document reflects the current WIDMS workflow record.'), ENT_QUOTES, 'UTF-8') ?></span>
            </footer>
        </article>
    </main>
</div>
<script src="assets/js/admin-dashboard.js"></script>
<?php if (isset($_GET['print'])): ?><script>window.addEventListener('load', () => window.print());</script><?php endif; ?>
</body>
</html>
