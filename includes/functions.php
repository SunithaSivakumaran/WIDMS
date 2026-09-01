<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

function sendRegistrationDecisionEmail(string $email,string $name,string $decision):bool
{
    $autoload=__DIR__.'/../vendor/autoload.php';
    if(!is_file($autoload)){error_log('WIDMS email: Composer dependencies are missing. Run composer install.');return false;}
    require_once $autoload;
    $smtp=require __DIR__.'/../config/smtp.php';
    if($smtp['username']===''||$smtp['password']===''||$smtp['from_email']===''){error_log('WIDMS email: SMTP credentials are not configured in config/smtp.local.php.');return false;}
    $approved=$decision==='approved';
    $subject='WIDMS registration request '.($approved?'approved':'rejected');
    $statusText=$approved?'Your WIDMS registration request has been approved. You can now sign in using your email address and the password you selected.':'Your WIDMS registration request has been rejected. Please contact the system administrator if you need more information.';
    $safeName=htmlspecialchars($name,ENT_QUOTES,'UTF-8');$safeText=htmlspecialchars($statusText,ENT_QUOTES,'UTF-8');
    try{
        $mail=new PHPMailer(true);$mail->isSMTP();$mail->Host=$smtp['host'];$mail->Port=$smtp['port'];$mail->SMTPAuth=true;$mail->Username=$smtp['username'];$mail->Password=$smtp['password'];$mail->CharSet='UTF-8';$mail->Timeout=20;
        $mail->SMTPSecure=$smtp['encryption']==='ssl'?PHPMailer::ENCRYPTION_SMTPS:PHPMailer::ENCRYPTION_STARTTLS;
        $mail->setFrom($smtp['from_email'],$smtp['from_name']);$mail->addAddress($email,$name);$mail->isHTML(true);$mail->Subject=$subject;
        $mail->Body="<div style=\"font-family:Arial,sans-serif;max-width:600px;margin:auto\"><h2 style=\"color:#1768bd\">WIDMS</h2><p>Hello {$safeName},</p><p>{$safeText}</p><p>Regards,<br>WIDMS Administration</p></div>";
        $mail->AltBody="Hello {$name},\n\n{$statusText}\n\nWIDMS Administration";$mail->send();return true;
    }catch(MailException $e){error_log('WIDMS SMTP delivery failed: '.$e->getMessage());return false;}
}
