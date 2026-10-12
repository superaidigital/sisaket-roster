<?php
declare(strict_types=1);
namespace App\Models {
    final class RosterModel {
        public static array $staff=[],$shifts=[],$settings=[],$entries=[];
        public static function staff(int $id):array{return self::$staff;}
        public static function shifts():array{return self::$shifts;}
        public static function settings(int $id):array{return self::$settings;}
        public static function entries(int $id):array{return self::$entries;}
        public static function requirements(int $h):array{return [];}
        public static function staffSkills(int $h):array{return [];}
        public static function holidays(int $h,int $y,int $m):array{return [];}
    }
}
namespace {
    use App\Models\RosterModel as M;
    use App\Services\{AutoScheduler,RosterValidator,RosterRepairPlanner};
    function jsonError(string $message,int $code=400):never{throw new \RuntimeException($message,$code);}
    require dirname(__DIR__).'/app/Services/SkillPolicy.php';
require dirname(__DIR__).'/app/Services/ShiftRestRules.php';
    require dirname(__DIR__).'/app/Services/RosterValidator.php';
    require dirname(__DIR__).'/app/Services/AutoScheduler.php';
    require dirname(__DIR__).'/app/Services/RosterRepairPlanner.php';
    $count=0;
    function check(bool $state,string $message):void {global $count; $count++;if(!$state)throw new \RuntimeException('TEST FAILED: '.$message);}
    M::$shifts=[
      ['id'=>1,'code'=>'MORNING','name'=>'เช้า','short_name'=>'ช','is_working'=>1,'start_time'=>'08:00:00','end_time'=>'16:00:00'],
      ['id'=>2,'code'=>'AFTERNOON','name'=>'บ่าย','short_name'=>'บ','is_working'=>1,'start_time'=>'16:00:00','end_time'=>'00:00:00'],
      ['id'=>3,'code'=>'NIGHT','name'=>'ดึก','short_name'=>'ด','is_working'=>1,'start_time'=>'00:00:00','end_time'=>'08:00:00'],
      ['id'=>4,'code'=>'OFF','name'=>'หยุด','short_name'=>'OFF','is_working'=>0,'start_time'=>null,'end_time'=>null],
      ['id'=>5,'code'=>'LEAVE','name'=>'ลา','short_name'=>'ลา','is_working'=>0,'start_time'=>null,'end_time'=>null],
    ];
    M::$staff=array_map(static fn($i)=>['id'=>$i,'full_name'=>'ทดสอบ '.$i,'personnel_type'=>'ทั่วไป'],range(1,12));
    M::$settings=['min_morning'=>2,'min_afternoon'=>2,'min_night'=>2,'min_rest_hours'=>11,'max_consecutive_work_days'=>6,'max_night_shifts'=>8];
    $r=['id'=>1,'hospital_id'=>1,'year'=>2026,'month'=>10,'lock_version'=>9,'status'=>'DRAFT'];
    $byId=array_column(M::$shifts,null,'id');
    foreach(AutoScheduler::plan($r)['changes'] as $row){
       $s=$byId[$row['shift_type_id']];
       M::$entries[]=['user_id'=>$row['user_id'],'work_date'=>$row['date'],'shift_type_id'=>$s['id'],
           'code'=>$s['code'],'shift_name'=>$s['name'],'short_name'=>$s['short_name'],'is_working'=>$s['is_working'],
           'start_time'=>$s['start_time'],'end_time'=>$s['end_time'],'note'=>''];
    }
    $valid=RosterValidator::run($r);
    check($valid['counts']['CRITICAL']===0,'healthy generated schedule');
    $safe=RosterRepairPlanner::plan($r);
    check(count($safe['changes'])===0 && count($safe['swaps'])===0,'healthy schedule needs no repair');
    check($safe['before']['CRITICAL']===0 && $safe['after']['CRITICAL']===0,'healthy plan remains valid');
    $ids=[];
    foreach(M::$entries as $i=>$e)$ids[$e['work_date']][(int)$e['user_id']]=$i;
    $found=false;
    foreach($ids as $date=>$people){
        foreach($people as $a=>$i){
            foreach($people as $b=>$j){
                if($a===$b || M::$entries[$i]['code']===M::$entries[$j]['code'])continue;
                $aa=M::$entries[$i];$bb=M::$entries[$j];
                foreach(['shift_type_id','code','shift_name','short_name','is_working','start_time','end_time'] as $f){
                    M::$entries[$i][$f]=$bb[$f]; M::$entries[$j][$f]=$aa[$f];
                }
                if(RosterValidator::run($r)['counts']['CRITICAL']>0){$found=true;break 3;}
                M::$entries[$i]=$aa;M::$entries[$j]=$bb;
            }
        }
    }
    check($found,'constructs unsafe legacy swap');
    $before=RosterValidator::run($r);
    $result=RosterRepairPlanner::plan($r);
    check($result['before']['CRITICAL']>0,'detects invalid rest');
    check($result['after']['CRITICAL']<$result['before']['CRITICAL'],'reduces Critical');
    check(count($result['changes'])>=2,'repair has at least two changes');
    check(count($result['swaps'])>=1,'provides human-readable swap plan');
    check($result['coverage_preserved']===true,'preserves shift coverage');
    $annotated=M::$entries;
    $annotated[$i]['note']='ห้ามเปลี่ยน';
    $withoutNoteSwap=RosterRepairPlanner::suggest($r,$annotated,M::$staff,M::$shifts,M::$settings);
    foreach($withoutNoteSwap['changes'] as $change)check(!($change['date']===$annotated[$i]['work_date'] && $change['user_id']===(int)$annotated[$i]['user_id']),'annotated slot untouched');
    check($withoutNoteSwap['after']['CRITICAL'] <= $withoutNoteSwap['before']['CRITICAL'],'annotated repair never makes critical worse');
    check(strlen($result['preview_hash'])===64,'digest included');
    $digest=$result['preview_hash'];
    check($digest===RosterRepairPlanner::plan($r)['preview_hash'],'deterministic same revision');
    $r['lock_version']=10;
    check($digest!==RosterRepairPlanner::plan($r)['preview_hash'],'reject stale preview digest');
    foreach($result['changes'] as $change){
        $idx=$ids[$change['date']][$change['user_id']];
        $shift=$byId[$change['shift_type_id']];
        foreach(['id'=>'shift_type_id','code'=>'code','name'=>'shift_name','short_name'=>'short_name',
          'is_working'=>'is_working','start_time'=>'start_time','end_time'=>'end_time'] as $f=>$dest)M::$entries[$idx][$dest]=$shift[$f];
    }
    check(RosterValidator::run($r)['counts']['CRITICAL']===$result['after']['CRITICAL'],'preview agrees with saved projection');
    echo "V9 repair tests passed: {$count} assertions. Critical: {$result['before']['CRITICAL']} → {$result['after']['CRITICAL']}; swaps: ".count($result['swaps'])."; evaluations: {$result['evaluations']}\n";
}
