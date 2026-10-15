<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class DutyCoverageService
{
    public static function scope(array $actor,int $hospital):void
    {
        if(!in_array((string)$actor['role'],['SUPERADMIN','ADMIN'],true))
            throw new RuntimeException('เฉพาะผู้ดูแลระบบสามารถตั้งค่าเกณฑ์ Duty');
        if($actor['role']!=='SUPERADMIN' && (int)$actor['hospital_id']!==$hospital)
            throw new RuntimeException('ผู้ดูแลระบบหน่วยบริการแก้เกณฑ์ได้เฉพาะหน่วยบริการของตน');
    }
    public static function hospitals(array $actor):array
    {
        $db=Database::pdo();$params=[];$sql='SELECT id,name FROM hospitals WHERE is_active=1';
        if($actor['role']!=='SUPERADMIN'){$sql.=' AND id=?';$params[]=(int)$actor['hospital_id'];}
        $q=$db->prepare($sql.' ORDER BY name');$q->execute($params);return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function data(array $actor,int $hospital):array
    {
        self::scope($actor,$hospital);
        $q=Database::pdo()->prepare('SELECT id,code,name FROM duty_types WHERE active=1 ORDER BY id');
        $q->execute();$types=$q->fetchAll(PDO::FETCH_ASSOC);
        $q=Database::pdo()->prepare("SELECT dt.code duty_code,r.skill_code,r.min_count,r.evidence_reference,
                 r.verified_at
             FROM duty_coverage_requirements r JOIN duty_types dt ON dt.id=r.duty_type_id
             WHERE r.hospital_id=? ORDER BY dt.id,r.skill_code");
        $q->execute([$hospital]);
        return ['types'=>$types,'skills'=>SkillPolicy::CATALOG,'requirements'=>$q->fetchAll(PDO::FETCH_ASSOC)];
    }
    public static function save(array $actor,int $hospital,array $rules):void
    {
        self::scope($actor,$hospital);
        $db=Database::pdo();
        $q=$db->prepare('SELECT id,code FROM duty_types WHERE active=1');$q->execute();
        $types=$q->fetchAll(PDO::FETCH_ASSOC);
        $typeByCode=[];foreach($types as $t)$typeByCode[$t['code']]=(int)$t['id'];
        if(count($rules)!==count($typeByCode))throw new InvalidArgumentException('ต้องระบุเกณฑ์ครบทุกประเภทเวรที่เปิดใช้งาน');
        $staffCount=$db->prepare("SELECT COUNT(*) FROM users WHERE hospital_id=? AND is_active=1 AND role IN ('STAFF','SCHEDULER','DIRECTOR')");
        $staffCount->execute([$hospital]);$eligible=(int)$staffCount->fetchColumn();
        $validated=[];
        foreach($rules as $r){
            if(!is_array($r) || !is_string($r['code']??null) || !isset($typeByCode[$r['code']]) || !is_array($r['minimums']??null))
                throw new InvalidArgumentException('รูปแบบเกณฑ์ไม่ถูกต้อง');
            $code=$r['code'];
            if(isset($validated[$code]))throw new InvalidArgumentException('ประเภทเวรซ้ำ');
            $evidence=$r['evidence_reference']??null;
            if(!is_string($evidence)||mb_strlen(trim($evidence))<8||mb_strlen(trim($evidence))>500)
                throw new InvalidArgumentException('ต้องระบุเลขที่/หลักฐานเกณฑ์อย่างน้อย 8 ตัวอักษร');
            $minima=$r['minimums'];
            if(!array_key_exists('__TOTAL__',$minima))throw new InvalidArgumentException('ขาดจำนวนบุคลากรขั้นต่ำ');
            $numbers=[];$allowed=['__TOTAL__'=>true]+array_fill_keys(array_keys(SkillPolicy::CATALOG),true);
            foreach($minima as $skill=>$value){
                if(!is_string($skill)||!isset($allowed[$skill])||!is_int($value)||$value<0||$value>100||$value>$eligible)
                    throw new InvalidArgumentException('จำนวนขั้นต่ำหรือรหัสทักษะไม่ถูกต้อง');
                $numbers[$skill]=$value;
            }
            foreach(array_keys(SkillPolicy::CATALOG) as $skill)
                if(!array_key_exists($skill,$numbers))
                    throw new InvalidArgumentException('กรุณายืนยันขั้นต่ำทุกทักษะ แม้ค่าเป็น 0');
            foreach($numbers as $skill=>$n)if($skill!=='__TOTAL__' && $n>$numbers['__TOTAL__'])
                throw new InvalidArgumentException('จำนวนขั้นต่ำของทักษะต้องไม่เกินจำนวนบุคลากรขั้นต่ำทั้งหมด');
            $validated[$code]=['evidence_reference'=>trim($evidence),'minima'=>$numbers];
        }
        $db->beginTransaction();
        try{
            $q=$db->prepare('SELECT id FROM hospitals WHERE id=? AND is_active=1 FOR UPDATE');
            $q->execute([$hospital]);if(!$q->fetchColumn())throw new RuntimeException('ไม่พบหน่วยบริการที่เปิดใช้งาน');
            $old=$db->prepare('SELECT duty_type_id,skill_code,min_count,evidence_reference FROM duty_coverage_requirements WHERE hospital_id=?');
            $old->execute([$hospital]);$oldRows=$old->fetchAll(PDO::FETCH_ASSOC);
            $db->prepare('DELETE FROM duty_coverage_requirements WHERE hospital_id=?')->execute([$hospital]);
            $q=$db->prepare('INSERT INTO duty_coverage_requirements
                (hospital_id,duty_type_id,skill_code,min_count,evidence_reference,verified_by)
                VALUES (?,?,?,?,?,?)');
            foreach($validated as $code=>$setting)
                foreach($setting['minima'] as $skill=>$min)
                    $q->execute([$hospital,$typeByCode[$code],$skill,$min,$setting['evidence_reference'],(int)$actor['id']]);
            $log=$db->prepare('INSERT INTO duty_audit_events (hospital_id,actor_id,action_code,record_type,record_id,change_summary) VALUES (?,?,?,?,?,?)');
            $log->execute([$hospital,(int)$actor['id'],'UPDATE_DUTY_COVERAGE','duty_coverage',$hospital,
                json_encode(['before'=>$oldRows,'after'=>$validated,'notice'=>'Review any pending/issued duty rosters after policy updates'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
            $db->commit();
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
}
