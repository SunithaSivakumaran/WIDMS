<?php
declare(strict_types=1);
requireRole('subject-officer');
require_once __DIR__.'/../../config/database.php';
require_once __DIR__.'/../../includes/activity.php';
require_once __DIR__.'/../../includes/prohibited-item-selector.php';
require_once __DIR__.'/../../includes/spectacle-categories.php';
$activePage='eligibility-rules';$db=database();$errors=[];$success=(string)($_SESSION['flash_success']??'');unset($_SESSION['flash_success']);$ruleId=filter_input(INPUT_GET,'rule_id',FILTER_VALIDATE_INT)?:filter_input(INPUT_POST,'rule_id',FILTER_VALIDATE_INT);
if(!$ruleId){http_response_code(404);exit('Eligibility rule not found.');}
function configuredBeneficiaryFields(string $raw):array{
 $raw=trim($raw);if($raw==='')return [];$fields=json_decode($raw,true,16,JSON_THROW_ON_ERROR);if(!is_array($fields)||!array_is_list($fields)||count($fields)>10)throw new RuntimeException('Add up to 10 beneficiary information fields.');$clean=[];$names=[];
 foreach($fields as $field){$label=preg_replace('/\s+/',' ',trim((string)($field['label']??'')));$type=(string)($field['type']??'');if(mb_strlen($label)<2||mb_strlen($label)>100||!in_array($type,['text','number','date','image','pdf'],true))throw new RuntimeException('Each beneficiary information field needs a valid name and type.');$key=mb_strtolower($label);if(isset($names[$key]))throw new RuntimeException('Beneficiary information field names must be different.');$names[$key]=true;$clean[]=['label'=>$label,'type'=>$type];}
 return $clean;
}

$spectacleActions=['save-spectacle-category','toggle-spectacle-category','delete-spectacle-category'];
if (($_SERVER['REQUEST_METHOD']??'GET')==='POST' && in_array((string)($_POST['action']??''),$spectacleActions,true)) {
 try {
  if(!verifyCsrfToken((string)($_POST['csrf_token']??'')))throw new RuntimeException('Your session expired. Refresh and try again.');
  $db->beginTransaction();
  $ruleCheck=$db->prepare('SELECT item.item_name FROM disability_aid_items rule JOIN inventory_items item ON item.id=rule.item_id WHERE rule.id=? AND rule.is_system=1 FOR UPDATE');
  $ruleCheck->execute([$ruleId]);
  $itemName=$ruleCheck->fetchColumn();
  if($itemName===false||!widmsIsSpectacleItem((string)$itemName))throw new RuntimeException('Spectacle types can only be managed from the built-in Spectacles rule.');
  $action=(string)$_POST['action'];
  $categoryId=filter_var($_POST['spectacle_category_id']??null,FILTER_VALIDATE_INT)?:0;
  $reference='SPECTACLE-TYPE-'.$categoryId;
  if($action==='save-spectacle-category') {
   $categoryName=preg_replace('/\s+/u',' ',trim((string)($_POST['spectacle_category_name']??'')));
   if(mb_strlen($categoryName)<2||mb_strlen($categoryName)>100)throw new RuntimeException('Enter a spectacle type between 2 and 100 characters.');
   if($categoryId>0) {
    $check=$db->prepare('SELECT id FROM spectacle_categories WHERE id=? FOR UPDATE');$check->execute([$categoryId]);
    if(!$check->fetchColumn())throw new RuntimeException('The selected spectacle type no longer exists.');
    $db->prepare('UPDATE spectacle_categories SET name=? WHERE id=?')->execute([$categoryName,$categoryId]);
   } else {
    $nextOrder=min(255,1+(int)$db->query('SELECT COALESCE(MAX(display_order),0) FROM spectacle_categories')->fetchColumn());
    $db->prepare('INSERT INTO spectacle_categories(name,display_order) VALUES(?,?)')->execute([$categoryName,$nextOrder]);
    $categoryId=(int)$db->lastInsertId();$reference='SPECTACLE-TYPE-'.$categoryId;
   }
   $message='Spectacle type saved.';
  } else {
   if($categoryId<1)throw new RuntimeException('Select a spectacle type.');
   $check=$db->prepare('SELECT name FROM spectacle_categories WHERE id=? FOR UPDATE');$check->execute([$categoryId]);
   if($check->fetchColumn()===false)throw new RuntimeException('The selected spectacle type no longer exists.');
   if($action==='delete-spectacle-category') {
    if(widmsSpectacleCategoryInUse($db,$categoryId))throw new RuntimeException('This spectacle type is already used. Deactivate it instead.');
    $db->prepare('DELETE FROM spectacle_categories WHERE id=?')->execute([$categoryId]);
    $message='Unused spectacle type removed.';
   } else {
    $db->prepare("UPDATE spectacle_categories SET status=IF(status='active','inactive','active') WHERE id=?")->execute([$categoryId]);
    $message='Spectacle type availability updated.';
   }
  }
  $db->commit();
  logActivity('Disability Aid Configuration',$message,$reference,$action==='delete-spectacle-category'?'deleted':'updated');
  $_SESSION['flash_success']=$message;unset($_SESSION['csrf_token']);
  header('Location: dashboard.php?page=edit-aid-rule&rule_id='.(int)$ruleId.'#spectacle-types');exit;
 } catch(Throwable $e) {
  if($db->inTransaction())$db->rollBack();
  error_log($e->getMessage());
  $errors[]=$e instanceof RuntimeException?$e->getMessage():($e instanceof PDOException&&$e->getCode()==='23000'?'A spectacle type with this name already exists.':'Unable to update spectacle types.');
 }
}

