<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Auth,Csrf,Database};
use App\Services\LoginThrottle;

final class AuthController {
    public function login(): void {
        $u=Auth::user();
        if ($u) {
            header('Location: index.php?c='.((int)($u['must_change_password'] ?? 0)===1 ? 'auth&a=change_password' : 'roster&a=index'));
            exit;
        }
        $error=$_SESSION['login_error'] ?? ''; unset($_SESSION['login_error']);
        require dirname(__DIR__).'/Views/auth/login.php';
    }
    public function do_login(): void {
        requirePost(); Csrf::check();
        $email=mb_strtolower(trim((string)($_POST['email'] ?? '')));
        $password=(string)($_POST['password'] ?? '');
        // A shared, locked server-side throttle persists across new PHP sessions.
        $retry=LoginThrottle::retryAfter($email);
        if ($retry>0) {
            $_SESSION['login_error']='พยายามเข้าสู่ระบบมากเกินไป กรุณารอ '.(int)ceil($retry/60).' นาทีแล้วลองใหม่';
            header('Location: index.php?c=auth&a=login',true,303); exit;
        }
        $q=Database::pdo()->prepare('SELECT id,password_hash,is_active,must_change_password FROM users WHERE email=? LIMIT 1');
        $q->execute([$email]); $u=$q->fetch();
        if (!$u || !(int)$u['is_active'] || !password_verify($password,(string)$u['password_hash'])) {
            LoginThrottle::recordFailure($email);
            $_SESSION['login_error']='อีเมลหรือรหัสผ่านไม่ถูกต้อง'; header('Location: index.php?c=auth&a=login'); exit;
        }
        LoginThrottle::clearAccount($email);
        session_regenerate_id(true);
        $_SESSION['user_id']=(int)$u['id'];
        $_SESSION['csrf']=bin2hex(random_bytes(32));
        header('Location: index.php?c='.((int)$u['must_change_password']===1?'auth&a=change_password':'roster&a=index')); exit;
    }
    public function change_password(): void {
        $user=Auth::user();
        if (!$user) { header('Location: index.php?c=auth&a=login'); exit; }
        $error=$_SESSION['password_change_error'] ?? '';
        unset($_SESSION['password_change_error']);
        require dirname(__DIR__).'/Views/auth/change_password.php';
    }
    public function do_change_password(): void {
        requirePost(); Csrf::check();
        $user=Auth::user();
        if (!$user) { header('Location: index.php?c=auth&a=login'); exit; }
        $old=(string)($_POST['current_password'] ?? '');
        $new=(string)($_POST['new_password'] ?? '');
        $confirm=(string)($_POST['confirm_password'] ?? '');
        $db=Database::pdo();
        $q=$db->prepare('SELECT password_hash FROM users WHERE id=? AND is_active=1');
        $q->execute([(int)$user['id']]);
        $existing=$q->fetchColumn();
        $error='';
        if (!is_string($existing) || !password_verify($old,$existing)) $error='รหัสผ่านปัจจุบันไม่ถูกต้อง';
        elseif ($new!==$confirm) $error='รหัสผ่านใหม่ทั้งสองช่องไม่ตรงกัน';
        elseif (strlen($new)<12 || strlen($new)>128) $error='รหัสผ่านใหม่ต้องมีความยาว 12–128 ตัวอักษร (แนะนำใช้อักษรอังกฤษและตัวเลข)';
        elseif (password_verify($new,$existing) || in_array(mb_strtolower($new),['1234','12345678','password1234','admin12345678'],true)) $error='กรุณาตั้งรหัสผ่านใหม่ที่แตกต่างจากรหัสผ่านเดิม';
        if ($error!=='') {
            $_SESSION['password_change_error']=$error;
            header('Location: index.php?c=auth&a=change_password'); exit;
        }
        $db->beginTransaction();
        try {
            $q=$db->prepare('UPDATE users SET password_hash=?,must_change_password=0 WHERE id=? AND is_active=1 AND password_hash=?');
            $q->execute([password_hash($new,PASSWORD_DEFAULT),(int)$user['id'],$existing]);
            if($q->rowCount()!==1) throw new \RuntimeException('Concurrent password change');
            $q=$db->prepare('INSERT INTO roster_audit_logs(user_id, action, hospital_id, ip_hash) VALUES(?,?,?,?)');
            $q->execute([(int)$user['id'],'CHANGE_PASSWORD',(int)$user['hospital_id'],auditIpHash()]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
        session_regenerate_id(true);
        $_SESSION['csrf']=bin2hex(random_bytes(32));
        $_SESSION['password_changed_success']=true;
        header('Location: index.php?c=roster&a=index'); exit;
    }
    public function logout(): void {
        requirePost(); Csrf::check(); Auth::logout(); header('Location: index.php?c=auth&a=login'); exit;
    }
}
