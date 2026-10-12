<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Services/AuditLogPresenter.php';
use App\Services\AuditLogPresenter;
$checks=0;
function verify7(bool $condition,string $description): void {
    global $checks;
    if(!$condition){fwrite(STDERR,"FAILED V7: {$description}\n");exit(1);}
    $checks++;
}
$shifts=[1=>'เช้า',2=>'บ่าย',3=>'ดึก',4=>'วันหยุด'];
$people=[2=>'บุคลากรทดสอบ 01'];
$rows=[
 ['id'=>1,'action'=>'UPDATE_SHIFT','old_value'=>'{"shift_type_id":3,"note":""}','new_value'=>'{"user_id":2,"shift_type_id":2,"note":""}'],
 ['id'=>2,'action'=>'UPDATE_SHIFT','old_value'=>'{"shift_type_id":2,"note":""}','new_value'=>'{"user_id":2,"shift_type_id":3,"note":""}'],
 ['id'=>3,'action'=>'DELETE_SHIFT','old_value'=>'{"shift_type_id":4,"note":""}','new_value'=>'{"user_id":2,"shift_type_id":null,"note":""}'],
 ['id'=>4,'action'=>'CREATE_REVISION','old_value'=>'{"version":1}','new_value'=>'{"version":2,"reason":"ทดลองแก้ไข"}'],
 ['id'=>5,'action'=>'PUBLISH','old_value'=>'{"status":"APPROVED"}','new_value'=>'{"status":"PUBLISHED"}'],
 ['id'=>6,'action'=>'UPDATE_SHIFT','old_value'=>'bad-json','new_value'=>'{"user_id":99,"shift_type_id":999,"note":"<script>alert(1)</script>"}'],
 ['id'=>7,'action'=>'UPDATE_SHIFT','old_value'=>null,'new_value'=>'{"user_id":2,"shift_type_id":1,"note":""}'],
];
$out=AuditLogPresenter::present($rows,$shifts,$people);
verify7(count($out)===7,'all events retained');
verify7($out[0]['display']['before']==='ดึก' && $out[0]['display']['after']==='บ่าย','first shift changed night to afternoon');
verify7($out[1]['display']['before']==='บ่าย' && $out[1]['display']['after']==='ดึก','second shift changed afternoon to night');
verify7($out[0]['display']['staff_name']==='บุคลากรทดสอบ 01','staff label');
verify7($out[2]['display']['before']==='วันหยุด' && $out[2]['display']['after']==='ไม่ได้จัดเวร','delete shift label');
verify7($out[3]['display']['before']==='Version 1' && $out[3]['display']['after']==='Version 2','revision display');
verify7($out[3]['display']['reason']==='ทดลองแก้ไข','version reason');
verify7($out[4]['display']['before']==='APPROVED' && $out[4]['display']['after']==='PUBLISHED','workflow display');
verify7($out[5]['display']['before']==='ไม่ได้จัดเวร' && $out[5]['display']['after']==='ประเภทเวร #999','malformed or inactive shift support');
verify7($out[5]['display']['after_note']==='<script>alert(1)</script>','raw data must survive for safe escaping');
verify7($out[6]['display']['before']==='ไม่ได้จัดเวร' && $out[6]['display']['after']==='เช้า','first assignment');
verify7($out[0]['old_value']===$rows[0]['old_value'],'audit source unchanged');
$root=dirname(__DIR__);
$controller=file_get_contents($root.'/app/Controllers/RosterController.php');
$model=file_get_contents($root.'/app/Models/RosterModel.php');
$js=file_get_contents($root.'/public/js/roster.js');
verify7(str_contains($controller,'AuditLogPresenter::present('),'API formatted server side');
verify7(str_contains($controller,"M::latest(\$hospital,\$year,\$month,true)"),'my schedule latest published');
verify7(str_contains($controller,"(int)\$entry['user_id']===(int)\$user['id']"),'my schedule only current login');
verify7(str_contains($controller,"SecurityPolicy::canViewVersionHistory"),'history privileged users only');
verify7(str_contains($model,"AND status IN ('PUBLISHED','LOCKED')"),'published-only SQL in model');
verify7(str_contains($js,"safe(d.before||'—')") && str_contains($js,"safe(d.after||'—')"),'audit names HTML escaped');
verify7(str_contains($js,'safe(original)') && str_contains($js,'safe(updated)'),'audit raw JSON HTML escaped');
// View rendering with hostile names and notes: no XSS in Thai day card markup.
if (!function_exists('e')) {function e(?string $s): string {return htmlspecialchars($s??'',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}}
$user=['id'=>2,'full_name'=>'<script>alert(1)</script>'];$h=['name'=>'<img src=x onerror=alert(1)>'];
$year=2026;$month=10;$days=31;$r=['status'=>'LOCKED','version'=>1];
$entries=[['work_date'=>'2026-10-07','code'=>'NIGHT','shift_name'=>'ดึก','is_working'=>1,'start_time'=>'00:00:00','end_time'=>'08:00:00','note'=>'<img src=x onerror=alert(1)>']];
ob_start();require $root.'/app/Views/roster/my_schedule.php';$html=(string)ob_get_clean();
verify7(str_contains($html,'&lt;script&gt;alert(1)&lt;/script&gt;'),'escape staff name');
verify7(str_contains($html,'&lt;img src=x onerror=alert(1)&gt;'),'escape notes/hospital');
verify7(!str_contains($html,'<script>alert(1)</script>'),'no raw script injection');
verify7(str_contains($html,'aria-label="วันพุธที่ 7'),'accessible day label');
verify7(str_contains($html,'2026-10-07')===false,'no unnecessary raw date leak in public html');
verify7(str_contains($html,'min-height:44px'),'touch target');
verify7(str_contains($html,'grid-template-columns:1fr;gap:9px'),'mobile day list');
verify7(str_contains($html,'Version 1'),'published version label');
echo "V7 audit/mobile tests passed: {$checks} assertions.\n";
