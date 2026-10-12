<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Core/bootstrap.php';
use App\Services\WorkflowPolicy;
use App\Services\RosterReadiness;

$checks=0;
$assert=static function(bool $okay,string $label) use (&$checks):void {
    $checks++;
    if(!$okay){fwrite(STDERR,"FAILED: $label\n"); exit(1);}
};
$assert(WorkflowPolicy::can('SUBMIT','SCHEDULER','DRAFT'),'Scheduler submits draft');
$assert(!WorkflowPolicy::can('SUBMIT','STAFF','DRAFT'),'Staff cannot submit');
$assert(!WorkflowPolicy::can('SUBMIT','SCHEDULER','PUBLISHED'),'Published cannot submit');
$assert(WorkflowPolicy::can('APPROVE','DIRECTOR','PENDING_APPROVAL'),'Director approves');
$assert(!WorkflowPolicy::can('APPROVE','ADMIN','PENDING_APPROVAL'),'Admin cannot approve');
$assert(WorkflowPolicy::can('PUBLISH','ADMIN','APPROVED'),'Admin publishes');
$assert(WorkflowPolicy::can('LOCK','DIRECTOR','PUBLISHED'),'Director locks');
$assert(!WorkflowPolicy::can('LOCK','DIRECTOR','LOCKED'),'Locked immutable');
$assert(WorkflowPolicy::can('REJECT','DIRECTOR','PENDING_APPROVAL'),'Director rejects');
$assert(WorkflowPolicy::available('STAFF','DRAFT')===[],'Staff no transitions');
$staff=[['eligible'=>true],['eligible'=>true],['eligible'=>false]];
$shifts=array_map(static fn($c)=>['code'=>$c],['MORNING','AFTERNOON','NIGHT','OFF']);
$settings=['min_morning'=>1,'min_afternoon'=>1,'min_night'=>0];
$roster=['status'=>'DRAFT'];
$good=RosterReadiness::assess($roster,$staff,$shifts,$settings,['counts'=>['CRITICAL'=>0,'WARNING'=>2]],5,'SCHEDULER');
$assert($good['ready'] && $good['warning']===2 && $good['eligible_staff']===2,'Readiness yes');
$bad=RosterReadiness::assess($roster,$staff,$shifts,$settings,['counts'=>['CRITICAL'=>3,'WARNING'=>0]],5,'SCHEDULER');
$assert(!$bad['ready'] && $bad['critical']===3,'Critical blocks readiness');
$empty=RosterReadiness::assess(null,[],$shifts,$settings,null,0,'SUPERADMIN');
$assert(!$empty['ready'] && $empty['status']==='EMPTY','No roster blocks');
$short=RosterReadiness::assess($roster,$staff,$shifts,['min_morning'=>2,'min_afternoon'=>2,'min_night'=>2],['counts'=>['CRITICAL'=>0,'WARNING'=>0]],100,'SUPERADMIN');
$assert(!$short['ready'],'Staffing shortfall blocks');
echo "V4 workflow/readiness tests passed: {$checks} assertions\n";
