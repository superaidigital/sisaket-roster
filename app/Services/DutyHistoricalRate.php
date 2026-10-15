<?php
declare(strict_types=1);
namespace App\Services;

use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

/**
 * Deterministic read-only historical rate selection.
 * Never authorizes compensation or creates payroll; callers MUST verify
 * actual attendance, person/group evidence, authority and budget.
 */
final class DutyHistoricalRate
{
    public static function select(array $candidates,int $groupId,int $dutyTypeId,string $date): array
    {
        $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if(!$d||$d->format('Y-m-d')!==$date)throw new InvalidArgumentException('Invalid work date');
        if($groupId<1||$dutyTypeId<1)throw new InvalidArgumentException('Invalid group or duty type');
        $eligible=[];
        foreach($candidates as $rate){
            if((int)($rate['group_id']??0)!==$groupId||(int)($rate['duty_type_id']??0)!==$dutyTypeId)continue;
            if(($rate['approval_status']??'')!=='APPROVED')continue;
            $from=(string)($rate['effective_from']??'');$to=(string)($rate['effective_to']??'');
            if($from==='' || $from>$date || ($to!=='' && $to<$date))continue;
            if(empty($rate['approved_by'])||empty($rate['approved_at'])||trim((string)($rate['source_reference']??''))==='')continue;
            $eligible[]=$rate;
        }
        if(count($eligible)===0)throw new RuntimeException('No approved and effective rate for work date');
        // Two overlapping approved rates are a financial integrity error,
        // not a reason to silently choose a newer rule.
        if(count($eligible)!==1)throw new RuntimeException('Ambiguous approved rates overlap for work date');
        return $eligible[0];
    }

    public static function staffGroup(array $groups,int $userId,string $date): array
    {
        $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if(!$d||$d->format('Y-m-d')!==$date||$userId<1)throw new InvalidArgumentException('Invalid user/date');
        $eligible=[];
        foreach($groups as $row){
            if((int)($row['user_id']??0)!==$userId)continue;
            $from=(string)($row['effective_from']??'');$to=(string)($row['effective_to']??'');
            if($from===''||$from>$date||($to!==''&&$to<$date))continue;
            if(empty($row['verified_by'])||empty($row['verified_at'])||trim((string)($row['evidence_reference']??''))==='')continue;
            $eligible[]=$row;
        }
        if(count($eligible)!==1)throw new RuntimeException(count($eligible)===0?'No verified staff group for work date':'Ambiguous staff compensation groups overlap');
        return $eligible[0];
    }

    /** A non-payable preview; never writes payroll_logs or approves a rate. */
    public static function preview(array $staffGroups,array $rates,int $userId,int $dutyTypeId,string $date,string $code,bool $actualWorkVerified): array
    {
        $group=self::staffGroup($staffGroups,$userId,$date);
        $rate=self::select($rates,(int)$group['group_id'],$dutyTypeId,$date);
        $amount=DutyPolicy::compensation($code,$rate,$actualWorkVerified);
        return ['user_id'=>$userId,'work_date'=>$date,'duty_code'=>$code,
            'group_id'=>(int)$group['group_id'],'rate_id'=>(int)$rate['id'],
            'rule_version'=>(string)$rate['rule_version'],'unit'=>(string)$rate['rate_unit'],
            'rate'=>(string)$rate['rate_amount'],'preview_amount'=>$amount,
            'payable'=>false,'requires_finance_approval'=>true];
    }
}
