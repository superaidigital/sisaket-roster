<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Core/bootstrap.php';
// Static source checks; live login throttling must be tested during XAMPP UAT.
$source=file_get_contents(dirname(__DIR__).'/app/Services/LoginThrottle.php');
$auth=file_get_contents(dirname(__DIR__).'/app/Controllers/AuthController.php');
$csv=file_get_contents(dirname(__DIR__).'/app/Services/ExcelExporter.php');
$fail=[];
foreach([
    'shared server-side lock'=>str_contains($source,'flock($fp,LOCK_EX)'),
    'keyed identity hash'=>str_contains($source,"hash_hmac('sha256'"),
    'account + IP buckets'=>str_contains($source,"self::digest('account'")&&str_contains($source,"self::digest('remote-ip'"),
    'auth checks throttle'=>str_contains($auth,'LoginThrottle::retryAfter($email)'),
    'auth records failed attempt'=>str_contains($auth,'LoginThrottle::recordFailure($email)'),
    'auth clears account on success'=>str_contains($auth,'LoginThrottle::clearAccount($email)'),
    'CSV formula guard'=>str_contains($csv,'preg_match(')&&str_contains($csv,'fputcsv($out,$safe)'),
    'Apache root guard'=>is_file(dirname(__DIR__).'/.htaccess'),
    'Apache public guard'=>is_file(dirname(__DIR__).'/public/.htaccess'),
    'app directory denied'=>is_file(dirname(__DIR__).'/app/.htaccess'),
] as $name=>$ok){if(!$ok)$fail[]=$name;}
if($fail){fwrite(STDERR,"FAIL: ".implode(', ',$fail)."\n");exit(1);}
echo "PASS: static hardening guards (10 checks)\n";
