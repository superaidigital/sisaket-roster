<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\RosterModel;

/** V9: read-only best-effort same-day shift swap search.
 * Preserves daily shift counts, explicit OFF/LEAVE, notes, inactive accounts and locked rosters.
 * No random suggestions; only strictly fewer Critical with zero newly-created Critical/Warning issues.
 * Never writes to MySQL. Apply re-derives the entire plan and uses saveCells optimistic locking.
 */
final class RosterRepairPlanner {
    public static function plan(array $roster): array {
        return self::suggest($roster,RosterModel::entries((int)$roster['id']),
            RosterModel::staff((int)$roster['hospital_id']),RosterModel::shifts(),
            RosterModel::settings((int)$roster['hospital_id']),7000,
            RosterModel::requirements((int)$roster['hospital_id']),RosterModel::staffSkills((int)$roster['hospital_id']));
    }
    private static function issueKeys(array $validation,string $type): array {
        $set=[];
        foreach($validation['items'] as $i) {
            if($i['severity']!==$type)continue;
            $key=$i['rule_code'].'|'.($i['user_id']??''). '|'.($i['work_date']??'');
            $set[$key]=true;
        }
        return $set;
    }
    private static function noNewIssues(array $before,array $after): bool {
        foreach(['CRITICAL','WARNING'] as $kind) {
            $present=self::issueKeys($before,$kind);
            foreach(self::issueKeys($after,$kind) as $key=>$_)if(!isset($present[$key]))return false;
        }
        return true;
    }
    private static function fillShift(array $entry,array $shift): array {
        foreach(['id'=>'shift_type_id','code'=>'code','name'=>'shift_name','short_name'=>'short_name',
            'is_working'=>'is_working','start_time'=>'start_time','end_time'=>'end_time'] as $source=>$target) {
            if(array_key_exists($source,$shift))$entry[$target]=$shift[$source];
        }
        return $entry;
    }
    private static function shiftCount(array $entries): array {
        $totals=[];
        foreach($entries as $entry){$key=$entry['work_date'].'|'.$entry['code'];$totals[$key]=($totals[$key]??0)+1;}
        ksort($totals);
        return $totals;
    }
    /** Pure planner entry point for tests and deterministic previews. */
    public static function suggest(array $roster,array $entries,array $staff,array $shifts,array $settings,int $limit=7000,array $requirements=[],array $skills=[]): array {
        $shiftByCode=[];$shiftById=[];
        foreach($shifts as $shift){$shiftByCode[(string)$shift['code']]=$shift;$shiftById[(int)$shift['id']]=$shift;}
        $eligible=[];
        foreach($staff as $person)$eligible[(int)$person['id']]=(string)$person['full_name'];
        $original=[];$dates=[];$indexes=[];
        foreach($entries as $i=>$e){
            $key=(int)$e['user_id'].'|'.$e['work_date'];$original[$key]=$e;
            if(!isset($eligible[(int)$e['user_id']]))continue;
            if(!in_array($e['code'],['MORNING','AFTERNOON','NIGHT','OFF'],true)
                || !isset($shiftById[(int)$e['shift_type_id']]))continue;
            if(trim((string)($e['note']??''))!=='')continue; // Never silently move an annotated duty.
            $dates[$e['work_date']][(int)$e['user_id']]=$i;
        }
        $baseline=RosterValidator::assess($roster,$entries,$staff,$shifts,$settings,$requirements,$skills);
        $initial=$baseline['counts'];$current=$baseline;$swaps=[];$evaluations=0;
        $first=sprintf('%04d-%02d-01',(int)$roster['year'],(int)$roster['month']);
        $last=date('Y-m-t',strtotime($first));
        $maxSwaps=min(50,max(1,count($staff)*4));
        for($iteration=0;$iteration<$maxSwaps && $evaluations<$limit;$iteration++) {
            if($current['counts']['CRITICAL']===0)break;
            $focus=[];
            foreach($current['items'] as $issue) {
                if($issue['severity']!=='CRITICAL' || empty($issue['user_id']) || empty($issue['work_date']))continue;
                $uid=(int)$issue['user_id'];$when=$issue['work_date'];
                if(isset($dates[$when][$uid]))$focus[$when][$uid]=true;
                $prior=date('Y-m-d',strtotime($when.' -1 day'));
                if($prior >= $first && $prior <= $last && isset($dates[$prior][$uid]))$focus[$prior][$uid]=true;
            }
            ksort($focus);$best=null;$bestValidation=null;$bestEntries=null;$bestPenalty=PHP_INT_MAX;
            foreach($focus as $date=>$affected) {
                $people=$dates[$date]; ksort($people);
                foreach($affected as $uid=>$_) {
                    $aIdx=$people[$uid];$one=$entries[$aIdx];
                    foreach($people as $partner=>$bIdx) {
                        if($uid===$partner)continue;
                        $two=$entries[$bIdx];
                        if($one['code']===$two['code'])continue;
                        if($evaluations >= $limit)break 3;
                        $candidate=$entries;
                        $candidate[$aIdx]=self::fillShift($one,$shiftById[(int)$two['shift_type_id']]);
                        $candidate[$bIdx]=self::fillShift($two,$shiftById[(int)$one['shift_type_id']]);
                        $assessed=RosterValidator::assess($roster,$candidate,$staff,$shifts,$settings,$requirements,$skills);
                        $evaluations++;
                        if($assessed['counts']['CRITICAL'] >= $current['counts']['CRITICAL'] || !self::noNewIssues($current,$assessed))continue;
                        $improvement=$current['counts']['CRITICAL']-$assessed['counts']['CRITICAL'];
                        // A tie prefers fewer changed slots relative to the original schedule.
                        $penalty=0;
                        foreach([$candidate[$aIdx],$candidate[$bIdx]] as $entry){
                            $key=(int)$entry['user_id'].'|'.$entry['work_date'];
                            if((int)$entry['shift_type_id'] !== (int)$original[$key]['shift_type_id'])$penalty++;
                        }
                        if($best===null || $improvement>$best['improvement'] ||
                          ($improvement===$best['improvement'] && $penalty<$bestPenalty)) {
                            $best=['date'=>$date,'user_a'=>(int)$uid,'user_b'=>(int)$partner,'improvement'=>$improvement];
                            $bestValidation=$assessed;$bestEntries=$candidate;$bestPenalty=$penalty;
                        }
                    }
                }
            }
            if($best===null)break;
            $oldA=$entries[$dates[$best['date']][$best['user_a']]];
            $oldB=$entries[$dates[$best['date']][$best['user_b']]];
            $newA=$bestEntries[$dates[$best['date']][$best['user_a']]];
            $newB=$bestEntries[$dates[$best['date']][$best['user_b']]];
            $swaps[]=['date'=>$best['date'],'first'=>[
                'user_id'=>$best['user_a'],'name'=>$eligible[$best['user_a']],
                'from'=>$oldA['code'],'to'=>$newA['code'],'from_name'=>(string)$shiftByCode[$oldA['code']]['name'],'to_name'=>(string)$shiftByCode[$newA['code']]['name']],
                'second'=>['user_id'=>$best['user_b'],'name'=>$eligible[$best['user_b']],
                'from'=>$oldB['code'],'to'=>$newB['code'],'from_name'=>(string)$shiftByCode[$oldB['code']]['name'],'to_name'=>(string)$shiftByCode[$newB['code']]['name']]];
            $entries=$bestEntries;$current=$bestValidation;
        }
        $changes=[];
        foreach($entries as $e){
            $key=(int)$e['user_id'].'|'.$e['work_date'];
            if((int)$e['shift_type_id']===(int)$original[$key]['shift_type_id'])continue;
            $changes[]=['user_id'=>(int)$e['user_id'],'date'=>$e['work_date'],
                'shift_type_id'=>(int)$e['shift_type_id'],'note'=>(string)($e['note']??'')];
        }
        usort($changes,static fn($a,$b)=>strcmp($a['date'],$b['date']) ?: ($a['user_id']<=>$b['user_id']));
        if(self::shiftCount($entries)!==self::shiftCount(array_values($original))) {
            throw new \LogicException('การสลับเวรต้องไม่เปลี่ยนอัตรากำลังรายวัน');
        }
        $revision=(int)($roster['lock_version']??0);
        $hash=hash('sha256',json_encode([(int)$roster['id'],$revision,$changes,$requirements,$skills],JSON_UNESCAPED_UNICODE));
        $remaining=array_values(array_filter($current['items'],static fn($i)=>$i['severity']==='CRITICAL'));
        return ['before'=>$initial,'after'=>$current['counts'],'swaps'=>$swaps,'changes'=>$changes,
            'preview_hash'=>$hash,'evaluations'=>$evaluations,'remaining'=>$remaining,
            'coverage_preserved'=>true,'partial'=>count($remaining)>0,
            'notice'=>'ข้อเสนอช่วยลด Critical เท่านั้น ต้องตรวจคุณสมบัติวิชาชีพ/ภารกิจของบุคลากรและระยะพักข้ามเดือนด้วย'];
    }
}
