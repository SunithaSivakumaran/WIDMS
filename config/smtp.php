<?php
declare(strict_types=1);
$settings=['host'=>getenv('WIDMS_SMTP_HOST')?:'smtp.gmail.com','port'=>(int)(getenv('WIDMS_SMTP_PORT')?:587),'encryption'=>getenv('WIDMS_SMTP_ENCRYPTION')?:'tls','username'=>getenv('WIDMS_SMTP_USERNAME')?:'','password'=>getenv('WIDMS_SMTP_PASSWORD')?:'','from_email'=>getenv('WIDMS_SMTP_FROM_EMAIL')?:'','from_name'=>getenv('WIDMS_SMTP_FROM_NAME')?:'WIDMS Administration'];
$localFile=__DIR__.'/smtp.local.php';if(is_file($localFile)){$local=require $localFile;if(is_array($local))$settings=array_merge($settings,$local);}return $settings;
