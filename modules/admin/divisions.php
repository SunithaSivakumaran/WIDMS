<?php
declare(strict_types=1);
requireRole('admin');
require_once __DIR__.'/../../config/database.php';
require_once __DIR__.'/../../includes/activity.php';

$activePage='divisions';$errors=[];$success=(string)($_SESSION['flash_success']??'');unset($_SESSION['flash_success']);$db=database();
if($_SERVER['REQUEST_METHOD']==='POST'){
    $action=(string)($_POST['action']??'');$officerId=filter_input(INPUT_POST,'officer_id',FILTER_VALIDATE_INT);$reason=trim((string)($_POST['deactivation_reason']??''));
    if(!verifyCsrfToken((string)($_POST['csrf_token']??'')))$errors[]=t('Your session expired. Refresh and try again.');
    elseif(!$officerId||!in_array($action,['deactivate','reactivate'],true))$errors[]=t('Invalid SSO action.');
    elseif($action==='deactivate'&&$reason==='')$errors[]=t('A deactivation reason is required.');
    else{try{
        $db->beginTransaction();
        // Lock the officer so concurrent admin actions cannot create conflicting states.
        $find=$db->prepare("SELECT id,full_name,status,ds_division_id FROM users WHERE id=:id AND role='social-service-officer' FOR UPDATE");$find->execute(['id'=>$officerId]);$officer=$find->fetch();
        if(!$officer)throw new RuntimeException(t('Social Service Officer not found.'));
        if($action==='deactivate'){
            if($officer['status']!=='active')throw new RuntimeException(t('This Social Service Officer is already deactivated.'));
            $db->prepare("UPDATE users SET status='inactive',deactivation_reason=:reason,deactivated_by=:admin,deactivated_at=NOW() WHERE id=:id")->execute(['reason'=>$reason,'admin'=>$_SESSION['user_id'],'id'=>$officerId]);
            $message=t('Social Service Officer deactivated successfully.');
        }else{
            // A DS Division may have only one active SSO.
            $conflict=$db->prepare("SELECT full_name FROM users WHERE role='social-service-officer' AND status='active' AND ds_division_id=:division AND id<>:id LIMIT 1 FOR UPDATE");$conflict->execute(['division'=>$officer['ds_division_id'],'id'=>$officerId]);$activeName=$conflict->fetchColumn();
            if($activeName)throw new RuntimeException(sprintf(t('%s is already active for this DS Division.'),$activeName));
            $db->prepare("UPDATE users SET status='active',deactivation_reason=NULL,deactivated_by=NULL,deactivated_at=NULL WHERE id=:id")->execute(['id'=>$officerId]);
            $message=t('Social Service Officer reactivated successfully.');
        }
        $db->commit();logActivity('SSO Assignments',$message,'USR-'.$officerId,$action);$_SESSION['flash_success']=$message;unset($_SESSION['csrf_token']);header('Location: dashboard.php?page=divisions');exit;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log($e->getMessage());$errors[]=$e instanceof RuntimeException?$e->getMessage():t('Unable to update the SSO assignment. Run the latest migration.');}}
}
$rows=[];try{
    // Empty divisions remain visible so missing SSO coverage is obvious.
    $rows=$db->query("SELECT d.name district,ds.name ds_division,ds.division_type,u.id officer_id,u.full_name officer_name,u.username officer_email,u.phone,u.status officer_status,u.deactivation_reason FROM ds_divisions ds JOIN districts d ON d.id=ds.district_id LEFT JOIN users u ON u.ds_division_id=ds.id AND u.role='social-service-officer' WHERE d.status='active' AND ds.status='active' ORDER BY d.name,ds.division_type,ds.name,FIELD(u.status,'active','inactive'),u.full_name")->fetchAll();
}catch(PDOException $e){error_log($e->getMessage());$errors[]=t('SSO assignments are unavailable. Run the latest migration.');}
?>
<!doctype html><html lang="<?=htmlspecialchars(widmsLanguage(),ENT_QUOTES,'UTF-8')?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=htmlspecialchars(t('SSO Division Assignments'),ENT_QUOTES,'UTF-8')?> | WIDMS</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="assets/css/admin-dashboard.css" rel="stylesheet"></head><body>
<?php require __DIR__.'/../../includes/admin-sidebar.php';?><div class="admin-shell"><header class="topbar"><div class="d-flex align-items-center gap-3"><button class="menu-button" id="menu-button">&#9776;</button><h1><?=htmlspecialchars(t('SSO Division Assignments'),ENT_QUOTES,'UTF-8')?></h1></div></header><main class="dashboard-content division-workflow-page">
<?php if($success!==''):?><div class="alert alert-success"><?=htmlspecialchars($success,ENT_QUOTES,'UTF-8')?></div><?php endif;?><?php if($errors!==[]):?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $error):?><li><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></li><?php endforeach;?></ul></div><?php endif;?>
<section class="admin-data-card geography-table-card"><div class="admin-data-header"><div><h2><?=htmlspecialchars(t('Approved SSO by DS Division'),ENT_QUOTES,'UTF-8')?></h2><p><?=htmlspecialchars(t('Review active and deactivated Social Service Officer assignments.'),ENT_QUOTES,'UTF-8')?></p></div></div><div class="admin-data-table-wrap"><table class="admin-data-table"><thead><tr><th><?=htmlspecialchars(t('District'))?></th><th><?=htmlspecialchars(t('DS Division'))?></th><th><?=htmlspecialchars(t('Approved SSO'))?></th><th><?=htmlspecialchars(t('Contact'))?></th><th><?=htmlspecialchars(t('Status'))?></th><th><?=htmlspecialchars(t('Reason'))?></th><th><?=htmlspecialchars(t('Action'))?></th></tr></thead><tbody>
<?php if(!$rows):?><tr><td colspan="7" class="admin-empty-row"><?=htmlspecialchars(t('No DS Divisions available.'))?></td></tr><?php else:foreach($rows as $row):?><tr><td><?=htmlspecialchars($row['district'])?></td><td><strong><?=htmlspecialchars($row['ds_division'])?></strong></td><td><?=htmlspecialchars($row['officer_name']?:t('No approved SSO'))?></td><td><?=$row['officer_id']?htmlspecialchars($row['officer_email'].($row['phone']?' / '.$row['phone']:'')):'—'?></td><td><?php if($row['officer_id']):?><span class="user-status <?=$row['officer_status']==='active'?'active':'inactive'?>"><?=htmlspecialchars(t($row['officer_status']==='active'?'Active':'Deactivated'))?></span><?php else:?>—<?php endif;?></td><td><?=htmlspecialchars($row['deactivation_reason']?:'—')?></td><td><?php if($row['officer_id']):?><form method="post" class="sso-status-form"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrfToken())?>"><input type="hidden" name="officer_id" value="<?=(int)$row['officer_id']?>"><?php if($row['officer_status']==='active'):?><input type="hidden" name="deactivation_reason" value=""><button class="reject-button sso-action-button" type="button" data-reason-trigger data-submit-name="action" data-submit-value="deactivate" data-dialog-title="<?=htmlspecialchars(t('Deactivate Social Service Officer'))?>" data-dialog-confirm="<?=htmlspecialchars(t('Confirm deactivation'))?>" data-reason-field="deactivation_reason"><?=htmlspecialchars(t('Deactivate'))?></button><?php else:?><button name="action" value="reactivate" class="approve-button sso-action-button"><?=htmlspecialchars(t('Reactivate'))?></button><?php endif;?></form><?php else:?>—<?php endif;?></td></tr><?php endforeach;endif;?></tbody></table></div></section>
</main></div><script src="assets/js/admin-dashboard.js"></script><script src="assets/js/admin-reason-dialog.js?v=1"></script></body></html>
