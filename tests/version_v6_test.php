<?php
declare(strict_types=1);
require_once __DIR__.'/../app/Services/VersionDiff.php';
use App\Services\VersionDiff;
$assert=0;
function test(bool $ok,string $message): void {
    global $assert;
    if(!$ok){fwrite(STDERR,"FAIL: {$message}\n");exit(1);} $assert++;
}
$before=[
 ['user_id'=>7,'work_date'=>'2026-10-07','code'=>'NIGHT','shift_name'=>'ดึก','note'=>''],
 ['user_id'=>8,'work_date'=>'2026-10-07','code'=>'MORNING','shift_name'=>'เช้า','note'=>''],
 ['user_id'=>9,'work_date'=>'2026-10-08','code'=>'OFF','shift_name'=>'หยุด','note'=>'A'],
];
$after=[
 ['user_id'=>7,'work_date'=>'2026-10-07','code'=>'AFTERNOON','shift_name'=>'บ่าย','note'=>''],
 ['user_id'=>9,'work_date'=>'2026-10-08','code'=>'OFF','shift_name'=>'หยุด','note'=>'A'],
 ['user_id'=>10,'work_date'=>'2026-10-07','code'=>'MORNING','shift_name'=>'เช้า','note'=>''],
];
$diff=VersionDiff::compare($before,$after,[7=>'ทดสอบ 07',8=>'ทดสอบ 08',10=>'ทดสอบ 10']);
test($diff['total']===3,'changed, removed, added');
test($diff['summary']===['added'=>1,'removed'=>1,'changed'=>1],'counts');
$by=[];foreach($diff['changes'] as $c)$by[$c['user_id']]=$c;
test($by[7]['before']['shift_code']==='NIGHT' && $by[7]['after']['shift_code']==='AFTERNOON','detect actual shift');
test($by[8]['type']==='removed' && $by[8]['after']===null,'removed');
test($by[10]['type']==='added' && $by[10]['before']===null,'added');
test(VersionDiff::compare($after,array_reverse($after))['total']===0,'order independent');
$noteEdit=$after; $noteEdit[0]['note']='<script>alert(1)</script>';
test(VersionDiff::compare($after,$noteEdit)['total']===1,'note-only change');
for($i=1;$i<=31;$i++){
    $base=[['user_id'=>1,'work_date'=>sprintf('2026-10-%02d',$i),'code'=>'NIGHT','note'=>'']];
    $new=$base;$new[0]['code']='OFF';
    test(VersionDiff::compare($base,$new)['total']===1,"day {$i}");
}
$root=dirname(__DIR__);
$controller=file_get_contents($root.'/app/Controllers/RosterController.php');
$route=file_get_contents($root.'/public/index.php');
$view=file_get_contents($root.'/app/Views/roster/index.php');
foreach(['version_history','version_compare','version_audit','my_schedule'] as $action) {
    test(str_contains($controller,"function {$action}()"),"controller {$action}");
    test(str_contains($route,"'{$action}'"),"route {$action}");
}
test(str_contains($controller,"SecurityPolicy::canViewVersionHistory"),'management-only history');
test(str_contains($controller,"M::latest(\$hospital,\$year,\$month,true)"),'my schedule published-only');
test(str_contains($controller,"(int)\$entry['user_id']===(int)\$user['id']"),'no other user in my schedule');
test(str_contains($view,'id="historyBtn"'),'history button');
test(str_contains(file_get_contents($root.'/app/Models/RosterModel.php'),"$".'currentMax!=='), 'version branching guard');
echo "V6 tests passed: {$assert} assertions.\n";
