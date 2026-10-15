<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Auth,Csrf};
use App\Services\DutyRateProposalService as Rates;
use InvalidArgumentException;
use RuntimeException;

final class DutyRateAdminController
{
    private function actor(): array
    {
        $actor=Auth::requireUser();
        Auth::requireRole($actor,['SUPERADMIN','ADMIN']);
        return $actor;
    }
    private function hospital(array $actor,array $data): int
    {
        $requested=isset($data['hospital_id'])
            ? intInput($data['hospital_id'],1,2147483647,'หน่วยบริการ')
            : (int)$actor['hospital_id'];
        // SUPERADMIN may select hospitals; ADMIN is strictly scoped to own.
        try { Rates::assertHospital($actor,$requested); }
        catch(RuntimeException $e) { jsonError($e->getMessage(),403); }
        return $requested;
    }
    public function index(): void
    {
        $user=$this->actor();
        $hospitals=Rates::hospitals((int)$user['hospital_id'],$user['role']==='SUPERADMIN');
        $pageTitle='ตั้งค่าอัตราค่าตอบแทน (ข้อเสนอ)';
        $active='duty_rates';
        require dirname(__DIR__).'/Views/admin/duty_rates.php';
    }
    public function data(): void
    {
        $user=$this->actor();
        $hospital=$this->hospital($user,$_GET);
        jsonOut(['success'=>true,
                 'rates'=>Rates::rules(),
                 'proposals'=>Rates::proposals($user,$hospital)]);
    }
    public function propose(): void
    {
        requirePost();
        Csrf::check();
        $user=$this->actor();
        $input=jsonBody();
        $hospital=$this->hospital($user,$input);
        try {
            $id=Rates::propose($user,$hospital,$input);
        }catch(InvalidArgumentException $e) {
            jsonError($e->getMessage(),422);
        }catch(RuntimeException $e) {
            jsonError($e->getMessage(),409);
        }
        jsonOut(['success'=>true,'proposal_id'=>$id,'status'=>'DRAFT',
            'message'=>'สร้างข้อเสนอแล้ว ยังไม่อนุมัติหรือใช้เบิกจ่าย'],201);
    }
}
