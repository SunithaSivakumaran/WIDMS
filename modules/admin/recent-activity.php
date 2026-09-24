<?php

declare(strict_types=1);

requireRole('admin');
require_once __DIR__ . '/../../includes/recent-activity-page.php';

renderRecentActivityPage('admin', __DIR__ . '/../../includes/admin-sidebar.php');
