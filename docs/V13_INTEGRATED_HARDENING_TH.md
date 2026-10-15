# Integrated V13 Security Hardening — Install / Verification

This increment is a **safe security patch**, **not** completion of leave, swapping, rate approval, payroll, electronic signature or production signoff. It preserves current V11 and Duty V13 data. No database migrations are run.

## Applied source changes

- \`app/Services/LoginThrottle.php\`: shared per-account and per-source-IP throttle with file locks across PHP sessions; account identifiers and IP addresses are HMAC-derived. **Single-host only** — switch to DB/Redis when load-balanced.
- \`app/Controllers/AuthController.php\`: checks throttle before password verification; records failures; clears account failures after successful authentication.
- \`app/Services/ExcelExporter.php\`: CSV fallback protects values beginning with formula triggers, including leading whitespace.
- \`.htaccess\` and directory-level \`.htaccess\` files: prevent access to application source, schemas, documents, CLI and tests under Apache when deployed in XAMPP subdirectory.
- \`config.example.php\`: optional placeholder for future encrypted leave data; **not yet implemented**.

## XAMPP rollout from an existing \`C:\xampp\htdocs\sisaket-roster-dev\`

1. Take both a SQL backup and a working-folder backup **before** updating. Verify the SQL backup exists and can be restored to a spare database.
2. Run \`git status\`. Never run \`git reset --hard\`, \`git clean -fd\` or use force pulls on local changes.
3. Inspect \`git diff\`, retain untracked files, and commit/stash local changes intentionally before switching branches. Do not add \`config.php\` to Git.
4. Fetch the branch \`feature/v13-integrated-hardening-20261015\` and review PR. **Do not merge this work into main until CI and UAT pass**.
5. Verify \`config.php\` sets a random \`app.ip_hash_secret\` of at least 32 non-placeholder characters. Use unique \`app.session_name\` for isolated deployments. Set \`cookie_secure=true\` when served via HTTPS.
6. Verify Apache \`AllowOverride\` permits \`.htaccess\`. Test that URL paths to \`/app/Core/Database.php\`, \`/database/schema.sql\`, \`/docs/\`, \`/bin/\`, \`/tests/\`, \`/.git/\` and \`/config.php\` are rejected, while \`/public/index.php?c=auth&a=login\` and public JS/CSS work.
7. From PowerShell:

~~~powershell
cd C:\xampp\htdocs\sisaket-roster-dev
C:\xampp\php\php.exe -l app\Controllers\AuthController.php
C:\xampp\php\php.exe -l app\Services\LoginThrottle.php
C:\xampp\php\php.exe -l app\Services\ExcelExporter.php
C:\xampp\php\php.exe tests\hardening_static_test.php
C:\xampp\php\php.exe tests\security_v11_test.php
C:\xampp\php\php.exe tests\duty_validation_test.php
C:\xampp\php\php.exe bin\duty_phase2_preflight.php
~~~

8. Perform HTTP tests with at least three accounts across two hospitals; verify a wrong password five times blocks a **new browser session**, and successful login still works after window expires. Verify that CSV values beginning \`=\`, \`+\`, \`-\`, \`@\` are treated as text in spreadsheet software.
9. Re-run Duty/V11 regression and inspect Apache logs without pasting secrets. Restore backup on failure.

### Important constraints

- Local XAMPP is not remotely accessible from ChatGPT; no files in your Windows directory were modified.
- This branch does not change/replace \`config.php\`, create/modify DB schemas, approve compensation or enable payments.
- Storing rate proposals is not legally binding approval, and printed documents remain marked previews.
- Apache \`.htaccess\` is not honored by Nginx. For production, point the web server DocumentRoot directly to \`public/\` and independently block access to all other folders.
- File-based rate limits use a host-local temp directory. Ensure permissions, cleanup and writable temp area for the Apache user; a storage failure intentionally prevents authentication rather than skipping the throttle.
