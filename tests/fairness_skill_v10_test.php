<?php
declare(strict_types=1);
namespace App\Models {
    final class RosterModel {}
}
namespace {
    use App\Services\{SkillPolicy,RosterValidator,FairnessAnalyzer,FairnessBalancePlanner};
    require dirname(__DIR__).'/app/Services/SkillPolicy.php';
    require dirname(__DIR__).'/app/Services/ShiftRestRules.php';
    require dirname(__DIR__).'/app/Services/RosterValidator.php';
    require dirname(__DIR__).'/app/Services/FairnessAnalyzer.php';
    require dirname(__DIR__).'/app/Services/FairnessBalancePlanner.php';
    $checks=0;
    function check(bool $truth,string $label):void {global $checks;$checks++;if(!$truth)throw new \RuntimeException('FAIL: '.$label);}
    $shifts=[
      ['id'=>1,'code'=>'MORNING','name'=>'เช้า','short_name'=>'ช','start_time'=>'08:00:00','end_time'=>'16:00:00','is_working'=>1],
      ['id'=>2,'code'=>'OFF','name'=>'หยุด','short_name'=>'OFF','start_time'=>null,'end_time'=>null,'is_working'=>0],
    ];
    $staff=[['id'=>1,'full_name'=>'A','is_active'=>1,'role'=>'STAFF'],['id'=>2,'full_name'=>'B','is_active'=>1,'role'=>'STAFF']];
    $settings=['min_morning'=>0,'min_afternoon'=>0,'min_night'=>0,'max_consecutive_work_days'=>6,'max_night_shifts'=>8,'min_rest_hours'=>11];
    $roster=['id'=>2,'hospital_id'=>1,'year'=>2026,'month'=>10,'lock_version'=>3,'status'=>'DRAFT'];
    $entries=[];
    foreach([1,2] as $d) foreach([1,2] as $uid){
        $s=$shifts[$uid===1?0:1];
        $entries[]=['user_id'=>$uid,'work_date'=>sprintf('2026-10-%02d',$d),
          'shift_type_id'=>$s['id'],'code'=>$s['code'],'is_working'=>$s['is_working'],
          'start_time'=>$s['start_time'],'end_time'=>$s['end_time'],'note'=>''];
    }
    $req=[['shift_type_id'=>1,'skill_code'=>'RN','min_count'=>1]];
    $onlyA=[['user_id'=>1,'skill_code'=>'RN']];
    $both=[['user_id'=>1,'skill_code'=>'RN'],['user_id'=>2,'skill_code'=>'RN']];
    check(SkillPolicy::index($onlyA)[1]['RN']===true,'explicit verified skill');
    check(!SkillPolicy::mayAssign(2,'2026-10-01',1,[],['MORNING'=>$shifts[0]],$req,SkillPolicy::index($onlyA),1,0),'unverified user cannot fill mandatory skill');
    check(SkillPolicy::mayAssign(1,'2026-10-01',1,[],['MORNING'=>$shifts[0]],$req,SkillPolicy::index($onlyA),1,0),'verified user may fill mandatory skill');
    $r=RosterValidator::assess($roster,$entries,$staff,$shifts,$settings,$req,[]);
    check(count(array_filter($r['items'],static fn($i)=>str_starts_with($i['rule_code'],'SKILL_COVERAGE_')))===31,'skill coverage catches 31 missing days including empty calendar days');
    $r=RosterValidator::assess($roster,$entries,$staff,$shifts,$settings,$req,$onlyA);
    check(count(array_filter($r['items'],static fn($i)=>str_starts_with($i['rule_code'],'SKILL_COVERAGE_')))===29,'verified RN covers assigned days only');
    // Skill coverage must be complete over the entire period before balance can be suggested.
    $plan=FairnessBalancePlanner::suggest($roster,$entries,$staff,$shifts,$settings,$req,$onlyA,200);
    check(!$plan['changes'],'fairness cannot sacrifice skill coverage');
    $plan=FairnessBalancePlanner::suggest($roster,$entries,$staff,$shifts,$settings,[],[],200);
    check(count($plan['changes'])===2,'one balanced same-day swap changes 2 cells without skill requirements');
    check($plan['work_gap_before']===2 && $plan['work_gap_after']===0,'fairness reduces work gap 2 to 0');
    check($plan['after']['CRITICAL']===0 && $plan['after']['WARNING']===0,'fairness preserves all validation');
    check(strlen($plan['preview_hash'])===64,'revision bound digest');
    $roster['lock_version']=4;
    check(FairnessBalancePlanner::suggest($roster,$entries,$staff,$shifts,$settings,[],[],200)['preview_hash']!==$plan['preview_hash'],'preview changes with revision');
    // All 31 days covered by at least one explicitly verified RN. C is unverified.
    $qualifiedStaff=[...$staff,['id'=>3,'full_name'=>'C','is_active'=>1,'role'=>'STAFF']];
    $month=[];
    for($d=1;$d<=31;$d++)foreach([1,2,3] as $uid){
        $s=($d%2 ? $uid===1 : $uid===2)?$shifts[0]:$shifts[1];
        $month[]=['user_id'=>$uid,'work_date'=>sprintf('2026-10-%02d',$d),'shift_type_id'=>$s['id'],
            'code'=>$s['code'],'is_working'=>$s['is_working'],'start_time'=>$s['start_time'],
            'end_time'=>$s['end_time'],'note'=>''];
    }
    check(RosterValidator::assess($roster,$month,$qualifiedStaff,$shifts,$settings,$req,$both)['counts']['CRITICAL']===0,
        'complete monthly RN coverage');
    $safe=FairnessBalancePlanner::suggest($roster,$month,$qualifiedStaff,$shifts,$settings,$req,$both,400);
    check(!$safe['changes'],'balancing refuses to give sole RN work to unverified C');
    $certAll=[...$both,['user_id'=>3,'skill_code'=>'RN']];
    $safe=FairnessBalancePlanner::suggest($roster,$month,$qualifiedStaff,$shifts,$settings,$req,$certAll,400);
    check($safe['score_after']<$safe['score_before'] && count($safe['changes'])>0,
        'balancing can improve when C skill verified');
    check($safe['after']['CRITICAL']===0 && $safe['after']['WARNING']===0,'balancing keeps certified monthly roster valid');
    $numbers=[['user_id'=>1,'full_name'=>'A','MORNING'=>20,'AFTERNOON'=>0,'NIGHT'=>0,'weekend_shifts'=>4,'holiday_shifts'=>0,'working_hours'=>160],
              ['user_id'=>2,'full_name'=>'B','MORNING'=>15,'AFTERNOON'=>0,'NIGHT'=>0,'weekend_shifts'=>3,'holiday_shifts'=>0,'working_hours'=>120]];
    $fair=FairnessAnalyzer::report($numbers);
    check($fair['metrics']['work']['gap']===5 && $fair['metrics']['hours']['gap']===40.0,'clear workload gap');
    check($fair['needs_review']===true,'uneven workloads are advisory flag');
    echo "V10 skills/fairness tests passed: {$checks} assertions.\n";
}
