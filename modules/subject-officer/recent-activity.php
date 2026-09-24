<?php

declare(strict_types=1);

requireRole('subject-officer');
require_once __DIR__ . '/../../includes/recent-activity-page.php';

renderRecentActivityPage('subject-officer', __DIR__ . '/../../includes/subject-officer-sidebar.php');
