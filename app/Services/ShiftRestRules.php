<?php
declare(strict_types=1);
namespace App\Services;

/** Shared V8 shift-rest calculation. Shift starts belong to their work_date;
 * end_time <= start_time means the shift ends on the following calendar day.
 * Time math uses Asia/Bangkok (no DST); no existing entry is altered here.
 */
final class ShiftRestRules {
    public static function interval(string $date,array $shift): ?array {
        if (!(int)($shift['is_working'] ?? 0)) return null;
        $start=(string)($shift['start_time']??'');
        $end=(string)($shift['end_time']??'');
        if(!preg_match('/^([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/',$start)
            || !preg_match('/^([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/',$end)) return null;
        $zone=new \DateTimeZone('Asia/Bangkok');
        $s=new \DateTimeImmutable($date.' '. $start,$zone);
        $e=new \DateTimeImmutable($date.' '. $end,$zone);
        if($e <= $s) $e=$e->modify('+1 day');
        return ['start'=>$s->getTimestamp(),'end'=>$e->getTimestamp()];
    }
    /** Signed minutes from end of earlier shift to start of the next shift.
     * Returns null if one shift has missing or invalid working hours.
     */
    public static function gapMinutes(string $oldDate,array $oldShift,string $newDate,array $newShift): ?int {
        $a=self::interval($oldDate,$oldShift);$b=self::interval($newDate,$newShift);
        if($a===null||$b===null)return null;
        return (int)round(($b['start']-$a['end'])/60);
    }
    /** Check a new shift against persisted and suggested assignments around the day.
     * One user may have only one shift per work_date; OFF and LEAVE need no rest.
     */
    public static function canAssign(int $uid,string $date,string $code,array $byDate,array $shiftCodes,int $minimumHours): bool {
        $candidate=$shiftCodes[$code]??null;
        if(!$candidate || self::interval($date,$candidate)===null)return false;
        foreach([-1,1] as $offset){
            $otherDate=date('Y-m-d',strtotime($date.($offset<0?' -1 day':' +1 day')));
            $otherCode=$byDate[$otherDate][$uid]??null;
            if($otherCode===null || !isset($shiftCodes[$otherCode]))continue;
            $other=$shiftCodes[$otherCode];
            if(!(int)($other['is_working']??0))continue;
            if($offset<0){
                if($otherCode==='NIGHT' && $code==='MORNING')return false; // pre-existing organizational policy
                $gap=self::gapMinutes($otherDate,$other,$date,$candidate);
            }else{
                if($code==='NIGHT' && $otherCode==='MORNING')return false;
                $gap=self::gapMinutes($date,$candidate,$otherDate,$other);
            }
            if($gap===null || $gap<$minimumHours*60)return false;
        }
        return true;
    }
    /** Read-only preview of legacy or preserved assignments that the scheduler cannot alter. */
    public static function conflicts(array $byDate,array $shiftCodes,int $minimumHours): array {
        $byPerson=[];
        foreach($byDate as $date=>$people) foreach($people as $uid=>$code) {
            $shift=$shiftCodes[$code]??null;
            if($shift && (int)($shift['is_working']??0)) $byPerson[(int)$uid][$date]=$code;
        }
        $issues=[];
        foreach($byPerson as $uid=>$dates){
            ksort($dates);
            $previous=null;
            foreach($dates as $date=>$code){
                $current=$shiftCodes[$code];
                if(self::interval($date,$current)===null){
                    $issues[]=['user_id'=>$uid,'date'=>$date,'rule'=>'INVALID_SHIFT_TIME',
                        'message'=>'เวรทำงานไม่ระบุเวลาเริ่ม/สิ้นสุดที่ถูกต้อง'];
                }elseif($previous!==null){
                    $gap=self::gapMinutes($previous['date'],$shiftCodes[$previous['code']],$date,$current);
                    if($gap!==null && $gap<$minimumHours*60){
                        $issues[]=['user_id'=>$uid,'date'=>$date,'rule'=>'MIN_REST_HOURS',
                            'message'=>'พักเพียง '.self::hoursLabel($gap).' (ขั้นต่ำ '.$minimumHours.' ชม.)'];
                    }
                    if($previous['code']==='NIGHT' && $code==='MORNING'
                        && $date===date('Y-m-d',strtotime($previous['date'].' +1 day'))){
                        $issues[]=['user_id'=>$uid,'date'=>$date,'rule'=>'NIGHT_MORNING',
                            'message'=>'เวรดึกต่อด้วยเวรเช้าวันถัดไป (ข้อกำหนดเดิมของหน่วยบริการ)'];
                    }
                }
                $previous=['date'=>$date,'code'=>$code];
            }
        }
        return $issues;
    }
    public static function hoursLabel(int $minutes): string {
        if($minutes<0)return 'ทับซ้อน '.sprintf('%d ชม. %02d นาที',intdiv(-$minutes,60),(-$minutes)%60);
        return sprintf('%d ชม. %02d นาที',intdiv($minutes,60),$minutes%60);
    }
}
