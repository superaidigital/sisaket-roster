<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class LeaveRequestService
{
    public static function validDate(string $s):string
    {
        if(!preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:00$/D',$s))throw new InvalidArgumentException('วันเวลาไม่ถูกต้อง');
        $d=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$s);
        if(!$d || $d->format('Y-m-d H:i:s')!==$s)throw new InvalidArgumentException('วันเวลาไม่ถูกต้อง');
        return $s;
    }
    public static function types():array
    {
        return Database::pdo()->query('SELECT id,code,name FROM leave_types WHERE active=1 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function listing(array $actor):array
    {
        $db=Database::pdo();$hospital=(int)$actor['hospital_id'];
        $all=in_array((string)$actor['role'],['SUPERADMIN','ADMIN','DIRECTOR'],true);
        $sql='SELECT lr.id,lr.requester_id,u.full_name,lt.name AS leave_type,lr.starts_at,lr.ends_at,lr.status,lr.created_at
              FROM leave_requests lr JOIN users u ON u.id=lr.requester_id
              JOIN leave_types lt ON lt.id=lr.leave_type_id WHERE lr.hospital_id=?';
        $args=[$hospital];
        if(!$all){$sql.=' AND lr.requester_id=?';$args[]=(int)$actor['id'];}
        $q=$db->prepare($sql.' ORDER BY lr.created_at DESC,lr.id DESC LIMIT 100');
        $q->execute($args);return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    private static function audit(PDO $db,int $h,int $actor,string $action,int $id,array $summary):void
    {
        $q=$db->prepare('INSERT INTO duty_audit_events (hospital_id,actor_id,action_code,record_type,record_id,change_summary) VALUES (?,?,?,?,?,?)');
        $q->execute([$h,$actor,$action,'leave_request',$id,json_encode($summary,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
    }
    public static function submit(array $actor,int $typeId,string $start,string $end,string $reason):int
    {
        $start=self::validDate($start);$end=self::validDate($end);
        if($end<=$start)throw new InvalidArgumentException('วันสิ้นสุดต้องหลังวันเริ่มต้น');
        if(strtotime($end)-strtotime($start)>366*86400)throw new InvalidArgumentException('ช่วงวันลาต้องไม่เกิน 366 วัน');
        $encrypted=LeaveReasonCipher::encrypt($reason);
        $db=Database::pdo();$db->beginTransaction();
        try{
            $uid=(int)$actor['id'];$hospital=(int)$actor['hospital_id'];
            $u=$db->prepare('SELECT id FROM users WHERE id=? AND hospital_id=? AND is_active=1 FOR UPDATE');
            $u->execute([$uid,$hospital]);if(!$u->fetchColumn())throw new InvalidArgumentException('ไม่พบบัญชีบุคลากรที่เปิดใช้งาน');
            $q=$db->prepare('SELECT id FROM leave_types WHERE id=? AND active=1');
            $q->execute([$typeId]);if(!$q->fetchColumn())throw new InvalidArgumentException('ประเภทวันลาไม่ถูกต้อง');
            $q=$db->prepare("SELECT id FROM leave_requests WHERE requester_id=? AND status IN ('SUBMITTED','APPROVED')
                AND starts_at<? AND ends_at>? LIMIT 1 FOR UPDATE");
            $q->execute([$uid,$end,$start]);
            if($q->fetchColumn())throw new RuntimeException('มีคำขอลาที่ส่งแล้วหรืออนุมัติแล้วทับช่วงเวลา');
            $q=$db->prepare("INSERT INTO leave_requests (hospital_id,requester_id,leave_type_id,starts_at,ends_at,encrypted_reason,status)
                             VALUES (?,?,?,?,?,?,'SUBMITTED')");
            $q->execute([$hospital,$uid,$typeId,$start,$end,$encrypted]);
            $id=(int)$db->lastInsertId();
            self::audit($db,$hospital,$uid,'SUBMIT_LEAVE',$id,['starts_at'=>$start,'ends_at'=>$end,'type_id'=>$typeId]);
            $db->commit();return $id;
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
    public static function decision(array $actor,int $requestId,string $decision):void
    {
        if(!in_array($decision,['APPROVED','REJECTED'],true))throw new InvalidArgumentException('คำตัดสินไม่ถูกต้อง');
        if(!in_array((string)$actor['role'],['DIRECTOR','SUPERADMIN'],true))throw new RuntimeException('ไม่มีสิทธิ์พิจารณาใบลา');
        $db=Database::pdo();$db->beginTransaction();
        try{
            $hospital=(int)$actor['hospital_id'];$uid=(int)$actor['id'];
            $q=$db->prepare('SELECT * FROM leave_requests WHERE id=? AND hospital_id=? FOR UPDATE');
            $q->execute([$requestId,$hospital]);$leave=$q->fetch(PDO::FETCH_ASSOC);
            if(!$leave || $leave['status']!=='SUBMITTED')throw new RuntimeException('ใบลาไม่อยู่ในสถานะรอพิจารณา');
            if((int)$leave['requester_id']===$uid)throw new RuntimeException('ไม่สามารถอนุมัติใบลาของตนเอง');
            // Separate legal scope; do not reuse a roster-approval delegation.
            $q=$db->prepare("SELECT 1 FROM approval_delegations WHERE delegate_user_id=? AND approval_scope='LEAVE_APPROVAL'
                AND (hospital_id=? OR hospital_id IS NULL) AND effective_from<=CURDATE()
                AND (effective_to IS NULL OR effective_to>=CURDATE()) AND order_reference<>'' AND evidence_uri<>'' LIMIT 1");
            $q->execute([$uid,$hospital]);if(!$q->fetchColumn())throw new RuntimeException('ยังไม่มีคำสั่งมอบอำนาจอนุมัติวันลาที่ตรวจสอบได้');
            $start=$leave['starts_at'];$end=$leave['ends_at'];$staff=(int)$leave['requester_id'];
            if($decision==='APPROVED'){
                $q=$db->prepare("SELECT da.id FROM duty_assignments da
                     JOIN duty_rosters dr ON dr.id=da.duty_roster_id
                     WHERE da.user_id=? AND da.status='ACTIVE'
                       AND dr.status IN ('APPROVED','PUBLISHED','LOCKED')
                       AND da.start_at<? AND da.end_at>? LIMIT 1");
                $q->execute([$staff,$end,$start]);
                if($q->fetchColumn())throw new RuntimeException('พบเวรที่อนุมัติ/เผยแพร่แล้วทับช่วงวันลา ต้องดำเนินการปรับเวรก่อน');
                // Published/locked V11 shifts are immutable; resolve shift coverage before approval.
                $q=$db->prepare("SELECT re.work_date,st.start_time,st.end_time FROM roster_entries re
                    JOIN rosters rr ON rr.id=re.roster_id
                    JOIN shift_types st ON st.id=re.shift_type_id
                    WHERE re.user_id=? AND rr.hospital_id=?
                    AND rr.status IN ('PUBLISHED','LOCKED') AND st.is_working=1
                    AND st.start_time IS NOT NULL AND st.end_time IS NOT NULL
                    AND re.work_date BETWEEN DATE_SUB(DATE(?),INTERVAL 1 DAY) AND DATE(?)");
                $q->execute([$staff,$hospital,$start,$end]);
                foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){
                    $rs=$row['work_date'].' '.$row['start_time'];
                    $re=$row['work_date'].' '.$row['end_time'];
                    if($re<=$rs)$re=(new DateTimeImmutable($re))->modify('+1 day')->format('Y-m-d H:i:s');
                    if(DutyPolicy::overlaps($start,$end,$rs,$re))
                        throw new RuntimeException('พบเวร V11 ที่เผยแพร่แล้วทับช่วงลา กรุณาจัดการเวรก่อนอนุมัติใบลา');
                }

            }
            $q=$db->prepare("UPDATE leave_requests SET status=?,approver_id=?,approved_at=NOW() WHERE id=? AND status='SUBMITTED'");
            $q->execute([$decision,$uid,$requestId]);
            if($q->rowCount()!==1)throw new RuntimeException('สถานะใบลาเปลี่ยนระหว่างดำเนินการ');
            self::audit($db,$hospital,$uid,'DECIDE_LEAVE',$requestId,['decision'=>$decision,'authority'=>'LEAVE_APPROVAL']);
            $db->commit();
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
}
