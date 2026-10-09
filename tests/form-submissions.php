<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/form-submissions.php';

$_SESSION = [];
$html = '<form method="post"><button>Save</button></form>'
    . '<form method=POST action="dashboard.php"><button>Approve</button></form>'
    . '<form method="get"><button>Search</button></form>'
    . '<script>const example = \'<form method="post">\';</script>';
$rendered = widmsAddFormSubmissionFields($html);
preg_match_all('/name="widms_submission_token" value="([a-f0-9]{48})"/', $rendered, $matches);
if (count($matches[1]) !== 2 || $matches[1][0] === $matches[1][1]) {
    throw new RuntimeException('POST forms must receive separate tokens.');
}
if (!str_contains($rendered, '<script>const example = \'<form method="post">\';</script>')) {
    throw new RuntimeException('Script content must not be rewritten.');
}
if (!widmsConsumeFormSubmissionToken($matches[1][0])) {
    throw new RuntimeException('First submission should be accepted.');
}
if (widmsConsumeFormSubmissionToken($matches[1][0])) {
    throw new RuntimeException('A replayed submission must be rejected.');
}
if (!widmsConsumeFormSubmissionToken($matches[1][1])) {
    throw new RuntimeException('Submitting another form must remain possible.');
}
if (widmsConsumeFormSubmissionToken('invalid')) {
    throw new RuntimeException('Invalid tokens must be rejected.');
}

echo "Form submission tests passed.\n";
