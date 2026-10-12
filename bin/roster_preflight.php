<?php
declare(strict_types=1);
/** Read-only diagnostic: php bin/roster_preflight.php SSK-DEMO-001 2026 10 */
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require dirname(__DIR__).'/app/Core/bootstrap.php';
use App\Core\Database;
use App\Models\RosterModel;
use App\Services\RosterValidator;
use App\Services\RosterReadiness;
$code=trim((string)($argv[1]??''));
$year=filter_var($argv[2]??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>2000,'max_range'=>2100]]);
$month=filter_var($argv[3]??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>12]]);
if($code==='' || !$year || !$month){fwrite(STDERR,"Usage: php bin/roster_preflight.php HOSPITAL_CODE YEAR_AD MONTH\nExample: php bin/roster_preflight.php SSK-DEMO-001 2026 10\n");exit(2);}
try {
    $q=Database::pdo()->prepare('SELECT id,name FROM hospitals WHERE hospital_code=? AND is_active=1');
    $q->execute([$code]);$hospital=$q->fetch();
    if(!$hospital){fwrite(STDERR,"Hospital not found or inactive\n");exit(2);}
    $id=(int)$hospital['id'];
    $roster=RosterModel::latest($id,(int)$year,(int)$month,false);
    $staff=RosterModel::rosterStaff($id,$roster?(int)$roster['id']:null);
    $entries=$roster?RosterModel::entries((int)$roster['id']):[];
    $validation=$roster?RosterValidator::run($roster):null;
    $result=RosterReadiness::assess($roster,$staff,RosterModel::shifts(),RosterModel::settings($id),$validation,count($entries),'SUPERADMIN');
    echo "Hospital: {$hospital['name']} [{$code}]\nMonth: {$year}-".str_pad((string)$month,2,'0',STR_PAD_LEFT)."\n";
    foreach(['status','eligible_staff','required_per_day','saved_entries','critical','warning','ready','next_step'] as $key) {
        $value=$result[$key]; echo $key.': '.(is_bool($value)?($value?'YES':'NO'):(string)$value)."\n";
    }
    foreach($result['issues'] as $issue)echo '- '.$issue."\n";
    exit($result['ready']?0:1);
} catch(Throwable $e){fwrite(STDERR,'Diagnostic error: '.$e->getMessage()."\n");exit(2);}
