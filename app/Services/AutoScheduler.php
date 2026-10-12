<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\RosterModel;

final class AutoScheduler {
    /** Deterministic greedy proposal: preserves all existing cells, never overrides leave/off.
     * Coverage is a goal, not a guarantee; unresolved shortages are explicitly reported.
     */
    public static function plan(array $roster): array {
        $staff=RosterModel::staff((int)$roster['hospital_id']);
        $shifts=RosterModel::shifts(); $shiftCodes=[];
        foreach($shifts as $s) $shiftCodes[$s['code']]=$s;
        foreach(['MORNING','AFTERNOON','NIGHT','OFF'] as $code) if(!isset($shiftCodes[$code])) jsonError("ต้องกำหนด Shift {$code} ที่ Active ก่อน",422);
        $settings=RosterModel::settings((int)$roster['hospital_id']);
        $requirements=RosterModel::requirements((int)$roster['hospital_id']);
        $verifiedSkills=SkillPolicy::index(RosterModel::staffSkills((int)$roster['hospital_id']));
        $minimumRest=max(0,(int)($settings['min_rest_hours']??11));
        $entries=RosterModel::entries((int)$roster['id']);
        $plan=[]; $counts=[]; $byDay=[]; $typeById=[]; $coverageTypes=[];
        $holidays=RosterModel::holidays((int)$roster['hospital_id'],(int)$roster['year'],(int)$roster['month']);
        $holidayDates=array_fill_keys(array_column($holidays,'holiday_date'),true);
        foreach($staff as $s) { $counts[(int)$s['id']]=['work'=>0,'night'=>0,'weekend'=>0,'holiday'=>0,'MORNING'=>0,'AFTERNOON'=>0,'NIGHT'=>0]; $typeById[(int)$s['id']]=$s['personnel_type'] ?: 'อื่นๆ'; }
        foreach($entries as $e) {
            $uid=(int)$e['user_id']; $date=$e['work_date'];
            $byDay[$date][$uid]=$e['code'];
            if(isset($typeById[$uid]) && (int)$e['is_working']) { $kind=$typeById[$uid]; $coverageTypes[$date][$e['code']][$kind]=($coverageTypes[$date][$e['code']][$kind]??0)+1; }
            if(!isset($counts[$uid]) || !(int)$e['is_working']) continue;
            $counts[$uid]['work']++;
            if(isset($counts[$uid][$e['code']])) $counts[$uid][$e['code']]++;
            if($e['code']==='NIGHT') $counts[$uid]['night']++;
            if((int)date('N',strtotime($date))>=6) $counts[$uid]['weekend']++;
            if(isset($holidayDates[$date])) $counts[$uid]['holiday']++;
        }
        $first=sprintf('%04d-%02d-01',$roster['year'],$roster['month']); $days=(int)date('t',strtotime($first));
        $unfilled=[]; $suggestions=[];
        $limits=['NIGHT'=>(int)$settings['min_night'],'MORNING'=>(int)$settings['min_morning'],'AFTERNOON'=>(int)$settings['min_afternoon']];
        foreach(range(1,$days) as $d) {
            $date=sprintf('%04d-%02d-%02d',$roster['year'],$roster['month'],$d);
            $isWeekend=(int)date('N',strtotime($date))>=6;
            $isHoliday=isset($holidayDates[$date]);
            foreach($limits as $code=>$target) {
                $assigned=count(array_filter($byDay[$date] ?? [],static fn($value,$uid)=>$value===$code && isset($counts[$uid]),ARRAY_FILTER_USE_BOTH));
                for($i=$assigned;$i<$target;$i++) {
                    $candidates=[];
                    foreach($staff as $person) {
                        $uid=(int)$person['id'];
                        if(isset($byDay[$date][$uid])) continue;
                        if($code==='NIGHT' && $counts[$uid]['night'] >= (int)$settings['max_night_shifts']) continue;
                        if(!SkillPolicy::mayAssign($uid,$date,(int)$shiftCodes[$code]['id'],$byDay,$shiftCodes,
                            $requirements,$verifiedSkills,$target,$i)) continue;
                        $prev=date('Y-m-d',strtotime($date.' -1 day'));
                        // V8: the same cross-midnight rest rule applies to both planning and validation.
                        if(!ShiftRestRules::canAssign($uid,$date,$code,$byDay,$shiftCodes,$minimumRest))continue;
                        $streak=0; $p=$prev;
                        while(isset($byDay[$p][$uid]) && !in_array($byDay[$p][$uid],['OFF','LEAVE'],true)) {
                            $streak++; $p=date('Y-m-d',strtotime($p.' -1 day'));
                        }
                        if($streak >= (int)$settings['max_consecutive_work_days']) continue;
                        $c=$counts[$uid];
                        $score=$c['work']*10 + ($code==='NIGHT'?$c['night']*8:0)
                            + ($c[$code]??0)*7
                            + ($isWeekend?$c['weekend']*5:0) + ($isHoliday?$c['holiday']*5:0)
                            + (($coverageTypes[$date][$code][$typeById[$uid]]??0)*4); // Balance personnel categories within a shift
                        $candidates[]=['id'=>$uid,'score'=>$score];
                    }
                    usort($candidates,static fn($a,$b)=>($a['score']<=>$b['score']) ?: ($a['id']<=>$b['id']));
                    if(!$candidates) { $unfilled[]=['date'=>$date,'shift'=>$code,'message'=>'บุคลากรไม่เพียงพอหรือไม่ผ่านเงื่อนไขการพัก']; continue; }
                    $uid=$candidates[0]['id']; $byDay[$date][$uid]=$code;
                    $kind=$typeById[$uid]; $coverageTypes[$date][$code][$kind]=($coverageTypes[$date][$code][$kind]??0)+1;
                    $counts[$uid]['work']++;
                    $counts[$uid][$code]++;
                    if($code==='NIGHT') $counts[$uid]['night']++;
                    if($isWeekend) $counts[$uid]['weekend']++;
                    if($isHoliday) $counts[$uid]['holiday']++;
                    $suggestions[]=['user_id'=>$uid,'date'=>$date,'shift_type_id'=>(int)$shiftCodes[$code]['id']];
                }
            }
        }
        // Fill unassigned calendar cells with explicit OFF, retaining preexisting data unchanged.
        foreach(range(1,$days) as $d) {
            $date=sprintf('%04d-%02d-%02d',$roster['year'],$roster['month'],$d);
            foreach($staff as $person) {
                $uid=(int)$person['id'];
                if(isset($byDay[$date][$uid])) continue;
                $byDay[$date][$uid]='OFF';
                $suggestions[]=['user_id'=>$uid,'date'=>$date,'shift_type_id'=>(int)$shiftCodes['OFF']['id']];
            }
        }
        // Preview metrics reflect the projected end state, including shifts already entered.
        // No database write occurs in plan(). Apply re-plans under the roster revision check.
        $shiftTotals=['MORNING'=>0,'AFTERNOON'=>0,'NIGHT'=>0,'OFF'=>0,'LEAVE'=>0];
        $distribution=[];
        foreach($staff as $person) {
            $uid=(int)$person['id'];
            $distribution[$uid]=['user_id'=>$uid,'name'=>$person['full_name'],
                'MORNING'=>0,'AFTERNOON'=>0,'NIGHT'=>0,'OFF'=>0,'LEAVE'=>0,'working'=>0];
        }
        $daily=[];
        foreach(range(1,$days) as $d) {
            $date=sprintf('%04d-%02d-%02d',$roster['year'],$roster['month'],$d);
            $dailyCounts=['MORNING'=>0,'AFTERNOON'=>0,'NIGHT'=>0];
            foreach($byDay[$date] ?? [] as $uid=>$code) {
                if(isset($shiftTotals[$code])) $shiftTotals[$code]++;
                if(isset($distribution[$uid][$code])) {
                    $distribution[$uid][$code]++;
                    if(in_array($code,['MORNING','AFTERNOON','NIGHT'],true)) $distribution[$uid]['working']++;
                }
                if(isset($dailyCounts[$code]) && isset($counts[$uid])) $dailyCounts[$code]++;
            }
            $daily[]=['date'=>$date,'counts'=>$dailyCounts,
                'shortage'=>array_sum(array_map(static fn($code)=>max(0,$limits[$code]-$dailyCounts[$code]),array_keys($limits)))];
        }
        $restIssues=ShiftRestRules::conflicts($byDay,$shiftCodes,$minimumRest);
        $workCounts=array_column($distribution,'working');
        $nightCounts=array_column($distribution,'NIGHT');
        return [
            'changes'=>$suggestions,'unfilled'=>$unfilled,
            'working_assigned'=>count($suggestions)-count(array_filter($suggestions,static fn($x)=>$x['shift_type_id']===(int)$shiftCodes['OFF']['id'])),
            'total_changes'=>count($suggestions),
            'preview'=>[
                'staff_count'=>count($staff),
                'days'=>$days,
                'minimum_per_day'=>$limits,
                'totals'=>$shiftTotals,
                'working_range'=>['min'=>$workCounts?min($workCounts):0,'max'=>$workCounts?max($workCounts):0],
                'night_range'=>['min'=>$nightCounts?min($nightCounts):0,'max'=>$nightCounts?max($nightCounts):0],
                'per_person'=>array_values($distribution),
                'daily'=>$daily,
                'coverage_complete'=>count($unfilled)===0,
                'minimum_rest_hours'=>$minimumRest,
                'rest_issues'=>$restIssues,
                'rest_safe'=>count($restIssues)===0,
                'skill_requirements_configured'=>count($requirements),
            ],
        ];
    }
}
