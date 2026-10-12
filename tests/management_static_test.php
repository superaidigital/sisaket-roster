<?php
declare(strict_types=1);
$root=dirname(__DIR__);
require $root.'/app/Core/bootstrap.php';
$files=['app/Controllers/AdminController.php','app/Models/AdminModel.php','app/Views/admin/dashboard.php','app/Views/admin/hospitals.php','app/Views/admin/hospital_form.php','app/Views/admin/staff.php','app/Views/admin/staff_form.php','app/Views/admin/_head.php','app/Views/admin/_foot.php','public/css/admin.css','public/js/admin.js','UPGRADE_V2.md'];
$issues=[];
foreach($files as $file) if(!is_file($root.'/'.$file))$issues[]='Missing '.$file;
$router=file_get_contents($root.'/public/index.php');
foreach(['hospital_save','staff_save','staff_reset_password'] as $action) if(!str_contains($router,"'".$action."'"))$issues[]='Missing route '.$action;
$controller=file_get_contents($root.'/app/Controllers/AdminController.php');
foreach(['Auth::requireUser','Csrf::check','requirePost','SUPERADMIN','staff_reset_password'] as $needle) if(!str_contains($controller,$needle))$issues[]='Missing guard '.$needle;
$model=file_get_contents($root.'/app/Models/AdminModel.php');
foreach(['password_hash','random_bytes','beginTransaction','RosterModel::audit','FOR UPDATE','staffCount'] as $needle) {
    // method is staffCounts (case-sensitive check below)
    if(!str_contains($model,$needle))$issues[]='Missing model feature '.$needle;
}
$staffView=file_get_contents($root.'/app/Views/admin/staff.php');
if(!str_contains($staffView,'tempPassword'))$issues[]='Missing one-time password UI';
if(!str_contains($model,"u.role<>\\'SUPERADMIN\\'") && !str_contains($model,"u.role<>'SUPERADMIN'"))$issues[]='SUPERADMIN management exclusion not enforced';
if(!str_contains(file_get_contents($root.'/app/Models/RosterModel.php'),"role NOT IN ('SUPERADMIN','ADMIN','HR')") &&
    !str_contains(file_get_contents($root.'/app/Models/RosterModel.php'),"role NOT IN (\\'SUPERADMIN\\',\\'ADMIN\\',\\'HR\\')"))$issues[]='Staff pool role exclusion lost';
if($issues){ echo implode(PHP_EOL,$issues).PHP_EOL;exit(1); }
echo "Management static checks passed.\n";
