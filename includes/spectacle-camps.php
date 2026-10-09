<?php
declare(strict_types=1);

require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/beneficiary-division-guard.php';

function scQuery(PDO $db, string $sql, array $params = []): PDOStatement
{
    $query = $db->prepare($sql);
    $query->execute($params);
    return $query;
}

function scActor(PDO $db, int $userId): array
{
    $actor = scQuery($db, "SELECT id,role,ds_division_id FROM users WHERE id=? AND status='active'", [$userId])->fetch(PDO::FETCH_ASSOC);
    if (!$actor || !in_array($actor['role'], ['admin','subject-officer','store-keeper','social-service-officer'], true)) {
        throw new DomainException('Access denied.');
    }
    return $actor;
}

function scCanView(array $actor, array $camp): bool
{
    return match ($actor['role']) {
        'admin' => true,
        // Subject Officers share camp operations; My Camps is a separate personal list.
        'subject-officer' => true,
        'social-service-officer' => (int) $actor['ds_division_id'] > 0
            && (int) $actor['ds_division_id'] === (int) $camp['ds_division_id']
            && in_array($camp['status'], ['approved','completed'], true),
        // Approval alone does not start the Store Keeper's stock workflow.
        'store-keeper' => in_array($camp['status'], ['approved','completed'], true)
            && !empty($camp['conducted_at']),
        default => false,
    };
}

function scCamp(PDO $db, int $id, array $actor, bool $lock = false): array
{
    $camp = scQuery($db, 'SELECT * FROM spectacle_camps WHERE id=?' . ($lock ? ' FOR UPDATE' : ''), [$id])->fetch(PDO::FETCH_ASSOC);
    if (!$camp || !scCanView($actor, $camp)) { throw new DomainException('Vision Camp not found or access denied.'); }
    return $camp;
}

function scText(array $input, string $key, int $max, bool $required = false): string
{
    $value = is_string($input[$key] ?? null) ? trim($input[$key]) : '';
    if (($required && $value === '') || mb_strlen($value) > $max) {
        throw new DomainException('Enter a valid value for ' . str_replace('_', ' ', $key) . '.');
    }
    return $value;
}

function scPositive(array $input, string $key): int
{
    $value = filter_var($input[$key] ?? null, FILTER_VALIDATE_INT);
    if ($value === false || $value < 1 || $value > 100000) { throw new DomainException('Enter a valid positive quantity or selection.'); }
    return $value;
}

