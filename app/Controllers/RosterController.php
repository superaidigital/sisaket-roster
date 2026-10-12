<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Auth,Csrf,Database};
use App\Models\RosterModel as M;
use App\Services\{RosterValidator as V,AutoScheduler as S,ExcelExporter,RosterReadiness,WorkflowPolicy,VersionDiff,AuditLogPresenter,RosterRepairPlanner,FairnessAnalyzer,FairnessBalancePlanner,SecurityPolicy};

final class RosterController {
    private function startPost(): array { requirePost(); Csrf::check(); return Auth::requireUser(); }
    private function postData(): array { return jsonBody(); }
    private function roster(array $u, mixed $id, bool $mustEdit=false): array {
        $id=intInput($id,1,2147483647,'รหัสตาราง');
        $r=M::one($id);
        if (!$r || !M::hospital((int)$r['hospital_id'])) jsonError('ไม่พบตารางเวร',404);
        Auth::hospitalId($u,(int)$r['hospital_id']);
        if (!SecurityPolicy::rosterReadable((string)$u['role'],(string)$r['status'])) jsonError('ตารางยังไม่เผยแพร่',403);
        if ($mustEdit) {
            Auth::requireRole($u,['SUPERADMIN','ADMIN','SCHEDULER']);
            if (!SecurityPolicy::rosterEditable((string)$u['role'],(string)$r['status']))
                jsonError('ตารางไม่อยู่ในสถานะ Draft',409);
        }
        return $r;
    }
    private function checkRevision(array $r,array $p): void {
        $value=intInput($p['expected_revision'] ?? null,0,2147483647,'revision');
        if((int)$r['lock_version']!==$value) jsonError('พบการแก้ไขตารางจากผู้ใช้อื่น กรุณาโหลดข้อมูลใหม่',409,['conflict'=>true,'server_revision'=>(int)$r['lock_version']]);
    }
    public function index(): void {
        $user=Auth::requireUser(); $hospitals=Auth::allowed($user,['SUPERADMIN','ADMIN']) ? M::hospitals() : [];
        require dirname(__DIR__).'/Views/roster/index.php';
    }
    public function data(): void {
        $u=Auth::requireUser(); [$year,$month]=monthInput($_GET);
        $hospital=Auth::hospitalId($u,isset($_GET['hospital_id'])?intInput($_GET['hospital_id'],1,2147483647,'หน่วยบริการ'):null);
        $h=M::hospital($hospital); if(!$h) jsonError('ไม่พบหน่วยบริการ',404);
        $r=M::latest($hospital,$year,$month,$u['role']==='STAFF');
        $staff=M::rosterStaff($hospital,$r?(int)$r['id']:null); // Include historical assignees; only eligible persons can receive shifts.
        $entries=$r ? M::entries((int)$r['id']) : [];
        $holidays=M::holidays($hospital,$year,$month);
        $shifts=M::shifts();
        $stats=$this->stats($staff,$entries,$shifts,$holidays);
        $settings=M::settings($hospital);
        $validation=$r ? V::run($r) : null;
        $readiness=RosterReadiness::assess($r,$staff,$shifts,$settings,$validation,count($entries),(string)$u['role']);
        $options=['can_edit'=>Auth::canEdit($u),'can_submit'=>Auth::canEdit($u),
            'can_approve'=>Auth::allowed($u,['SUPERADMIN','DIRECTOR']),
            'can_publish'=>Auth::allowed($u,['SUPERADMIN','ADMIN','DIRECTOR']),
            'can_lock'=>Auth::allowed($u,['SUPERADMIN','ADMIN','DIRECTOR']),
            'can_audit'=>($u['role']==='SUPERADMIN'), 'can_settings'=>Auth::allowed($u,['SUPERADMIN','ADMIN'])];
        $eligibleIds=[];
        foreach($staff as $person) if(!empty($person['eligible']))$eligibleIds[(int)$person['id']]=true;
        $eligibleStats=array_values(array_filter($stats['per_person'],
            static fn($person)=>isset($eligibleIds[(int)$person['user_id']])));
        jsonOut(['success'=>true,'hospital'=>$h,'roster'=>$r,'staff'=>$staff,'entries'=>$entries,
            'shifts'=>$shifts,'holidays'=>$holidays,'settings'=>$settings,
            'stats'=>$stats,'fairness'=>FairnessAnalyzer::report($eligibleStats),
            'skill_requirements_count'=>count(M::requirements($hospital)),
            'readiness'=>$readiness,'permissions'=>$options,'year'=>$year,'month'=>$month]);
    }
    private function stats(array $staff,array $entries,array $shifts,array $holidays): array {
        $codes=[]; foreach($shifts as $s) $codes[(int)$s['id']]=$s;
        $holidayKeys=array_fill_keys(array_column($holidays,'holiday_date'),true);
        $totals=['MORNING'=>0,'AFTERNOON'=>0,'NIGHT'=>0,'OFF'=>0,'LEAVE'=>0,'working_hours'=>0];
        $people=[]; foreach($staff as $s) $people[(int)$s['id']]=['user_id'=>(int)$s['id'],'full_name'=>$s['full_name'],'MORNING'=>0,'AFTERNOON'=>0,'NIGHT'=>0,'OFF'=>0,'LEAVE'=>0,'working_hours'=>0,'weekend_shifts'=>0,'holiday_shifts'=>0];
        foreach($entries as $e) {
            $shift=$codes[(int)$e['shift_type_id']] ?? null; if(!$shift) continue;
            $code=$shift['code']; $uid=(int)$e['user_id'];
            if(!isset($people[$uid])) continue;
            if(!array_key_exists($code,$people[$uid])) $people[$uid][$code]=0;
            if(!array_key_exists($code,$totals)) $totals[$code]=0;
            $totals[$code]++; $people[$uid][$code]++;
            if((int)$shift['is_working']) {
                $start=strtotime('2026-01-01 '.($shift['start_time'] ?? '00:00:00'));
                $end=strtotime('2026-01-01 '.($shift['end_time'] ?? '00:00:00'));
                $hours=($end-$start)/3600; if($hours<=0) $hours+=24;
                $people[$uid]['working_hours']+=$hours; $totals['working_hours']+=$hours;
                if((int)date('N',strtotime($e['work_date']))>=6) $people[$uid]['weekend_shifts']++;
                if(isset($holidayKeys[$e['work_date']])) $people[$uid]['holiday_shifts']++;
            }
        }
        return ['totals'=>$totals,'per_person'=>array_values($people),'all_shifts'=>count($entries)];
    }
    public function create(): void {
        $u=$this->startPost(); Auth::requireRole($u,['SUPERADMIN','ADMIN','SCHEDULER']); $in=$this->postData();
        [$year,$month]=monthInput($in);
        $h=Auth::hospitalId($u,isset($in['hospital_id'])?intInput($in['hospital_id'],1,2147483647,'หน่วยบริการ'):null);
        if(!M::hospital($h)) jsonError('หน่วยบริการไม่ถูกต้อง',404);
        $r=M::create($h,$year,$month,(int)$u['id']); jsonOut(['success'=>true,'roster'=>$r]);
    }
    public function save_shift(): void {
        $u=$this->startPost(); $in=$this->postData(); $r=$this->roster($u,$in['roster_id'] ?? null,true);
        $this->checkRevision($r,$in);
        $change=['user_id'=>$in['user_id'] ?? null,'date'=>$in['date'] ?? null,'shift_type_id'=>$in['shift_type_id'] ?? null,'note'=>$in['note'] ?? ''];
        $save=M::saveCells((int)$r['id'],(int)$u['id'],(int)$r['lock_version'],[$change]);
        $result=V::run($r); jsonOut(['success'=>true,'message'=>'บันทึกเวรแล้ว','validation'=>$result['counts']]+$save);
    }
    public function bulk_save(): void {
        $u=$this->startPost(); $in=$this->postData(); $r=$this->roster($u,$in['roster_id'] ?? null,true);
        $this->checkRevision($r,$in);
        $changes=$in['changes'] ?? null;
        if(!is_array($changes)) jsonError('changes ต้องเป็นรายการ',422);
        $save=M::saveCells((int)$r['id'],(int)$u['id'],(int)$r['lock_version'],$changes);
        jsonOut(['success'=>true,'message'=>'บันทึกหลายช่องเรียบร้อย']+$save);
    }
    public function validate(): void {
        $u=Auth::requireUser();
        $r=$this->roster($u,$_GET['roster_id'] ?? null);
        $result=V::run($r);
        jsonOut(['success'=>true,'validation'=>$result]);
    }
    public function auto_preview(): void {
        $u=$this->startPost(); $in=$this->postData(); $r=$this->roster($u,$in['roster_id'] ?? null,true);
        $this->checkRevision($r,$in); $plan=S::plan($r);
        jsonOut(['success'=>true,'plan'=>$plan]);
    }
    public function auto_apply(): void {
        $u=$this->startPost(); $in=$this->postData(); $r=$this->roster($u,$in['roster_id'] ?? null,true);
        $this->checkRevision($r,$in); $plan=S::plan($r);
        if(!$plan['changes']) jsonOut(['success'=>true,'message'=>'ไม่มีช่องที่ต้องเพิ่ม','saved'=>0,'revision'=>(int)$r['lock_version']]);
        $result=M::saveCells((int)$r['id'],(int)$u['id'],(int)$r['lock_version'],$plan['changes'],'AUTO_SCHEDULE');
        jsonOut(['success'=>true,'message'=>'จัดเวรอัตโนมัติแล้ว','unfilled'=>$plan['unfilled']]+$result);
    }
    /** A proposed repair is always recomputed server-side; no client-supplied cell edits are trusted. */
    public function repair_preview(): void {
        $user=$this->startPost(); $input=$this->postData();
        $r=$this->roster($user,$input['roster_id']??null,true);
        $this->checkRevision($r,$input);
        $plan=RosterRepairPlanner::plan($r);
        jsonOut(['success'=>true,'plan'=>$plan]);
    }
    public function repair_apply(): void {
        $user=$this->startPost(); $input=$this->postData();
        $r=$this->roster($user,$input['roster_id']??null,true);
        $this->checkRevision($r,$input);
        $plan=RosterRepairPlanner::plan($r);
        if(!$plan['changes']) jsonError('ยังไม่พบการสลับเวรที่ลด Critical ได้ กรุณาแก้ไขด้วยตนเอง',422);
        $token=(string)($input['preview_hash']??'');
        if($token==='' || !hash_equals($plan['preview_hash'],$token)) {
            jsonError('ข้อเสนอซ่อมเปลี่ยนไป กรุณา Preview ใหม่ก่อนยืนยัน',409,['conflict'=>true]);
        }
        $result=M::saveCells((int)$r['id'],(int)$user['id'],(int)$r['lock_version'],$plan['changes'],'REPAIR_SHIFT');
        $after=V::run($r);
        jsonOut(['success'=>true,'message'=>'บันทึกการซ่อมตารางเวรแล้ว','remaining_critical'=>$after['counts']['CRITICAL'],
            'remaining_warning'=>$after['counts']['WARNING'],'validation'=>$after['counts']]+$result);
    }
    /** V10 fairness proposals never change saved shifts until explicitly confirmed. */
    public function fairness_preview(): void {
        $u=$this->startPost();$in=$this->postData();$r=$this->roster($u,$in['roster_id']??null,true);
        $this->checkRevision($r,$in);
        jsonOut(['success'=>true,'plan'=>FairnessBalancePlanner::plan($r)]);
    }
    public function fairness_apply(): void {
        $u=$this->startPost();$in=$this->postData();$r=$this->roster($u,$in['roster_id']??null,true);
        $this->checkRevision($r,$in);
        $plan=FairnessBalancePlanner::plan($r);
        if(!$plan['changes'])jsonError('ไม่พบการสลับเวรที่ปรับสมดุลได้โดยไม่สร้างข้อผิดพลาด',422);
        if(!is_string($in['preview_hash']??null)||!hash_equals($plan['preview_hash'],$in['preview_hash']))
            jsonError('Preview เปลี่ยนไป กรุณาตรวจรายการใหม่ก่อนยืนยัน',409,['conflict'=>true]);
        $result=M::saveCells((int)$r['id'],(int)$u['id'],(int)$r['lock_version'],$plan['changes'],'FAIRNESS_BALANCE');
        $after=V::run($r);
        jsonOut(['success'=>true,'validation'=>$after['counts'],'message'=>'บันทึกการปรับสมดุลแล้ว']+$result);
    }
    private function copyPlan(array $r): array {
        $first=sprintf('%04d-%02d-01',$r['year'],$r['month']); $previous=date('Y-m-01',strtotime($first.' -1 day'));
        $source=M::latest((int)$r['hospital_id'],(int)substr($previous,0,4),(int)substr($previous,5,2),false);
        if(!$source) jsonError('ไม่พบตารางเดือนก่อน',404);
        $targetDates=(int)date('t',strtotime($first)); $eligible=[];
        foreach(M::staff((int)$r['hospital_id']) as $s) $eligible[(int)$s['id']]=true;
        $existing=[]; foreach(M::entries((int)$r['id']) as $e) $existing[$e['user_id'].'|'.$e['work_date']]=true;
        $changes=[]; $inactive=[]; $skippedDays=0; $occupied=0;
        foreach(M::entries((int)$source['id']) as $e) {
            $uid=(int)$e['user_id'];
            if(!isset($eligible[$uid])) { $inactive[$uid]=true; continue; }
            $day=(int)substr($e['work_date'],8,2);
            if($day>$targetDates) { $skippedDays++; continue; }
            $date=sprintf('%04d-%02d-%02d',$r['year'],$r['month'],$day);
            if(isset($existing[$uid.'|'.$date])) { $occupied++; continue; }
            $changes[]=['user_id'=>$uid,'date'=>$date,'shift_type_id'=>(int)$e['shift_type_id'],'note'=>$e['note']];
        }
        return ['source'=>$source,'changes'=>$changes,'inactive_count'=>count($inactive),'invalid_days'=>$skippedDays,'existing_skipped'=>$occupied,'eligible_staff_count'=>count($eligible)];
    }
    public function copy_preview(): void {
        $u=$this->startPost(); $in=$this->postData(); $r=$this->roster($u,$in['roster_id'] ?? null,true);
        $this->checkRevision($r,$in); $plan=$this->copyPlan($r);
        jsonOut(['success'=>true,'preview'=>['source_version'=>$plan['source']['version'],'source_status'=>$plan['source']['status'],
            'to_copy'=>count($plan['changes']),'inactive_count'=>$plan['inactive_count'],
            'invalid_days'=>$plan['invalid_days'],'existing_skipped'=>$plan['existing_skipped'],'eligible_staff_count'=>$plan['eligible_staff_count']]]);
    }
    public function copy_apply(): void {
        $u=$this->startPost(); $in=$this->postData(); $r=$this->roster($u,$in['roster_id'] ?? null,true);
        $this->checkRevision($r,$in); $plan=$this->copyPlan($r);
        if(!$plan['changes']) jsonError('ไม่มีข้อมูลสำหรับคัดลอก',422);
        $result=M::saveCells((int)$r['id'],(int)$u['id'],(int)$r['lock_version'],$plan['changes'],'COPY_MONTH');
        jsonOut(['success'=>true,'message'=>'คัดลอกเดือนก่อนแล้ว']+$result);
    }
    private function transition(string $action,array $u,array $p): void {
        $r=$this->roster($u,$p['roster_id'] ?? null);
        $this->checkRevision($r,$p);
        $step=WorkflowPolicy::step($action);
        if (!$step) jsonError('ไม่รู้จักการเปลี่ยนสถานะ',422);
        if (!WorkflowPolicy::roleAllows($action,(string)$u['role'])) jsonError('ไม่มีสิทธิ์ดำเนินการ',403);
        $from=$step['from']; $to=$step['to']; $by=$step['by']; $at=$step['at'];
        if ($r['status']!==$from) jsonError("สถานะปัจจุบัน {$r['status']} ไม่อนุญาตให้ {$action}",409);
        $reason=trim((string)($p['reason'] ?? ''));
        if($action==='REJECT' && mb_strlen($reason)<3) jsonError('กรุณาระบุเหตุผลที่ส่งคืนอย่างน้อย 3 ตัวอักษร',422);
        if(mb_strlen($reason)>500) jsonError('เหตุผลยาวเกิน 500 ตัวอักษร',422);
        $db=Database::pdo(); $db->beginTransaction();
        try {
            $q=$db->prepare('SELECT * FROM rosters WHERE id=? FOR UPDATE'); $q->execute([$r['id']]); $current=$q->fetch();
            if(!$current || $current['status']!==$from || (int)$current['lock_version']!==(int)$r['lock_version']) {
                throw new \RuntimeException('CONFLICT: สถานะ/Revision เปลี่ยนไป กรุณาโหลดใหม่');
            }
            // Validate after locking roster, so no concurrent cell save can slip between check and transition.
            $validation=V::run($current);
            $eligibles=M::rosterStaff((int)$current['hospital_id'],(int)$current['id']);
            $allEntries=M::entries((int)$current['id']);
            $readiness=RosterReadiness::assess($current,$eligibles,M::shifts(),M::settings((int)$current['hospital_id']),$validation,count($allEntries),(string)$u['role']);
            if ($step['check'] && !$readiness['ready']) {
                $detail=implode('; ',array_slice($readiness['issues'],0,2));
                throw new \DomainException('VALIDATION: ตารางยังไม่พร้อมดำเนินการ '.$detail);
            }
            V::persist((int)$r['id'],$validation);
            $sql='UPDATE rosters SET status=?,lock_version=lock_version+1'; $args=[$to];
            if($by) { $sql.=', '.$by.'=?,'.$at.'=NOW()'; $args[]=(int)$u['id']; }
            if($action==='REJECT') $sql.=',approved_by=NULL,approved_at=NULL';
            $sql.=' WHERE id=?'; $args[]=(int)$r['id'];
            $q=$db->prepare($sql); $q->execute($args);
            $snapshot=M::snapshot((int)$r['id']);
            $q=$db->prepare('INSERT INTO roster_versions (roster_id,version,action,changed_by,reason,before_snapshot,after_snapshot) VALUES (?,?,?,?,?,?,?)');
            $q->execute([$r['id'],$r['version'],$action,$u['id'],$reason,$snapshot,$snapshot]);
            M::audit($db,(int)$u['id'],$action,(int)$r['id'],(int)$r['hospital_id'],null,['status'=>$from],['status'=>$to,'reason'=>$reason]);
            $db->commit(); jsonOut(['success'=>true,'message'=>'เปลี่ยนสถานะเป็น '.$to,'status'=>$to,'revision'=>(int)$r['lock_version']+1,'validation'=>$validation['counts']]);
        } catch (\Throwable $e) {
            if($db->inTransaction()) $db->rollBack();
            if($e instanceof \DomainException && str_starts_with($e->getMessage(),'VALIDATION:')) {
                $latest=V::run($r); jsonError(substr($e->getMessage(),12),422,['validation'=>$latest]);
            }
            if($e instanceof \RuntimeException && str_starts_with($e->getMessage(),'CONFLICT:')) {
                jsonError(substr($e->getMessage(),10),409,['conflict'=>true]);
            }
            throw $e;
        }
    }
    public function submit(): void { $u=$this->startPost(); $this->transition('SUBMIT',$u,$this->postData()); }
    public function approve(): void { $u=$this->startPost(); $this->transition('APPROVE',$u,$this->postData()); }
    public function reject(): void { $u=$this->startPost(); $this->transition('REJECT',$u,$this->postData()); }
    public function publish(): void { $u=$this->startPost(); $this->transition('PUBLISH',$u,$this->postData()); }
    public function lock(): void { $u=$this->startPost(); $this->transition('LOCK',$u,$this->postData()); }
    public function new_revision(): void {
        $u=$this->startPost(); Auth::requireRole($u,['SUPERADMIN','ADMIN','SCHEDULER']); $in=$this->postData();
        $r=$this->roster($u,$in['roster_id'] ?? null);
        $this->checkRevision($r,$in);
        if(!in_array($r['status'],['PUBLISHED','LOCKED'],true)) jsonError('สร้าง Version ใหม่หลังเผยแพร่เท่านั้น',409);
        $latest=M::latest((int)$r['hospital_id'],(int)$r['year'],(int)$r['month'],false);
        if(!$latest || (int)$latest['id']!==(int)$r['id']) jsonError('มี Version ใหม่กว่านี้แล้ว กรุณาเปิดตารางล่าสุด',409);
        $reason=trim((string)($in['reason'] ?? ''));
        if(mb_strlen($reason)<3 || mb_strlen($reason)>500) jsonError('ระบุเหตุผลการปรับปรุง 3–500 ตัวอักษร',422);
        $created=M::newRevision($r,(int)$u['id'],$reason,(int)$r['lock_version']); jsonOut(['success'=>true,'roster'=>$created]);
    }
    /** V6 history and comparison are management-only. STAFF must never see a draft version. */
    private function versionViewer(array $user): void {
        if (!SecurityPolicy::canViewVersionHistory((string)$user['role'])) jsonError('ไม่มีสิทธิ์ดูประวัติ Version',403);
    }
    public function version_history(): void {
        $user=Auth::requireUser(); $this->versionViewer($user);
        [$year,$month]=monthInput($_GET);
        $hospital=Auth::hospitalId($user,isset($_GET['hospital_id'])?intInput($_GET['hospital_id'],1,2147483647,'หน่วยบริการ'):null);
        if(!M::hospital($hospital)) jsonError('ไม่พบหน่วยบริการ',404);
        jsonOut(['success'=>true,'versions'=>M::versionHistory($hospital,$year,$month)]);
    }
    public function version_compare(): void {
        $user=Auth::requireUser(); $this->versionViewer($user);
        $a=$this->roster($user,$_GET['from_id']??null);
        $b=$this->roster($user,$_GET['to_id']??null);
        if((int)$a['hospital_id']!==(int)$b['hospital_id'] || (int)$a['year']!==(int)$b['year'] || (int)$a['month']!==(int)$b['month'])
            jsonError('เปรียบเทียบได้เฉพาะ Version ของหน่วยบริการและเดือนเดียวกัน',422);
        $staff=array_merge(M::rosterStaff((int)$a['hospital_id'],(int)$a['id']), M::rosterStaff((int)$b['hospital_id'],(int)$b['id']));
        $names=[]; foreach($staff as $person) $names[(int)$person['id']]=$person['full_name'];
        jsonOut(['success'=>true,'from'=>['id'=>(int)$a['id'],'version'=>(int)$a['version'],'status'=>$a['status']],
            'to'=>['id'=>(int)$b['id'],'version'=>(int)$b['version'],'status'=>$b['status']],
            'diff'=>VersionDiff::compare(M::entries((int)$a['id']),M::entries((int)$b['id']),$names)]);
    }
    public function version_audit(): void {
        $user=Auth::requireUser(); $this->versionViewer($user);
        $roster=$this->roster($user,$_GET['roster_id']??null);
        $shifts=[];
        foreach(M::allShiftsForAudit() as $shift) $shifts[(int)$shift['id']]=(string)$shift['name'];
        $people=[];
        foreach(M::rosterAuditStaff((int)$roster['id']) as $person) $people[(int)$person['id']]=(string)$person['full_name'];
        $items=AuditLogPresenter::present(M::rosterAudit((int)$roster['id']),$shifts,$people);
        jsonOut(['success'=>true,'version'=>(int)$roster['version'],'items'=>$items]);
    }
    /** Dedicated read-only individual schedule. Never accepts a user_id from the client. */
    public function my_schedule(): void {
        $user=Auth::requireUser();
        Auth::requireRole($user,['STAFF','SCHEDULER','DIRECTOR']);
        [$year,$month]=monthInput($_GET);
        $hospital=Auth::hospitalId($user,null);
        $h=M::hospital($hospital);
        if(!$h) jsonError('ไม่พบหน่วยบริการ',404);
        $r=M::latest($hospital,$year,$month,true);
        $entries=$r ? array_values(array_filter(M::entries((int)$r['id']),static fn($entry)=>(int)$entry['user_id']===(int)$user['id'])) : [];
        $shifts=M::shifts();
        $days=(int)date('t',strtotime(sprintf('%04d-%02d-01',$year,$month)));
        require dirname(__DIR__).'/Views/roster/my_schedule.php';
    }
    public function export_excel(): void {
        $u=Auth::requireUser(); $r=$this->roster($u,$_GET['roster_id'] ?? null);
        ExcelExporter::download($r,M::hospital((int)$r['hospital_id']),M::rosterStaff((int)$r['hospital_id'],(int)$r['id']),M::entries((int)$r['id']));
    }
    public function export_print(): void {
        $u=Auth::requireUser(); $r=$this->roster($u,$_GET['roster_id'] ?? null);
        $hospital=M::hospital((int)$r['hospital_id']); $staff=M::rosterStaff((int)$r['hospital_id'],(int)$r['id']);
        $entries=M::entries((int)$r['id']); $days=(int)date('t',strtotime(sprintf('%04d-%02d-01',$r['year'],$r['month'])));
        require dirname(__DIR__).'/Views/roster/print.php';
    }
    public function settings_save(): void {
        $u=$this->startPost(); Auth::requireRole($u,['SUPERADMIN','ADMIN']); $in=$this->postData();
        $hospital=Auth::hospitalId($u,intInput($in['hospital_id']??null,1,2147483647,'หน่วยบริการ'));
        if(!M::hospital($hospital)) jsonError('ไม่พบหน่วยบริการ',404);
        $limits=[];
        foreach(['min_morning','min_afternoon','min_night'] as $field) $limits[$field]=intInput($in[$field]??null,0,100,$field);
        $limits['max_consecutive_work_days']=intInput($in['max_consecutive_work_days']??null,1,31,'max_consecutive_work_days');
        $limits['max_night_shifts']=intInput($in['max_night_shifts']??null,0,31,'max_night_shifts');
        $limits['min_rest_hours']=intInput($in['min_rest_hours']??null,8,24,'min_rest_hours');
        $db=Database::pdo();$db->beginTransaction();
        try {
            $q=$db->prepare('SELECT * FROM roster_settings WHERE hospital_id=? FOR UPDATE');$q->execute([$hospital]);$before=$q->fetch()?:null;
            $q=$db->prepare('INSERT INTO roster_settings (hospital_id,min_morning,min_afternoon,min_night,max_consecutive_work_days,max_night_shifts,min_rest_hours) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE min_morning=VALUES(min_morning),min_afternoon=VALUES(min_afternoon),min_night=VALUES(min_night),max_consecutive_work_days=VALUES(max_consecutive_work_days),max_night_shifts=VALUES(max_night_shifts),min_rest_hours=VALUES(min_rest_hours)');
            $q->execute([$hospital,$limits['min_morning'],$limits['min_afternoon'],$limits['min_night'],$limits['max_consecutive_work_days'],$limits['max_night_shifts'],$limits['min_rest_hours']]);
            M::audit($db,(int)$u['id'],'UPDATE_SETTINGS',null,$hospital,null,$before,$limits);
            $db->commit();jsonOut(['success'=>true,'message'=>'บันทึกเกณฑ์จัดเวรแล้ว','settings'=>$limits]);
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
    public function audit(): void {
        $u=Auth::requireUser(); Auth::requireRole($u,['SUPERADMIN']);
        $hospital=Auth::hospitalId($u,isset($_GET['hospital_id'])?intInput($_GET['hospital_id'],1,2147483647,'หน่วยบริการ'):null);
        jsonOut(['success'=>true,'items'=>M::auditList($hospital)]);
    }
}
