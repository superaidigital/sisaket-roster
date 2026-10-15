<?php
declare(strict_types=1);

/**
 * Sisaket Roster V13 readiness / READ ONLY.
 * Does not CREATE, INSERT, UPDATE, DELETE, ALTER or migrate anything.
 * Usage: C:\xampp\php\php.exe bin\duty_v13_readiness_readonly.php
 */
require dirname(__DIR__) . '/app/Core/bootstrap.php';

$failures = 0;
$warnings = 0;
function reportCheck(string $kind, string $label, string $detail = ''): void
{
    global $failures, $warnings;
    if ($kind === 'FAIL') ++$failures;
    if ($kind === 'WARN') ++$warnings;
    echo '[' . $kind . '] ' . $label . ($detail !== '' ? ' - ' . $detail : '') . PHP_EOL;
}

echo '=== SISAKET ROSTER V13 READ-ONLY CHECK ===' . PHP_EOL;
echo 'No database changes will be made.' . PHP_EOL;

try {
    $cfg = config();
    $dbName = (string)($cfg['db']['name'] ?? '');
    $sessionName = (string)($cfg['app']['session_name'] ?? '');
    reportCheck($dbName === 'sisaket_roster_dev' ? 'OK' : 'FAIL',
        'DEV database selected', $dbName !== '' ? $dbName : '(empty)');
    reportCheck($sessionName === 'SSK_DUTY_PHASE2_SID' ? 'OK' : 'FAIL',
        'Session separated from V11', $sessionName !== '' ? $sessionName : '(empty)');
    reportCheck(extension_loaded('pdo_mysql') ? 'OK' : 'FAIL', 'pdo_mysql installed');
    if (!extension_loaded('pdo_mysql')) exit(2);

    $pdo = \App\Core\Database::pdo();
    $connectedName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    reportCheck($connectedName === $dbName ? 'OK' : 'FAIL',
        'Connected MySQL schema', $connectedName);
    if ($connectedName !== 'sisaket_roster_dev') {
        reportCheck('FAIL', 'Refusing further DEV-readiness checks on a non-DEV database');
        exit(2);
    }

    $q = $pdo->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?');
    $q->execute([$connectedName]);
    $tables = array_fill_keys($q->fetchAll(\PDO::FETCH_COLUMN), true);
    $sets = [
        'V11 base' => ['hospitals', 'users', 'shift_types', 'rosters', 'roster_entries', 'holidays'],
        'Phase 1' => ['duty_types', 'duty_rosters', 'duty_assignments', 'leave_requests',
                      'duty_audit_events', 'compensation_rates', 'compensation_groups',
                      'staff_compensation_groups', 'shift_swaps', 'swap_approvals'],
        'V13' => ['duty_rate_change_proposals', 'duty_rate_eligibility',
                  'duty_rate_audit_events', 'duty_document_snapshots',
                  'duty_notification_outbox', 'staff_document_order'],
    ];
    foreach ($sets as $stage => $names) {
        $missing = array_values(array_filter($names,
            static fn(string $name): bool => !isset($tables[$name])));
        reportCheck($missing ? 'FAIL' : 'OK', $stage . ' schema',
            $missing ? 'Missing: ' . implode(', ', $missing) : count($names) . ' tables found');
    }
    if (isset($tables['users'], $tables['hospitals'])) {
        $users = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $hospitals = (int)$pdo->query('SELECT COUNT(*) FROM hospitals')->fetchColumn();
        reportCheck($users > 0 ? 'OK' : 'FAIL', 'V11 user records', 'count=' . $users);
        reportCheck($hospitals > 0 ? 'OK' : 'FAIL', 'V11 hospital records', 'count=' . $hospitals);

        $q = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS
                            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'users'");
        $q->execute([$connectedName]);
        $columns = array_fill_keys($q->fetchAll(\PDO::FETCH_COLUMN), true);
        $login = ['id', 'hospital_id', 'full_name', 'email', 'password_hash',
                  'is_active', 'must_change_password', 'role', 'position'];
        $missing = array_values(array_filter($login,
            static fn(string $name): bool => !isset($columns[$name])));
        reportCheck($missing ? 'FAIL' : 'OK', 'V11 login-required columns',
            $missing ? implode(', ', $missing) : 'Present; password hashes not read');
    }

    if (isset($tables['compensation_rates'])) {
        $groups = $pdo->query('SELECT approval_status, COUNT(*) AS n FROM compensation_rates GROUP BY approval_status')
                      ->fetchAll(\PDO::FETCH_ASSOC);
        $summary = [];
        foreach ($groups as $g) $summary[] = $g['approval_status'] . '=' . $g['n'];
        reportCheck('OK', 'Rate approval status (counts only)',
            $summary ? implode(', ', $summary) : 'No rows');
        $approved = 0;
        foreach ($groups as $g) if ($g['approval_status'] === 'APPROVED') $approved += (int)$g['n'];
        if ($approved === 0) reportCheck('WARN', 'No approved compensation rates',
            'Draft rates must never be paid or printed as approved');
    }

    $router = dirname(__DIR__) . '/public/index.php';
    $code = is_file($router) ? (string)file_get_contents($router) : '';
    reportCheck(str_contains($code, "'duty'=>") ? 'OK' : 'FAIL',
        'Phase 2 duty route');
    reportCheck(str_contains($code, "'duty_rates'=>") ? 'OK' : 'WARN',
        'V13 rates route', 'Expected only after applying reviewed V13 overlay');
    reportCheck(str_contains($code, "'duty_docs'=>") ? 'OK' : 'WARN',
        'V13 documents route', 'Expected only after applying reviewed V13 overlay');
} catch (\Throwable $e) {
    // Deliberately avoid printing PDO DSNs or private values from exception messages.
    reportCheck('FAIL', 'Preflight exception', get_class($e) . '; see restricted server logs');
}

echo PHP_EOL . 'Read-only result: ' . $failures . ' FAIL, '
    . $warnings . ' WARN; no data was changed.' . PHP_EOL;
exit($failures === 0 ? 0 : 2);
