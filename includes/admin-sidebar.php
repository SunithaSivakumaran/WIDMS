<?php
declare(strict_types=1);

$navigation = require __DIR__ . '/admin-navigation.php';
$activePage = $activePage ?? 'dashboard';
$adminName = htmlspecialchars((string) $_SESSION['full_name'], ENT_QUOTES, 'UTF-8');
$profileImage = !empty($_SESSION['profile_image']) ? htmlspecialchars((string) $_SESSION['profile_image'], ENT_QUOTES, 'UTF-8') : '';
?>
<aside class="sidebar admin-role-sidebar management-role-sidebar" id="admin-sidebar">
    <div class="sidebar-brand">
        <img class="sidebar-logo" src="assets/images/client-logo.jpeg" alt="WIDMS logo">
        <span><strong>WIDMS</strong></span>
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
                <small>Administrator</small>
            </span>
    </a>

    <nav class="sidebar-nav" aria-label="Admin navigation">

        <?php foreach ($navigation as $section => $items): ?>
        <p class="nav-heading"><?= htmlspecialchars($section, ENT_QUOTES, 'UTF-8') ?></p>

        <?php foreach ($items as $item): ?>
        <a href="dashboard.php?page=<?= urlencode($item['page']) ?>"class="nav-link<?= $item['page'] === $activePage ? ' active' : '' ?>">
                <span class="nav-icon" aria-hidden="true"><?= $item['icon'] ?></span>
                <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
        </a>
        <?php endforeach; ?>
            
        <?php endforeach; ?>
    </nav>

    <a class="sign-out" href="logout.php">← Sign Out</a>
</aside>
<div class="sidebar-overlay" id="sidebar-overlay"></div>
