<?php
declare(strict_types=1);
namespace App\Services;

/**
 * Duty V13 review gate. An application state transition is not an issued order.
 * Scope must be verified against a written delegation for the hospital.
 */
final class DutyReviewPolicy
{
    public const SCOPE='DUTY_APPROVAL';
    public static function allowed(string $role,string $from,string $action,bool $delegated): bool
    {
        return match($action) {
            'approve' => $from==='SUBMITTED' && $delegated && in_array($role,['DIRECTOR','SUPERADMIN'],true),
            'reject' => $from==='SUBMITTED' && $delegated && in_array($role,['DIRECTOR','SUPERADMIN'],true),
            'publish' => $from==='APPROVED' && in_array($role,['SUPERADMIN','ADMIN','SCHEDULER'],true),
            'lock' => $from==='PUBLISHED' && in_array($role,['SUPERADMIN','ADMIN'],true),
            default => false,
        };
    }
    public static function target(string $action): ?string
    {
        return ['approve'=>'APPROVED','reject'=>'DRAFT','publish'=>'PUBLISHED','lock'=>'LOCKED'][$action]??null;
    }
}
