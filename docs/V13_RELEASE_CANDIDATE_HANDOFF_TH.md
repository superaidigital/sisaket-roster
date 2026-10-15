# SISAKET ROSTER V13 — Release Candidate handoff (not production acceptance)

Branch: \`feature/v13-release-candidate-workflows-20261015\`, based on \`feature/v13-integrated-hardening-20261015\`.

## Included in this increment
- Existing V11 and V13 draft UI, submit checks and GitHub hardening.
- A separate \`DutyReviewPolicy\` and \`DutyReviewService\`, with:
  - \`SUBMITTED -> APPROVED\`, \`SUBMITTED -> DRAFT\` (returned for correction)
  - \`APPROVED -> PUBLISHED\`, \`PUBLISHED -> LOCKED\`
  - Valid delegation row for approving/rejecting (\`approval_scope='DUTY_APPROVAL'\`), scope and effective dates; two-person approver rule
  - CSRF, RBAC, tenant check, row-level lock, revision conflict check, transaction + audit record.
- Role-filtered review buttons and explicit clinical-coverage reviewer attestation.
- Read-only \`bin/v13_release_preflight.php\` with no DB changes.

## Mandatory operator checks
1. Back up MySQL using phpMyAdmin or mysqldump; restore into a separate DEV database and verify it.
2. Back up the local repository directory, including untracked local files. Check \`git status\` first.
3. Review the draft PR and pass all GitHub workflows; never force-pull or hard-reset local work.
4. Review migrations, particularly \`database/migrations/20261015_duty_foundation.sql\` and \`database/migrations/20261015_v13_phase3_rate_proposals.sql\`. This PR contains **no migrations**.
5. Run from \`C:\xampp\htdocs\sisaket-roster-dev\`:

~~~powershell
C:\xampp\php\php.exe tests\duty_review_policy_test.php
C:\xampp\php\php.exe tests\duty_validation_test.php
C:\xampp\php\php.exe tests\duty_phase2_test.php
C:\xampp\php\php.exe tests\security_v11_test.php
C:\xampp\php\php.exe bin\v13_release_preflight.php
~~~

6. Confirm \`approval_delegations\` has an independently authenticated, legally valid current order covering \`DUTY_APPROVAL\`, hospital, authorized reviewer and dates. Do not fabricate or insert sample delegation records merely to pass preflight.
7. UAT with distinct accounts: SCHEDULER submits; correctly delegated DIRECTOR (not author) reviews; without delegation cannot approve; a different authorized actor publishes; lock after publication; compare immutable V11 output before and after. Test rejected, stale revision, leave overlap, holiday, and night-duty rollover.
8. Check all web static assets/Apache paths, browser back navigation, print preview and workstation sessions.

## Important limitations
- Not a government e-signature or an issued order. The reviewer attestation has no cryptographic identity proof.
- Duty skill coverage and staffing adequacy are not independently computed; a human attestation must be backed by actual professional verification.
- Leave request, swap consent and approval, rate activation/financial audit, payroll export, signed documents, LINE OA, PDPA retention and E2E browser regression are **not fully implemented or accepted**.
- Production readiness requires appropriate official orders, PDPA review, HTTPS, log monitoring, backup/restore, qualified approvals, and user acceptance.
- ChatGPT did not access or modify Windows XAMPP or the user's live MySQL data.
