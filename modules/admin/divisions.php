<?php
declare(strict_types=1);
requireRole('admin');
require_once __DIR__.'/../../config/database.php';
require_once __DIR__.'/../../includes/activity.php';
$activePage='divisions'; $errors=[]; $success=(string)($_SESSION['flash_success']??''); unset($_SESSION['flash_success']);

if($_SERVER['REQUEST_METHOD']==='POST'){
    $action=(string)($_POST['action']??''); $name=trim((string)($_POST['name']??''));
    if(!verifyCsrfToken((string)($_POST['csrf_token']??'')))$errors[]='Your session expired. Refresh and try again.';
    if(mb_strlen($name)<2||mb_strlen($name)>120)$errors[]='Enter a valid name between 2 and 120 characters.';
    if($errors===[]){
        try{
            if($action==='add-district'){$sql='INSERT INTO districts(name,created_by) VALUES(:name,:user)';$params=['name'=>$name,'user'=>$_SESSION['user_id']];$label='District';}
            elseif($action==='add-ds'){$parent=filter_input(INPUT_POST,'district_id',FILTER_VALIDATE_INT);if(!$parent)throw new RuntimeException('Select a district.');$sql='INSERT INTO ds_divisions(district_id,name,created_by) VALUES(:parent,:name,:user)';$params=['parent'=>$parent,'name'=>$name,'user'=>$_SESSION['user_id']];$label='DS Division';}
            elseif($action==='add-gn'){$parent=filter_input(INPUT_POST,'ds_division_id',FILTER_VALIDATE_INT);if(!$parent)throw new RuntimeException('Select a DS Division.');$sql='INSERT INTO gn_divisions(ds_division_id,name,created_by) VALUES(:parent,:name,:user)';$params=['parent'=>$parent,'name'=>$name,'user'=>$_SESSION['user_id']];$label='GN Division';}
            else throw new RuntimeException('Invalid geography action.');
            $statement=database()->prepare($sql);$statement->execute($params);$id=(int)database()->lastInsertId();
            logActivity('Divisions',"Added $label — $name",strtoupper(substr($label,0,2)).'-'.$id,'done');
            $_SESSION['flash_success']="$label added successfully.";unset($_SESSION['csrf_token']);header('Location: dashboard.php?page=divisions');exit;
        }catch(Throwable $exception){error_log($exception->getMessage());$errors[]=$exception instanceof RuntimeException?$exception->getMessage():($exception instanceof PDOException&&$exception->getCode()==='23000'?'That geography record already exists.':'Unable to save geography. Import database/migration_master_data.sql.');}
    }
}
$districts=$dsDivisions=$rows=[];
try{$districts=database()->query("SELECT id,name FROM districts WHERE status='active' ORDER BY name")->fetchAll();$dsDivisions=database()->query("SELECT ds.id,CONCAT(d.name,' — ',ds.name) name FROM ds_divisions ds JOIN districts d ON d.id=ds.district_id WHERE ds.status='active' ORDER BY d.name,ds.name")->fetchAll();$rows=database()->query("SELECT d.name district,ds.name ds_division,GROUP_CONCAT(gn.name ORDER BY gn.name SEPARATOR ', ') gn_divisions,ds.status,(SELECT COUNT(*) FROM users u WHERE u.ds_division_id=ds.id AND u.role='social-service-officer') officers FROM ds_divisions ds JOIN districts d ON d.id=ds.district_id LEFT JOIN gn_divisions gn ON gn.ds_division_id=ds.id GROUP BY ds.id ORDER BY d.name,ds.name")->fetchAll();}catch(PDOException $e){error_log($e->getMessage());$errors[]='Geography is unavailable. Import database/migration_master_data.sql.';}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Division Management | WIDMS</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="assets/css/admin-dashboard.css" rel="stylesheet"></head><body><?php require __DIR__.'/../../includes/admin-sidebar.php';?><div class="admin-shell"><header class="topbar"><div class="d-flex align-items-center gap-3"><button class="menu-button" id="menu-button">&#9776;</button><h1>Division Management</h1></div></header><main class="dashboard-content division-workflow-page">
<?php if($success):?><div class="alert alert-success"><?=htmlspecialchars($success,ENT_QUOTES,'UTF-8')?></div><?php endif;?><?php if($errors):?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $error):?><li><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></li><?php endforeach;?></ul></div><?php endif;?>
<section class="geography-form-grid">
<form method="post" class="admin-data-card compact-master-form"><h2>Add District</h2><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrfToken(),ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="action" value="add-district"><label>District Name<input name="name" required maxlength="120"></label><button class="admin-primary-action">Add District</button></form>
<form method="post" class="admin-data-card compact-master-form"><h2>Add DS Division</h2><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrfToken(),ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="action" value="add-ds"><label>District<select name="district_id" required><option value="">Select district</option><?php foreach($districts as $row):?><option value="<?=(int)$row['id']?>"><?=htmlspecialchars($row['name'],ENT_QUOTES,'UTF-8')?></option><?php endforeach;?></select></label><label>DS Division Name<input name="name" required maxlength="120"></label><button class="admin-primary-action">Add DS Division</button></form>
<form method="post" class="admin-data-card compact-master-form"><h2>Add GN Division</h2><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrfToken(),ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="action" value="add-gn"><label>DS Division<select name="ds_division_id" required><option value="">Select DS Division</option><?php foreach($dsDivisions as $row):?><option value="<?=(int)$row['id']?>"><?=htmlspecialchars($row['name'],ENT_QUOTES,'UTF-8')?></option><?php endforeach;?></select></label><label>GN Division Name<input name="name" required maxlength="120"></label><button class="admin-primary-action">Add GN Division</button></form>
</section>
<section class="admin-data-card geography-table-card"><div class="admin-data-header"><h2>District and Division Hierarchy</h2></div><div class="admin-data-table-wrap"><table class="admin-data-table"><thead><tr><th>District</th><th>DS Division</th><th>GN Divisions</th><th>Assigned Social Service Officers</th><th>Status</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="5" class="admin-empty-row">No divisions available.</td></tr><?php else:foreach($rows as $row):?><tr><td><?=htmlspecialchars($row['district'],ENT_QUOTES,'UTF-8')?></td><td><strong><?=htmlspecialchars($row['ds_division'],ENT_QUOTES,'UTF-8')?></strong></td><td><?=htmlspecialchars($row['gn_divisions']?:'None',ENT_QUOTES,'UTF-8')?></td><td><?=(int)$row['officers']?></td><td><?=ucfirst($row['status'])?></td></tr><?php endforeach;endif;?></tbody></table></div></section>
</main></div><script src="assets/js/admin-dashboard.js"></script></body></html>
