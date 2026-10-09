<?php
declare(strict_types=1);

function scEscape(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function scLabel(string $value): string { return scEscape(t($value)); }
function scCampDateLabel(?string $value): string
{
    $date=substr((string)$value,0,10);
    $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
    return $parsed && $parsed->format('Y-m-d')===$date ? $parsed->format('d M Y') : '—';
}
function scCampTimeLabel(?string $value): string
{
    if (!preg_match('/(?:^|\s)([01]\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?/',(string)$value,$parts)) return '—';
    $hour=(int)$parts[1];
    return (($hour%12)?:12).':'.$parts[2].' '.t($hour<12?'AM':'PM');
}
function scCampWhen(?string $date, ?string $time): void
{
    echo '<div class="sc-camp-when"><span><small>'.scLabel('Time').'</small><strong>'.scEscape(scCampTimeLabel($time)).'</strong></span>';
    echo '<span><small>'.scLabel('Date').'</small><span>'.scEscape(scCampDateLabel($date)).'</span></span></div>';
}
function scCampRoleLabel(string $role): string
{
    return match($role) {
        'subject-officer'=>scLabel('Subject Officer'),
        'social-service-officer'=>scLabel('Social Service Officer'),
        'store-keeper'=>scLabel('Store Keeper'),
        'admin'=>scLabel('Administrator'),
        default=>scEscape(ucwords(str_replace('-',' ',$role))),
    };
}
function scStatus(string $status): string
{
    return ['pending'=>'Need Approval','approved'=>'Approved','rejected'=>'Rejected','completed'=>'Completed','registered'=>'Registered','released'=>'Released to Subject Officer','sso-handover'=>'SSO Handover'][$status] ?? $status;
}
function scBadge(string $status, ?string $label = null): void
{
    echo '<span class="sc-badge sc-' . scEscape($status) . '">' . scLabel($label ?? scStatus($status)) . '</span>';
}
function scDistributionProgress(int $approved, int $distributed, bool $ssoHandover = false): string
{
    if ($approved<1) return 'pending';
    if ($distributed >= $approved) return 'distributed';
    if ($ssoHandover) return 'sso-handover';
    return $distributed < 1 ? 'pending' : 'in-distribution';
}
function scFormStart(string $action, int $campId = 0, bool $aidLayout = false): void
{
    $class = $aidLayout ? 'aid-request-form sc-camp-request-form' : 'sc-form';
    echo '<form method="post" class="'.$class.'"><input type="hidden" name="csrf_token" value="'.scEscape(csrfToken()).'">';
    echo '<input type="hidden" name="action" value="'.scEscape($action).'"><input type="hidden" name="camp_id" value="'.$campId.'">';
    echo '<input type="hidden" name="request_token" value="'.bin2hex(random_bytes(16)).'">';
}
function scInput(string $label, string $name, string $type = 'text', bool $required = true, string $extra = '', ?string $default = null): void
{
    $value = ($_POST['action'] ?? '') !== '' && is_string($_POST[$name] ?? null) ? $_POST[$name] : ($default ?? '');
    echo '<label><span>'.scLabel($label).($required?' <span aria-hidden="true">*</span>':'').'</span><input type="'.scEscape($type).'" name="'.scEscape($name).'" value="'.scEscape($value).'" '.($required?'required ':'').$extra.'></label>';
}
function scCampTimeFields(string $prefix = 'camp', string $label = 'Camp Time'): void
{
    $selectedHour = is_string($_POST[$prefix.'_hour'] ?? null) ? $_POST[$prefix.'_hour'] : '';
    $selectedMinute = is_string($_POST[$prefix.'_minute'] ?? null) ? $_POST[$prefix.'_minute'] : '';
    $selectedPeriod = is_string($_POST[$prefix.'_period'] ?? null) ? $_POST[$prefix.'_period'] : '';
    echo '<div class="sc-camp-time-control"><span class="sc-camp-time-title">'.scLabel($label).' <span aria-hidden="true">*</span></span><div class="sc-camp-time-fields">';
    echo '<label><span>'.scLabel('Hour').'</span><select name="'.scEscape($prefix.'_hour').'" required><option value="">'.scLabel('Hour').'</option>';
    for ($hour = 1; $hour <= 12; $hour++) {
        $value = sprintf('%02d', $hour);
        echo '<option value="'.$value.'"'.($selectedHour === $value ? ' selected' : '').'>'.$value.'</option>';
    }
    echo '</select></label><label><span>'.scLabel('Minute').'</span><select name="'.scEscape($prefix.'_minute').'" required><option value="">'.scLabel('Minute').'</option>';
    for ($minute = 0; $minute < 60; $minute++) {
        $value = sprintf('%02d', $minute);
        echo '<option value="'.$value.'"'.($selectedMinute === $value ? ' selected' : '').'>'.$value.'</option>';
    }
    echo '</select></label><label><span>'.scLabel('AM / PM').'</span><select name="'.scEscape($prefix.'_period').'" required><option value="">'.scLabel('AM / PM').'</option>';
    foreach (['AM','PM'] as $period) {
        echo '<option value="'.$period.'"'.($selectedPeriod === $period ? ' selected' : '').'>'.scLabel($period).'</option>';
    }
    echo '</select></label></div></div>';
}
function scRemarks(string $name = 'remarks', string $label = 'Remarks', int $max = 500): void
{
    echo '<label class="sc-wide"><span>'.scLabel($label).'</span><textarea name="'.scEscape($name).'" rows="2" maxlength="'.$max.'">'.scEscape(is_string($_POST[$name]??null)?$_POST[$name]:'').'</textarea></label>';
}
function scCategorySelect(array $categories): void
{
    echo '<label><span>'.scLabel('Spectacle Type').' *</span><select name="spectacle_category_id" required><option value="">'.scLabel('Select type').'</option>';
    foreach ($categories as $category) {
        $label = scLabel($category['name']) . (($category['status'] ?? 'active') === 'active' ? '' : ' (' . scLabel('Inactive') . ')');
        echo '<option value="'.(int)$category['id'].'"'.((string)($_POST['spectacle_category_id']??'')===(string)$category['id']?' selected':'').'>'.$label.'</option>';
    }
    echo '</select></label>';
}
function scSubmit(string $label, bool $aidLayout = false): void
{
    $actionsClass = $aidLayout ? 'aid-form-actions' : 'sc-wide sc-actions';
    $buttonClass = $aidLayout ? 'submit-aid-button' : 'admin-primary-action';
    echo '<div class="'.$actionsClass.'"><button class="'.$buttonClass.'" type="submit">'.scLabel($label).'</button></div></form>';
}
function scDecision(bool $quantity = false, int $maximum = 0): void
{
    echo '<label><span>'.scLabel('Decision').'</span><select name="decision" data-sc-decision required><option value="">'.scLabel('Select').'</option><option value="approved">'.scLabel('Approve').'</option><option value="rejected">'.scLabel('Reject').'</option></select></label>';
    if ($quantity) scInput('Approved quantity','approved_quantity','number',false,'min="1" max="'.$maximum.'" data-sc-approval-only',(string)$maximum);
    echo '<label class="sc-wide"><span>'.scLabel('Reason / remarks').'</span><textarea name="reason" rows="2" maxlength="500" data-sc-rejection-reason placeholder="'.scLabel('Required when rejecting the request').'"></textarea></label>';
}
function scDetails(array $values): void
{
    echo '<dl class="correction-details aid-request-card-details sc-details">';
    foreach ($values as $label=>$value) echo '<div><dt>'.scLabel($label).'</dt><dd>'.scEscape($value).'</dd></div>';
    echo '</dl>';
}

function scParticipantRegistrationLink(array $camp, array $actor, string $from = 'spectacle-camps'): void
{
    if ($actor['role'] !== 'subject-officer'
        || $camp['status'] !== 'approved' || empty($camp['conducted_at'])
        || (int)($camp['conductor_id']??0)!==(int)$actor['id']) return;
    $from=$from==='my-spectacle-camps'?'my-spectacle-camps':'spectacle-camps';
    echo '<a class="admin-primary-action sc-action-register" href="dashboard.php?page=spectacle-camp-register&amp;camp_id='.(int)$camp['id'].'&amp;from='.$from.'">'.scLabel('Register Participant').'</a>';
}

/** A standalone Aid Request-style form; details and screening decision save together. */
function scParticipantRegistrationForm(array $camp, array $gnDivisions, int $restrictionMonths = 0, array $participants = [], array $spectacleCategories = []): void
{
    ?>
    <section class="aid-form-card sc-beneficiary-card" id="camp-beneficiary-form">
        <header class="aid-card-header"><h2><?= scLabel('Camp Beneficiary Registration') ?></h2><span class="sc-badge sc-approved">VC-<?= (int)$camp['id'] ?></span></header>
        <?php scFormStart('save-participant',(int)$camp['id'],true); ?>
        <?php $undecided=array_filter($participants,static fn(array $p):bool=>$p['status']==='registered'); if ($undecided): ?>
        <label><?= scLabel('Participant') ?>
            <select name="participant_id" data-sc-existing-participant>
                <option value=""><?= scLabel('New beneficiary') ?></option>
                <?php foreach ($undecided as $p): $fields=array_intersect_key($p,array_flip(['full_name','gender','nic','elder_card_number','address','phone','gn_division_id','spectacle_category_id'])); ?>
                <option value="<?= (int)$p['id'] ?>" data-participant="<?= scEscape(json_encode($fields,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)) ?>" <?= (string)($_POST['participant_id']??'')===(string)$p['id']?'selected':'' ?>><?= scEscape($p['full_name'].' · '.($p['nic']?:$p['elder_card_number'])) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php endif; ?>
        <fieldset>
            <legend><?= scLabel('Beneficiary Details') ?></legend>
            <div class="aid-form-grid three-columns">
                <?php scInput('Full name','full_name','text',true,'maxlength="150" autocomplete="name"'); ?>
                <label><?= scLabel('Gender') ?> *<select name="gender" required><option value=""><?= scLabel('Select') ?></option><?php foreach (['male'=>'Male','female'=>'Female','other'=>'Other'] as $value=>$label): ?><option value="<?= $value ?>" <?= ($_POST['gender']??'')===$value?'selected':'' ?>><?= scLabel($label) ?></option><?php endforeach; ?></select></label>
                <?php scInput('Phone Number','phone','tel',false,'maxlength="25" autocomplete="tel"'); ?>
            </div>
            <div class="aid-form-grid"><?php scInput('Address','address','text',true,'maxlength="255" autocomplete="street-address"'); ?></div>
        </fieldset>
        <fieldset>
            <legend><?= scLabel('Identification') ?></legend>
            <p id="camp-identification-help"><?= scLabel('NIC or Elder Card is required. You may enter both.') ?></p>
            <p class="sc-division-conflict" data-sc-division-conflict role="alert" hidden></p>
            <div class="aid-form-grid two-columns">
                <?php scInput('NIC Number','nic','text',false,'maxlength="12" pattern="(?:[0-9]{9}[VvXx]|[0-9]{12})" aria-describedby="camp-identification-help"'); ?>
                <?php scInput('Elder Card Number','elder_card_number','text',false,'maxlength="50" aria-describedby="camp-identification-help"'); ?>
            </div>
        </fieldset>
        <fieldset>
            <legend><?= scLabel('Beneficiary Location') ?></legend>
            <div class="aid-form-grid three-columns">
                <label><?= scLabel('District') ?><input value="<?= scEscape($camp['district_name']) ?>" readonly><input type="hidden" name="district_id" value="<?= (int)$camp['district_id'] ?>"></label>
                <label><?= scLabel('DS Division') ?><input value="<?= scEscape($camp['division_name']) ?>" readonly><input type="hidden" name="ds_division_id" value="<?= (int)$camp['ds_division_id'] ?>"></label>
                <label><?= scLabel('GN Division') ?> *
                    <select name="gn_division_id" required>
                        <option value=""><?= scLabel('Select GN Division') ?></option>
                        <?php foreach ($gnDivisions as $gn): ?><option value="<?= (int)$gn['id'] ?>" <?= (string)($_POST['gn_division_id']??'')===(string)$gn['id']?'selected':'' ?>><?= scEscape($gn['name']) ?></option><?php endforeach; ?>
                    </select>
                </label>
            </div>
        </fieldset>
        <fieldset>
            <legend><?= scLabel('Decision') ?></legend>
            <div class="aid-form-grid two-columns">
                <label><?= scLabel('Decision') ?> *
                    <select name="decision" data-sc-decision required>
                        <option value=""><?= scLabel('Select') ?></option>
                        <option value="approved" <?= ($_POST['decision']??'')==='approved'?'selected':'' ?>><?= scLabel('Selected for spectacles') ?></option>
                        <option value="rejected" <?= ($_POST['decision']??'')==='rejected'?'selected':'' ?>><?= scLabel('Rejected') ?></option>
                    </select>
                </label>
            </div>
            <div class="sc-decision-detail" data-sc-approved-section <?= ($_POST['decision']??'')==='approved'?'':'hidden' ?>>
                <p class="sc-period-note"><?= scLabel('Repeat issue waiting period (months)') ?>: <strong><?= $restrictionMonths ?></strong><br><?= scLabel($restrictionMonths>0?'Previous distributions are checked using NIC or Elder Card before selection and issue.':'No waiting period is configured. Set it in the Spectacles eligibility rule if required.') ?></p>
                <fieldset class="spectacle-type-choice sc-spectacle-type-choice"><legend><?= scLabel('Spectacle Type') ?> *</legend>
                    <div class="sc-spectacle-type-options">
                        <?php foreach ($spectacleCategories as $category): ?>
                            <label><input type="radio" name="spectacle_category_id" value="<?= $category['id'] ?>" data-sc-approval-only <?= (string)($_POST['spectacle_category_id']??'')===(string)$category['id']?'checked':'' ?>><span><?= scLabel($category['name']) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            </div>
            <div class="sc-decision-detail" data-sc-rejected-section <?= ($_POST['decision']??'')==='rejected'?'':'hidden' ?>>
                <label class="full-field"><?= scLabel('Rejection Reason') ?> *<textarea name="reason" rows="3" maxlength="500" data-sc-rejection-reason placeholder="<?= scLabel('Required when rejecting the request') ?>"><?= scEscape(is_string($_POST['reason']??null)?$_POST['reason']:'') ?></textarea></label>
            </div>
        </fieldset>
        <?php scSubmit('Save participant',true); ?>
    </section>
    <?php
}

/** Each beneficiary has an independent issue form; only stock held by this officer counts. */
function scDistributionTable(array $camp, array $participants, array $balances, bool $canDistribute, bool $isSubject, array $subjectReady = []): void
{
    scTableStart(['Participant','NIC / Elder Card','Status','Stock available to you','Distribution date','Distribution time','Remarks','Action']);
    foreach ($participants as $p):
        $balance = $balances[(int)($p['spectacle_category_id']??0)] ?? [];
        $held = max(0,(int)($balance[$isSubject?'subject':'mine']??0));
        $available = $canDistribute ? ($isSubject ? min($held,max(0,(int)($subjectReady[(int)($p['spectacle_category_id']??0)]??0))) : $held) : 0;
        $pending = $p['status']==='approved' && empty($p['distribution_id']);
        $enabled = $canDistribute && $pending && $available>0;
        $formId = 'sc-distribute-'.(int)$p['id'];
        $retry = ($_POST['action']??'')==='distribute' && (int)($_POST['participant_id']??0)===(int)$p['id'];
        ?>
        <tr data-sc-row>
            <td><strong><?= scEscape($p['full_name']) ?></strong><small>SCP-<?= (int)$p['id'] ?> · <?= scLabel((string)($p['spectacle_category_name']??'Not selected')) ?></small></td>
            <td><?= scEscape(implode(' / ',array_filter([$p['nic'],$p['elder_card_number']]))) ?></td>
            <td><?php scBadge($p['distribution_id']?'completed':$p['status'],$p['distribution_id']?'Spectacles Distributed':($pending?'Pending Distribution':null)); ?></td>
            <td><?= $canDistribute?$available:'—' ?></td>
            <td><?php if($canDistribute && $pending): ?><input class="sc-row-input" type="date" name="distribution_date" form="<?= $formId ?>" aria-label="<?= scLabel('Distribution date') ?>" min="<?= scEscape($camp['camp_date']) ?>" max="<?= scToday() ?>" value="<?= scEscape($retry && is_string($_POST['distribution_date']??null)?$_POST['distribution_date']:scToday()) ?>" required <?= $enabled?'':'disabled' ?>><?php else: ?><?= scEscape($p['distribution_date']??'—') ?><?php endif; ?></td>
            <td><?php if($canDistribute && $pending): ?><input class="sc-row-input" type="time" name="distribution_time" form="<?= $formId ?>" aria-label="<?= scLabel('Distribution time') ?>" value="<?= scEscape($retry && is_string($_POST['distribution_time']??null)?$_POST['distribution_time']:scNowTime()) ?>" required <?= $enabled?'':'disabled' ?>><?php else: ?><?= !empty($p['distribution_time'])?scEscape(scCampTimeLabel($p['distribution_time'])):'—' ?><?php endif; ?></td>
            <td><?php if($canDistribute && $pending): ?><input class="sc-row-input" name="remarks" form="<?= $formId ?>" maxlength="500" aria-label="<?= scLabel('Remarks') ?>" value="<?= scEscape($retry && is_string($_POST['remarks']??null)?$_POST['remarks']:'') ?>" <?= $enabled?'':'disabled' ?>><?php else: ?>—<?php endif; ?></td>
            <td><?php if($canDistribute && $pending): ?>
                <form id="<?= $formId ?>" method="post" class="sc-distribute-row">
                    <input type="hidden" name="csrf_token" value="<?= scEscape(csrfToken()) ?>">
                    <input type="hidden" name="action" value="distribute">
                    <input type="hidden" name="camp_id" value="<?= (int)$camp['id'] ?>">
                    <input type="hidden" name="participant_id" value="<?= (int)$p['id'] ?>">
                    <input type="hidden" name="request_token" value="<?= bin2hex(random_bytes(16)) ?>">
                    <button type="submit" class="admin-primary-action" <?= $enabled?'':'disabled' ?>><?= scLabel('Distribute') ?></button>
                    <?php if(!$enabled): ?><small><?= scLabel($isSubject && $held>0 ? 'Available on the planned distribution date' : 'Awaiting stock') ?></small><?php endif; ?>
                </form>
            <?php else: ?>—<?php endif; ?></td>
        </tr>
    <?php endforeach;
    scTableEnd(8,$participants===[]);
}

function scParticipantTable(array $participants, ?string $backPage = null, ?string $backLabel = null, ?array $distributionCamp = null, array $subjectReady = [], array $balances = [], array $noticeSchedules = [], bool $ssoHandover = false, bool $isSubjectDistributor = true): void
{
    usort($participants,static fn(array $left,array $right):int=>(int)$left['id']<=>(int)$right['id']);
    $selected=0; $distributed=0;
    foreach ($participants as $participant) {
        if ($participant['status']!=='approved') continue;
        $selected++;
        if (!empty($participant['distribution_id'])) $distributed++;
    }
    $spectacleStatusClass=$selected===0?'none':scDistributionProgress($selected,$distributed,$ssoHandover);
    $spectacleStatus=$selected===0?'No spectacles selected':match($spectacleStatusClass) {
        'in-distribution'=>'In Distribution', 'sso-handover'=>'SSO Handover', 'distributed'=>'Distributed', default=>'Pending Distribution',
    };
    echo '<div class="sc-camp-spectacle-status">';
    if (in_array($backPage,['my-spectacle-camps','spectacle-camps'],true)) {
        $backLabel ??= $backPage==='my-spectacle-camps'?'My Camps':'All Vision Camps';
        echo '<a class="outline-action sc-participant-back" href="dashboard.php?page='.$backPage.'">&larr; '.scLabel($backLabel).'</a>';
    }
    echo '<span class="sc-camp-spectacle-label">'.scLabel('Spectacle Status').'</span>';
    scBadge($spectacleStatusClass,$spectacleStatus);
    echo '</div>';
    $headers=['Participant','Gender','NIC / Elder Card','Address','GN Division','Phone Number','Spectacle Type','Status','SSO Handover date','SSO Distribution date','Rejection Reason'];
    if ($distributionCamp) $headers[]='Action';
    scTableStart($headers,true,true);
    foreach ($participants as $registrationIndex=>$p): ?>
    <?php $participantStatus=!empty($p['distribution_id'])?'distributed':($p['status']==='approved' && $ssoHandover && !empty($p['sso_handover_date'])?'sso-handover':$p['status']); ?>
    <tr data-sc-row data-sc-status="<?= scEscape($participantStatus) ?>">
        <td><strong><?= $registrationIndex+1 ?>. <?= scEscape($p['full_name']) ?></strong><small>SCP-<?= (int)$p['id'] ?></small></td>
        <td><?= $p['gender']?scLabel(ucfirst($p['gender'])):'—' ?></td>
        <td><?= scEscape(implode(' / ',array_filter([$p['nic'],$p['elder_card_number']]))) ?></td>
        <td><?= scEscape($p['address']) ?></td><td><?= scEscape($p['gn_name']) ?></td>
        <td><?= scEscape($p['phone']??'—') ?></td>
        <td><?= !empty($p['spectacle_category_name'])?scEscape($p['spectacle_category_name']):'&mdash;' ?></td>
        <td><?php scBadge($participantStatus,!empty($p['distribution_id'])?'Distributed':($participantStatus==='sso-handover'?'SSO Handover':($p['status']==='approved'?'Selected for spectacles':($p['status']==='registered'?'Pending decision':null)))); ?></td>
        <td><?php if ($p['status']==='approved' && !empty($p['sso_handover_date']) && (empty($p['distribution_id']) || ($p['source']??'')==='social-service-officer')): ?><span><?= scEscape(scCampDateLabel((string)$p['sso_handover_date'])) ?></span><?php if (!empty($p['sso_handover_name'])): ?><small><?= scEscape($p['sso_handover_name']) ?></small><?php endif; ?><?php else: ?>&mdash;<?php endif; ?></td>
        <td><?php if (!empty($p['distribution_id']) && ($p['source']??'')==='social-service-officer'): ?><span><?= scEscape(scCampDateLabel((string)$p['distribution_date'])) ?></span><?php if (!empty($p['distribution_time'])): ?><small><?= scEscape(scCampTimeLabel((string)$p['distribution_time'])) ?></small><?php endif; ?><?php else: ?>&mdash;<?php endif; ?></td>
        <td><?= scEscape($p['rejection_reason']??'—') ?></td>
        <?php if ($distributionCamp): $categoryId=(int)($p['spectacle_category_id']??0); ?><td class="sc-participant-distribute-cell">
            <?php if ($p['status']==='approved' && empty($p['distribution_id'])):
                $held=max(0,(int)($balances[$categoryId][$isSubjectDistributor?'subject':'mine']??0));
                $available=$isSubjectDistributor?min($held,max(0,(int)($subjectReady[$categoryId]??0))):$held;
            ?><form method="post" class="sc-participant-distribute-form">
                <input type="hidden" name="csrf_token" value="<?= scEscape(csrfToken()) ?>">
                <input type="hidden" name="action" value="distribute">
                <input type="hidden" name="camp_id" value="<?= (int)$distributionCamp['id'] ?>">
                <input type="hidden" name="participant_id" value="<?= (int)$p['id'] ?>">
                <input type="hidden" name="distribution_date" value="<?= scEscape(scToday()) ?>">
                <input type="hidden" name="request_token" value="<?= bin2hex(random_bytes(16)) ?>">
                <label class="sc-participant-distribute-time"><?= scLabel('Distribution time') ?><input type="time" name="distribution_time" value="<?= scEscape(scNowTime()) ?>" required <?= $available>0?'':'disabled' ?>></label>
                <button type="submit" class="admin-primary-action" <?= $available>0?'':'disabled' ?>><?= scLabel('Distribute') ?></button>
                <?php if ($available<1): ?><small><?= scLabel($isSubjectDistributor && $held>0?'Available on the planned distribution date':'Awaiting stock') ?></small><?php endif; ?>
            </form><?php endif; ?>
            <?php if ($p['status']!=='approved'): ?>&mdash;<?php endif; ?>
        </td><?php endif; ?>
    </tr>
    <?php endforeach;
    scTableEnd(count($headers),$participants===[]);
}

/** Shared Subject Officer action shown in Camp History and camp details. */
function scConductCampAction(array $camp, array $actor, bool $compact = false): void
{
    if ($actor['role'] !== 'subject-officer'
        || $camp['status'] !== 'approved' || !empty($camp['conducted_at'])) return;
    $scheduled = $camp['camp_date'] > scToday();
    ?>
    <form method="post" class="sc-conduct-form<?= $compact ? ' is-compact' : '' ?>">
        <input type="hidden" name="csrf_token" value="<?= scEscape(csrfToken()) ?>">
        <input type="hidden" name="action" value="conduct-camp">
        <input type="hidden" name="camp_id" value="<?= (int) $camp['id'] ?>">
        <input type="hidden" name="request_token" value="<?= bin2hex(random_bytes(16)) ?>">
        <button class="admin-primary-action sc-action-conduct" type="submit" <?= $scheduled ? 'disabled title="'.scLabel('Available on the scheduled camp date:').' '.scEscape($camp['camp_date']).'"' : '' ?>><?= scLabel('Conduct Camp') ?></button>
        <?php if (!$compact): ?><small><?= scLabel($scheduled ? 'Available on the scheduled camp date:' : 'Confirm when the camp takes place to open participant registration.') ?><?= $scheduled ? ' '.scEscape($camp['camp_date']) : '' ?></small><?php endif; ?>
    </form>
    <?php
}

function scCompleteCampAction(array $camp, array $actor, bool $compact = false): void
{
    if ($actor['role'] !== 'subject-officer' || $camp['status'] !== 'approved'
        || empty($camp['conducted_at']) || (int)($camp['conductor_id']??0)!==(int)$actor['id']) return;
    $undecided=(int)$camp['participant_count']-(int)$camp['approved_count']-(int)$camp['rejected_count'];
    ?>
    <form method="post" class="sc-complete-form<?= $compact?' is-compact':'' ?>" data-sc-complete-form data-camp-reference="VC-<?= (int)$camp['id'] ?>">
        <input type="hidden" name="csrf_token" value="<?= scEscape(csrfToken()) ?>">
        <input type="hidden" name="action" value="complete-camp">
        <input type="hidden" name="camp_id" value="<?= (int)$camp['id'] ?>">
        <input type="hidden" name="request_token" value="<?= bin2hex(random_bytes(16)) ?>">
        <button type="submit" class="sc-complete-button" <?= $undecided>0?'disabled title="Record a decision for every participant first"':'' ?>><?= scLabel('Complete Camp') ?></button>
    </form>
    <?php
}
/** Camp decisions use the same card and button layout as other pending approvals. */
function scCampApprovalCard(array $row): void
{
    $campId = (int) $row['id'];
    $reason = (int) ($_POST['camp_id'] ?? 0) === $campId && is_string($_POST['reason'] ?? null)
        ? $_POST['reason'] : '';
    ?>
    <article id="vision-camp-request-<?= $campId ?>" class="admin-correction-item admin-notification-target" tabindex="-1">
        <div class="correction-summary">
            <div>
                <div class="correction-reference-line"><strong>VC-<?= $campId ?></strong><span><?= scEscape($row['requester_name']) ?></span><span class="role-label green"><?= scLabel('Subject Officer') ?></span></div>
                <p class="correction-submission-meta"><?= scLabel('Submitted on') ?> <?= scEscape($row['created_at']) ?></p>
            </div>
            <span class="correction-status pending"><?= scLabel('Pending') ?></span>
        </div>
        <?php scDetails([
            'District'=>$row['district_name'], 'DS Division'=>$row['division_name'],
            'Requested By'=>$row['requester_name'], 'Estimated Participants'=>$row['estimated_participants'],
            'Camp Date'=>$row['camp_date'], 'Camp Time'=>$row['camp_time'],
        ]); ?>
        <?php if (!empty($row['remarks'])): ?><p><strong><?= scLabel('Remarks') ?>:</strong> <?= scEscape($row['remarks']) ?></p><?php endif; ?>
        <form method="post" action="dashboard.php?page=pending-approvals&amp;tab=vision-camps" class="admin-decision-form registration-decision-form" data-camp-approval>
            <input type="hidden" name="csrf_token" value="<?= scEscape(csrfToken()) ?>">
            <input type="hidden" name="action" value="decide-camp">
            <input type="hidden" name="camp_id" value="<?= $campId ?>">
            <input type="hidden" name="request_token" value="<?= bin2hex(random_bytes(16)) ?>">
            <label><?= scLabel('Admin note') ?><textarea name="reason" rows="2" maxlength="500" placeholder="<?= scLabel('Required when rejecting the request') ?>" data-required-message="<?= scLabel('A rejection reason is required.') ?>"><?= scEscape($reason) ?></textarea></label>
            <div class="correction-decision-footer registration-decision-footer">
                <a class="outline-action" href="dashboard.php?page=spectacle-camps&amp;camp_id=<?= $campId ?>"><?= scLabel('View details') ?></a>
                <div class="correction-decision-actions">
                    <button name="decision" value="approved" class="approve-button" type="submit">&#10003; <?= scLabel('Approve') ?></button>
                    <button name="decision" value="rejected" class="reject-button" type="submit">&#10005; <?= scLabel('Reject') ?></button>
                </div>
            </div>
        </form>
    </article>
    <?php
}

/** Shared card shell for camp approval, stock approval and store release. */
function scRequestCard(array $row, string $kind, string $role): void
{
    if ($kind === 'camp' && $role === 'admin' && $row['status'] === 'pending') {
        scCampApprovalCard($row);
        return;
    }
    $isCamp=$kind==='camp'; $campId=(int)($row['camp_id']??$row['id']);
    echo '<article class="admin-correction-item store-dispatch-request-card sc-request-card"><div class="correction-summary"><div><strong>'.scLabel($isCamp?'Vision Camp Request':'Vision Camp Stock Request').' · '.($isCamp?'VC-':'SCS-').(int)$row['id'].'</strong><p>'.scLabel('Vision Camp').' — '.scEscape($row['division_name']).'</p></div>';
    scBadge($row['status'],!$isCamp && $row['status']==='approved'?'Approved - Awaiting Store Release':null);
    echo '</div>';
    $details=['District'=>$row['district_name'],'DS Division'=>$row['division_name'],'Requested By'=>$row['requester_name'],'Camp Date'=>$row['camp_date'],'Camp Time'=>$row['camp_time'],'Request Date'=>$row['created_at']];
    if ($isCamp) $details['Estimated Participants']=$row['estimated_participants'];
    else $details += ['Spectacle Type'=>$row['spectacle_category_name']??'—','Approved participants'=>$row['approved_count'],'Requested quantity'=>$row['quantity'],'Approved quantity'=>$row['approved_quantity']??'—','Planned Distribution Date'=>scCampDateLabel($row['planned_distribution_date']),'Planned Distribution Time'=>scCampTimeLabel($row['planned_distribution_time']??null),'Distribution Place'=>$row['planned_distribution_place']?:'—','Release To'=>$row['requester_name']];
    scDetails($details);
    if (!empty($row['remarks'])) echo '<p>'.scEscape($row['remarks']).'</p>';
    if (!empty($row['decision_reason'])) echo '<p>'.scLabel('Reason / remarks').': '.scEscape($row['decision_reason']).'</p>';
    if ($role!=='store-keeper') echo '<a class="outline-action" href="dashboard.php?page=spectacle-camps&amp;camp_id='.$campId.'">'.scLabel('View details').'</a>';
    if ($role==='admin' && $row['status']==='pending') {
        scFormStart($isCamp?'decide-camp':'decide-stock',$campId);
        if (!$isCamp) echo '<input type="hidden" name="stock_request_id" value="'.(int)$row['id'].'">';
        scDecision(!$isCamp,(int)($row['quantity']??0)); scSubmit('Save decision');
    } elseif (!$isCamp && $role==='store-keeper' && $row['status']==='approved') {
        scFormStart('release-stock',$campId);
        echo '<input type="hidden" name="stock_request_id" value="'.(int)$row['id'].'">'; scSubmit('Release to Subject Officer');
    }
    echo '</article>';
}
/** Compact, searchable request list with the existing approval and release actions. */
function scRequestTable(array $rows, string $kind, string $role, bool $adminApprovalQueue = false): void
{
    $isCamp = $kind === 'camp';
    if (!$isCamp) $rows = scGroupStockRequests($rows);
    $headers = $isCamp
        ? ['Camp', 'District / DS Division', 'Camp Date', 'Estimated Participants', 'Requested By', 'Remarks', 'Status', 'Action']
        : ['Request', 'Camp', 'District / DS Division', 'Spectacle Type', 'Quantity', 'Approved', 'Planned Distribution', 'Requested By', 'Status', 'Action'];
    scTableStart($headers);
    foreach ($rows as $row):
        $campId = (int) ($row['camp_id'] ?? $row['id']);
        $rowId = (int) $row['id'];
        $batchRef = (string) ($row['batch_ref'] ?? '');
        $detailPage = 'spectacle-camps';
        ?>
        <tr data-sc-row <?= $isCamp ? 'id="vision-camp-request-' . $rowId . '" class="admin-notification-target" tabindex="-1"' : '' ?>>
            <td><strong><?= $isCamp ? 'VC-'.$rowId : ($batchRef !== '' ? 'SCB-'.scEscape(strtoupper(substr($batchRef,0,8))) : 'SCS-'.$rowId) ?></strong></td>
            <?php if (!$isCamp): ?><td>VC-<?= $campId ?></td><?php endif; ?>
            <td><strong><?= scEscape($row['division_name']) ?></strong><small><?= scEscape($row['district_name']) ?></small></td>
            <?php if ($isCamp): ?>
                <td><?= scEscape($row['camp_date']) ?><small><?= scEscape($row['camp_time']) ?></small></td>
                <td><?= (int) $row['estimated_participants'] ?></td>
            <?php else: ?>
                <td><?php foreach ($row['batch_lines'] as $line): ?><span class="sc-receipt-line-detail"><?= scEscape($line['spectacle_category_name'] ?? '—') ?> <small>× <?= (int) $line['quantity'] ?></small></span><?php endforeach; ?></td>
                <td><?= (int) $row['quantity'] ?></td>
                <td><?= $row['approved_quantity'] !== null ? (int) $row['approved_quantity'] : '—' ?></td>
                <td><?php scCampWhen($row['planned_distribution_date'],$row['planned_distribution_time']??null); ?><?php if (!empty($row['planned_distribution_place'])): ?><small><?= scEscape($row['planned_distribution_place']) ?></small><?php endif; ?></td>
            <?php endif; ?>
            <td><?= scEscape($row['requester_name']) ?><small><?= scEscape($row['created_at']) ?></small></td>
            <?php if ($isCamp): ?><td class="sc-request-remarks"><?= scEscape($row['remarks'] ?: '—') ?></td><?php endif; ?>
            <td><?php scBadge((string) $row['status'], !$isCamp && $row['status'] === 'approved' ? 'Approved - Awaiting Store Release' : null); ?><?php if (!$isCamp && $row['decision_reason']): ?><small><?= scEscape($row['decision_reason']) ?></small><?php endif; ?></td>
            <td class="sc-request-actions">
                <?php if ($role!=='store-keeper'): ?><a class="outline-action" href="dashboard.php?page=<?= $detailPage ?>&amp;camp_id=<?= $campId ?>"><?= scLabel('View details') ?></a><?php endif; ?>
                <?php if ($isCamp && $role === 'admin' && $row['status'] === 'pending'): ?>
                    <form method="post" action="dashboard.php?page=pending-approvals&amp;tab=vision-camps" class="sc-table-action-form" data-camp-approval>
                        <input type="hidden" name="csrf_token" value="<?= scEscape(csrfToken()) ?>">
                        <input type="hidden" name="action" value="decide-camp">
                        <input type="hidden" name="camp_id" value="<?= $campId ?>">
                        <input type="hidden" name="request_token" value="<?= bin2hex(random_bytes(16)) ?>">
                        <textarea name="reason" rows="2" maxlength="500" aria-label="<?= scLabel('Admin note') ?>" placeholder="<?= scLabel('Reason required when rejecting') ?>" data-required-message="<?= scLabel('A rejection reason is required.') ?>"></textarea>
                        <div class="sc-table-action-buttons"><button name="decision" value="approved" class="approve-button" type="submit"><?= scLabel('Approve') ?></button><button name="decision" value="rejected" class="reject-button" type="submit"><?= scLabel('Reject') ?></button></div>
                    </form>
                <?php elseif (!$isCamp && $batchRef !== '' && $role === 'admin' && $row['status'] === 'pending'): ?>
                    <?php scFormStart('decide-stock-batch', $campId); ?><input type="hidden" name="batch_ref" value="<?= scEscape($batchRef) ?>"><?php scDecision(); scSubmit('Save decision'); ?>
                <?php elseif (!$isCamp && $batchRef !== '' && $role === 'store-keeper' && $row['status'] === 'approved'): ?>
                    <?php scFormStart('release-stock-batch', $campId); ?><input type="hidden" name="batch_ref" value="<?= scEscape($batchRef) ?>"><?php scSubmit('Release camp stock batch'); ?>
                <?php elseif (!$isCamp && $role === 'admin' && $row['status'] === 'pending'): ?>
                    <?php scFormStart('decide-stock', $campId); ?><input type="hidden" name="stock_request_id" value="<?= $rowId ?>"><?php scDecision(true, (int) $row['quantity']); scSubmit('Save decision'); ?>
                <?php elseif (!$isCamp && $role === 'store-keeper' && $row['status'] === 'approved'): ?>
                    <?php scFormStart('release-stock', $campId); ?><input type="hidden" name="stock_request_id" value="<?= $rowId ?>"><?php scSubmit('Release to Subject Officer'); ?>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach;
    scTableEnd(count($headers), $rows === [], $adminApprovalQueue ? 'No pending Vision Camp requests.' : 'No records found.');
}

/** Admin approval queues use the same request-card pattern for camps and camp stock. */
function scApprovalCardList(array $rows, string $kind): void
{
    $isCamp = $kind === 'camp';
    if (!$isCamp) $rows = scGroupStockRequests($rows);
    ?>
    <section class="admin-data-card admin-correction-review-card sc-approval-review-card" aria-label="<?= scLabel($isCamp ? 'Vision Camp Requests' : 'Vision Camp Stock Requests') ?>">
        <div class="admin-correction-list sc-approval-list">
        <?php if ($rows === []): ?>
            <div class="empty-corrections sc-approval-empty" role="status">
                <div><strong><?= scLabel($isCamp ? 'No pending Vision Camp requests.' : 'No pending Vision Camp stock requests.') ?></strong><span><?= scLabel('New requests will appear here for approval.') ?></span></div>
            </div>
        <?php else: ?>
        <div class="sc-approval-card-list">
            <?php foreach ($rows as $row):
                $campId = (int) ($isCamp ? $row['id'] : $row['camp_id']);
                $batchRef = $isCamp ? '' : (string) ($row['batch_ref'] ?? '');
                $reference = $isCamp ? 'VC-'.$campId : ($batchRef !== '' ? 'SCB-'.strtoupper(substr($batchRef,0,12)) : 'SCS-'.(int)$row['id']);
                $formAction = $isCamp ? 'dashboard.php?page=pending-approvals&amp;tab=vision-camps' : 'dashboard.php?page=spectacle-camp-stock-approvals';
                $lineCount = $isCamp ? 0 : count($row['batch_lines']);
                ?>
                <article id="<?= $isCamp ? 'vision-camp-request-'.$campId : 'vision-camp-stock-request-'.(int)$row['id'] ?>" class="sc-approval-card admin-notification-target" data-sc-approval-card tabindex="-1">
                    <div class="sc-approval-card-head">
                        <div><strong class="sc-approval-reference"><?= scEscape($reference) ?></strong><span><?= scEscape($row['division_name']) ?> <small>· <?= scEscape($row['district_name']) ?></small></span><a class="sc-approval-detail-link" href="dashboard.php?page=spectacle-camps&amp;camp_id=<?= $campId ?>"><?= scLabel('View details') ?></a></div>
                        <?php scBadge('pending','Need Approval'); ?>
                    </div>
                    <div class="sc-approval-details">
                        <?php if ($isCamp): ?>
                            <div><small><?= scLabel('Camp Schedule') ?></small><?php scCampWhen($row['camp_date'],$row['camp_time']); ?></div>
                            <div><small><?= scLabel('Estimated Participants') ?></small><strong><?= (int)$row['estimated_participants'] ?></strong></div>
                        <?php else: ?>
                            <div><small><?= scLabel('Camp') ?></small><strong>VC-<?= $campId ?></strong></div>
                            <div><small><?= scLabel('Total Units') ?></small><strong><?= (int)$row['quantity'] ?></strong></div>
                            <div><small><?= scLabel('Planned Distribution') ?></small><?php scCampWhen($row['planned_distribution_date'],$row['planned_distribution_time']??null); ?></div>
                            <div><small><?= scLabel('Distribution Place') ?></small><strong><?= scEscape($row['planned_distribution_place'] ?: '—') ?></strong></div>
                        <?php endif; ?>
                        <div class="sc-approval-requester"><small><?= scLabel('Requested By') ?></small><strong><?= scEscape($row['requester_username'] ?? $row['requester_name']) ?></strong><span><?= scCampRoleLabel((string)($row['requester_role'] ?? 'subject-officer')) ?></span><small><?= scEscape($row['created_at']) ?></small></div>
                    </div>
                    <?php if ($isCamp): ?>
                        <?php if (trim((string)$row['remarks']) !== ''): ?><div class="sc-approval-remarks"><small><?= scLabel('Remarks') ?></small><p><?= nl2br(scEscape($row['remarks'])) ?></p></div><?php endif; ?>
                    <?php else: ?>
                        <div class="sc-approval-lines"><strong><?= $lineCount ?> <?= scLabel($lineCount === 1 ? 'Spectacle Type' : 'Spectacle Types') ?></strong><div class="sc-approval-line-head"><span><?= scLabel('Spectacle Type') ?></span><span><?= scLabel('Quantity') ?></span></div><?php foreach ($row['batch_lines'] as $line): ?><div class="sc-approval-line"><span><?= scEscape($line['spectacle_category_name'] ?? '—') ?></span><strong><?= (int)$line['quantity'] ?></strong></div><?php endforeach; ?></div>
                        <?php if (trim((string)$row['remarks']) !== ''): ?><div class="sc-approval-remarks"><small><?= scLabel('Remarks') ?></small><p><?= nl2br(scEscape($row['remarks'])) ?></p></div><?php endif; ?>
                    <?php endif; ?>
                    <form method="post" action="<?= $formAction ?>" class="sc-approval-decision" data-camp-approval>
                        <input type="hidden" name="csrf_token" value="<?= scEscape(csrfToken()) ?>">
                        <input type="hidden" name="request_token" value="<?= bin2hex(random_bytes(16)) ?>">
                        <input type="hidden" name="action" value="<?= $isCamp ? 'decide-camp' : ($batchRef !== '' ? 'decide-stock-batch' : 'decide-stock') ?>">
                        <input type="hidden" name="camp_id" value="<?= $campId ?>">
                        <?php if (!$isCamp): ?><?php if ($batchRef !== ''): ?><input type="hidden" name="batch_ref" value="<?= scEscape($batchRef) ?>"><?php else: ?><input type="hidden" name="stock_request_id" value="<?= (int)$row['id'] ?>"><label class="sc-approval-quantity"><?= scLabel('Approved quantity') ?><input type="number" name="approved_quantity" min="1" max="<?= (int)$row['quantity'] ?>" value="<?= (int)$row['quantity'] ?>"></label><?php endif; ?><?php endif; ?>
                        <label class="sc-approval-note"><?= scLabel('Admin note') ?><textarea name="reason" rows="2" maxlength="500" placeholder="<?= scLabel('Reason required when rejecting') ?>" data-required-message="<?= scLabel('A rejection reason is required.') ?>"></textarea></label>
                        <div class="sc-approval-footer"><small><?= scLabel($isCamp ? 'Approval allows the camp to be conducted.' : 'Approval reserves the full camp stock batch for Store Keeper release.') ?></small><div><button type="submit" name="decision" value="approved" class="approve-button"><?= scLabel('Approve') ?></button><button type="submit" name="decision" value="rejected" class="reject-button"><?= scLabel('Reject') ?></button></div></div>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        </div>
    </section>
    <?php
}

/** Store Keeper receives one release card per approved camp batch. */
function scReleaseCardList(array $rows): void
{
    $rows = scGroupStockRequests($rows);
    ?>
    <section class="sc-approval-list" aria-label="<?= scLabel('Camp Release Requests') ?>">
        <label class="sc-approval-search"><span><?= scLabel('Search') ?></span><input type="search" data-sc-approval-search placeholder="<?= scLabel('Search requests') ?>" autocomplete="off"></label>
        <?php if ($rows === []): ?>
            <div class="sc-approval-empty"><strong><?= scLabel('No approved camp stock requests to release.') ?></strong><span><?= scLabel('Admin-approved camp batches will appear here.') ?></span></div>
        <?php else: ?>
        <div class="sc-approval-card-list">
            <?php foreach ($rows as $row):
                $campId = (int) $row['camp_id'];
                $batchRef = (string) ($row['batch_ref'] ?? '');
                $reference = $batchRef !== '' ? 'SCB-'.strtoupper(substr($batchRef,0,12)) : 'SCS-'.(int)$row['id'];
                $lineCount = count($row['batch_lines']);
                ?>
                <article class="sc-approval-card sc-release-card" data-sc-approval-card>
                    <div class="sc-approval-card-head">
                        <div><strong class="sc-approval-reference"><?= scEscape($reference) ?></strong><span>VC-<?= $campId ?> &middot; <?= scEscape($row['division_name']) ?> <small>&middot; <?= scEscape($row['district_name']) ?></small></span></div>
                        <?php scBadge('approved','Ready to Release'); ?>
                    </div>
                    <div class="sc-approval-details">
                        <div><small><?= scLabel('Approved Units') ?></small><strong><?= (int)($row['approved_quantity'] ?? $row['quantity']) ?></strong></div>
                        <div><small><?= scLabel('Planned Distribution') ?></small><?php scCampWhen($row['planned_distribution_date'],$row['planned_distribution_time']??null); ?></div>
                        <div><small><?= scLabel('Distribution Place') ?></small><strong><?= scEscape($row['planned_distribution_place'] ?: '—') ?></strong></div>
                        <div class="sc-approval-requester"><small><?= scLabel('Release To') ?></small><strong><?= scEscape($row['requester_username'] ?? $row['requester_name']) ?></strong><span><?= scCampRoleLabel((string)($row['requester_role'] ?? 'subject-officer')) ?></span></div>
                        <div><small><?= scLabel('Requested At') ?></small><strong><?= scEscape($row['created_at']) ?></strong></div>
                    </div>
                    <div class="sc-approval-lines"><strong><?= $lineCount ?> <?= scLabel($lineCount === 1 ? 'Spectacle Type' : 'Spectacle Types') ?></strong><div class="sc-approval-line-head"><span><?= scLabel('Spectacle Type') ?></span><span><?= scLabel('Approved') ?></span></div><?php foreach ($row['batch_lines'] as $line): ?><div class="sc-approval-line"><span><?= scEscape($line['spectacle_category_name'] ?? '—') ?></span><strong><?= (int)($line['approved_quantity'] ?? $line['quantity']) ?></strong></div><?php endforeach; ?></div>
                    <?php if (trim((string)$row['remarks']) !== ''): ?><div class="sc-approval-remarks"><small><?= scLabel('Remarks') ?></small><p><?= nl2br(scEscape($row['remarks'])) ?></p></div><?php endif; ?>
                    <?php if (trim((string)$row['decision_reason']) !== ''): ?><div class="sc-approval-remarks"><small><?= scLabel('Admin note') ?></small><p><?= nl2br(scEscape($row['decision_reason'])) ?></p></div><?php endif; ?>
                    <form method="post" action="dashboard.php?page=spectacle-camp-releases" class="sc-approval-decision sc-release-action">
                        <input type="hidden" name="csrf_token" value="<?= scEscape(csrfToken()) ?>">
                        <input type="hidden" name="request_token" value="<?= bin2hex(random_bytes(16)) ?>">
                        <input type="hidden" name="camp_id" value="<?= $campId ?>">
                        <?php if ($batchRef !== ''): ?><input type="hidden" name="action" value="release-stock-batch"><input type="hidden" name="batch_ref" value="<?= scEscape($batchRef) ?>"><?php else: ?><input type="hidden" name="action" value="release-stock"><input type="hidden" name="stock_request_id" value="<?= (int)$row['id'] ?>"><?php endif; ?>
                        <div class="sc-approval-footer"><small><?= scLabel('Release this approved stock to the requesting Subject Officer.') ?></small><button type="submit" class="sc-release-button"><?= scLabel($batchRef !== '' ? 'Release camp stock batch' : 'Release to Subject Officer') ?></button></div>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="sc-approval-empty" data-sc-approval-empty hidden><strong><?= scLabel('No matching requests.') ?></strong></div>
        <?php endif; ?>
    </section>
    <?php
}
function scTableStart(array $headers, bool $search = true, bool $statusFilter = false, bool $campStatusFilter = false, array $campDistricts = [], bool $stockStatusFilter = false, bool $distributionStatusFilter = false): void
{
    echo '<div class="sc-table-section">';
    if ($statusFilter) echo '<div class="sc-participant-filters"><label class="sc-table-search"><span>'.scLabel('Search').'</span><input type="search" data-sc-search placeholder="'.scLabel('Search participants by name, ID or spectacle type').'"></label><label class="sc-table-search"><span>'.scLabel('Status').'</span><select data-sc-status-filter><option value="">'.scLabel('All statuses').'</option><option value="approved">'.scLabel('Selected for spectacles').'</option><option value="sso-handover">'.scLabel('SSO Handover').'</option><option value="distributed">'.scLabel('Distributed').'</option><option value="rejected">'.scLabel('Rejected').'</option></select></label></div>';
    elseif ($campStatusFilter) {
        echo '<div class="sc-participant-filters"><label class="sc-table-search"><span>'.scLabel('Search').'</span><input type="search" data-sc-search placeholder="'.scLabel('Search this table').'"></label><label class="sc-table-search"><span>'.scLabel('Status').'</span><select data-sc-camp-status-filter><option value="">'.scLabel('All statuses').'</option><option value="pending">'.scLabel('Need Approval').'</option><option value="awaiting-conduct">'.scLabel('Awaiting Conduct').'</option><option value="conducted">'.scLabel('Camp Conducted').'</option><option value="completed">'.scLabel('Completed').'</option><option value="rejected">'.scLabel('Rejected').'</option></select></label>';
        if ($campDistricts) {
            echo '<label class="sc-table-search"><span>'.scLabel('District').'</span><select data-sc-camp-district-filter><option value="">'.scLabel('All districts').'</option>';
            foreach ($campDistricts as $id=>$name) echo '<option value="'.(int)$id.'">'.scEscape($name).'</option>';
            echo '</select></label>';
        }
        if ($stockStatusFilter) echo '<label class="sc-table-search"><span>'.scLabel('Stock request status').'</span><select data-sc-stock-status-filter><option value="">'.scLabel('All stock statuses').'</option><option value="none">'.scLabel('Not requested').'</option><option value="pending">'.scLabel('Pending Admin approval').'</option><option value="approved">'.scLabel('Awaiting Store Release').'</option><option value="rejected">'.scLabel('Rejected').'</option><option value="released">'.scLabel('Released').'</option></select></label>';
        if ($distributionStatusFilter) echo '<label class="sc-table-search"><span>'.scLabel('Distribution status').'</span><select data-sc-distribution-status-filter><option value="">'.scLabel('All distribution statuses').'</option><option value="pending">'.scLabel('Pending Distribution').'</option><option value="in-distribution">'.scLabel('In Distribution').'</option><option value="sso-handover">'.scLabel('SSO Handover').'</option><option value="distributed">'.scLabel('Distributed').'</option></select></label>';
        echo '</div>';
    }
    elseif ($search) echo '<label class="sc-table-search"><span>'.scLabel('Search').'</span><input type="search" data-sc-search placeholder="'.scLabel('Search this table').'"></label>';
    echo '<div class="sc-table-wrap" tabindex="0" role="region" aria-label="'.scLabel('Vision Camp records').'"><table class="aid-request-list-table sc-table"><thead><tr>';
    foreach ($headers as $header) echo '<th scope="col">'.scLabel($header).'</th>';
    echo '</tr></thead><tbody>';
}
function scTableEnd(int $columns, bool $empty, string $emptyMessage = 'No records found.'): void
{
    echo '<tr data-sc-empty'.($empty?'':' hidden').'><td colspan="'.$columns.'">'.scLabel($emptyMessage).'</td></tr></tbody></table></div></div>';
}
