<?php
declare(strict_types=1);

if (!function_exists('renderCorrectionRequestHistory')) {
    /**
     * Render the shared correction-request history table.
     */
    function renderCorrectionRequestHistory(array $requests, array $errorTypes): void
    {
        ?>
        <section class="correction-history-component">
            <div class="correction-history-filters" role="search" aria-label="<?= htmlspecialchars(t('Filter correction request history'), ENT_QUOTES, 'UTF-8') ?>">
                <label for="correction-history-search"><span><?= htmlspecialchars(t('Search'), ENT_QUOTES, 'UTF-8') ?></span><input type="search" id="correction-history-search" placeholder="<?= htmlspecialchars(t('Search reference, error or response'), ENT_QUOTES, 'UTF-8') ?>"></label>
                <label for="correction-history-status"><span><?= htmlspecialchars(t('Status'), ENT_QUOTES, 'UTF-8') ?></span><select id="correction-history-status"><option value=""><?= htmlspecialchars(t('All statuses'), ENT_QUOTES, 'UTF-8') ?></option><option value="pending"><?= htmlspecialchars(t('Pending'), ENT_QUOTES, 'UTF-8') ?></option><option value="approved"><?= htmlspecialchars(t('Done'), ENT_QUOTES, 'UTF-8') ?></option><option value="rejected"><?= htmlspecialchars(t('Rejected'), ENT_QUOTES, 'UTF-8') ?></option></select></label>
                <label for="correction-history-type"><span><?= htmlspecialchars(t('Error Type'), ENT_QUOTES, 'UTF-8') ?></span><select id="correction-history-type"><option value=""><?= htmlspecialchars(t('All error types'), ENT_QUOTES, 'UTF-8') ?></option><?php foreach ($errorTypes as $type => $label): ?><option value="<?= htmlspecialchars((string) $type, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t((string) $label), ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label>
            </div>
            <div class="correction-table-wrap store-table-card">
                <table class="correction-table">
                    <thead>
                    <tr><th><?= htmlspecialchars(t('ID'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Record Ref'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Error Type'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Proposed Fix'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Submitted'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Status'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(t('Admin Response'), ENT_QUOTES, 'UTF-8') ?></th></tr>
                    </thead>
                    <tbody>
                    <?php if ($requests === []): ?>
                        <tr><td colspan="7" class="empty-table"><?= htmlspecialchars(t('No correction requests submitted yet.'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($requests as $request): ?>
                            <tr class="correction-history-row" data-status="<?= htmlspecialchars((string) $request['status'], ENT_QUOTES, 'UTF-8') ?>" data-error-type="<?= htmlspecialchars((string) $request['error_type'], ENT_QUOTES, 'UTF-8') ?>">
                                <td>CR-<?= str_pad((string) $request['id'], 3, '0', STR_PAD_LEFT) ?></td>
                                <td><?= htmlspecialchars((string) $request['record_reference'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars(t((string) ($errorTypes[$request['error_type']] ?? $request['error_type'])), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $request['proposed_correction'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= date('d M Y', strtotime((string) $request['created_at'])) ?></td>
                                <td><span class="correction-status <?= htmlspecialchars((string) $request['status'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(t($request['status'] === 'approved' ? 'Done' : ucfirst((string) $request['status'])), ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td class="admin-response"><?= htmlspecialchars((string) ($request['admin_reason'] ?: ($request['status'] === 'pending' ? 'Awaiting admin review' : '—')), ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr id="correction-history-no-results" hidden><td colspan="7" class="empty-table"><?= htmlspecialchars(t('No correction requests match these filters.'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <script>
        (() => {
            const search = document.getElementById('correction-history-search');
            const status = document.getElementById('correction-history-status');
            const type = document.getElementById('correction-history-type');
            const rows = [...document.querySelectorAll('.correction-history-row')];
            const empty = document.getElementById('correction-history-no-results');
            if (!search || !status || !type || !empty) return;
            const filter = () => {
                const term = search.value.trim().toLocaleLowerCase();
                let visible = 0;
                for (const row of rows) {
                    row.hidden = (status.value !== '' && row.dataset.status !== status.value)
                        || (type.value !== '' && row.dataset.errorType !== type.value)
                        || (term !== '' && !row.textContent.toLocaleLowerCase().includes(term));
                    if (!row.hidden) visible++;
                }
                empty.hidden = visible !== 0;
            };
            search.addEventListener('input', filter);
            status.addEventListener('change', filter);
            type.addEventListener('change', filter);
        })();
        </script>
        <?php
    }
}
