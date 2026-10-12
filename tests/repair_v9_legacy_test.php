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
require dirname(__DIR__).'/app/Services/RosterRepairPlanner.php';
M::$shifts=[
  ['id'=>1,'code'=>'MORNING','name'=>'เช้า','short_name'=>'ช','is_working'=>1,'start_time'=>'08:00:00','end_time'=>'16:00:00'],
  ['id'=>2,'code'=>'AFTERNOON','name'=>'บ่าย','short_name'=>'บ','is_working'=>1,'start_time'=>'16:00:00','end_time'=>'00:00:00'],
  ['id'=>3,'code'=>'NIGHT','name'=>'ดึก','short_name'=>'ด','is_working'=>1,'start_time'=>'00:00:00','end_time'=>'08:00:00'],
  ['id'=>4,'code'=>'OFF','name'=>'หยุด','short_name'=>'OFF','is_working'=>0,'start_time'=>null,'end_time'=>null],
];
M::$staff=array_map(static fn($i)=>['id'=>$i,'full_name'=>'บุคลากรทดสอบ '.sprintf('%02d',$i),'personnel_type'=>'ทั่วไป'],range(1,12));
M::$settings=['min_morning'=>2,'min_afternoon'=>2,'min_night'=>2,'min_rest_hours'=>11,'max_consecutive_work_days'=>6,'max_night_shifts'=>8];
$r=['id'=>1,'hospital_id'=>1,'year'=>2026,'month'=>10,'lock_version'=>2,'status'=>'DRAFT'];
M::$entries=json_decode((string)file_get_contents(__DIR__.'/fixtures/legacy_v7_oct2026.json'),true,512,JSON_THROW_ON_ERROR);
$validation=RosterValidator::run($r);
echo 'Legacy errors: '. $validation['counts']['CRITICAL'].' and '. $validation['counts']['WARNING']." warnings\n";
$start=microtime(true);$plan=RosterRepairPlanner::plan($r);
echo 'Repair: '. $plan['before']['CRITICAL'].' → '.$plan['after']['CRITICAL'].' Critical; swaps: '.count($plan['swaps']).'; changes: '.count($plan['changes']).'; evaluations: '.$plan['evaluations'].'; time: '.round(microtime(true)-$start,2)." s\n";
if($plan['before']['CRITICAL']<=0 || $plan['after']['CRITICAL'] >= $plan['before']['CRITICAL'])throw new \RuntimeException('failed to improve legacy schedule');
}
