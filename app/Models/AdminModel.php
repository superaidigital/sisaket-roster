<?php
declare(strict_types=1);
namespace App\Models;

use App\Core\Database;
use PDO;
use RuntimeException;

/** Roster master data. No permanent deletions: historical roster FKs remain intact. */
final class AdminModel {
    public static function hospitals(): array {
        return Database::pdo()->query(
            'SELECT h.id,h.hospital_code,h.name,h.district,h.subdistrict,h.is_active, '
            .'(SELECT COUNT(*) FROM users u WHERE u.hospital_id=h.id AND u.is_active=1) AS active_users, '
            .'(SELECT COUNT(*) FROM rosters r WHERE r.hospital_id=h.id) AS roster_count '
            .'FROM hospitals h ORDER BY h.is_active DESC,h.name,h.id'
        )->fetchAll();
    }
    public static function hospital(int $id): ?array {
        $q=Database::pdo()->prepare('SELECT * FROM hospitals WHERE id=?');
        $q->execute([$id]); return $q->fetch() ?: null;
    }
    public static function staff(int $hospitalId=0,string $search=''): array {
        $sql='SELECT u.id,u.hospital_id,u.full_name,u.email,u.role,u.position,u.personnel_type,u.photo_url,u.is_active,u.must_change_password,u.updated_at, '
            .'h.name AS hospital_name,h.is_active AS hospital_active '
            .'FROM users u JOIN hospitals h ON h.id=u.hospital_id WHERE u.role<>\'SUPERADMIN\'';
        $args=[];
        if ($hospitalId>0) { $sql.=' AND u.hospital_id=?'; $args[]=$hospitalId; }
        if ($search!=='') { $sql.=' AND (u.full_name LIKE ? OR u.email LIKE ? OR u.position LIKE ?)';
            $pattern='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$search).'%';
            array_push($args,$pattern,$pattern,$pattern);
        }
        $q=Database::pdo()->prepare($sql.' ORDER BY h.name,u.is_active DESC,u.full_name LIMIT 500');
        $q->execute($args); return $q->fetchAll();
    }
    public static function user(int $id): ?array {
        $q=Database::pdo()->prepare('SELECT id,hospital_id,full_name,email,role,position,personnel_type,photo_url,is_active,must_change_password,updated_at FROM users WHERE id=?');
        $q->execute([$id]); return $q->fetch() ?: null;
    }
    public static function staffCounts(): array {
        return Database::pdo()->query("SELECT h.id AS hospital_id,h.name,COUNT(u.id) AS staff_count
            FROM hospitals h LEFT JOIN users u ON u.hospital_id=h.id AND u.is_active=1
            AND u.role IN ('STAFF','SCHEDULER','DIRECTOR')
            WHERE h.is_active=1 GROUP BY h.id,h.name ORDER BY h.name")->fetchAll();
    }
    private static function stamp(array $row,array $fields): string {
        $out=[];
        foreach($fields as $key) $out[$key]=(string)($row[$key] ?? '');
        return hash('sha256',json_encode($out,JSON_UNESCAPED_UNICODE));
    }
    public static function hospitalStamp(array $r): string {
        return self::stamp($r,['id','hospital_code','name','district','subdistrict','is_active']);
    }
    public static function userStamp(array $r): string {
        return self::stamp($r,['id','hospital_id','full_name','email','role','position','personnel_type','photo_url','is_active','must_change_password','updated_at']);
    }
    public static function saveHospital(?int $id,array $data,int $actor,?string $stamp): int {
        $db=Database::pdo(); $db->beginTransaction();
        try {
            $old=null;
            if ($id!==null) {
                $q=$db->prepare('SELECT * FROM hospitals WHERE id=? FOR UPDATE'); $q->execute([$id]);
                $old=$q->fetch();
                if (!$old) throw new RuntimeException('ไม่พบหน่วยบริการนี้');
                if (!hash_equals(self::hospitalStamp($old),(string)$stamp)) throw new RuntimeException('ข้อมูลหน่วยบริการถูกแก้ไขจากหน้าจออื่น กรุณาโหลดใหม่');
                if (!(int)$data['is_active'] && (int)$old['is_active']) {
                    $q=$db->prepare('SELECT COUNT(*) FROM users WHERE hospital_id=? AND is_active=1');
                    $q->execute([$id]);
                    if ((int)$q->fetchColumn()>0) throw new RuntimeException('ไม่สามารถปิดหน่วยบริการที่ยังมีบัญชีผู้ใช้งานอยู่ กรุณาปรับบัญชีผู้ใช้ก่อน');
                }
                $q=$db->prepare('UPDATE hospitals SET name=?,district=?,subdistrict=?,is_active=? WHERE id=?');
                $q->execute([$data['name'],$data['district'],$data['subdistrict'],$data['is_active'],$id]);
            } else {
                if (!$data['is_active']) throw new RuntimeException('หน่วยบริการใหม่ต้องเปิดใช้งานก่อน');
                $q=$db->prepare('INSERT INTO hospitals (hospital_code,name,district,subdistrict,is_active) VALUES (?,?,?,?,?)');
                $q->execute([$data['hospital_code'],$data['name'],$data['district'],$data['subdistrict'],$data['is_active']]);
                $id=(int)$db->lastInsertId();
                $db->prepare('INSERT INTO roster_settings (hospital_id) VALUES (?)')->execute([$id]);
            }
            RosterModel::audit($db,$actor,$old?'UPDATE_HOSPITAL':'CREATE_HOSPITAL',null,$id,null,
                $old?self::publicHospital($old):null,$data);
            $db->commit(); return $id;
        } catch (\Throwable $e) { if($db->inTransaction()) $db->rollBack(); throw $e; }
    }
    private static function publicHospital(array $r): array {
        return array_intersect_key($r,array_flip(['hospital_code','name','district','subdistrict','is_active']));
    }
    private static function publicUser(array $r): array {
        return array_intersect_key($r,array_flip(['id','hospital_id','full_name','role','position','personnel_type','is_active']));
    }
    public static function saveUser(?int $id,array $data,int $actor,?string $stamp): array {
        $db=Database::pdo(); $db->beginTransaction();
        try {
            $old=null; $newPassword=null;
            $q=$db->prepare('SELECT id,is_active FROM hospitals WHERE id=? FOR UPDATE');
            $q->execute([$data['hospital_id']]); $hospital=$q->fetch();
            if (!$hospital) throw new RuntimeException('ไม่พบหน่วยบริการ');
            if ($id!==null) {
                $q=$db->prepare('SELECT id,hospital_id,full_name,email,role,position,personnel_type,photo_url,is_active,must_change_password,updated_at FROM users WHERE id=? FOR UPDATE');
                $q->execute([$id]); $old=$q->fetch();
                if (!$old || $old['role']==='SUPERADMIN') throw new RuntimeException('ไม่อนุญาตให้แก้ไขบัญชี SUPERADMIN ในหน้านี้');
                if(!hash_equals(self::userStamp($old),(string)$stamp)) throw new RuntimeException('ข้อมูลบุคลากรถูกแก้ไขจากหน้าจออื่น กรุณาโหลดใหม่');
            }
            if ((int)$data['is_active'] && !(int)$hospital['is_active']) throw new RuntimeException('ไม่สามารถเปิดบัญชีผู้ใช้ในหน่วยบริการที่ปิดแล้ว');
            if ($old && ((int)$old['hospital_id']!==$data['hospital_id'] ||
                ((int)$old['is_active'] && !$data['is_active']) ||
                (in_array($data['role'],['ADMIN','HR'],true) && !in_array($old['role'],['ADMIN','HR'],true)))) {
                $q=$db->prepare("SELECT COUNT(*) FROM roster_entries e JOIN rosters r ON r.id=e.roster_id
                    WHERE e.user_id=? AND e.work_date>=CURDATE() AND r.status IN ('PENDING_APPROVAL','APPROVED','PUBLISHED','LOCKED')");
                $q->execute([$id]);
                if ((int)$q->fetchColumn()>0) throw new RuntimeException('บุคลากรยังมีเวรในอนาคตที่ส่งอนุมัติ/เผยแพร่แล้ว กรุณาปรับตารางเวรก่อนย้ายหน่วย ปิดบัญชี หรือเปลี่ยนสิทธิ์');
            }
            if ($old) {
                $q=$db->prepare('UPDATE users SET hospital_id=?,full_name=?,email=?,role=?,position=?,personnel_type=?,photo_url=?,is_active=? WHERE id=?');
                $q->execute([$data['hospital_id'],$data['full_name'],$data['email'],$data['role'],$data['position'],$data['personnel_type'],$data['photo_url'],$data['is_active'],$id]);
            } else {
                $newPassword=self::randomPassword();
                $q=$db->prepare('INSERT INTO users (hospital_id,full_name,email,password_hash,must_change_password,role,position,personnel_type,photo_url,is_active) VALUES (?,?,?,?,1,?,?,?,?,?)');
                $q->execute([$data['hospital_id'],$data['full_name'],$data['email'],password_hash($newPassword,PASSWORD_DEFAULT),$data['role'],$data['position'],$data['personnel_type'],$data['photo_url'],$data['is_active']]);
                $id=(int)$db->lastInsertId();
            }
            RosterModel::audit($db,$actor,$old?'UPDATE_STAFF':'CREATE_STAFF',null,$data['hospital_id'],null,$old?self::publicUser($old):null,self::publicUser($data)+['id'=>$id]);
            $db->commit(); return ['id'=>$id,'temporary_password'=>$newPassword];
        } catch (\Throwable $e) { if($db->inTransaction()) $db->rollBack(); throw $e; }
    }
    public static function resetPassword(int $id,int $actor,array $permittedRoles): string {
        $db=Database::pdo();$db->beginTransaction();
        try {
            $q=$db->prepare('SELECT id,hospital_id,role,email FROM users WHERE id=? FOR UPDATE');
            $q->execute([$id]);$user=$q->fetch();
            if (!$user || !in_array($user['role'],$permittedRoles,true)) throw new RuntimeException('ไม่อนุญาตให้รีเซ็ตรหัสผ่านบัญชีนี้');
            if ($id===$actor) throw new RuntimeException('ไม่สามารถรีเซ็ตรหัสผ่านตนเองผ่านเมนูนี้');
            $password=self::randomPassword();
            $q=$db->prepare('UPDATE users SET password_hash=?,must_change_password=1 WHERE id=?');
            $q->execute([password_hash($password,PASSWORD_DEFAULT),$id]);
            RosterModel::audit($db,$actor,'RESET_STAFF_PASSWORD',null,(int)$user['hospital_id'],null,['user_id'=>$id],['user_id'=>$id,'must_change_password'=>1]);
            $db->commit();return $password;
        } catch (\Throwable $e) { if($db->inTransaction())$db->rollBack();throw $e; }
    }
    private static function randomPassword(): string {
        return rtrim(strtr(base64_encode(random_bytes(18)),'+/','-_'),'=');
    }
}
