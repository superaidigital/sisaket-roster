<?php
declare(strict_types=1);
/** Deterministic database-free regression tests for auto-schedule V5.
 * Run: php tests/autoschedule_v5_test.php
 */
namespace App\Models {
    final class RosterModel {
        public static array $people=[];
        public static array $assigned=[];
        public static array $parameters=[];
        public static array $daysOff=[];
        public static function staff(int $hospitalId):array { return self::$people; }
        public static function shifts():array { return [
            ['id'=>1,'code'=>'MORNING','is_working'=>1,'start_time'=>'08:00:00','end_time'=>'16:00:00'],
            ['id'=>2,'code'=>'AFTERNOON','is_working'=>1,'start_time'=>'16:00:00','end_time'=>'00:00:00'],
            ['id'=>3,'code'=>'NIGHT','is_working'=>1,'start_time'=>'00:00:00','end_time'=>'08:00:00'],
            ['id'=>4,'code'=>'OFF','is_working'=>0,'start_time'=>null,'end_time'=>null],
            ['id'=>5,'code'=>'LEAVE','is_working'=>0,'start_time'=>null,'end_time'=>null]]; }
        public static function settings(int $hospitalId):array {return self::$parameters;}
        public static function entries(int $rosterId):array {return self::$assigned;}
        public static function requirements(int $h):array{return [];}
        public static function staffSkills(int $h):array{return [];}
        public static function holidays(int $hospitalId,int $year,int $month):array {return self::$daysOff;}
    }
}
namespace {
    use App\Models\RosterModel as M;
    use App\Services\AutoScheduler;
    function jsonError(...$args):never {throw new \RuntimeException((string)$args[0]);}
    require dirname(__DIR__).'/app/Services/SkillPolicy.php';
require dirname(__DIR__).'/app/Services/ShiftRestRules.php';
    require dirname(__DIR__).'/app/Services/AutoScheduler.php';
    $checked=0;
    $verify=static function(bool $condition,string $label) use (&$checked):void {
        $checked++;
        if(!$condition) {fwrite(STDERR,"FAIL: {$label}\n");exit(1);}
    };
    $roster=['id'=>1,'hospital_id'=>1,'year'=>2026,'month'=>10];
    M::$parameters=['min_morning'=>2,'min_afternoon'=>2,'min_night'=>2,
        'max_consecutive_work_days'=>6,'max_night_shifts'=>8,'min_rest_hours'=>11];
    M::$people=array_map(static fn(int $n)=>['id'=>$n,'full_name'=>'บุคลากรทดสอบ '.$n,
        'personnel_type'=>$n%2?'พยาบาล':'สาธารณสุข'],range(1,12));
    $plan=AutoScheduler::plan($roster);
    $overview=$plan['preview'];
    $verify(count($plan['unfilled'])===0,'12 staff fills coverage');
    $verify($overview['coverage_complete']===true,'preview complete flag');
    $verify($overview['staff_count']===12,'12 staff count');
    $verify($overview['days']===31,'October 31 days');
    $verify($overview['totals']['MORNING']===62,'morning total 62');
    $verify($overview['totals']['AFTERNOON']===62,'afternoon total 62');
    $verify($overview['totals']['NIGHT']===62,'night total 62');
    $verify($overview['totals']['OFF']===186,'OFF total 186');
    $verify($plan['working_assigned']===186,'186 working shifts');
    $verify($plan['total_changes']===372,'372 monthly cells');
    $verify(count($overview['daily'])===31,'31 daily metrics');
    $verify(count(array_filter($overview['daily'],static fn($d)=>$d['shortage']>0))===0,'No daily shortage');
    $verify($overview['night_range']['max']<=8,'All night counts under quota');
    $verify($overview['working_range']['max']-$overview['working_range']['min']<=4,'Fair distribution of total work');
    $keyed=[];
    foreach($plan['changes'] as $row){
        $key=$row['user_id'].'|'.$row['date'];
        $verify(!isset($keyed[$key]),'No duplicate user-day: '.$key);
        $keyed[$key]=$row['shift_type_id'];
    }
    foreach(range(1,12) as $uid){
        $streak=0;$nights=0;$last=null;
        foreach(range(1,31) as $d){
            $date=sprintf('2026-10-%02d',$d);$code=$keyed[$uid.'|'.$date]??0;
            $verify(!($last===3 && $code===1),'No NIGHT then MORNING');
            $streak=in_array($code,[1,2,3],true)?$streak+1:0;
            $verify($streak<=6,'No overlong streak');
            if($code===3)$nights++;
            $last=$code;
        }
        $verify($nights<=8,'Individual night quota');
    }
    // Existing entries are never overwritten; leave/off are respected.
    M::$assigned=[['user_id'=>1,'work_date'=>'2026-10-01','code'=>'LEAVE','shift_type_id'=>5,'is_working'=>0],
       ['user_id'=>2,'work_date'=>'2026-10-02','code'=>'NIGHT','shift_type_id'=>3,'is_working'=>1],
       ['user_id'=>3,'work_date'=>'2026-10-03','code'=>'OFF','shift_type_id'=>4,'is_working'=>0]];
    $withExisting=AutoScheduler::plan($roster);
    $verify(count($withExisting['changes'])===369,'Existing shifts preserved');
    foreach($withExisting['changes'] as $row){
        $verify(!in_array($row['user_id'].'|'.$row['date'],['1|2026-10-01','2|2026-10-02','3|2026-10-03'],true),'No existing entry overwritten');
    }
    $verify($withExisting['preview']['totals']['LEAVE']===1,'Existing leave counted');
    M::$assigned=[];
    M::$people=array_slice(M::$people,0,3);
    $short=AutoScheduler::plan($roster);
    $verify(count($short['unfilled'])>0,'Insufficient staff reported');
    $verify(!$short['preview']['coverage_complete'],'Insufficient coverage incomplete');
    $verify(count(array_filter($short['preview']['daily'],static fn($d)=>$d['shortage']>0))>0,'Shortfall by day present');
    $roster['year']=2028;$roster['month']=2;
    M::$people=array_map(static fn(int $n)=>['id'=>$n,'full_name'=>'P '.$n,'personnel_type'=>'test'],range(1,12));
    $leap=AutoScheduler::plan($roster);
    $verify($leap['preview']['days']===29,'Leap February 29 days');
    $verify($leap['total_changes']===348,'29x12 cells');
    echo "V5 auto-schedule tests passed: {$checked} assertions. October: 186 working shifts, 0 shortages.\n";
}
