<?php
declare(strict_types=1);
namespace App\Models {
    final class RosterModel {
        public static array $entries=[];
        public static array $staff=[];
        public static array $settings=[];
        public static function staff(int $h):array{return self::$staff;}
        public static function settings(int $h):array{return self::$settings;}
        public static function shifts():array{return [
            ['id'=>1,'code'=>'MORNING','name'=>'เช้า','is_working'=>1,'start_time'=>'08:00:00','end_time'=>'16:00:00'],
            ['id'=>2,'code'=>'AFTERNOON','name'=>'บ่าย','is_working'=>1,'start_time'=>'16:00:00','end_time'=>'00:00:00'],
            ['id'=>3,'code'=>'NIGHT','name'=>'ดึก','is_working'=>1,'start_time'=>'00:00:00','end_time'=>'08:00:00'],
            ['id'=>4,'code'=>'OFF','name'=>'OFF','is_working'=>0,'start_time'=>null,'end_time'=>null],
            ['id'=>5,'code'=>'LEAVE','name'=>'ลา','is_working'=>0,'start_time'=>null,'end_time'=>null],
        ];}
        public static function entries(int $roster):array{return self::$entries;}
        public static function requirements(int $h):array{return [];}
        public static function staffSkills(int $h):array{return [];}
        public static function holidays(int $h,int $y,int $m):array{return [];}
    }
}
namespace {
    use App\Models\RosterModel as M;
    use App\Services\{ShiftRestRules as Rest,RosterValidator,AutoScheduler};
    function jsonError(string $message,int $code=400):never{throw new \RuntimeException($message,$code);}
    require dirname(__DIR__).'/app/Services/SkillPolicy.php';
require dirname(__DIR__).'/app/Services/ShiftRestRules.php';
    require dirname(__DIR__).'/app/Services/RosterValidator.php';
    require dirname(__DIR__).'/app/Services/AutoScheduler.php';
    $checks=0;
    function ok(bool $flag,string $description):void {global $checks;$checks++;if(!$flag)throw new \RuntimeException('FAILED: '.$description);}
    function entry(int $user,string $date,string $code):array{
        foreach(M::shifts() as $shift)if($shift['code']===$code)return [
            'user_id'=>$user,'work_date'=>$date,'code'=>$code,'shift_type_id'=>$shift['id'],
            'is_working'=>$shift['is_working'],'start_time'=>$shift['start_time'],'end_time'=>$shift['end_time']];
        throw new \RuntimeException('Unknown shift');
    }
    $shift=array_column(M::shifts(),null,'code');
    ok(Rest::gapMinutes('2026-10-18',$shift['AFTERNOON'],'2026-10-19',$shift['NIGHT'])===0,'Afternoon->Night at midnight has zero rest');
    ok(Rest::gapMinutes('2026-10-18',$shift['AFTERNOON'],'2026-10-19',$shift['MORNING'])===480,'Afternoon->Morning rest 8h');
    ok(Rest::gapMinutes('2026-10-18',$shift['MORNING'],'2026-10-19',$shift['NIGHT'])===480,'Morning->Night rest 8h');
    ok(Rest::gapMinutes('2026-10-18',$shift['MORNING'],'2026-10-19',$shift['MORNING'])===960,'Morning->Morning rest 16h');
    ok(Rest::gapMinutes('2028-02-29',$shift['AFTERNOON'],'2028-03-01',$shift['NIGHT'])===0,'Leap-month boundary midnight');
    ok(Rest::gapMinutes('2026-10-18',$shift['NIGHT'],'2026-10-19',$shift['AFTERNOON'])===1920,'Night->Afternoon rest 32h');
    $byDay=['2026-10-18'=>[1=>'AFTERNOON'],'2026-10-19'=>[2=>'NIGHT']];
    ok(!Rest::canAssign(1,'2026-10-19','NIGHT',$byDay,$shift,11),'Reject zero-rest shift');
    ok(!Rest::canAssign(2,'2026-10-18','AFTERNOON',$byDay,$shift,11),'Reject future conflicting assignment');
    ok(Rest::canAssign(1,'2026-10-19','AFTERNOON',$byDay,$shift,11),'Allow safe next afternoon');
    ok(!Rest::canAssign(1,'2026-10-19','MORNING',$byDay,$shift,11),'Reject 8h rest');
    ok(Rest::canAssign(1,'2026-10-20','MORNING',$byDay,$shift,11),'Allow rest with gap');
    ok(Rest::interval('2026-10-18',$shift['OFF'])===null,'Nonworking shift has no interval');
    $conflicts=Rest::conflicts(['2026-10-18'=>[1=>'AFTERNOON'],'2026-10-19'=>[1=>'NIGHT']],$shift,11);
    ok(count($conflicts)===1 && $conflicts[0]['rule']==='MIN_REST_HOURS','Preview shows legacy zero-rest conflict');
    M::$settings=['min_morning'=>0,'min_afternoon'=>0,'min_night'=>0,'max_consecutive_work_days'=>6,'max_night_shifts'=>8,'min_rest_hours'=>11];
    M::$staff=[['id'=>1,'full_name'=>'ผู้ทดสอบ','personnel_type'=>'พยาบาล','is_active'=>1]];
    M::$entries=[entry(1,'2026-10-18','AFTERNOON'),entry(1,'2026-10-19','NIGHT')];
    $roster=['id'=>1,'hospital_id'=>1,'year'=>2026,'month'=>10];
    $validation=RosterValidator::run($roster);
    ok($validation['counts']['CRITICAL']===1 && $validation['items'][0]['rule_code']==='MIN_REST_HOURS','Validator blocks zero-rest after publish');
    ok(str_contains($validation['items'][0]['message'],'0 ชม.'),'Critical description indicates zero rest');
    M::$entries=[entry(1,'2026-10-18','AFTERNOON'),entry(1,'2026-10-19','MORNING')];
    $validation=RosterValidator::run($roster);
    ok($validation['counts']['CRITICAL']===1,'Validator blocks 8-hour rest under 11-hour policy');
    M::$entries=[entry(1,'2026-10-18','NIGHT'),entry(1,'2026-10-19','MORNING')];
    $validation=RosterValidator::run($roster);
    ok(array_filter($validation['items'],static fn($i)=>$i['rule_code']==='NIGHT_MORNING')!==[],'Preexisting Night->Morning policy remains');
    M::$entries=[entry(1,'2026-10-18','AFTERNOON'),entry(1,'2026-10-19','OFF'),entry(1,'2026-10-20','NIGHT')];
    ok(RosterValidator::run($roster)['counts']['CRITICAL']===0,'OFF gap allows safe rest');
    M::$staff=array_map(static fn(int $n)=>['id'=>$n,'full_name'=>'Tester '.$n,'personnel_type'=>'test'],range(1,12));
    M::$settings=['min_morning'=>2,'min_afternoon'=>2,'min_night'=>2,'max_consecutive_work_days'=>6,'max_night_shifts'=>8,'min_rest_hours'=>11];
    M::$entries=[];
    $plan=AutoScheduler::plan($roster);
    ok($plan['unfilled']===[],'Scheduler fills coverage for 12');
    ok($plan['preview']['rest_issues']===[],'Scheduler 12 staff has no rest conflicts');
    ok($plan['preview']['minimum_rest_hours']===11,'Preview shows min rest setting');
    $codes=array_column(M::shifts(),null,'id');
    M::$entries=[];
    foreach($plan['changes'] as $x) {
        $e=$codes[$x['shift_type_id']];
        M::$entries[]=entry($x['user_id'],$x['date'],$e['code']);
    }
    $validation=RosterValidator::run($roster);
    ok($validation['counts']['CRITICAL']===0,'Validator confirms auto schedule has 0 Critical');
    ok($validation['counts']['WARNING']===0,'Validator confirms auto schedule has 0 Warning');
    M::$entries=[entry(1,'2026-10-18','AFTERNOON'),entry(1,'2026-10-19','NIGHT')];
    $plan=AutoScheduler::plan($roster);
    ok($plan['preview']['rest_safe']===false && count($plan['preview']['rest_issues'])>=1,'Auto Preview warns about unchanged invalid legacy shifts');
    ok(!in_array('2026-10-18|1',array_map(static fn($c)=>$c['date'].'|'.$c['user_id'],$plan['changes']),true),'Preserve existing afternoon');
    ok(!in_array('2026-10-19|1',array_map(static fn($c)=>$c['date'].'|'.$c['user_id'],$plan['changes']),true),'Preserve existing night');
    echo "V8 Shift Rest tests passed: {$checks} assertions. No MySQL connection required.\n";
}
