<?php
declare(strict_types=1);

/**
 * Phase 2 local development preflight. CLI ONLY, read-only, never prints passwords
 * or raw PDO exception messages, never inserts/migrates user data.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }

require dirname(__DIR__).'/app/Core/bootstrap.php';

use App\Core\Database;

$failed = 0;
function checkpoint(string $label, bool $ok, string $hint = ''): void {
    global $failed;
    echo ($ok ? '[OK] ' : '[FAIL] ' ).$label.($hint !== '' ? ' — '.$hint : '').PHP_EOL;
    if (!$ok) $failed++;
}
echo "=== Sisaket Duty Phase 2 / local READ-ONLY preflight ===\n";

checkpoint('PHP >= 8.0', PHP_VERSION_ID >= 80000, PHP_VERSION);
checkpoint('PDO MySQL enabled', extension_loaded('pdo_mysql'));
$file=dirname(__DIR__).'/config.php';
if(!is_file($file)) {
    checkpoint('Local config.php',false,'Copy config.example.php and edit locally; do not upload to GitHub.');
    exit(1);
}
try {
    $config=config();
    $dbConfig=$config['db'] ?? [];
    $safeName=(string)($dbConfig['name'] ?? '');
    $safePort=(int)($dbConfig['port'] ?? 0);
    echo "Configured database: ".preg_replace('/[^a-zA-Z0-9_]/','?', $safeName)." / port ".$safePort."\n";
    checkpoint('Database credentials configured',
        !empty($dbConfig['user']) &&
        ($dbConfig['password'] ?? '') !== 'REPLACE_WITH_DATABASE_PASSWORD',
        'Do not paste or publish passwords.');
    $session=(string)($config['app']['session_name'] ?? 'SSK_ROSTER_SID');
    checkpoint('Phase 2 unique session cookie',
        $session !== 'SSK_ROSTER_SID' && preg_match('/^[A-Za-z][A-Za-z0-9_]{7,50}$/D',$session)===1,
        'Set app.session_name to SSK_DUTY_PHASE2_SID to avoid V11 localhost cookie conflict.');

    $pdo=Database::pdo();
    checkpoint('MySQL connection',true);

    $required=['hospitals','users','shift_types','rosters','roster_entries',
       'holidays','duty_types','duty_rosters','duty_assignments',
       'leave_requests','duty_audit_events','compensation_rates'];
    $stmt=$pdo->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()');
    $present=array_fill_keys(array_map('strtolower',$stmt->fetchAll(\PDO::FETCH_COLUMN)),true);
    foreach($required as $table){
        checkpoint('Table '.$table,isset($present[strtolower($table)]),
            isset($present[strtolower($table)])?'':'Missing; check DEV database and Phase 1 migration.');
    }
    if(isset($present['users'])){
        $pdo->query('SELECT id,hospital_id,full_name,email,role,is_active FROM users LIMIT 0');
        checkpoint('V11 user columns readable',true);
        // Match AuthController::do_login SELECT without exposing any account details.
        $pdo->query('SELECT id,password_hash,is_active,must_change_password FROM users LIMIT 0')->closeCursor();
        checkpoint('Login SELECT columns readable',true,'Required by auth&a=do_login');
        $users=(int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        checkpoint('User accounts exist',$users>0,'count '.$users);
    }
    if(isset($present['hospitals'])){
        $n=(int)$pdo->query('SELECT COUNT(*) FROM hospitals WHERE is_active=1')->fetchColumn();
        checkpoint('Active hospitals exist',$n>0,'count '.$n);
    }
    if(isset($present['compensation_rates'])){
        $n=(int)$pdo->query("SELECT COUNT(*) FROM compensation_rates WHERE approval_status='DRAFT'")->fetchColumn();
        echo "[INFO] Proposed DRAFT compensation rates: ".$n." (no payroll authorization implied)\n";
    }
} catch (PDOException $e){
    $driver=(int)($e->errorInfo[1] ?? 0);
    $code=(string)$e->getCode();
    $hint=match (true) {
        $driver===1045 => 'MySQL rejected username/password. Check config.php db.user/password.',
        $driver===1049 => 'Configured database does not exist; create/restore a separate DEV database.',
        $driver===2002 || $driver===2003 => 'MySQL service/host/port unreachable; start MySQL in XAMPP and check db.host/port.',
        $driver===1146 => 'Table missing. Import Phase 1 migration into selected DEV database.',
        $driver===1044 || $driver===1142 => 'Database account lacks required SELECT permission.',
        $code==='42S02' => 'Table not found; verify migration and selected DEV database.',
        default => 'Check XAMPP MySQL service, config DB name/credentials, DEV schema and Apache/PHP logs.'
    };
    checkpoint('Database check',false,$hint.' (SQLSTATE '.$code.')');
} catch (Throwable $e) {
    checkpoint('Preflight',false,'Unexpected configuration/schema issue; consult local logs (do not share passwords).');
}
echo $failed===0 ? "Preflight OK (read-only; no data changed)\n" : "Found $failed issue(s) (no data changed)\n";
exit($failed===0 ? 0 : 1);
