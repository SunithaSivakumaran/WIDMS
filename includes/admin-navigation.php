<?php
declare(strict_types=1);

// Group existing pages by workflow; routing and permissions remain unchanged.
return [
    'Overview' => [
        ['icon' => '📊', 'label' => 'Dashboard', 'page' => 'dashboard'],
    ],
    'Approvals & Reviews' => [
        ['icon' => '🔔', 'label' => 'Pending Approvals', 'page' => 'pending-approvals'],
        ['icon' => '📋', 'label' => 'Reviewed Aid Requests', 'page' => 'item-requests'],
        ['icon' => '✅', 'label' => 'Reviewed Stock Quota Requests', 'page' => 'reviewed-stock-quota-requests'],
        ['icon' => '✅', 'label' => 'Reviewed Correction Requests', 'page' => 'reviewed-correction-requests'],
    ],
    'Distribution' => [
        ['icon' => '&#8594;', 'label' => 'Direct Aid Distribution', 'page' => 'direct-distribution'],
        ['icon' => '&#128230;', 'label' => 'Direct Aid Releases & History', 'page' => 'direct-aid-release-queue'],
    ],
    'Stock & Payments' => [
        ['icon' => '📦', 'label' => 'Current Stock', 'page' => 'current-stock'],
        ['icon' => '📈', 'label' => 'Social Service Officer Pools', 'page' => 'officer-pools'],
        ['icon' => '🧾', 'label' => 'Payment History', 'page' => 'receipt-history'],
    ],
    'Suppliers' => [
        ['icon' => '⚙️', 'label' => 'Register & Allocate', 'page' => 'supplier-config'],
        ['icon' => '🏢', 'label' => 'Registered Suppliers', 'page' => 'supplier-details'],
        ['icon' => '📊', 'label' => 'Balance Summary', 'page' => 'supplier-balances'],
    ],
    'User Management' => [
        ['icon' => '👥', 'label' => 'Users', 'page' => 'users'],
        ['icon' => '🗺️', 'label' => 'Divisions', 'page' => 'divisions'],
    ],
    'Reports & Activity' => [
        ['icon' => '📑', 'label' => 'Reports', 'page' => 'reports'],
        ['icon' => '🕘', 'label' => 'Recent Activity', 'page' => 'recent-activity'],
        ['icon' => '🔍', 'label' => 'Audit Log', 'page' => 'audit-log'],
    ],
    'System' => [
        ['icon' => '⚙️', 'label' => 'System Config', 'page' => 'system-config'],
    ],
];
