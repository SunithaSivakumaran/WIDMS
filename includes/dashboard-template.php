<?php
declare(strict_types=1);

$safeTitle = htmlspecialchars($dashboardTitle, ENT_QUOTES, 'UTF-8');
$safeName = htmlspecialchars((string) $_SESSION['full_name'], ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $safeTitle ?> | WIDMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <main class="container min-vh-100 d-flex align-items-center justify-content-center py-4">
        <section class="card border-0 shadow-sm text-center p-5" style="max-width: 620px; width: 100%;">
            <img src="assets/images/client-logo.jpeg" alt="WIDMS logo" class="align-self-center mb-3 rounded-circle" width="72" height="72" style="object-fit: cover;">
            <h1 class="h3 mb-3"><?= $safeTitle ?></h1>
            <p class="text-secondary mb-4">Welcome, <?= $safeName ?>. Your dashboard will be developed here next.</p>
            <a class="btn btn-outline-danger align-self-center" href="logout.php">Sign Out</a>
        </section>
    </main>
</body>
</html>