// Save the optional beneficiary field with the editable item rule, not as a separate hard-coded item setting.
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='save-rule-with-detail'){
 if(!verifyCsrfToken((string)($_POST['csrf_token']??'')))$errors[]='Your session expired. Refresh and try again.';
 else try{
  $disability=filter_input(INPUT_POST,'disability_type_id',FILTER_VALIDATE_INT);$name=preg_replace('/\s+/',' ',trim((string)($_POST['item_name']??'')));$variety=preg_replace('/\s+/',' ',trim((string)($_POST['variety']??'')));$value=filter_input(INPUT_POST,'restriction_value',FILTER_VALIDATE_INT);$unit=(string)($_POST['restriction_unit']??'');$blocked=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['prohibited_item_ids']??[])))));$detailFields=configuredBeneficiaryFields((string)($_POST['beneficiary_fields_json']??''));$firstField=$detailFields[0]??['label'=>null,'type'=>'text'];$detailLabel=$firstField['label'];$detailType=in_array($firstField['type'],['text','number'],true)?$firstField['type']:'text';
  if(!$disability||mb_strlen($name)<2||mb_strlen($name)>100)throw new RuntimeException('Enter a valid disability and item name.');if($value===false||$value<0||$value>100||!in_array($unit,['months','years'],true))throw new RuntimeException('Enter a valid probation period and unit.');$months=$unit==='years'?$value*12:$value;
  $db->beginTransaction();
  $q=$db->prepare('SELECT rule.item_id,rule.is_system,rule.disability_type_id,item.item_name FROM disability_aid_items rule JOIN inventory_items item ON item.id=rule.item_id WHERE rule.id=:id FOR UPDATE');
  $q->execute(['id'=>$ruleId]);
  $storedRule=$q->fetch();
  $itemId=(int)($storedRule['item_id']??0);
  if(!$storedRule||!$itemId)throw new RuntimeException('The eligibility rule no longer exists.');
  $isSystemRule=(int)$storedRule['is_system']===1;
  $isContactLensRule=(bool)preg_match('/contact\s*lens/i',(string)$storedRule['item_name']);
  $q=$db->prepare("SELECT id FROM disability_types WHERE id=:id AND status='active'");
  $q->execute(['id'=>$isSystemRule?(int)$storedRule['disability_type_id']:$disability]);
  if(!$q->fetchColumn())throw new RuntimeException('The selected disability type is unavailable.');
  if(!$isSystemRule)$db->prepare('UPDATE inventory_items SET item_name=:name,variety=:variety WHERE id=:id')->execute(['name'=>$name,'variety'=>$variety,'id'=>$itemId]);
  if($isSystemRule){
   $isSpectacleRule=widmsIsSpectacleItem((string)$storedRule['item_name']);
   $systemLabel=$isSpectacleRule?'Spectacle Type':($isContactLensRule?null:$detailLabel);
   $systemType=$isSpectacleRule||$isContactLensRule?'text':$detailType;
   $db->prepare("UPDATE disability_aid_items SET restriction_months=:months,beneficiary_field_label=:label,beneficiary_field_type=:type,status='active' WHERE id=:id")->execute(['months'=>$months,'label'=>$systemLabel,'type'=>$systemType,'id'=>$ruleId]);
   if($isContactLensRule)$db->prepare("DELETE FROM disability_aid_item_fields WHERE disability_aid_item_id=:rule AND is_system=1 AND LOWER(TRIM(field_label)) IN ('power','prescription power','prescribed power')")->execute(['rule'=>$ruleId]);
   $q=$db->prepare('SELECT LOWER(field_label) FROM disability_aid_item_fields WHERE disability_aid_item_id=:rule AND is_system=1');
   $q->execute(['rule'=>$ruleId]);
   $systemFieldNames=array_fill_keys($q->fetchAll(PDO::FETCH_COLUMN),true);
   $db->prepare('DELETE FROM disability_aid_item_fields WHERE disability_aid_item_id=:rule AND is_system=0')->execute(['rule'=>$ruleId]);
  }else{
   $db->prepare("UPDATE disability_aid_items SET disability_type_id=:disability,restriction_months=:months,beneficiary_field_label=:label,beneficiary_field_type=:type,status='active' WHERE id=:id")->execute(['disability'=>$disability,'months'=>$months,'label'=>$detailLabel,'type'=>$detailType,'id'=>$ruleId]);
   $systemFieldNames=[];
   $db->prepare('DELETE FROM disability_aid_item_fields WHERE disability_aid_item_id=:rule')->execute(['rule'=>$ruleId]);
  }
  $addField=$db->prepare('INSERT INTO disability_aid_item_fields(disability_aid_item_id,field_label,field_type,display_order) VALUES(:rule,:label,:type,:position)');
  $fieldPosition=$isSystemRule&&!$isContactLensRule?2:1;
  foreach($detailFields as $detailField){
   if(isset($systemFieldNames[mb_strtolower($detailField['label'])]))continue;
   $addField->execute(['rule'=>$ruleId,'label'=>$detailField['label'],'type'=>$detailField['type'],'position'=>$fieldPosition++]);
  }
  $db->prepare('DELETE FROM disability_item_prohibitions WHERE disability_aid_item_id=:id')->execute(['id'=>$ruleId]);
  $add=$db->prepare('INSERT INTO disability_item_prohibitions(disability_aid_item_id,prohibited_item_id) VALUES(:rule,:item)');
  foreach($blocked as $blockedItem)if($blockedItem!==$itemId)$add->execute(['rule'=>$ruleId,'item'=>$blockedItem]);
  if (preg_match('/contact\s*lens/i', (string) $storedRule['item_name']) || preg_match('/contact\s*lens/i', $name)) {
   $db->prepare("UPDATE disability_aid_items SET beneficiary_field_label=NULL,beneficiary_field_type='text' WHERE id=?")->execute([$ruleId]);
   $db->prepare("DELETE FROM disability_aid_item_fields WHERE disability_aid_item_id=? AND is_system=1 AND LOWER(TRIM(field_label)) IN ('power','prescription power','prescribed power')")->execute([$ruleId]);
  }
  $db->prepare('UPDATE inventory_items SET can_sso_distribute=:allowed,is_returnable=:returnable WHERE id=:item')->execute(['allowed'=>(string)($_POST['can_sso_distribute']??'')==='1'?1:0,'returnable'=>(string)($_POST['is_returnable']??'')==='1'?1:0,'item'=>$itemId]);
  $db->commit();logActivity('Disability Aid Configuration','Updated item rule and beneficiary fields','RULE-'.$ruleId);$_SESSION['flash_success']='Eligibility rule updated.';unset($_SESSION['csrf_token']);header('Location: dashboard.php?page=eligibility-rules');exit;
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log($e->getMessage());$errors[]=$e instanceof RuntimeException?$e->getMessage():'Unable to update the eligibility rule.';}
}

