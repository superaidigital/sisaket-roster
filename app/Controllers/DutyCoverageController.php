<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Auth,Csrf};
use App\Services\DutyCoverageService as Coverage;
use InvalidArgumentException;
use RuntimeException;
final class DutyCoverageController
{
    private function context(array $input):array
    {
        $u=Auth::requireUser();
        $hospital=intInput($input['hospital_id']??$u['hospital_id'],1,2147483647,'หน่วยบริการ');
        try{Coverage::scope($u,$hospital);}catch(RuntimeException $e){jsonError($e->getMessage(),403);}
        return [$u,$hospital];
    }
    public function index():void
    {
        $user=Auth::requireUser();
        Auth::requireRole($user,['SUPERADMIN','ADMIN']);
        $hospitals=Coverage::hospitals($user);
        require dirname(__DIR__).'/Views/admin/duty_coverage.php';
    }
    public function data():void
    {
        [$u,$hospital]=$this->context($_GET);
        jsonOut(['success'=>true]+Coverage::data($u,$hospital));
    }
    public function save():void
    {
        requirePost();Csrf::check();$input=jsonBody();[$u,$hospital]=$this->context($input);
        $rules=$input['rules']??null;if(!is_array($rules))jsonError('ข้อมูลเกณฑ์ไม่ถูกต้อง',422);
        try{Coverage::save($u,$hospital,$rules);}
        catch(InvalidArgumentException $e){jsonError($e->getMessage(),422);}
        catch(RuntimeException $e){jsonError($e->getMessage(),409);}
        jsonOut(['success'=>true,'message'=>'บันทึกเกณฑ์แล้ว โปรดตรวจตารางทุก Version อีกครั้ง']);
    }
}
