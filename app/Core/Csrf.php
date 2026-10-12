<?php
declare(strict_types=1);
namespace App\Core;
final class Csrf {
    public static function token(): string {
        if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));
        return $_SESSION['csrf'];
    }
    public static function check(): void {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
        if (!is_string($token) || !hash_equals(self::token(),$token)) jsonError('CSRF token ไม่ถูกต้อง กรุณาโหลดหน้าใหม่',419);
    }
}
