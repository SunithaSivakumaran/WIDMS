<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$language = $argv[1] ?? 'en';
if (!in_array($language, ['en','si','ta'], true)) throw new InvalidArgumentException('Invalid test language.');
$_SESSION = ['widms_language'=>$language];
require_once __DIR__ . '/../includes/i18n.php';
// Retain the original test entry point while testing its sidebar replacement.
require_once __DIR__ . '/../includes/admin-approval-navigation.php';
$navigation = require __DIR__ . '/../includes/admin-navigation.php';
$counts = ['registrations'=>0,'aid'=>2,'stock'=>0,'corrections'=>100,'vision-camps'=>1,'camp-stock'=>3];
ob_start();
echo '<nav>';
renderAdminSidebarNavigation($navigation, $counts, 'pending-approvals', ['tab'=>'vision-camps']);
echo '</nav>';
$html = (string) ob_get_clean();
$document = new DOMDocument();
@$document->loadHTML('<?xml encoding="UTF-8">'.$html);
$xpath = new DOMXPath($document);
$check = static function(bool $valid, string $message): void { if (!$valid) throw new RuntimeException($message); };
$check($xpath->query('//span[@data-approval-count]')->length === 6, 'Approval category count missing.');
$check($xpath->query('//nav/a[@aria-current="page"]')->length === 1, 'Active category is ambiguous.');
$check($xpath->query('//span[@data-approval-count="registrations"]')->item(0)->textContent === '0', 'Zero count missing.');
$check($xpath->query('//span[@data-approval-count="corrections"]')->item(0)->textContent === '100', 'Large count was truncated.');
$check($xpath->query('//a[@aria-current="page"]/span[@data-approval-count="vision-camps"]')->length === 1, 'Wrong camp link highlighted.');
$check(!str_contains($html, 'admin-approval-tabs.js'), 'Horizontal auto-scroll script is still loaded.');
$check(str_contains($html, htmlspecialchars(t('Vision Camp Requests'), ENT_QUOTES, 'UTF-8')), 'Translated camp label missing.');
$check(str_contains($html, 'page=pending-approvals&amp;tab=vision-camps'), 'Camp category link missing.');
$check(!str_contains($html, 'class="approval-tabs"'), 'Old horizontal tabs are still displayed.');
foreach ($navigation as $items) {
    foreach ($items as $item) {
        $route = ['page'=>$item['page']] + ($item['query'] ?? []);
        $matches = 0;
        foreach ($navigation as $group) foreach ($group as $candidate) {
            $matches += (int) adminNavigationItemActive($candidate, $route['page'], $route);
        }
        $check($matches === 1, 'Sidebar route has ambiguous selection: '.$item['label']);
    }
}
// Verify SQL count filters and batching in an isolated in-memory database.
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('CONCAT', static fn(...$values)=>implode('', $values));
$db->exec("CREATE TABLE registration_requests (status TEXT);
CREATE TABLE aid_requests (status TEXT);
CREATE TABLE goods_requests (id INTEGER, status TEXT, request_batch_ref TEXT);
CREATE TABLE correction_requests (status TEXT);
CREATE TABLE spectacle_camps (id INTEGER, status TEXT);
CREATE TABLE spectacle_camp_stock_requests (id INTEGER, camp_id INTEGER, status TEXT, batch_ref TEXT);
INSERT INTO registration_requests VALUES ('pending'),('approved');
INSERT INTO aid_requests VALUES ('pending'),('pending'),('distributed');
INSERT INTO goods_requests VALUES (1,'pending-admin-approval','batch'),(2,'pending-admin-approval','batch'),(3,'pending-admin-approval',''),(4,'released','old');
INSERT INTO correction_requests VALUES ('rejected');
INSERT INTO spectacle_camps VALUES (1,'pending'),(2,'approved'),(3,'completed');
INSERT INTO spectacle_camp_stock_requests VALUES (1,2,'pending',NULL),(2,3,'pending','camp-batch'),(3,3,'pending','camp-batch'),(4,3,'released','released-batch');");
$check(adminApprovalNavigationCounts($db) === ['registrations'=>1,'aid'=>2,'stock'=>2,'corrections'=>0,'vision-camps'=>1,'camp-stock'=>1], 'Pending counts or batching incorrect.');
echo "Approval sidebar passed ($language): links, selection, badges and count queries; no live database access.\n";
