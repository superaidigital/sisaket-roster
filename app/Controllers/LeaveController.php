<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Auth,Csrf};
use App\Services\LeaveRequestService as Leave;
use InvalidArgumentException;
use RuntimeException;

final class LeaveController
{
    public function index():void
    {
        $user=Auth::requireUser();
        require dirname(__DIR__).'/Views/leave/index.php';
    }
    public function data():void
    {
        $user=Auth::requireUser();
        jsonOut(['success'=>true,'types'=>Leave::types(),'requests'=>Leave::listing($user),
            'can_review'=>in_array((string)$user['role'],['DIRECTOR','SUPERADMIN'],true)]);
    }
    public function detail():void
    {
        $u=Auth::requireUser();
        $id=intInput($_GET['request_id']??null,1,2147483647,'เลขใบลา');
        try{$data=Leave::detail($u,$id);}
        catch(InvalidArgumentException $e){jsonError($e->getMessage(),422);}
        catch(RuntimeException $e){jsonError($e->getMessage(),403);}
        jsonOut(['success'=>true,'detail'=>$data]);
    }
    public function submit():void
    {
        requirePost();Csrf::check();$u=Auth::requireUser();$d=jsonBody();
        $type=intInput($d['leave_type_id']??null,1,32767,'ประเภทการลา');
        foreach(['starts_at','ends_at'] as $field)if(!is_string($d[$field]??null))jsonError('วันเวลาไม่ถูกต้อง',422);
        $reason=$d['reason']??'';
        if(!is_string($reason))jsonError('เหตุผลไม่ถูกต้อง',422);
        try{$id=Leave::submit($u,$type,(string)$d['starts_at'],(string)$d['ends_at'],$reason);}
        catch(InvalidArgumentException $e){jsonError($e->getMessage(),422);}
        catch(RuntimeException $e){jsonError($e->getMessage(),409);}
        jsonOut(['success'=>true,'id'=>$id,'message'=>'ส่งคำขอลาแล้ว (รอผู้มีอำนาจพิจารณา)']);
    }
    public function decide():void
    {
        requirePost();Csrf::check();$u=Auth::requireUser();$d=jsonBody();
        $id=intInput($d['request_id']??null,1,2147483647,'เลขใบลา');
        $decision=$d['decision']??null;
        if(!is_string($decision))jsonError('คำตัดสินไม่ถูกต้อง',422);
        try{Leave::decision($u,$id,$decision);}
        catch(InvalidArgumentException $e){jsonError($e->getMessage(),422);}
        catch(RuntimeException $e){jsonError($e->getMessage(),409);}
        jsonOut(['success'=>true,'message'=>'บันทึกผลพิจารณาแล้ว']);
    }
}
