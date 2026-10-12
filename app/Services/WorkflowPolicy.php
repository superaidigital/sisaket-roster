<?php
declare(strict_types=1);
namespace App\Services;

/** Centralized, database-independent workflow rules. Never trust UI permissions. */
final class WorkflowPolicy {
    private const STEPS = [
        'SUBMIT' => ['from'=>'DRAFT','to'=>'PENDING_APPROVAL','roles'=>['SUPERADMIN','ADMIN','SCHEDULER'],'by'=>'submitted_by','at'=>'submitted_at','check'=>true],
        'APPROVE' => ['from'=>'PENDING_APPROVAL','to'=>'APPROVED','roles'=>['SUPERADMIN','DIRECTOR'],'by'=>'approved_by','at'=>'approved_at','check'=>true],
        'REJECT' => ['from'=>'PENDING_APPROVAL','to'=>'DRAFT','roles'=>['SUPERADMIN','DIRECTOR'],'by'=>null,'at'=>null,'check'=>false],
        'PUBLISH' => ['from'=>'APPROVED','to'=>'PUBLISHED','roles'=>['SUPERADMIN','ADMIN','DIRECTOR'],'by'=>'published_by','at'=>'published_at','check'=>true],
        'LOCK' => ['from'=>'PUBLISHED','to'=>'LOCKED','roles'=>['SUPERADMIN','ADMIN','DIRECTOR'],'by'=>'locked_by','at'=>'locked_at','check'=>false],
    ];
    public static function step(string $action): ?array { return self::STEPS[$action] ?? null; }
    public static function roleAllows(string $action,string $role): bool {
        $s=self::step($action); return $s !== null && in_array($role,$s['roles'],true);
    }
    public static function can(string $action,string $role,string $status): bool {
        $s=self::step($action); return $s !== null && $s['from']===$status && self::roleAllows($action,$role);
    }
    public static function available(string $role,string $status): array {
        return array_values(array_filter(array_keys(self::STEPS),static fn(string $a):bool=>self::can($a,$role,$status)));
    }
}
