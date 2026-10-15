<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Phase 2 DEV-only draft-duty persistence.
 * No changes to V11 rosters; no payroll or approval is performed.
 * Locks the user row first, then roster row, to serialize edits across periods.
 */
final class DutyRosterService {
    public static function hospital(int $hospitalId): ?array {
        $q=Database::pdo()->prepare('SELECT id,name,hospital_code FROM hospitals WHERE id=? AND is_active=1');
        $q->execute([$hospitalId]);
        return $q->fetch() ?: null;
    }
    public static function current(int $hospitalId,int $year,int $month,bool $publishedOnly=false): ?array {
        $sql='SELECT * FROM duty_rosters WHERE hospital_id=? AND roster_year=? AND roster_month=?';
        if($publishedOnly) $sql.=" AND status IN ('PUBLISHED','LOCKED')";
        $sql.=' ORDER BY version DESC LIMIT 1';
        $q=Database::pdo()->prepare($sql);
        $q->execute([$hospitalId,$year,$month]);
        return $q->fetch() ?: null;
    }
    public static function people(int $hospitalId,?int $selfId=null): array {
        $sql="SELECT id,full_name,position FROM users WHERE hospital_id=? AND is_active=1 AND role IN ('STAFF','SCHEDULER','DIRECTOR')";
        $args=[$hospitalId];
        if($selfId!==null){$sql.=' AND id=?';$args[]=$selfId;}
        $q=Database::pdo()->prepare($sql.' ORDER BY full_name,id');
        $q->execute($args);
        return $q->fetchAll();
    }
    public static function types(): array {
        return Database::pdo()->query('SELECT id,code,name,starts_at,ends_at,end_day_offset FROM duty_types WHERE active=1 ORDER BY id')->fetchAll();
    }
    public static function entries(int $rosterId,?int $selfId=null): array {
        $sql='SELECT da.id,da.user_id,da.work_date,dt.code,da.start_at,da.end_at
                FROM duty_assignments da JOIN duty_types dt ON dt.id=da.duty_type_id
                WHERE da.duty_roster_id=? AND da.status=?';
        $args=[$rosterId,'ACTIVE'];
        if($selfId!==null){$sql.=' AND da.user_id=?';$args[]=$selfId;}
        $q=Database::pdo()->prepare($sql.' ORDER BY da.work_date,da.user_id,dt.id');
        $q->execute($args);
        return $q->fetchAll();
    }
    public static function create(int $hospitalId,int $year,int $month,int $actorId): array {
        $existing=self::current($hospitalId,$year,$month);
        if($existing){
            if($existing['status']!=='DRAFT') throw new RuntimeException('มีตารางเวรที่ไม่ใช่ Draft แล้ว ห้ามเขียนทับ Version เก่า');
            return $existing;
        }
        $db=Database::pdo();
        try{
            $q=$db->prepare('INSERT INTO duty_rosters (hospital_id,roster_year,roster_month,version,created_by) VALUES (?,?,?,1,?)');
            $q->execute([$hospitalId,$year,$month,$actorId]);
        }catch(\PDOException $e){
            // Another creator may win the unique version race.
            if((string)$e->getCode()!=='23000') throw $e;
            $existing=self::current($hospitalId,$year,$month);
            if(!$existing || $existing['status']!=='DRAFT') throw new RuntimeException('ตารางเดือนนี้ถูกสร้างหรือเปลี่ยนสถานะแล้ว');
            return $existing;
        }
        $out=self::current($hospitalId,$year,$month);
        if(!$out) throw new RuntimeException('ไม่พบ Draft หลังสร้าง');
        return $out;
    }
    public static function save(array $roster,int $actorId,int $userId,string $date,string $code,bool $enabled,int $expectedRevision): int {
        if($roster['status']!=='DRAFT') throw new RuntimeException('แก้ไขได้เฉพาะ Draft เท่านั้น');
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)) throw new InvalidArgumentException('รูปแบบวันที่ไม่ถูกต้อง');
        [$start,$end]=DutyPolicy::interval($date,$code);
        if((int)substr($date,0,4)!==(int)$roster['roster_year'] ||
           (int)substr($date,5,2)!==(int)$roster['roster_month'])
            throw new InvalidArgumentException('วันที่ไม่อยู่ในรอบเดือนที่เลือก');

        $db=Database::pdo();
        $db->beginTransaction();
        try{
            $staff=$db->prepare("SELECT id FROM users WHERE id=? AND hospital_id=? AND is_active=1
                                  AND role IN ('STAFF','SCHEDULER','DIRECTOR') FOR UPDATE");
            $staff->execute([$userId,(int)$roster['hospital_id']]);
            if(!$staff->fetch()) throw new InvalidArgumentException('ไม่พบผู้ปฏิบัติงานที่มีสิทธิ์ในหน่วยบริการ');

            $locked=$db->prepare('SELECT * FROM duty_rosters WHERE id=? AND hospital_id=? FOR UPDATE');
            $locked->execute([(int)$roster['id'],(int)$roster['hospital_id']]);
            $r=$locked->fetch();
            if(!$r || $r['status']!=='DRAFT') throw new RuntimeException('ตารางถูกเปลี่ยนสถานะแล้ว');
            if((int)$r['revision']!==$expectedRevision)
                throw new RuntimeException('มีการแก้ไขจากผู้ใช้รายอื่น กรุณาโหลดข้อมูลใหม่');

            $type=$db->prepare('SELECT id FROM duty_types WHERE code=? AND active=1');
            $type->execute([$code]);
            $typeId=$type->fetchColumn();
            if(!$typeId) throw new InvalidArgumentException('ประเภทเวรไม่ได้เปิดใช้งาน');

            $find=$db->prepare('SELECT id,status FROM duty_assignments
                               WHERE duty_roster_id=? AND user_id=? AND work_date=? AND duty_type_id=? FOR UPDATE');
            $find->execute([(int)$r['id'],$userId,$date,(int)$typeId]);
            $old=$find->fetch();

            if($enabled){
                if($code==='ย'){
                    $day=(int)(new DateTimeImmutable($date))->format('N');
                    $holiday=$db->prepare('SELECT 1 FROM holidays WHERE holiday_date=? AND (hospital_id IS NULL OR hospital_id=?) LIMIT 1');
                    $holiday->execute([$date,(int)$r['hospital_id']]);
                    if($day<6 && !$holiday->fetchColumn())
                        throw new InvalidArgumentException('เวร ย ต้องอยู่ในวันหยุดราชการหรือวันที่กำหนดเป็นวันหยุด');
                }
                $leave=$db->prepare("SELECT id FROM leave_requests WHERE requester_id=? AND status='APPROVED'
                                      AND starts_at < ? AND ends_at > ? LIMIT 1 FOR UPDATE");
                $leave->execute([$userId,$end,$start]);
                if($leave->fetch()) throw new InvalidArgumentException('บุคลากรมีใบลาอนุมัติทับช่วงเวร');

                // Current draft and OTHER month confirmed duty rosters.
                // Same-month history stays immutable, but is not counted twice during a new version draft.
                $other=$db->prepare("SELECT da.id,da.start_at,da.end_at
                    FROM duty_assignments da JOIN duty_rosters dr ON dr.id=da.duty_roster_id
                    WHERE da.user_id=? AND da.status='ACTIVE'
                      AND da.start_at < ? AND da.end_at > ?
                      AND (da.duty_roster_id=?
                        OR ((dr.roster_year<>? OR dr.roster_month<>?)
                          AND dr.status IN ('APPROVED','PUBLISHED','LOCKED')))
                    FOR UPDATE");
                $other->execute([$userId,$end,$start,(int)$r['id'],(int)$r['roster_year'],(int)$r['roster_month']]);
                foreach($other->fetchAll() as $entry){
                    if($old && (int)$entry['id']===(int)$old['id']) continue;
                    if(DutyPolicy::overlaps($start,$end,$entry['start_at'],$entry['end_at']))
                        throw new InvalidArgumentException('เวรซ้อนทับกับรายการ OT อื่น');
                }

                // Prevent overlaps with the existing published V11 monthly shift roster.
                $v11=$db->prepare("SELECT re.work_date, st.start_time,st.end_time
                    FROM roster_entries re JOIN rosters rr ON rr.id=re.roster_id
                    JOIN shift_types st ON st.id=re.shift_type_id
                    WHERE re.user_id=? AND rr.hospital_id=?
                      AND rr.status IN ('PUBLISHED','LOCKED') AND st.is_working=1
                      AND re.work_date BETWEEN DATE_SUB(DATE(?),INTERVAL 1 DAY) AND DATE(?)
                      AND st.start_time IS NOT NULL AND st.end_time IS NOT NULL");
                $v11->execute([$userId,(int)$r['hospital_id'],$date,$date]);
                foreach($v11->fetchAll() as $v){
                    $vs=$v['work_date'].' '.$v['start_time'];
                    $ve=$v['work_date'].' '.$v['end_time'];
                    if($ve<=$vs) $ve=(new DateTimeImmutable($ve))->modify('+1 day')->format('Y-m-d H:i:s');
                    if(DutyPolicy::overlaps($start,$end,$vs,$ve))
                        throw new InvalidArgumentException('เวรชนกับตารางเวรปกติ V11 ที่เผยแพร่แล้ว');
                }
            }

            $changed=false;
            $assignmentId=$old?(int)$old['id']:null;
            if($enabled){
                if(!$old){
                    $q=$db->prepare("INSERT INTO duty_assignments
                      (duty_roster_id,user_id,duty_type_id,work_date,start_at,end_at,created_by,updated_by)
                      VALUES (?,?,?,?,?,?,?,?)");
                    $q->execute([(int)$r['id'],$userId,(int)$typeId,$date,$start,$end,$actorId,$actorId]);
                    $assignmentId=(int)$db->lastInsertId(); $changed=true;
                }elseif($old['status']==='CANCELLED'){
                    $q=$db->prepare("UPDATE duty_assignments SET status='ACTIVE',start_at=?,end_at=?,updated_by=? WHERE id=?");
                    $q->execute([$start,$end,$actorId,$assignmentId]); $changed=true;
                }
            }elseif($old && $old['status']==='ACTIVE'){
                $q=$db->prepare("UPDATE duty_assignments SET status='CANCELLED',updated_by=? WHERE id=?");
                $q->execute([$actorId,$assignmentId]); $changed=true;
            }
            if($changed){
                $db->prepare('UPDATE duty_rosters SET revision=revision+1 WHERE id=?')->execute([(int)$r['id']]);
                $audit=$db->prepare('INSERT INTO duty_audit_events
                  (hospital_id,actor_id,action_code,record_type,record_id,change_summary) VALUES (?,?,?,?,?,?)');
                $audit->execute([(int)$r['hospital_id'],$actorId,$enabled?'ASSIGN_DUTY':'CANCEL_DUTY',
                  'duty_assignment',(int)$assignmentId,json_encode(['work_date'=>$date,'duty_type'=>$code,'enabled'=>$enabled],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
            }
            $db->commit();
            return (int)$r['revision']+($changed?1:0);
        }catch(\Throwable $e){
            if($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }
}
