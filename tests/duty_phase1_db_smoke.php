<?php
declare(strict_types=1);
/**
 * Read-only smoke tests for V11 schema + Phase 1 migration.
 * Run ONLY on an isolated disposable test DB, with explicit environment variables.
 * Tests presence, FK integrity, and 9 DRAFT default rates without changing data.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
$host=getenv('TEST_DB_HOST') ?: '127.0.0.1';
$name=getenv('TEST_DB_NAME') ?: '';
$user=getenv('TEST_DB_USER') ?: '';
$pass=getenv('TEST_DB_PASSWORD');
if ($name !== 'sisaket_roster_ci' || $user === '') {
    fwrite(STDERR,"SAFETY STOP: requires TEST_DB_NAME=sisaket_roster_ci and TEST_DB_USER.\n");
    exit(2);
}
$pdo=new PDO("mysql:host={$host};port=3306;dbname={$name};charset=utf8mb4",$user,$pass===false?'':$pass,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false,
]);
$checks=0;
function check(bool $ok,string $msg): void {
    global $checks;
    $checks++;
    if (!$ok) { fwrite(STDERR,"FAIL: ".$msg."\n");exit(1); }
}
$tabs=$pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()")->fetchAll(PDO::FETCH_COLUMN);
$set=array_fill_keys($tabs,true);
$expected=[
  'hospitals','users','shift_types','rosters','roster_entries','holidays',
  'duty_types','compensation_groups','compensation_rates','staff_compensation_groups',
  'duty_rosters','duty_assignments','approval_delegations','shift_swaps',
  'swap_approvals','leave_types','leave_requests','payroll_runs','payroll_logs','duty_audit_events'
];
foreach($expected as $t) check(isset($set[$t]),"required table ".$t);
$types=$pdo->query("SELECT code,starts_at,ends_at,end_day_offset,billable_minutes FROM duty_types ORDER BY FIELD(code,'ย','บ','ร')")->fetchAll();
check(count($types)===3,'exactly 3 duty types');
$expectedTimes=[
 'ย'=>['08:30:00','16:30:00',0,0],
 'บ'=>['16:31:00','20:30:00',0,240],
 'ร'=>['20:31:00','08:29:00',1,0],
];
foreach($types as $type){
    check(isset($expectedTimes[$type['code']]),'unexpected code');
    check([$type['starts_at'],$type['ends_at'],(int)$type['end_day_offset'],(int)$type['billable_minutes']]
        ===$expectedTimes[$type['code']],"times ".$type['code']);
}
$rates=$pdo->query("SELECT cr.group_id,dt.code,cr.rate_unit,cr.rate_amount,cr.approval_status
  FROM compensation_rates cr JOIN duty_types dt ON dt.id=cr.duty_type_id
  WHERE cr.rule_version='BP-20261015-DRAFT'")->fetchAll();
check(count($rates)===9,'9 proposed default rates');
$expectedRates=[
 '1:ย'=>['PER_SHIFT','650.00'],'1:บ'=>['PER_HOUR','80.00'],'1:ร'=>['PER_SHIFT','150.00'],
 '2:ย'=>['PER_SHIFT','520.00'],'2:บ'=>['PER_HOUR','60.00'],'2:ร'=>['PER_SHIFT','0.00'],
 '3:ย'=>['PER_SHIFT','330.00'],'3:บ'=>['PER_SHIFT','160.00'],'3:ร'=>['PER_SHIFT','0.00']
];
foreach($rates as $r){
    $key=$r['group_id'].':'.$r['code'];
    check(isset($expectedRates[$key]),'unexpected rate key');
    check([$r['rate_unit'],$r['rate_amount']]===$expectedRates[$key],$key.' amount/unit');
    check($r['approval_status']==='DRAFT','rates must remain DRAFT');
}
$fk=$pdo->query("SELECT TABLE_NAME,REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL")->fetchAll();
$fkCount=0;
foreach($fk as $x) if($x['TABLE_NAME']==='duty_assignments'||$x['TABLE_NAME']==='compensation_rates') $fkCount++;
check($fkCount>=7,'foreign keys for assignments and rates');
check((int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn()===0,'no real users in CI database');
echo "PASS: {$checks} assertions; MySQL8 Phase1 base + migration. READ ONLY, no data changed.\n";
