<?php
declare(strict_types=1);

requireRole('subject-officer');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/approved-aid-bundle-availability.php';
$activePage = 'dashboard';

$metrics=['submitted'=>0,'beneficiaries'=>0,'releases'=>0,'returns'=>0,'approved'=>0,'pending'=>0,'rejected'=>0];
$approvedNeeds=[];
$readyBundleCount = 0;
$ssoPools = ['officers' => 0, 'deliverable' => 0, 'low_items' => 0];
try {
    $poolDb = database();
    $poolThreshold = $poolDb->query("SELECT setting_value FROM system_settings WHERE setting_key = 'low_stock_threshold' LIMIT 1")->fetchColumn();
    $poolThreshold = $poolThreshold === false ? 10 : max(0, (int) $poolThreshold);
    $poolStatement = $poolDb->prepare(
        "SELECT (SELECT COUNT(*) FROM users WHERE role = 'social-service-officer' AND status = 'active' AND ds_division_id IS NOT NULL) AS officers,
                COALESCE(SUM(GREATEST(p.allocated - p.distributed + p.reused, 0)), 0) AS deliverable,
                COALESCE(SUM(CASE WHEN GREATEST(p.allocated - p.distributed + p.reused, 0) <= :threshold THEN 1 ELSE 0 END), 0) AS low_items
         FROM division_pools p
         WHERE EXISTS (SELECT 1 FROM users u WHERE u.ds_division_id=p.ds_division_id
                       AND u.role='social-service-officer' AND u.status='active')"
    );
    $poolStatement->execute(['threshold' => $poolThreshold]);
    foreach ($poolStatement->fetch(PDO::FETCH_ASSOC) ?: [] as $key => $value) {
        $ssoPools[$key] = (int) $value;
    }
} catch (PDOException $e) {
    error_log('Subject Officer SSO pool summary unavailable: ' . $e->getMessage());
}
try {
    $bundleAvailability = widmsApprovedAidBundleAvailability(database());
    $readyBundleCount = count($bundleAvailability['available']);
    $needsByItem = [];
    foreach (['available', 'waiting'] as $availability) {
        foreach ($bundleAvailability[$availability] as $request) {
            $itemId = (int) $request['item_id'];
            $needsByItem[$itemId] ??= [
                'item_name' => $request['item_name'],
                'variety' => $request['variety'],
                'beneficiary_count' => 0,
                'quantity' => 0,
                'ready_count' => 0,
            ];
            $needsByItem[$itemId]['beneficiary_count']++;
            $needsByItem[$itemId]['quantity'] += (int) $request['quantity'];
            if ($availability === 'available') {
                $needsByItem[$itemId]['ready_count']++;
            }
        }
    }
    $approvedNeeds = array_values($needsByItem);
    usort($approvedNeeds, static fn(array $left, array $right): int =>
        $right['quantity'] <=> $left['quantity']
        ?: strcmp((string) $left['item_name'], (string) $right['item_name']));
} catch (Throwable $exception) {
    error_log('Subject Officer approved bundle summary unavailable: ' . $exception->getMessage());
}
try {
    $db = database();
    $user = (int) $_SESSION['user_id'];
    // Each submission may contain multiple item lines, but it is one request batch.
    $stmt = $db->prepare(
        "SELECT COUNT(*) AS submitted,
                COALESCE(SUM(has_approved), 0) AS approved,
                COALESCE(SUM(has_pending), 0) AS pending,
                COALESCE(SUM(has_rejected), 0) AS rejected,
                COALESCE(SUM(awaiting_dispatch), 0) AS releases
         FROM (
             SELECT COALESCE(NULLIF(request_batch_ref, ''), CONCAT('GR-', id)) AS batch_ref,
                    MAX(status IN ('approved-awaiting-dispatch', 'dispatched')) AS has_approved,
                    MAX(status = 'pending-admin-approval') AS has_pending,
                    MAX(status = 'rejected') AS has_rejected,
                    MAX(status = 'approved-awaiting-dispatch') AS awaiting_dispatch
             FROM goods_requests
             WHERE requested_by = :user
               AND destination_sso_id IS NOT NULL
               AND aid_request_id IS NULL
               AND NOT EXISTS (
                   SELECT 1 FROM goods_request_aid_requests linked
                   WHERE linked.goods_request_id = goods_requests.id
               )
             GROUP BY batch_ref
         ) AS request_batches"
    );
    $stmt->execute(['user' => $user]);
    foreach ($stmt->fetch(PDO::FETCH_ASSOC) ?: [] as $key => $value) {
        $metrics[$key] = (int) $value;
    }
} catch (PDOException $e) {
    error_log('Subject Officer quota pipeline unavailable: ' . $e->getMessage());
}
try {
    $db = database();
    $user = (int) $_SESSION['user_id'];
    $stmt = $db->prepare("SELECT COUNT(*) FROM beneficiaries b WHERE b.status='active' AND (b.ds_division_id=(SELECT ds_division_id FROM users WHERE id=:user) OR (SELECT ds_division_id FROM users WHERE id=:user2) IS NULL)");
    $stmt->execute(['user' => $user, 'user2' => $user]);
    $metrics['beneficiaries'] = (int) $stmt->fetchColumn();
    $stmt = $db->prepare("SELECT COUNT(*) FROM item_returns r JOIN distributions d ON d.id=r.distribution_id JOIN beneficiaries b ON b.id=d.beneficiary_id WHERE MONTH(r.processed_at)=MONTH(CURDATE()) AND YEAR(r.processed_at)=YEAR(CURDATE()) AND (b.ds_division_id=(SELECT ds_division_id FROM users WHERE id=:user) OR (SELECT ds_division_id FROM users WHERE id=:user2) IS NULL)");
    $stmt->execute(['user' => $user, 'user2' => $user]);
    $metrics['returns'] = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log($e->getMessage());
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Subject Officer Dashboard | SWPCS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin-dashboard.css?v=<?= filemtime(__DIR__ . '/../../public/assets/css/admin-dashboard.css') ?>" rel="stylesheet">
</head>
<body class="subject-dashboard">
    <?php require __DIR__ . '/../../includes/subject-officer-sidebar.php'; ?>

    <div class="admin-shell">
        <header class="topbar">
            <div class="d-flex align-items-center gap-3">
                <button type="button" class="menu-button" id="menu-button" aria-label="Open navigation">☰</button>
                <h1>Dashboard</h1>
            </div>
            <div class="topbar-actions">
                <label class="search-box"><span aria-hidden="true">🔍</span><input type="search" placeholder="Search anything..." aria-label="Search"></label>
                <button class="notification-button" type="button" aria-label="Notifications">🔔</button>
            </div>
        </header>

        <main class="dashboard-content admin-dashboard-page">
            <section class="stats-grid" aria-label="Subject Officer statistics">
                <a class="stat-card stat-card-link" href="dashboard.php?page=my-goods-requests"><span class="stat-icon">📋</span><p>Submitted Quota Requests</p><strong><?=$metrics['submitted']?></strong><small>Stock quota requests submitted</small><span class="stat-card-action"><?=htmlspecialchars(t('View details'),ENT_QUOTES,'UTF-8')?> <span aria-hidden="true">&#8594;</span></span></a>
                <a class="stat-card stat-card-link" href="dashboard.php?page=beneficiaries"><span class="stat-icon">🗃️</span><p>Beneficiaries in Division</p><strong><?=$metrics['beneficiaries']?></strong><small>Active beneficiary records</small><span class="stat-card-action"><?=htmlspecialchars(t('View details'),ENT_QUOTES,'UTF-8')?> <span aria-hidden="true">&#8594;</span></span></a>
                <a class="stat-card stat-card-link" href="dashboard.php?page=my-goods-requests"><span class="stat-icon">📤</span><p>Pending Quota Releases</p><strong><?=$metrics['releases']?></strong><small>Approved, awaiting Store Keeper release</small><span class="stat-card-action"><?=htmlspecialchars(t('View details'),ENT_QUOTES,'UTF-8')?> <span aria-hidden="true">&#8594;</span></span></a>
                <a class="stat-card stat-card-link" href="dashboard.php?page=returns"><span class="stat-icon">🔄</span><p>Returns This Month</p><strong><?=$metrics['returns']?></strong><small>Division return records</small><span class="stat-card-action"><?=htmlspecialchars(t('View details'),ENT_QUOTES,'UTF-8')?> <span aria-hidden="true">&#8594;</span></span></a>
            </section>

            <section class="panel subject-sso-pools-panel" aria-labelledby="subject-sso-pools-title">
                <div class="panel-header subject-sso-pools-header">
                    <div class="subject-sso-pools-heading">
                        <span class="subject-sso-pools-heading-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8.5 12 4l9 4.5v7L12 20l-9-4.5z"/><path d="m3 8.5 9 4.5 9-4.5M12 13v7"/></svg>
                        </span>
                        <div>
                            <h2 id="subject-sso-pools-title"><?= htmlspecialchars(t('DS Division Pools'), ENT_QUOTES, 'UTF-8') ?></h2>
                            <p>Stock available across active SSO divisions</p>
                        </div>
                    </div>
                    <a class="subject-sso-pools-action" href="dashboard.php?page=officer-pools">View all SSO pools <span aria-hidden="true">&#8594;</span></a>
                </div>
                <div class="subject-sso-pools-summary">
                    <div class="subject-sso-pool-metric is-officers"><span class="subject-sso-pool-metric-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="9" cy="8" r="3"/><path d="M3.5 20v-2a5.5 5.5 0 0 1 11 0v2M16 5.5a3 3 0 0 1 0 5.5M17.5 14a4.5 4.5 0 0 1 3 4.2V20"/></svg></span><div><strong><?= number_format($ssoPools['officers']) ?></strong><span>Active SSOs</span></div></div>
                    <div class="subject-sso-pool-metric is-stock"><span class="subject-sso-pool-metric-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><path d="M3 5h8v7H3zM13 5h8v7h-8zM8 14h8v7H8z"/></svg></span><div><strong><?= number_format($ssoPools['deliverable']) ?></strong><span>Deliverable units</span></div></div>
                    <a class="subject-sso-pool-metric is-low <?= $ssoPools['low_items'] > 0 ? 'is-alert' : 'is-clear' ?>" href="dashboard.php?page=officer-pools&amp;stock=low"><span class="subject-sso-pool-metric-icon" aria-hidden="true">!</span><div><strong><?= number_format($ssoPools['low_items']) ?></strong><span>Low-stock items</span></div><span class="subject-sso-pool-metric-arrow" aria-hidden="true">&#8594;</span></a>
                </div>
            </section>

            <section class="panel approved-needs-panel">
                <div class="panel-header approved-needs-header">
                    <div class="approved-needs-heading">
                        <span class="approved-needs-heading-icon" aria-hidden="true">✓</span>
                        <div>
                            <h2><?=htmlspecialchars(t('Approved Needs — Awaiting Goods'),ENT_QUOTES,'UTF-8')?></h2>
                            <p><?=htmlspecialchars(t('Approved beneficiary needs by aid item'),ENT_QUOTES,'UTF-8')?></p>
                        </div>
                    </div>
                    <div class="approved-needs-actions">
                        <span class="approved-needs-count"><?=count($approvedNeeds)?> <?=htmlspecialchars(t('aid item types'),ENT_QUOTES,'UTF-8')?></span>
                        <a class="admin-primary-action" href="dashboard.php?page=approved-aid-bundles"><?=htmlspecialchars(t('Approved Aid Bundles'),ENT_QUOTES,'UTF-8')?><?php if ($readyBundleCount > 0): ?><span class="approved-needs-ready-count" aria-label="<?= htmlspecialchars($readyBundleCount . ' ' . t('ready to bundle'), ENT_QUOTES, 'UTF-8') ?>"><?= $readyBundleCount ?></span><?php endif; ?><span aria-hidden="true">→</span></a>
                    </div>
                </div>
                <div class="operation-summary-grid p-3">
                    <?php if($approvedNeeds===[]):?><article class="approved-needs-empty"><span aria-hidden="true">✓</span><div><strong><?=htmlspecialchars(t('All caught up'),ENT_QUOTES,'UTF-8')?></strong><p><?=htmlspecialchars(t('No requests awaiting goods'),ENT_QUOTES,'UTF-8')?></p></div></article><?php else:foreach($approvedNeeds as $need):?>
                    <article class="operation-summary-card approved-need-card">
                        <div class="approved-need-topline"><span class="approved-need-badge"><?=htmlspecialchars(t('Approved'),ENT_QUOTES,'UTF-8')?></span><?php if ((int)$need['ready_count'] > 0): ?><span class="approved-need-ready"><?= (int)$need['ready_count'] ?> <?= htmlspecialchars(t('ready to bundle'), ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?></div>
                        <h3><?=htmlspecialchars(widmsAidItemName((string)$need['item_name']).($need['variety']?' — '.$need['variety']:''),ENT_QUOTES,'UTF-8')?></h3>
                        <div class="approved-need-quantity"><strong><?=(int)$need['quantity']?></strong><span><?=htmlspecialchars(t('units required'),ENT_QUOTES,'UTF-8')?></span></div>
                        <p class="approved-need-beneficiaries"><span aria-hidden="true">👥</span> <?=(int)$need['beneficiary_count']?> <?=htmlspecialchars(t('beneficiary request(s)'),ENT_QUOTES,'UTF-8')?></p>
                    </article>
                    <?php endforeach;endif;?>
                </div>
            </section>

            <section class="subject-summary-section">
                <article class="panel division-panel division-summary-panel">
                    <div class="panel-header division-summary-header">
                        <span class="division-summary-icon" aria-hidden="true">📊</span>
                        <div>
                            <h2><?=htmlspecialchars(t('My Division Summary'),ENT_QUOTES,'UTF-8')?></h2>
                            <p><?=htmlspecialchars(t('Quota requests submitted by you'),ENT_QUOTES,'UTF-8')?></p>
                        </div>
                    </div>

                    <div class="division-summary-body">
                        <div class="quota-overview-callout">
                            <span class="quota-overview-icon" aria-hidden="true">📦</span>
                            <div class="quota-overview-copy">
                                <h3><?=htmlspecialchars(t('Quota Request Overview'),ENT_QUOTES,'UTF-8')?></h3>
                                <p><?=htmlspecialchars(t('Follow your requests through Admin approval and Store Keeper dispatch.'),ENT_QUOTES,'UTF-8')?></p>
                            </div>
                            <a class="quota-history-link" href="dashboard.php?page=my-goods-requests"><?=htmlspecialchars(t('Quota Request History'),ENT_QUOTES,'UTF-8')?> <span aria-hidden="true">→</span></a>
                        </div>

                        <div class="request-pipeline">
                            <h3><?=htmlspecialchars(t('My Request Pipeline'),ENT_QUOTES,'UTF-8')?></h3>
                            <div class="request-pipeline-grid">
                                <article class="pipeline-stat pipeline-total">
                                    <span class="pipeline-stat-icon" aria-hidden="true">📄</span>
                                    <div><span><?=htmlspecialchars(t('Total Submitted'),ENT_QUOTES,'UTF-8')?></span><strong><?=$metrics['submitted']?></strong></div>
                                </article>
                                <article class="pipeline-stat pipeline-approved">
                                    <span class="pipeline-stat-icon" aria-hidden="true">✓</span>
                                    <div><span><?=htmlspecialchars(t('Approved'),ENT_QUOTES,'UTF-8')?></span><strong><?=$metrics['approved']?></strong></div>
                                </article>
                                <article class="pipeline-stat pipeline-pending">
                                    <span class="pipeline-stat-icon" aria-hidden="true">⌛</span>
                                    <div><span><?=htmlspecialchars(t('Pending'),ENT_QUOTES,'UTF-8')?></span><strong><?=$metrics['pending']?></strong></div>
                                </article>
                                <article class="pipeline-stat pipeline-rejected">
                                    <span class="pipeline-stat-icon" aria-hidden="true">×</span>
                                    <div><span><?=htmlspecialchars(t('Rejected'),ENT_QUOTES,'UTF-8')?></span><strong><?=$metrics['rejected']?></strong></div>
                                </article>
                            </div>
                        </div>
                    </div>
                </article>
            </section>
        </main>
    </div>

    <script src="assets/js/admin-dashboard.js"></script>
</body>
</html>
