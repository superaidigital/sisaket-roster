# Security notice / คำแนะนำด้านความปลอดภัย

**This repository contains application source code, not production configuration.**

- This GitHub package does **not** contain `config.php`, real database exports, live credentials, or actual employee records.
- Keep the repository **Private** while testing. Do not publish identifiable staff data, hashes, credentials, or database backups.
- Create `config.php` on the target server from `config.example.php`, set a strong database password and an independent 32+ character IP-hash secret. Do not commit it.
- **Public-safe source:** fixed-password demo account installers were deliberately excluded. Provision admins with a unique strong password using `bin/create_user.php` and avoid committing secrets.
- The V11 unit/security preflight checks do not replace penetration testing, real HTTP authorization tests, or review of currently LOCKED rosters against updated staffing/rest rules.
- Verify the deployed app is served from `/public/` only, with private folders inaccessible over HTTP.

If an actual secret was committed, remove it from Git history and rotate it immediately; adding a path to `.gitignore` afterward is not enough.
