<?php
declare(strict_types=1);

// Group existing pages by the officer's day-to-day workflow.
return [
    'Overview' => [
        ['icon' => '&#128202;', 'label' => 'Dashboard', 'page' => 'dashboard'],
    ],
    'Aid Requests' => [
        ['icon' => '&#10133;', 'label' => 'New Aid Request', 'page' => 'new-aid-request'],
        ['icon' => '&#128203;', 'label' => 'Aid Activity History', 'page' => 'aid-requests'],
    ],
    'My Stock' => [
        ['icon' => '&#128230;', 'label' => 'My Pool Quota', 'page' => 'pool-quota'],
        ['icon' => '&#128203;', 'label' => 'Assigned Stock Quotas', 'page' => 'assigned-stock-quotas'],
    ],
    'Distribution' => [
        ['icon' => '&#128229;', 'label' => 'Pending Aid Handover', 'page' => 'pending-handover'],
        ['icon' => '&#129309;', 'label' => 'Distribute Aid', 'page' => 'distribute-aid'],
    ],
    'Returns' => [
        ['icon' => '&#128260;', 'label' => 'Process Return', 'page' => 'process-return'],
        ['icon' => '&#128203;', 'label' => 'Return History', 'page' => 'return-history'],
    ],
    'Reports & Activity' => [
        ['icon' => '&#128209;', 'label' => 'Request Status Report', 'page' => 'request-status-report'],
        ['icon' => '&#128344;', 'label' => 'Recent Activity', 'page' => 'recent-activity'],
    ],
];
