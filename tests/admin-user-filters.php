<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/user-filters.php';

function userFilterCheck(bool $valid, string $message): void
{
    if (!$valid) { throw new RuntimeException($message); }
}

$db = database();
// Connection-local fixtures only; no real users or SMS are changed.
$db->exec("CREATE TEMPORARY TABLE admin_filter_people (
    id INT,full_name VARCHAR(100),username VARCHAR(120),email VARCHAR(120),salary_number VARCHAR(30),
    phone VARCHAR(25),division VARCHAR(100),role VARCHAR(40),status VARCHAR(20),district_id INT,ds_division_id INT
    ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$db->exec("CREATE TEMPORARY TABLE admin_filter_districts (id INT,name VARCHAR(100)) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$db->exec("CREATE TEMPORARY TABLE admin_filter_divisions (id INT,name VARCHAR(100)) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$db->exec("INSERT INTO admin_filter_districts VALUES (1,'Galle'); INSERT INTO admin_filter_divisions VALUES (1,'Akmeemana')");
$insert = $db->prepare('INSERT INTO admin_filter_people VALUES (?,?,?,?,?,?,?,?,?,?,?)');
$insert->execute([1,'Alice','swpcs001','alice@example.test','001','0771234561',null,'admin','active',null,null]);
$insert->execute([2,'Bob','swpcs002','bob@example.test','002','0771234562',null,'store-keeper','inactive',null,null]);
$insert->execute([3,'සුනිතා','swpcs003','sso@example.test','003','0771234563','Akmeemana','social-service-officer','active',1,1]);
$insert->execute([4,'சுனிதா','swpcs004','subject@example.test','004','0771234564',null,'subject-officer','active',null,null]);
$lookup = static function (array $input) use ($db): array {
    $filter = widmsUserFilters($input);
    $query = $db->prepare('SELECT u.id FROM admin_filter_people u LEFT JOIN admin_filter_districts d ON d.id=u.district_id LEFT JOIN admin_filter_divisions ds ON ds.id=u.ds_division_id' . $filter['where'] . ' ORDER BY u.id');
    $query->execute($filter['parameters']);
    return array_map('intval', $query->fetchAll(PDO::FETCH_COLUMN));
};
foreach ([
    [[],[1,2,3,4]],
    [['status'=>'active'],[1,3,4]],
    [['status'=>'inactive'],[2]],
    [['role'=>'store-keeper','status'=>'active'],[]],
    [['role'=>'store-keeper','status'=>'inactive','search'=>'BOB'],[2]],
    [['role'=>'admin'],[1]],
    [['role'=>'social-service-officer'],[3]],
    [['role'=>'subject-officer'],[4]],
    [['search'=>'001'],[1]],
    [['search'=>'0771234564'],[4]],
    [['search'=>'alice@example.test'],[1]],
    [['search'=>'swpcs002'],[2]],
    [['search'=>'Galle'],[3]],
    [['search'=>'Akmeemana'],[3]],
    [['search'=>'සුනිතා'],[3]],
    [['search'=>'சுனிதா'],[4]],
    [['search'=>"' OR 1=1 --"],[]],
    [['search'=>'%'],[]],
    [['search'=>'_'],[]],
    [['status'=>['active'],'role'=>['admin'],'search'=>['x']],[1,2,3,4]],
] as [$input,$expected]) {
    userFilterCheck($lookup($input)===$expected, 'Unexpected user-filter results: ' . json_encode($input));
}
userFilterCheck(mb_strlen(widmsUserFilters(['search'=>str_repeat('a',300)])['search'])===120,'Search length was not bounded.');
echo "Admin user search/status/role checks passed, including Sinhala/Tamil and combined filters; operational data unchanged.\n";
