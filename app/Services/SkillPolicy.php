<?php
declare(strict_types=1);
namespace App\Services;

/** Hospital-specific, explicitly confirmed duty skills. No position/title inference. */
final class SkillPolicy {
    public const CATALOG = [
        'RN'=>'ทักษะการพยาบาล (ตรวจสอบหลักฐานโดยหน่วยบริการ)',
        'CLINICAL'=>'ทักษะบริการทางคลินิก',
        'EMERGENCY'=>'ทักษะดูแลผู้ป่วยฉุกเฉิน',
        'PUBLIC_HEALTH'=>'ทักษะงานสาธารณสุข',
    ];
    public static function index(array $assignments): array {
        $out=[];
        foreach($assignments as $a) if(isset(self::CATALOG[(string)($a['skill_code']??'')]))
            $out[(int)$a['user_id']][(string)$a['skill_code']]=true;
        return $out;
    }
    /** Pure coverage check. Requirement rows contain shift_type_id, skill_code, min_count. */
    public static function missing(array $entries,array $requirements,array $assignments,array $shifts): array {
        if(!$requirements)return [];
        $index=self::index($assignments); $ids=[];
        foreach($shifts as $s)$ids[(int)$s['id']]=(string)$s['code'];
        $byDay=[];
        foreach($entries as $e) $byDay[(string)$e['work_date']][(int)$e['shift_type_id']][]=(int)$e['user_id'];
        $missing=[];
        foreach($byDay as $date=>$byShift) foreach($requirements as $req) {
            $sid=(int)$req['shift_type_id'];$code=(string)$req['skill_code'];$min=(int)$req['min_count'];
            if($min<1 || !isset(self::CATALOG[$code]))continue;
            $actual=0;
            foreach($byShift[$sid]??[] as $uid)if(!empty($index[$uid][$code]))$actual++;
            if($actual<$min)$missing[]=['date'=>$date,'shift_type_id'=>$sid,'shift_code'=>$ids[$sid]??'',
                'skill_code'=>$code,'skill_name'=>self::CATALOG[$code],'actual'=>$actual,'minimum'=>$min];
        }
        return $missing;
    }
    /** For an unfilled shift, reserve enough of the remaining positions for the missing skills. */
    public static function mayAssign(int $uid,string $date,int $shiftId,array $dayCodes,array $shiftByCode,
                                     array $requirements,array $skills,int $target,int $already): bool {
        if(!$requirements)return true;
        $remaining=$target-$already;
        if($remaining<=0)return true;
        foreach($requirements as $req) {
            if((int)$req['shift_type_id']!==$shiftId || (int)$req['min_count']<1)continue;
            $code=(string)$req['skill_code'];$covered=0;
            foreach($dayCodes[$date]??[] as $personId=>$shiftCode) {
                if(!isset($shiftByCode[$shiftCode]) || (int)$shiftByCode[$shiftCode]['id']!==$shiftId)continue;
                if(!empty($skills[(int)$personId][$code]))$covered++;
            }
            $needed=(int)$req['min_count']-$covered;
            if($needed>=$remaining && empty($skills[$uid][$code]))return false;
        }
        return true;
    }
}
