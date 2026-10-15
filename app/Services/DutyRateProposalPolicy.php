<?php
declare(strict_types=1);
namespace App\Services;

use DateTimeImmutable;
use InvalidArgumentException;

/** Pure validation for DRAFT-only rate proposals; NEVER authorizes payments. */
final class DutyRateProposalPolicy
{
    public static function normalize(array $input): array
    {
        $group = filter_var($input['group_id'] ?? null, FILTER_VALIDATE_INT);
        $code = $input['duty_code'] ?? null;
        $unit = $input['rate_unit'] ?? null;
        if (!in_array($group, [1, 2, 3], true)
            || !is_string($code) || !in_array($code, ['ย', 'บ', 'ร'], true)
            || !is_string($unit) || !in_array($unit, ['PER_SHIFT', 'PER_HOUR'], true)) {
            throw new InvalidArgumentException('กลุ่มตำแหน่ง ประเภทเวร หรือหน่วยอัตราไม่ถูกต้อง');
        }
        if ($unit === 'PER_HOUR' && $code !== 'บ') {
            throw new InvalidArgumentException('อัตรารายชั่วโมงใช้เฉพาะเวร บ');
        }

        $raw = $input['rate_amount'] ?? null;
        if (!is_string($raw) || !preg_match('/^(?:0|[1-9]\d{0,6})(?:\.\d{1,2})?$/D', $raw)) {
            throw new InvalidArgumentException('จำนวนเงินต้องเป็นทศนิยมไม่เกิน 2 ตำแหน่ง (ไม่เกิน 9,999,999.99)');
        }
        [$baht, $sat] = array_pad(explode('.', $raw, 2), 2, '');
        $amount = $baht . '.' . str_pad($sat, 2, '0', STR_PAD_RIGHT);

        $from = $input['effective_from'] ?? null;
        $to = $input['effective_to'] ?? null;
        if (!is_string($from) || !self::validDate($from)
            || ($to !== null && (!is_string($to) || !self::validDate($to) || $to < $from))) {
            throw new InvalidArgumentException('วันที่มีผลบังคับใช้ไม่ถูกต้อง');
        }
        $eligible = $input['on_call_pay_eligible'] ?? false;
        if (!is_bool($eligible)) {
            throw new InvalidArgumentException('สิทธิ์ On Call ต้องเป็น true หรือ false');
        }
        if ($code !== 'ร' && $eligible) {
            throw new InvalidArgumentException('เลือกสิทธิ์ On Call ได้เฉพาะเวร ร');
        }
        if ($code === 'ร' && $eligible === false && $amount !== '0.00') {
            throw new InvalidArgumentException('เวร ร ที่ไม่มีสิทธิ์รับเงินต้องกำหนดอัตรา 0.00');
        }

        $reference = $input['legal_reference'] ?? null;
        $reason = $input['reason'] ?? null;
        if (!is_string($reference) || !is_string($reason)) {
            throw new InvalidArgumentException('กรุณาระบุเอกสารอ้างอิงและเหตุผล');
        }
        $reference = trim($reference);
        $reason = trim($reason);
        if (mb_strlen($reference) < 5 || mb_strlen($reference) > 500
            || mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
            throw new InvalidArgumentException('หลักฐานและเหตุผลต้องมีความยาว 5–500 ตัวอักษร');
        }
        return [
            'group_id' => $group,
            'duty_code' => $code,
            'rate_unit' => $unit,
            'rate_amount' => $amount,
            'effective_from' => $from,
            'effective_to' => $to,
            'on_call_pay_eligible' => $eligible,
            'legal_reference' => $reference,
            'reason' => $reason,
        ];
    }

    private static function validDate(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) return false;
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $d !== false && $d->format('Y-m-d') === $value
            && (int)substr($value, 0, 4) >= 2000 && (int)substr($value, 0, 4) <= 2100;
    }
}
