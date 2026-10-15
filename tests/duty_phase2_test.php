<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Services/DutyPolicy.php';
use App\Services\DutyPolicy as Policy;
$checks=0;
function dutyCheck(bool $condition,string $name):void{
 global $checks;$checks++;
 if(!$condition){fwrite(STDERR,"FAIL: $name\n");exit(1);}
}
$root=dirname(__DIR__);
$router=file_get_contents($root.'/public/index.php');
$controller=file_get_contents($root.'/app/Controllers/DutyController.php');
$service=file_get_contents($root.'/app/Services/DutyRosterService.php');
$view=file_get_contents($root.'/app/Views/duty/index.php');
dutyCheck(str_contains($router,"'duty'=>['index','data','create','save','validate','submit']"),'strict routes');
dutyCheck(str_contains($router,'DutyController'),'controller dispatch');
dutyCheck(str_contains($controller,'Csrf::check()'),'CSRF');
dutyCheck(str_contains($controller,'Auth::hospitalId'),'tenant validation');
dutyCheck(str_contains($controller,"['SUPERADMIN','ADMIN','SCHEDULER']"),'editor RBAC');
dutyCheck(str_contains($controller,"\$u['role']==='STAFF'"),'staff published filter');
dutyCheck(str_contains($service,'FOR UPDATE'),'transaction locks');
dutyCheck(str_contains($service,"'DRAFT'"),'draft write guard');
dutyCheck(str_contains($service,'revision'), 'optimistic version');
dutyCheck(str_contains($service,'leave_requests'),'leave conflict');
dutyCheck(str_contains($service,'roster_entries'),'V11 cross-roster overlap');
dutyCheck(str_contains($service,'duty_audit_events'),'audit');
dutyCheck(str_contains($service,'DutyPolicy::overlaps'),'overlap validation');
$dutyJs=file_get_contents($root.'/public/js/duty-roster-v13.js');
dutyCheck(str_contains($dutyJs,'data-active') && str_contains($dutyJs,'aria-pressed'),'accessible state toggles');
dutyCheck(!str_contains($controller,'payroll'),'no payroll payout route');
dutyCheck(!str_contains($router,"'ot'=>"),'legacy OT V12 route remains absent');
[$a,$b]=Policy::interval('2026-10-15','ร');
dutyCheck($a==='2026-10-15 20:31:00' && $b==='2026-10-16 08:29:00','on call overnight');
echo "PASS Duty Phase 2 source and pure policy: {$checks} assertions; DB NOT ACCESSED.\n";
