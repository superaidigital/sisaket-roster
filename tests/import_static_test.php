<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$router=file_get_contents($root.'/public/index.php');
$controller=file_get_contents($root.'/app/Controllers/AdminController.php');
$reader=file_get_contents($root.'/app/Services/StaffSpreadsheetReader.php');
$service=file_get_contents($root.'/app/Services/StaffImportService.php');
$view=file_get_contents($root.'/app/Views/admin/staff_import.php');
foreach(['staff_import','staff_import_preview','staff_import_confirm','staff_import_result'] as $s) {
    if(!str_contains($router,"'".$s."'")) throw new RuntimeException('Missing route '.$s);
    if(!str_contains($controller,'function '.$s.'(')) throw new RuntimeException('Missing action '.$s);
}
foreach(['Csrf::check','is_uploaded_file','StaffSpreadsheetReader::read','StaffImportService::commit','nonce'] as $needle)
    if(!str_contains($controller,$needle)) throw new RuntimeException('Missing guard '.$needle);
foreach(['ZipArchive','MAX_ROWS','MAX_XML_BYTES','LIBXML_NONET'] as $needle)
    if(!str_contains($reader,$needle)) throw new RuntimeException('Missing reader protection '.$needle);
foreach(['beginTransaction','password_hash','random_bytes','IMPORT_STAFF','commit','rollBack','ALLOWED_ROLES','existingEmails'] as $needle)
    if(!str_contains($service,$needle)) throw new RuntimeException('Missing import constraint '.$needle);
foreach(['csrf_token','staff_file','hospital_id','staff_import_confirm'] as $needle)
    if(!str_contains($view,$needle)) throw new RuntimeException('Missing import UI '.$needle);
foreach(['roster_staff_import_template.xlsx','roster_staff_import_template.csv'] as $name)
    if(!is_file($root.'/public/templates/'.$name)) throw new RuntimeException('Missing template '.$name);
echo "Staff import static guard checks passed.\n";
