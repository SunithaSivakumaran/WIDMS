<?php

declare(strict_types=1);

/**
 * Render one searchable prohibited-item selector while preserving checkbox-based form submission.
 */
function renderProhibitedItemSelector(
    array $items,
    array $selectedIds = [],
    ?int $excludedItemId = null,
    string $emptyMessage = 'No other items are available yet.'
): void {
    $availableItems = array_values(array_filter(
        $items,
        static fn(array $item): bool => (int) $item['id'] !== $excludedItemId
    ));

    if (!$availableItems) {
        echo '<small class="multi-select-unavailable">'
            . htmlspecialchars(t($emptyMessage), ENT_QUOTES, 'UTF-8')
            . '</small>';
        return;
    }

    $selectedLookup = array_fill_keys(array_map('intval', $selectedIds), true);
    ?>
    <div
        class="searchable-multi-select"
        data-searchable-multi-select
        data-remove-label="<?= htmlspecialchars(t('Remove'), ENT_QUOTES, 'UTF-8') ?>"
    >
        <div class="multi-select-selection" data-multi-selection aria-live="polite"></div>
        <div class="multi-select-input-wrap">
            <input
                type="search"
                data-multi-search
                aria-label="<?= htmlspecialchars(t('Search prohibited items'), ENT_QUOTES, 'UTF-8') ?>"
                aria-expanded="false"
                placeholder="<?= htmlspecialchars(t('Type to search items...'), ENT_QUOTES, 'UTF-8') ?>"
                autocomplete="off"
            >
            <span class="multi-select-count" data-multi-count hidden>0</span>
        </div>
        <div class="multi-select-dropdown" data-multi-dropdown hidden>
            <div class="multi-select-options" data-multi-options>
                <?php foreach ($availableItems as $item): ?>
                    <?php $label = $item['item_name'] . ($item['variety'] ? ' / ' . $item['variety'] : ''); ?>
                    <label class="multi-select-option" data-multi-option>
                        <input
                            type="checkbox"
                            name="prohibited_item_ids[]"
                            value="<?= (int) $item['id'] ?>"
                            <?= isset($selectedLookup[(int) $item['id']]) ? 'checked' : '' ?>
                        >
                        <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                    </label>
                <?php endforeach; ?>
                <p class="multi-select-no-results" data-multi-no-results hidden>
                    <?= htmlspecialchars(t('No matching items found.'), ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>
        </div>
    </div>
    <?php
}
