<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Read-only document snapshots. NO payment totals, approval, or write actions.
 * The printed result is only a preview and never an issued order.
 */
final class DutyDocumentPreviewService
{
    public static function thaiDigits(string $value): string
    {
        return strtr($value, ['0'=>'๐','1'=>'๑','2'=>'๒','3'=>'๓','4'=>'๔','5'=>'๕','6'=>'๖','7'=>'๗','8'=>'๘','9'=>'๙']);
    }
    public static function dateThai(string $date, bool $arabic=false): string
    {
        $dt=DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$dt || $dt->format('Y-m-d') !== $date) throw new InvalidArgumentException('Invalid document date');
        $names=[1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
        $render=$dt->format('j').' '.$names[(int)$dt->format('n')].' '.((int)$dt->format('Y')+543);
        return $arabic ? $render : self::thaiDigits($render);
    }
    public static function monthTitle(int $year,int $month,bool $arabic=false): string
    {
        if ($month<1 || $month>12 || $year<2000 || $year>2100) throw new InvalidArgumentException('Invalid document month');
        $d=sprintf('%04d-%02d-01',$year,$month);
        $full=self::dateThai($d,$arabic);
        $parts=explode(' ',$full);
        return $parts[1].' '.$parts[2];
    }
    /**
     * Turns duty assignments into a 31-day table. Excludes CANCELLED entries.
     * Allows multiple duty types on one date: e.g. บ/ร, ย/บ/ร.
     */
    public static function grid(int $year,int $month,array $staff,array $assignments): array
    {
        if ($year<2000 || $year>2100 || $month<1 || $month>12) throw new InvalidArgumentException('Invalid month');
        $max=(int)(new DateTimeImmutable(sprintf('%04d-%02d-01',$year,$month)))->format('t');
        $allowed=['ย'=>true,'บ'=>true,'ร'=>true];
        $rows=[];
        foreach ($staff as $person) {
            $uid=(int)($person['id']??0);
            if ($uid<1) continue;
            $rows[$uid]=[
                'id'=>$uid, 'name'=>(string)($person['full_name']??''),
                'position'=>(string)($person['position']??''),
                'cells'=>array_fill(1,31,[]),
                'totals'=>['ย'=>0,'บ'=>0,'ร'=>0],
            ];
        }
        $seen=[];
        foreach($assignments as $duty) {
            $id=(int)($duty['user_id']??0);
            $date=(string)($duty['work_date']??'');
            $code=(string)($duty['code']??'');
            if (!isset($rows[$id],$allowed[$code]) || ($duty['status']??'ACTIVE')!=='ACTIVE') continue;
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)
                || substr($date,0,7)!==sprintf('%04d-%02d',$year,$month)) continue;
            $day=(int)substr($date,8,2);
            if($day<1||$day>$max) continue;
            $key=$id.'|'.$date.'|'.$code;
            if(isset($seen[$key])) continue;
            $seen[$key]=true;
            $rows[$id]['cells'][$day][$code]=true;
            $rows[$id]['totals'][$code]++;
        }
        foreach($rows as &$r) {
            foreach($r['cells'] as $day=>&$cell) {
                if($day>$max) { $cell=''; continue; }
                $cell=implode('/',array_keys(array_filter([
                    'ย'=>isset($cell['ย']),'บ'=>isset($cell['บ']),'ร'=>isset($cell['ร'])
                ])));
            }
            unset($cell);
            $r['total']=array_sum($r['totals']);
        }
        unset($r);
        return ['days_in_month'=>$max,'rows'=>array_values($rows)];
    }
    public static function load(int $hospitalId,int $year,int $month): array
    {
        $db=Database::pdo();
        $q=$db->prepare("SELECT dr.id,dr.hospital_id,dr.roster_year,dr.roster_month,
                                dr.version,dr.revision,dr.status,h.name AS hospital_name,
                                h.hospital_code
                         FROM duty_rosters dr JOIN hospitals h ON h.id=dr.hospital_id
                         WHERE dr.hospital_id=? AND dr.roster_year=? AND dr.roster_month=?
                         ORDER BY dr.version DESC LIMIT 1");
        $q->execute([$hospitalId,$year,$month]);
        $r=$q->fetch(PDO::FETCH_ASSOC);
        if (!$r) throw new RuntimeException('ยังไม่พบตารางเวรของหน่วยบริการและเดือนที่เลือก');

        $q=$db->prepare("SELECT u.id,u.full_name,u.position
                         FROM duty_assignments da JOIN users u ON u.id=da.user_id
                         WHERE da.duty_roster_id=? AND da.status='ACTIVE'
                         GROUP BY u.id,u.full_name,u.position
                         ORDER BY u.full_name,u.id");
        $q->execute([(int)$r['id']]);
        $staff=$q->fetchAll(PDO::FETCH_ASSOC);

        $q=$db->prepare("SELECT da.user_id,da.work_date,dt.code,da.status
                         FROM duty_assignments da JOIN duty_types dt ON dt.id=da.duty_type_id
                         WHERE da.duty_roster_id=? AND da.status='ACTIVE'
                         ORDER BY da.user_id,da.work_date,dt.id");
        $q->execute([(int)$r['id']]);
        $entries=$q->fetchAll(PDO::FETCH_ASSOC);
        return [
            'roster'=>$r,'table'=>self::grid($year,$month,$staff,$entries),
            'staff'=>$staff
        ];
    }
    public static function swap(int $hospitalId,int $swapId): ?array
    {
        $q=Database::pdo()->prepare("SELECT s.id,s.status,s.created_at,s.reason,s.requester_id,
                       s.replacement_id,s.reciprocal_assignment_id,da.work_date,
                       dt.code AS duty_code,u1.full_name AS requester_name,
                       u1.position AS requester_position,u2.full_name AS replacement_name,
                       u2.position AS replacement_position,h.name AS hospital_name
                       FROM shift_swaps s
                       JOIN hospitals h ON h.id=s.hospital_id
                       JOIN duty_assignments da ON da.id=s.assignment_id
                       JOIN duty_types dt ON dt.id=da.duty_type_id
                       JOIN users u1 ON u1.id=s.requester_id
                       JOIN users u2 ON u2.id=s.replacement_id
                       WHERE s.id=? AND s.hospital_id=? LIMIT 1");
        $q->execute([$swapId,$hospitalId]);
        return $q->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
