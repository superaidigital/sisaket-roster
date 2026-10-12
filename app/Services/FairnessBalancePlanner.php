<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\RosterModel;

/** V10 deterministic advisory balancing: same-day WORK/OFF swaps only.
 * Requires zero initial criticals/warnings. Never overrides notes, leave, historical or ineligible users.
 * Full validator (including configured skills) must pass after every projected swap.
 */
final class FairnessBalancePlanner {
    public static function plan(array $roster): array {
        $h=(int)$roster['hospital_id'];
        return self::suggest($roster,RosterModel::entries((int)$roster['id']),RosterModel::staff($h),
            RosterModel::shifts(),RosterModel::settings($h),RosterModel::requirements($h),RosterModel::staffSkills($h));
    }
    private static function stats(array $entries,array $ids): array {
        $counts=[]; foreach($ids as $uid=>$_)$counts[$uid]=['work'=>0,'night'=>0,'weekend'=>0];
        foreach($entries as $e){$uid=(int)$e['user_id'];if(!isset($counts[$uid]))continue;
            if(!(int)$e['is_working'])continue;
            $counts[$uid]['work']++;
            if($e['code']==='NIGHT')$counts[$uid]['night']++;
            if((int)date('N',strtotime($e['work_date']))>=6)$counts[$uid]['weekend']++;
        }
        return $counts;
    }
    private static function score(array $counts): int {
        if(!$counts)return 0;
        $n=count($counts);$score=0;
        foreach(['work'=>10,'night'=>3,'weekend'=>2] as $kind=>$weight){
            $sum=array_sum(array_column($counts,$kind));
            foreach($counts as $c){$d=$n*$c[$kind]-$sum;$score+=$weight*$d*$d;}
        }
        return $score;
    }
    private static function coverage(array $entries): array {
        $r=[];foreach($entries as $e){$key=$e['work_date'].'|'.$e['code'];$r[$key]=($r[$key]??0)+1;}ksort($r);return $r;
    }
    public static function suggest(array $roster,array $entries,array $staff,array $shifts,array $settings,
                                   array $requirements=[],array $skills=[],int $budget=3000): array {
        $ids=[];$eligible=[];
        foreach($staff as $s)if(!empty($s['is_active']) || !array_key_exists('is_active',$s)){
            if(!in_array($s['role']??'STAFF',['SUPERADMIN','ADMIN','HR'],true)){
                $ids[(int)$s['id']]=(string)$s['full_name'];$eligible[(int)$s['id']]=true;
            }
        }
        $shiftsById=[];foreach($shifts as $shift)$shiftsById[(int)$shift['id']]=$shift;
        $before=RosterValidator::assess($roster,$entries,$staff,$shifts,$settings,$requirements,$skills);
        $current=$before;
        $stats=self::stats($entries,$ids);$startingStats=$stats;$startScore=self::score($stats);$score=$startScore;
        $original=[];$dates=[];
        foreach($entries as $i=>$e){
            $key=(int)$e['user_id'].'|'.$e['work_date'];$original[$key]=$e;
            if(!isset($eligible[(int)$e['user_id']]) || trim((string)($e['note']??''))!=='' || !in_array($e['code'],['MORNING','AFTERNOON','NIGHT','OFF'],true))continue;
            $dates[$e['work_date']][(int)$e['user_id']]=$i;
        }
        $swaps=[];$evaluations=0;
        if($before['counts']['CRITICAL']===0 && $before['counts']['WARNING']===0){
            ksort($dates);
            for($pass=0;$pass<10 && $evaluations<$budget;$pass++) {
                $best=null;$bestScore=$score;$bestEntries=null;
                $stats=self::stats($entries,$ids);
                foreach($dates as $date=>$people){
                    foreach($people as $a=>$ai){
                        if(!(int)$entries[$ai]['is_working'])continue;
                        foreach($people as $b=>$bi){
                            if($entries[$bi]['code']!=='OFF'||($stats[$a]['work']-$stats[$b]['work'])<2)continue;
                            if($evaluations>=$budget)break 3;
                            $candidate=$entries;
                            foreach([[$ai,$bi],[$bi,$ai]] as [$idx,$from]) {
                                $entry=$candidate[$idx];$shift=$shiftsById[(int)$entries[$from]['shift_type_id']];
                                foreach(['id'=>'shift_type_id','code'=>'code','name'=>'shift_name','short_name'=>'short_name','is_working'=>'is_working','start_time'=>'start_time','end_time'=>'end_time'] as $src=>$dest)
                                    if(array_key_exists($src,$shift))$entry[$dest]=$shift[$src];
                                $candidate[$idx]=$entry;
                            }
                            $evaluations++;
                            $candidateCounts=self::stats($candidate,$ids);
                            $candidateScore=self::score($candidateCounts);
                            if($candidateScore >= $bestScore)continue;
                            $check=RosterValidator::assess($roster,$candidate,$staff,$shifts,$settings,$requirements,$skills);
                            if($check['counts']['CRITICAL']>0 || $check['counts']['WARNING']>0)continue;
                            $bestScore=$candidateScore;$best=[$date,(int)$a,(int)$b,(int)$ai,(int)$bi];$bestEntries=$candidate;
                        }
                    }
                }
                if($best===null)break;
                [$date,$a,$b,$ai,$bi]=$best;
                $swaps[]=['date'=>$date,'first'=>['user_id'=>$a,'name'=>$ids[$a],
                    'from'=>$entries[$ai]['code'],'to'=>$bestEntries[$ai]['code']],
                    'second'=>['user_id'=>$b,'name'=>$ids[$b],
                    'from'=>$entries[$bi]['code'],'to'=>$bestEntries[$bi]['code']]];
                $entries=$bestEntries;$score=$bestScore;
            }
        }
        $changes=[];
        foreach($entries as $e){$k=(int)$e['user_id'].'|'.$e['work_date'];
            if((int)$e['shift_type_id']!==(int)$original[$k]['shift_type_id'])$changes[]=[
                'user_id'=>(int)$e['user_id'],'date'=>$e['work_date'],
                'shift_type_id'=>(int)$e['shift_type_id'],'note'=>(string)($e['note']??'')];
        }
        usort($changes,static fn($a,$b)=>strcmp($a['date'],$b['date']) ?: $a['user_id']<=>$b['user_id']);
        if(self::coverage($entries)!==self::coverage(array_values($original)))throw new \LogicException('Coverage changed');
        $scoreAfter=self::score(self::stats($entries,$ids));
        $hash=hash('sha256',json_encode([(int)$roster['id'],(int)$roster['lock_version'],$changes,$requirements,$skills],JSON_UNESCAPED_UNICODE));
        return ['changes'=>$changes,'swaps'=>$swaps,'evaluations'=>$evaluations,
            'before'=>$before['counts'],'after'=>RosterValidator::assess($roster,$entries,$staff,$shifts,$settings,$requirements,$skills)['counts'],
            'score_before'=>$startScore,'score_after'=>$scoreAfter,'preview_hash'=>$hash,
            'work_gap_before'=>self::gap($startingStats,'work'),'work_gap_after'=>self::gap(self::stats($entries,$ids),'work'),
            'note'=>'การปรับสมดุลเป็นข้อเสนอ ไม่แทนการตรวจความเหมาะสมของเวร ทักษะ จำนวนชั่วโมงตามสัญญา และการหมุนเวียนข้ามเดือน'];
    }
    private static function gap(array $counts,string $column): int {
        if(!$counts)return 0;$numbers=array_column($counts,$column);return max($numbers)-min($numbers);
    }
}
