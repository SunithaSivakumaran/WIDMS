<?php
declare(strict_types=1);

function renderAdminApprovalTabs(array $counts, string $activeTab): void
{
    $tabs = [
        'registrations' => ['User Registration Requests', 'dashboard.php?page=pending-approvals', 'red'],
        'aid' => ['Pending Aid Requests', 'dashboard.php?page=item-requests&view=pending', 'yellow'],
        'stock' => ['Stock Release', 'dashboard.php?page=goods-requests', 'yellow'],
        'corrections' => ['Correction Requests', 'dashboard.php?page=correction-requests', 'yellow'],
    ];
    ?>
    <nav class="approval-tabs" aria-label="<?= htmlspecialchars(t('Approval categories'), ENT_QUOTES, 'UTF-8') ?>">
        <?php foreach ($tabs as $key => [$label, $url, $countClass]): ?>
            <a class="approval-tab<?= $key === $activeTab ? ' active' : '' ?>"
               href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>"
               <?= $key === $activeTab ? 'aria-current="page"' : '' ?>>
                <?= htmlspecialchars(t($label), ENT_QUOTES, 'UTF-8') ?>
                <span class="tab-count <?= htmlspecialchars($countClass, ENT_QUOTES, 'UTF-8') ?>"><?= (int) ($counts[$key] ?? 0) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php
}
