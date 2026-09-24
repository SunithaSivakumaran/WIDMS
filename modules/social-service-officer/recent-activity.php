<?php

declare(strict_types=1);

requireRole('social-service-officer');
require_once __DIR__ . '/../../includes/recent-activity-page.php';

renderRecentActivityPage('social-service-officer', __DIR__ . '/../../includes/social-service-officer-sidebar.php');
