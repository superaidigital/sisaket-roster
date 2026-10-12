<?php
declare(strict_types=1);
namespace App\Core;
use RuntimeException;
use App\Services\SecurityPolicy;

final class Auth {
    public static function user(): ?array {
        if (empty($_SESSION['user_id'])) return null;
        $q = Database::pdo()->prepare('SELECT id,hospital_id,full_name,email,role,is_active,must_change_password FROM users WHERE id=? LIMIT 1');
        $q->execute([(int) $_SESSION['user_id']]);
        $user = $q->fetch();
        if (!$user || !(int)$user['is_active']) { self::logout(); return null; }
        return $user;
    }
    public static function requireUser(): array {
        $u = self::user();
        if (!$u) {
            if (wantsJson()) jsonError('กรุณาเข้าสู่ระบบ', 401);
            header('Location: index.php?c=auth&a=login'); exit;
        }
        if ((int)($u['must_change_password'] ?? 0) === 1) {
            $url='index.php?c=auth&a=change_password';
            if (wantsJson()) jsonError('กรุณาเปลี่ยนรหัสผ่านเริ่มต้นก่อนใช้งานระบบ', 403, ['password_change_required'=>true, 'redirect'=>$url]);
            header('Location: '.$url); exit;
        }
        return $u;
    }
    public static function allowed(array $user, array $roles): bool { return in_array($user['role'], $roles, true); }
    public static function requireRole(array $u, array $roles): void {
        if (!self::allowed($u, $roles)) jsonError('ไม่มีสิทธิ์ดำเนินการ',403);
    }
    public static function hospitalId(array $u, ?int $requested): int {
        $own=(int)$u['hospital_id'];
        $target=$requested ?? $own;
        if (!SecurityPolicy::hospitalAllowed((string)$u['role'],$own,$target)) {
            jsonError('ไม่สามารถเข้าถึงหน่วยบริการอื่น',403);
        }
        return $target;
    }
    public static function canEdit(array $u): bool { return SecurityPolicy::rosterEditable((string)$u['role'],'DRAFT'); }
    public static function logout(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time()-3600, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
        }
        session_destroy();
    }
}
