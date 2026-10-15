<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

/** Guarded, audited V13 reviewer transitions. Never writes V11/payroll. */
final class DutyReviewService
{
    public static function hasDelegation(PDO $db,array $actor,int $hospital,string $workDate): bool
    {
        $query=$db->prepare("SELECT 1 FROM approval_delegations
            WHERE delegate_user_id=? AND approval_scope=?
              AND (hospital_id=? OR hospital_id IS NULL)
              AND effective_from<=? AND (effective_to IS NULL OR effective_to>=?)
              AND order_reference<>'' AND evidence_uri<>''
              LIMIT 1");
        $query->execute([(int)$actor['id'],DutyReviewPolicy::SCOPE,$hospital,$workDate,$workDate]);
        return (bool)$query->fetchColumn();
    }
    public static function transition(array $actor,int $hospital,int $rosterId,int $revision,string $action):array
    {
        $target=DutyReviewPolicy::target($action);
        if(!$target)throw new RuntimeException('ไม่รู้จักการดำเนินการ');
        $db=Database::pdo();$db->beginTransaction();
        try{
            $q=$db->prepare('SELECT * FROM duty_rosters WHERE id=? AND hospital_id=? FOR UPDATE');
            $q->execute([$rosterId,$hospital]);
            $roster=$q->fetch(PDO::FETCH_ASSOC);
            if(!$roster)throw new RuntimeException('ไม่พบตารางเวร');
            if((int)$roster['revision']!==$revision)throw new RuntimeException('Revision เปลี่ยนแล้ว กรุณาโหลดหน้าใหม่');
            $today=date('Y-m-d');
            $delegated=in_array($action,['approve','reject'],true)
                && self::hasDelegation($db,$actor,$hospital,$today);
            if(!DutyReviewPolicy::allowed((string)$actor['role'],(string)$roster['status'],$action,$delegated))
                throw new RuntimeException('ไม่มีสิทธิ์หรือไม่มีคำสั่งมอบอำนาจที่มีผลสำหรับการดำเนินการนี้');
            if($action==='approve'){
                if((int)$roster['created_by']===(int)$actor['id'])
                    throw new RuntimeException('ผู้จัดทำไม่สามารถอนุมัติตารางของตนเอง');
                $result=DutyRosterValidationService::report($roster);
                if((int)$result['critical']>0)throw new RuntimeException('ต้องแก้ Critical '.$result['critical'].' รายการก่อนอนุมัติ');
                // Clinical skill adequacy is intentionally NOT auto-certified here.
                // It is independently attested by a reviewer through the UI.
            }
            $q=$db->prepare("UPDATE duty_rosters SET status=?,revision=revision+1,
                    approved_by=CASE WHEN ?='approve' THEN ? WHEN ?='reject' THEN NULL ELSE approved_by END,
                    approved_at=CASE WHEN ?='approve' THEN NOW() WHEN ?='reject' THEN NULL ELSE approved_at END
                    WHERE id=? AND hospital_id=? AND status=? AND revision=?");
            $q->execute([$target,$action,(int)$actor['id'],$action,$action,$action,
                $rosterId,$hospital,(string)$roster['status'],$revision]);
            if($q->rowCount()!==1)throw new RuntimeException('ไม่สามารถเปลี่ยนสถานะตารางเวร');
            $q=$db->prepare('INSERT INTO duty_audit_events
                 (hospital_id,actor_id,action_code,record_type,record_id,change_summary)
                 VALUES (?,?,?,?,?,?)');
            $q->execute([$hospital,(int)$actor['id'],'DUTY_'.strtoupper($action),
                'duty_roster',$rosterId,json_encode([
                'before'=>(string)$roster['status'],'after'=>$target,
                'revision_before'=>$revision,'revision_after'=>$revision+1,
                'delegation_scope'=>$delegated?DutyReviewPolicy::SCOPE:null,
                'clinical_review_attested'=>$action==='approve',
                'note'=>'Workflow state only; not a digital signature or legally issued order'
            ],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
            $db->commit();
            return ['id'=>$rosterId,'status'=>$target,'revision'=>$revision+1];
        }catch(\Throwable $e){
            if($db->inTransaction())$db->rollBack();
            throw $e;
        }
    }
}
