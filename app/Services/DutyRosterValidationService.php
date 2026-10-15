<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;
use PDO;
use RuntimeException;

/**
 * Read-only, whole-roster pre-submit checks.
 * The result is advisory. Clinical coverage / competencies and authorization
 * must still be reviewed by an authorized human before issuing an order.
 * This service never calculates payroll, approves orders, or touches V11.
 */
final class DutyRosterValidationService
{
    private static function issue(array &$issues,string $severity,string $code,string $date,string $name,string $message): void
    {
        $issues[]=['severity'=>$severity,'code'=>$code,'work_date'=>$date,
            'person'=>$name,'message'=>$message];
    }

    /** Pure function suitable for regression tests without MySQL. */
    public static function analyze(array $roster,array $staff,array $duties,array $leaves,array $others,array $v11,array $holidays): array
    {
        $year=(int)$roster['roster_year'];
        $month=(int)$roster['roster_month'];
        $ym=sprintf('%04d-%02d',$year,$month);
        $active=[];
        foreach($staff as $person) {
            if ((int)($person['is_active']??1) !== 1) continue;
            if (!in_array($person['role']??'STAFF',['STAFF','SCHEDULER','DIRECTOR'],true)) continue;
            if ((int)$person['hospital_id'] !== (int)$roster['hospital_id']) continue;
            $active[(int)$person['id']]=(string)$person['full_name'];
        }
        $holidayDates=[];
        foreach($holidays as $h)$holidayDates[(string)$h['holiday_date']]=true;
        $issues=[];$entries=[];$count=['ย'=>0,'บ'=>0,'ร'=>0];
        $seen=[];
        foreach($duties as $d) {
            if(($d['status']??'ACTIVE')!=='ACTIVE')continue;
            $uid=(int)$d['user_id'];
            $name=$active[$uid]??'รหัสบุคลากร '.$uid;
            $date=(string)$d['work_date'];
            $code=(string)($d['code']??'');
            $id=(int)($d['id']??0);
            if(!isset($active[$uid])){
                self::issue($issues,'CRITICAL','INELIGIBLE_STAFF',$date,$name,'บุคลากรไม่ใช่ผู้ปฏิบัติงานที่เปิดใช้งานในหน่วยบริการ');
            }
            if(!in_array($code,['ย','บ','ร'],true)){
                self::issue($issues,'CRITICAL','UNKNOWN_DUTY_TYPE',$date,$name,'ประเภทเวรไม่รองรับ');continue;
            }
            try{[$start,$end]=DutyPolicy::interval($date,$code);}
            catch(\InvalidArgumentException $e){self::issue($issues,'CRITICAL','INVALID_DATE',$date,$name,'วันและประเภทเวรไม่ถูกต้อง');continue;}
            if(substr($date,0,7)!==$ym){
                self::issue($issues,'CRITICAL','WRONG_MONTH',$date,$name,'รายการเวรอยู่นอกรอบเดือน');continue;
            }
            if((string)($d['start_at']??'')!==$start || (string)($d['end_at']??'')!==$end)
                self::issue($issues,'CRITICAL','INTERVAL_MISMATCH',$date,$name,'เวลาเวรที่บันทึกไม่ตรงตามประเภทเวร');
            $key=$uid.'|'.$date.'|'.$code;
            if(isset($seen[$key]))self::issue($issues,'CRITICAL','DUPLICATE_DUTY',$date,$name,'พบเวรประเภทเดียวกันซ้ำในวันเดียวกัน');
            $seen[$key]=true;
            if($code==='ย'){
                $weekday=(int)(new DateTimeImmutable($date))->format('N');
                if($weekday<6&&!isset($holidayDates[$date]))
                    self::issue($issues,'CRITICAL','HOLIDAY_REQUIRED',$date,$name,'เวร ย อยู่ในวันราชการที่ไม่ถูกกำหนดเป็นวันหยุด');
            }
            $count[$code]++;
            $entries[]=['id'=>$id,'uid'=>$uid,'name'=>$name,'date'=>$date,'code'=>$code,'start'=>$start,'end'=>$end];
        }
        foreach($entries as $i=>$entry){
            foreach($entries as $j=>$another){
                if($j<=$i || $entry['uid']!==$another['uid'])continue;
                if(DutyPolicy::overlaps($entry['start'],$entry['end'],$another['start'],$another['end']))
                    self::issue($issues,'CRITICAL','TIME_OVERLAP',$entry['date'],$entry['name'],'ช่วงเวลาเวรทับซ้อนกับ '.$another['date'].' ('.$another['code'].')');
            }
            foreach($leaves as $leave){
                if((int)$leave['requester_id']!==$entry['uid'])continue;
                if(DutyPolicy::overlaps($entry['start'],$entry['end'],(string)$leave['starts_at'],(string)$leave['ends_at']))
                    self::issue($issues,'CRITICAL','LEAVE_OVERLAP',$entry['date'],$entry['name'],'ช่วงเวรทับซ้อนกับวันลาที่ได้รับอนุมัติ');
            }
            foreach($others as $other){
                if((int)$other['user_id']!==$entry['uid'])continue;
                if(DutyPolicy::overlaps($entry['start'],$entry['end'],(string)$other['start_at'],(string)$other['end_at']))
                    self::issue($issues,'CRITICAL','CONFIRMED_DUTY_OVERLAP',$entry['date'],$entry['name'],'ชนกับตาราง Duty ที่ได้รับการยืนยันในรอบเดือนอื่น');
            }
            foreach($v11 as $regular){
                if((int)$regular['user_id']!==$entry['uid'])continue;
                if(empty($regular['start_time'])||empty($regular['end_time']))continue;
                $rs=(string)$regular['work_date'].' '.(string)$regular['start_time'];
                $re=(string)$regular['work_date'].' '.(string)$regular['end_time'];
                if($re<=$rs)$re=(new DateTimeImmutable($re))->modify('+1 day')->format('Y-m-d H:i:s');
                if(DutyPolicy::overlaps($entry['start'],$entry['end'],$rs,$re))
                    self::issue($issues,'CRITICAL','V11_OVERLAP',$entry['date'],$entry['name'],'ชนกับตาราง V11 ที่เผยแพร่แล้ว');
            }
        }
        if(!$entries)self::issue($issues,'CRITICAL','EMPTY_ROSTER','—','—','ยังไม่มีรายการเวร ไม่สามารถส่งตรวจได้');
        // Rest is a review prompt, not an invented mandatory legal minimum.
        $byPerson=[];
        foreach($entries as $e)$byPerson[$e['uid']][]=$e;
        foreach($byPerson as $list){
            usort($list,fn($a,$b)=>strcmp($a['start'],$b['start']));
            for($i=0;$i+1<count($list);$i++){
                if($list[$i]['code']!=='ร')continue;
                $gap=strtotime($list[$i+1]['start'])-strtotime($list[$i]['end']);
                if($gap>=0 && $gap<8*3600)
                    self::issue($issues,'WARNING','REST_REVIEW',$list[$i+1]['date'],$list[$i+1]['name'],'ควรตรวจสอบความเหมาะสมของเวลาพักหลังเวร On Call');
            }
        }
        $critical=0;$warning=0;
        foreach($issues as $issue){if($issue['severity']==='CRITICAL')$critical++;else $warning++;}
        return [
            'roster_id'=>(int)($roster['id']??0),
            'revision'=>(int)($roster['revision']??0),
            'status'=>(string)($roster['status']??''),
            'assignment_count'=>count($entries),
            'counts'=>$count,
            'critical'=>$critical,'warning'=>$warning,'issues'=>$issues,
            'can_submit'=>$critical===0 && ($roster['status']??'')==='DRAFT',
            'coverage_review_required'=>true,
            'notice'=>'ยังต้องให้ผู้มีอำนาจตรวจความเพียงพอของอัตรากำลัง ทักษะวิชาชีพ และเอกสารประกอบ การผ่านระบบไม่ใช่คำสั่งอนุมัติ'
        ];
    }

