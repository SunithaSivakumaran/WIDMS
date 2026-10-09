<?php
declare(strict_types=1);

// Group existing pages by workflow; routing and permissions remain unchanged.
return [
    'Overview' => [
        ['icon' => '📊', 'label' => 'Dashboard', 'page' => 'dashboard'],
    ],
    'Aid Requests' => [
        ['icon' => '📝', 'label' => 'Direct Aid Request', 'page' => 'direct-aid-request'],
        ['icon' => '📦', 'label' => 'Approved Aid Bundles', 'page' => 'approved-aid-bundles'],
        ['icon' => '📑', 'label' => 'Aid Activity History', 'page' => 'my-aid-requests'],
        ['icon' => '📋', 'label' => 'Aid Requests (Monitor)', 'page' => 'aid-requests'],
    ],
    'Distribution' => [
        ['icon' => '📤', 'label' => 'Final Distribution', 'page' => 'distribute-items'],
        ['icon' => '📑', 'label' => 'Distribution History', 'page' => 'distribution-history'],
    ],
    'Stock & Quotas' => [
        ['icon' => '&#10133;', 'label' => 'New Quota Request', 'page' => 'request-goods'],
        ['icon' => '&#128203;', 'label' => 'SSO Quota History', 'page' => 'my-goods-requests'],
        ['icon' => '&#128101;', 'label' => 'Beneficiary Request Bundles', 'page' => 'my-beneficiary-requests'],
        ['icon' => '📦', 'label' => 'Current Stock', 'page' => 'current-stock'],
        ['icon' => '📈', 'label' => 'DS Division Pools', 'page' => 'officer-pools'],
    ],
    'Returns' => [
        ['icon' => '&#128260;', 'label' => 'Process Return', 'page' => 'returns'],
        ['icon' => '&#128203;', 'label' => 'Return History', 'page' => 'return-history'],
    ],
    'Vision Camps' => [
        ['icon'=>'&#10133;', 'label'=>'New Vision Camp', 'page'=>'spectacle-camp-new'],
        ['icon'=>'&#128197;', 'label'=>'My Camps', 'page'=>'my-spectacle-camps'],
        ['icon'=>'&#128083;', 'label'=>'All Vision Camps', 'page'=>'spectacle-camps'],
        ['icon'=>'&#128230;', 'label'=>'Request Camp Stock', 'page'=>'vision-camp-stock-requests'],
    ],
    'Suppliers' => [
        ['icon' => '⚙️', 'label' => 'Register & Allocate', 'page' => 'supplier-config'],
        ['icon' => '🏢', 'label' => 'Registered Suppliers', 'page' => 'supplier-details'],
        ['icon' => '📊', 'label' => 'Balance Summary', 'page' => 'supplier-balances'],
    ],
    'Eligibility Configuration' => [
        ['icon' => '🛠️', 'label' => 'Eligibility Rule Builder', 'page' => 'item-categories'],
        ['icon' => '📋', 'label' => 'Configured Eligibility Rules', 'page' => 'eligibility-rules'],
    ],
    'Reports & Activity' => [
        ['icon' => '📑', 'label' => 'Reports', 'page' => 'reports'],
        ['icon' => '🕘', 'label' => 'Recent Activity', 'page' => 'recent-activity'],
        ['icon' => '🔍', 'label' => 'Audit Log', 'page' => 'audit-log'],
    ],
];
