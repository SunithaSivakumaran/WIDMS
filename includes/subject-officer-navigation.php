<?php
declare(strict_types=1);

return [
    'Overview' => [
        ['icon' => '📊', 'label' => 'Dashboard', 'page' => 'dashboard'],
    ],
    'Operations' => [
        ['icon' => '📦', 'label' => 'Request Goods from Store', 'page' => 'request-goods'],
        ['icon' => '👓', 'label' => 'Vision Camp / Direct Procurement', 'page' => 'vision-camp'],
        ['icon' => '🔵', 'label' => 'Contact Lens Orders', 'page' => 'contact-lens-orders'],
        ['icon' => '🗃️', 'label' => 'Beneficiaries', 'page' => 'beneficiaries'],
        ['icon' => '📦', 'label' => 'Distribute Items', 'page' => 'distribute-items'],
        ['icon' => '🔄', 'label' => 'Returns', 'page' => 'returns'],
    ],
    'Requests' => [
        ['icon' => '📋', 'label' => 'Aid Requests (Monitor)', 'page' => 'aid-requests'],
        ['icon' => '✅', 'label' => 'Correction Approval', 'page' => 'correction-approval'],
    ],
    'Procurement' => [
        ['icon' => '📦', 'label' => 'Central Stock', 'page' => 'central-stock'],
        ['icon' => '🏢', 'label' => 'Suppliers', 'page' => 'suppliers'],
    ],
    // Separate builder and register links so officers can enter or review rules directly.
    'Eligibility Configuration' => [
        ['icon' => '🛠️', 'label' => 'Eligibility Rule Builder', 'page' => 'item-categories'],
        ['icon' => '📋', 'label' => 'Configured Eligibility Rules', 'page' => 'eligibility-rules'],
    ],
    'Monitoring' => [
        ['icon' => '📈', 'label' => 'Social Service Officer Pools', 'page' => 'officer-pools'],
        ['icon' => '📑', 'label' => 'Reports', 'page' => 'reports'],
        ['icon' => '🔍', 'label' => 'Audit Log', 'page' => 'audit-log'],
    ],
];
