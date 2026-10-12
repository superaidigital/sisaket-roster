<?php
declare(strict_types=1);
namespace App\Services;

/** Read-only readiness summary; backend transition enforces rules again. */
final class RosterReadiness {
    public static function assess(?array $roster,array $staff,array $shifts,array $settings,?array $validation,int $entryCount,string $role): array {
        $eligible=count(array_filter($staff,static fn($p):bool=>!empty($p['eligible'])));
        $required=(int)$settings['min_morning']+(int)$settings['min_afternoon']+(int)$settings['min_night'];
        $codes=array_column($shifts,'code');
        $missing=array_values(array_diff(['MORNING','AFTERNOON','NIGHT','OFF'],$codes));
        $critical=(int)($validation['counts']['CRITICAL']??0);
        $warning=(int)($validation['counts']['WARNING']??0);
        $status=(string)($roster['status']??'EMPTY');
        $issues=[];
        if($eligible===0) $issues[]='ยังไม่มีบุคลากรที่เปิดใช้งานและมีสิทธิ์จัดเวร';
        if($required>$eligible) $issues[]="อัตรากำลังขั้นต่ำรวม {$required} คนต่อวัน มากกว่าบุคลากรที่จัดเวรได้ {$eligible} คน";
        if($missing) $issues[]='ไม่พบประเภทเวรที่ใช้งาน: '.implode(', ',$missing);
        if(!$roster) $issues[]='ยังไม่ได้สร้างตารางเวรเดือนนี้';
        elseif($entryCount===0) $issues[]='ยังไม่มีการบันทึกเวรในตาราง';
        if($roster && $critical>0) $issues[]="พบข้อผิดพลาดระดับ Critical {$critical} รายการ";
        $ready=$roster!==null && $eligible>0 && $required<=$eligible && !$missing && $entryCount>0 && $critical===0;
        $steps=[
            'EMPTY'=>'สร้างตารางเวร Draft แล้วเพิ่มบุคลากรและกำหนดเวร',
            'DRAFT'=>$ready?'ตรวจสอบตารางอีกครั้ง แล้วกดส่งอนุมัติ':'แก้ไขรายการที่ยังไม่พร้อม ก่อนส่งอนุมัติ',
            'PENDING_APPROVAL'=>$ready?'รอผู้มีสิทธิ์ตรวจสอบและอนุมัติ':'ผู้อนุมัติตรวจพบปัญหา ควรส่งคืนให้แก้ไข',
            'APPROVED'=>$ready?'ตารางพร้อมให้ผู้มีสิทธิ์เผยแพร่':'พบปัญหาหลังอนุมัติ ควรตรวจสอบก่อนเผยแพร่',
            'PUBLISHED'=>'เผยแพร่แล้ว บุคลากรสามารถดูตารางได้',
            'LOCKED'=>'ตารางถูกล็อก หากต้องแก้ไขต้องสร้าง Version ใหม่',
        ];
        return [
            'status'=>$status,'eligible_staff'=>$eligible,'required_per_day'=>$required,
            'saved_entries'=>$entryCount,'critical'=>$critical,'warning'=>$warning,
            'issues'=>$issues,'ready'=>$ready,'next_step'=>$steps[$status]??'ตรวจสอบสถานะ',
            'allowed_actions'=>WorkflowPolicy::available($role,$status),
        ];
    }
}
