<?php
declare(strict_types=1);

require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/spectacle-camps.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $db=database();
    $actor=scActor($db,(int)($_SESSION['user_id']??0));
    if ($actor['role']!=='subject-officer') throw new DomainException('Access denied.');
    $campId=filter_var($_GET['camp_id']??null,FILTER_VALIDATE_INT)?:0;
    $camp=scCamp($db,$campId,$actor);
    if ($camp['status']!=='approved' || !$camp['conducted_at']) throw new DomainException('Participant registration is not available for this camp.');
    $nic=strtoupper(trim((string)($_GET['nic']??'')));
    $elder=strtoupper(trim((string)($_GET['elder_card_number']??'')));
    $participantId=filter_var($_GET['participant_id']??null,FILTER_VALIDATE_INT)?:null;
    if ($nic!=='' || $elder!=='') {
        scAssertParticipantRegistrationEligibility($db,$camp,['nic'=>$nic,'elder_card_number'=>$elder],$participantId);
    }
    echo json_encode(['valid'=>true],JSON_THROW_ON_ERROR);
} catch (BeneficiaryDivisionException $e) {
    http_response_code(422);
    echo json_encode(['valid'=>false,'message'=>$e->getMessage()],JSON_THROW_ON_ERROR);
} catch (DomainException $e) {
    http_response_code(422);
    echo json_encode(['valid'=>false,'message'=>$e->getMessage()],JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['valid'=>false,'message'=>'Unable to validate the beneficiary division.'],JSON_THROW_ON_ERROR);
}
