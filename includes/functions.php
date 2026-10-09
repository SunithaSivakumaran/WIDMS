<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

function sendRegistrationDecisionEmail(string $email,string $name,string $decision,string $reason='',?string $username=null):bool
{
    $autoload=__DIR__.'/../vendor/autoload.php';
    if(!is_file($autoload)){error_log('SWPCS email: Composer dependencies are missing. Run composer install.');return false;}
    require_once $autoload;
    $smtp=require __DIR__.'/../config/smtp.php';
    if($smtp['username']===''||$smtp['password']===''||$smtp['from_email']===''){error_log('SWPCS email: SMTP credentials are not configured in config/smtp.local.php.');return false;}
    $approved=$decision==='approved';
    $subject='SWPCS registration request '.($approved?'approved':'rejected');
    // Include the recorded reason so rejected applicants understand the decision.
    $statusText=$approved?'Your SWPCS account has been successfully created. Your username is '.($username ?? $email).'. Sign in using the password you entered during registration.':'Your SWPCS registration request has been rejected.'.($reason!==''?' Reason: '.$reason:'');
    $safeName=htmlspecialchars($name,ENT_QUOTES,'UTF-8');$safeText=htmlspecialchars($statusText,ENT_QUOTES,'UTF-8');
    try{
        $mail=new PHPMailer(true);$mail->isSMTP();$mail->Host=$smtp['host'];$mail->Port=$smtp['port'];$mail->SMTPAuth=true;$mail->Username=$smtp['username'];$mail->Password=$smtp['password'];$mail->CharSet='UTF-8';$mail->Timeout=20;
        $mail->SMTPSecure=$smtp['encryption']==='ssl'?PHPMailer::ENCRYPTION_SMTPS:PHPMailer::ENCRYPTION_STARTTLS;
        $mail->setFrom($smtp['from_email'],$smtp['from_name']);$mail->addAddress($email,$name);$mail->isHTML(true);$mail->Subject=$subject;
        $mail->Body="<div style=\"font-family:Arial,sans-serif;max-width:600px;margin:auto\"><h2 style=\"color:#1768bd\">SWPCS</h2><p>Hello {$safeName},</p><p>{$safeText}</p><p>Regards,<br>SWPCS Administration</p></div>";
        $mail->AltBody="Hello {$name},\n\n{$statusText}\n\nSWPCS Administration";$mail->send();return true;
    }catch(MailException $e){error_log('SWPCS SMTP delivery failed: '.$e->getMessage());return false;}
}
