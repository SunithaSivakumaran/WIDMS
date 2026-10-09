<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/approved-aid-bundle-availability.php';
$navigation = require __DIR__ . '/subject-officer-navigation.php';
$activePage = $activePage ?? 'dashboard';
$officerName = htmlspecialchars((string) $_SESSION['full_name'], ENT_QUOTES, 'UTF-8');
$profileImage = !empty($_SESSION['profile_image']) ? htmlspecialchars((string) $_SESSION['profile_image'], ENT_QUOTES, 'UTF-8') : '';
$distributionCounts = ['distribute-items' => null, 'approved-aid-bundles' => null];
try {
    $sidebarDistributionCountStatement = database()->prepare(
        "SELECT COUNT(*) FROM goods_fulfillments f
         JOIN aid_requests ar ON ar.id = f.aid_request_id
         WHERE f.status = 'with-subject-officer'
           AND ar.status = 'approved'"
    );
    $sidebarDistributionCountStatement->execute();
    $distributionCounts['distribute-items'] = (int) $sidebarDistributionCountStatement->fetchColumn();
} catch (Throwable $exception) {
    error_log('Subject Officer distribution sidebar counts unavailable: ' . $exception->getMessage());
}
try {
    $distributionCounts['approved-aid-bundles'] = isset($readyBundleCount)
        ? (int) $readyBundleCount
        : count(widmsApprovedAidBundleAvailability(database())['available']);
} catch (Throwable $exception) {
    error_log('Subject Officer bundle sidebar count unavailable: ' . $exception->getMessage());
}
?>
<aside class="sidebar management-role-sidebar" id="admin-sidebar">
    <div class="sidebar-brand">
        <img class="sidebar-logo" src="assets/images/client-logo.jpeg" alt="SWPCS logo">
        <span class="sidebar-brand-name"><strong>SWPCS</strong></span>
        <?php renderLanguageSwitcher('sidebar-language'); ?>
        <button type="button" class="sidebar-close" id="sidebar-close" aria-label="Close navigation">&times;</button>
    </div>

    <a class="admin-profile profile-link" href="profile.php" title="Edit profile">
        <?php if ($profileImage !== ''): ?>
            <img class="profile-avatar profile-avatar-image" src="<?= $profileImage ?>" alt="">
            <?php else: ?>
            <span class="profile-avatar subject-avatar">
                <?= strtoupper(substr($officerName, 0, 1)) ?>
            </span><?php endif; ?>
            <span>
                <strong><?= $officerName ?></strong>
                <small><?= htmlspecialchars(t('Subject Officer'), ENT_QUOTES, 'UTF-8') ?></small>
            </span>
    </a>

    <nav class="sidebar-nav" aria-label="Subject Officer navigation">
        <?php foreach ($navigation as $section => $items): ?>
            <p class="nav-heading"><?= htmlspecialchars(t($section), ENT_QUOTES, 'UTF-8') ?></p>
            <?php foreach ($items as $item): ?>
                <a href="dashboard.php<?= $item['page'] === 'dashboard' ? '' : '?page=' . urlencode($item['page']) ?>"
                   class="nav-link<?= $item['page'] === $activePage ? ' active' : '' ?>"<?= $item['page'] === $activePage ? ' aria-current="page"' : '' ?>>
                    <span class="nav-icon" aria-hidden="true"><?= $item['icon'] ?></span>
                    <span class="sidebar-distribution-label"><?= htmlspecialchars(t($item['label']), ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if (array_key_exists($item['page'], $distributionCounts) && $distributionCounts[$item['page']] > 0): ?>
                        <?php $sidebarDistributionCount = $distributionCounts[$item['page']]; ?>
                        <span class="sidebar-distribution-count" aria-label="<?= htmlspecialchars(t($item['label']) . ': ' . $sidebarDistributionCount, ENT_QUOTES, 'UTF-8') ?>"><?= $sidebarDistributionCount ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <!-- Translate this shared action for the selected interface language. -->
    <a class="sign-out" href="logout.php"><span aria-hidden="true">&#8592;</span><?= htmlspecialchars(t('Sign Out'), ENT_QUOTES, 'UTF-8') ?></a>
</aside>
<div class="sidebar-overlay" id="sidebar-overlay"></div>
