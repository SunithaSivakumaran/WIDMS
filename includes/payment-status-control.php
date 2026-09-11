<?php
declare(strict_types=1);

if (!function_exists('renderPaymentStatusControl')) {
    /**
     * Render a consistent payment-status badge, optionally as a history link.
     */
    function renderPaymentStatusControl(
        string $status,
        string $label,
        ?string $href = null,
        string $accessibleName = '',
        string $extraClass = ''
    ): string {
        $statusClass = preg_replace('/[^a-z0-9_-]/i', '', $status) ?: 'unknown';
        $classes = trim('payment-badge payment-status-control ' . $statusClass . ' ' . $extraClass);
        $safeClasses = htmlspecialchars($classes, ENT_QUOTES, 'UTF-8');
        $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

        if ($href === null) {
            return '<span class="' . $safeClasses . '">' . $safeLabel . '</span>';
        }

        $name = $accessibleName !== '' ? $accessibleName : $label;
        $safeHref = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        return '<a class="' . $safeClasses . '" href="' . $safeHref . '" aria-label="' . $safeName . '" title="' . $safeName . '">' . $safeLabel . '</a>';
    }
}
