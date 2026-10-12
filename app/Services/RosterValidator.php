<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\RosterModel;
use App\Core\Database;

final class RosterValidator {
    public static function run(array $roster): array {
        $hospital=(int)$roster['hospital_id']; $year=(int)$roster['year']; $month=(int)$roster['month'];
        return self::assess($roster, RosterModel::entries((int)$roster['id']),
            RosterModel::staff($hospital), RosterModel::shifts(), RosterModel::settings($hospital),
            RosterModel::requirements($hospital),RosterModel::staffSkills($hospital));
    }
    /** Pure evaluation of a projected roster (no writes, no DB calls). V9 repair uses precisely the same rules as publishing. */
    public static function assess(array $roster,array $entries,array $staff,array $shifts,array $settings,array $requirements=[],array $skills=[]): array {
        $year=(int)$roster['year']; $month=(int)$roster['month'];
        $minRest=max(0,(int)($settings['min_rest_hours']??11));
        $shiftById=[];
        foreach($shifts as $shift) $shiftById[(int)$shift['id']]=$shift;
        $staffById=[];
        foreach($staff as $person) $staffById[(int)$person['id']]=$person;
        $issues=[];
        $add=static function(string $severity,string $rule,?string $date,?int $uid,string $message) use (&$issues): void {
            $issues[]=['severity'=>$severity,'rule_code'=>$rule,'work_date'=>$date,'user_id'=>$uid,'message'=>$message];
        };
        $byPerson=[]; $byDate=[]; $seen=[]; $verified=SkillPolicy::index($skills); $qualifiedCoverage=[];
        foreach($entries as $e) {
            $uid=(int)$e['user_id']; $date=$e['work_date']; $code=$e['code']; $key=$uid.'|'.$date;
            if(isset($seen[$key])) $add('CRITICAL','DUPLICATE_SHIFT',$date,$uid,'พบเวรซ้ำในวันเดียวกัน');
            $seen[$key]=true;
            if(!isset($staffById[$uid])) $add('CRITICAL','INVALID_STAFF',$date,$uid,'บุคลากรหมดสิทธิ์หรือย้ายหน่วยบริการ / inactive');
            if(!isset($shiftById[(int)$e['shift_type_id']])) $add('WARNING','INACTIVE_SHIFT',$date,$uid,'ประเภทเวรนี้ถูกปิดใช้งาน');
            $byPerson[$uid][$date]=$e;
            $byDate[$date][$code][]=$uid;
            foreach($verified[$uid]??[] as $skill=>$has) if($has)
                $qualifiedCoverage[$date][(int)$e['shift_type_id']][$skill]=($qualifiedCoverage[$date][(int)$e['shift_type_id']][$skill]??0)+1;
        }
        $days=(int)date('t',strtotime(sprintf('%04d-%02d-01',$year,$month)));
        $minimum=['MORNING'=>(int)$settings['min_morning'],'AFTERNOON'=>(int)$settings['min_afternoon'],'NIGHT'=>(int)$settings['min_night']];
        $thai=['MORNING'=>'เช้า','AFTERNOON'=>'บ่าย','NIGHT'=>'ดึก'];
        for($d=1;$d<=$days;$d++) {
            $date=sprintf('%04d-%02d-%02d',$year,$month,$d);
            foreach($minimum as $code=>$min) {
                $number=count($byDate[$date][$code] ?? []);
                if($number<$min) $add('CRITICAL','MIN_'.$code,$date,null,"เวร{$thai[$code]}มี {$number} คน ขั้นต่ำ {$min} คน");
            }
        }
        if($requirements) {
            for($d=1;$d<=$days;$d++) {
                $date=sprintf('%04d-%02d-%02d',$year,$month,$d);
                foreach($requirements as $need) {
                    $sid=(int)$need['shift_type_id'];$skill=(string)$need['skill_code'];
                    if(!isset(SkillPolicy::CATALOG[$skill]) || (int)$need['min_count']<1)continue;
                    $shiftCode=$shiftById[$sid]['code']??'SHIFT';
                    $count=(int)($qualifiedCoverage[$date][$sid][$skill]??0);
                    if($count<(int)$need['min_count'])
                        $add('CRITICAL','SKILL_COVERAGE_'.$skill,$date,null,
                            "{$date}: เวร {$shiftCode} ต้องมีทักษะ {$skill} ขั้นต่ำ {$need['min_count']} คน มีเพียง {$count} คน");
                }
            }
        }
        $maxDays=(int)$settings['max_consecutive_work_days']; $maxNights=(int)$settings['max_night_shifts'];
        foreach($staff as $person) {
            $uid=(int)$person['id']; $name=$person['full_name'];
            $nights=0; $streak=0; $working=0; $hasRest=false; $lastCode=null; $previousWork=null;
            for($d=1;$d<=$days;$d++) {
                $date=sprintf('%04d-%02d-%02d',$year,$month,$d);
                $e=$byPerson[$uid][$date] ?? null; $code=$e['code'] ?? null;
                $isWorking=$e && (int)$e['is_working']===1;
                if($code==='NIGHT') $nights++;
                if($isWorking) {
                    $streak++; $working++;
                    $interval=ShiftRestRules::interval($date,$e);
                    if($interval===null){
                        $add('CRITICAL','INVALID_SHIFT_TIME',$date,$uid,"{$name}: เวรทำงานไม่ระบุเวลาเริ่ม/สิ้นสุดที่ถูกต้อง");
                    }elseif($previousWork!==null){
                        $gap=ShiftRestRules::gapMinutes($previousWork['date'],$previousWork['entry'],$date,$e);
                        if($gap!==null && $gap<$minRest*60) {
                            $label=ShiftRestRules::hoursLabel($gap);
                            $add('CRITICAL','MIN_REST_HOURS',$date,$uid,
                                "{$name}: เวร {$previousWork['date']} → {$date} พัก {$label} (ขั้นต่ำ {$minRest} ชม.)");
                        }
                    }
                    $previousWork=['date'=>$date,'entry'=>$e];
                } else { $streak=0; $hasRest=true; }
                if($lastCode==='NIGHT' && $code==='MORNING') $add('CRITICAL','NIGHT_MORNING',$date,$uid,"{$name}: เวรดึกต่อด้วยเวรเช้าในวันถัดไป");
                if($streak===$maxDays+1) $add('WARNING','CONSECUTIVE_DAYS',$date,$uid,"{$name}: ทำงานต่อเนื่องเกิน {$maxDays} วัน");
                $lastCode=$code;
            }
            if($nights>$maxNights) $add('WARNING','NIGHT_QUOTA',null,$uid,"{$name}: เวรดึก {$nights} ครั้ง เกินเกณฑ์ {$maxNights}");
            if($working>0 && !$hasRest) $add('WARNING','NO_REST_DAY',null,$uid,"{$name}: ไม่พบวันพักตลอดเดือน");
        }
        $counts=['CRITICAL'=>0,'WARNING'=>0,'INFO'=>0];
        foreach($issues as $i) $counts[$i['severity']]++;
        return ['items'=>$issues,'counts'=>$counts,'valid_to_publish'=>$counts['CRITICAL']===0];
    }
    public static function persist(int $rosterId,array $result): void {
        $db=Database::pdo(); $q=$db->prepare('DELETE FROM roster_validation_results WHERE roster_id=?'); $q->execute([$rosterId]);
        $q=$db->prepare('INSERT INTO roster_validation_results (roster_id,severity,rule_code,work_date,user_id,message) VALUES (?,?,?,?,?,?)');
        foreach($result['items'] as $i) $q->execute([$rosterId,$i['severity'],$i['rule_code'],$i['work_date'],$i['user_id'],$i['message']]);
    }
}