/** Match prior spectacle issues by either identifier, across camps and ordinary aid. */
function scSpectacleWaitingPeriod(PDO $db, int $itemId, string $nic, string $elder, ?string $onDate = null): array
{
    $lock=$db->inTransaction()?' FOR UPDATE':'';
    $rules=scQuery($db,"SELECT restriction_months FROM disability_aid_items WHERE item_id=? AND is_system=1 AND status='active' ORDER BY id".$lock,[$itemId])->fetchAll(PDO::FETCH_COLUMN);
    if (!$rules) throw new DomainException('Built-in Spectacles configuration is missing. Run database migrations.');
    $months=max(array_map('intval',$rules));
    $result=['months'=>$months,'last_received'=>null,'eligible_on'=>null,'eligible'=>true];
    if ($nic==='' && $elder==='') return $result;
    $campDate=scQuery($db,"SELECT d.distribution_date FROM spectacle_camp_distributions d
        JOIN spectacle_camp_participants p ON p.id=d.participant_id JOIN spectacle_camps c ON c.id=d.camp_id
        WHERE c.item_id=? AND ((?<>'' AND UPPER(TRIM(p.nic))=?) OR (?<>'' AND UPPER(TRIM(p.elder_card_number))=?))
        ORDER BY d.distribution_date DESC,d.id DESC LIMIT 1".$lock,[$itemId,$nic,$nic,$elder,$elder])->fetchColumn();
    $ordinaryDate=scQuery($db,"SELECT d.distributed_at FROM distributions d JOIN beneficiaries b ON b.id=d.beneficiary_id
        WHERE d.item_id=? AND ((?<>'' AND UPPER(TRIM(b.nic))=?) OR (?<>'' AND UPPER(TRIM(b.elders_card_number))=?))
          AND (SELECT COALESCE(SUM(ret.quantity),0) FROM item_returns ret WHERE ret.distribution_id=d.id AND ret.stock_review_status='accepted') < d.quantity
        ORDER BY d.distributed_at DESC,d.id DESC LIMIT 1".$lock,[$itemId,$nic,$nic,$elder,$elder])->fetchColumn();
    $dates=array_filter([$campDate?substr((string)$campDate,0,10):null,$ordinaryDate?substr((string)$ordinaryDate,0,10):null]);
    if (!$dates) return $result;
    $result['last_received']=max($dates);
    // Calendar months, clamped to the target month's last day (e.g. 31 January + 1 month).
    $last=new DateTimeImmutable($result['last_received']);
    $target=$last->modify('first day of this month')->modify('+'.$months.' months');
    $day=min((int)$last->format('j'),(int)$target->format('t'));
    $result['eligible_on']=$target->setDate((int)$target->format('Y'),(int)$target->format('m'),$day)->format('Y-m-d');
    $result['eligible']=$months===0 || $result['eligible_on']<=($onDate??scToday());
    return $result;
}

function scAssertSpectacleWaitingPeriod(PDO $db, array $camp, array $identity, ?string $onDate = null): void
{
    $check=scSpectacleWaitingPeriod($db,(int)$camp['item_id'],strtoupper(trim((string)($identity['nic']??''))),strtoupper(trim((string)($identity['elder_card_number']??''))),$onDate);
    if (!$check['eligible']) {
        $message='This beneficiary received spectacles on %s and cannot receive them again until %s.';
        throw new DomainException(sprintf(function_exists('t')?t($message):$message,$check['last_received'],$check['eligible_on']));
    }
}

/** Reserve an identity once it enters a camp, as well as enforcing prior issue dates. */
function scAssertParticipantRegistrationEligibility(PDO $db, array $camp, array $identity, ?int $currentParticipantId = null): void
{
    $nic = strtoupper(trim((string)($identity['nic'] ?? '')));
    $elder = strtoupper(trim((string)($identity['elder_card_number'] ?? '')));
    assertBeneficiaryIdentityDivision($db, $nic, $elder, (int)$camp['ds_division_id']);
    scAssertSpectacleWaitingPeriod($db, $camp, ['nic' => $nic, 'elder_card_number' => $elder]);

    // Camp registration must respect the same unfinished-request and related-item
    // restrictions as ordinary aid requests when the identity is known there.
    $activeAid = scQuery($db, "SELECT ar.id,i.item_name FROM aid_requests ar
        JOIN beneficiaries b ON b.id=ar.beneficiary_id JOIN inventory_items i ON i.id=ar.item_id
        WHERE ar.status IN ('pending','approved','goods-requested')
          AND ((?<>'' AND UPPER(TRIM(b.nic))=?) OR (?<>'' AND UPPER(TRIM(b.elders_card_number))=?))
        ORDER BY ar.id DESC LIMIT 1 FOR UPDATE", [$nic,$nic,$elder,$elder])->fetch(PDO::FETCH_ASSOC);
    if ($activeAid) {
        throw new DomainException('This beneficiary has an active aid request for '.$activeAid['item_name'].' (AR-'.$activeAid['id'].'). Complete or reject it before camp registration.');
    }

    $relatedIssues = scQuery($db, "SELECT d.distributed_at,i.item_name,r.restriction_months
        FROM distributions d JOIN beneficiaries b ON b.id=d.beneficiary_id
        JOIN inventory_items i ON i.id=d.item_id
        JOIN disability_types dt ON LOWER(TRIM(dt.name)) COLLATE utf8mb4_unicode_ci=LOWER(TRIM(b.disability)) COLLATE utf8mb4_unicode_ci
        JOIN disability_aid_items r ON r.disability_type_id=dt.id AND r.item_id=d.item_id AND r.status='active'
        JOIN disability_item_prohibitions blocked ON blocked.disability_aid_item_id=r.id AND blocked.prohibited_item_id=?
        WHERE d.item_id<>? AND r.restriction_months>0
          AND ((?<>'' AND UPPER(TRIM(b.nic))=?) OR (?<>'' AND UPPER(TRIM(b.elders_card_number))=?))
          AND (SELECT COALESCE(SUM(ret.quantity),0) FROM item_returns ret
               WHERE ret.distribution_id=d.id AND ret.stock_review_status='accepted')<d.quantity
        ORDER BY d.distributed_at DESC,d.id DESC FOR UPDATE",
        [(int)$camp['item_id'],(int)$camp['item_id'],$nic,$nic,$elder,$elder])->fetchAll(PDO::FETCH_ASSOC);
    foreach ($relatedIssues as $issue) {
        $received = new DateTimeImmutable((string)$issue['distributed_at']);
        $eligibleOn = $received->modify('+'.(int)$issue['restriction_months'].' months');
        if ($eligibleOn->format('Y-m-d')>scToday()) {
            throw new DomainException('This beneficiary received '.$issue['item_name'].' on '.$received->format('Y-m-d').' and cannot be registered for spectacles until '.$eligibleOn->format('Y-m-d').'.');
        }
    }

    $existing = scQuery($db, "SELECT p.id,p.camp_id,p.status
        FROM spectacle_camp_participants p
        JOIN spectacle_camps c ON c.id=p.camp_id
        WHERE c.item_id=? AND p.id<>?
          AND ((?<>'' AND UPPER(TRIM(p.nic))=?) OR (?<>'' AND UPPER(TRIM(p.elder_card_number))=?))
          AND (p.camp_id=? OR (
              c.status IN ('approved','completed') AND p.status IN ('registered','approved')
              AND NOT EXISTS (SELECT 1 FROM spectacle_camp_distributions d WHERE d.participant_id=p.id)
          ))
        ORDER BY (p.camp_id=?) DESC,p.id DESC LIMIT 1 FOR UPDATE",
        [(int)$camp['item_id'],$currentParticipantId ?? 0,$nic,$nic,$elder,$elder,(int)$camp['id'],(int)$camp['id']]
    )->fetch(PDO::FETCH_ASSOC);
    if (!$existing) return;
    if ((int)$existing['camp_id']===(int)$camp['id']) {
        throw new DomainException('This NIC or Elder Card is already registered for this camp.');
    }
    throw new DomainException('This NIC or Elder Card is already registered or selected in Vision Camp VC-'.$existing['camp_id'].'. Complete that beneficiary\'s distribution before registering them in another camp.');
}

function scDate(array $input, string $key): string
{
    $value = scText($input, $key, 10, true);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) { throw new DomainException('Enter a valid date.'); }
    return $value;
}

function scToday(): string { return (new DateTimeImmutable('now', new DateTimeZone('Asia/Colombo')))->format('Y-m-d'); }
function scNowTime(): string { return (new DateTimeImmutable('now', new DateTimeZone('Asia/Colombo')))->format('H:i'); }
function scNowDateTime(): string { return (new DateTimeImmutable('now', new DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s'); }
function scClockTime(array $input, string $key): string
{
    $value=scText($input,$key,5,true);
    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$value)) throw new DomainException('Enter a valid time.');
    return $value;
}

function scCampScheduleTime(array $input): string
{
    return scSelectedClockTime($input,'camp','camp_time');
}

function scSelectedClockTime(array $input, string $prefix, string $legacyName): string
{
    if (!array_key_exists($prefix.'_hour',$input) && !array_key_exists($prefix.'_minute',$input) && !array_key_exists($prefix.'_period',$input)) {
        return scClockTime($input,$legacyName);
    }
    $hour=$input[$prefix.'_hour']??null;
    $minute=$input[$prefix.'_minute']??null;
    $period=$input[$prefix.'_period']??null;
    if (!is_string($hour) || !preg_match('/^(?:0[1-9]|1[0-2])$/D',$hour)
        || !is_string($minute) || !preg_match('/^[0-5]\d$/D',$minute)
        || !in_array($period,['AM','PM'],true)) {
        throw new DomainException('Select a valid time.');
    }
    $hour24=(int)$hour%12 + ($period==='PM'?12:0);
    return sprintf('%02d:%s',$hour24,$minute);
}

function scCategoryId(PDO $db, array $input, bool $allowInactive = false): int
{
    $categoryId = scPositive($input, 'spectacle_category_id');
    if (!scQuery($db, 'SELECT id FROM spectacle_categories WHERE id=?' . ($allowInactive ? '' : " AND status='active'"), [$categoryId])->fetchColumn()) {
        throw new DomainException($allowInactive ? 'Select a valid spectacle type.' : 'Select an active spectacle type.');
    }
    return $categoryId;
}

/** Camp balances stay separate for each spectacle type. */
function scBalances(PDO $db, int $campId, ?int $ssoId = null): array
{
    $balances = [];
    $add = static function (int $categoryId, string $key, int $quantity) use (&$balances): void {
        if ($categoryId < 1) return;
        $balances[$categoryId] ??= array_fill_keys(['received','pending','reserved','released','subject_distributed','sso_distributed','transferred','my_received','my_distributed'], 0);
        $balances[$categoryId][$key] += $quantity;
    };
    foreach (scQuery($db, 'SELECT COALESCE(line.spectacle_category_id, receipt.spectacle_category_id) category_id, COALESCE(line.quantity,receipt.quantity) quantity FROM spectacle_camp_receipts receipt LEFT JOIN stock_receipt_spectacle_lines line ON line.stock_receipt_id=receipt.stock_receipt_id WHERE receipt.camp_id=?', [$campId]) as $row) { $add((int)$row['category_id'],'received',(int)$row['quantity']); }
    foreach (scQuery($db, 'SELECT spectacle_category_id,quantity,approved_quantity,status FROM spectacle_camp_stock_requests WHERE camp_id=?', [$campId]) as $row) {
        if ($row['status']==='pending') $add((int)$row['spectacle_category_id'],'pending',(int)$row['quantity']);
        if ($row['status']==='approved') $add((int)$row['spectacle_category_id'],'reserved',(int)$row['approved_quantity']);
        if ($row['status']==='released') $add((int)$row['spectacle_category_id'],'released',(int)$row['approved_quantity']);
    }
    foreach (scQuery($db, 'SELECT spectacle_category_id,quantity,sso_id FROM spectacle_camp_transfers WHERE camp_id=?', [$campId]) as $row) {
        $add((int)$row['spectacle_category_id'],'transferred',(int)$row['quantity']);
        if ((int)$row['sso_id']===$ssoId) $add((int)$row['spectacle_category_id'],'my_received',(int)$row['quantity']);
    }
    foreach (scQuery($db, 'SELECT spectacle_category_id,quantity,source,distributed_by FROM spectacle_camp_distributions WHERE camp_id=?', [$campId]) as $row) {
        $add((int)$row['spectacle_category_id'],$row['source']==='subject-officer'?'subject_distributed':'sso_distributed',(int)$row['quantity']);
        if ($row['source']==='social-service-officer' && (int)$row['distributed_by']===$ssoId) $add((int)$row['spectacle_category_id'],'my_distributed',(int)$row['quantity']);
    }
    foreach ($balances as &$row) {
        $row['store'] = $row['received']-$row['released'];
        $row['available'] = $row['store']-$row['reserved']-$row['pending'];
        $row['subject'] = $row['released']-$row['subject_distributed']-$row['transferred'];
        $row['sso'] = $row['transferred']-$row['sso_distributed'];
        $row['mine'] = $row['my_received']-$row['my_distributed'];
    }
    unset($row);
    ksort($balances, SORT_NUMERIC);
    return $balances;
}

/** Released units whose planned date and time have arrived, less units already used or transferred. */
function scSubjectDistributableQuantity(PDO $db, int $campId, int $categoryId, string $date, ?string $time = null): int
{
    $time ??= $date===scToday() ? scNowTime() : '23:59';
    $ready=(int)scQuery($db,"SELECT COALESCE(SUM(approved_quantity),0) FROM spectacle_camp_stock_requests
        WHERE camp_id=? AND spectacle_category_id=? AND status='released'
          AND TIMESTAMP(planned_distribution_date,COALESCE(planned_distribution_time,'00:00:00'))<=?
          AND DATE(released_at)<=?",[$campId,$categoryId,$date.' '.$time.':00',$date])->fetchColumn();
    $used=(int)scQuery($db,"SELECT COALESCE(SUM(quantity),0) FROM spectacle_camp_distributions
        WHERE camp_id=? AND spectacle_category_id=? AND source='subject-officer'",[$campId,$categoryId])->fetchColumn();
    $transferred=(int)scQuery($db,"SELECT COALESCE(SUM(quantity),0) FROM spectacle_camp_transfers
        WHERE camp_id=? AND spectacle_category_id=?",[$campId,$categoryId])->fetchColumn();
    return max(0,$ready-$used-$transferred);
}

/** Selected beneficiaries set the maximum supplier receipt for each spectacle type. */
function scCampReceiptNeed(PDO $db, int $campId): array
{
    $balances=scBalances($db,$campId);
    $needed=[];
    foreach (scQuery($db,"SELECT spectacle_category_id,COUNT(*) quantity FROM spectacle_camp_participants WHERE camp_id=? AND status='approved' AND spectacle_category_id IS NOT NULL GROUP BY spectacle_category_id",[$campId]) as $row) {
        $categoryId=(int)$row['spectacle_category_id'];
        $remaining=(int)$row['quantity']-(int)($balances[$categoryId]['received']??0);
        if ($remaining>0) $needed[$categoryId]=$remaining;
    }
    ksort($needed,SORT_NUMERIC);
    return $needed;
}

/** Do not request stock already pending, reserved, or released for a beneficiary. */
function scCampRequestNeed(PDO $db, int $campId): array
{
    $balances=scBalances($db,$campId);
    $needed=[];
    foreach (scQuery($db,"SELECT spectacle_category_id,COUNT(*) quantity FROM spectacle_camp_participants WHERE camp_id=? AND status='approved' AND spectacle_category_id IS NOT NULL GROUP BY spectacle_category_id",[$campId]) as $row) {
        $categoryId=(int)$row['spectacle_category_id'];
        $balance=$balances[$categoryId]??[];
        $remaining=(int)$row['quantity']-(int)($balance['pending']??0)
            -(int)($balance['reserved']??0)-(int)($balance['released']??0);
        $available=(int)($balance['available']??0);
        if ($remaining>0 && $available>0) $needed[$categoryId]=min($remaining,$available);
    }
    ksort($needed,SORT_NUMERIC);
    return $needed;
}

/** One displayed request per camp batch; historical single-type requests stay separate. */
function scGroupStockRequests(array $rows): array
{
    $groups = [];
    foreach ($rows as $row) {
        $ref = (string) ($row['batch_ref'] ?? '');
        $key = $ref !== '' ? 'batch-' . $row['camp_id'] . '-' . $ref : 'line-' . $row['id'];
        if (!isset($groups[$key])) {
            $groups[$key] = $row;
            $groups[$key]['batch_lines'] = [];
            $groups[$key]['quantity'] = 0;
            $groups[$key]['approved_quantity'] = null;
        }
        $groups[$key]['batch_lines'][] = $row;
        $groups[$key]['quantity'] += (int) $row['quantity'];
        if ($row['approved_quantity'] !== null) {
            $groups[$key]['approved_quantity'] = (int) ($groups[$key]['approved_quantity'] ?? 0)
                + (int) $row['approved_quantity'];
        }
    }
    return array_values($groups);
}

function scNotify(PDO $db, array $camp, string $eventKey, string $message, ?string $role = null, ?int $recipient = null): void
{
    $ids = $role !== null
        ? scQuery($db, "SELECT id FROM users WHERE role=? AND status='active'", [$role])->fetchAll(PDO::FETCH_COLUMN)
        : [$recipient ?? (int)$camp['requested_by']];
    $division = scQuery($db, 'SELECT name FROM ds_divisions WHERE id=?', [$camp['ds_division_id']])->fetchColumn();
    $targetUrl = $role === 'admin' && $eventKey === 'requested'
        ? 'dashboard.php?page=pending-approvals&tab=vision-camps#vision-camp-request-' . $camp['id']
        : ($role === 'admin' && str_starts_with($eventKey, 'stock-request-')
            ? 'dashboard.php?page=spectacle-camp-stock-approvals'
        : ($role === 'store-keeper'
            ? ($eventKey === 'completed'
                ? 'dashboard.php?page=receive-items&camp_id=' . $camp['id']
                : 'dashboard.php?page=spectacle-camp-releases')
            : (str_starts_with($eventKey, 'received-')
                ? 'dashboard.php?page=vision-camp-stock-requests&camp_id=' . $camp['id']
                : 'dashboard.php?page=spectacle-camps&camp_id=' . $camp['id'])));
    foreach ($ids as $id) {
        $recipientTargetUrl=$targetUrl;
        if ($role===null) {
            $recipientRole=scQuery($db,'SELECT role FROM users WHERE id=?',[(int)$id])->fetchColumn();
            if ($recipientRole==='subject-officer' || $recipientRole==='social-service-officer') {
                $campPage=$recipientRole==='subject-officer' && (int)$id===(int)$camp['requested_by']
                    ? 'my-spectacle-camps'
                    : 'spectacle-camps';
                $recipientTargetUrl='dashboard.php?page='.$campPage.'#vision-camp-row-'.(int)$camp['id'];
            }
        }
        notifyUser($db, (int)$id, 'sc-' . $camp['id'] . '-' . $eventKey, 'Vision Camp',
            'Vision Camp - ' . $division, $message,
            $recipientTargetUrl);
        // Queue in this transaction. The normal worker sends only after commit.
        // Match the generic notification scanner's deduplication key.
        $notificationId=scQuery($db,'SELECT id FROM user_notifications WHERE user_id=? AND notification_key=?',[(int)$id,'sc-'.$camp['id'].'-'.$eventKey])->fetchColumn();
        $sms='SWPCS VC-'.$camp['id'].' - '.$division.'. '.$message;
        $sms=mb_strcut(mb_substr($sms,0,490),0,950,'UTF-8');
        scQuery($db,'INSERT INTO notification_sms_outbox (user_id,notification_key,message) VALUES (?,?,?) ON DUPLICATE KEY UPDATE id=id',[(int)$id,'user-notification-'.$notificationId,$sms]);
    }
}

/** All mutating actions serialize on the camp and commit with their audit/notifications. */
function scPerform(PDO $db, int $actorId, string $action, array $input): int
{
    if ($db->inTransaction()) { throw new LogicException('Camp actions require their own transaction.'); }
    $token = scText($input, 'request_token', 32, true);
    if (!preg_match('/^[a-f0-9]{32}$/D', $token)) { throw new DomainException('Invalid submission token. Refresh the page.'); }
    $db->beginTransaction();
    try {
        $actor = scActor($db, $actorId);
        $previous = scQuery($db, 'SELECT camp_id FROM spectacle_camp_events WHERE actor_id=? AND request_token=?', [$actorId,$token])->fetchColumn();
        if ($previous) { scCamp($db,(int)$previous,$actor); $db->commit(); return (int)$previous; }
        $details = '';
        if ($action === 'create-camp') {
            if ($actor['role'] !== 'subject-officer') { throw new DomainException('Only a Subject Officer can create a Vision Camp.'); }
            $district = scPositive($input,'district_id'); $division = scPositive($input,'ds_division_id');
            if (!scQuery($db,"SELECT ds.id FROM ds_divisions ds JOIN districts d ON d.id=ds.district_id WHERE ds.id=? AND ds.district_id=? AND ds.status='active' AND d.status='active' AND ds.division_type<>'service-centre'",[$division,$district])->fetchColumn()) {
                throw new DomainException('Select an active DS Division belonging to the district.');
            }
            $date = scDate($input,'camp_date'); $time = scCampScheduleTime($input);
            if ($date < scToday() || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$time)) { throw new DomainException('Select a current/future camp date and valid time.'); }
            $item = scQuery($db,"SELECT i.id FROM inventory_items i JOIN disability_aid_items r ON r.item_id=i.id WHERE i.item_name='Spectacles' AND r.is_system=1 AND r.status='active' ORDER BY i.id LIMIT 1")->fetchColumn();
            if (!$item) { throw new DomainException('Built-in Spectacles configuration is missing. Run database migrations.'); }
            scQuery($db,'INSERT INTO spectacle_camps (district_id,ds_division_id,item_id,requested_by,estimated_participants,camp_date,camp_time,remarks) VALUES (?,?,?,?,?,?,?,?)',[$district,$division,$item,$actorId,scPositive($input,'estimated_participants'),$date,$time,scText($input,'remarks',1000)]);
            $campId=(int)$db->lastInsertId(); $camp=scCamp($db,$campId,$actor,true);
            scNotify($db,$camp,'requested','A new Vision Camp scheduled for ' . $date . ' needs approval.','admin');
            $details='Vision Camp requested for ' . $date;
        } else {
            $campId=scPositive($input,'camp_id'); $camp=scCamp($db,$campId,$actor,true);
            // Check again after acquiring the lock: concurrent double submissions cannot repeat writes.
            if (scQuery($db,'SELECT id FROM spectacle_camp_events WHERE actor_id=? AND request_token=?',[$actorId,$token])->fetchColumn()) { $db->commit(); return $campId; }
            $isSubject=$actor['role']==='subject-officer';
            if ($action==='decide-camp') {
                if ($actor['role']!=='admin' || $camp['status']!=='pending') throw new DomainException('Only a pending camp can be reviewed by Admin.');
                $decision=scText($input,'decision',10,true);
                if (!in_array($decision,['approved','rejected'],true)) throw new DomainException('Select an approval decision.');
                $reason=scText($input,'reason',500,$decision==='rejected');
                scQuery($db,'UPDATE spectacle_camps SET status=?,reviewed_by=?,reviewed_at=NOW(),decision_reason=? WHERE id=?',[$decision,$actorId,$reason,$campId]);
                $details='Camp ' . $decision . ($reason!==''?': '.$reason:'');
                scNotify($db,$camp,'decision','Your camp scheduled for '.$camp['camp_date'].' was '.$decision.'. '.$reason);
            } elseif ($action==='conduct-camp') {
                if (!$isSubject || $camp['status']!=='approved' || $camp['conducted_at']!==null || $camp['camp_date']>scToday()) throw new DomainException('A Subject Officer can conduct an approved camp on or after its date.');
                scQuery($db,'UPDATE spectacle_camps SET conducted_at=NOW() WHERE id=?',[$campId]);
                $details='Camp conducted';
            } elseif ($action==='save-participant' || $action==='decide-participant') {
                if (!$isSubject || $camp['status']!=='approved' || !$camp['conducted_at']) throw new DomainException('Participants can be recorded only by a Subject Officer after the approved camp is conducted.');
                $conductorId=(int)scQuery($db,"SELECT actor_id FROM spectacle_camp_events WHERE camp_id=? AND action='conduct-camp' ORDER BY id LIMIT 1",[$campId])->fetchColumn();
                if ($conductorId!==$actorId) throw new DomainException('Only the Subject Officer who conducted this camp can register or review participants.');
                if ($action==='save-participant') {
                    $existingId=($input['participant_id']??'')!==''?scPositive($input,'participant_id'):null;
                    if ($existingId!==null) {
                        $existing=scQuery($db,'SELECT id,status FROM spectacle_camp_participants WHERE id=? AND camp_id=? FOR UPDATE',[$existingId,$campId])->fetch(PDO::FETCH_ASSOC);
                        if (!$existing || $existing['status']!=='registered') throw new DomainException('Only a registered, undecided participant can be reviewed.');
                    }
                    $nic=strtoupper(scText($input,'nic',20)); $elder=strtoupper(scText($input,'elder_card_number',50));
                    if ($nic==='' && $elder==='') throw new DomainException('NIC or Elder Card Number is required.');
                    if ($nic!=='' && !preg_match('/^(?:[0-9]{9}[VX]|[0-9]{12})$/D',$nic)) throw new DomainException('Enter a valid NIC number.');
                    $gn=scPositive($input,'gn_division_id');
                    if ((int)($input['district_id']??0)!==(int)$camp['district_id'] || (int)($input['ds_division_id']??0)!==(int)$camp['ds_division_id'] || !scQuery($db,"SELECT id FROM gn_divisions WHERE id=? AND ds_division_id=? AND status='active'",[$gn,$camp['ds_division_id']])->fetchColumn()) throw new DomainException('Participant geography must belong to this camp division.');
                    $phone=scText($input,'phone',25);
                    if ($phone!=='' && !preg_match('/^\+?[0-9 ()-]{7,25}$/D',$phone)) throw new DomainException('Enter a valid phone number or leave it empty.');
                    $decision=scText($input,'decision',10);
                    if ($decision!=='' && !in_array($decision,['approved','rejected'],true)) throw new DomainException('Select a participant decision.');
                    $gender=scText($input,'gender',10,$decision!=='');
                    if ($gender!=='' && !in_array($gender,['male','female','other'],true)) throw new DomainException('Select a valid gender.');
                    $reason=$decision==='rejected'?scText($input,'reason',500,true):null;
                    $power=null; // Vision Camps do not collect prescription power.
                    $categoryId=$decision==='approved'?scCategoryId($db,$input):null;
                    scAssertParticipantRegistrationEligibility($db,$camp,['nic'=>$nic,'elder_card_number'=>$elder],$existingId);
                    $prescription=$decision==='approved'?scText($input,'prescription_details',1000):'';
                    $values=[scText($input,'full_name',150,true),$gender?:null,$nic?:null,$elder?:null,scText($input,'address',255,true),$phone?:null,$camp['district_id'],$camp['ds_division_id'],$gn,$decision?:'registered',$power,$categoryId,$prescription,$reason,$decision!==''?$actorId:null,$decision!==''?$actorId:null];
                    if ($existingId!==null) {
                        scQuery($db,'UPDATE spectacle_camp_participants SET full_name=?,gender=?,nic=?,elder_card_number=?,address=?,phone=?,district_id=?,ds_division_id=?,gn_division_id=?,status=?,power=?,spectacle_category_id=?,prescription_details=?,rejection_reason=?,decided_by=?,decided_at=IF(? IS NULL,NULL,NOW()) WHERE id=? AND camp_id=?',array_merge($values,[$existingId,$campId]));
                    } else {
                        scQuery($db,'INSERT INTO spectacle_camp_participants (full_name,gender,nic,elder_card_number,address,phone,district_id,ds_division_id,gn_division_id,status,power,spectacle_category_id,prescription_details,rejection_reason,decided_by,decided_at,camp_id,recorded_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,IF(? IS NULL,NULL,NOW()),?,?)',array_merge($values,[$campId,$actorId]));
                        $existingId=(int)$db->lastInsertId();
                    }
                    $details='Participant saved: SCP-' . $existingId . ($decision!==''?'; '.$decision:'') . ($reason!==null?': '.$reason:'');
                } else {
                    $participantId=scPositive($input,'participant_id');
                    $participant=scQuery($db,'SELECT * FROM spectacle_camp_participants WHERE id=? AND camp_id=? FOR UPDATE',[$participantId,$campId])->fetch(PDO::FETCH_ASSOC);
                    if (!$participant || $participant['status']!=='registered') throw new DomainException('Only a registered, undecided participant can be reviewed.');
                    $decision=scText($input,'decision',10,true);
                    if (!in_array($decision,['approved','rejected'],true)) throw new DomainException('Select a participant decision.');
                    $reason=scText($input,'reason',500,$decision==='rejected');
                    $power=null; // Vision Camps do not collect prescription power.
                    if ($decision==='approved') scAssertParticipantRegistrationEligibility($db,$camp,$participant,$participantId);
                    $categoryId=$decision==='approved'?scCategoryId($db,$input):null;
                    scQuery($db,'UPDATE spectacle_camp_participants SET status=?,power=?,spectacle_category_id=?,prescription_details=?,rejection_reason=?,decided_by=?,decided_at=NOW() WHERE id=?',[$decision,$power,$categoryId,scText($input,'prescription_details',1000),$decision==='rejected'?$reason:null,$actorId,$participantId]);
                    $details='Participant SCP-'.$participantId.' '.$decision.($reason!==''?': '.$reason:'');
                }
            } elseif ($action==='receive-stock') {
                if ($actor['role']!=='store-keeper' || $camp['status']!=='completed') throw new DomainException('Store Keeper can receive stock only after the camp is completed.');
                $power=null; $categoryId=scCategoryId($db,$input,true);
                $quantity=scPositive($input,'quantity'); $date=scDate($input,'received_date');
                if ($quantity!==(int)(scCampReceiptNeed($db,$campId)[$categoryId]??0)) throw new DomainException('Receipt quantity must match the remaining selected participants for this spectacle type.');
                if ($date>scToday() || $date<substr((string)$camp['completed_at'],0,10)) throw new DomainException('Receipt date must be between camp completion and today.');
                scQuery($db,'INSERT INTO spectacle_camp_receipts (camp_id,power,spectacle_category_id,quantity,reference_code,received_date,received_by,remarks) VALUES (?,?,?,?,?,?,?,?)',[$campId,$power,$categoryId,$quantity,scText($input,'reference_code',100,true),$date,$actorId,scText($input,'remarks',500)]);
                $receiptId=$db->lastInsertId(); $details='Received '.$quantity.' spectacles'.' (SCR-'.$receiptId.')';
                $conductorId=(int)scQuery($db,"SELECT actor_id FROM spectacle_camp_events WHERE camp_id=? AND action='conduct-camp' ORDER BY id LIMIT 1",[$campId])->fetchColumn();
                if ($conductorId<1) throw new DomainException('The conducting Subject Officer could not be identified.');
                scNotify($db,$camp,'received-'.$receiptId,'Spectacles for your camp are now available in the store. Submit a stock request with a distribution date.',null,$conductorId);
            } elseif ($action==='request-stock') {
                throw new DomainException('Submit one camp stock batch from the Request Camp Stock page.');
            } elseif ($action==='request-stock-batch') {
                $conductorId=(int)scQuery($db,"SELECT actor_id FROM spectacle_camp_events WHERE camp_id=? AND action='conduct-camp' ORDER BY id LIMIT 1",[$campId])->fetchColumn();
                if (!$isSubject || $camp['status']!=='completed' || $conductorId!==$actorId) throw new DomainException('Only the conducting Subject Officer can request stock after this camp is completed.');
                $date=scDate($input,'planned_distribution_date');
                $time=scSelectedClockTime($input,'planned_distribution','planned_distribution_time');
                $place=scText($input,'planned_distribution_place',255,true);
                if ($date<scToday() || $date<$camp['camp_date']) throw new DomainException('Select a current/future distribution date after the camp.');
                $needs=scCampRequestNeed($db,$campId);
                if ($needs===[]) throw new DomainException('No received camp stock is currently available for a new request.');
                $remarks=scText($input,'remarks',500);
                $batchRef=bin2hex(random_bytes(16));
                $total=0;
                foreach ($needs as $categoryId=>$quantity) {
                    scQuery($db,'INSERT INTO spectacle_camp_stock_requests (camp_id,batch_ref,requested_by,power,spectacle_category_id,quantity,planned_distribution_date,planned_distribution_time,planned_distribution_place,remarks) VALUES (?,?,?,NULL,?,?,?,?,?,?)',[$campId,$batchRef,$actorId,$categoryId,$quantity,$date,$time,$place,$remarks]);
                    $total += $quantity;
                }
                $details='Camp stock batch '.$batchRef.': '.$total.' spectacles, planned '.$date.' '.$time.' at '.$place;
                scNotify($db,$camp,'stock-request-batch-'.$batchRef,'A Vision Camp stock batch needs Admin approval. Planned distribution: '.$date.' '.$time.' at '.$place.'.','admin');
            } elseif ($action==='decide-stock-batch' || $action==='release-stock-batch') {
                if ($camp['status']!=='completed') throw new DomainException('Camp must be completed before stock can be approved or released.');
                $batchRef=scText($input,'batch_ref',32,true);
                if (!preg_match('/^[a-f0-9]{32}$/D',$batchRef)) throw new DomainException('Invalid camp stock batch.');
                $lines=scQuery($db,'SELECT * FROM spectacle_camp_stock_requests WHERE camp_id=? AND batch_ref=? ORDER BY id FOR UPDATE',[$campId,$batchRef])->fetchAll(PDO::FETCH_ASSOC);
                if ($lines===[]) throw new DomainException('Camp stock batch not found.');
                $balance=scBalances($db,$campId);
                if ($action==='decide-stock-batch') {
                    if ($actor['role']!=='admin') throw new DomainException('Only Admin can review a pending stock batch.');
                    $decision=scText($input,'decision',10,true);
                    if (!in_array($decision,['approved','rejected'],true)) throw new DomainException('Select an approval decision.');
                    $reason=scText($input,'reason',500,$decision==='rejected');
                    foreach ($lines as $line) {
                        if ($line['status']!=='pending') throw new DomainException('This camp stock batch has already been reviewed.');
                        $category=(int)$line['spectacle_category_id'];
                        $available=(int)($balance[$category]['store']??0)-(int)($balance[$category]['reserved']??0);
                        if ($decision==='approved' && (int)$line['quantity']>$available) throw new DomainException('Camp stock is no longer sufficient for every type in this batch.');
                    }
                    scQuery($db,"UPDATE spectacle_camp_stock_requests SET status=?,approved_quantity=CASE WHEN ?='approved' THEN quantity ELSE NULL END,reviewed_by=?,reviewed_at=NOW(),decision_reason=? WHERE camp_id=? AND batch_ref=?",[$decision,$decision,$actorId,$reason,$campId,$batchRef]);
                    $details='Camp stock batch '.$batchRef.' '.$decision;
                    scNotify($db,$camp,'stock-decision-batch-'.$batchRef,$details,null,(int)$lines[0]['requested_by']);
                    if ($decision==='approved') scNotify($db,$camp,'release-needed-batch-'.$batchRef,'Approved Vision Camp stock batch is ready for release to the requesting Subject Officer.','store-keeper');
                } else {
                    if ($actor['role']!=='store-keeper') throw new DomainException('Only Store Keeper can release approved camp stock.');
                    foreach ($lines as $line) {
                        if ($line['status']!=='approved') throw new DomainException('Admin must approve the entire camp stock batch before release.');
                        if ((int)$line['approved_quantity']>(int)($balance[(int)$line['spectacle_category_id']]['store']??0)) throw new DomainException('Insufficient camp stock for this batch.');
                    }
                    scQuery($db,"UPDATE spectacle_camp_stock_requests SET status='released',released_by=?,released_at=NOW() WHERE camp_id=? AND batch_ref=?",[$actorId,$campId,$batchRef]);
                    $details='Released camp stock batch '.$batchRef.' to Subject Officer';
                    scNotify($db,$camp,'released-batch-'.$batchRef,'Vision Camp stock batch has been released. Record each beneficiary distribution.',null,(int)$lines[0]['requested_by']);
                }
            } elseif ($action==='decide-stock' || $action==='release-stock') {
                if ($camp['status']!=='completed') throw new DomainException('Camp must be completed before stock can be approved or released.');
                $requestId=scPositive($input,'stock_request_id');
                $request=scQuery($db,'SELECT * FROM spectacle_camp_stock_requests WHERE id=? AND camp_id=? FOR UPDATE',[$requestId,$campId])->fetch(PDO::FETCH_ASSOC);
                if (!$request) throw new DomainException('Stock request does not belong to this camp.');
                if (!empty($request['batch_ref'])) throw new DomainException('Use the camp batch action for this request.');
                $categoryId=(int)$request['spectacle_category_id'];
                $balance=scBalances($db,$campId)[$categoryId]??[];
                if ($action==='decide-stock') {
                    if ($actor['role']!=='admin' || $request['status']!=='pending') throw new DomainException('Only Admin can review a pending stock request.');
                    $decision=scText($input,'decision',10,true);
                    if (!in_array($decision,['approved','rejected'],true)) throw new DomainException('Select an approval decision.');
                    $reason=scText($input,'reason',500,$decision==='rejected');
                    $quantity=$decision==='approved'?scPositive($input,'approved_quantity'):null;
                    if ($quantity!==null && ($quantity>(int)$request['quantity'] || $quantity>($balance['store']-$balance['reserved']))) throw new DomainException('Approved quantity cannot exceed the request or available camp stock.');
                    scQuery($db,'UPDATE spectacle_camp_stock_requests SET status=?,approved_quantity=?,reviewed_by=?,reviewed_at=NOW(),decision_reason=? WHERE id=?',[$decision,$quantity,$actorId,$reason,$requestId]);
                    $details='Stock request SCS-'.$requestId.' '.$decision.($reason!==''?': '.$reason:'');
                    scNotify($db,$camp,'stock-decision-'.$requestId,$details,null,(int)$request['requested_by']);
                    if ($decision==='approved') scNotify($db,$camp,'release-needed-'.$requestId,'Approved Vision Camp stock request SCS-'.$requestId.' is ready for release to the requesting Subject Officer.','store-keeper');
                } else {
                    if ($actor['role']!=='store-keeper' || $request['status']!=='approved') throw new DomainException('Store Keeper may release only Admin-approved requests.');
                    if ((int)$request['approved_quantity']>($balance['store']??0)) throw new DomainException('Insufficient stock for this camp.');
                    scQuery($db,"UPDATE spectacle_camp_stock_requests SET status='released',released_by=?,released_at=NOW() WHERE id=?",[$actorId,$requestId]);
                    $details='Released SCS-'.$requestId.' to Subject Officer: '.$request['approved_quantity'].' spectacles';
                    scNotify($db,$camp,'released-'.$requestId,'Vision Camp spectacles have been released. Record each beneficiary distribution.',null,(int)$request['requested_by']);
                }
            } elseif ($action==='distribute') {
                if ((!$isSubject && $actor['role']!=='social-service-officer') || !in_array($camp['status'],['approved','completed'],true)) throw new DomainException('Only a Subject Officer or assigned division SSO may distribute.');
                if ($isSubject && scQuery($db,'SELECT id FROM spectacle_camp_transfers WHERE camp_id=? LIMIT 1',[$campId])->fetchColumn()) {
                    throw new DomainException('This camp stock has been handed over to the Division SSO. Only that SSO can distribute the remaining spectacles.');
                }
                if (!$isSubject && !scQuery($db,'SELECT id FROM spectacle_camp_transfers WHERE camp_id=? AND sso_id=? LIMIT 1',[$campId,$actorId])->fetchColumn()) {
                    throw new DomainException('This camp has not been handed over to you for distribution.');
                }
                $participantId=scPositive($input,'participant_id');
                $participant=scQuery($db,'SELECT * FROM spectacle_camp_participants WHERE id=? AND camp_id=? FOR UPDATE',[$participantId,$campId])->fetch(PDO::FETCH_ASSOC);
                if (!$participant || $participant['status']!=='approved' || scQuery($db,'SELECT id FROM spectacle_camp_distributions WHERE participant_id=?',[$participantId])->fetchColumn()) throw new DomainException('Select an approved participant who has not already received spectacles.');
                $categoryId=(int)$participant['spectacle_category_id'];
                if($categoryId<1)throw new DomainException('The participant has no spectacle type.');
                $balance=scBalances($db,$campId,$actorId)[$categoryId]??[];
                if (($balance[$isSubject?'subject':'mine']??0)<1) throw new DomainException('No released/transferred camp stock is available for this role.');
                $date=scDate($input,'distribution_date');
                $time=scClockTime($input+['distribution_time'=>scNowTime()],'distribution_time');
                scAssertSpectacleWaitingPeriod($db,$camp,$participant,$date);
                $latest=$isSubject?null:scQuery($db,'SELECT MAX(transfer_date) FROM spectacle_camp_transfers WHERE camp_id=? AND sso_id=?',[$campId,$actorId])->fetchColumn();
                if ($date>scToday() || $date<$camp['camp_date'] || ($latest && $date<$latest) || ($date===scToday() && $time>scNowTime())) throw new DomainException('Distribution date and time cannot precede receipt of stock or be in the future.');
                if ($isSubject && scSubjectDistributableQuantity($db,$campId,$categoryId,$date,$time)<1) throw new DomainException('Distribution is available only on or after the planned date and time for released camp stock.');
                scQuery($db,'INSERT INTO spectacle_camp_distributions (camp_id,participant_id,power,spectacle_category_id,source,distributed_by,distribution_date,distribution_time,remarks) VALUES (?,?,NULL,?,?,?,?,?,?)',[$campId,$participantId,$categoryId,$actor['role'],$actorId,$date,$time,scText($input,'remarks',500)]);
                $details='Spectacles distributed to SCP-'.$participantId.' by '.$actor['role'];
                if ($actor['role']==='social-service-officer') scNotify($db,$camp,'distributed-'.$participantId,'The Divisional SSO recorded a spectacle distribution from your camp stock.');
            } elseif ($action==='handover-to-sso') {
                if (!$isSubject || $camp['status']!=='completed') throw new DomainException('Only a Subject Officer can hand over remaining camp stock after camp completion.');
                $ssos=scQuery($db,"SELECT id,full_name FROM users WHERE role='social-service-officer' AND status='active' AND ds_division_id=? FOR UPDATE",[$camp['ds_division_id']])->fetchAll(PDO::FETCH_ASSOC);
                if (count($ssos)!==1) throw new DomainException('This camp division must have one active SSO before remaining spectacles can be handed over.');
                $ssoId=(int)$ssos[0]['id'];
                $balances=scBalances($db,$campId);
                $handoverLines=[];
                foreach ($balances as $categoryId=>$balance) {
                    $quantity=(int)($balance['subject']??0);
                    if ($quantity<1) continue;
                    if ($quantity>scSubjectDistributableQuantity($db,$campId,(int)$categoryId,scToday(),scNowTime())) {
                        throw new DomainException('Remaining camp stock can be handed over only on or after its planned distribution date and time.');
                    }
                    $handoverLines[(int)$categoryId]=$quantity;
                }
                if ($handoverLines===[]) throw new DomainException('There is no remaining released camp stock to hand over.');
                foreach ($handoverLines as $categoryId=>$quantity) {
                    scQuery($db,'INSERT INTO spectacle_camp_transfers (camp_id,power,spectacle_category_id,quantity,sso_id,transferred_by,transfer_date,remarks) VALUES (?,NULL,?,?,?,?,?,?)',[$campId,$categoryId,$quantity,$ssoId,$actorId,scToday(),'Full remaining camp stock handover.']);
                }
                $details='Handed over all remaining camp spectacles to Division SSO USR-'.$ssoId;
                scNotify($db,$camp,'sso-handover','Remaining Vision Camp spectacles were handed over to you. Record each pending participant distribution.',null,$ssoId);
            } elseif ($action==='transfer-stock') {
                if (!$isSubject || $camp['status']!=='completed') throw new DomainException('Only a Subject Officer can transfer remaining stock after camp completion.');
                $categoryId=scCategoryId($db,$input,true);
                $quantity=scPositive($input,'quantity'); $ssoId=scPositive($input,'sso_id'); $date=scDate($input,'transfer_date');
                $sso=scQuery($db,"SELECT id FROM users WHERE id=? AND role='social-service-officer' AND status='active' AND ds_division_id=? FOR UPDATE",[$ssoId,$camp['ds_division_id']])->fetchColumn();
                $balance=scBalances($db,$campId)[$categoryId]??[];
                $released=scQuery($db,"SELECT MAX(DATE(released_at)) FROM spectacle_camp_stock_requests WHERE camp_id=? AND status='released'",[$campId])->fetchColumn();
                if (!$sso || $quantity>($balance['subject']??0)) throw new DomainException('Select the active SSO for this camp division and a quantity within your remaining stock.');
                if ($date>scToday() || $date<$camp['camp_date'] || ($released && $date<$released)) throw new DomainException('Transfer date cannot precede stock release or be in the future.');
                if ($quantity>scSubjectDistributableQuantity($db,$campId,$categoryId,$date)) throw new DomainException('Transfer is available only on or after the planned distribution date for released camp stock.');
                scQuery($db,'INSERT INTO spectacle_camp_transfers (camp_id,power,spectacle_category_id,quantity,sso_id,transferred_by,transfer_date,remarks) VALUES (?,NULL,?,?,?,?,?,?)',[$campId,$categoryId,$quantity,$ssoId,$actorId,$date,scText($input,'remarks',500)]);
                $transferId=$db->lastInsertId(); $details='Transferred '.$quantity.' spectacles'.' to SSO USR-'.$ssoId;
                scNotify($db,$camp,'transfer-'.$transferId,'Remaining spectacles from this Vision Camp were transferred to you for follow-up distribution.',null,$ssoId);
            } elseif ($action==='complete-camp') {
                if (!$isSubject || $camp['status']!=='approved' || !$camp['conducted_at']) throw new DomainException('Only a Subject Officer can complete a conducted camp.');
                $conductorId=(int)scQuery($db,"SELECT actor_id FROM spectacle_camp_events WHERE camp_id=? AND action='conduct-camp' ORDER BY id LIMIT 1",[$campId])->fetchColumn();
                if ($conductorId!==$actorId) throw new DomainException('Only the conducting Subject Officer can complete this camp.');
                if (scQuery($db,"SELECT id FROM spectacle_camp_participants WHERE camp_id=? AND status='registered' LIMIT 1",[$campId])->fetchColumn()) throw new DomainException('Record decisions for all registered participants first.');
                if (scQuery($db,"SELECT id FROM spectacle_camp_participants WHERE camp_id=? AND status='approved' AND spectacle_category_id IS NULL LIMIT 1",[$campId])->fetchColumn()) throw new DomainException('Select a spectacle type for every approved participant first.');
                scQuery($db,"UPDATE spectacle_camps SET status='completed',completed_at=NOW() WHERE id=?",[$campId]);
                $details='Participant registration completed; supplier stock may now be received';
                if (scCampReceiptNeed($db,$campId)!==[]) scNotify($db,$camp,'completed','Participant registration is complete for this Vision Camp. Receive the selected spectacle types into camp stock.','store-keeper');
            } else { throw new DomainException('Invalid Vision Camp action.'); }
        }
        scQuery($db,'INSERT INTO spectacle_camp_events (camp_id,actor_id,action,details,request_token) VALUES (?,?,?,?,?)',[$campId,$actorId,$action,$details,$token]);
        scQuery($db,'INSERT INTO activity_logs (user_id,role,module,action,record_reference,status) VALUES (?,?,?,?,?,?)',[$actorId,$actor['role'],'Vision Camps',mb_substr($details,0,500),'VC-'.$campId,'done']);
        $db->commit();
        return $campId;
    } catch (Throwable $exception) {
        if ($db->inTransaction()) $db->rollBack();
        throw $exception;
    }
}