if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')!=='save-rule-with-detail'&&!in_array((string)($_POST['action']??''),$spectacleActions,true)){
 if(!verifyCsrfToken((string)($_POST['csrf_token']??'')))$errors[]='Your session expired. Refresh and try again.';
 else try{
  $disability=filter_input(INPUT_POST,'disability_type_id',FILTER_VALIDATE_INT);$name=preg_replace('/\s+/',' ',trim((string)($_POST['item_name']??'')));$variety=preg_replace('/\s+/',' ',trim((string)($_POST['variety']??'')));$value=filter_input(INPUT_POST,'restriction_value',FILTER_VALIDATE_INT);$unit=(string)($_POST['restriction_unit']??'');$blocked=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['prohibited_item_ids']??[])))));
  if(!$disability||mb_strlen($name)<2||mb_strlen($name)>100)throw new RuntimeException('Enter a valid disability and item name.');if($value===false||$value<0||$value>100||!in_array($unit,['months','years'],true))throw new RuntimeException('Enter a valid probation period and unit.');$months=$unit==='years'?$value*12:$value;
  $db->beginTransaction();$q=$db->prepare('SELECT item_id,is_system,disability_type_id FROM disability_aid_items WHERE id=:id FOR UPDATE');$q->execute(['id'=>$ruleId]);$storedRule=$q->fetch();$itemId=(int)($storedRule['item_id']??0);if(!$storedRule||!$itemId)throw new RuntimeException('The eligibility rule no longer exists.');$isSystemRule=(int)$storedRule['is_system']===1;
  $q=$db->prepare("SELECT id FROM disability_types WHERE id=:id AND status='active'");$q->execute(['id'=>$isSystemRule?(int)$storedRule['disability_type_id']:$disability]);if(!$q->fetchColumn())throw new RuntimeException('The selected disability type is unavailable.');
  if(!$isSystemRule)$db->prepare('UPDATE inventory_items SET item_name=:name,variety=:variety WHERE id=:id')->execute(['name'=>$name,'variety'=>$variety,'id'=>$itemId]);
  $db->prepare('UPDATE inventory_items SET can_sso_distribute=:allowed,is_returnable=:returnable WHERE id=:item')->execute(['allowed'=>(string)($_POST['can_sso_distribute']??'')==='1'?1:0,'returnable'=>(string)($_POST['is_returnable']??'')==='1'?1:0,'item'=>$itemId]);
  if($isSystemRule)$db->prepare("UPDATE disability_aid_items SET restriction_months=:months,status='active' WHERE id=:id")->execute(['months'=>$months,'id'=>$ruleId]);else $db->prepare("UPDATE disability_aid_items SET disability_type_id=:disability,restriction_months=:months,status='active' WHERE id=:id")->execute(['disability'=>$disability,'months'=>$months,'id'=>$ruleId]);
  $db->prepare('DELETE FROM disability_item_prohibitions WHERE disability_aid_item_id=:id')->execute(['id'=>$ruleId]);$add=$db->prepare('INSERT INTO disability_item_prohibitions(disability_aid_item_id,prohibited_item_id) VALUES(:rule,:item)');foreach($blocked as $blockedItem)if($blockedItem!==$itemId)$add->execute(['rule'=>$ruleId,'item'=>$blockedItem]);
  $db->commit();logActivity('Disability Aid Configuration','Updated full eligibility rule','RULE-'.$ruleId);$_SESSION['flash_success']='Eligibility rule updated.';unset($_SESSION['csrf_token']);header('Location: dashboard.php?page=eligibility-rules');exit;
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log($e->getMessage());$errors[]=$e instanceof RuntimeException?$e->getMessage():($e instanceof PDOException&&$e->getCode()==='23000'?'That item or disability rule already exists.':'Unable to update the eligibility rule.');}
}

