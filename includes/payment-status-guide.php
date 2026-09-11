<?php
declare(strict_types=1);

require_once __DIR__ . '/payment-status-control.php';

if (!function_exists('renderPaymentStatusGuide')) {
    /**
     * Render the shared legend for payment-status colors.
     *
     * @param array<string, string> $labels
     */
    function renderPaymentStatusGuide(array $labels, string $title = 'Payment status guide'): string
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $html = '<div class="approval-legend approval-legend-standalone payment-status-guide" aria-label="' . $safeTitle . '">';
        $html .= '<strong class="approval-legend-title">' . $safeTitle . '</strong>';

        foreach (['fully-paid', 'partially-paid', 'unpaid'] as $status) {
            if (isset($labels[$status])) {
                $safeStatus = htmlspecialchars($status, ENT_QUOTES, 'UTF-8');
                $safeLabel = htmlspecialchars((string) $labels[$status], ENT_QUOTES, 'UTF-8');
                $html .= '<span class="payment-status-guide-item"><span class="payment-status-swatch payment-status-swatch-' . $safeStatus . '" aria-hidden="true"></span><span class="payment-status-guide-label">' . $safeLabel . '</span></span>';
            }
        }

        return $html . '</div>';
    }
}
