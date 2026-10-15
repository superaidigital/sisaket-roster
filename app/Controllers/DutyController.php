<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Auth,Csrf};
use App\Services\DutyRosterService as D;
use App\Models\RosterModel;
use InvalidArgumentException;
use RuntimeException;

final class DutyController {
    private function context(array $input): array {
        $u=Auth::requireUser();
        [$year,$month]=monthInput($input);
        $requested=isset($input['hospital_id'])?intInput($input['hospital_id'],1,2147483647,'หน่วยบริการ'):null;
        $hospitalId=Auth::hospitalId($u,$requested);
        if(!D::hospital($hospitalId)) jsonError('ไม่พบหน่วยบริการ',404);
        return [$u,$hospitalId,$year,$month];
    }
    private function editor(array $u): void {
        Auth::requireRole($u,['SUPERADMIN','ADMIN','SCHEDULER']);
    }
    public function index(): void {
        $user=Auth::requireUser();
        $hospitals=Auth::allowed($user,['SUPERADMIN','ADMIN']) ? RosterModel::hospitals():[];
        require dirname(__DIR__).'/Views/duty/index.php';
    }
    public function data(): void {
        [$u,$h,$year,$month]=$this->context($_GET);
        $readonly=$u['role']==='STAFF';
        $r=D::current($h,$year,$month,$readonly);
        $staff=D::people($h,$readonly?(int)$u['id']:null);
        jsonOut(['success'=>true,'roster'=>$r,'staff'=>$staff,'types'=>D::types(),
            'entries'=>$r?D::entries((int)$r['id'],$readonly?(int)$u['id']:null):[],
            'can_edit'=>!$readonly && in_array($u['role'],['SUPERADMIN','ADMIN','SCHEDULER'],true) && ($r===null || $r['status']==='DRAFT')]);
    }
    public function create(): void {
        requirePost();Csrf::check();$input=jsonBody();
        [$u,$h,$year,$month]=$this->context($input);$this->editor($u);
        try{$r=D::create($h,$year,$month,(int)$u['id']);}
        catch(RuntimeException $e){jsonError($e->getMessage(),409);}
        jsonOut(['success'=>true,'roster'=>$r]);
    }
    public function save(): void {
        requirePost();Csrf::check();$input=jsonBody();
        [$u,$h,$year,$month]=$this->context($input);$this->editor($u);
        $r=D::current($h,$year,$month);
        if(!$r || $r['status']!=='DRAFT') jsonError('ไม่พบ Draft ที่แก้ไขได้',409);
        $uid=intInput($input['user_id']??null,1,2147483647,'บุคลากร');
        $revision=intInput($input['expected_revision']??null,0,2147483647,'Revision');
        $date=$input['work_date']??null;$code=$input['code']??null;$enabled=$input['enabled']??null;
        if(!is_string($date)||!is_string($code)||!is_bool($enabled))
            jsonError('ข้อมูลวัน เวร หรือสถานะไม่ถูกต้อง',422);
        try{$next=D::save($r,(int)$u['id'],$uid,$date,$code,$enabled,$revision);}
        catch(InvalidArgumentException $e){jsonError($e->getMessage(),422);}
        catch(RuntimeException $e){jsonError($e->getMessage(),409);}
        jsonOut(['success'=>true,'revision'=>$next]);
    }
}
