<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Services/DutyReviewPolicy.php';
use App\Services\DutyReviewPolicy as P;
$n=0;
function check(bool $c,string $s):void{global $n;$n++;if(!$c){fwrite(STDERR,"FAIL: ".$s."\n");exit(1);}}
check(P::allowed('DIRECTOR','SUBMITTED','approve',true),'director with valid delegation approves');
check(!P::allowed('DIRECTOR','SUBMITTED','approve',false),'director no delegation');
check(!P::allowed('STAFF','SUBMITTED','approve',true),'staff cannot approve');
check(!P::allowed('SCHEDULER','SUBMITTED','approve',true),'scheduler cannot approve');
check(!P::allowed('DIRECTOR','DRAFT','approve',true),'draft cannot approve');
check(P::allowed('SUPERADMIN','SUBMITTED','reject',true),'delegated superadmin reject');
check(P::allowed('SCHEDULER','APPROVED','publish',false),'publisher');
check(!P::allowed('STAFF','APPROVED','publish',false),'staff cannot publish');
check(!P::allowed('ADMIN','APPROVED','lock',false),'no skipping publish');
check(P::allowed('ADMIN','PUBLISHED','lock',false),'lock only published');
check(P::target('approve')==='APPROVED','target status');
check(P::target('reject')==='DRAFT','revision workflow');
check(P::target('unknown')===null,'unknown action');
echo "PASS ".$n." Duty review policy assertions; no database accessed.\n";
