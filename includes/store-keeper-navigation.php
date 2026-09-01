<?php
declare(strict_types=1);

return [
    'Overview' => [
        ['icon' => '📊', 'label' => 'Dashboard', 'page' => 'dashboard'],
    ],
    'Inventory' => [
        ['icon' => '📥', 'label' => 'Receive Items', 'page' => 'receive-items'],
        ['icon' => '📦', 'label' => 'Current Stock', 'page' => 'current-stock'],
    ],
    'Dispatch' => [
        ['icon' => '✅', 'label' => 'Approved Requests Ready for Dispatch', 'page' => 'approved-dispatches'],
        ['icon' => '🚚', 'label' => 'Recently Dispatched', 'page' => 'recent-dispatches'],
    ],
    'Requests' => [
        ['icon' => '📝', 'label' => 'Correction Requests', 'page' => 'correction-requests'],
    ],
];
