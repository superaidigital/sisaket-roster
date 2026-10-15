<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Auth;
use App\Services\DutyDocumentPreviewService as Docs;
use App\Services\DutyRateProposalService as Scope;
use InvalidArgumentException;
use RuntimeException;

final class DutyDocumentsController
{
    public function preview(): void
    {
        $user=Auth::requireUser();
        if(!in_array($user['role'],['SUPERADMIN','ADMIN','SCHEDULER','DIRECTOR'],true))
            jsonError('ไม่มีสิทธิ์ดูเอกสารภายใน',403);
        $hospital=intInput($_GET['hospital_id']??$user['hospital_id'],1,2147483647,'หน่วยบริการ');
        // SUPERADMIN may browse all; other roles are restricted to their own unit.
        if($user['role']!=='SUPERADMIN' && $hospital!==(int)$user['hospital_id'])
            jsonError('ไม่มีสิทธิ์เข้าถึงหน่วยบริการ',403);
        $type=(string)($_GET['type']??'annex');
        if(!in_array($type,['annex','memo','swap'],true)) jsonError('ไม่พบประเภทเอกสาร',404);
        [$year,$month]=monthInput($_GET);
        try {
            if($type==='swap') {
                $swapId=intInput($_GET['swap_id']??null,1,2147483647,'รหัสการเปลี่ยนเวร');
                $swap=Docs::swap($hospital,$swapId);
                if(!$swap) jsonError('ไม่พบใบเปลี่ยนเวร',404);
                $data=['swap'=>$swap];
            } else {
                $data=Docs::load($hospital,$year,$month);
            }
        }catch(InvalidArgumentException $e) {
            jsonError($e->getMessage(),422);
        }catch(RuntimeException $e) {
            jsonError($e->getMessage(),404);
        }
        $hospitalId=$hospital;
        require dirname(__DIR__).'/Views/duty/document_preview.php';
    }
}
