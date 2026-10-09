<?php
declare(strict_types=1);
require_once __DIR__.'/../../config/database.php';
require_once __DIR__.'/../../includes/spectacle-camps.php';
require_once __DIR__.'/../../includes/spectacle-camp-components.php';
require_once __DIR__.'/../../includes/spectacle-categories.php';
require_once __DIR__.'/../../includes/ui-messages.php';
header('Cache-Control: no-store');
$activePage=(string)($_GET['page']??'spectacle-camps');
$pageTitles=['spectacle-camp-new'=>'New Vision Camp','spectacle-camp-approvals'=>'Camp Approvals','spectacle-camp-stock-approvals'=>'Stock Approvals','spectacle-camp-releases'=>'Camp Release Requests'];
$title=$pageTitles[$activePage]??(($_SESSION['role']??'')==='subject-officer'?'All Vision Camps':'Camp History');
if ($activePage==='my-spectacle-camps') $title='My Camps';
$isRegistrationPage=$activePage==='spectacle-camp-register';
$isParticipantListPage=$activePage==='spectacle-camp-participants';
if ($isRegistrationPage) $title='Camp Beneficiary Registration';
if ($isParticipantListPage) $title='Registered Participants';
$campRoute=in_array($activePage,['my-spectacle-camps','spectacle-camp-register'],true) ? 'my-spectacle-camps' : 'spectacle-camps';
$participantFrom=in_array((string)($_GET['from']??''),['my-spectacle-camps','spectacle-camps'],true)
    ? (string)$_GET['from'] : ($activePage==='my-spectacle-camps'?'my-spectacle-camps':'spectacle-camps');
