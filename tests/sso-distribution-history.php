<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../config/database.php';

$officer = database()->query(
    "SELECT u.id
     FROM users u
     LEFT JOIN distributions d ON d.distributed_by = u.id AND d.ds_division_id = u.ds_division_id
     WHERE u.role = 'social-service-officer' AND u.status = 'active'
     GROUP BY u.id
     ORDER BY COUNT(d.id) DESC, u.id
     LIMIT 1"
)->fetchColumn();
if (!$officer) {
    throw new RuntimeException('An active Social Service Officer is required for the history test.');
}

$_SESSION['user_id'] = (int) $officer;
$_SESSION['role'] = 'social-service-officer';
$_SESSION['full_name'] = 'History Test Officer';
$_SERVER['REQUEST_METHOD'] = 'GET';

$render = static function (array $filters): array {
    $_GET = $filters;
    ob_start();
    require __DIR__ . '/../modules/social-service-officer/distribution-history.php';
    $html = (string) ob_get_clean();
    if ($error !== '') {
        throw new RuntimeException($error);
    }
    return [$html, $rows];
};

[$html, $rows] = $render([]);
$expected = (int) database()->query(
    'SELECT COUNT(*) FROM distributions d JOIN users u ON u.id = d.distributed_by
     WHERE u.id = ' . (int) $officer . ' AND d.ds_division_id = u.ds_division_id'
)->fetchColumn();
if (count($rows) !== $expected || substr_count($html, '<tr id="distribution-') !== $expected) {
    throw new RuntimeException('Distribution history must show the officer’s recorded distributions.');
}
if (!str_contains($html, 'name="search"') || !str_contains($html, 'name="item_id"')
    || !str_contains($html, 'name="date_from"') || !str_contains($html, 'name="date_to"')) {
    throw new RuntimeException('Distribution history filters are missing.');
}

if ($rows !== []) {
    $sample = $rows[0];
    [, $matching] = $render(['search' => 'DIST-' . str_pad((string) $sample['id'], 4, '0', STR_PAD_LEFT)]);
    if (count($matching) !== 1 || (int) $matching[0]['id'] !== (int) $sample['id']) {
        throw new RuntimeException('The reference search did not find the matching distribution.');
    }
    [, $matching] = $render(['item_id' => (string) $sample['item_id']]);
    if ($matching === [] || array_filter($matching, static fn(array $row): bool => (int) $row['item_id'] !== (int) $sample['item_id'])) {
        throw new RuntimeException('The aid-item filter returned the wrong distributions.');
    }
    [, $matching] = $render(['date_from' => '2099-01-01']);
    if ($matching !== []) {
        throw new RuntimeException('The distribution date filter did not exclude earlier records.');
    }
}

$cardsPage = (string) file_get_contents(__DIR__ . '/../modules/social-service-officer/distribute-aid.php');
if (!str_contains($cardsPage, 'page=sso-distribution-history') || str_contains($cardsPage, "Today's Distributions")) {
    throw new RuntimeException('Distribute Items should link to history without embedding its table.');
}

echo "SSO distribution history: separate page, scoped records, search and filters.\n";
