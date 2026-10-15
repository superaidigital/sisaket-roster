<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Services/DutyDocumentPreviewService.php';
use App\Services\DutyDocumentPreviewService as Docs;
$n=0;
function ok(bool $v,string $m):void {global $n;$n++;if(!$v){fwrite(STDERR,"FAIL: $m\n");exit(1);}}
ok(Docs::thaiDigits('102/2569')==='๑๐๒/๒๕๖๙','Thai digits');
ok(Docs::dateThai('2026-10-15')==='๑๕ ตุลาคม ๒๕๖๙','Memo digits');
ok(Docs::dateThai('2026-10-15',true)==='15 ตุลาคม 2569','Swap Arabic');
ok(Docs::monthTitle(2026,10)==='ตุลาคม ๒๕๖๙','Memo month');
ok(Docs::monthTitle(2026,10,true)==='ตุลาคม 2569','Annex month');
$staff=[['id'=>1,'full_name'=>'ทดสอบ','position'=>'พยาบาล'],['id'=>2,'full_name'=>'ตัวอย่าง','position'=>'เจ้าหน้าที่']];
$assign=[
 ['user_id'=>1,'work_date'=>'2026-10-01','code'=>'ย'],
 ['user_id'=>1,'work_date'=>'2026-10-01','code'=>'บ'],
 ['user_id'=>1,'work_date'=>'2026-10-01','code'=>'ร'],
 ['user_id'=>1,'work_date'=>'2026-10-01','code'=>'ย'],
 ['user_id'=>1,'work_date'=>'2026-10-02','code'=>'บ','status'=>'CANCELLED'],
 ['user_id'=>2,'work_date'=>'2026-10-03','code'=>'บ'],
 ['user_id'=>2,'work_date'=>'2026-11-03','code'=>'ร']
];
$x=Docs::grid(2026,10,$staff,$assign);
ok($x['days_in_month']===31,'31-day October');
ok(count($x['rows'])===2,'2 staff');
ok($x['rows'][0]['cells'][1]==='ย/บ/ร','multi duty same date');
ok($x['rows'][0]['total']===3,'deduplicated');
ok($x['rows'][1]['total']===1,'other month excluded');
ok($x['rows'][1]['cells'][3]==='บ','second employee');
$y=Docs::grid(2026,2,$staff,[]);
ok($y['days_in_month']===28,'February');
ok($y['rows'][0]['cells'][29]==='','February phantom days');
echo "PASS: $n document preview assertions. No DB calls.\n";
