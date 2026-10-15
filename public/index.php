<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Core/bootstrap.php';

try {
    $app=config('app');
    if (session_status()===PHP_SESSION_NONE) {
        session_name($app['session_name'] ?? 'SSK_ROSTER_SID');
        session_set_cookie_params([
            'lifetime'=>0,'path'=>'/','secure'=>(bool)($app['cookie_secure'] ?? false),
            'httponly'=>true,'samesite'=>'Lax',
        ]);
        ini_set('session.use_strict_mode','1');
        session_start();
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Cache-Control: no-store');
    $c=(string)($_GET['c'] ?? 'roster');
    $a=(string)($_GET['a'] ?? 'index');
    $routes=[
        'auth'=>['login','do_login','change_password','do_change_password','logout'],
        'admin'=>['index','hospitals','hospital_form','hospital_save','staff','staff_form','staff_save','staff_reset_password','staff_import','staff_import_preview','staff_import_confirm','staff_import_result','skills','skills_save'],
        'duty'=>['index','data','create','save','validate','submit','review'],
        'duty_rates'=>['index','data','propose'],
        'leave'=>['index','data','detail','submit','decide'],
        'swap'=>['index','data','request','consent','review','reason'],
        'duty_docs'=>['preview'],
        'roster'=>['index','data','create','save_shift','bulk_save','validate','auto_preview','auto_apply','repair_preview','repair_apply','fairness_preview','fairness_apply',
            'copy_preview','copy_apply','submit','approve','reject','publish','lock','new_revision',
            'export_excel','export_print','audit','settings_save','version_history','version_compare','version_audit','my_schedule'],
    ];
    if (!isset($routes[$c]) || !in_array($a,$routes[$c],true)) jsonError('ไม่พบหน้าที่ร้องขอ',404);
    $controller=match($c) {
        'auth'=>new App\Controllers\AuthController(),
        'admin'=>new App\Controllers\AdminController(),
        'duty'=>new App\Controllers\DutyController(),
        'duty_rates'=>new App\Controllers\DutyRateAdminController(),
        'leave'=>new App\Controllers\LeaveController(),
        'swap'=>new App\Controllers\SwapController(),
        'duty_docs'=>new App\Controllers\DutyDocumentsController(),
        default=>new App\Controllers\RosterController(),
    };
    $controller->{$a}();
} catch (PDOException $e) {
    error_log((string)$e);
    jsonError('ไม่สามารถเชื่อมต่อฐานข้อมูลหรือทำรายการได้ กรุณาติดต่อผู้ดูแลระบบ',500);
} catch (Throwable $e) {
    error_log((string)$e);
    jsonError('เกิดข้อผิดพลาดภายในระบบ',500);
}
