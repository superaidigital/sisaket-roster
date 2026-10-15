# Sisaket PAO Smart Duty and e-Leave — Phase 1 Foundation

**Status: foundation only. Not production-ready or authorized for actual payroll claims.**

## Shipped
- Additive migration: `database/migrations/20261015_duty_foundation.sql`.
- Pure policy: `app/Services/DutyPolicy.php` with times, overlaps, compensation safeguards and swap-state transitions.
- Pure tests: `tests/duty_foundation_test.php` (no database access).
- No controllers, routes, live UI or payout automation is delivered in phase 1. The removed local OT V12 PATCH is a separate matter.
- Existing V11 tables, records, versions and existing OT tables are untouched.

## Required legal and accounting confirmation
- Group 3 afternoon 160 THB is the requested prospective business rule, while an attached previous document specified 165 THB. Historical payments must remain at historical approved rates.
- All 9 rates are inserted as `DRAFT`, with no effective date; they must not become payable without confirmation of documented authorization, budget, effective dates, actual-duty evidence, eligible payee and authorized group assignment.
- DIRECTOR or SUPERADMIN role alone never confers legal signature/delegation authority.
- Personnel medical details require app-layer encryption, access restriction, and retention/privacy rules not supplied in phase 1.

## Safe XAMPP install on a backup DEV database
1. Back up the current database and verify test restoration before migration.
2. In phpMyAdmin select your *development* database explicitly. Migration has NO `USE`, `DROP`, or `ALTER` statements.
3. Run/import `database/migrations/20261015_duty_foundation.sql` only on selected development database.
4. Verify with `SHOW TABLES LIKE 'duty_%';`, `SHOW TABLES LIKE 'compensation_%';`, and `SELECT approval_status,COUNT(*) FROM compensation_rates GROUP BY approval_status;`. Nine proposed rules should initially be DRAFT.

## Safest new checkout (avoid staged OT V12 edits)
Use a separate folder instead of force-switching your existing `sisaket-roster-dev` with staged/uncommitted OT files:

```powershell
cd C:\xampp\htdocs
git clone --branch feature/duty-eleave-foundation-20261015 https://github.com/superaidigital/sisaket-roster.git sisaket-roster-foundation
cd .\sisaket-roster-foundation
C:\xampp\php\php.exe tests\duty_foundation_test.php
C:\xampp\php\php.exe tests\security_v11_test.php
```

## Important incomplete implementation gates
- Transactional Duty APIs must enforce RBAC, hospital scope, duplicate overlaps across roster versions, approved-leave collisions, holidays, skills and rest minimums before insert/update.
- Mutable draft versions only; published/locked histories must not be overwritten.
- Swaps need stored consent, legitimate delegate/authority approval, stale-revision checks and audit.
- Staff pay groups and rate effective ranges must be checked for overlaps and legally approved by authorized roles.
- Payroll must require actual-work proof, closed-run immutability and adjustments rather than overwrites; UNIQUE within one payroll run alone does NOT prevent cross-run double claims.
- e-Leave needs entitlement policy and ledger plus encrypted medical details, attachments, audits and retention.
- Run real PHP/MySQL integration tests, documentation signoff and UAT before launch.

## Developer note
Existing `roster_entries` has one user/day/version uniqueness. New `duty_assignments` enables multiple duty types per day without modifying V11. Application-level interval validation remains mandatory.