<?php
declare(strict_types=1);
// Database-free checks. Run: php tests/static_test.php
$root=dirname(__DIR__);
$required=['database/schema.sql','database/seed.sql','app/Core/bootstrap.php','app/Core/Database.php','app/Core/Auth.php','app/Core/Csrf.php',
    'app/Models/RosterModel.php','app/Services/RosterValidator.php','app/Services/AutoScheduler.php','app/Services/ExcelExporter.php',
    'app/Controllers/AuthController.php','app/Controllers/RosterController.php','app/Views/auth/login.php',
    'app/Views/roster/index.php','app/Views/roster/print.php','public/index.php','public/js/roster.js','public/css/roster.css',
    'bin/create_user.php','bin/diagnose.php','bin/setup_local_admin.php','bin/setup_admin_local.bat','app/Views/auth/change_password.php','database/migrations/20261012_add_password_change.sql','START_HERE_ADMIN.txt','UPGRADE_V2.md','config.example.php','README.md','index.php','bin/.htaccess','docs/.htaccess','tests/.htaccess'];
$errors=[];
foreach($required as $f) if(!is_file($root.'/'.$f)) $errors[]="Missing file: {$f}";
$sql=file_get_contents($root.'/database/schema.sql');
foreach(['UNIQUE KEY uq_roster_person_day','UNIQUE KEY uq_roster_version','ENGINE=InnoDB','roster_audit_logs','roster_validation_results'] as $keyword)
    if(!str_contains($sql,$keyword))$errors[]="Missing SQL constraint: {$keyword}";
$auth=file_get_contents($root.'/app/Models/RosterModel.php');
if(!str_contains($auth,"role NOT IN ('SUPERADMIN','ADMIN','HR')") && !str_contains($auth,"role NOT IN (\\'SUPERADMIN\\',\\'ADMIN\\',\\'HR\\')"))$errors[]='Exclusion filter not found';
if(!str_contains($sql,'must_change_password'))$errors[]='Password change flag not in schema';
foreach(['app/Controllers/AuthController.php'=>'do_change_password','app/Core/Auth.php'=>'password_change_required','public/index.php'=>"'change_password'",'bin/setup_local_admin.php'=>'password_verify'] as $filename=>$needle){
    if(!str_contains(file_get_contents($root.'/'.$filename),$needle))$errors[]='Missing forced password change handling: '.$filename;
}
echo $errors?implode(PHP_EOL,$errors).PHP_EOL:'Static integrity checks passed.'.PHP_EOL;
exit($errors?1:0);
