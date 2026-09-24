<?php
declare(strict_types=1);

// Group existing pages by workflow; routing and permissions remain unchanged.
return [
    'Overview' => [
        ['icon' => '📊', 'label' => 'Dashboard', 'page' => 'dashboard'],
    ],
    'Inventory' => [
        ['icon' => '📥', 'label' => 'Receive Aid', 'page' => 'receive-items'],
        ['icon' => '📦', 'label' => 'Current Stock', 'page' => 'current-stock'],
        ['icon' => '🧾', 'label' => 'Receipt History', 'page' => 'receipt-history'],
    ],
    'Dispatch' => [
        ['icon' => '✅', 'label' => 'Dispatch Requests', 'page' => 'approved-dispatches'],
        ['icon' => '🚚', 'label' => 'Recently Dispatched', 'page' => 'recent-dispatches'],
    ],
    'Corrections' => [
        ['icon' => '📝', 'label' => 'Correction Requests', 'page' => 'correction-requests'],
        ['icon' => '📋', 'label' => 'Request History', 'page' => 'request-history'],
    ],
    'Activity' => [
        ['icon' => '🕘', 'label' => 'Recent Activity', 'page' => 'recent-activity'],
    ],
];
