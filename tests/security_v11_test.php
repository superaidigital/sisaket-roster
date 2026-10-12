<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Services/SecurityPolicy.php';
require dirname(__DIR__).'/app/Services/WorkflowPolicy.php';
use App\Services\SecurityPolicy as S;
use App\Services\WorkflowPolicy as W;

$checks=0;
function checkV11(bool $condition,string $label):void{
    global $checks;$checks++;
    if(!$condition){fwrite(STDERR,"FAIL: {$label}\n");exit(1);}
}
$roles=['SUPERADMIN','ADMIN','SCHEDULER','DIRECTOR','STAFF','HR','UNKNOWN'];
$statuses=['DRAFT','PENDING_APPROVAL','APPROVED','PUBLISHED','LOCKED'];
foreach($roles as $role){
    foreach([1,2] as $hospital){
        $manager=in_array($role,['SUPERADMIN','ADMIN'],true);
        $known=$role!=='UNKNOWN';
        checkV11(S::hospitalAllowed($role,1,$hospital)===($known&&($manager||$hospital===1)),"tenant {$role} {$hospital}");
    }
    checkV11(!S::hospitalAllowed($role,0,1),'invalid owner '.$role);
    checkV11(!S::hospitalAllowed($role,1,0),'invalid target '.$role);
    checkV11(S::canManage($role)===in_array($role,['SUPERADMIN','ADMIN'],true),'admin '.$role);
    checkV11(S::canViewVersionHistory($role)===in_array($role,['SUPERADMIN','ADMIN','SCHEDULER','DIRECTOR'],true),'history '.$role);
    checkV11(S::canViewMySchedule($role)===in_array($role,['STAFF','SCHEDULER','DIRECTOR'],true),'mine '.$role);
    foreach($statuses as $status){
        $read=$role!=='UNKNOWN'&&($role!=='STAFF'||in_array($status,['PUBLISHED','LOCKED'],true));
        checkV11(S::rosterReadable($role,$status)===$read,"read {$role} {$status}");
        $edit=in_array($role,['SUPERADMIN','ADMIN','SCHEDULER'],true)&&$status==='DRAFT';
        checkV11(S::rosterEditable($role,$status)===$edit,"write {$role} {$status}");
        // Actual state machine authorization must match its transitions.
        foreach(['SUBMIT','APPROVE','REJECT','PUBLISH','LOCK'] as $action){
            $step=W::step($action);
            $expected=$step['from']===$status&&in_array($role,$step['roles'],true);
            checkV11(W::can($action,$role,$status)===$expected,"workflow {$role} {$status} {$action}");
        }
    }
}
checkV11(!S::rosterEditable('SUPERADMIN','LOCKED'),'even superadmin cannot edit locked');
checkV11(!S::rosterReadable('STAFF','DRAFT'),'staff cannot read draft');
checkV11(!S::hospitalAllowed('SCHEDULER',1,2),'scheduler cannot cross hospital');
checkV11(!S::hospitalAllowed('DIRECTOR',1,2),'director cannot cross hospital');
checkV11(!S::hospitalAllowed('STAFF',1,2),'staff cannot cross hospital');
checkV11(S::rosterReadable('STAFF','LOCKED'),'staff can view locked published version');
$source=file_get_contents(dirname(__DIR__).'/app/Models/RosterModel.php');
$controller=file_get_contents(dirname(__DIR__).'/app/Controllers/RosterController.php');
$auth=file_get_contents(dirname(__DIR__).'/app/Core/Auth.php');
checkV11(str_contains($source,'SELECT * FROM rosters WHERE id=? FOR UPDATE'),'version source locked');
checkV11(str_contains($source,"(int)$"."source['lock_version']!==".'$expectedRevision'),'version revision checked under lock');
checkV11(str_contains($source,"AND status IN ('PUBLISHED','LOCKED')"),'staff version list filtered');
checkV11(str_contains($controller,'SecurityPolicy::rosterReadable'),'HTTP roster read guard');
checkV11(str_contains($controller,'SecurityPolicy::rosterEditable'),'HTTP roster edit guard');
checkV11(str_contains($controller,"Auth::hospitalId(\$u,(int)\$r['hospital_id'])"),'HTTP roster tenant guard');
checkV11(str_contains($auth,'SecurityPolicy::hospitalAllowed'),'role+tenant central guard');
checkV11(str_contains($controller,'$this->checkRevision($r,$in)'),'revision checked at HTTP layer');
checkV11(str_contains($controller,'$this->versionViewer($user)'),'history permission guard');
echo "PASS V11 Authorization & Workflow: {$checks} assertions (unit+source checks).\n";
