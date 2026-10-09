<?php
declare(strict_types=1);

/** Show requested quantity versus Central Stock throughout the release workflow. */
function renderAidStockComparison(string $item, int $quantity, int $centralAvailable): void
{
    $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    ?>
    <span class="aid-stock-comparison" aria-label="<?= $escape(t('Stock match')) ?>">
        <span class="aid-stock-metric aid-stock-requested">
            <span><?= $escape(t('Requested aid')) ?></span>
            <strong><?= $escape($item) ?></strong>
            <small><?= $quantity ?> <?= $escape(t('units')) ?></small>
        </span>
        <span class="aid-stock-metric <?= $centralAvailable >= $quantity ? 'aid-stock-matched' : 'aid-stock-short' ?>">
            <span><?= $escape(t('Central Stock')) ?></span>
            <strong><?= $centralAvailable ?> <?= $escape(t('available')) ?></strong>
            <small><?= $escape(t($centralAvailable >= $quantity ? 'Enough stock' : 'Insufficient stock')) ?></small>
        </span>
    </span>
    <?php
}
