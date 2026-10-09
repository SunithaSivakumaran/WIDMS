<?php
declare(strict_types=1);

$navigation = require __DIR__ . '/admin-navigation.php';
$activePage = $activePage ?? 'dashboard';
$sidebarPage = is_string($_GET['page'] ?? null) ? $_GET['page'] : $activePage;
$sidebarApprovalCounts = [];
try {
    require_once __DIR__ . '/../config/database.php';
    $sidebarApprovalCounts = adminApprovalNavigationCounts(database());
} catch (PDOException $exception) {
    error_log('Admin sidebar approval counts unavailable.');
}
$adminName = htmlspecialchars((string) $_SESSION['full_name'], ENT_QUOTES, 'UTF-8');
$profileImage = !empty($_SESSION['profile_image']) ? htmlspecialchars((string) $_SESSION['profile_image'], ENT_QUOTES, 'UTF-8') : '';
?>
<link href="assets/css/admin-approval-sidebar.css?v=1" rel="stylesheet">
<aside class="sidebar admin-role-sidebar management-role-sidebar" id="admin-sidebar">
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
            <span class="profile-avatar">
                <?= strtoupper(substr($adminName, 0, 1)) ?>
            </span>
            <?php endif; ?>

            <span>
                <strong><?= $adminName ?></strong>
                <small><?= htmlspecialchars(t('Administrator'), ENT_QUOTES, 'UTF-8') ?></small>
            </span>
    </a>

    <nav class="sidebar-nav" aria-label="Admin navigation">

        <?php renderAdminSidebarNavigation($navigation, $sidebarApprovalCounts, $sidebarPage, $_GET); ?>
    </nav>

    <!-- Translate this shared action for the selected interface language. -->
    <a class="sign-out" href="logout.php"><span aria-hidden="true">&#8592;</span><?= htmlspecialchars(t('Sign Out'), ENT_QUOTES, 'UTF-8') ?></a>
</aside>
<div class="sidebar-overlay" id="sidebar-overlay"></div>
