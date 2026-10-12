<?php
declare(strict_types=1);
$root=dirname(__DIR__);
require $root.'/app/Core/bootstrap.php';
if(session_status()===PHP_SESSION_NONE)session_start();
$user=['id'=>1,'hospital_id'=>1,'full_name'=>'ผู้ดูแลระบบ','role'=>'SUPERADMIN'];
$hospitals=[['id'=>1,'hospital_code'=>'TEST001','name'=>'รพ.สต. ตัวอย่าง','is_active'=>1]];
$flash=null;
$preview=['hospital_id'=>1,'hospital_name'=>'รพ.สต. ตัวอย่าง','created_at'=>time(),'nonce'=>'testnonce1234',
    'counts'=>['ready'=>1,'skip'=>0,'error'=>0],
    'rows'=>[['line'=>2,'full_name'=>'<script>alert(1)</script>','email'=>'nurse@example.local','role'=>'STAFF','position'=>'พยาบาล','personnel_type'=>'ข้าราชการ','status'=>'ready','issues'=>[]]]];
ob_start();require $root.'/app/Views/admin/staff_import.php';$html=ob_get_clean();
foreach(['<!doctype html>','name="staff_file"','name="hospital_id"','staff_import_confirm','testnonce1234','&lt;script&gt;alert(1)&lt;/script&gt;'] as $text)
    if(!str_contains($html,$text)) throw new RuntimeException('Missing escaped preview view item: '.$text);
if(str_contains($html,'<script>alert(1)</script>')) throw new RuntimeException('XSS injection in preview');
$result=['created'=>1,'skipped'=>0,'credentials'=>[['full_name'=>'นางสาวทดสอบ','email'=>'nurse@example.local','temporary_password'=>'abc-secret-example']]];
ob_start();require $root.'/app/Views/admin/staff_import_result.php';$html=ob_get_clean();
foreach(['<!doctype html>','abc-secret-example','data-reveal-target','data-copy-target'] as $text)
    if(!str_contains($html,$text)) throw new RuntimeException('Missing result view item: '.$text);
echo "Staff import view render / escaping checks passed.\n";
