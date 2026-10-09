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
    $item = widmsAidItemName((string) $row['item_name']) . (!empty($row['variety']) ? ' / ' . $row['variety'] : '');
    $stockReady = (int) ($row[$direct ? 'central_available' : 'central_stock'] ?? 0) >= (int) $row['quantity'];
    $powerReady = true;
    if ($direct && !empty($row['spectacles'])) {
        $powerReady = (int) ($row['spectacle_category_id'] ?? 0) > 0
            && (int) ($row['spectacle_available'] ?? 0) >= (int) $row['quantity'];
    }
    foreach (($row['linked_aid'] ?? []) as $linked) {
        if (!empty($linked['spectacles']) && empty($linked['spectacle_matched'])) {
            $powerReady = false;
        }
    }
    $details = $direct
        ? [
            'Beneficiary' => (string) $row['beneficiary_name'],
            'DS Division' => (string) $row['division_name'],
            'Release To' => $requester,
        ]
        : [
            'District / DS Division' => (string) $row['district_name'] . ' / ' . $row['division_name'],
            'Approved By' => (string) $row['approver_name'],
            'Release To' => $requester . ' (' . t('Subject Officer') . ')',
        ];
    if ($plannedSso !== '') {
        $details['Planned SSO'] = $plannedSso;
    }
    if ($direct && !empty($row['spectacles'])) {
        $details['Spectacle Type'] = (string) ($row['spectacle_category_name'] ?? t('Not selected'));
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
        <?php if ($direct): ?>
            <?php renderAidStockComparison($item, (int) $row['quantity'], (int) $row['central_available']); ?>
            <?php if (!empty($row['spectacles'])): ?><p class="aid-bundle-id"><?= $escape(t('Spectacle Type') . ': ' . t((string) ($row['spectacle_category_name'] ?? t('Not selected')))) ?> · <?= (int) ($row['spectacle_available'] ?? 0) ?> <?= $escape(t('available')) ?></p><?php endif; ?>
        <?php elseif (!empty($row['linked_aid'])): ?>
            <div class="store-linked-beneficiaries">
                <?php foreach ($row['linked_aid'] as $linked): ?>
                    <div class="store-linked-beneficiary">
                        <strong><?= $escape($linked['beneficiary_name']) ?></strong>
                        <small><?= $escape($linked['identification'] ?: t('No identification recorded')) ?></small>
                        <?php renderAidStockComparison($item, (int) $linked['quantity'], (int) $row['central_stock']); ?>
                        <?php if (!empty($linked['spectacles'])): ?><small><?= $escape(t('Spectacle Type') . ': ' . t((string) ($linked['spectacle_category_name'] ?? t('Not selected')))) ?> · <?= (int) ($linked['spectacle_available'] ?? 0) ?> <?= $escape(t('available')) ?></small><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <?php renderAidStockComparison($item, (int) $row['quantity'], (int) $row['central_stock']); ?>
        <?php endif; ?>
        <form method="post" class="store-dispatch-card-actions">
            <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
            <input type="hidden" name="<?= $direct ? 'admin_direct_release_id' : 'request_id' ?>" value="<?= $id ?>">
            <button class="admin-primary-action" type="submit" <?= !$stockReady || !$powerReady ? 'disabled' : '' ?>><?= $escape(t($direct ? 'Release to Admin' : 'Release Stock Quota')) ?></button>
            <?php if (!$stockReady || !$powerReady): ?><small class="fulfillment-warning"><?= $escape(t('Waiting for matching Central Stock')) ?></small><?php endif; ?>
        </form>
    </article>
    <?php
}
