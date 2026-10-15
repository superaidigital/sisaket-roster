<?php
declare(strict_types=1);
namespace App\Services;

/**
 * Single-host, cross-session login throttle for XAMPP/Apache.
 * Use a shared DB/Redis implementation before deploying to multiple web servers.
 * Keys are HMACs; no raw account identifier or IP is stored in the state files.
 */
final class LoginThrottle
{
    private const WINDOW = 600;
    private const ACCOUNT_LIMIT = 5;
    private const IP_LIMIT = 30;

    private static function root(): string
    {
        $root=sys_get_temp_dir().DIRECTORY_SEPARATOR.'sisaket_roster_login_limiter';
        if (!is_dir($root) && !@mkdir($root,0700,true) && !is_dir($root)) {
            throw new \RuntimeException('Cannot initialize the protected login throttle');
        }
        return $root;
    }

    private static function digest(string $scope,string $value): string
    {
        $secret=(string)(config('app')['ip_hash_secret']??'');
        if(strlen($secret)<32 || str_starts_with($secret,'REPLACE_')) {
            throw new \RuntimeException('Configure a strong app.ip_hash_secret before login');
        }
        return hash_hmac('sha256',$scope."\0".$value,$secret);
    }

    /**
     * Exclusive lock protects read/modify/write from concurrent PHP processes.
     * Callback returns [newState, result]; no state is written when null.
     */
    private static function locked(string $key,callable $callback): mixed
    {
        $path=self::root().DIRECTORY_SEPARATOR.$key.'.json';
        $fp=@fopen($path,'c+');
        if(!$fp)throw new \RuntimeException('Cannot open login throttle storage');
        try {
            if(!flock($fp,LOCK_EX))throw new \RuntimeException('Cannot lock login throttle storage');
            rewind($fp);
            $raw=stream_get_contents($fp);
            $state=is_string($raw)?json_decode($raw,true):null;
            if(!is_array($state))$state=['since'=>time(),'count'=>0,'blocked_until'=>0];
            [$updated,$out]=$callback($state);
            if(is_array($updated)){
                $json=json_encode($updated,JSON_THROW_ON_ERROR);
                rewind($fp);
                if(!ftruncate($fp,0)||fwrite($fp,$json)!==strlen($json)||!fflush($fp))
                    throw new \RuntimeException('Cannot persist login throttle');
            }
            return $out;
        }finally{flock($fp,LOCK_UN);fclose($fp);}
    }

    private static function buckets(string $email): array
    {
        $ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');
        return [
            [self::digest('account',mb_strtolower(trim($email))),self::ACCOUNT_LIMIT],
            [self::digest('remote-ip',$ip),self::IP_LIMIT]
        ];
    }

    public static function retryAfter(string $email): int
    {
        $remaining=0;$now=time();
        foreach(self::buckets($email) as [$key,$limit]){
            $retry=self::locked($key,static function(array $s)use($now):array{
                $blocked=(int)($s['blocked_until']??0);
                return [null,max(0,$blocked-$now)];
            });
            $remaining=max($remaining,$retry);
        }
        return $remaining;
    }

    public static function recordFailure(string $email): void
    {
        $now=time();
        foreach(self::buckets($email) as [$key,$limit]){
            self::locked($key,static function(array $s)use($now,$limit):array{
                if($now-(int)($s['since']??0)>=self::WINDOW){
                    $s=['since'=>$now,'count'=>0,'blocked_until'=>0];
                }
                $s['count']=(int)($s['count']??0)+1;
                if($s['count']>=$limit)$s['blocked_until']=max((int)($s['blocked_until']??0),$now+self::WINDOW);
                return [$s,null];
            });
        }
    }

    public static function clearAccount(string $email): void
    {
        $key=self::digest('account',mb_strtolower(trim($email)));
        self::locked($key,static fn(array $s):array=>[
            ['since'=>time(),'count'=>0,'blocked_until'=>0],null
        ]);
    }
}
