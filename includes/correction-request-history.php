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
            <div class="correction-history-heading"><h2>My Correction Requests</h2></div>
            <div class="correction-table-wrap store-table-card">
                <table class="correction-table">
                    <thead>
                    <tr><th>ID</th><th>Record Ref</th><th>Error Type</th><th>Proposed Fix</th><th>Submitted</th><th>Status</th><th>Admin Response</th></tr>
                    </thead>
                    <tbody>
                    <?php if ($requests === []): ?>
                        <tr><td colspan="7" class="empty-table">No correction requests submitted yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td>CR-<?= str_pad((string) $request['id'], 3, '0', STR_PAD_LEFT) ?></td>
                                <td><?= htmlspecialchars((string) $request['record_reference'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($errorTypes[$request['error_type']] ?? $request['error_type']), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $request['proposed_correction'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= date('d M Y', strtotime((string) $request['created_at'])) ?></td>
                                <td><span class="correction-status <?= htmlspecialchars((string) $request['status'], ENT_QUOTES, 'UTF-8') ?>"><?= $request['status'] === 'approved' ? 'Done' : ucfirst((string) $request['status']) ?></span></td>
                                <td class="admin-response"><?= htmlspecialchars((string) ($request['admin_reason'] ?: ($request['status'] === 'pending' ? 'Awaiting admin review' : '—')), ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php
    }
}
