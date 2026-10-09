<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/../includes/aid-request-details.php';

$details = json_encode([
    ['label' => 'Power', 'type' => 'number', 'value' => '+2.00'],
    ['label' => 'Lens size', 'type' => 'text', 'value' => 'Medium'],
], JSON_THROW_ON_ERROR);
$lens = aidRequestDetails(['item_name' => 'Contact Lens', 'beneficiary_details_json' => $details]);
if (count($lens) !== 2 || $lens[0]['label'] !== 'Power' || $lens[1]['label'] !== 'Lens size') {
    throw new RuntimeException('Configured Contact Lens beneficiary details must remain visible.');
}
$other = aidRequestDetails(['item_name' => 'Other aid', 'beneficiary_details_json' => $details]);
if (count($other) !== 2) {
    throw new RuntimeException('Details for unrelated aid items must be preserved.');
}

echo "Contact Lens details: OK\n";
