<?php
declare(strict_types=1);

/** Shared pending-dispatch card for approved quotas and Admin direct aid. */
function renderStoreDispatchRequestCard(array $row, string $kind): void
{
    $direct = $kind === 'direct';
    $escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    $id = (int) $row['id'];
    $requestId = $direct ? (int) $row['aid_request_id'] : $id;
    $reference = ($direct ? 'AR-' : 'GR-') . str_pad((string) $requestId, 4, '0', STR_PAD_LEFT);
    $requester = (string) $row[$direct ? 'admin_name' : 'requester_name'];
    $plannedSso = !$direct ? (string) ($row['sso_name'] ?? '') : '';
    $roleClass = $direct ? 'role-admin' : ($plannedSso !== '' ? 'role-sso' : 'role-subject');
    $cardClass = $direct ? 'dispatch-to-admin' : ($plannedSso !== '' ? 'dispatch-for-sso' : 'dispatch-to-subject');
    $roleLabel = $direct ? 'Administrator' : ($plannedSso !== '' ? 'SSO Planned' : 'Subject Officer');
    $item = (string) $row['item_name'] . (!empty($row['variety']) ? ' / ' . $row['variety'] : '');
    $power = $row['prescribed_power'] !== null ? sprintf('%+.2f', (float) $row['prescribed_power']) : '—';
    $details = $direct
        ? [
            'Beneficiary' => (string) $row['beneficiary_name'],
            'DS Division' => (string) $row['division_name'],
            'Quantity' => number_format((int) $row['quantity']),
            'Stock' => number_format((int) $row['central_stock']),
            'Prescription Power' => $power,
            'Release To' => $requester,
        ]
        : [
            'Quantity' => number_format((int) $row['quantity']),
            'Stock' => number_format((int) $row['central_stock']),
            'District / DS Division' => (string) $row['district_name'] . ' / ' . $row['division_name'],
            'Approved By' => (string) $row['approver_name'],
            'Prescription Power' => $power,
            'Release To' => $requester . ' (' . t('Subject Officer') . ')',
        ];
    if ($plannedSso !== '') {
        $details['Planned SSO'] = $plannedSso;
    }
    ?>
    <article id="<?= $direct ? 'aid-request-' : 'goods-request-' ?><?= $requestId ?>" class="admin-correction-item store-dispatch-request-card <?= $cardClass ?> admin-notification-target" tabindex="-1">
        <div class="correction-summary">
            <div>
                <div class="correction-reference-line"><strong><?= $reference ?></strong><span><?= $escape($item) ?></span></div>
                <p class="correction-submission-meta"><?= $escape(t('Requested By')) ?> <strong><?= $escape($requester) ?></strong><?php if (!$direct && !empty($row['request_batch_ref'])): ?> · <?= $escape($row['request_batch_ref']) ?><?php endif; ?></p>
            </div>
            <span class="dispatch-role-badge <?= $roleClass ?>"><?= $escape(t($roleLabel)) ?></span>
        </div>
        <dl class="correction-details aid-request-card-details">
            <?php foreach ($details as $label => $value): ?>
                <div><dt><?= $escape(t($label)) ?></dt><dd><?= $escape($value) ?></dd></div>
            <?php endforeach; ?>
            <?php if (!$direct): ?>
                <div><dt><?= $escape(t('Document')) ?></dt><dd><a class="outline-action" target="_blank" rel="noopener" href="dashboard.php?page=goods-request-document&amp;request_id=<?= $id ?>&amp;print=1"><?= $escape(t('View PDF')) ?></a></dd></div>
            <?php endif; ?>
        </dl>
        <form method="post" class="store-dispatch-card-actions">
            <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
            <input type="hidden" name="<?= $direct ? 'admin_direct_release_id' : 'request_id' ?>" value="<?= $id ?>">
            <button class="admin-primary-action" type="submit"><?= $escape(t($direct ? 'Release to Admin' : 'Release Stock Quota')) ?></button>
        </form>
    </article>
    <?php
}
