<?php
declare(strict_types=1);
namespace App\Services;

/** Advisory-only monthly dispersion; not a substitute for contracted hours or specialty-specific targets. */
final class FairnessAnalyzer {
    public static function report(array $people): array {
        $items=[];
        foreach($people as $s) {
            $work=(int)($s['MORNING']??0)+(int)($s['AFTERNOON']??0)+(int)($s['NIGHT']??0);
            $items[]=['user_id'=>(int)$s['user_id'],'name'=>(string)$s['full_name'],
                'work'=>$work,'night'=>(int)($s['NIGHT']??0),
                'weekend'=>(int)($s['weekend_shifts']??0),'holiday'=>(int)($s['holiday_shifts']??0),
                'hours'=>(float)($s['working_hours']??0)];
        }
        $metrics=[];
        foreach(['work','night','weekend','holiday','hours'] as $metric){
            $values=array_column($items,$metric);$low=$values?min($values):0;$high=$values?max($values):0;
            $metrics[$metric]=['min'=>$low,'max'=>$high,'gap'=>$high-$low,'average'=>count($values)?round(array_sum($values)/count($values),2):0];
        }
        usort($items,static fn($a,$b)=>($b['work']<=>$a['work']) ?: ($b['night']<=>$a['night']) ?: ($a['user_id']<=>$b['user_id']));
        return ['metrics'=>$metrics,'people'=>$items,
            'needs_review'=>($metrics['work']['gap']>3 || $metrics['night']['gap']>2),
            'note'=>'ตัวเลขนี้เป็นข้อมูลประกอบการพิจารณา ต้องเทียบอัตราจ้าง ภาระงาน ทักษะ ข้อจำกัดส่วนบุคคล และการหมุนเวียนข้ามเดือนก่อนสรุปความเป็นธรรม'];
    }
}
