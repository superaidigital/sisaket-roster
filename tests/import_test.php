<?php
declare(strict_types=1);
/** CSV parser and import-validation smoke tests runnable without a database. */
if (!function_exists('mb_check_encoding')) {
    function mb_check_encoding(string $v,string $charset='UTF-8'): bool { return preg_match('//u',$v)===1; }
}
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $v,?string $charset='UTF-8'): int { return iconv_strlen($v,$charset?:'UTF-8'); }
}
if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $v,?string $charset='UTF-8'): string { return strtolower($v); } // Email/roles are ASCII in smoke tests.
}
require dirname(__DIR__).'/app/Services/StaffSpreadsheetReader.php';
require dirname(__DIR__).'/app/Services/StaffImportService.php';
use App\Services\StaffSpreadsheetReader;
use App\Services\StaffImportService;

function expect(bool $test,string $message): void { if(!$test) throw new RuntimeException($message); }
$path=tempnam(sys_get_temp_dir(),'roster_import_');
file_put_contents($path,"\xEF\xBB\xBFfull_name,email,role,position,personnel_type\r\n\"นางสาว ทดสอบ\",nurse@example.local,staff,พยาบาล,ข้าราชการ\r\n\n");
try {
    $rows=StaffSpreadsheetReader::read($path,'sample.csv');
    expect(count($rows)===1,'expected 1 nonblank row');
    expect($rows[0]['line']===2,'expected line number 2');
    expect($rows[0]['full_name']==='นางสาว ทดสอบ','Thai CSV UTF-8 parse failed');
    expect($rows[0]['role']==='staff','reader should preserve text until normalization');
    $rm=new ReflectionMethod(StaffImportService::class,'sanitize');
    $clean=$rm->invoke(null,$rows[0]);
    expect($clean['role']==='STAFF','role canonicalization failed');
    $validation=new ReflectionMethod(StaffImportService::class,'validateRow');
    expect($validation->invoke(null,$clean)===[],'valid row rejected');
    $bad=$clean; $bad['role']='SUPERADMIN';
    expect(count($validation->invoke(null,$bad))>0,'forbidden role accepted');
    $bad=$clean; $bad['email']='invalid-email';
    expect(count($validation->invoke(null,$bad))>0,'invalid email accepted');
    $bad=$clean; $bad['full_name']='=A1';
    expect(count($validation->invoke(null,$bad))>0,'formula-looking name accepted');
    echo "Staff import CSV / role validation smoke tests passed.\n";
} finally { unlink($path); }
