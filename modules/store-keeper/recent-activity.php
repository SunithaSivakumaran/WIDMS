<?php

declare(strict_types=1);

requireRole('store-keeper');
require_once __DIR__ . '/../../includes/recent-activity-page.php';

renderRecentActivityPage('store-keeper', __DIR__ . '/../../includes/store-keeper-sidebar.php');