try{$q=$db->prepare('SELECT dai.*,dt.name disability_name,i.item_name,i.variety,i.can_sso_distribute,i.is_returnable FROM disability_aid_items dai JOIN disability_types dt ON dt.id=dai.disability_type_id JOIN inventory_items i ON i.id=dai.item_id WHERE dai.id=:id');$q->execute(['id'=>$ruleId]);$rule=$q->fetch();if(!$rule){http_response_code(404);exit('Eligibility rule not found.');}$disabilities=$db->query("SELECT id,name FROM disability_types WHERE status='active' ORDER BY name")->fetchAll();$aidItems=$db->query('SELECT id,item_name,variety FROM inventory_items ORDER BY item_name,variety')->fetchAll();$q=$db->prepare('SELECT prohibited_item_id FROM disability_item_prohibitions WHERE disability_aid_item_id=:id');$q->execute(['id'=>$ruleId]);$selected=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));}catch(PDOException $e){error_log($e->getMessage());exit('Unable to load the eligibility rule.');}
$isSpectacleRule=(int)$rule['is_system']===1&&widmsIsSpectacleItem((string)$rule['item_name']);
$isContactLensRule=(bool)preg_match('/contact\s*lens/i',(string)$rule['item_name']);
$spectacleCategories=$isSpectacleRule?widmsSpectacleCategories($db,false):[];
if ((int)$rule['is_system'] === 1) $rule['item_name'] = widmsAidItemName((string)$rule['item_name']);
$years=(int)$rule['restriction_months']>=12&&(int)$rule['restriction_months']%12===0;$period=$years?(int)$rule['restriction_months']/12:(int)$rule['restriction_months'];
?>
<!doctype html><html lang="<?=htmlspecialchars(widmsLanguage(),ENT_QUOTES,'UTF-8')?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Edit Eligibility Rule | SWPCS</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="assets/css/admin-dashboard.css?v=<?=filemtime(__DIR__.'/../../public/assets/css/admin-dashboard.css')?>" rel="stylesheet"></head><body><?php require __DIR__.'/../../includes/subject-officer-sidebar.php';?><div class="admin-shell"><header class="topbar"><h1>Edit Eligibility Rule</h1></header><main class="dashboard-content edit-aid-rule-page"><?php if($errors):?><div class="alert alert-danger"><?=htmlspecialchars(implode(' ',$errors),ENT_QUOTES,'UTF-8')?></div><?php endif;?>
<!-- The dedicated editor keeps all rule details visible without expanding a crowded table row. -->
<div class="edit-rule-heading"><div><a class="edit-rule-back-button" href="dashboard.php?page=eligibility-rules"><span aria-hidden="true">&larr;</span> Back to Eligibility Rules</a><h2><?=htmlspecialchars($rule['item_name'],ENT_QUOTES,'UTF-8')?></h2><p><?=(int)$rule['is_system']===1?'The disability and item are built in. You can edit the probation, prohibited items, and additional beneficiary information.':'Review and change every detail associated with this eligibility rule.'?></p></div><span class="user-status active"><?=(int)$rule['is_system']===1?htmlspecialchars(t('Built-in Item'),ENT_QUOTES,'UTF-8'):'Active Rule'?></span></div>
<form method="post" class="edit-aid-rule-form" id="edit-aid-rule-form" data-built-in-label="<?=htmlspecialchars(t('Built-in'),ENT_QUOTES,'UTF-8')?>" data-contact-lens="<?=$isContactLensRule?'1':'0'?>"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrfToken(),ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="rule_id" value="<?=(int)$ruleId?>"><section><h3>Item and Disability</h3><?php if((int)$rule['is_system']===1):?><p class="built-in-item-note"><?=htmlspecialchars(t('Built-in values are protected and cannot be renamed or moved to another disability.'),ENT_QUOTES,'UTF-8')?></p><?php endif;?><div class="edit-rule-grid"><label>Disability Type<select name="disability_type_id" required <?=(int)$rule['is_system']===1?'aria-readonly="true"':''?>><?php foreach($disabilities as $d):?><option value="<?=(int)$d['id']?>" <?=(int)$d['id']===(int)$rule['disability_type_id']?'selected':''?> <?=(int)$rule['is_system']===1&&(int)$d['id']!==(int)$rule['disability_type_id']?'disabled':''?>><?=htmlspecialchars($d['name'],ENT_QUOTES,'UTF-8')?></option><?php endforeach;?></select></label><label>Item Name<input name="item_name" value="<?=htmlspecialchars($rule['item_name'],ENT_QUOTES,'UTF-8')?>" maxlength="100" required <?=(int)$rule['is_system']===1?'readonly':''?>></label><label>Variety / Model<input name="variety" value="<?=htmlspecialchars($rule['variety'],ENT_QUOTES,'UTF-8')?>" maxlength="100" <?=(int)$rule['is_system']===1?'readonly':''?>></label></div></section>
<section><div class="aid-item-options">
<label class="aid-setting-option"><input type="checkbox" name="can_sso_distribute" value="1" <?=(int)$rule['can_sso_distribute']===1?'checked':''?>><span><?=htmlspecialchars(t('Allow SSO to distribute this aid'),ENT_QUOTES,'UTF-8')?></span></label>
<label class="aid-setting-option"><input type="checkbox" name="is_returnable" value="1" <?=(int)$rule['is_returnable']===1?'checked':''?>><span><?=htmlspecialchars(t('This aid can be returned'),ENT_QUOTES,'UTF-8')?></span></label>
</div></section>
<section><h3>Probation Period</h3><div class="edit-rule-grid compact"><label>Period<input type="number" name="restriction_value" min="0" max="100" value="<?=$period?>" required></label><label>Unit<select name="restriction_unit"><option value="months" <?=$years?'':'selected'?>>Months</option><option value="years" <?=$years?'selected':''?>>Years</option></select></label></div></section>
<section><h3>Items Prohibited During Probation</h3><p>The same item is always prohibited automatically. Select any additional items below.</p><div class="edit-prohibited-selector"><?php renderProhibitedItemSelector($aidItems, $selected, (int)$rule['item_id']); ?></div></section>
<?php if($errors): ?><div class="alert alert-danger" role="alert"><?=htmlspecialchars(implode(' ',$errors),ENT_QUOTES,'UTF-8')?></div><?php endif; ?>
<div class="edit-rule-actions"><a class="outline-action edit-rule-cancel-button" href="dashboard.php?page=eligibility-rules">Cancel</a><button class="admin-primary-action" type="submit">Save Changes</button></div></form>
<?php if($isSpectacleRule): ?>
<section class="spectacle-category-config" id="spectacle-types" aria-labelledby="spectacle-types-heading">
  <?php if($success): ?><div class="alert alert-success spectacle-category-save-message"><?=htmlspecialchars($success,ENT_QUOTES,'UTF-8')?></div><?php endif; ?>
  <div class="spectacle-category-config-header">
    <div><h2 id="spectacle-types-heading"><?=htmlspecialchars(t('Spectacle Types'),ENT_QUOTES,'UTF-8')?></h2><p><?=htmlspecialchars(t('These choices appear on every spectacle request and stock receipt.'),ENT_QUOTES,'UTF-8')?></p></div>
    <span class="spectacle-category-config-count"><?=count($spectacleCategories)?> choices</span>
  </div>
  <div class="spectacle-category-config-body">
    <form method="post" class="spectacle-category-editor" id="spectacle-category-form">
      <input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrfToken(),ENT_QUOTES,'UTF-8')?>">
      <input type="hidden" name="rule_id" value="<?=(int)$ruleId?>">
      <input type="hidden" name="action" value="save-spectacle-category">
      <fieldset><legend><?=htmlspecialchars(t('Select a type to rename, or add a new one'),ENT_QUOTES,'UTF-8')?></legend>
        <div class="spectacle-category-options">
          <label class="spectacle-category-option"><input type="radio" name="spectacle_category_id" value="0" data-spectacle-option-name="" checked><span><?=htmlspecialchars(t('Add new type'),ENT_QUOTES,'UTF-8')?></span></label>
          <?php foreach($spectacleCategories as $category): ?>
          <label class="spectacle-category-option"><input type="radio" name="spectacle_category_id" value="<?=(int)$category['id']?>" data-spectacle-option-name="<?=htmlspecialchars($category['name'],ENT_QUOTES,'UTF-8')?>"><span><?=htmlspecialchars(t($category['name']),ENT_QUOTES,'UTF-8')?></span></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <label class="spectacle-category-name"><?=htmlspecialchars(t('Type name'),ENT_QUOTES,'UTF-8')?><input name="spectacle_category_name" maxlength="100" required autocomplete="off"></label>
      <button class="admin-primary-action" type="submit"><?=htmlspecialchars(t('Save Type'),ENT_QUOTES,'UTF-8')?></button>
    </form>
    <div class="spectacle-category-availability" aria-label="Spectacle type availability">
      <h3>Availability</h3>
      <?php foreach($spectacleCategories as $category): $inUse=widmsSpectacleCategoryInUse($db,(int)$category['id']); ?>
      <div class="spectacle-category-availability-row">
        <span class="spectacle-category-availability-name"><?=htmlspecialchars(t($category['name']),ENT_QUOTES,'UTF-8')?></span>
        <span class="spectacle-category-status <?=($category['status']==='active'?'is-active':'is-inactive')?>"><?=htmlspecialchars(t($category['status']==='active'?'Active':'Inactive'),ENT_QUOTES,'UTF-8')?></span>
        <form method="post" class="spectacle-category-row-actions">
          <input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrfToken(),ENT_QUOTES,'UTF-8')?>">
          <input type="hidden" name="rule_id" value="<?=(int)$ruleId?>">
          <input type="hidden" name="spectacle_category_id" value="<?=(int)$category['id']?>">
          <button class="outline-action" type="submit" name="action" value="toggle-spectacle-category"><?=htmlspecialchars(t($category['status']==='active'?'Deactivate':'Activate'),ENT_QUOTES,'UTF-8')?></button>
          <?php if(!$inUse): ?><button class="outline-action spectacle-category-remove" type="submit" name="action" value="delete-spectacle-category" data-spectacle-remove data-category-name="<?=htmlspecialchars($category['name'],ENT_QUOTES,'UTF-8')?>"><?=htmlspecialchars(t('Remove'),ENT_QUOTES,'UTF-8')?></button><?php endif; ?>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<dialog class="spectacle-category-remove-dialog" id="spectacle-category-remove-dialog" aria-labelledby="spectacle-category-remove-title">
  <h2 id="spectacle-category-remove-title"><?=htmlspecialchars(t('Remove'),ENT_QUOTES,'UTF-8')?> <?=htmlspecialchars(t('Spectacle Type'),ENT_QUOTES,'UTF-8')?>?</h2>
  <p><?=htmlspecialchars(t('Remove this unused spectacle type?'),ENT_QUOTES,'UTF-8')?> <strong data-spectacle-remove-name></strong></p>
  <div class="spectacle-category-remove-dialog-actions"><button type="button" class="outline-action" data-spectacle-remove-cancel>Cancel</button><button type="button" class="spectacle-category-confirm-remove" data-spectacle-remove-confirm><?=htmlspecialchars(t('Remove'),ENT_QUOTES,'UTF-8')?></button></div>
</dialog>
<?php endif; ?>
</main></div><script src="assets/js/admin-dashboard.js?v=<?=filemtime(__DIR__.'/../../public/assets/js/admin-dashboard.js')?>"></script><script src="assets/js/searchable-multi-select.js?v=3"></script><?php if($isSpectacleRule): ?><script src="assets/js/spectacle-category-config.js"></script><?php endif; ?></body></html>
