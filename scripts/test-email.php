<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit("CLI only.\n");}
if(!filter_var($argv[1]??'',FILTER_VALIDATE_EMAIL)){fwrite(STDERR,"Usage: php scripts/test-email.php recipient@example.com\n");
exit(1);}
require_once __DIR__.'/../includes/functions.php';$sent=sendRegistrationDecisionEmail($argv[1],'WIDMS Test User','approved');echo $sent?"SMTP test email sent successfully.\n":"SMTP test failed. Check config/smtp.local.php and the PHP error log.\n";exit($sent?0:1);
