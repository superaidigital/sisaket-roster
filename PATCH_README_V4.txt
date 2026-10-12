SISAKET ROSTER V4 — WORKFLOW READINESS PATCH

1. Back up the existing MySQL database and XAMPP project folder.
2. Unzip to C:\xampp\htdocs\ (result: sisaket-roster folder).
3. Confirm app\Services\WorkflowPolicy.php exists.
4. Restart Apache; use Ctrl+F5 in Chrome.
5. Run: C:\xampp\php\php.exe tests\workflow_v4_test.php
6. Optional read-only diagnostic: C:\xampp\php\php.exe bin\roster_preflight.php SSK-DEMO-001 2026 10

NO schema migrations. DO NOT replace config.php. Do not re-import schema.sql/seed.sql.
Read UPGRADE_V4.md for full instructions in Thai.
