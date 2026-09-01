<?php
declare(strict_types=1);
requireRole('admin');
require_once __DIR__.'/../../config/database.php';
require_once __DIR__.'/../../includes/activity.php';

$activePage='beneficiaries';
$errors=[];
$success=(string)($_SESSION['flash_success']??'');
unset($_SESSION['flash_success']);
if($_SERVER['REQUEST_METHOD']==='POST')
    {
        $id=filter_input(INPUT_POST,'request_id',FILTER_VALIDATE_INT);
        $decision=(string)($_POST['decision']??'');
        $reason=trim((string)($_POST['rejection_reason']??''));
        if(!verifyCsrfToken((string)($_POST['csrf_token']??'')))
            $errors[]='Your session expired.';
        if(!$id||!in_array($decision,['approved','rejected'],true))
            $errors[]='Invalid decision.';
        if($decision==='rejected'&&$reason==='')
            $errors[]='A rejection reason is required.';
        if(!$errors)
            {
                try
                {
                    $db=database();
                    $db->beginTransaction();
                    $s=$db->prepare("SELECT * FROM beneficiary_registration_requests WHERE id=:id AND status='pending' FOR UPDATE");
                    $s->execute(['id'=>$id]);$request=$s->fetch();
                    
                    if(!$request)throw new RuntimeException('This request was already reviewed or does not exist.');
                    if($decision==='approved')
                        {
                            $dup=$db->prepare('SELECT id FROM beneficiaries WHERE nic=:nic');
                            $dup->execute(['nic'=>$request['nic']]);
                            if($dup->fetch())throw new RuntimeException('A beneficiary with this NIC already exists.');
                            $insert=$db->prepare('INSERT INTO beneficiaries(registration_request_id,district_id,ds_division_id,gn_division_id,full_name,nic,date_of_birth,gender,phone,address,disability,approved_by,approved_at) VALUES(:request,:district,:ds,:gn,:name,:nic,:dob,:gender,:phone,:address,:disability,:admin,NOW())');$insert->execute(['request'=>$id,
                                                'district'=>$request['district_id'],
                                                'ds'=>$request['ds_division_id'],
                                                'gn'=>$request['gn_division_id'],
                                                'name'=>$request['full_name'],
                                                'nic'=>$request['nic'],
                                                'dob'=>$request['date_of_birth'],
                                                'gender'=>$request['gender'],
                                                'phone'=>$request['phone'],
                                                'address'=>$request['address'],
                                                'disability'=>$request['disability'],
                                                'admin'=>$_SESSION['user_id']]);
                                                
                        }
                        
                        $u=$db->prepare('UPDATE beneficiary_registration_requests SET status=:status,rejection_reason=:reason,reviewed_by=:admin,reviewed_at=NOW() WHERE id=:id');
                        $u->execute(['status'=>$decision,
                                    'reason'=>$decision==='rejected'?$reason:null,
                                    'admin'=>$_SESSION['user_id'],
                                    'id'=>$id]);
                                    
                        $db->commit();
                        logActivity('Beneficiaries',ucfirst($decision).' beneficiary registration — '.$request['full_name'],'BR-'.str_pad((string)$id,4,'0',STR_PAD_LEFT),$decision);
                        $_SESSION['flash_success']='Beneficiary request '.$decision.'.';
                        unset($_SESSION['csrf_token']);
                        header('Location: dashboard.php?page=beneficiaries');
                        exit;
                }
                
                catch(Throwable $e)
                    {
                        if(isset($db)&&$db->inTransaction())$db->rollBack();error_log($e->getMessage());
                        $errors[]=$e instanceof RuntimeException?$e->getMessage():'Unable to review beneficiary request.';
                    }
            }
    }
    try{
        $requests=database()->query("SELECT r.*,d.name district,ds.name ds_name,gn.name gn_name,u.full_name submitted_name,TIMESTAMPDIFF(YEAR,r.date_of_birth,CURDATE()) age FROM beneficiary_registration_requests r JOIN districts d ON d.id=r.district_id JOIN ds_divisions ds ON ds.id=r.ds_division_id JOIN gn_divisions gn ON gn.id=r.gn_division_id JOIN users u ON u.id=r.submitted_by ORDER BY CASE r.status WHEN 'pending' THEN 0 ELSE 1 END,r.id DESC")->fetchAll();$beneficiaries=database()->query('SELECT b.*,d.name district,ds.name ds_name,gn.name gn_name,TIMESTAMPDIFF(YEAR,b.date_of_birth,CURDATE()) age FROM beneficiaries b JOIN districts d ON d.id=b.district_id JOIN ds_divisions ds ON ds.id=b.ds_division_id JOIN gn_divisions gn ON gn.id=b.gn_division_id ORDER BY b.full_name')->fetchAll();}catch(PDOException $e){error_log($e->getMessage());$requests=$beneficiaries=[];$errors[]='Beneficiary workflow is unavailable. Import database/migration_beneficiaries.sql.';}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Beneficiary Management | WIDMS</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="assets/css/admin-dashboard.css" rel="stylesheet"></head><body><?php require __DIR__.'/../../includes/admin-sidebar.php';?><div class="admin-shell"><header class="topbar"><div class="d-flex align-items-center gap-3"><button class="menu-button" id="menu-button">&#9776;</button><h1>Beneficiary Management</h1></div></header><main class="dashboard-content admin-beneficiary-workflow"><?php if($success):?><div class="alert alert-success"><?=htmlspecialchars($success,ENT_QUOTES,'UTF-8')?></div><?php endif;?><?php if($errors):?><div class="alert alert-danger"><?=htmlspecialchars(implode(' ',$errors),ENT_QUOTES,'UTF-8')?></div><?php endif;?>
