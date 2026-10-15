<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Auth,Csrf};
use App\Services\DutySwapService as Swap;
use InvalidArgumentException;
use RuntimeException;

final class SwapController
{
    public function index():void {$user=Auth::requireUser();require dirname(__DIR__).'/Views/swap/index.php';}
    public function data():void
    {
        $u=Auth::requireUser();
        jsonOut(['success'=>true,'assignments'=>Swap::availableAssignments($u),
            'replacements'=>Swap::replacements($u),'requests'=>Swap::listing($u),
            'user_id'=>(int)$u['id'],'role'=>(string)$u['role']]);
    }
    public function request():void
    {
        requirePost();Csrf::check();$u=Auth::requireUser();$d=jsonBody();
        $assignment=intInput($d['assignment_id']??null,1,2147483647,'เวร');
        $replacement=intInput($d['replacement_id']??null,1,2147483647,'ผู้รับแทน');
        $reason=$d['reason']??null;if(!is_string($reason))jsonError('กรุณาระบุเหตุผล',422);
        try{$id=Swap::request($u,$assignment,$replacement,trim($reason));}
        catch(InvalidArgumentException $e){jsonError($e->getMessage(),422);}
        catch(RuntimeException $e){jsonError($e->getMessage(),409);}
        jsonOut(['success'=>true,'id'=>$id,'message'=>'ส่งคำขอเปลี่ยนเวรเพื่อรอความยินยอมแล้ว']);
    }
    public function consent():void
    {
        requirePost();Csrf::check();$u=Auth::requireUser();$d=jsonBody();
        $id=intInput($d['swap_id']??null,1,2147483647,'คำขอ');
        if(!is_bool($d['accept']??null))jsonError('การยินยอมไม่ถูกต้อง',422);
        try{Swap::consent($u,$id,$d['accept']);}
        catch(RuntimeException $e){jsonError($e->getMessage(),409);}
        jsonOut(['success'=>true,'message'=>'บันทึกการตอบรับแล้ว']);
    }
    public function review():void
    {
        requirePost();Csrf::check();$u=Auth::requireUser();$d=jsonBody();
        $id=intInput($d['swap_id']??null,1,2147483647,'คำขอ');
        if(!is_bool($d['endorse']??null))jsonError('ผลการตรวจรับไม่ถูกต้อง',422);
        try{Swap::directorReview($u,$id,$d['endorse']);}
        catch(RuntimeException $e){jsonError($e->getMessage(),409);}
        jsonOut(['success'=>true,'message'=>'บันทึกผลตรวจรับแล้ว คำขอยังต้องผ่านผู้มีอำนาจขั้นสุดท้ายและกระบวนการแก้คำสั่ง']);
    }
    public function reason():void
    {
        $u=Auth::requireUser();$id=intInput($_GET['swap_id']??null,1,2147483647,'คำขอ');
        try{$reason=Swap::reason($u,$id);}
        catch(RuntimeException $e){jsonError($e->getMessage(),403);}
        jsonOut(['success'=>true,'reason'=>$reason]);
    }
}
