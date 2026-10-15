<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Services/DutyPolicy.php';
require dirname(__DIR__).'/app/Services/DutyRosterValidationService.php';
require dirname(__DIR__).'/app/Services/SkillPolicy.php';
use App\Services\DutyRosterValidationService as V;
$n=0;function ck(bool $value,string $msg):void {global $n;$n++;if(!$value){fwrite(STDERR,"FAIL ".$msg."\n");exit(1);}}
$roster=['id'=>1,'hospital_id'=>1,'roster_year'=>2026,'roster_month'=>10,'revision'=>0,'status'=>'DRAFT'];
$coverage=[['duty_code'=>'ย','skill_code'=>'__TOTAL__','min_count'=>1,'evidence_reference'=>'CI test criteria']];
function v13Analyze(...$args):array {global $coverage;$args[]=$coverage;$args[]=[];return V::analyze(...$args);}
$people=[['id'=>2,'hospital_id'=>1,'is_active'=>1,'full_name'=>'ตัวอย่าง','role'=>'STAFF']];
function duty(int $id,string $date,string $code,int $uid=2):array {
 [$start,$end]=\App\Services\DutyPolicy::interval($date,$code);
 return ['id'=>$id,'user_id'=>$uid,'work_date'=>$date,'code'=>$code,'start_at'=>$start,'end_at'=>$end,'status'=>'ACTIVE'];
}
$clean=v13Analyze($roster,$people,[duty(1,'2026-10-03','ย')],[],[],[],[]);
ck($clean['critical']===0,'valid Saturday');
ck($clean['can_submit']===true,'valid Draft');
ck($clean['counts']['ย']===1,'count duty');
$empty=v13Analyze($roster,$people,[],[],[],[],[]);
ck($empty['critical']===1&&!$empty['can_submit'],'empty blocked');
$weekday=v13Analyze($roster,$people,[duty(1,'2026-10-05','ย')],[],[],[],[]);
ck($weekday['issues'][0]['code']==='HOLIDAY_REQUIRED','weekday non holiday');
$holiday=v13Analyze($roster,$people,[duty(1,'2026-10-05','ย')],[],[],[],[['holiday_date'=>'2026-10-05']]);
ck($holiday['critical']===0,'listed holiday');
$double=v13Analyze($roster,$people,[duty(1,'2026-10-03','ย'),duty(2,'2026-10-03','ย')],[],[],[],[]);
ck($double['critical']>0,'duplicate');
$leave=v13Analyze($roster,$people,[duty(1,'2026-10-03','ย')],[['requester_id'=>2,'starts_at'=>'2026-10-03 08:00:00','ends_at'=>'2026-10-03 17:00:00']],[],[],[]);
ck($leave['issues'][0]['code']==='LEAVE_OVERLAP','leave collision');
$other=v13Analyze($roster,$people,[duty(1,'2026-10-03','ย')],[],[['user_id'=>2,'start_at'=>'2026-10-03 10:00:00','end_at'=>'2026-10-03 12:00:00']],[],[]);
ck($other['issues'][0]['code']==='CONFIRMED_DUTY_OVERLAP','confirmed collision');
$v11=v13Analyze($roster,$people,[duty(1,'2026-10-03','ย')],[],[],[['user_id'=>2,'work_date'=>'2026-10-03','start_time'=>'08:00:00','end_time'=>'17:00:00']],[]);
ck($v11['issues'][0]['code']==='V11_OVERLAP','V11 collision');
$outside=v13Analyze($roster,$people,[duty(1,'2026-11-03','บ')],[],[],[],[]);
ck($outside['critical']>0,'outside month');
$readonly=v13Analyze([...$roster,'status'=>'SUBMITTED'],$people,[duty(1,'2026-10-03','ย')],[],[],[],[]);
ck($readonly['can_submit']===false,'submit only Draft');
$missing=V::analyze($roster,$people,[duty(1,'2026-10-03','ย')],[],[],[],[]);
ck($missing['critical']>0 && $missing['issues'][0]['code']==='COVERAGE_POLICY_MISSING','missing policy is critical');
$req=[...$coverage,['duty_code'=>'ย','skill_code'=>'RN','min_count'=>1,'evidence_reference'=>'CI test criteria']];
$gap=V::analyze($roster,$people,[duty(1,'2026-10-03','ย')],[],[],[],[],$req,[]);
ck($gap['critical']>0,'missing verified skill critical');
$qualified=V::analyze($roster,$people,[duty(1,'2026-10-03','ย')],[],[],[],[],$req,[['user_id'=>2,'skill_code'=>'RN']]);
ck($qualified['critical']===0,'verified RN meets minimum');
echo "PASS ".$n." Duty validation pure assertions; no database accessed.\n";
