<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use App\Models\RosterModel;
use PDO;
use RuntimeException;

final class StaffImportService {
    private const ALLOWED_ROLES=['STAFF','SCHEDULER','DIRECTOR'];
    private const MAX_AGE=900;

    /** Check all rows and mark duplicates. Never auto-update existing accounts. */
    public static function preview(array $rows,int $hospitalId): array {
        $h=Database::pdo()->prepare('SELECT id,name,is_active FROM hospitals WHERE id=?');
        $h->execute([$hospitalId]); $hospital=$h->fetch();
        if (!$hospital || !(int)$hospital['is_active']) throw new RuntimeException('กรุณาเลือกหน่วยบริการที่เปิดใช้งาน');
        $emails=[];$seen=[];$out=[];
        foreach($rows as $row) {
            $clean=self::sanitize($row);
            $email=$clean['email'];
            $issues=self::validateRow($clean);
            if ($email!=='') {
                if (isset($seen[$email])) $issues[]='อีเมลซ้ำกับแถว '.$seen[$email].' ในไฟล์';
                else $seen[$email]=$clean['line'];
                $emails[]=$email;
            }
            $out[]=$clean+['status'=>'ready','issues'=>$issues];
        }
        $existing=self::existingEmails($emails);
        foreach($out as &$row) {
            if (!$row['issues'] && isset($existing[$row['email']])) {
                $row['status']='skip'; $row['issues']=['อีเมลนี้มีบัญชีอยู่แล้ว (ข้าม ไม่เขียนทับ)'];
            } elseif ($row['issues']) $row['status']='error';
        }
        unset($row);
        $counts=['ready'=>0,'skip'=>0,'error'=>0];
        foreach($out as $r) $counts[$r['status']]++;
        return ['hospital_id'=>$hospitalId,'hospital_name'=>$hospital['name'],'rows'=>$out,'counts'=>$counts,'created_at'=>time()];
    }
    public static function validSession(array $p,int $hospitalId): bool {
        return $hospitalId>0 && (int)($p['hospital_id']??0)===$hospitalId
            && isset($p['created_at'],$p['rows'],$p['counts'])
            && is_array($p['rows']) && $p['rows']
            && time()-(int)$p['created_at']<=self::MAX_AGE;
    }
    private static function sanitize(array $row): array {
        $email=mb_strtolower(trim((string)($row['email']??'')));
        return ['line'=>(int)($row['line']??0),
            'full_name'=>trim((string)($row['full_name']??'')),
            'email'=>$email,
            'role'=>strtoupper(trim((string)($row['role']??''))),
            'position'=>trim((string)($row['position']??'')),
            'personnel_type'=>trim((string)($row['personnel_type']??''))];
    }
    private static function validateRow(array $r): array {
        $errors=[];
        foreach(['full_name'=>200,'email'=>190,'position'=>150,'personnel_type'=>100] as $field=>$max) {
            if(mb_strlen($r[$field])>$max) $errors[]="$field ยาวเกิน $max ตัวอักษร";
            if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/',$r[$field])) $errors[]="$field มีอักขระควบคุมที่ไม่อนุญาต";
            if (preg_match('/^[=+@]/',$r[$field])) $errors[]="$field ต้องเป็นข้อความ ไม่ใช่สูตร";
        }
        if($r['full_name']==='') $errors[]='ต้องระบุชื่อ-นามสกุล';
        if(!filter_var($r['email'],FILTER_VALIDATE_EMAIL)) $errors[]='อีเมลไม่ถูกต้อง';
        if(!in_array($r['role'],self::ALLOWED_ROLES,true)) $errors[]='สิทธิ์ต้องเป็น STAFF, SCHEDULER หรือ DIRECTOR เท่านั้น';
        return $errors;
    }
    private static function existingEmails(array $emails): array {
        $emails=array_values(array_unique(array_filter($emails)));
        if(!$emails) return [];
        $found=[];
        foreach(array_chunk($emails,100) as $chunk) {
            $marks=implode(',',array_fill(0,count($chunk),'?'));
            $stmt=Database::pdo()->prepare("SELECT LOWER(email) AS email FROM users WHERE email IN ($marks)");
            $stmt->execute($chunk);
            foreach($stmt->fetchAll(PDO::FETCH_COLUMN) as $email) $found[$email]=true;
        }
        return $found;
    }
    /** Single transaction; unique email constraint catches concurrent import attempts. */
    public static function commit(array $preview,int $actor): array {
        $hospitalId=(int)$preview['hospital_id'];
        if (!self::validSession($preview,$hospitalId)) throw new RuntimeException('ข้อมูลตัวอย่างหมดอายุ กรุณาอัปโหลดไฟล์ใหม่');
        if ((int)$preview['counts']['error']>0) throw new RuntimeException('ไฟล์ยังมีแถวผิดพลาด กรุณาแก้ไขไฟล์และนำเข้าใหม่');
        $db=Database::pdo();$db->beginTransaction();
        try {
            $q=$db->prepare('SELECT is_active FROM hospitals WHERE id=? FOR UPDATE');
            $q->execute([$hospitalId]);
            if (!(int)$q->fetchColumn()) throw new RuntimeException('หน่วยบริการถูกปิดใช้งานแล้ว');
            $eligible=[];
            foreach($preview['rows'] as $row) {
                if($row['status']!=='ready') continue;
                $r=self::sanitize($row);
                if(self::validateRow($r)) throw new RuntimeException('ข้อมูลแถว '.$r['line'].' ผิดพลาด กรุณานำเข้าใหม่');
                $eligible[]=$r;
            }
            if (!$eligible) throw new RuntimeException('ไม่มีรายชื่อใหม่สำหรับนำเข้า');
            $existing=self::existingEmails(array_column($eligible,'email'));
            if($existing) throw new RuntimeException('มีบัญชีถูกเพิ่มระหว่างตรวจสอบข้อมูล กรุณา Preview ใหม่ก่อนนำเข้า');
            $q=$db->prepare('INSERT INTO users (hospital_id,full_name,email,password_hash,must_change_password,role,position,personnel_type,photo_url,is_active) VALUES (?,?,?,?,1,?,?,?,NULL,1)');
            $credentials=[];
            foreach($eligible as $r) {
                $secret=rtrim(strtr(base64_encode(random_bytes(18)),'+/','-_'),'=');
                $q->execute([$hospitalId,$r['full_name'],$r['email'],password_hash($secret,PASSWORD_DEFAULT),$r['role'],$r['position'],$r['personnel_type']]);
                $id=(int)$db->lastInsertId();
                RosterModel::audit($db,$actor,'IMPORT_STAFF',null,$hospitalId,null,null,
                    ['user_id'=>$id,'email'=>$r['email'],'role'=>$r['role'],'source_line'=>$r['line']]);
                $credentials[]=['full_name'=>$r['full_name'],'email'=>$r['email'],'temporary_password'=>$secret];
            }
            $db->commit();
            return ['created'=>count($credentials),'skipped'=>(int)$preview['counts']['skip'],'credentials'=>$credentials];
        } catch (\Throwable $e) {
            if($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }
}
