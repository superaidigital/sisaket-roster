# Phase 2: Draft duty matrix (DEV only)

## Development scope
- New route `index.php?c=duty&a=index`; old V11 remains at roster route.
- A responsive matrix permits independent duty codes ย (holiday), บ (afternoon), ร (overnight On Call) in a day.
- Phase 2 is only DRAFT management. No actual-payroll, official approvals, public publication or paid-claim execution.
- API creates a new Version 1 Draft for a new month only; if a non-Draft already exists, it intentionally returns 409 and does not overwrite history.
- Writes use CSRF, authenticated user, hospital access, editor role, revision compare, MySQL row locks, leave overlap, V11 published-shift and OT conflicts, and audit events.
- Employees (STAFF) see only their own published/locked data; no edit UI or server-side edit permission.

## Install
Prerequisite: pull Phase 1 foundation migration into a backed-up, selected DEV database as documented in docs/DUTY_ELEAVE_FOUNDATION_PHASE1_TH.md.
Test in a NEW checkout folder to preserve local OT V12 patch and uncommitted files:

```powershell
cd C:\xampp\htdocs
git clone --branch feature/duty-phase2-draft-ui-20261015 https://github.com/superaidigital/sisaket-roster.git sisaket-duty-phase2
cd .\sisaket-duty-phase2
Copy-Item config.example.php config.php
code config.php
C:\xampp\php\php.exe -l app\Services\DutyRosterService.php
C:\xampp\php\php.exe -l app\Controllers\DutyController.php
C:\xampp\php\php.exe -l app\Views\duty\index.php
C:\xampp\php\php.exe -l public\index.php
C:\xampp\php\php.exe tests\duty_foundation_test.php
C:\xampp\php\php.exe tests\duty_phase2_test.php
C:\xampp\php\php.exe tests\security_v11_test.php
C:\xampp\php\php.exe bin\diagnose.php
```

Set config.php to an isolated database and avoid reusing the production session cookie name on the same localhost host. After Apache serves the new project path, navigate to `http://localhost/sisaket-duty-phase2/public/index.php?c=duty&a=index`. Never expose /app or /database publicly to the internet; configure public docroot for production.

## Critical known limitations: block real-world launch
- Cross-table V11 conflict check conservatively treats V11 published working shifts as blocking OT time overlaps; policy for approved dual duty needs legal/workplace signoff.
- No final proof of DB-backed end-to-end test, no actual staffing-minimum/skill/physical-rest validator, no department-specific leave entitlement, no duty version creation after publication.
- `duty_assignments` unique index prevents same duty code twice in one Draft, but complex cross-version concurrency and payroll anti-duplicate need additional integration review.
- No binding of an official holiday-calendar data feed; holiday codes rely on the V11 `holidays` table plus weekends.
- This is not the old OT V12 module and does not reinstate its compensation or payout endpoints.
- A 409 response is expected when another operator changes the revision; refresh before retry.
- Review schema compatibility with local MySQL 8 and run a clean database integration suite before merging.