<section class="admin-data-card"><div class="admin-data-header"><h2>Beneficiary Registration Requests <span class="inline-count"><?=count(array_filter($requests,fn($r)=>$r['status']==='pending'))?> pending</span></h2></div><div class="admin-data-table-wrap"><table class="admin-data-table beneficiary-requests-table"><thead><tr><th>ID</th><th>Name / NIC</th><th>Age</th><th>Location</th><th>Disability</th><th>Submitted By</th><th>Status</th><th>Action</th></tr></thead><tbody><?php if(!$requests):?><tr><td colspan="8" class="admin-empty-row">No beneficiary registration requests.</td></tr><?php else:foreach($requests as $r):?><tr><td>BR-<?=str_pad((string)$r['id'],4,'0',STR_PAD_LEFT)?></td><td><strong><?=htmlspecialchars($r['full_name'],ENT_QUOTES,'UTF-8')?></strong><small><?=htmlspecialchars($r['nic'],ENT_QUOTES,'UTF-8')?></small></td><td><?=(int)$r['age']?></td><td><?=htmlspecialchars($r['district'].' / '.$r['ds_name'].' / '.$r['gn_name'],ENT_QUOTES,'UTF-8')?></td><td><?=htmlspecialchars($r['disability'],ENT_QUOTES,'UTF-8')?></td><td><?=htmlspecialchars($r['submitted_name'],ENT_QUOTES,'UTF-8')?></td><td><?=ucfirst($r['status'])?></td><td><?php if($r['status']==='pending'):?><form method="post" class="beneficiary-decision-form"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrfToken(),ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="request_id" value="<?=(int)$r['id']?>"><input name="rejection_reason" placeholder="Reason required for rejection"><button name="decision" value="approved" class="approve-button">Approve</button><button name="decision" value="rejected" class="reject-button">Reject</button></form><?php else:?><?=htmlspecialchars($r['rejection_reason']?:'Reviewed',ENT_QUOTES,'UTF-8')?><?php endif;?></td></tr><?php endforeach;endif;?></tbody></table></div></section>
<section class="admin-data-card beneficiary-list-card"><div class="admin-data-header"><h2>Official Beneficiary Registry</h2><input type="search" placeholder="Search by name or NIC..."></div><div class="admin-data-table-wrap"><table class="admin-data-table registered-beneficiaries-table"><thead><tr><th>Name</th><th>NIC</th><th>Age</th><th>District</th><th>DS Division</th><th>GN Division</th><th>Phone</th><th>Disability</th><th>Status</th></tr></thead><tbody><?php if(!$beneficiaries):?><tr><td colspan="9" class="admin-empty-row">No approved beneficiaries.</td></tr><?php else:foreach($beneficiaries as $b):?><tr><td><strong><?=htmlspecialchars($b['full_name'],ENT_QUOTES,'UTF-8')?></strong></td><td><?=htmlspecialchars($b['nic'],ENT_QUOTES,'UTF-8')?></td><td><?=(int)$b['age']?></td><td><?=htmlspecialchars($b['district'],ENT_QUOTES,'UTF-8')?></td><td><?=htmlspecialchars($b['ds_name'],ENT_QUOTES,'UTF-8')?></td><td><?=htmlspecialchars($b['gn_name'],ENT_QUOTES,'UTF-8')?></td><td><?=htmlspecialchars($b['phone']?:'—',ENT_QUOTES,'UTF-8')?></td><td><?=htmlspecialchars($b['disability'],ENT_QUOTES,'UTF-8')?></td><td><?=ucfirst($b['status'])?></td></tr><?php endforeach;endif;?></tbody></table></div></section>
</main></div><script src="assets/js/admin-dashboard.js"></script></body></html>
