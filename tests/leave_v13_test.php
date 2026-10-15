<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Services/LeaveReasonCipher.php';
require dirname(__DIR__).'/app/Services/LeaveRequestService.php';
use App\Services\LeaveReasonCipher as C;
use App\Services\LeaveRequestService as L;
$count=0;
function ok(bool $cond,string $what):void{global $count;$count++;if(!$cond){fwrite(STDERR,"FAIL: ".$what."\n");exit(1);}}
$key=random_bytes(32);
$text="ลาป่วย (ข้อมูลส่วนบุคคล) sensitive reason";
$a=C::encryptWithKey($text,$key);
$b=C::encryptWithKey($text,$key);
ok($a!==$b,'unique nonce');
ok(!str_contains($a,$text),'reason not plaintext');
ok(C::decryptWithKey($a,$key)===$text,'encrypted roundtrip');
ok(strlen($a)===strlen($text)+31,'cipher layout');
try{C::decryptWithKey($a,random_bytes(32));ok(false,'wrong key rejected');}catch(InvalidArgumentException $e){ok(true,'wrong key rejected');}
$tampered=$a;$tampered[strlen($tampered)-1]=chr(ord(substr($tampered,-1))^1);
try{C::decryptWithKey($tampered,$key);ok(false,'tag tamper rejected');}catch(InvalidArgumentException $e){ok(true,'tag tamper rejected');}
try{C::encryptWithKey('abc','short');ok(false,'short key rejected');}catch(InvalidArgumentException $e){ok(true,'short key rejected');}
ok(L::validDate('2026-10-15 08:30:00')==='2026-10-15 08:30:00','datetime valid');
foreach(['2026-02-30 08:30:00','2026-10-15 25:00:00','2026-10-15T08:30','2026-10-15 08:30:40'] as $d){
 try{L::validDate($d);ok(false,'invalid date '.$d);}catch(InvalidArgumentException $e){ok(true,'invalid date rejected');}
}
$router=file_get_contents(dirname(__DIR__).'/public/index.php');
ok(str_contains($router,"'leave'=>['index','data','detail','submit','decide']"),'strict leave route');
$service=file_get_contents(dirname(__DIR__).'/app/Services/LeaveRequestService.php');
ok(str_contains($service,"'LEAVE_APPROVAL'"),'separate mandate scope');
ok(str_contains($service,"'PUBLISHED','LOCKED'"),'V11 and Duty overlap');
echo "PASS {$count} leave cipher, dates and policy checks; no DB accessed.\n";
