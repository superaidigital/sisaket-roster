<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Safe shift-swap request, consent and director endorsement.
 * NEVER edits approved/published/locked assignments or compensation.
 * Authority approval and a new roster amendment version must be implemented
 * and formally accepted before a swap can be applied to an issued order.
 */
final class DutySwapService
{
    private const PENDING=['PENDING_CONSENT','PENDING_DIRECTOR','PENDING_AUTHORITY','REVISION_REQUIRED'];

    public static function availableAssignments(array $actor):array
    {
        $db=Database::pdo();
        $q=$db->prepare("SELECT da.id,da.work_date,dt.code,da.start_at,da.end_at,dr.version
            FROM duty_assignments da JOIN duty_rosters dr ON dr.id=da.duty_roster_id
            JOIN duty_types dt ON dt.id=da.duty_type_id
            WHERE da.user_id=? AND dr.hospital_id=? AND da.status='ACTIVE'
                AND dr.status IN ('APPROVED','PUBLISHED','LOCKED')
                AND da.work_date>=DATE_SUB(CURDATE(),INTERVAL 30 DAY)
                AND da.work_date<DATE_ADD(CURDATE(),INTERVAL 365 DAY)
            ORDER BY da.work_date LIMIT 120");
        $q->execute([(int)$actor['id'],(int)$actor['hospital_id']]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function replacements(array $actor):array
    {
        $q=Database::pdo()->prepare("SELECT id,full_name,position FROM users
            WHERE hospital_id=? AND id<>? AND is_active=1
            AND role IN ('STAFF','SCHEDULER','DIRECTOR') ORDER BY full_name,id");
        $q->execute([(int)$actor['hospital_id'],(int)$actor['id']]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function listing(array $actor):array
    {
        $all=in_array((string)$actor['role'],['DIRECTOR','SUPERADMIN'],true);
        $sql="SELECT s.id,s.assignment_id,s.requester_id,s.replacement_id,
                requester.full_name requester_name,replacement.full_name replacement_name,
                da.work_date,dt.code,s.status,s.created_at,s.expected_revision
            FROM shift_swaps s
            JOIN users requester ON requester.id=s.requester_id
            JOIN users replacement ON replacement.id=s.replacement_id
            JOIN duty_assignments da ON da.id=s.assignment_id
            JOIN duty_types dt ON dt.id=da.duty_type_id
            WHERE s.hospital_id=?";
        $params=[(int)$actor['hospital_id']];
        if(!$all){$sql.=' AND (s.requester_id=? OR s.replacement_id=?)';$params[]=(int)$actor['id'];$params[]=(int)$actor['id'];}
        $q=Database::pdo()->prepare($sql.' ORDER BY s.id DESC LIMIT 100');
        $q->execute($params);return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function audit(PDO $db,int $hospital,int $actor,int $id,string $action,array $summary):void
    {
        $q=$db->prepare('INSERT INTO duty_audit_events (hospital_id,actor_id,action_code,record_type,record_id,change_summary) VALUES (?,?,?,?,?,?)');
        $q->execute([$hospital,$actor,$action,'shift_swap',$id,json_encode($summary,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
    }

    private static function eligible(PDO $db,int $replacement,int $hospital,string $start,string $end):void
    {
        $q=$db->prepare("SELECT 1 FROM users WHERE id=? AND hospital_id=? AND is_active=1
                 AND role IN ('STAFF','SCHEDULER','DIRECTOR') LIMIT 1");
        $q->execute([$replacement,$hospital]);
        if(!$q->fetchColumn())throw new InvalidArgumentException('ผู้รับเวรแทนไม่ได้อยู่ในหน่วยบริการหรือไม่มีสิทธิ์');

        $q=$db->prepare("SELECT id FROM leave_requests WHERE requester_id=? AND status='APPROVED'
                 AND starts_at<? AND ends_at>? LIMIT 1");
        $q->execute([$replacement,$end,$start]);
        if($q->fetchColumn())throw new RuntimeException('ผู้รับเวรแทนมีวันลาที่อนุมัติแล้วทับช่วงเวลา');

        $q=$db->prepare("SELECT da.id FROM duty_assignments da
            JOIN duty_rosters dr ON dr.id=da.duty_roster_id
            WHERE da.user_id=? AND da.status='ACTIVE'
                AND dr.status IN ('APPROVED','PUBLISHED','LOCKED')
                AND da.start_at<? AND da.end_at>? LIMIT 1");
        $q->execute([$replacement,$end,$start]);
        if($q->fetchColumn())throw new RuntimeException('ผู้รับเวรแทนมีเวร Duty ที่อนุมัติแล้วทับช่วงเวลา');

        $q=$db->prepare("SELECT re.work_date,st.start_time,st.end_time
            FROM roster_entries re JOIN rosters rr ON rr.id=re.roster_id
            JOIN shift_types st ON st.id=re.shift_type_id
            WHERE re.user_id=? AND rr.hospital_id=? AND rr.status IN ('PUBLISHED','LOCKED')
                AND st.is_working=1 AND st.start_time IS NOT NULL AND st.end_time IS NOT NULL
                AND re.work_date BETWEEN DATE_SUB(DATE(?),INTERVAL 1 DAY) AND DATE(?)");
        $q->execute([$replacement,$hospital,$start,$end]);
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $v) {
            $vs=$v['work_date'].' '.$v['start_time'];
            $ve=$v['work_date'].' '.$v['end_time'];
            if($ve<=$vs)$ve=(new DateTimeImmutable($ve))->modify('+1 day')->format('Y-m-d H:i:s');
            if(DutyPolicy::overlaps($start,$end,$vs,$ve))
                throw new RuntimeException('ผู้รับเวรแทนชนตารางเวร V11 ที่เผยแพร่แล้ว');
        }
    }

    public static function request(array $actor,int $assignmentId,int $replacement,string $reason):int
    {
        if($replacement===(int)$actor['id'])throw new InvalidArgumentException('ไม่สามารถแลกเวรกับตนเอง');
        if($reason===''||mb_strlen($reason)>400)throw new InvalidArgumentException('กรุณาระบุเหตุผลไม่เกิน 400 ตัวอักษร');
        $cipher=LeaveReasonCipher::encrypt($reason);
        $stored='ENC1:'.base64_encode((string)$cipher);
        $db=Database::pdo();$db->beginTransaction();
        try {
            $hospital=(int)$actor['hospital_id'];
            $q=$db->prepare("SELECT da.user_id,da.start_at,da.end_at,dr.revision,dr.status roster_status
                FROM duty_assignments da JOIN duty_rosters dr ON dr.id=da.duty_roster_id
                WHERE da.id=? AND dr.hospital_id=? AND da.status='ACTIVE' FOR UPDATE");
            $q->execute([$assignmentId,$hospital]);$assignment=$q->fetch(PDO::FETCH_ASSOC);
            if(!$assignment || (int)$assignment['user_id']!==(int)$actor['id'])
                throw new RuntimeException('ไม่พบเวรของตนที่สามารถยื่นเปลี่ยนได้');
            if(!in_array($assignment['roster_status'],['APPROVED','PUBLISHED','LOCKED'],true))
                throw new RuntimeException('สามารถขอเปลี่ยนได้เฉพาะเวรที่ผ่านการรับรองแล้ว');
            self::eligible($db,$replacement,$hospital,$assignment['start_at'],$assignment['end_at']);
            $pending=$db->prepare("SELECT id FROM shift_swaps WHERE assignment_id=?
                AND status IN ('PENDING_CONSENT','PENDING_DIRECTOR','PENDING_AUTHORITY','REVISION_REQUIRED')
                LIMIT 1");
            $pending->execute([$assignmentId]);
            if($pending->fetchColumn())throw new RuntimeException('มีคำขอเปลี่ยนเวรค้างอยู่สำหรับเวรนี้');
            $q=$db->prepare("INSERT INTO shift_swaps
                (hospital_id,assignment_id,requester_id,replacement_id,reason,expected_revision,status)
                VALUES (?,?,?,?,?,?,'PENDING_CONSENT')");
            $q->execute([$hospital,$assignmentId,(int)$actor['id'],$replacement,$stored,(int)$assignment['revision']]);
            $id=(int)$db->lastInsertId();
            self::audit($db,$hospital,(int)$actor['id'],$id,'REQUEST_SWAP',['assignment_id'=>$assignmentId,'replacement_id'=>$replacement]);
            $db->commit();return $id;
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }

    public static function consent(array $actor,int $id,bool $accept):void
    {
        $db=Database::pdo();$db->beginTransaction();
        try{
            $q=$db->prepare('SELECT * FROM shift_swaps WHERE id=? AND hospital_id=? FOR UPDATE');
            $q->execute([$id,(int)$actor['hospital_id']]);$swap=$q->fetch(PDO::FETCH_ASSOC);
            if(!$swap || $swap['status']!=='PENDING_CONSENT' || (int)$swap['replacement_id']!==(int)$actor['id'])
                throw new RuntimeException('ไม่มีคำขอที่รอความยินยอมจากคุณ');
            $next=$accept?'PENDING_DIRECTOR':'REJECTED';
            $q=$db->prepare("UPDATE shift_swaps SET status=? WHERE id=? AND status='PENDING_CONSENT'");
            $q->execute([$next,$id]);if($q->rowCount()!==1)throw new RuntimeException('สถานะคำขอเปลี่ยนไป');
            $q=$db->prepare("INSERT INTO swap_approvals (shift_swap_id,actor_id,step_code,decision)
                VALUES (?,?,'CONSENT',?)");
            $q->execute([$id,(int)$actor['id'],$accept?'APPROVED':'REJECTED']);
            self::audit($db,(int)$actor['hospital_id'],(int)$actor['id'],$id,'CONSENT_SWAP',['decision'=>$accept]);
            $db->commit();
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }

    public static function directorReview(array $actor,int $id,bool $endorse):void
    {
        if(!in_array((string)$actor['role'],['DIRECTOR','SUPERADMIN'],true))
            throw new RuntimeException('ไม่มีสิทธิ์ตรวจรับการเปลี่ยนเวร');
        $db=Database::pdo();$db->beginTransaction();
        try{
            $hospital=(int)$actor['hospital_id'];
            $q=$db->prepare("SELECT s.*,da.start_at,da.end_at,dr.revision,dr.status roster_status
                FROM shift_swaps s JOIN duty_assignments da ON da.id=s.assignment_id
                JOIN duty_rosters dr ON dr.id=da.duty_roster_id
                WHERE s.id=? AND s.hospital_id=? FOR UPDATE");
            $q->execute([$id,$hospital]);$swap=$q->fetch(PDO::FETCH_ASSOC);
            if(!$swap || $swap['status']!=='PENDING_DIRECTOR')
                throw new RuntimeException('คำขอไม่อยู่ในขั้นตอน ผอ.รพ.สต. ตรวจสอบ');
            if(in_array((int)$actor['id'],[(int)$swap['requester_id'],(int)$swap['replacement_id']],true))
                throw new RuntimeException('คู่สัญญาไม่สามารถตรวจรับคำขอของตนเอง');
            $q=$db->prepare("SELECT id FROM approval_delegations WHERE delegate_user_id=?
                 AND approval_scope='SHIFT_SWAP_REVIEW' AND (hospital_id=? OR hospital_id IS NULL)
                 AND effective_from<=CURDATE() AND (effective_to IS NULL OR effective_to>=CURDATE())
                 AND order_reference<>'' AND evidence_uri<>'' LIMIT 1");
            $q->execute([(int)$actor['id'],$hospital]);
            $delegationId=$q->fetchColumn();
            if(!$delegationId)throw new RuntimeException('ไม่มีคำสั่งมอบอำนาจตรวจรับการเปลี่ยนเวรที่มีผล');
            if((int)$swap['expected_revision']!==(int)$swap['revision'])
                throw new RuntimeException('ตารางเวรมี Revision ใหม่ ต้องตรวจรายการและยื่นคำขอฉบับใหม่');
            if($endorse){
                self::eligible($db,(int)$swap['replacement_id'],$hospital,$swap['start_at'],$swap['end_at']);
            }
            $next=$endorse?'PENDING_AUTHORITY':'REJECTED';
            $q=$db->prepare("UPDATE shift_swaps SET status=? WHERE id=? AND status='PENDING_DIRECTOR'");
            $q->execute([$next,$id]);if($q->rowCount()!==1)throw new RuntimeException('สถานะคำขอเปลี่ยนไป');
            $q=$db->prepare("INSERT INTO swap_approvals
                 (shift_swap_id,actor_id,step_code,decision,delegation_id)
                 VALUES (?,?,'DIRECTOR_REVIEW',?,?)");
            $q->execute([$id,(int)$actor['id'],$endorse?'APPROVED':'REJECTED',(int)$delegationId]);
            self::audit($db,$hospital,(int)$actor['id'],$id,'REVIEW_SWAP',['endorsed'=>$endorse,'next_status'=>$next]);
            $db->commit();
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }

    public static function reason(array $actor,int $id):string
    {
        $db=Database::pdo();$hospital=(int)$actor['hospital_id'];
        $q=$db->prepare('SELECT * FROM shift_swaps WHERE id=? AND hospital_id=? LIMIT 1');
        $q->execute([$id,$hospital]);$swap=$q->fetch(PDO::FETCH_ASSOC);
        if(!$swap)throw new RuntimeException('ไม่พบคำขอเปลี่ยนเวร');
        $partner=in_array((int)$actor['id'],[(int)$swap['requester_id'],(int)$swap['replacement_id']],true);
        if(!$partner){
            if(!in_array((string)$actor['role'],['DIRECTOR','SUPERADMIN'],true))
                throw new RuntimeException('ไม่มีสิทธิ์อ่านเหตุผลการเปลี่ยนเวร');
            $q=$db->prepare("SELECT 1 FROM approval_delegations WHERE delegate_user_id=?
                AND approval_scope='SHIFT_SWAP_REVIEW' AND (hospital_id=? OR hospital_id IS NULL)
                AND effective_from<=CURDATE() AND (effective_to IS NULL OR effective_to>=CURDATE())
                AND order_reference<>'' AND evidence_uri<>'' LIMIT 1");
            $q->execute([(int)$actor['id'],$hospital]);if(!$q->fetchColumn())
                throw new RuntimeException('ไม่มีคำสั่งมอบอำนาจอ่านเหตุผล');
        }
        $value=(string)$swap['reason'];
        if(!str_starts_with($value,'ENC1:'))throw new RuntimeException('คำขอเดิมไม่มีเหตุผลเข้ารหัสตามมาตรฐานที่รองรับ');
        $encoded=base64_decode(substr($value,5),true);
        if($encoded===false)throw new RuntimeException('ข้อมูลเหตุผลเข้ารหัสไม่ถูกต้อง');
        $reason=LeaveReasonCipher::decrypt($encoded);
        self::audit($db,$hospital,(int)$actor['id'],$id,'READ_SWAP_REASON',['partner'=>$partner]);
        return $reason;
    }
}
