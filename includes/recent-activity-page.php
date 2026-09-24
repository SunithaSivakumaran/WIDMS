<?php

declare(strict_types=1);

require_once __DIR__ . '/activity.php';

function renderRecentActivityPage(string $role, string $sidebarFile): void
{
    $activities = recentActivities($role === 'admin' ? null : (int) $_SESSION['user_id'], 50);
    $activePage = 'recent-activity';
    $bodyClass = match ($role) {
        'admin' => 'admin-recent-activity-page',
        'store-keeper' => 'store-page store-recent-activity-page',
        'subject-officer' => 'subject-recent-activity-page',
        default => 'social-officer-dashboard social-recent-activity-page',
    };
    ?>
    <!doctype html>
    <html lang="<?= htmlspecialchars(widmsLanguage(), ENT_QUOTES, 'UTF-8') ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= htmlspecialchars(t('Recent Activity'), ENT_QUOTES, 'UTF-8') ?> | WIDMS</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="assets/css/admin-dashboard.css?v=90" rel="stylesheet">
    </head>
    <body class="<?= htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') ?> widms-unified-ui">
    <?php require $sidebarFile; ?>
    <div class="admin-shell">
        <header class="topbar">
            <div class="d-flex align-items-center gap-3">
                <button type="button" class="menu-button" id="menu-button" aria-label="<?= htmlspecialchars(t('Open navigation'), ENT_QUOTES, 'UTF-8') ?>">&#9776;</button>
                <h1><?= htmlspecialchars(t('Recent Activity'), ENT_QUOTES, 'UTF-8') ?></h1>
            </div>
            <div class="topbar-actions">
                <label class="search-box"><span aria-hidden="true">&#128269;</span><input type="search" placeholder="<?= htmlspecialchars(t('Search anything...'), ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars(t('Search this page'), ENT_QUOTES, 'UTF-8') ?>"></label>
                <button class="notification-button" type="button" aria-label="<?= htmlspecialchars(t('Notifications'), ENT_QUOTES, 'UTF-8') ?>">&#128276;</button>
            </div>
        </header>
        <main class="dashboard-content recent-activity-workflow-page">
            <section class="admin-data-card recent-activity-card">
                <div class="admin-data-header recent-activity-toolbar">
                    <div class="store-table-filters" data-store-table-filter="recent-activity-table">
                        <label>
                            <span><?= htmlspecialchars(t('Search'), ENT_QUOTES, 'UTF-8') ?></span>
                            <input type="search" data-filter-search placeholder="<?= htmlspecialchars(t('Search activity...'), ENT_QUOTES, 'UTF-8') ?>">
                        </label>
                    </div>
                </div>
                <div class="admin-data-table-wrap recent-activity-table-wrap">
                    <table class="admin-data-table store-standard-table" id="recent-activity-table">
                        <thead>
                        <tr>
                            <th><?= htmlspecialchars(t('Time'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(t('Module'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(t('Activity'), ENT_QUOTES, 'UTF-8') ?></th>
                            <?php if ($role === 'admin'): ?><th><?= htmlspecialchars(t('By'), ENT_QUOTES, 'UTF-8') ?></th><?php endif; ?>
                            <th><?= htmlspecialchars(t('Reference'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th><?= htmlspecialchars(t('Status'), ENT_QUOTES, 'UTF-8') ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($activities === []): ?>
                            <tr><td colspan="<?= $role === 'admin' ? 6 : 5 ?>" class="admin-empty-row"><?= htmlspecialchars(t('No recent activity.'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($activities as $activity): ?>
                                <?php $statusClass = preg_replace('/[^a-z0-9-]+/', '-', strtolower((string) $activity['status'])) ?: 'pending'; ?>
                                <tr data-filter-row>
                                    <td class="recent-activity-time"><?= htmlspecialchars(date('d M Y, H:i', strtotime((string) $activity['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) $activity['module'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><strong><?= htmlspecialchars((string) $activity['action'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                    <?php if ($role === 'admin'): ?><td><?= htmlspecialchars((string) $activity['actor_name'], ENT_QUOTES, 'UTF-8') ?></td><?php endif; ?>
                                    <td><?= htmlspecialchars((string) ($activity['record_reference'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><span class="status <?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucwords(str_replace('-', ' ', (string) $activity['status'])), ENT_QUOTES, 'UTF-8') ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr data-filter-empty hidden><td colspan="<?= $role === 'admin' ? 6 : 5 ?>" class="admin-empty-row"><?= htmlspecialchars(t('No activity matches your search.'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
    <script src="assets/js/admin-dashboard.js?v=26"></script>
    </body>
    </html>
    <?php
}
