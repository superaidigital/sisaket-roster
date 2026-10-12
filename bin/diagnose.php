<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
require dirname(__DIR__).'/app/Core/bootstrap.php';
use App\Core\Database;
$failed=0;
function check(string $label,bool $valid,string $hint=''): void {
    global $failed;
    echo ($valid?'[OK] ':'[FAIL] ').$label.($hint?' — '.$hint:'').PHP_EOL;
    if(!$valid)$failed++;
}
check('PHP >= 8.0',PHP_VERSION_ID>=80000,PHP_VERSION);
foreach(['pdo_mysql','mbstring','zip','openssl'] as $ext)check('Extension '.$ext,extension_loaded($ext));
check('config.php exists',is_file(dirname(__DIR__).'/config.php'));
if(!is_file(dirname(__DIR__).'/config.php'))exit(1);
try{
    $c=config();check('Change example DB password',($c['db']['password']??'')!=='REPLACE_WITH_DATABASE_PASSWORD');
    check('Change IP hash secret',strlen($c['app']['ip_hash_secret']??'')>=32 && $c['app']['ip_hash_secret']!=='REPLACE_WITH_A_LONG_RANDOM_SECRET');
    $db=Database::pdo();check('DB connect',true);
    $tables=['hospitals','users','shift_types','rosters','roster_entries','roster_versions','roster_validation_results','roster_audit_logs','holidays','roster_settings'];
    foreach($tables as $t){$st=$db->query('SHOW TABLES LIKE '.$db->quote($t));check('Table '.$t,(bool)$st->fetchColumn());}
    $shiftCount=(int)$db->query("SELECT COUNT(*) FROM shift_types WHERE code IN ('MORNING','AFTERNOON','NIGHT','OFF','LEAVE') AND is_active=1")->fetchColumn();
    check('Required shifts (5)',$shiftCount===5);
    $h=(int)$db->query('SELECT COUNT(*) FROM hospitals WHERE is_active=1')->fetchColumn();
    check('Active hospital count > 0',$h>0);
}catch(Throwable $e){check('Database health',false,$e->getMessage());}
echo $failed===0?"Environment OK\n":"Found {$failed} issue(s)\n";
exit($failed===0?0:1);
