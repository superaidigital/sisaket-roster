<?php
declare(strict_types=1);
namespace App\Models;
use App\Core\Database;
use PDO;

final class RosterModel {
    public static function hospitals(): array { return Database::pdo()->query('SELECT id,hospital_code,name,district,subdistrict FROM hospitals WHERE is_active=1 ORDER BY name')->fetchAll(); }
    public static function hospital(int $id): ?array {
        $q=Database::pdo()->prepare('SELECT * FROM hospitals WHERE id=? AND is_active=1'); $q->execute([$id]); return $q->fetch() ?: null;
    }
    public static function settings(int $hospitalId): array {
        $q=Database::pdo()->prepare('SELECT * FROM roster_settings WHERE hospital_id=?'); $q->execute([$hospitalId]);
        return $q->fetch() ?: ['min_morning'=>2,'min_afternoon'=>2,'min_night'=>2,'max_consecutive_work_days'=>6,'max_night_shifts'=>8,'min_rest_hours'=>11];
    }
    /** V10 skills are explicit manual confirmations; titles alone never confer a skill. */
    public static function requirements(int $hospitalId): array {
        $q=Database::pdo()->prepare('SELECT hospital_id,shift_type_id,skill_code,min_count FROM roster_shift_skill_requirements WHERE hospital_id=? AND min_count>0 ORDER BY shift_type_id,skill_code');
        $q->execute([$hospitalId]); return $q->fetchAll();
    }
    public static function staffSkills(int $hospitalId): array {
        $q=Database::pdo()->prepare("SELECT rs.user_id,rs.skill_code FROM roster_staff_skills rs JOIN users u ON u.id=rs.user_id WHERE rs.hospital_id=? AND u.hospital_id=? AND u.is_active=1 AND u.role IN ('STAFF','SCHEDULER','DIRECTOR')");
        $q->execute([$hospitalId,$hospitalId]); return $q->fetchAll();
    }
    public static function shifts(): array { return Database::pdo()->query('SELECT * FROM shift_types WHERE is_active=1 ORDER BY sort_order,id')->fetchAll(); }
    public static function staff(int $hospitalId, bool $includeInactive=false): array {
        $sql='SELECT id,full_name,role,position,personnel_type,photo_url,is_active FROM users WHERE hospital_id=? AND role NOT IN (\'SUPERADMIN\',\'ADMIN\',\'HR\')';
        if (!$includeInactive) $sql.=' AND is_active=1';
        $q=Database::pdo()->prepare($sql.' ORDER BY full_name,id'); $q->execute([$hospitalId]); return $q->fetchAll();
    }
    /** Display historical assignees even after transfer, role change or deactivation.
     * Only eligible=true staff may receive a new shift.
     */
    public static function rosterStaff(int $hospitalId, ?int $rosterId): array {
        $db=Database::pdo();
        $sql="SELECT id,hospital_id,full_name,role,position,personnel_type,photo_url,is_active FROM users WHERE (hospital_id=? AND is_active=1 AND role NOT IN ('SUPERADMIN','ADMIN','HR'))";
        $args=[$hospitalId];
        if($rosterId) { $sql.=' OR id IN (SELECT user_id FROM roster_entries WHERE roster_id=?)'; $args[]=$rosterId; }
        $sql.=' ORDER BY full_name,id';
        $q=$db->prepare($sql);$q->execute($args);
        $items=$q->fetchAll();
        foreach($items as &$person) {
            $person['eligible']=(int)$person['is_active']===1 && (int)$person['hospital_id']===$hospitalId
                && !in_array($person['role'],['SUPERADMIN','ADMIN','HR'],true);
        }
        unset($person);
        return $items;
    }
    public static function holidays(int $hospitalId, int $year,int $month): array {
        $q=Database::pdo()->prepare('SELECT holiday_date,name FROM holidays WHERE (hospital_id=? OR hospital_id IS NULL) AND holiday_date BETWEEN ? AND ? ORDER BY holiday_date');
        $first=sprintf('%04d-%02d-01',$year,$month); $last=date('Y-m-t',strtotime($first));
        $q->execute([$hospitalId,$first,$last]); return $q->fetchAll();
    }
    public static function latest(int $hospitalId, int $year, int $month, bool $staffOnly): ?array {
        $sql='SELECT * FROM rosters WHERE hospital_id=? AND `year`=? AND `month`=?';
        if ($staffOnly) $sql.=" AND status IN ('PUBLISHED','LOCKED')";
        $q=Database::pdo()->prepare($sql.' ORDER BY version DESC LIMIT 1');
        $q->execute([$hospitalId,$year,$month]); return $q->fetch() ?: null;
    }
    public static function one(int $id): ?array {
        $q=Database::pdo()->prepare('SELECT * FROM rosters WHERE id=?'); $q->execute([$id]); return $q->fetch() ?: null;
    }
    public static function entries(int $rosterId): array {
        $q=Database::pdo()->prepare('SELECT e.id,e.user_id,e.work_date,e.shift_type_id,e.note,e.updated_at,t.code,t.name AS shift_name,t.short_name,t.is_working,t.start_time,t.end_time FROM roster_entries e JOIN shift_types t ON t.id=e.shift_type_id WHERE e.roster_id=? ORDER BY e.work_date,e.user_id');
        $q->execute([$rosterId]); return $q->fetchAll();
    }
    public static function create(int $hospital,int $year,int $month,int $actor): array {
        $db=Database::pdo(); $db->beginTransaction();
        try {
            // Unique month/version index handles concurrent creation races.
            $q=$db->prepare('SELECT id FROM rosters WHERE hospital_id=? AND `year`=? AND `month`=? LIMIT 1 FOR UPDATE');
            $q->execute([$hospital,$year,$month]);
            if ($q->fetch()) jsonError('เดือนนี้มีตารางแล้ว กรุณาเปิดตารางเดิม',409);
            $q=$db->prepare('INSERT INTO rosters (hospital_id,`year`,`month`,created_by) VALUES (?,?,?,?)');
            $q->execute([$hospital,$year,$month,$actor]);
            $id=(int)$db->lastInsertId();
            self::audit($db,$actor,'CREATE_ROSTER',$id,$hospital,null,null,['year'=>$year,'month'=>$month]);
            $db->commit(); return self::one($id);
        } catch (\Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
    }
    public static function audit(PDO $db,int $actor,string $action,?int $roster,int $hospital,?string $date,mixed $old,mixed $new): void {
        $q=$db->prepare('INSERT INTO roster_audit_logs (user_id,action,roster_id,hospital_id,work_date,old_value,new_value,ip_hash) VALUES (?,?,?,?,?,?,?,?)');
        $q->execute([$actor,$action,$roster,$hospital,$date,$old===null?null:json_encode($old,JSON_UNESCAPED_UNICODE),$new===null?null:json_encode($new,JSON_UNESCAPED_UNICODE),auditIpHash()]);
    }
    public static function saveCells(int $rosterId, int $actor,int $expectedRevision,array $changes,string $action='UPDATE_SHIFT'): array {
        if (count($changes)<1 || count($changes)>3500) jsonError('จำนวน Cell ต้องอยู่ระหว่าง 1–3500',422);
        $db=Database::pdo(); $db->beginTransaction();
        try {
            $q=$db->prepare('SELECT * FROM rosters WHERE id=? FOR UPDATE'); $q->execute([$rosterId]); $roster=$q->fetch();
            if (!$roster) jsonError('ไม่พบตารางเวร',404);
            if ($roster['status']!=='DRAFT') jsonError('แก้ไขได้เฉพาะ Draft; หากเผยแพร่แล้วให้สร้าง Version ใหม่',409);
            if ((int)$roster['lock_version']!==$expectedRevision) jsonError('ตารางถูกแก้ไขโดยผู้ใช้อื่น กรุณารีเฟรชก่อนบันทึก',409,['conflict'=>true,'server_revision'=>(int)$roster['lock_version']]);
            $getUser=$db->prepare('SELECT id,hospital_id,role,is_active FROM users WHERE id=?');
            $getShift=$db->prepare('SELECT id,is_active FROM shift_types WHERE id=?');
            $getEntry=$db->prepare('SELECT shift_type_id,note FROM roster_entries WHERE roster_id=? AND user_id=? AND work_date=?');
            $upsert=$db->prepare('INSERT INTO roster_entries (roster_id,user_id,work_date,shift_type_id,note,created_by,updated_by) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE shift_type_id=VALUES(shift_type_id),note=VALUES(note),updated_by=VALUES(updated_by)');
            $delete=$db->prepare('DELETE FROM roster_entries WHERE roster_id=? AND user_id=? AND work_date=?');
            $seen=[]; $count=0;
            $start=sprintf('%04d-%02d-01',$roster['year'],$roster['month']); $end=date('Y-m-t',strtotime($start));
            foreach ($changes as $change) {
                if (!is_array($change)) jsonError('รูปแบบรายการไม่ถูกต้อง',422);
                $uid=intInput($change['user_id'] ?? null,1,2147483647,'บุคลากร');
                $date=(string)($change['date'] ?? '');
                $dt=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
                if (!$dt || $dt->format('Y-m-d')!==$date || $date<$start || $date>$end) jsonError('วันที่ไม่อยู่ในเดือนของตาราง',422);
                $key=$uid.'|'.$date;
                if (isset($seen[$key])) jsonError('ส่งข้อมูลบุคลากร/วันที่ซ้ำกัน',422);
                $seen[$key]=true;
                $shiftId=$change['shift_type_id'] ?? ($change['shift_id'] ?? null);
                $getUser->execute([$uid]); $person=$getUser->fetch();
                if (!$person) jsonError('ไม่พบบุคลากร',422);
                $eligible=(int)$person['hospital_id']===(int)$roster['hospital_id'] && (int)$person['is_active']===1 && !in_array($person['role'],['SUPERADMIN','ADMIN','HR'],true);
                // Allow deleting historical entries for retired/transferred/excluded staff, never adding to them.
                if (!$eligible && $shiftId!==null && $shiftId!=='') jsonError('บุคลากรไม่อยู่ใน Staff Pool หรือพ้นสภาพแล้ว',422);
                if ($shiftId!==null && $shiftId!=='') {
                    $shiftId=intInput($shiftId,1,65535,'ประเภทเวร');
                    $getShift->execute([$shiftId]); $shift=$getShift->fetch();
                    if (!$shift || !(int)$shift['is_active']) jsonError('ประเภทเวรไม่พร้อมใช้งาน',422);
                } else $shiftId=null;
                $note=trim((string)($change['note'] ?? ''));
                if (mb_strlen($note)>500) jsonError('หมายเหตุยาวเกิน 500 ตัวอักษร',422);
                $getEntry->execute([$rosterId,$uid,$date]); $old=$getEntry->fetch() ?: null;
                if (!$eligible && !$old) jsonError('ไม่อนุญาตให้แก้ไขบุคลากรที่อยู่นอก Staff Pool',422);
                if ($shiftId===null) $delete->execute([$rosterId,$uid,$date]);
                else $upsert->execute([$rosterId,$uid,$date,$shiftId,$note,$actor,$actor]);
                self::audit($db,$actor,$shiftId===null?'DELETE_SHIFT':$action,$rosterId,(int)$roster['hospital_id'],$date,$old,['user_id'=>$uid,'shift_type_id'=>$shiftId,'note'=>$note]);
                $count++;
            }
            $q=$db->prepare('UPDATE rosters SET lock_version=lock_version+1 WHERE id=?'); $q->execute([$rosterId]);
            $db->commit(); return ['saved'=>$count,'revision'=>$expectedRevision+1];
        } catch (\Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
    }
    public static function snapshot(int $rosterId): string {
        $entries=self::entries($rosterId);
        return json_encode(array_map(static fn($e)=>['user_id'=>(int)$e['user_id'],'date'=>$e['work_date'],'shift_type_id'=>(int)$e['shift_type_id'],'note'=>$e['note']],$entries),JSON_UNESCAPED_UNICODE);
    }
    public static function newRevision(array $published,int $actor,string $reason,int $expectedRevision): array {
        $db=Database::pdo(); $db->beginTransaction();
        try {
            $q=$db->prepare('SELECT * FROM rosters WHERE id=? FOR UPDATE'); $q->execute([(int)$published['id']]); $source=$q->fetch();
            if (!$source || !in_array($source['status'],['PUBLISHED','LOCKED'],true)) jsonError('สร้างรุ่นใหม่ได้จากตารางที่เผยแพร่แล้วเท่านั้น',409);
            // V11: Check revision AGAIN under database row lock, preventing a stale create.
            if((int)$source['lock_version']!==$expectedRevision) {
                throw new \DomainException('Revision เปลี่ยนไป กรุณาโหลดตารางใหม่ก่อนสร้าง Version');
            }
            $q=$db->prepare('SELECT MAX(version) AS mx FROM rosters WHERE hospital_id=? AND `year`=? AND `month`=?');
            $q->execute([$source['hospital_id'],$source['year'],$source['month']]); $currentMax=(int)$q->fetch()['mx'];
            // A second request must not branch from a published version after a newer draft exists.
            if($currentMax!==(int)$source['version']) throw new \DomainException('มี Version ใหม่กว่านี้แล้ว กรุณาโหลดตารางล่าสุด');
            $version=$currentMax+1;
            $q=$db->prepare("INSERT INTO rosters (hospital_id,`year`,`month`,status,version,base_roster_id,created_by) VALUES (?,?,?,'DRAFT',?,?,?)");
            $q->execute([$source['hospital_id'],$source['year'],$source['month'],$version,$source['id'],$actor]); $id=(int)$db->lastInsertId();
            $q=$db->prepare('INSERT INTO roster_entries (roster_id,user_id,work_date,shift_type_id,note,created_by,updated_by) SELECT ?,user_id,work_date,shift_type_id,note,?,? FROM roster_entries WHERE roster_id=?');
            $q->execute([$id,$actor,$actor,$source['id']]);
            $before=self::snapshot((int)$source['id']); $after=self::snapshot($id);
            $q=$db->prepare('INSERT INTO roster_versions (roster_id,version,action,changed_by,reason,before_snapshot,after_snapshot) VALUES (?,?,?,?,?,?,?)');
            $q->execute([$id,$version,'CREATE_REVISION',$actor,$reason,$before,$after]);
            self::audit($db,$actor,'CREATE_REVISION',$id,(int)$source['hospital_id'],null,['from_id'=>$source['id'],'version'=>$source['version']],['version'=>$version,'reason'=>$reason]);
            $db->commit(); return self::one($id);
        } catch (\Throwable $e) {
            if($db->inTransaction()) $db->rollBack();
            if($e instanceof \DomainException) jsonError($e->getMessage(),409,['conflict'=>true]);
            throw $e;
        }
    }
    public static function auditList(int $hospitalId,int $limit=100): array {
        $q=Database::pdo()->prepare('SELECT a.id,a.action,a.roster_id,a.work_date,a.old_value,a.new_value,a.created_at,u.full_name FROM roster_audit_logs a JOIN users u ON u.id=a.user_id WHERE a.hospital_id=? ORDER BY a.id DESC LIMIT '.max(1,min(200,$limit)));
        $q->execute([$hospitalId]); return $q->fetchAll();
    }
    /** Historical versions for the same hospital and month. All queries are scoped by hospital. */
    public static function versionHistory(int $hospitalId,int $year,int $month): array {
        $q=Database::pdo()->prepare("SELECT r.id,r.version,r.status,r.lock_version,r.base_roster_id,r.created_at,r.updated_at,r.submitted_at,r.approved_at,r.published_at,r.locked_at,
            u.full_name AS creator_name,
            (SELECT rv.reason FROM roster_versions rv WHERE rv.roster_id=r.id AND rv.action='CREATE_REVISION' ORDER BY rv.id DESC LIMIT 1) AS reason,
            (SELECT COUNT(*) FROM roster_entries re WHERE re.roster_id=r.id) AS entry_count
            FROM rosters r JOIN users u ON u.id=r.created_by
            WHERE r.hospital_id=? AND r.`year`=? AND r.`month`=? ORDER BY r.version DESC");
        $q->execute([$hospitalId,$year,$month]); return $q->fetchAll();
    }
    /** Include inactive shift types in history, so older audit records retain meaningful names. */
    public static function allShiftsForAudit(): array {
        return Database::pdo()->query('SELECT id,name FROM shift_types ORDER BY id')->fetchAll();
    }
    /** Only resolve staff that actually appear in this roster's audit events / entries.
     * Role authorization and hospital scoping are enforced by the controller.
     */
    public static function rosterAuditStaff(int $rosterId): array {
        $q=Database::pdo()->prepare('SELECT DISTINCT u.id,u.full_name FROM users u
            WHERE u.id IN (SELECT e.user_id FROM roster_entries e WHERE e.roster_id=?)
               OR u.id IN (SELECT a.user_id FROM roster_audit_logs a WHERE a.roster_id=?)');
        $q->execute([$rosterId,$rosterId]);
        return $q->fetchAll();
    }
    /** Read-only audit history of one roster; this is not a publication endpoint. */
    public static function rosterAudit(int $rosterId,int $limit=100): array {
        $limit=max(1,min(200,$limit));
        $q=Database::pdo()->prepare('SELECT a.id,a.action,a.work_date,a.old_value,a.new_value,a.created_at,u.full_name AS actor_name
            FROM roster_audit_logs a JOIN users u ON u.id=a.user_id WHERE a.roster_id=? ORDER BY a.id DESC LIMIT '.$limit);
        $q->execute([$rosterId]);return $q->fetchAll();
    }

}
