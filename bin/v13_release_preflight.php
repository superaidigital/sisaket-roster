<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Core/bootstrap.php';

use App\Core\Database;
use PDO;

/**
 * V13 release-candidate readiness. Read-only; never alters user/roster/payroll
 * records. Prints counts and gaps without exposing names, leave reasons or keys.
 */
function result(string $name,bool $ok,string $detail=''):void {
    global $errors;
    echo ($ok?'[OK]  ':'[FAIL] ').$name.($detail!==''?' — '.$detail:'').PHP_EOL;
    if(!$ok)$errors++;
}
$errors=0;
echo "=== SISAKET V13 RELEASE CANDIDATE — READ ONLY ===\n";
try{
    $cfg=config();
    $app=$cfg['app']??[];
    $database=$cfg['db']??[];
    result('Dedicated session name',($app['session_name']??'')!=='SSK_ROSTER_SID' && strlen((string)($app['session_name']??''))>=8,'Avoid collisions on localhost');
    result('IP hash secret configured',strlen((string)($app['ip_hash_secret']??''))>=32 && !str_starts_with((string)($app['ip_hash_secret']??''),'REPLACE_'));
    result('Sensitive-data encryption key',preg_match('/^[0-9a-f]{64}$/iD',(string)($app['sensitive_data_key']??''))===1,'Required for leave/swap reasons');
    $db=Database::pdo();
    result('Database connection',true);
    $tables=['hospitals','users','rosters','roster_entries','shift_types',
        'duty_types','duty_rosters','duty_assignments','duty_audit_events',
        'approval_delegations','leave_requests','shift_swaps','swap_approvals',
        'compensation_rates','payroll_runs','payroll_logs',
        'duty_rate_change_proposals','duty_rate_audit_events'];
    $q=$db->query('SELECT DATABASE()');
    echo 'Database: '.(string)$q->fetchColumn().PHP_EOL;
    $q=$db->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1');
    foreach($tables as $table){$q->execute([$table]);result('Table '.$table,(bool)$q->fetchColumn());}
    $q=$db->query("SELECT COUNT(*) AS count FROM duty_rosters WHERE status='SUBMITTED'");
    echo 'Awaiting V13 review: '.(int)$q->fetchColumn().PHP_EOL;
    $q=$db->prepare("SELECT COUNT(*) FROM approval_delegations WHERE approval_scope=? AND effective_from<=CURDATE() AND (effective_to IS NULL OR effective_to>=CURDATE()) AND order_reference<>'' AND evidence_uri<>''");
    $q->execute([\App\Services\DutyReviewPolicy::SCOPE]);
    $delegations=(int)$q->fetchColumn();
    result('Documented current duty approvals',$delegations>0,'Valid delegation records: '.$delegations);
    foreach(['LEAVE_APPROVAL'=>'leave decisions','SHIFT_SWAP_REVIEW'=>'swap endorsements'] as $scope=>$label){
        $q->execute([$scope]);
        $count=(int)$q->fetchColumn();
        result('Documented '.$label,$count>0,'Valid records: '.$count);
    }
    echo "NOTE: A valid delegation record still needs legal verification of source order and authority.\n";
    echo "NOTE: Successful preflight is NOT evidence of clinical staffing review, authorization, or payment approval.\n";
}catch(Throwable $e){
    result('Read-only preflight could not complete',false,$e instanceof PDOException?'Database or migration unavailable':$e->getMessage());
}
echo "Issues found: $errors; no rows changed\n";
exit($errors===0?0:1);
