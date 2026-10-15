<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Services/DutyRateProposalPolicy.php';
use App\Services\DutyRateProposalPolicy as P;
$count=0;
function testOk(bool $ok,string $msg):void {global $count;$count++;if(!$ok){fwrite(STDERR,"FAIL ".$msg."\n");exit(1);}}
function rejected(array $data,string $msg):void {try{P::normalize($data);}catch(InvalidArgumentException $e){testOk(true,$msg);return;}testOk(false,$msg);}
$good=[
 'group_id'=>1,'duty_code'=>'บ','rate_unit'=>'PER_HOUR','rate_amount'=>'80',
 'effective_from'=>'2026-10-01','effective_to'=>null,'on_call_pay_eligible'=>false,
 'legal_reference'=>'ORDER TEST','reason'=>'Change requested by test'
];
$out=P::normalize($good);
testOk($out['rate_amount']==='80.00','normalizes integer satang');
testOk($out['group_id']===1,'group');
testOk($out['on_call_pay_eligible']===false,'non on call');
rejected([...$good,'group_id'=>99],'invalid group');
rejected([...$good,'duty_code'=>'X'],'invalid code');
rejected([...$good,'duty_code'=>'ร'],'hourly oncall forbidden');
rejected([...$good,'rate_amount'=>'-1'],'negative');
rejected([...$good,'rate_amount'=>'1.999'],'more than 2 decimals');
rejected([...$good,'rate_amount'=>'10000000'],'amount upper bound');
rejected([...$good,'effective_from'=>'2026-02-30'],'invalid date');
rejected([...$good,'effective_to'=>'2026-09-30'],'date order');
rejected([...$good,'on_call_pay_eligible'=>true],'eligible only oncall');
rejected([...$good,'reason'=>'x'],'missing reason');
rejected([...$good,'on_call_pay_eligible'=>'true'],'type safety');
$out=P::normalize([...$good,'duty_code'=>'ร','rate_unit'=>'PER_SHIFT','rate_amount'=>'150.00','on_call_pay_eligible'=>true]);
testOk($out['on_call_pay_eligible']===true,'eligible oncall');
rejected([...$good,'duty_code'=>'ร','rate_unit'=>'PER_SHIFT','rate_amount'=>'150'],'ineligible positive oncall');
$out=P::normalize([...$good,'duty_code'=>'ร','rate_unit'=>'PER_SHIFT','rate_amount'=>'0']);
testOk($out['rate_amount']==='0.00','zero oncall');
echo "PASS: $count proposal policy assertions. Database not accessed.\n";
