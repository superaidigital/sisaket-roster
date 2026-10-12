<?php
declare(strict_types=1);
namespace App\Services;

/** Turn the existing immutable audit JSON into human-readable, read-only fields.
 * Historical JSON is never altered. Lookup maps come from trusted server-side tables.
 */
final class AuditLogPresenter {
    private static function decode(mixed $json): ?array {
        if (!is_string($json) || $json === '') return null;
        $data=json_decode($json,true);
        return json_last_error()===JSON_ERROR_NONE && is_array($data) ? $data : null;
    }
    private static function shift(mixed $value,array $shifts): string {
        if ($value===null || $value==='') return 'ไม่ได้จัดเวร';
        $id=filter_var($value,FILTER_VALIDATE_INT);
        if ($id===false || $id<1) return 'ไม่ทราบประเภทเวร';
        return (string)($shifts[$id]??('ประเภทเวร #'.$id));
    }
    /** @param array<int,array> $items @param array<int,string> $shiftNames @param array<int,string> $staffNames */
    public static function present(array $items,array $shiftNames,array $staffNames): array {
        $out=[];
        foreach ($items as $item) {
            $before=self::decode($item['old_value']??null);
            $after=self::decode($item['new_value']??null);
            $uid=(int)($after['user_id']??$before['user_id']??0);
            $action=(string)($item['action']??'');
            $isShift=in_array($action,['UPDATE_SHIFT','DELETE_SHIFT','AUTO_SCHEDULE','COPY_MONTH'],true);
            $summary='บันทึกการดำเนินการ '.str_replace('_',' ',$action);
            $beforeLabel='—'; $afterLabel='—';
            $oldNote='';$newNote='';
            if ($isShift) {
                $beforeLabel=self::shift($before['shift_type_id']??null,$shiftNames);
                $afterLabel=self::shift($after['shift_type_id']??null,$shiftNames);
                $oldNote=(string)($before['note']??'');
                $newNote=(string)($after['note']??'');
                $summary=match ($action) {
                    'DELETE_SHIFT'=>'ล้างเวร',
                    'AUTO_SCHEDULE'=>'จัดเวรอัตโนมัติ',
                    'COPY_MONTH'=>'คัดลอกเวรจากเดือนก่อน',
                    default=>'เปลี่ยนเวร',
                };
            } elseif ($action==='CREATE_REVISION') {
                $summary='สร้าง Version ใหม่';
                $beforeLabel=isset($before['version'])?'Version '.(int)$before['version']:'—';
                $afterLabel=isset($after['version'])?'Version '.(int)$after['version']:'—';
            } elseif (isset($before['status']) || isset($after['status'])) {
                $beforeLabel=(string)($before['status']??'—');
                $afterLabel=(string)($after['status']??'—');
                $summary='เปลี่ยนสถานะตาราง';
            }
            $item['display']=[
                'summary'=>$summary,
                'staff_name'=>$uid>0?(string)($staffNames[$uid]??('บุคลากร #'.$uid)):'',
                'before'=>$beforeLabel,
                'after'=>$afterLabel,
                'before_note'=>$oldNote,
                'after_note'=>$newNote,
                'reason'=>(string)($after['reason']??''),
                'is_shift'=>$isShift,
            ];
            $out[]=$item;
        }
        return $out;
    }
}
