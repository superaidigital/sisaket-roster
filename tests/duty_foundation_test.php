<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Services/DutyPolicy.php';
use App\Services\DutyPolicy as D;

$checks=0;
function ok(bool $condition,string $label): void {
    global $checks;
    $checks++;
    if(!$condition){fwrite(STDERR,"FAIL: $label\n");exit(1);}
}
function fails(callable $fn,string $label): void {
    try{$fn();}catch(InvalidArgumentException $e){ok(true,$label);return;}
    ok(false,$label);
}
[$s,$e]=D::interval('2026-10-15','ร');
ok($s==='2026-10-15 20:31:00'&&$e==='2026-10-16 08:29:00','on call ends tomorrow');
[$s,$e]=D::interval('2026-10-15','บ');
ok($s==='2026-10-15 16:31:00'&&$e==='2026-10-15 20:30:00','afternoon');
fails(fn()=>D::interval('2026-02-30','ย'),'reject invalid date');
fails(fn()=>D::interval('2026-10-15','X'),'reject invalid type');
ok(D::overlaps('2026-10-15 16:31:00','2026-10-15 20:30:00',
'2026-10-15 20:29:00','2026-10-16 08:29:00'),'overlap');
ok(!D::overlaps('2026-10-15 16:31:00','2026-10-15 20:30:00',
'2026-10-15 20:30:00','2026-10-16 08:29:00'),'touching boundaries');
$c=['user_id'=>1,'start_at'=>'2026-10-15 16:31:00','end_at'=>'2026-10-15 20:30:00'];
$r=[['id'=>11,'user_id'=>1,'start_at'=>'2026-10-15 17:00:00','end_at'=>'2026-10-15 18:00:00','status'=>'ACTIVE']];
ok(D::validateAssignment($c,$r)===['TIME_OVERLAP'],'same person collision');
$r[0]['user_id']=2;
ok(D::validateAssignment($c,$r)===[],'other person allowed');
$r[0]['user_id']=1;$r[0]['status']='CANCELLED';
ok(D::validateAssignment($c,$r)===[],'cancelled omitted');
ok(D::canMove('DRAFT','PENDING_CONSENT'),'valid transition');
ok(!D::canMove('DRAFT','APPROVED'),'skip approval blocked');
ok(!D::canMove('APPROVED','DRAFT'),'approved immutable');
$rate=['approval_status'=>'APPROVED','effective_from'=>'2026-10-01',
'approved_by'=>1,'approved_at'=>'2026-10-01 10:00:00',
'source_reference'=>'ORDER-TEST','rate_unit'=>'PER_HOUR','rate_amount'=>'80.00'];
ok(D::compensation('บ',$rate,true)==='320.00','group1 afternoon');
$rate['rate_amount']='60.00';
ok(D::compensation('บ',$rate,true)==='240.00','group2 afternoon');
$rate['rate_unit']='PER_SHIFT';$rate['rate_amount']='160.00';
ok(D::compensation('บ',$rate,true)==='160.00','group3 afternoon');
$rate['rate_amount']='0.00';
ok(D::compensation('ร',$rate,true)==='0.00','ineligible on call zero');
$rate['approval_status']='DRAFT';
fails(fn()=>D::compensation('บ',$rate,true),'draft rate never payable');
$rate['approval_status']='APPROVED';
fails(fn()=>D::compensation('บ',$rate,false),'no actual-work verification');
$rate['rate_amount']='-10.00';
fails(fn()=>D::compensation('บ',$rate,true),'negative rate');
echo "PASS Duty foundation pure policy: $checks assertions; NO database accessed.\n";
