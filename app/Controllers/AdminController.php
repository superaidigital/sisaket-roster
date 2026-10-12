<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Auth,Csrf,Database};
use App\Models\AdminModel as M;
use App\Services\{StaffSpreadsheetReader,StaffImportService,SkillPolicy};
use App\Models\RosterModel;
use PDOException;
use RuntimeException;

/** Management for roster master data only; NOT a separate HR module. */
final class AdminController {
    private const STAFF_ROLES = ['STAFF','SCHEDULER','DIRECTOR'];
    private const SUPERADMIN_ROLES = ['STAFF','SCHEDULER','DIRECTOR','ADMIN','HR'];

    private function user(): array {
        $u=Auth::requireUser();
        Auth::requireRole($u,['SUPERADMIN','ADMIN']);
        return $u;
    }
    private function isSuper(array $user): bool { return $user['role']==='SUPERADMIN'; }
    private function roles(array $user): array {
        return $this->isSuper($user) ? self::SUPERADMIN_ROLES : self::STAFF_ROLES;
    }
    private function go(string $query): never {
        header('Location: index.php?c=admin&a='.$query, true, 303); exit;
    }
    private function alert(string $type,string $message,?string $password=null): void {
        $_SESSION['admin_flash']=['type'=>$type,'message'=>$message];
        if ($password!==null) $_SESSION['admin_temp_password']=$password;
    }
    private function checkPost(): array { requirePost(); Csrf::check(); return $this->user(); }
    private function nonblank(mixed $value,string $label,int $max): string {
        if(!is_string($value)) throw new RuntimeException($label.' ไม่ถูกต้อง');
        $value=trim($value);
        if($value==='' || mb_strlen($value)>$max) throw new RuntimeException($label.' ต้องมี 1–'.$max.' ตัวอักษร');
        return $value;
    }
    private function optional(mixed $value,string $label,int $max): string {
        if(!is_string($value)) throw new RuntimeException($label.' ไม่ถูกต้อง');
        $value=trim($value);
        if(mb_strlen($value)>$max) throw new RuntimeException($label.' ยาวเกิน '.$max.' ตัวอักษร');
        return $value;
    }
    private function recordId(): ?int {
        if (!isset($_GET['id']) || $_GET['id']==='') return null;
        return intInput($_GET['id'],1,2147483647,'รหัสรายการ');
    }
    public function index(): void {
        $user=$this->user(); $hospitals=M::hospitals(); $counts=M::staffCounts();
        $flash=$_SESSION['admin_flash']??null; unset($_SESSION['admin_flash']);
        require dirname(__DIR__).'/Views/admin/dashboard.php';
    }
    public function hospitals(): void {
        $user=$this->user(); $hospitals=M::hospitals();
        $flash=$_SESSION['admin_flash']??null; unset($_SESSION['admin_flash']);
        require dirname(__DIR__).'/Views/admin/hospitals.php';
    }
    public function hospital_form(): void {
        $user=$this->user();
        if(!$this->isSuper($user)) jsonError('เฉพาะ SUPERADMIN สามารถเพิ่มหรือแก้ไขหน่วยบริการ',403);
        $id=$this->recordId(); $record=$id!==null ? M::hospital($id) : null;
        if($id!==null && !$record) jsonError('ไม่พบหน่วยบริการ',404);
        $flash=$_SESSION['admin_flash']??null;unset($_SESSION['admin_flash']);
        require dirname(__DIR__).'/Views/admin/hospital_form.php';
    }
    public function hospital_save(): void {
        $user=$this->checkPost();
        if(!$this->isSuper($user)) jsonError('เฉพาะ SUPERADMIN สามารถแก้ไขหน่วยบริการ',403);
        $id=null;
        try {
            $id=empty($_POST['id'])?null:intInput($_POST['id'],1,2147483647,'รหัสหน่วยบริการ');
            $code=strtoupper($this->nonblank($_POST['hospital_code']??'', 'รหัสหน่วยบริการ',30));
            if(!preg_match('/^[A-Z0-9][A-Z0-9_-]{2,29}$/D',$code)) throw new RuntimeException('รหัสหน่วยบริการต้องเป็น A–Z, 0–9, _ หรือ - ความยาว 3–30 ตัวอักษร');
            $data=['hospital_code'=>$code,
                'name'=>$this->nonblank($_POST['name']??'', 'ชื่อหน่วยบริการ',200),
                'district'=>$this->optional($_POST['district']??'', 'อำเภอ',120),
                'subdistrict'=>$this->optional($_POST['subdistrict']??'', 'ตำบล',120),
                'is_active'=>isset($_POST['is_active'])?1:0];
            if($id!==null) {
                $existing=M::hospital($id);
                if(!$existing || $existing['hospital_code']!==$code) throw new RuntimeException('ไม่อนุญาตให้แก้ไขรหัสหน่วยบริการที่สร้างแล้ว');
            }
            M::saveHospital($id,$data,(int)$user['id'],is_string($_POST['stamp']??null)?$_POST['stamp']:null);
            $this->alert('success',$id===null?'เพิ่มหน่วยบริการและเกณฑ์จัดเวรเริ่มต้นแล้ว':'บันทึกข้อมูลหน่วยบริการแล้ว');
            $this->go('hospitals');
        } catch (\Throwable $e) {
            error_log((string)$e);
            $msg=$e instanceof PDOException && $e->getCode()==='23000'?'รหัสหน่วยบริการนี้ถูกใช้งานแล้ว':
                ($e instanceof RuntimeException?$e->getMessage():'บันทึกหน่วยบริการไม่สำเร็จ กรุณาตรวจสอบข้อมูลและลองใหม่');
            $this->alert('danger',$msg);
            $this->go('hospital_form'.($id!==null?'&id='.$id:''));
        }
    }
    public function staff(): void {
        $user=$this->user();
        $hospitalId=isset($_GET['hospital_id']) && $_GET['hospital_id']!=='' ? intInput($_GET['hospital_id'],1,2147483647,'หน่วยบริการ') : 0;
        $search=trim((string)($_GET['q']??''));
        if (mb_strlen($search)>100) $search=mb_substr($search,0,100);
        $staff=M::staff($hospitalId,$search);$hospitals=M::hospitals();
        $flash=$_SESSION['admin_flash']??null;unset($_SESSION['admin_flash']);
        $tempPassword=$_SESSION['admin_temp_password']??null;unset($_SESSION['admin_temp_password']);
        require dirname(__DIR__).'/Views/admin/staff.php';
    }
    public function staff_form(): void {
        $user=$this->user(); $id=$this->recordId();
        $record=$id!==null?M::user($id):null;
        if($id!==null && !$record) jsonError('ไม่พบบัญชีผู้ใช้',404);
        if($record && !in_array($record['role'],$this->roles($user),true)) jsonError('ไม่มีสิทธิ์แก้ไขบัญชีนี้',403);
        $roles=$this->roles($user);$hospitals=M::hospitals();
        $flash=$_SESSION['admin_flash']??null;unset($_SESSION['admin_flash']);
        require dirname(__DIR__).'/Views/admin/staff_form.php';
    }
    public function staff_save(): void {
        $user=$this->checkPost(); $id=null;
        try {
            $id=empty($_POST['id'])?null:intInput($_POST['id'],1,2147483647,'รหัสบุคลากร');
            $role=$this->nonblank($_POST['role']??'','สิทธิ์ผู้ใช้งาน',30);
            if(!in_array($role,$this->roles($user),true)) throw new RuntimeException('ไม่มีสิทธิ์ตั้งค่าบทบาทนี้');
            $email=mb_strtolower($this->nonblank($_POST['email']??'','อีเมล',190));
            if(!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('กรุณากรอกอีเมลให้ถูกต้อง');
            $url=$this->optional($_POST['photo_url']??'','URL รูปภาพ',500);
            if($url!=='' && (!filter_var($url,FILTER_VALIDATE_URL) || strtolower((string)parse_url($url,PHP_URL_SCHEME))!=='https'))
                throw new RuntimeException('URL รูปภาพต้องเป็นลิงก์ HTTPS ที่ถูกต้อง');
            $data=[
                'hospital_id'=>intInput($_POST['hospital_id']??null,1,2147483647,'หน่วยบริการ'),
                'full_name'=>$this->nonblank($_POST['full_name']??'','ชื่อ-นามสกุล',200),
                'email'=>$email,'role'=>$role,
                'position'=>$this->optional($_POST['position']??'','ตำแหน่ง',150),
                'personnel_type'=>$this->optional($_POST['personnel_type']??'','ประเภทบุคลากร',100),
                'photo_url'=>$url===''?null:$url,
                'is_active'=>isset($_POST['is_active'])?1:0
            ];
            if($id!==null) {
                $existing=M::user($id);
                if(!$existing || !in_array($existing['role'],$this->roles($user),true)) throw new RuntimeException('ไม่มีสิทธิ์แก้ไขบัญชีนี้');
            }
            $result=M::saveUser($id,$data,(int)$user['id'],is_string($_POST['stamp']??null)?$_POST['stamp']:null);
            $this->alert('success',$id===null?'เพิ่มบุคลากรเรียบร้อยแล้ว ระบบสร้างรหัสผ่านชั่วคราวให้':'แก้ไขข้อมูลบุคลากรแล้ว',$result['temporary_password']);
            $this->go('staff');
        } catch (\Throwable $e) {
            error_log((string)$e);
            $msg=$e instanceof PDOException && $e->getCode()==='23000'?'อีเมลนี้ถูกใช้ในระบบแล้ว':
                ($e instanceof RuntimeException?$e->getMessage():'บันทึกบุคลากรไม่สำเร็จ กรุณาลองใหม่');
            $this->alert('danger',$msg);
            $this->go('staff_form'.($id!==null?'&id='.$id:''));
        }
    }
    /** Import from a single hospital-scoped roster staff file (no HR module). */
    public function staff_import(): void {
        $user=$this->user();
        $hospitals=array_values(array_filter(M::hospitals(),static fn($h)=>(int)$h['is_active']===1));
        $flash=$_SESSION['admin_flash']??null; unset($_SESSION['admin_flash']);
        $preview=$_SESSION['staff_import_preview']??null;
        if($preview && !StaffImportService::validSession($preview,(int)$preview['hospital_id'])) {
            unset($_SESSION['staff_import_preview']); $preview=null;
        }
        require dirname(__DIR__).'/Views/admin/staff_import.php';
    }
    public function staff_import_preview(): void {
        $user=$this->checkPost();
        unset($_SESSION['staff_import_preview']);
        try {
            $hospitalId=intInput($_POST['hospital_id']??null,1,2147483647,'หน่วยบริการ');
            $file=$_FILES['staff_file']??null;
            if(!is_array($file) || ($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)
                throw new RuntimeException('อัปโหลดไม่สำเร็จ โปรดตรวจขนาดไฟล์ไม่เกิน 1 MB และการตั้งค่า PHP');
            if (($file['size']??0)>1048576 || ($file['size']??0)<=0)
                throw new RuntimeException('ขนาดไฟล์ต้องไม่เกิน 1 MB');
            if (!is_uploaded_file((string)$file['tmp_name'])) throw new RuntimeException('ไม่พบไฟล์อัปโหลดที่ถูกต้อง');
            $rows=StaffSpreadsheetReader::read((string)$file['tmp_name'],(string)$file['name']);
            $preview=StaffImportService::preview($rows,$hospitalId);
            $preview['nonce']=bin2hex(random_bytes(16));
            $preview['actor']=(int)$user['id'];
            $_SESSION['staff_import_preview']=$preview;
            $this->alert('success','อ่านไฟล์สำเร็จ ตรวจสอบตัวอย่างก่อนยืนยันนำเข้า');
        } catch (\Throwable $e) {
            error_log((string)$e);
            $this->alert('danger',$e instanceof RuntimeException?$e->getMessage():'อ่านไฟล์ไม่สำเร็จ กรุณาตรวจสอบแม่แบบและลองใหม่');
        }
        $this->go('staff_import');
    }
    public function staff_import_confirm(): void {
        $user=$this->checkPost();
        $preview=$_SESSION['staff_import_preview']??null;
        if(!is_array($preview) || !StaffImportService::validSession($preview,(int)($preview['hospital_id']??0)) ||
           !hash_equals((string)($preview['nonce']??''),(string)($_POST['nonce']??'')) ||
           (int)($preview['actor']??0)!==(int)$user['id']) {
            $this->alert('danger','รายการยืนยันหมดอายุหรือไม่ถูกต้อง กรุณาอัปโหลดไฟล์ใหม่');
            $this->go('staff_import');
        }
        // One-time approval token: consume even if the insert fails.
        unset($_SESSION['staff_import_preview']);
        try {
            $result=StaffImportService::commit($preview,(int)$user['id']);
            $_SESSION['staff_import_result']=$result;
            $this->go('staff_import_result');
        } catch (\Throwable $e) {
            error_log((string)$e);
            $this->alert('danger',$e instanceof RuntimeException?$e->getMessage():
                'ไม่สามารถนำเข้าข้อมูลได้ ไม่มีรายการใหม่ถูกบันทึก กรุณาตรวจสอบไฟล์แล้วลองใหม่');
            $this->go('staff_import');
        }
    }
    public function staff_import_result(): void {
        $user=$this->user();
        $result=$_SESSION['staff_import_result']??null;
        unset($_SESSION['staff_import_result']);
        if(!is_array($result)) { $this->go('staff'); }
        require dirname(__DIR__).'/Views/admin/staff_import_result.php';
    }
    public function staff_reset_password(): void {
        $user=$this->checkPost();
        $id=intInput($_POST['id']??null,1,2147483647,'รหัสบุคลากร');
        try {
            $password=M::resetPassword($id,(int)$user['id'],$this->roles($user));
            $this->alert('success','รีเซ็ตรหัสผ่านสำเร็จ ผู้ใช้ต้องเปลี่ยนรหัสผ่านเมื่อเข้าสู่ระบบ',$password);
        } catch (\Throwable $e) {
            error_log((string)$e);
            $this->alert('danger',$e instanceof RuntimeException?$e->getMessage():'รีเซ็ตรหัสผ่านไม่สำเร็จ');
        }
        $this->go('staff');
    }
    /** V10 scope is always a single enabled hospital; ADMIN can configure duties for any hospital. */
    public function skills(): void {
        $user=$this->user();$hospitals=RosterModel::hospitals();
        $hospitalId=isset($_GET['hospital_id']) ? intInput($_GET['hospital_id'],1,2147483647,'รพ.สต.') : (int)($hospitals[0]['id']??0);
        if(!$hospitalId || !RosterModel::hospital($hospitalId)) jsonError('ไม่พบ รพ.สต. ที่เปิดใช้งาน',404);
        $staff=RosterModel::staff($hospitalId);$shifts=array_values(array_filter(RosterModel::shifts(),static fn($s)=>(int)$s['is_working']===1));
        $requirements=RosterModel::requirements($hospitalId);$assignments=SkillPolicy::index(RosterModel::staffSkills($hospitalId));
        $catalog=SkillPolicy::CATALOG;$pageTitle='ทักษะบุคลากรและเกณฑ์ประจำเวร';$active='skills';
        $flash=$_SESSION['admin_flash']??null;unset($_SESSION['admin_flash']);
        require dirname(__DIR__).'/Views/admin/skills.php';
    }
    public function skills_save(): void {
        $user=$this->checkPost();
        $hospital=intInput($_POST['hospital_id']??null,1,2147483647,'รพ.สต.');
        if(!RosterModel::hospital($hospital))jsonError('ไม่พบ รพ.สต.',404);
        $allowedStaff=[];
        foreach(RosterModel::staff($hospital) as $s)$allowedStaff[(int)$s['id']]=true;
        $shiftIds=[];
        foreach(RosterModel::shifts() as $s)if((int)$s['is_working']===1)$shiftIds[(int)$s['id']]=true;
        $skillRows=$_POST['staff_skills']??[]; $reqRows=$_POST['requirements']??[];
        if(!is_array($skillRows)||!is_array($reqRows))jsonError('ข้อมูลไม่ถูกต้อง',422);
        $newSkills=[];$newReq=[];
        foreach($skillRows as $uid=>$row){
            if(!ctype_digit((string)$uid)||!isset($allowedStaff[(int)$uid])||!is_array($row))jsonError('บุคลากรไม่ถูกต้อง',422);
            foreach($row as $skill=>$checked){
                if(!isset(SkillPolicy::CATALOG[(string)$skill]) || (string)$checked!=='1')jsonError('รหัสทักษะไม่ถูกต้อง',422);
                $newSkills[]=[(int)$uid,(string)$skill];
            }
        }
        foreach($reqRows as $sid=>$row){
            if(!ctype_digit((string)$sid)||!isset($shiftIds[(int)$sid])||!is_array($row))jsonError('ประเภทเวรไม่ถูกต้อง',422);
            foreach($row as $skill=>$minimum){
                if(!isset(SkillPolicy::CATALOG[(string)$skill]))jsonError('รหัสทักษะไม่ถูกต้อง',422);
                $n=intInput($minimum,0,100,'ขั้นต่ำทักษะ');
                if($n>count($allowedStaff))jsonError('ขั้นต่ำทักษะมากกว่าจำนวนบุคลากร',422);
                if($n>0)$newReq[]=[(int)$sid,(string)$skill,$n];
            }
        }
        $db=Database::pdo();$db->beginTransaction();
        try{
            // Serialize concurrent settings submissions for this hospital.
            $lock=$db->prepare('SELECT id FROM hospitals WHERE id=? FOR UPDATE');$lock->execute([$hospital]);
            $previous=['skills'=>RosterModel::staffSkills($hospital),'requirements'=>RosterModel::requirements($hospital)];
            $db->prepare('DELETE FROM roster_staff_skills WHERE hospital_id=?')->execute([$hospital]);
            $db->prepare('DELETE FROM roster_shift_skill_requirements WHERE hospital_id=?')->execute([$hospital]);
            $q=$db->prepare('INSERT INTO roster_staff_skills (hospital_id,user_id,skill_code,verified_by) VALUES (?,?,?,?)');
            foreach($newSkills as [$uid,$code])$q->execute([$hospital,$uid,$code,(int)$user['id']]);
            $q=$db->prepare('INSERT INTO roster_shift_skill_requirements (hospital_id,shift_type_id,skill_code,min_count) VALUES (?,?,?,?)');
            foreach($newReq as [$sid,$code,$n])$q->execute([$hospital,$sid,$code,$n]);
            RosterModel::audit($db,(int)$user['id'],'UPDATE_SHIFT_SKILLS',null,$hospital,null,
                $previous,['skills'=>$newSkills,'requirements'=>$newReq]);
            $db->commit();$this->alert('success','บันทึกทักษะและเกณฑ์เวรแล้ว โปรดตรวจสอบตารางทุก Version อีกครั้ง');
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
        $this->go('skills&hospital_id='.$hospital);
    }

}
