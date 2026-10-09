<?php
declare(strict_types=1);
requireRole('admin');
require_once __DIR__.'/../../config/database.php';
require_once __DIR__.'/../../includes/activity.php';

$activePage='divisions';$errors=[];$success=(string)($_SESSION['flash_success']??'');unset($_SESSION['flash_success']);$db=database();
$districtFilterId=filter_var($_GET['district_id']??null,FILTER_VALIDATE_INT)?:null;
$dsFilterId=filter_var($_GET['ds_division_id']??null,FILTER_VALIDATE_INT)?:null;
$returnParams=['page'=>'divisions'];
if($districtFilterId)$returnParams['district_id']=$districtFilterId;
if($dsFilterId)$returnParams['ds_division_id']=$dsFilterId;
$returnUrl='dashboard.php?'.http_build_query($returnParams);
if($_SERVER['REQUEST_METHOD']==='POST'){
    $action=(string)($_POST['action']??'');$officerId=filter_input(INPUT_POST,'officer_id',FILTER_VALIDATE_INT);$reason=trim((string)($_POST['deactivation_reason']??''));
    if(!verifyCsrfToken((string)($_POST['csrf_token']??'')))$errors[]=t('Your session expired. Refresh and try again.');
    elseif(!$officerId||$action!=='deactivate')$errors[]=t('Invalid SSO action.');
    elseif($reason==='')$errors[]=t('A deactivation reason is required.');
    else{try{
        $db->beginTransaction();
        // Lock the officer so concurrent admin actions cannot create conflicting states.
        $find=$db->prepare("SELECT id,full_name,status,ds_division_id FROM users WHERE id=:id AND role='social-service-officer' FOR UPDATE");$find->execute(['id'=>$officerId]);$officer=$find->fetch();
        if(!$officer)throw new RuntimeException(t('Social Service Officer not found.'));
        if($officer['status']!=='active')throw new RuntimeException(t('This Social Service Officer is already deactivated.'));
        $db->prepare("UPDATE users SET status='inactive',deactivation_reason=:reason,deactivated_by=:admin,deactivated_at=NOW() WHERE id=:id")->execute(['reason'=>$reason,'admin'=>$_SESSION['user_id'],'id'=>$officerId]);
        $message=t('Social Service Officer deactivated successfully.');
        $db->commit();logActivity('SSO Assignments',$message,'USR-'.$officerId,$action);$_SESSION['flash_success']=$message;unset($_SESSION['csrf_token']);header('Location: '.$returnUrl);exit;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log($e->getMessage());$errors[]=$e instanceof RuntimeException?$e->getMessage():t('Unable to update the SSO assignment. Run the latest migration.');}}
}
$rows=$districtOptions=$dsOptions=[];try{
    $districtOptions=$db->query("SELECT id,name FROM districts WHERE status='active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $dsOptions=$db->query("SELECT ds.id,ds.district_id,ds.name FROM ds_divisions ds JOIN districts d ON d.id=ds.district_id WHERE ds.status='active' AND d.status='active' ORDER BY ds.name")->fetchAll(PDO::FETCH_ASSOC);
    if($districtFilterId&&!in_array($districtFilterId,array_map('intval',array_column($districtOptions,'id')),true))$districtFilterId=null;
    if(!$districtFilterId||!array_filter($dsOptions,static fn(array $ds):bool=>(int)$ds['id']===$dsFilterId&&(int)$ds['district_id']===$districtFilterId))$dsFilterId=null;
    // Keep uncovered divisions visible, but never join a deactivated officer into this assignment list.
    $sql="SELECT d.name district,ds.name ds_division,ds.division_type,u.id officer_id,u.full_name officer_name,COALESCE(u.email,u.username) officer_email,u.phone FROM ds_divisions ds JOIN districts d ON d.id=ds.district_id LEFT JOIN users u ON u.ds_division_id=ds.id AND u.role='social-service-officer' AND u.status='active' WHERE d.status='active' AND ds.status='active'";
    $parameters=[];
    if($districtFilterId){$sql.=' AND d.id=:district_id';$parameters['district_id']=$districtFilterId;}
    if($dsFilterId){$sql.=' AND ds.id=:ds_division_id';$parameters['ds_division_id']=$dsFilterId;}
    $sql.=' ORDER BY d.name,ds.division_type,ds.name,u.full_name';
    $query=$db->prepare($sql);$query->execute($parameters);$rows=$query->fetchAll(PDO::FETCH_ASSOC);
}catch(PDOException $e){error_log($e->getMessage());$errors[]=t('SSO assignments are unavailable. Run the latest migration.');}
?>
<!doctype html><html lang="<?=htmlspecialchars(widmsLanguage(),ENT_QUOTES,'UTF-8')?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=htmlspecialchars(t('SSO Division Assignments'),ENT_QUOTES,'UTF-8')?> | SWPCS</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="assets/css/admin-dashboard.css?v=69" rel="stylesheet"></head><body>
<?php require __DIR__.'/../../includes/admin-sidebar.php';?><div class="admin-shell"><header class="topbar"><div class="d-flex align-items-center gap-3"><button class="menu-button" id="menu-button">&#9776;</button><h1><?=htmlspecialchars(t('SSO Division Assignments'),ENT_QUOTES,'UTF-8')?></h1></div></header><main class="dashboard-content division-workflow-page">
<?php if($success!==''):?><div class="alert alert-success"><?=htmlspecialchars($success,ENT_QUOTES,'UTF-8')?></div><?php endif;?><?php if($errors!==[]):?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $error):?><li><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></li><?php endforeach;?></ul></div><?php endif;?>
<section class="admin-data-card geography-table-card">
    <div class="admin-data-header"><div><h2><?=htmlspecialchars(t('Approved SSO by DS Division'),ENT_QUOTES,'UTF-8')?></h2><p><?=htmlspecialchars(t('Review active Social Service Officer assignments.'),ENT_QUOTES,'UTF-8')?></p></div></div>
    <form method="get" action="dashboard.php" class="division-filters" role="search">
        <input type="hidden" name="page" value="divisions">
        <label><span><?=htmlspecialchars(t('District'),ENT_QUOTES,'UTF-8')?></span><select name="district_id" id="sso-district-filter"><option value=""><?=htmlspecialchars(t('All districts'),ENT_QUOTES,'UTF-8')?></option><?php foreach($districtOptions as $district):?><option value="<?=(int)$district['id']?>" <?=$districtFilterId===(int)$district['id']?'selected':''?>><?=htmlspecialchars($district['name'],ENT_QUOTES,'UTF-8')?></option><?php endforeach;?></select></label>
        <label><span><?=htmlspecialchars(t('DS Division'),ENT_QUOTES,'UTF-8')?></span><select name="ds_division_id" id="sso-ds-filter" <?=$districtFilterId?'':'disabled'?>><option value=""><?=htmlspecialchars(t('All DS Divisions'),ENT_QUOTES,'UTF-8')?></option><?php foreach($dsOptions as $division):$divisionInDistrict=(int)$division['district_id']===$districtFilterId;?><option value="<?=(int)$division['id']?>" data-district-id="<?=(int)$division['district_id']?>" <?=$dsFilterId===(int)$division['id']?'selected':''?> <?=$divisionInDistrict?'':'hidden disabled'?>><?=htmlspecialchars($division['name'],ENT_QUOTES,'UTF-8')?></option><?php endforeach;?></select></label>
        <div class="division-filter-actions"><button type="submit" class="approve-button"><?=htmlspecialchars(t('Filter'),ENT_QUOTES,'UTF-8')?></button><a class="outline-action" href="dashboard.php?page=divisions"><?=htmlspecialchars(t('Clear filters'),ENT_QUOTES,'UTF-8')?></a></div>
    </form>
    <div class="admin-data-table-wrap"><table class="admin-data-table"><thead><tr><th><?=htmlspecialchars(t('District'))?></th><th><?=htmlspecialchars(t('DS Division'))?></th><th><?=htmlspecialchars(t('Approved SSO'))?></th><th><?=htmlspecialchars(t('Contact'))?></th><th><?=htmlspecialchars(t('Status'))?></th><th><?=htmlspecialchars(t('Action'))?></th></tr></thead><tbody>
    <?php if(!$rows):?><tr><td colspan="6" class="admin-empty-row"><?=htmlspecialchars(t($districtFilterId||$dsFilterId?'No DS Divisions match these filters.':'No DS Divisions available.'),ENT_QUOTES,'UTF-8')?></td></tr><?php else:foreach($rows as $row):?><tr><td><?=htmlspecialchars($row['district'],ENT_QUOTES,'UTF-8')?></td><td><strong><?=htmlspecialchars($row['ds_division'],ENT_QUOTES,'UTF-8')?></strong></td><td><?=htmlspecialchars($row['officer_name']?:t('No approved SSO'),ENT_QUOTES,'UTF-8')?></td><td><?=$row['officer_id']?htmlspecialchars($row['officer_email'].($row['phone']?' / '.$row['phone']:''),ENT_QUOTES,'UTF-8'):'—'?></td><td><?=$row['officer_id']?'<span class="user-status active">'.htmlspecialchars(t('Active'),ENT_QUOTES,'UTF-8').'</span>':'—'?></td><td><?php if($row['officer_id']):?><form method="post" action="<?=htmlspecialchars($returnUrl,ENT_QUOTES,'UTF-8')?>" class="sso-status-form"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrfToken(),ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="officer_id" value="<?=(int)$row['officer_id']?>"><input type="hidden" name="deactivation_reason" value=""><button class="reject-button sso-action-button" type="button" data-reason-trigger data-submit-name="action" data-submit-value="deactivate" data-dialog-title="<?=htmlspecialchars(t('Deactivate Social Service Officer'),ENT_QUOTES,'UTF-8')?>" data-dialog-confirm="<?=htmlspecialchars(t('Confirm deactivation'),ENT_QUOTES,'UTF-8')?>" data-reason-field="deactivation_reason"><?=htmlspecialchars(t('Deactivate'),ENT_QUOTES,'UTF-8')?></button></form><?php else:?>—<?php endif;?></td></tr><?php endforeach;endif;?></tbody></table></div>
</section>
</main></div><script src="assets/js/admin-dashboard.js"></script><script src="assets/js/admin-reason-dialog.js?v=1"></script><script>
(() => {
    const district = document.getElementById('sso-district-filter');
    const division = document.getElementById('sso-ds-filter');
    if (!district || !division) return;
    district.addEventListener('change', () => {
        division.value = '';
        division.disabled = district.value === '';
        for (const option of division.options) {
            if (!option.dataset.districtId) continue;
            const visible = option.dataset.districtId === district.value;
            option.hidden = !visible;
            option.disabled = !visible;
        }
    });
})();
</script></body></html>
