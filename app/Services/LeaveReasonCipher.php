<?php
declare(strict_types=1);
namespace App\Services;

use InvalidArgumentException;

/** Encryption at rest for sensitive leave reasons. No plaintext fallback. */
final class LeaveReasonCipher
{
    public static function key(): string
    {
        $hex=(string)(config('app')['sensitive_data_key']??'');
        if(!preg_match('/^[0-9a-f]{64}$/iD',$hex))
            throw new InvalidArgumentException('กรุณาตั้งค่า app.sensitive_data_key เป็นคีย์สุ่ม 64 ตัวอักษรฐานสิบหก');
        return hex2bin($hex);
    }
    public static function encrypt(string $reason): ?string
    {
        if($reason==='')return null;
        if(strlen($reason)>1500)throw new InvalidArgumentException('เหตุผลยาวเกิน 1500 ไบต์');
        $nonce=random_bytes(12);$tag='';
        $cipher=openssl_encrypt($reason,'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,$nonce,$tag,'leave-v1',16);
        if($cipher===false)throw new InvalidArgumentException('เข้ารหัสเหตุผลไม่สำเร็จ');
        return 'LV1'.$nonce.$tag.$cipher;
    }
    public static function decrypt(?string $stored): string
    {
        if($stored===null || $stored==='')return '';
        if(strlen($stored)<31 || substr($stored,0,3)!=='LV1')throw new InvalidArgumentException('รูปแบบเหตุผลเข้ารหัสไม่รองรับ');
        $clear=openssl_decrypt(substr($stored,31),'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,substr($stored,3,12),substr($stored,15,16),'leave-v1');
        if($clear===false)throw new InvalidArgumentException('ไม่สามารถถอดรหัสเหตุผลได้');
        return $clear;
    }
}
