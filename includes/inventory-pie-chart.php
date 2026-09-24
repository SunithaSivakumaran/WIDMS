<?php

declare(strict_types=1);

/**
 * Prepare a compact, readable stock composition for dashboard pie charts.
 * Duplicate item names are combined and small categories are grouped as Other.
 *
 * @param array<int, array{name?: mixed, quantity?: mixed}> $rows
 * @return array{segments: array<int, array{label: string, quantity: int, percentage: float, color: string, is_other: bool}>, total: int, gradient: string}
 */
function widmsInventoryPieData(array $rows, int $maximumSegments = 6): array
{
    $totals = [];
    foreach ($rows as $row) {
        $label = trim((string) ($row['name'] ?? ''));
        $quantity = max(0, (int) ($row['quantity'] ?? 0));
        if ($label === '' || $quantity === 0) {
            continue;
        }
        $totals[$label] = ($totals[$label] ?? 0) + $quantity;
    }

    arsort($totals, SORT_NUMERIC);
    $maximumSegments = max(2, $maximumSegments);
    $grouped = [];
    $otherQuantity = 0;
    foreach ($totals as $label => $quantity) {
        if (count($grouped) < $maximumSegments - 1 || count($totals) <= $maximumSegments) {
            $grouped[] = ['label' => $label, 'quantity' => $quantity, 'is_other' => false];
        } else {
            $otherQuantity += $quantity;
        }
    }
    if ($otherQuantity > 0) {
        $grouped[] = ['label' => 'Other', 'quantity' => $otherQuantity, 'is_other' => true];
    }

    $total = array_sum(array_column($grouped, 'quantity'));
    if ($total === 0) {
        return ['segments' => [], 'total' => 0, 'gradient' => '#dfe8ee'];
    }

    $palette = ['#1769aa', '#40a6d8', '#30a46c', '#f0b43c', '#e8784b', '#7569d8', '#8196a8'];
    $segments = [];
    $gradientParts = [];
    $cursor = 0.0;
    foreach ($grouped as $index => $row) {
        $percentage = ((int) $row['quantity'] / $total) * 100;
        $end = $index === count($grouped) - 1 ? 100.0 : $cursor + $percentage;
        $color = $palette[$index % count($palette)];
        $gradientParts[] = sprintf('%s %.4F%% %.4F%%', $color, $cursor, $end);
        $segments[] = [
            'label' => (string) $row['label'],
            'quantity' => (int) $row['quantity'],
            'percentage' => $percentage,
            'color' => $color,
            'is_other' => (bool) $row['is_other'],
        ];
        $cursor = $end;
    }

    return [
        'segments' => $segments,
        'total' => $total,
        'gradient' => 'conic-gradient(' . implode(', ', $gradientParts) . ')',
    ];
}

/**
 * Render the shared, accessible inventory pie-chart body used by role dashboards.
 *
 * @param array<int, array{name?: mixed, quantity?: mixed}> $rows
 */
function renderInventoryPieChart(array $rows, string $ariaLabel, string $emptyMessage, bool $chartOnly = false): void
{
    $chart = widmsInventoryPieData($rows);
    if ($chart['total'] === 0) {
        if ($chartOnly) {
            echo '<div class="inventory-pie-content inventory-pie-only">';
            echo '<div class="inventory-pie-chart inventory-pie-chart-empty" role="img" aria-label="' . htmlspecialchars($emptyMessage, ENT_QUOTES, 'UTF-8') . '"><span aria-hidden="true">0</span></div>';
            echo '</div>';
            return;
        }
        echo '<p class="dashboard-empty-state inventory-pie-empty">' . htmlspecialchars($emptyMessage, ENT_QUOTES, 'UTF-8') . '</p>';
        return;
    }

    $accessibleSummary = [];
    foreach ($chart['segments'] as $segment) {
        $label = $segment['is_other'] ? t('Other') : $segment['label'];
        $accessibleSummary[] = $label . ': ' . number_format($segment['quantity']);
    }
    if ($chartOnly) {
        ?>
        <div class="inventory-pie-content inventory-pie-only">
            <div
                class="inventory-pie-chart"
                style="--inventory-pie-fill: <?= htmlspecialchars($chart['gradient'], ENT_QUOTES, 'UTF-8') ?>"
                role="img"
                aria-label="<?= htmlspecialchars($ariaLabel . '. ' . implode(', ', $accessibleSummary), ENT_QUOTES, 'UTF-8') ?>"
            ></div>
            <ul class="inventory-pie-legend inventory-pie-only-legend" aria-label="<?= htmlspecialchars(t('Chart legend'), ENT_QUOTES, 'UTF-8') ?>">
                <?php foreach ($chart['segments'] as $segment): ?>
                    <?php $label = $segment['is_other'] ? t('Other') : $segment['label']; ?>
                    <li>
                        <span class="inventory-pie-key" style="--inventory-pie-color: <?= htmlspecialchars($segment['color'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></span>
                        <span class="inventory-pie-label" title="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                        <strong><?= number_format($segment['quantity']) ?></strong>
                        <small><?= number_format($segment['percentage'], 1) ?>%</small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
        return;
    }
    ?>
    <div class="inventory-pie-content">
        <div class="inventory-pie-summary">
            <span><?= htmlspecialchars(t('Total Units'), ENT_QUOTES, 'UTF-8') ?></span>
            <strong><?= number_format($chart['total']) ?></strong>
        </div>
        <div class="inventory-pie-layout">
            <figure class="inventory-pie-figure">
                <div
                    class="inventory-pie-chart"
                    style="--inventory-pie-fill: <?= htmlspecialchars($chart['gradient'], ENT_QUOTES, 'UTF-8') ?>"
                    role="img"
                    aria-label="<?= htmlspecialchars($ariaLabel . '. ' . implode(', ', $accessibleSummary), ENT_QUOTES, 'UTF-8') ?>"
                ></div>
            </figure>
            <ul class="inventory-pie-legend" aria-label="<?= htmlspecialchars(t('Chart legend'), ENT_QUOTES, 'UTF-8') ?>">
                <?php foreach ($chart['segments'] as $segment): ?>
                    <?php $label = $segment['is_other'] ? t('Other') : $segment['label']; ?>
                    <li>
                        <span class="inventory-pie-key" style="--inventory-pie-color: <?= htmlspecialchars($segment['color'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></span>
                        <span class="inventory-pie-label" title="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                        <strong><?= number_format($segment['quantity']) ?></strong>
                        <small><?= number_format($segment['percentage'], 1) ?>%</small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php
}
