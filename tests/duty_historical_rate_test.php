<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Services/DutyPolicy.php';
require dirname(__DIR__).'/app/Services/DutyHistoricalRate.php';
use App\Services\DutyHistoricalRate as R;
$n=0;
function ok(bool $v,string $m):void{global $n;$n++;if(!$v){fwrite(STDERR,"FAIL: ".$m."\n");exit(1);}}
function rejected(callable $fn,string $m):void{try{$fn();}catch(\RuntimeException|\InvalidArgumentException $e){ok(true,$m);return;}ok(false,$m);}
$g=['user_id'=>7,'group_id'=>1,'effective_from'=>'2026-01-01','effective_to'=>null,'verified_by'=>9,'verified_at'=>'2026-01-01 12:00:00','evidence_reference'=>'HR-record'];
$r=['id'=>13,'group_id'=>1,'duty_type_id'=>2,'approval_status'=>'APPROVED','effective_from'=>'2026-01-01','effective_to'=>'2026-12-31','approved_by'=>9,'approved_at'=>'2026-01-01 12:00:00','source_reference'=>'signed-order','rule_version'=>'v1','rate_unit'=>'PER_HOUR','rate_amount'=>'80.00'];
$out=R::preview([$g],[$r],7,2,'2026-10-15','บ',true);
ok($out['preview_amount']==='320.00','4 hour compensation preview');
ok($out['payable']===false,'preview never authorizes payment');
rejected(fn()=>R::preview([$g],[$r],7,2,'2026-10-15','บ',false),'attendance verification');
rejected(fn()=>R::select([array_merge($r,['approval_status'=>'DRAFT'])],1,2,'2026-10-15'),'no draft rate');
rejected(fn()=>R::select([$r],1,2,'2027-02-01'),'date outside approval');
rejected(fn()=>R::select([$r,$r],1,2,'2026-10-15'),'overlapping approved rate blocked');
rejected(fn()=>R::staffGroup([$g,$g],7,'2026-10-15'),'overlapping groups blocked');
rejected(fn()=>R::staffGroup([$g],8,'2026-10-15'),'other staff prohibited');
rejected(fn()=>R::select([$r],1,2,'2026-02-31'),'invalid date');
$zero=array_merge($r,['rate_unit'=>'PER_SHIFT','rate_amount'=>'0.00']);
ok(R::preview([$g],[$zero],7,2,'2026-10-15','บ',true)['preview_amount']==='0.00','zero approved rate honored');
echo "PASS $n historical finance preview assertions (database-free)\n";