    /**
     * Loads all clinical/roster evidence for the selected hospital and month.
     * No INSERT/UPDATE/DELETE. Caller may run within a locked transaction.
     */
    public static function report(array $roster): array
    {
        $db=Database::pdo();
        $hospital=(int)$roster['hospital_id'];$id=(int)$roster['id'];
        $begin=sprintf('%04d-%02d-01',(int)$roster['roster_year'],(int)$roster['roster_month']);
        $end=(new DateTimeImmutable($begin))->modify('+1 month')->format('Y-m-d');
        $windowEnd=(new DateTimeImmutable($end))->modify('+1 day')->format('Y-m-d');
        $prev=(new DateTimeImmutable($begin))->modify('-1 day')->format('Y-m-d');

        $staff=$db->prepare('SELECT id,hospital_id,full_name,role,is_active FROM users WHERE hospital_id=?');
        $staff->execute([$hospital]);
        $duties=$db->prepare('SELECT da.id,da.user_id,da.work_date,da.start_at,da.end_at,da.status,dt.code
            FROM duty_assignments da JOIN duty_types dt ON dt.id=da.duty_type_id
            WHERE da.duty_roster_id=? ORDER BY da.user_id,da.start_at,da.id');
        $duties->execute([$id]);
        $leaves=$db->prepare("SELECT requester_id,starts_at,ends_at FROM leave_requests
            WHERE hospital_id=? AND status='APPROVED' AND starts_at<? AND ends_at>?");
        $leaves->execute([$hospital,$windowEnd.' 00:00:00',$begin.' 00:00:00']);
        $others=$db->prepare("SELECT da.user_id,da.start_at,da.end_at FROM duty_assignments da
            JOIN duty_rosters dr ON dr.id=da.duty_roster_id
            WHERE dr.hospital_id=? AND da.duty_roster_id<>? AND da.status='ACTIVE'
              AND dr.status IN ('APPROVED','PUBLISHED','LOCKED')
              AND (dr.roster_year<>? OR dr.roster_month<>?)
              AND da.start_at<? AND da.end_at>?");
        $others->execute([$hospital,$id,(int)$roster['roster_year'],(int)$roster['roster_month'],$windowEnd.' 00:00:00',$begin.' 00:00:00']);
        $v11=$db->prepare("SELECT re.user_id,re.work_date,st.start_time,st.end_time
            FROM roster_entries re JOIN rosters rr ON rr.id=re.roster_id
            JOIN shift_types st ON st.id=re.shift_type_id
            WHERE rr.hospital_id=? AND rr.status IN ('PUBLISHED','LOCKED')
            AND st.is_working=1 AND st.start_time IS NOT NULL AND st.end_time IS NOT NULL
            AND re.work_date BETWEEN ? AND ?");
        $v11->execute([$hospital,$prev,$end]);
        $holidays=$db->prepare('SELECT holiday_date FROM holidays WHERE (hospital_id IS NULL OR hospital_id=?) AND holiday_date>=? AND holiday_date<?');
        $holidays->execute([$hospital,$begin,$end]);
        return self::analyze($roster,$staff->fetchAll(PDO::FETCH_ASSOC),$duties->fetchAll(PDO::FETCH_ASSOC),
            $leaves->fetchAll(PDO::FETCH_ASSOC),$others->fetchAll(PDO::FETCH_ASSOC),
            $v11->fetchAll(PDO::FETCH_ASSOC),$holidays->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Transactional gate. Changes only this selected DRAFT roster and appends an audit record. */
    public static function submit(array $candidate,int $actorId,int $expectedRevision): array
    {
        $db=Database::pdo();$db->beginTransaction();
        try{
            $q=$db->prepare('SELECT * FROM duty_rosters WHERE id=? AND hospital_id=? FOR UPDATE');
            $q->execute([(int)$candidate['id'],(int)$candidate['hospital_id']]);
            $roster=$q->fetch(PDO::FETCH_ASSOC);
            if(!$roster || $roster['status']!=='DRAFT')throw new RuntimeException('ส่งตรวจได้เฉพาะ Draft เท่านั้น');
            if((int)$roster['revision']!==$expectedRevision)throw new RuntimeException('มีผู้แก้ไขตารางเวรแล้ว กรุณาตรวจสอบอีกครั้ง');
            $report=self::report($roster);
            if($report['critical']>0)throw new RuntimeException('มีข้อผิดพลาด Critical '.$report['critical'].' รายการ กรุณาแก้ไขก่อนส่งตรวจ');
            $q=$db->prepare("UPDATE duty_rosters SET status='SUBMITTED',revision=revision+1
                WHERE id=? AND hospital_id=? AND status='DRAFT' AND revision=?");
            $q->execute([(int)$roster['id'],(int)$roster['hospital_id'],$expectedRevision]);
            if($q->rowCount()!==1)throw new RuntimeException('สถานะตารางเปลี่ยนระหว่างส่งตรวจ');
            $q=$db->prepare('INSERT INTO duty_audit_events (hospital_id,actor_id,action_code,record_type,record_id,change_summary) VALUES (?,?,?,?,?,?)');
            $q->execute([(int)$roster['hospital_id'],$actorId,'SUBMIT_DUTY_ROSTER','duty_roster',(int)$roster['id'],
                json_encode(['from'=>'DRAFT','to'=>'SUBMITTED','revision'=>$expectedRevision+1,
                    'critical'=>$report['critical'],'warning'=>$report['warning'],
                    'clinical_coverage_review_pending'=>true],JSON_THROW_ON_ERROR)]);
            $db->commit();
            $report['revision']=$expectedRevision+1;
            $report['status']='SUBMITTED';
            $report['can_submit']=false;
            return $report;
        }catch(\Throwable $e){
            if($db->inTransaction())$db->rollBack();
            throw $e;
        }
    }
}
