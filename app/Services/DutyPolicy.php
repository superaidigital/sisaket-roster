<?php
declare(strict_types=1);
namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** Pure validation/calculation. No database writes. Rate approval checked at caller AND here. */
final class DutyPolicy {
    public const TIMEZONE = 'Asia/Bangkok';
    private const TYPES = [
        'ย'=>['08:30:00','16:30:00',0,0],
        'บ'=>['16:31:00','20:30:00',0,240],
        'ร'=>['20:31:00','08:29:00',1,0],
    ];
    public static function interval(string $date,string $code): array {
        if (!isset(self::TYPES[$code])) throw new InvalidArgumentException('Unknown duty type');
        $tz=new DateTimeZone(self::TIMEZONE);
        $start=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$date.' '.self::TYPES[$code][0],$tz);
        if (!$start || $start->format('Y-m-d')!==$date) throw new InvalidArgumentException('Invalid date');
        $end=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$date.' '.self::TYPES[$code][1],$tz);
        if (!$end) throw new InvalidArgumentException('Invalid end');
        if(self::TYPES[$code][2]) $end=$end->modify('+1 day');
        return [$start->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s')];
    }
    /** Half-open intervals [start,end). Adjacent end/start permitted. */
    public static function overlaps(string $aStart,string $aEnd,string $bStart,string $bEnd): bool {
        return $aStart < $bEnd && $bStart < $aEnd;
    }
    public static function validateAssignment(array $candidate,array $current): array {
        $issues=[];
        if(!isset($candidate['user_id'],$candidate['start_at'],$candidate['end_at'])) return ['MISSING_FIELDS'];
        if($candidate['end_at'] <= $candidate['start_at']) $issues[]='INVALID_INTERVAL';
        foreach($current as $row){
            if((int)$row['user_id']!==(int)$candidate['user_id'] ||
               ($row['status']??'ACTIVE')!=='ACTIVE' ||
               (isset($candidate['id']) && (int)$candidate['id']===(int)($row['id']??0))) continue;
            if(self::overlaps($candidate['start_at'],$candidate['end_at'],$row['start_at'],$row['end_at']))
                $issues[]='TIME_OVERLAP';
        }
        return array_values(array_unique($issues));
    }
    /** Requires externally verified group/qualification, effective approved rate, and actual-work verification. */
    public static function compensation(string $code, array $rate, bool $actualWorkVerified=false): string {
        if(!isset(self::TYPES[$code])) throw new InvalidArgumentException('Unknown type');
        if(($rate['approval_status']??'')!=='APPROVED' || empty($rate['effective_from']) ||
            empty($rate['approved_by']) || empty($rate['approved_at']) || empty($rate['source_reference']))
            throw new InvalidArgumentException('Compensation rate not legally approved');
        if(!$actualWorkVerified) throw new InvalidArgumentException('Actual duty not verified');
        $unit=(string)($rate['rate_unit']??'');
        $amount=(string)($rate['rate_amount']??'');
        if(!preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/D',$amount))
            throw new InvalidArgumentException('Invalid rate');
        if(!in_array($unit,['PER_HOUR','PER_SHIFT'],true)) throw new InvalidArgumentException('Invalid rate unit');
        if($unit==='PER_HOUR' && $code!=='บ') throw new InvalidArgumentException('Unsupported hourly duty');
        if($unit==='PER_HOUR'){
            // Exact integer cents avoids floating-point drift.
            $parts=explode('.',$amount);
            $cents=((int)$parts[0])*100+(int)str_pad($parts[1]??'',2,'0');
            $cents=intdiv($cents*self::TYPES[$code][3],60);
        }else{
            $parts=explode('.',$amount);
            $cents=((int)$parts[0])*100+(int)str_pad($parts[1]??'',2,'0');
        }
        return intdiv($cents,100).'.'.str_pad((string)($cents%100),2,'0',STR_PAD_LEFT);
    }
    public static function canMove(string $from,string $to): bool {
        return in_array($to,[
            'DRAFT'=>['PENDING_CONSENT','CANCELLED'],
            'PENDING_CONSENT'=>['PENDING_DIRECTOR','REJECTED','CANCELLED'],
            'PENDING_DIRECTOR'=>['PENDING_AUTHORITY','REJECTED','REVISION_REQUIRED'],
            'PENDING_AUTHORITY'=>['APPROVED','REJECTED','REVISION_REQUIRED']
        ][$from]??[],true);
    }
}
