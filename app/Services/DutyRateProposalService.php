<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

/** Only proposes rates. No UPDATE to compensation_rates, no approval or payment. */
final class DutyRateProposalService
{
    public static function hospitals(int $actorHospital, bool $super): array
    {
        $sql = 'SELECT id,name FROM hospitals WHERE is_active=1';
        $args = [];
        if (!$super) { $sql .= ' AND id=?'; $args[]=$actorHospital; }
        $q=Database::pdo()->prepare($sql.' ORDER BY name');
        $q->execute($args);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function rules(): array
    {
        return Database::pdo()->query(
            "SELECT cr.group_id,g.name AS group_name,dt.code AS duty_code,cr.rule_version,
                    cr.rate_unit,cr.rate_amount,cr.effective_from,cr.effective_to,
                    cr.approval_status
             FROM compensation_rates cr JOIN compensation_groups g ON g.id=cr.group_id
             JOIN duty_types dt ON dt.id=cr.duty_type_id
             ORDER BY cr.group_id,dt.id,cr.id DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function proposals(array $actor, int $hospitalId): array
    {
        self::assertHospital($actor, $hospitalId);
        $q=Database::pdo()->prepare(
            "SELECT p.id,p.proposal_version,p.group_id,dt.code AS duty_code,
                    p.proposed_rate_unit,p.proposed_rate_amount,p.proposed_effective_from,
                    p.proposed_effective_to,p.on_call_pay_eligible,p.legal_reference,
                    p.reason,p.status,p.created_at
             FROM duty_rate_change_proposals p
             JOIN duty_types dt ON dt.id=p.duty_type_id
             WHERE p.hospital_id=? ORDER BY p.id DESC LIMIT 100"
        );
        $q->execute([$hospitalId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function propose(array $actor,int $hospitalId,array $raw): int
    {
        self::assertHospital($actor, $hospitalId);
        $p=DutyRateProposalPolicy::normalize($raw);
        $db=Database::pdo();
        $db->beginTransaction();
        try {
            // Serializes version allocation per hospital. No stale MAX(version) race.
            $q=$db->prepare('SELECT id FROM hospitals WHERE id=? AND is_active=1 FOR UPDATE');
            $q->execute([$hospitalId]);
            if (!$q->fetchColumn()) throw new RuntimeException('หน่วยบริการไม่เปิดใช้งาน');
            $q=$db->prepare('SELECT id FROM duty_types WHERE code=? AND active=1');
            $q->execute([$p['duty_code']]);
            $typeId=$q->fetchColumn();
            if (!$typeId) throw new RuntimeException('ไม่พบประเภทเวร');

            $q=$db->prepare('SELECT COALESCE(MAX(proposal_version),0)+1
                FROM duty_rate_change_proposals
                WHERE hospital_id=? AND group_id=? AND duty_type_id=?');
            $q->execute([$hospitalId,$p['group_id'],(int)$typeId]);
            $version=(int)$q->fetchColumn();
            $q=$db->prepare("INSERT INTO duty_rate_change_proposals
                 (hospital_id,group_id,duty_type_id,proposal_version,
                 proposed_rate_unit,proposed_rate_amount,on_call_pay_eligible,
                 proposed_effective_from,proposed_effective_to,legal_reference,reason,proposed_by,status)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'DRAFT')");
            $q->execute([
                $hospitalId,$p['group_id'],(int)$typeId,$version,
                $p['rate_unit'],$p['rate_amount'],$p['on_call_pay_eligible']?1:0,
                $p['effective_from'],$p['effective_to'],$p['legal_reference'],$p['reason'],(int)$actor['id']
            ]);
            $id=(int)$db->lastInsertId();
            $q=$db->prepare("INSERT INTO duty_rate_audit_events
                    (hospital_id,proposal_id,actor_id,action_code,after_snapshot)
                    VALUES (?,?,?,'CREATE_DRAFT_PROPOSAL',?)");
            $q->execute([$hospitalId,$id,(int)$actor['id'],json_encode([
                'group_id'=>$p['group_id'],'duty_code'=>$p['duty_code'],
                'proposal_version'=>$version,'rate_unit'=>$p['rate_unit'],
                'rate_amount'=>$p['rate_amount'],'effective_from'=>$p['effective_from'],
                'effective_to'=>$p['effective_to'],'on_call_pay_eligible'=>$p['on_call_pay_eligible']
            ],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
            $db->commit();
            return $id;
        }catch(\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }
    public static function assertHospital(array $actor,int $hospitalId): void
    {
        if (!in_array((string)($actor['role']??''),['SUPERADMIN','ADMIN'],true)) {
            throw new RuntimeException('ไม่มีสิทธิ์จัดการอัตราค่าตอบแทน');
        }
        if ($hospitalId<1 || ($actor['role']!=='SUPERADMIN' && $hospitalId!==(int)$actor['hospital_id'])) {
            throw new RuntimeException('ไม่มีสิทธิ์เข้าถึงหน่วยบริการนี้');
        }
    }
}
