<?php
declare(strict_types=1);

function t(string $value): string { return $value; }
function csrfToken(): string { return 'test-token'; }
function widmsAidItemName(string $name): string { return $name; }
require __DIR__ . '/../includes/aid-stock-comparison.php';

require __DIR__ . '/../includes/store-dispatch-request-card.php';

$direct = [
    'id' => 3, 'aid_request_id' => 18, 'item_name' => 'wheelchair', 'variety' => '',
    'admin_name' => 'Administrator', 'beneficiary_name' => '<script>alert(1)</script>',
    'division_name' => 'Akmeemana', 'quantity' => 1, 'central_stock' => 10,
    'prescribed_power' => null, 'central_available' => 10, 'spectacles' => false,
];
$quota = [
    'id' => 4, 'item_name' => 'hearing aid', 'variety' => '',
    'requester_name' => 'Subject Officer', 'request_batch_ref' => 'GRB-4',
    'district_name' => 'Galle', 'division_name' => 'Akmeemana',
    'quantity' => 5, 'central_stock' => 20, 'approver_name' => 'Administrator',
    'prescribed_power' => null, 'sso_name' => 'Assigned SSO', 'linked_aid' => [],
];

ob_start();
renderStoreDispatchRequestCard($direct, 'direct');
renderStoreDispatchRequestCard($quota, 'quota');
$lens = $direct;
$lens['id'] = 5;
$lens['aid_request_id'] = 19;
$lens['item_name'] = 'Contact Lens';
$lens['optical'] = true;
renderStoreDispatchRequestCard($lens, 'direct');
$html = (string) ob_get_clean();

foreach (['aid-request-18', 'goods-request-4', 'Release to Admin', 'Release Stock Quota',
    'dispatch-to-admin', 'dispatch-for-sso', 'Requested By', 'Release To', 'SSO Planned'] as $expected) {
    if (!str_contains($html, $expected)) {
        fwrite(STDERR, "Missing dispatch card content: {$expected}\n");
        exit(1);
    }
}
if (str_contains($html, '<script>') || !str_contains($html, '&lt;script&gt;')) {
    fwrite(STDERR, "Dispatch card failed to escape beneficiary content.\n");
    exit(1);
}
if (!preg_match('/<article id="aid-request-19".*?<button[^>]*type="submit"(?![^>]*disabled)/s', $html)) {
    fwrite(STDERR, "Quantity-only Contact Lens dispatch should be enabled without a power value.\n");
    exit(1);
}
echo "Store dispatch request cards: OK\n";
