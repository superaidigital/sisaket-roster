<?php
declare(strict_types=1);
$root=dirname(__DIR__);
require $root.'/app/Core/bootstrap.php';
if(session_status()===PHP_SESSION_NONE)session_start();
$user=['id'=>1,'hospital_id'=>1,'full_name'=>'ผู้ดูแลระบบ','role'=>'SUPERADMIN'];
$hospitals=[['id'=>1,'hospital_code'=>'SSK-DEMO-001','name'=>'ศูนย์ตัวอย่าง','district'=>'เมือง','subdistrict'=>'ทดสอบ','is_active'=>1,'active_users'=>1,'roster_count'=>0]];
$counts=[['hospital_id'=>1,'name'=>'ศูนย์ตัวอย่าง','staff_count'=>0]];
$flash=null;
function renderFile(string $file,array $context): string {
    extract($context,EXTR_SKIP);ob_start(); require $file; return ob_get_clean();
}
$common=compact('user','hospitals','counts','flash');
$tests=[
    ['app/Views/admin/dashboard.php',$common,['ศูนย์จัดการข้อมูลตารางเวร','จัดการบุคลากร','จัดการหน่วยบริการ']],
    ['app/Views/admin/hospitals.php',$common,['SSK-DEMO-001','เพิ่ม รพ.สต.']],
    ['app/Views/admin/hospital_form.php',$common+['record'=>null],['hospital_code','csrf_token']],
    ['app/Views/admin/staff.php',$common+['staff'=>[],'hospitalId'=>0,'search'=>'','tempPassword'=>null],['เพิ่มบุคลากร','ไม่พบบุคลากร']],
    ['app/Views/admin/staff_form.php',$common+['record'=>null,'roles'=>['STAFF','SCHEDULER','DIRECTOR']],['full_name','hospital_id','csrf_token']],
];
$errors=[];
foreach($tests as [$file,$context,$needles]) {
    $html=renderFile($root.'/'.$file,$context);
    foreach($needles as $needle) if(!str_contains($html,$needle))$errors[]="$file missing '$needle'";
    if(!str_contains($html,'<!doctype html>'))$errors[]="$file missing html layout";
}
$render=renderFile($root.'/app/Views/admin/staff.php',$common+['staff'=>[['id'=>5,'full_name'=>'<script>alert(1)</script>','email'=>'person@example.org','position'=>'','personnel_type'=>'','hospital_name'=>'ศูนย์ตัวอย่าง','hospital_id'=>1,'role'=>'STAFF','is_active'=>1,'must_change_password'=>0]],'hospitalId'=>0,'search'=>'','tempPassword'=>'AbCd1234-test-5678']);
if(str_contains($render,'<script>alert(1)</script>'))$errors[]='XSS output was not escaped';
if(!str_contains($render,'&lt;script&gt;alert(1)&lt;/script&gt;'))$errors[]='XSS test text not encoded';
if(!str_contains($render,'AbCd1234-test-5678'))$errors[]='Temporary password not rendered';
if($errors){echo implode(PHP_EOL,$errors).PHP_EOL;exit(1);}
echo "Management view render checks passed.\n";
