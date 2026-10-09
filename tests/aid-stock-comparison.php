<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/aid-stock-comparison.php';

ob_start();
renderAidStockComparison('Spectacles', 2, 5);
$matched = (string) ob_get_clean();
if (str_contains($matched, 'Power') || substr_count($matched, 'aid-stock-matched') !== 1) {
    throw new RuntimeException('Quantity-only Central Stock should render as a match.');
}

ob_start();
renderAidStockComparison('Contact Lens', 2, 1);
$short = (string) ob_get_clean();
if (str_contains($short, 'Power') || !str_contains($short, 'aid-stock-short')) {
    throw new RuntimeException('Insufficient Contact Lens quantity should render as a shortage.');
}

ob_start();
renderAidStockComparison('Contact Lens', 1, 5);
$reservedShort = (string) ob_get_clean();
if (str_contains($reservedShort, 'Power') || str_contains($reservedShort, 'aid-stock-short')) {
    throw new RuntimeException('Contact Lens matching must use quantity without power.');
}

echo "Aid stock comparison rendering passed.\n";
