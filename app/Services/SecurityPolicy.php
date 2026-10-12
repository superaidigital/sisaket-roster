<?php
declare(strict_types=1);
namespace App\Services;

/** V11: Pure authorization decisions; server MUST resolve the authenticated user from DB. */
final class SecurityPolicy {
    private const ALL_ROLES = ['SUPERADMIN','ADMIN','SCHEDULER','DIRECTOR','STAFF','HR'];
    private const MANAGERS = ['SUPERADMIN','ADMIN'];
    private const EDITORS = ['SUPERADMIN','ADMIN','SCHEDULER'];
    private const VERSION_VIEWERS = ['SUPERADMIN','ADMIN','SCHEDULER','DIRECTOR'];

    public static function hospitalAllowed(string $role,int $ownHospital,int $requestedHospital): bool {
        return $ownHospital > 0 && $requestedHospital > 0 &&
            in_array($role,self::ALL_ROLES,true) &&
            (in_array($role,self::MANAGERS,true) || $ownHospital === $requestedHospital);
    }
    public static function rosterReadable(string $role,string $status): bool {
        if(!in_array($role,self::ALL_ROLES,true)) return false;
        // STAFF can access only a version already published; never draft/approval.
        return $role !== 'STAFF' || in_array($status,['PUBLISHED','LOCKED'],true);
    }
    public static function rosterEditable(string $role,string $status): bool {
        return in_array($role,self::EDITORS,true) && $status === 'DRAFT';
    }
    public static function canManage(string $role): bool {
        return in_array($role,self::MANAGERS,true);
    }
    public static function canViewVersionHistory(string $role): bool {
        return in_array($role,self::VERSION_VIEWERS,true);
    }
    public static function canViewMySchedule(string $role): bool {
        return in_array($role,['STAFF','SCHEDULER','DIRECTOR'],true);
    }
}
