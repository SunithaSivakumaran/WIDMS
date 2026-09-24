<?php
declare(strict_types=1);

// The standalone beneficiary-registration workflow is retired. Keep this
// legacy endpoint as a redirect so old bookmarks reach the direct request form.
requireRole('subject-officer');
header('Location: dashboard.php?page=direct-aid-request');
exit;
