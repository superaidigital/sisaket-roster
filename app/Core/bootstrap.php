<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class,'App\\')) return;
    $path = dirname(__DIR__) . '/' . str_replace('\\','/',substr($class,4)) . '.php';
    if (is_file($path)) require $path;
});

function config(?string $section=null): mixed {
    static $c = null;
    if ($c===null) {
        $file=dirname(__DIR__,2).'/config.php';
        if (!is_file($file)) throw new RuntimeException('Missing config.php. Copy config.example.php and edit database settings.');
        $c=require $file;
        date_default_timezone_set($c['app']['timezone'] ?? 'Asia/Bangkok');
    }
    return $section===null ? $c : ($c[$section] ?? null);
}
function e(?string $s): string { return htmlspecialchars($s ?? '',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function wantsJson(): bool {
    return (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json') || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || ($_SERVER['REQUEST_METHOD'] ?? '')==='POST';
}
function jsonOut(array $data,int $code=200): never {
    http_response_code($code); header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); exit;
}
function jsonError(string $message,int $code=400, array $extra=[]): never {
    jsonOut(['success'=>false,'message'=>$message]+$extra,$code);
}
function jsonBody(): array {
    $raw=file_get_contents('php://input');
    if (strlen($raw)>1024*1024) jsonError('ข้อมูลที่ส่งใหญ่เกินกำหนด',413);
    $data=json_decode($raw,true);
    if (!is_array($data)) jsonError('JSON ไม่ถูกต้อง');
    return $data;
}
function requirePost(): void { if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') jsonError('รองรับ POST เท่านั้น',405); }
function intInput(mixed $x,int $min,int $max,string $label): int {
    $n=filter_var($x,FILTER_VALIDATE_INT);
    if ($n===false || $n<$min || $n>$max) jsonError('ค่า '.$label.' ไม่ถูกต้อง',422);
    return $n;
}
function monthInput(array $in): array {
    $y=intInput($in['year'] ?? date('Y'),2000,2100,'ปี ค.ศ.');
    $m=intInput($in['month'] ?? date('n'),1,12,'เดือน');
    return [$y,$m];
}
function auditIpHash(): string {
    // Avoid saving raw IP addresses; stable keyed hash can be rotated by changing secret.
    $ip=(string)($_SERVER['REMOTE_ADDR'] ?? 'CLI');
    return hash_hmac('sha256',$ip,(string)(config('app')['ip_hash_secret'] ?? ''));
}
