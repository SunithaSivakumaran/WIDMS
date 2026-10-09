<?php
declare(strict_types=1);

/** Shared queue definitions for sidebar links, counts and tests. */
function adminApprovalNavigationItems(): array
{
    return [
        ['icon'=>'&#128100;', 'label'=>'User Registration Requests', 'page'=>'pending-approvals', 'count_key'=>'registrations'],
        ['icon'=>'&#128203;', 'label'=>'Pending Aid Requests', 'page'=>'item-requests', 'query'=>['view'=>'pending'], 'count_key'=>'aid'],
        ['icon'=>'&#128230;', 'label'=>'Stock Quota Requests', 'page'=>'goods-requests', 'count_key'=>'stock'],
        ['icon'=>'&#9998;', 'label'=>'Correction Requests', 'page'=>'correction-requests', 'count_key'=>'corrections'],
        ['icon'=>'&#128083;', 'label'=>'Vision Camp Requests', 'page'=>'pending-approvals', 'query'=>['tab'=>'vision-camps'], 'count_key'=>'vision-camps'],
        ['icon'=>'&#128230;', 'label'=>'Vision Camp Stock Requests', 'page'=>'spectacle-camp-stock-approvals', 'count_key'=>'camp-stock'],
    ];
}

function adminApprovalNavigationCounts(PDO $db): array
{
    $queries = [
        'registrations'=>"SELECT COUNT(*) FROM registration_requests WHERE status='pending'",
        'aid'=>"SELECT COUNT(*) FROM aid_requests WHERE status='pending'",
        'stock'=>"SELECT COUNT(DISTINCT COALESCE(NULLIF(request_batch_ref, ''), CONCAT('GR-', id))) FROM goods_requests WHERE status='pending-admin-approval'",
        'corrections'=>"SELECT COUNT(*) FROM correction_requests WHERE status='pending'",
        'vision-camps'=>"SELECT COUNT(*) FROM spectacle_camps WHERE status='pending'",
        'camp-stock'=>"SELECT COUNT(DISTINCT COALESCE(r.batch_ref,CONCAT('legacy-',r.id))) FROM spectacle_camp_stock_requests r JOIN spectacle_camps c ON c.id=r.camp_id WHERE r.status='pending' AND c.status='completed'",
    ];
    $counts = [];
    foreach ($queries as $key=>$sql) {
        try { $counts[$key] = (int) $db->query($sql)->fetchColumn(); }
        catch (PDOException $exception) {
            $counts[$key] = null;
            error_log('Admin approval count unavailable: '.$key);
        }
    }
    return $counts;
}

function adminNavigationItemActive(array $item, string $page, array $query): bool
{
    // Retain old bookmarks without displaying a second Camp Approvals entry.
    if ($page === 'spectacle-camp-approvals') {
        $page = 'pending-approvals';
        $query['tab'] = 'vision-camps';
    }
    if ($item['page'] !== $page) return false;
    if ($page === 'pending-approvals') {
        $tab = ($query['tab'] ?? '') === 'vision-camps' ? 'vision-camps' : '';
        return ($item['query']['tab'] ?? '') === $tab;
    }
    if ($page === 'item-requests') {
        $view = ($query['view'] ?? '') === 'pending' ? 'pending' : '';
        return ($item['query']['view'] ?? '') === $view;
    }
    return true;
}

function renderAdminSidebarNavigation(array $navigation, array $counts, string $page, array $query): void
{
    foreach ($navigation as $section=>$items): ?>
        <p class="nav-heading"><?= htmlspecialchars(t($section), ENT_QUOTES, 'UTF-8') ?></p>
        <?php foreach ($items as $item):
            $active = adminNavigationItemActive($item, $page, $query);
            $url = 'dashboard.php?' . http_build_query(['page'=>$item['page']] + ($item['query'] ?? []), '', '&', PHP_QUERY_RFC3986);
        ?>
        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" class="nav-link<?= $active ? ' active' : '' ?>"<?= $active ? ' aria-current="page"' : '' ?>>
            <span class="nav-icon" aria-hidden="true"><?= $item['icon'] ?></span>
            <span class="admin-nav-label"><?= htmlspecialchars(t($item['label']), ENT_QUOTES, 'UTF-8') ?></span>
            <?php if (isset($item['count_key'])): $count = $counts[$item['count_key']] ?? null; ?>
            <span class="sidebar-request-count" data-approval-count="<?= htmlspecialchars($item['count_key'], ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($count === null ? t('Count unavailable') : t('Pending') . ': ' . $count, ENT_QUOTES, 'UTF-8') ?>"><?= $count === null ? '—' : (int) $count ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach;
    endforeach;
}