$error=''; $notice=(string)($_SESSION['camp_notice']??''); unset($_SESSION['camp_notice']);
$db=null; $camp=null; $restrictionMonths=0; $camps=$stockRequests=$participants=$balances=$districts=$divisions=$gnDivisions=$ssos=$receipts=$transfers=$distributions=$events=$spectacleCategories=$spectacleStockCategories=$receiptLines=$requestNeeds=[];
$campStockStatuses=[]; $campDistributionDates=[]; $campDistributionTimes=[]; $campDistributionPlaces=[]; $campSsoHandovers=[]; $campSsoHandoversForActor=[]; $subjectReady=[]; $participantNoticeSchedules=[]; $ssoCanDistributeCamp=false;
$ssoView=in_array((string)($_GET['sso_view']??''),['distribution','history'],true)?(string)$_GET['sso_view']:'';
$tab=is_string($_GET['tab']??null)?$_GET['tab']:'overview';
if (!in_array($tab,['overview','participants','stock','distribution','history'],true)) $tab='overview';
$campId=filter_var($_GET['camp_id']??null,FILTER_VALIDATE_INT)?:0;
$actor=null; $role=(string)($_SESSION['role']??'');
try {
    $db=database(); $actor=scActor($db,(int)($_SESSION['user_id']??0)); $role=$actor['role'];
    if ($role==='store-keeper' && $activePage!=='spectacle-camp-releases') {
        header('Location: dashboard.php?page=spectacle-camp-releases');
        exit;
    }
    // The Store Keeper has only the release queue, never the shared camp-detail page.
    if ($role==='store-keeper' && $activePage==='spectacle-camp-releases') $campId=0;
    $spectacleCategories=widmsSpectacleCategories($db);
    $spectacleStockCategories=widmsSpectacleCategories($db,false);
    if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
        if (!verifyCsrfToken((string)($_POST['csrf_token']??''))) throw new DomainException('Your session expired. Refresh the page and try again.');
        if ($isParticipantListPage && (!in_array($role,['subject-officer','social-service-officer'],true) || ($_POST['action']??'')!=='distribute' || (int)($_POST['camp_id']??0)!==$campId)) throw new DomainException('Invalid Vision Camp action.');
        if ($isRegistrationPage && (($_POST['action']??'')!=='save-participant' || (int)($_POST['camp_id']??0)!==$campId)) throw new DomainException('Invalid Vision Camp action.');
        if (($_POST['action']??'')==='save-participant' && !in_array($_POST['decision']??null,['approved','rejected'],true)) throw new DomainException('Select a participant decision.');
        $id=scPerform($db,(int)$actor['id'],(string)($_POST['action']??''),$_POST);
        $_SESSION['camp_notice']=match((string)$_POST['action']) {
            'save-participant'=>'Participant saved. You can add another beneficiary.',
            'distribute'=>'Spectacles distributed successfully.',
            default=>'Vision Camp updated successfully.',
        };
        if (($_POST['action']??'')==='save-participant') {
            header('Location: dashboard.php?page=spectacle-camp-participants&camp_id='.$id.'&from='.$participantFrom); exit;
        }
        if ($isParticipantListPage && ($_POST['action']??'')==='distribute') {
            header('Location: dashboard.php?page=spectacle-camp-participants&camp_id='.$id.'&from='.$participantFrom); exit;
        }
        if (($_POST['action']??'')==='conduct-camp') {
            header('Location: dashboard.php?page=spectacle-camp-register&camp_id='.$id.'&from='.$participantFrom); exit;
        }
        if ($role==='store-keeper') {
            header('Location: dashboard.php?page=spectacle-camp-releases'); exit;
        }
        if ($role==='admin' && ($_POST['action']??'')==='decide-stock-batch') {
            header('Location: dashboard.php?page=spectacle-camp-stock-approvals'); exit;
        }
        $nextPage=$role==='subject-officer' && in_array($activePage,['my-spectacle-camps','spectacle-camp-new'],true) ? 'my-spectacle-camps' : 'spectacle-camps';
        if (($_POST['action']??'')==='complete-camp') {
            header('Location: dashboard.php?page='.$nextPage); exit;
        }
        if (($_POST['action']??'')==='handover-to-sso') {
            header('Location: dashboard.php?page='.$nextPage); exit;
        }
        $nextTab=match((string)$_POST['action']) {
            'conduct-camp','save-participant','decide-participant'=>'participants',
            'receive-stock','request-stock','request-stock-batch','decide-stock','decide-stock-batch','release-stock','release-stock-batch'=>'stock',
            'distribute','transfer-stock','handover-to-sso'=>'distribution',default=>'overview',
        };
        header('Location: dashboard.php?page='.$nextPage.'&camp_id='.$id.'&tab='.$nextTab); exit;
    }
} catch (DomainException|BeneficiaryDivisionException $e) { $error=$e->getMessage(); }
catch (PDOException $e) {
    error_log('Vision Camp database error: '.$e->getCode());
    $error=(int)($e->errorInfo[1]??0)===1062?'This participant or receipt is already recorded for the camp.':'Unable to load Vision Camps. Run the latest database migration.';
} catch (Throwable $e) { error_log('Vision Camp action failed.'); $error='Unable to process the Vision Camp action.'; }
try {
    if ($db && $actor) {
        $scope='1=1'; $params=[];
        if (($activePage==='my-spectacle-camps' || $isRegistrationPage) && $role!=='subject-officer') throw new DomainException('Access denied.');
        if ($isParticipantListPage && !in_array($role,['subject-officer','social-service-officer','admin'],true)) throw new DomainException('Access denied.');
        if ($activePage==='my-spectacle-camps') {
            $scope='c.requested_by=?'; $params[]=$actor['id'];
        }
        if (($isRegistrationPage || $isParticipantListPage) && $campId<1) throw new DomainException('Select a Vision Camp from My Camps.');
        if ($role==='social-service-officer') {
            $scope="c.ds_division_id=? AND c.status IN ('approved','completed')";
            $params[]=$actor['ds_division_id']??0;
        }
        if ($role==='store-keeper') $scope="c.status IN ('approved','completed') AND c.conducted_at IS NOT NULL";
        if ($activePage==='spectacle-camp-approvals') $scope.=" AND c.status='pending'";
        $camps=scQuery($db,"SELECT c.*,d.name district_name,ds.name division_name,u.full_name requester_name,u.username requester_username,u.role requester_role,
            (SELECT e.actor_id FROM spectacle_camp_events e WHERE e.camp_id=c.id AND e.action='conduct-camp' ORDER BY e.id LIMIT 1) conductor_id,
            (SELECT conductor.full_name FROM spectacle_camp_events e JOIN users conductor ON conductor.id=e.actor_id WHERE e.camp_id=c.id AND e.action='conduct-camp' ORDER BY e.id LIMIT 1) conductor_name,
            (SELECT conductor.username FROM spectacle_camp_events e JOIN users conductor ON conductor.id=e.actor_id WHERE e.camp_id=c.id AND e.action='conduct-camp' ORDER BY e.id LIMIT 1) conductor_username,
            (SELECT COUNT(*) FROM spectacle_camp_participants p WHERE p.camp_id=c.id) participant_count,
            (SELECT COUNT(*) FROM spectacle_camp_participants p WHERE p.camp_id=c.id AND p.status='approved') approved_count,
            (SELECT COUNT(*) FROM spectacle_camp_participants p WHERE p.camp_id=c.id AND p.status='rejected') rejected_count,
            (SELECT COUNT(*) FROM spectacle_camp_distributions x WHERE x.camp_id=c.id) distributed_count
            FROM spectacle_camps c JOIN districts d ON d.id=c.district_id JOIN ds_divisions ds ON ds.id=c.ds_division_id JOIN users u ON u.id=c.requested_by WHERE $scope ORDER BY c.id DESC",$params)->fetchAll(PDO::FETCH_ASSOC);
        if ($camps) {
            $visibleIds=array_map(static fn(array $listedCamp):int=>(int)$listedCamp['id'],$camps);
            $placeholders=implode(',',array_fill(0,count($visibleIds),'?'));
            $statusRows=scQuery($db,"SELECT camp_id,batch_ref,id,status,planned_distribution_date,planned_distribution_time,planned_distribution_place,released_at FROM spectacle_camp_stock_requests WHERE camp_id IN ($placeholders) ORDER BY id DESC",$visibleIds)->fetchAll(PDO::FETCH_ASSOC);
            $seenBatches=[];
            foreach ($statusRows as $statusRow) {
                $stockCampId=(int)$statusRow['camp_id'];
                if ($statusRow['status']==='released') {
                    $planned=(string)$statusRow['planned_distribution_date'];
                    $plannedTime=(string)($statusRow['planned_distribution_time']??'');
                    $plannedAt=$planned.' '.($plannedTime!==''?$plannedTime:'00:00:00');
                    $existingAt=isset($campDistributionDates[$stockCampId])
                        ? $campDistributionDates[$stockCampId].' '.($campDistributionTimes[$stockCampId]!==''?$campDistributionTimes[$stockCampId]:'00:00:00') : null;
                    if ($existingAt===null || $plannedAt<$existingAt) {
                        $campDistributionDates[$stockCampId]=$planned;
                        $campDistributionTimes[$stockCampId]=$plannedTime;
                        $campDistributionPlaces[$stockCampId]=(string)($statusRow['planned_distribution_place']??'');
                    }
                }
                $stockBatch=(string)($statusRow['batch_ref']??'');
                $stockKey=$stockCampId.'-'.($stockBatch!==''?$stockBatch:'single-'.$statusRow['id']);
                if (isset($seenBatches[$stockKey])) continue;
                $seenBatches[$stockKey]=true;
                $campStockStatuses[$stockCampId][]=[
                    'reference'=>$stockBatch!==''?'SCB-'.strtoupper(substr($stockBatch,0,8)):'SCS-'.(int)$statusRow['id'],
                    'status'=>(string)$statusRow['status'],
                    'released_at'=>$statusRow['released_at'],
                    'planned_distribution_date'=>$statusRow['planned_distribution_date'],
                    'planned_distribution_time'=>$statusRow['planned_distribution_time'],
                    'planned_distribution_place'=>$statusRow['planned_distribution_place'],
                ];
            }
            foreach (scQuery($db,"SELECT DISTINCT camp_id FROM spectacle_camp_transfers WHERE camp_id IN ($placeholders)",$visibleIds)->fetchAll(PDO::FETCH_COLUMN) as $handoverCampId) {
                $campSsoHandovers[(int)$handoverCampId]=true;
            }
            if ($role==='social-service-officer') foreach (scQuery($db,"SELECT DISTINCT camp_id FROM spectacle_camp_transfers WHERE camp_id IN ($placeholders) AND sso_id=?",array_merge($visibleIds,[(int)$actor['id']]))->fetchAll(PDO::FETCH_COLUMN) as $handoverCampId) {
                $campSsoHandoversForActor[(int)$handoverCampId]=true;
            }
        }
        if ($campId>0) {
            scCamp($db,$campId,$actor);
            foreach ($camps as $row) if ((int)$row['id']===$campId) $camp=$row;
            // Detail links normally use the unfiltered workspace route.
            if (!$camp) throw new DomainException('Vision Camp not found or access denied.');
            if ($isRegistrationPage && (int)$camp['requested_by'] !== (int)$actor['id']) $campRoute='spectacle-camps';
            if ($isRegistrationPage && $camp['status']==='completed') {
                header('Location: dashboard.php?page=spectacle-camp-participants&camp_id='.$campId.'&from='.$participantFrom);
                exit;
            }
            if ($isRegistrationPage && ($camp['status']!=='approved' || !$camp['conducted_at'] || (int)$camp['conductor_id']!==(int)$actor['id'])) throw new DomainException('Only the Subject Officer who conducted this approved camp can register participants.');
            if ($isRegistrationPage || $tab==='participants') $restrictionMonths=scSpectacleWaitingPeriod($db,(int)$camp['item_id'],'','')['months'];
            if (!$isRegistrationPage) $title='Vision Camp — '.$camp['division_name'];
            $participants=scQuery($db,'SELECT p.*,g.name gn_name,category.name spectacle_category_name,x.id distribution_id,x.distribution_date,x.distribution_time,x.source,
                (SELECT t.transfer_date FROM spectacle_camp_transfers t WHERE t.camp_id=p.camp_id AND t.spectacle_category_id=p.spectacle_category_id ORDER BY t.id DESC LIMIT 1) sso_handover_date,
                (SELECT u.full_name FROM spectacle_camp_transfers t JOIN users u ON u.id=t.sso_id WHERE t.camp_id=p.camp_id AND t.spectacle_category_id=p.spectacle_category_id ORDER BY t.id DESC LIMIT 1) sso_handover_name,
                (SELECT t.sso_id FROM spectacle_camp_transfers t WHERE t.camp_id=p.camp_id AND t.spectacle_category_id=p.spectacle_category_id ORDER BY t.id DESC LIMIT 1) sso_handover_sso_id
                FROM spectacle_camp_participants p JOIN gn_divisions g ON g.id=p.gn_division_id LEFT JOIN spectacle_categories category ON category.id=p.spectacle_category_id LEFT JOIN spectacle_camp_distributions x ON x.participant_id=p.id WHERE p.camp_id=? ORDER BY p.id ASC',[$campId])->fetchAll(PDO::FETCH_ASSOC);
            if ($role==='social-service-officer' && $isParticipantListPage && $ssoView!=='') {
                $participants=array_values(array_filter($participants,static function(array $participant) use ($actor,$ssoView): bool {
                    if ((int)($participant['sso_handover_sso_id']??0)!==(int)$actor['id']) return false;
                    return $ssoView==='history'
                        ? !empty($participant['distribution_id']) && ($participant['source']??'')==='social-service-officer'
                        : $participant['status']==='approved' && empty($participant['distribution_id']);
                }));
            }
            $balances=scBalances($db,$campId,(int)$actor['id']);
            if (!$isRegistrationPage && ($tab==='distribution' || $isParticipantListPage) && $role==='subject-officer') {
                foreach (array_keys($balances) as $categoryId) $subjectReady[$categoryId]=scSubjectDistributableQuantity($db,$campId,(int)$categoryId,scToday());
            }
            if ($camp['status']==='completed') $requestNeeds=scCampRequestNeed($db,$campId);
            $gnDivisions=scQuery($db,"SELECT id,name FROM gn_divisions WHERE ds_division_id=? AND status='active' ORDER BY name",[$camp['ds_division_id']])->fetchAll();
            $ssos=scQuery($db,"SELECT id,full_name FROM users WHERE role='social-service-officer' AND status='active' AND ds_division_id=? ORDER BY full_name",[$camp['ds_division_id']])->fetchAll();
            $receipts=scQuery($db,'SELECT r.*,category.name spectacle_category_name,u.full_name actor_name,s.company_name supplier_name,sr.total_cost,sr.paid_amount,sr.balance_amount,sr.payment_status FROM spectacle_camp_receipts r LEFT JOIN spectacle_categories category ON category.id=r.spectacle_category_id JOIN users u ON u.id=r.received_by LEFT JOIN stock_receipts sr ON sr.id=r.stock_receipt_id LEFT JOIN suppliers s ON s.id=sr.supplier_id WHERE r.camp_id=? ORDER BY r.id DESC',[$campId])->fetchAll();
            foreach(scQuery($db,'SELECT line.stock_receipt_id,category.name spectacle_category_name,line.quantity,line.unit_cost FROM spectacle_camp_receipts receipt JOIN stock_receipt_spectacle_lines line ON line.stock_receipt_id=receipt.stock_receipt_id JOIN spectacle_categories category ON category.id=line.spectacle_category_id WHERE receipt.camp_id=? ORDER BY receipt.id DESC,category.display_order,category.name',[$campId])->fetchAll(PDO::FETCH_ASSOC) as $line) $receiptLines[(int)$line['stock_receipt_id']][]=$line;
            $transfers=scQuery($db,'SELECT r.*,category.name spectacle_category_name,u.full_name sso_name,a.full_name actor_name FROM spectacle_camp_transfers r LEFT JOIN spectacle_categories category ON category.id=r.spectacle_category_id JOIN users u ON u.id=r.sso_id JOIN users a ON a.id=r.transferred_by WHERE r.camp_id=? ORDER BY r.id DESC',[$campId])->fetchAll();
            $campSsoHandovers[$campId]=$transfers!==[];
            $ssoCanDistributeCamp=$role==='social-service-officer' && (bool)scQuery($db,'SELECT id FROM spectacle_camp_transfers WHERE camp_id=? AND sso_id=? LIMIT 1',[$campId,(int)$actor['id']])->fetchColumn();
            $distributions=scQuery($db,'SELECT x.*,category.name spectacle_category_name,p.full_name,p.nic,p.elder_card_number,p.prescription_details,u.full_name actor_name FROM spectacle_camp_distributions x LEFT JOIN spectacle_categories category ON category.id=x.spectacle_category_id JOIN spectacle_camp_participants p ON p.id=x.participant_id JOIN users u ON u.id=x.distributed_by WHERE x.camp_id=? ORDER BY x.id DESC',[$campId])->fetchAll();
            $events=scQuery($db,'SELECT e.*,u.full_name actor_name FROM spectacle_camp_events e JOIN users u ON u.id=e.actor_id WHERE e.camp_id=? ORDER BY e.id DESC',[$campId])->fetchAll();
        }
        $stockScope=$scope; $stockParams=$params;
        if ($camp) { $stockScope.=' AND c.id=?'; $stockParams[]=$campId; }
        if ($activePage==='spectacle-camp-stock-approvals') $stockScope.=" AND r.status='pending'";
        if ($activePage==='spectacle-camp-releases') $stockScope.=" AND r.status='approved'";
        $stockRequests=scQuery($db,"SELECT r.*,category.name spectacle_category_name,c.camp_date,c.camp_time,d.name district_name,ds.name division_name,u.full_name requester_name,u.username requester_username,u.role requester_role,
            (SELECT COUNT(*) FROM spectacle_camp_participants p WHERE p.camp_id=c.id AND p.status='approved') approved_count
            FROM spectacle_camp_stock_requests r JOIN spectacle_camps c ON c.id=r.camp_id JOIN districts d ON d.id=c.district_id JOIN ds_divisions ds ON ds.id=c.ds_division_id JOIN users u ON u.id=r.requested_by LEFT JOIN spectacle_categories category ON category.id=r.spectacle_category_id WHERE $stockScope ORDER BY r.id DESC",$stockParams)->fetchAll(PDO::FETCH_ASSOC);
        if ($isParticipantListPage && $role==='subject-officer') foreach ($stockRequests as $request) {
            $categoryId=(int)($request['spectacle_category_id']??0);
            if ($categoryId>0 && !isset($participantNoticeSchedules[$categoryId]) && in_array($request['status'],['approved','released'],true)) {
                $participantNoticeSchedules[$categoryId]=['date'=>$request['planned_distribution_date'],'time'=>$request['planned_distribution_time']];
            }
        }
        if ($role==='subject-officer') {
            $districts=scQuery($db,"SELECT id,name FROM districts WHERE status='active' ORDER BY name")->fetchAll();
            $divisions=scQuery($db,"SELECT id,name,district_id FROM ds_divisions WHERE status='active' AND division_type<>'service-centre' ORDER BY name")->fetchAll();
        }
    }
} catch (DomainException $e) { http_response_code(403); $error=$e->getMessage(); $camp=null; $camps=[]; }
catch (Throwable $e) { error_log('Vision Camp data unavailable.'); $error='Unable to load Vision Camps. Run the latest database migration.'; }
if ($isParticipantListPage) $title=$role==='social-service-officer' && $ssoView==='history'?'My Distribution History':($role==='social-service-officer' && $ssoView==='distribution'?'My Distribution':'Registered Participants');
$isSubject=$role==='subject-officer';
$canManage=$camp && $isSubject && $camp['status']==='approved' && $camp['conducted_at'];
$canRegister=$canManage && (int)($camp['conductor_id']??0)===(int)($actor['id']??0);
$canRequestStock=$camp && $isSubject && $camp['status']==='completed' && (int)($camp['conductor_id']??0)===(int)($actor['id']??0);
$canWorkStock=$camp && $isSubject && $camp['status']==='completed';
$isQueue=in_array($activePage,['spectacle-camp-stock-approvals','spectacle-camp-releases'],true);
$isAdminStockApprovalQueue=$role==='admin' && $activePage==='spectacle-camp-stock-approvals';
?>
<!doctype html><html lang="<?= scEscape(widmsLanguage()) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= scEscape(t($title)) ?> | SWPCS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="assets/css/admin-dashboard.css?v=68" rel="stylesheet"><link href="assets/css/spectacle-camps.css?v=<?= filemtime(__DIR__.'/../../public/assets/css/spectacle-camps.css') ?>" rel="stylesheet"></head>
<body class="widms-unified-ui sc-page<?= $isAdminStockApprovalQueue ? ' sc-approval-page' : '' ?>">
<?php if (in_array($role,['admin','subject-officer','store-keeper','social-service-officer'],true)) require __DIR__.'/../../includes/'.$role.'-sidebar.php'; ?>
<div class="admin-shell"><header class="topbar"><div class="d-flex align-items-center gap-3"><button type="button" class="menu-button" id="menu-button" aria-label="<?= scLabel('Open navigation') ?>">&#9776;</button><h1><?= scEscape(t($title)) ?></h1></div><div class="topbar-actions"><button class="notification-button" type="button" aria-label="<?= scLabel('Notifications') ?>">&#128276;</button></div></header>
<main class="dashboard-content sc-content<?= $isAdminStockApprovalQueue ? ' admin-correction-review-page' : '' ?>">
<?php if ($error!==''): ?><div class="alert alert-danger" role="alert"><?= scLabel($error) ?></div><?php endif; ?>
<?php renderSuccessMessage($notice); ?>
<?php if ($isParticipantListPage): ?>
<?php if ($camp && $camp['status']==='completed' && in_array($role,['subject-officer','admin'],true)): ?><div class="sc-participant-export-actions"><a class="outline-action" href="vision-camp-participants-export.php?camp_id=<?= $campId ?>&amp;format=print" target="_blank" rel="noopener"><?= scLabel('Print with signatures') ?></a><a class="admin-primary-action" href="vision-camp-participants-export.php?camp_id=<?= $campId ?>&amp;format=xlsx"><?= scLabel('Download Excel') ?></a><?php if ($isSubject && $participantNoticeSchedules): ?><a class="outline-action sc-letter-edit-link" href="vision-camp-a5-letter.php?camp_id=<?= $campId ?>&amp;edit=1"><?= scLabel('Edit A5 letter') ?></a><a class="outline-action sc-letter-print-link" href="vision-camp-a5-letter.php?camp_id=<?= $campId ?>" target="_blank" rel="noopener"><?= scLabel('Print A5 letters') ?></a><?php endif; ?></div><?php endif; ?>
<?php if ($camp): $participantDistributionCamp=($isSubject && $camp['status']==='completed' && empty($campSsoHandovers[$campId])) || ($role==='social-service-officer' && $ssoView==='distribution' && $camp['status']==='completed' && $ssoCanDistributeCamp) ? $camp : null; $participantBackPage=$role==='admin'?'spectacle-camps':($participantFrom==='my-spectacle-camps' && (int)$camp['requested_by']===(int)$actor['id']?'my-spectacle-camps':'spectacle-camps'); $participantBackLabel=$role==='admin'?'Camp History':($role==='social-service-officer'?'My Division Camps':null); scParticipantTable($participants,$participantBackPage,$participantBackLabel,$participantDistributionCamp,$subjectReady,$balances,$participantNoticeSchedules,!empty($campSsoHandovers[$campId]),$isSubject); endif; ?>
<?php elseif ($isRegistrationPage && $camp && $canRegister): ?>
<div class="sc-camp-heading">
    <a class="outline-action" href="dashboard.php?page=<?= $campRoute ?>">&larr; <?= scLabel($campRoute==='my-spectacle-camps'?'My Camps':'All Vision Camps') ?></a>
    <strong>VC-<?= $campId ?> · <?= scEscape($camp['division_name']) ?></strong>
    <a class="outline-action" href="dashboard.php?page=spectacle-camp-participants&amp;camp_id=<?= $campId ?>&amp;from=<?= scEscape($participantFrom) ?>"><?= scLabel('View registered participants') ?></a>
</div>
<?php scParticipantRegistrationForm($camp,$gnDivisions,$restrictionMonths,$participants,$spectacleCategories); ?>
<?php elseif ($isRegistrationPage): ?>
<?php elseif ($actor && $activePage==='spectacle-camp-new' && $role==='subject-officer'): ?>
<section class="aid-form-card">
<?php scFormStart('create-camp',0,true); ?>
<fieldset><legend><?= scLabel('Camp Location') ?></legend><div class="aid-form-grid two-columns">
<label><span><?= scLabel('District') ?> *</span><select name="district_id" data-sc-district required><option value=""><?= scLabel('Select District') ?></option><?php foreach($districts as $d): ?><option value="<?= (int)$d['id'] ?>" <?= (string)($_POST['district_id']??'')===(string)$d['id']?'selected':'' ?>><?= scEscape($d['name']) ?></option><?php endforeach; ?></select></label>
<label><span><?= scLabel('DS Division') ?> *</span><select name="ds_division_id" data-sc-division required><option value=""><?= scLabel('Select DS Division') ?></option><?php foreach($divisions as $d): ?><option value="<?= (int)$d['id'] ?>" data-district="<?= (int)$d['district_id'] ?>" <?= (string)($_POST['ds_division_id']??'')===(string)$d['id']?'selected':'' ?>><?= scEscape($d['name']) ?></option><?php endforeach; ?></select></label>
</div></fieldset>
<fieldset><legend><?= scLabel('Camp Schedule') ?></legend>
<div class="aid-form-grid three-columns">
<?php scInput('Estimated Participants','estimated_participants','number',true,'min="1" max="100000"'); scInput('Camp Date','camp_date','date',true,'min="'.scToday().'"'); scCampTimeFields(); ?>
</div></fieldset>
<fieldset><legend><?= scLabel('Remarks') ?></legend><?php scRemarks('remarks','Remarks',1000); ?></fieldset>
<?php scSubmit('Submit for approval',true); ?>
</section>
<?php elseif ($camp): ?>
<section class="sc-camp-navigation"><div class="sc-camp-heading"><?php if ($camp['status']!=='completed'): ?><a class="outline-action" href="dashboard.php?page=<?= $campRoute ?>">&larr; <?= scLabel($campRoute==='my-spectacle-camps'?'My Camps':($role==='subject-officer'?'All Vision Camps':'Camp History')) ?></a><?php endif; ?><div class="sc-camp-identity"><span class="sc-eyebrow"><?= scLabel('Vision Camp') ?> · VC-<?= $campId ?></span><strong><?= scEscape($camp['division_name']) ?></strong></div><?php scBadge($camp['status']); ?></div>
<?php if ($isSubject && $camp['status']==='approved' && !$camp['conducted_at']): ?>
<section class="sc-panel sc-conduct-panel"><h2><?= scLabel('Conduct Camp') ?></h2><?php scConductCampAction($camp,$actor); ?></section>
<?php endif; ?>
<nav class="sc-tabs" aria-label="<?= scLabel('Camp sections') ?>"><?php foreach(['overview'=>'Overview','participants'=>'Participants','stock'=>'Camp Stock','distribution'=>'Distribution','history'=>'History'] as $key=>$label): ?><a href="dashboard.php?page=<?= $campRoute ?>&amp;camp_id=<?= $campId ?>&amp;tab=<?= $key ?>" <?= $tab===$key?'class="active" aria-current="page"':'' ?>><?= scLabel($label) ?></a><?php endforeach; ?></nav></section>
<?php if ($tab==='overview'): ?>
<section class="sc-panel sc-overview-panel"><header class="sc-overview-header"><div><span class="sc-eyebrow">VC-<?= $campId ?></span><h2><?= scLabel('Camp overview') ?></h2></div><?php if ($canManage) scParticipantRegistrationLink($camp,$actor,$campRoute); ?></header><?php scDetails(['District'=>$camp['district_name'],'DS Division'=>$camp['division_name'],'Requested By'=>$camp['requester_name'],'Estimated Participants'=>$camp['estimated_participants'],'Camp Date'=>$camp['camp_date'],'Camp Time'=>$camp['camp_time'],'Request Date'=>$camp['created_at'],'Conducted By'=>($camp['conductor_name']??null) ? $camp['conductor_name'].' ('.$camp['conductor_username'].')' : '—','Conducted At'=>$camp['conducted_at']??'—']); ?>
<?php if ($camp['remarks']): ?><p><?= scEscape($camp['remarks']) ?></p><?php endif; ?>
<?php if ($camp['decision_reason']): ?><p><?= scLabel('Reason / remarks') ?>: <?= scEscape($camp['decision_reason']) ?></p><?php endif; ?>
<div class="sc-metrics"><?php foreach(['Total participants'=>$camp['participant_count'],'Approved participants'=>$camp['approved_count'],'Rejected participants'=>$camp['rejected_count'],'Spectacles Distributed'=>count($distributions)] as $label=>$value): ?><div><span><?= scLabel($label) ?></span><strong><?= (int)$value ?></strong></div><?php endforeach; ?></div>
<?php if ($role==='admin' && $camp['status']==='pending'): ?><a class="admin-primary-action" href="dashboard.php?page=pending-approvals&amp;tab=vision-camps#vision-camp-request-<?= $campId ?>"><?= scLabel('Vision Camp Requests') ?></a><?php endif; ?>
<?php if ($canRegister): ?><p><?= scLabel('Complete the camp after recording every participant decision. Registration closes at completion.') ?></p><?php scCompleteCampAction($camp,$actor); ?><?php endif; ?>
</section>
<?php elseif ($tab==='participants'): ?>
<?php if ($canRegister) scParticipantRegistrationForm($camp,$gnDivisions,$restrictionMonths,$participants,$spectacleCategories); else scParticipantTable($participants); ?>
<?php elseif ($tab==='stock'): ?>
<section class="sc-panel"><h2><?= scLabel('Camp Stock') ?></h2><p><?= scLabel('Camp stock is tracked by spectacle type. Receiving stock does not release it.') ?></p><?php scTableStart(['Spectacle Type','Received','Store balance','Pending requests','Reserved','Released','Distributed by Subject Officer','Remaining with Subject Officer','Transferred to SSO','Remaining with SSO'],false); foreach($balances as $categoryId=>$b): ?><tr data-sc-row><td><?= scEscape(widmsSpectacleCategoryName($db,(int)$categoryId)??'—') ?></td><?php foreach(['received','store','pending','reserved','released','subject_distributed','subject','transferred','sso'] as $k): ?><td><?= (int)$b[$k] ?></td><?php endforeach; ?></tr><?php endforeach; scTableEnd(10,$balances===[]); ?></section>
<?php if ($role==='store-keeper' && $camp['status']==='completed'): ?><section class="sc-panel sc-camp-supplier-entry"><h2><?= scLabel('Receive Camp Spectacles') ?></h2><p><?= scLabel('Selected participant counts set each spectacle type quantity. Enter the supplier and unit prices.') ?></p><a class="admin-primary-action" href="dashboard.php?page=receive-items&amp;camp_id=<?= $campId ?>"><?= scLabel('Record Supplier Delivery') ?></a></section><?php endif; ?>
<?php if ($canRequestStock): ?><p class="sc-registration-link"><a class="admin-primary-action" href="dashboard.php?page=vision-camp-stock-requests&amp;camp_id=<?= $campId ?>"><?= scLabel('Request Camp Stock') ?></a></p><?php endif; ?>
<h2><?= scLabel('Stock requests') ?></h2><?php scRequestTable($stockRequests,'stock',$role); ?>
<h2><?= scLabel('Receipt & Payment History') ?></h2>
<?php scTableStart(['Reference','Spectacle Type','Quantity','Unit Price (Rs)','Supplier','Total Price (Rs)','Paid','Due','Status','Received Date','Received By','Action']); foreach($receipts as $r): $lines=$receiptLines[(int)($r['stock_receipt_id']??0)]??[]; ?>
<tr data-sc-row>
    <td><?= scEscape($r['reference_code']) ?></td>
    <td><?php if($lines): foreach($lines as $line): ?><span class="sc-receipt-line-detail"><?= scLabel((string)$line['spectacle_category_name']) ?> <small>× <?= (int)$line['quantity'] ?></small></span><?php endforeach; elseif($r['spectacle_category_name']): ?><?= scLabel((string)$r['spectacle_category_name']) ?><?php else: ?>—<?php endif; ?></td>
    <td><?= (int)$r['quantity'] ?></td>
    <td><?php if($lines): foreach($lines as $line): ?><span class="sc-receipt-line-detail">Rs <?= number_format((float)$line['unit_cost'],2) ?></span><?php endforeach; else: ?>—<?php endif; ?></td>
    <td><?= scEscape($r['supplier_name']??'—') ?></td>
    <td><?= $r['total_cost']!==null?'Rs '.number_format((float)$r['total_cost'],2):'—' ?></td>
    <td><?= $r['paid_amount']!==null?'Rs '.number_format((float)$r['paid_amount'],2):'—' ?></td>
    <td><?= $r['balance_amount']!==null?'Rs '.number_format((float)$r['balance_amount'],2):'—' ?></td>
    <td><?= $r['payment_status']?scLabel(ucwords(str_replace('-',' ',$r['payment_status']))):'—' ?></td>
    <td><?= scEscape($r['received_date']) ?></td>
    <td><?= scEscape($r['actor_name']) ?></td>
    <td><?php if($r['stock_receipt_id']): ?><a class="outline-action" href="dashboard.php?page=receipt-payment-details&amp;receipt_id=<?= (int)$r['stock_receipt_id'] ?>"><?= scLabel('Payment History') ?></a><?php else: ?>—<?php endif; ?></td>
</tr>
<?php endforeach; scTableEnd(12,$receipts===[]); ?>
<?php elseif ($tab==='distribution'): ?>
<?php if ($isSubject && !empty($campSsoHandovers[$campId])): ?><section class="sc-panel sc-distribution-panel"><h2><?= scLabel('Spectacles handed over') ?></h2><p><?= scLabel('The remaining camp spectacles have been handed over to the Division SSO. The SSO will complete the remaining participant distributions.') ?></p></section><?php else: ?><section class="sc-panel sc-distribution-panel"><h2><?= scLabel('Distribute Spectacles') ?></h2><p><?= scLabel('Distribute is available after stock release and its planned distribution date, or after stock is transferred to you.') ?></p><?php scDistributionTable($camp,$participants,$balances,($isSubject || $role==='social-service-officer') && in_array($camp['status'],['approved','completed'],true),$isSubject,$subjectReady); ?></section><?php endif; ?>
<?php if($canWorkStock && array_sum(array_map(static fn(array $balance):int=>(int)($balance['subject']??0),$balances))>0): ?><section class="sc-panel sc-sso-handover-panel"><h2><?= scLabel('Hand over Remaining Spectacles to Division SSO') ?></h2><p><?= scLabel('This transfers all remaining released spectacles to the active SSO assigned to this camp division. The camp stays in SSO Handover until every approved participant has received spectacles.') ?></p><form method="post" class="sc-form" data-sc-handover-form data-camp-reference="VC-<?= $campId ?>"><input type="hidden" name="csrf_token" value="<?= scEscape(csrfToken()) ?>"><input type="hidden" name="action" value="handover-to-sso"><input type="hidden" name="camp_id" value="<?= $campId ?>"><input type="hidden" name="request_token" value="<?= bin2hex(random_bytes(16)) ?>"><?php scSubmit('Hand over to Division SSO'); ?></form></section><?php endif; ?>
<h2><?= scLabel('Transferred stock') ?></h2><?php scTableStart(['Quantity','Divisional SSO','Transfer date','Transferred by','Remarks']); foreach($transfers as $r): ?><tr data-sc-row><td><?= (int)$r['quantity'] ?></td><td><?= scEscape($r['sso_name']) ?></td><td><?= scEscape($r['transfer_date']) ?></td><td><?= scEscape($r['actor_name']) ?></td><td><?= scEscape($r['remarks']) ?></td></tr><?php endforeach; scTableEnd(5,$transfers===[]); ?>
<?php endif; ?>
<?php if(in_array($tab,['distribution','history'],true)): ?><h2><?= scLabel('Distribution History') ?></h2><?php scTableStart(['Participant','NIC / Elder Card','Quantity','Distribution date / time','Distributed by','Source','Remarks']); foreach($distributions as $r): ?><tr data-sc-row><td><?= scEscape($r['full_name']) ?></td><td><?= scEscape(implode(' / ',array_filter([$r['nic'],$r['elder_card_number']]))) ?></td><td><?= (int)$r['quantity'] ?></td><td><?php scCampWhen($r['distribution_date'],$r['distribution_time']??null); ?></td><td><?= scEscape($r['actor_name']) ?></td><td><?= scLabel($r['source']==='subject-officer'?'Subject Officer':'Divisional SSO') ?></td><td><?= scEscape($r['remarks']) ?></td></tr><?php endforeach; scTableEnd(7,$distributions===[]); ?><?php endif; ?>
<?php if($tab==='history'): scParticipantTable($participants); ?><h2><?= scLabel('Audit History') ?></h2><?php scTableStart(['Date','Action','Details','By']); foreach($events as $r): ?><tr data-sc-row><td><?= scEscape($r['created_at']) ?></td><td><?= scEscape($r['action']) ?></td><td><?= scEscape($r['details']) ?></td><td><?= scEscape($r['actor_name']) ?></td></tr><?php endforeach; scTableEnd(4,$events===[]); ?><?php endif; ?>
<?php elseif($actor && $isQueue): ?><?php if($role==='admin' && $activePage==='spectacle-camp-stock-approvals') scApprovalCardList($stockRequests,'stock'); elseif($role==='store-keeper' && $activePage==='spectacle-camp-releases') scReleaseCardList($stockRequests); else scRequestTable($stockRequests,'stock',$role); ?>
<?php elseif($actor && $activePage==='spectacle-camp-approvals'): ?><?php scRequestTable($camps,'camp',$role); ?>
<?php elseif($actor): ?>
<?php
echo '<div class="sc-plain-camp-list">';
$campDistricts=[];
$showAllCampFilters=$role==='admin' || ($isSubject && $activePage==='spectacle-camps');
if ($showAllCampFilters) foreach ($camps as $listedCamp) $campDistricts[(int)$listedCamp['district_id']]=$listedCamp['district_name'];
asort($campDistricts,SORT_NATURAL|SORT_FLAG_CASE);
scTableStart(['Camp / Location','Scheduled','Requested By','Participants','Conducted By','Conducted At','Status','Action'],true,false,$activePage==='my-spectacle-camps'||$showAllCampFilters,$campDistricts,$isSubject||$role==='admin',$isSubject||$role==='admin');
foreach ($camps as $c):
    $openTab='overview';
    $campStage=$c['status']==='completed'?'completed':($c['status']==='approved'?($c['conducted_at']?'conducted':'awaiting-conduct'):$c['status']);
    $stockStatuses=$campStockStatuses[(int)$c['id']]??[];
    $stockStatusValues=$stockStatuses?implode(' ',array_unique(array_column($stockStatuses,'status'))):'none';
    $hasSsoHandover=!empty($campSsoHandovers[(int)$c['id']]);
    $distributionStage=$c['status']==='completed' && (int)$c['approved_count']>0?scDistributionProgress((int)$c['approved_count'],(int)$c['distributed_count'],$hasSsoHandover):'none';
?>
<tr data-sc-row id="vision-camp-row-<?= (int)$c['id'] ?>" class="admin-notification-target" tabindex="-1" data-sc-camp-stage="<?= scEscape($campStage) ?>" data-sc-camp-district="<?= (int)$c['district_id'] ?>" data-sc-stock-status="<?= scEscape($stockStatusValues) ?>" data-sc-distribution-status="<?= scEscape($distributionStage) ?>">
    <td class="sc-camp-location"><strong>VC-<?= (int)$c['id'] ?></strong><span><?= scEscape($c['division_name']) ?></span><small><?= scEscape($c['district_name']) ?></small></td>
    <td class="sc-camp-date"><?php scCampWhen($c['camp_date'],$c['camp_time']); ?></td>
    <td class="sc-camp-actor"><span class="sc-actor-username"><?= scEscape($c['requester_username']) ?></span><small><?= scCampRoleLabel((string)$c['requester_role']) ?></small></td>
    <td class="sc-camp-participants"><strong><?= (int)$c['participant_count'] ?> / <?= (int)$c['estimated_participants'] ?></strong><span class="sc-participant-approved"><?= scLabel('Approved participants') ?>: <b><?= (int)$c['approved_count'] ?></b></span><span class="sc-participant-rejected"><?= scLabel('Rejected participants') ?>: <b><?= (int)$c['rejected_count'] ?></b></span></td>
    <td class="sc-camp-actor"><?php if (!empty($c['conductor_username'])): ?><span class="sc-actor-username"><?= scEscape($c['conductor_username']) ?></span><small><?= scLabel('Subject Officer') ?></small><?php else: ?>—<?php endif; ?></td>
    <td class="sc-camp-date"><?php if ($c['conducted_at']): scCampWhen($c['conducted_at'],$c['conducted_at']); else: ?>—<?php endif; ?></td>
    <td>
        <div class="sc-combined-status"><small><?= scLabel('Camp') ?></small><?php scBadge($c['status']); ?></div>
        <?php if ($c['status']==='completed' && $c['completed_at']): ?><div class="sc-camp-completed-at"><small><?= scLabel('Completed at') ?></small><?php scCampWhen($c['completed_at'],$c['completed_at']); ?></div><?php endif; ?>
        <?php if ($c['status']==='completed' && (int)$c['approved_count']>0): $distributionStatus=scDistributionProgress((int)$c['approved_count'],(int)$c['distributed_count'],$hasSsoHandover); $distributionLabel=$distributionStatus==='pending'?'Pending Distribution':($distributionStatus==='in-distribution'?'In Distribution':($distributionStatus==='sso-handover'?'SSO Handover':'Distributed')); ?><div class="sc-combined-status sc-combined-distribution-status"><small><?= scLabel('Distribution') ?></small><?php scBadge($distributionStatus,$distributionLabel); ?><small><?= (int)$c['distributed_count'] ?> / <?= (int)$c['approved_count'] ?></small><?php if (isset($campDistributionDates[(int)$c['id']])): ?><span class="sc-distribution-date"><small><?= scLabel('Distribution date') ?></small><?= scEscape(scCampDateLabel($campDistributionDates[(int)$c['id']])) ?></span><?php if (($campDistributionTimes[(int)$c['id']]??'')!==''): ?><span class="sc-distribution-date"><small><?= scLabel('Distribution time') ?></small><?= scEscape(scCampTimeLabel($campDistributionTimes[(int)$c['id']])) ?></span><?php endif; ?><?php if (($campDistributionPlaces[(int)$c['id']]??'')!==''): ?><span class="sc-distribution-date"><small><?= scLabel('Distribution Place') ?></small><?= scEscape($campDistributionPlaces[(int)$c['id']]) ?></span><?php endif; ?><?php endif; ?></div><?php endif; ?>
        <div class="sc-combined-status sc-combined-stock-status"><small><?= scLabel('Stock request') ?></small><?php if (!$stockStatuses): ?><span class="sc-stock-status sc-stock-status-none"><?= scLabel('Not requested') ?></span><?php else: foreach ($stockStatuses as $stockStatus): ?><span class="sc-stock-status-row"><span class="sc-stock-reference"><?= scEscape($stockStatus['reference']) ?></span><span class="sc-stock-status sc-stock-status-<?= scEscape($stockStatus['status']) ?>"><?= scLabel(['pending'=>'Pending Admin approval','approved'=>'Approved - Awaiting Store Release','rejected'=>'Rejected','released'=>'Released'][$stockStatus['status']]??ucwords(str_replace('-', ' ', $stockStatus['status']))) ?></span></span><?php if ($stockStatus['status']==='released'): ?><span class="sc-stock-dates"><span><small><?= scLabel('Released') ?></small><?= scEscape(scCampDateLabel((string)$stockStatus['released_at'])) ?> <?= scEscape(scCampTimeLabel((string)$stockStatus['released_at'])) ?></span></span><?php endif; endforeach; endif; ?></div>
    </td>
    <td><div class="sc-camp-row-actions">
        <?php if ($role==='admin'): ?><a class="outline-action sc-action-participants" href="dashboard.php?page=spectacle-camp-participants&amp;camp_id=<?= (int)$c['id'] ?>&amp;from=spectacle-camps"><?= scLabel('View registered participants') ?></a>
        <?php elseif (!in_array($activePage,['spectacle-camps','my-spectacle-camps'],true)): ?><a class="outline-action sc-action-details" href="dashboard.php?page=<?= $campRoute ?>&amp;camp_id=<?= (int)$c['id'] ?>&amp;tab=<?= $openTab ?>"><?= scLabel('View details') ?></a><?php endif; ?>
        <?php if (($isSubject && $c['conducted_at']) || ($role==='social-service-officer' && $c['status']==='completed')): ?><a class="outline-action sc-action-participants" href="dashboard.php?page=spectacle-camp-participants&amp;camp_id=<?= (int)$c['id'] ?>&amp;from=<?= scEscape($activePage==='my-spectacle-camps'?'my-spectacle-camps':'spectacle-camps') ?>"><?= scLabel('View registered participants') ?></a><?php endif; ?>
        <?php if ($role==='social-service-officer' && $c['status']==='completed' && !empty($campSsoHandoversForActor[(int)$c['id']])): $ssoDistributionComplete=(int)$c['approved_count']>0 && (int)$c['distributed_count']>=(int)$c['approved_count']; ?><a class="admin-primary-action sc-action-distribute" href="dashboard.php?page=spectacle-camp-participants&amp;camp_id=<?= (int)$c['id'] ?>&amp;from=spectacle-camps&amp;sso_view=<?= $ssoDistributionComplete?'history':'distribution' ?>"><?= scLabel($ssoDistributionComplete?'My Distribution History':'My Distribution') ?></a><?php endif; ?>
        <?php if ($isSubject && !$hasSsoHandover && $c['status']==='completed' && (int)$c['distributed_count']<(int)$c['approved_count'] && isset($campDistributionDates[(int)$c['id']])): $distributionOpens=$campDistributionDates[(int)$c['id']]; $distributionOpensTime=$campDistributionTimes[(int)$c['id']]??''; $distributionOpensAt=$distributionOpens.' '.($distributionOpensTime!==''?$distributionOpensTime:'00:00:00'); ?>
            <?php if ($distributionOpensAt<=scNowDateTime()): ?><a class="admin-primary-action sc-action-distribute" href="dashboard.php?page=spectacle-camp-participants&amp;camp_id=<?= (int)$c['id'] ?>&amp;from=<?= scEscape($activePage==='my-spectacle-camps'?'my-spectacle-camps':'spectacle-camps') ?>"><?= scLabel('Distribute Spectacles') ?></a><?php else: ?><button type="button" class="admin-primary-action sc-action-distribute" disabled title="<?= scLabel('Available on the planned distribution date:') ?> <?= scEscape(scCampDateLabel($distributionOpens)) ?> <?= scEscape(scCampTimeLabel($distributionOpensTime)) ?>"><?= scLabel('Distribute Spectacles') ?></button><small class="sc-distribution-wait-note"><?= scLabel('Available on') ?> <?= scEscape(scCampDateLabel($distributionOpens)) ?> <?= scEscape(scCampTimeLabel($distributionOpensTime)) ?></small><?php endif; ?>
            <?php if (!$hasSsoHandover): ?><form method="post" class="sc-row-handover-form" data-sc-handover-form data-camp-reference="VC-<?= (int)$c['id'] ?>"><input type="hidden" name="csrf_token" value="<?= scEscape(csrfToken()) ?>"><input type="hidden" name="action" value="handover-to-sso"><input type="hidden" name="camp_id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="request_token" value="<?= bin2hex(random_bytes(16)) ?>"><button type="submit" class="outline-action sc-action-sso-handover" <?= $distributionOpensAt<=scNowDateTime()?'':'disabled' ?>><?= scLabel('Hand over to Division SSO') ?></button></form><?php endif; ?>
        <?php endif; ?>
        <?php scConductCampAction($c,$actor,true); scParticipantRegistrationLink($c,$actor,$activePage==='my-spectacle-camps'?'my-spectacle-camps':'spectacle-camps'); ?>
        <?php scCompleteCampAction($c,$actor,true); ?>
    </div></td>
</tr>
<?php endforeach; scTableEnd(8,$camps===[]); echo '</div>'; ?>
<?php endif; ?>
</main></div>
<?php if ($isSubject): ?>
<dialog class="sc-complete-dialog" id="sc-complete-dialog" aria-labelledby="sc-complete-title" aria-describedby="sc-complete-description">
    <div class="sc-complete-dialog-card">
        <h2 id="sc-complete-title"><?= scLabel('Complete Vision Camp?') ?></h2>
        <p id="sc-complete-description"><?= scLabel('Are you sure you want to complete') ?> <strong data-sc-complete-camp></strong>? <?= scLabel('Participant registration will close, and the Store Keeper can then receive stock.') ?></p>
        <div class="sc-complete-dialog-actions">
            <button type="button" class="outline-action" data-sc-complete-cancel><?= scLabel('Cancel') ?></button>
            <button type="button" class="sc-complete-button" data-sc-complete-confirm><?= scLabel('Yes, complete camp') ?></button>
        </div>
    </div>
</dialog>
<dialog class="sc-complete-dialog" id="sc-handover-dialog" aria-labelledby="sc-handover-title" aria-describedby="sc-handover-description">
    <div class="sc-complete-dialog-card">
        <h2 id="sc-handover-title"><?= scLabel('Hand over to Division SSO?') ?></h2>
        <p id="sc-handover-description"><?= scLabel('Are you sure you want to hand over all remaining released spectacles for') ?> <strong data-sc-handover-camp></strong>? <?= scLabel('This cannot be undone. The Division SSO will distribute the remaining stock to approved participants.') ?></p>
        <div class="sc-complete-dialog-actions">
            <button type="button" class="outline-action" data-sc-handover-cancel><?= scLabel('Cancel') ?></button>
            <button type="button" class="sc-complete-button" data-sc-handover-confirm><?= scLabel('Yes, hand over') ?></button>
        </div>
    </div>
</dialog>
<?php endif; ?>
<script src="assets/js/admin-dashboard.js?v=26"></script><script src="assets/js/spectacle-camps.js?v=<?= filemtime(__DIR__.'/../../public/assets/js/spectacle-camps.js') ?>"></script><script src="assets/js/vision-camp-approvals.js"></script></body></html>
