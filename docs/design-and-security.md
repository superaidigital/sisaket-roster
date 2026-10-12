# Design / Security / Operations Notes

## Scope & permission matrix

| Role | เลือก รพ.สต. | ดู Draft | บันทึก Draft | Submit | Approve/Reject | Publish/Lock | Staff Pool | Audit |
|---|---|---|---|---|---|---|---|---|
| SUPERADMIN | ทุกแห่ง | ทุกแห่ง | ✓ | ✓ | ✓ | ✓ | **ไม่ปรากฏ** | ✓ |
| ADMIN | ทุกแห่ง | ทุกแห่ง | ✓ | ✓ | — | ✓ | **ไม่ปรากฏ** | — |
| SCHEDULER | ของตน | ของตน | ✓ | ✓ | — | — | ✓ ถ้า active | — |
| DIRECTOR | ของตน | ของตน | — | — | ✓ | ✓ | ✓ ถ้า active | — |
| STAFF | ของตน | เฉพาะ Published/Locked | — | — | — | — | ✓ ถ้า active | — |
| HR | ของตน | ของตน (read-only) | — | — | — | — | **ไม่ปรากฏ** | — |

**บทบาทสำหรับงานจัดเวรกับบทบาทบริหารระบบต้องแยกกัน:** ผู้มี Role SUPERADMIN / ADMIN / HR ไม่ปรากฏใน Staff Pool แม้ active. ถ้ามีผู้ได้รับ Role SCHEDULER หรือ DIRECTOR และต้องปฏิบัติเวร ให้มีชื่อได้โดยไม่ขัดรายการที่ต้องตัดออก. การตัดสินใจนี้ยึดข้อกำหนดที่ให้มา ไม่ใช่เกณฑ์สมรรถนะบุคลากรทั่วไป

## Data model: object identity & immutable versions

- `rosters.id` คือ identity ของตารางหนึ่ง **Version**; `hospital_id+year+month+version` ระบุเดือนและรุ่นไม่ซ้ำ
- `roster_entries` เป็น 1 คน/วัน/เวอร์ชัน ด้วย Composite UNIQUE. `shift_types` กำหนดการแสดงผลและเวลา
- **แก้ Published/Locked ตรงไม่ได้**. API `new_revision` สำเนา `roster_entries` สู่แถว `rosters` ใหม่ status DRAFT และเพิ่ม `version` ทำให้ประวัติ Version 1 ไม่สูญหาย
- `roster_versions` เก็บ snapshot ของเหตุการณ์สำคัญ; `roster_audit_logs` เก็บเหตุการณ์แก้แต่ละ Cell และการเปลี่ยนสถานะ อ้างอิง Actor จริงใน Session
- หาก Version 2 ยัง Draft หรือ Pending, STAFF จะได้ Version 1 Published/Locked ล่าสุดจากเงื่อนไข Status
- การอนุมัติ ตรวจ validation แล้ว Lock Row + Check revision ใน Transaction; ถ้าใครแก้ระหว่างตรวจจะถูกปฏิเสธ
- Version Snapshot ปัจจุบันบันทึกตอนสร้าง Revision/เปลี่ยนสถานะ; Audit Log เก็บ before/after ของ Cell ระหว่าง Draft. ระบบไม่ได้เก็บ Full Snapshot แยกทุกครั้งที่กด Cell (ป้องกันข้อมูลซ้ำขนาดใหญ่)

## Security threats and mitigations

| Threat | Mitigation |
|---|---|
| SQL injection | PDO parameter binding / `ATTR_EMULATE_PREPARES=false`; no interpolated user SQL identifiers |
| XSS | PHP `htmlspecialchars`, JS `safe` before inserting dynamic HTML; avoid raw innerHTML from DB |
| CSRF | per-session random token on every mutating POST incl. logout |
| IDOR / hospital leak | DB-backed role; hospital scope from session; checked per roster id on every endpoint |
| Fake role/client tamper | Never trust role from request, User is queried from DB every request |
| Session hijack | HttpOnly, SameSite Lax, strict session, regenerate on login; HTTPS cookie when deployed |
| Concurrent edit | version check + `SELECT .. FOR UPDATE` row lock and transaction |
| Partial bulk changes | Explicit Transaction, validate every entry, rollback on exception |
| Password storage | `password_hash(PASSWORD_DEFAULT)` and `password_verify`; minimum 12 chars at provisioning |
| Exposure of visitor IP | `hash_hmac('sha256', ip, secret)` in audit instead of raw address |
| Leaking secrets | config.php in .gitignore and root Apache access denied; production public-only document root |
| Abuse of login | Session-based 5 attempts per 10 min; **not** a substitute for reverse-proxy/IP rate limiting |
| Excel injection | XLSX inlineStrings (not formulas); CSV fallback should be reviewed if arbitrary user content may be interpreted as spreadsheet formulas |

### Important hardening before exposure to Internet

1. Add application-level login throttling stored centrally across sessions (Redis or DB), 2FA for SUPERADMIN, account lock/reset process and administrator approval workflow.
2. Enforce server headers via Web Server: HTTPS/HSTS, CSP (allow only fixed Bootstrap CDN or self-host assets), no public directory listing, protected backup paths.
3. Formal PDPA notices and retention policy before collection of personnel data. `full_name`, assignment data and audit history may be personal data; implement deletion/anonymization and access reviews per approved policy.
4. No anonymous/public calendar API. Restrict exports and audits to authorized accounts. Consider stronger document download tracking before deployment.
5. Schedule backups and test restores; rotate secrets, protect DB credentials. Never use default blank root XAMPP credentials on production.
6. Verify all safety-critical staffing rules and minimum numbers with each facility: different job classifications, professional qualifications, staff unavailability, statutory leave, cross-midnight boundaries, and public holidays. The current algorithm is a baseline and **not** evidence of legal/clinical staffing compliance.

## Performance considerations

- Loading a month: `roster_entries` read once; `staff`, `shift_types`, `holidays`, `settings` read in batches; browser renders ~3100 cells for 100 staff and 31 days.
- Updating one Cell: Only the changed Cell sent to API; validation run server-side for feedback. After response, UI currently refreshes the month (simple predictable state model), so very frequent editing may require later client-side diff patching to eliminate re-render.
- Bulk: Single transaction and prepared statements reused; large operations generate audit logs for each Cell. On slower DB hosting, consider batched audit insert, temporary indexing or background batch (not included here).
- Use DB indexes: `rosters(hospital_id,year,month,status)`, `roster_entries(roster_id,user_id,work_date)` and `(roster_id,work_date,shift_type_id)`, `users(hospital_id,role,is_active)`.
- The scheduler balances personnel-type diversity within each shift as a soft preference but does not verify clinical qualifications. It is deterministic and greedy, optimizing locally. Complex qualification, seniority, contractual rest hours, staffing tiers, multiple shift per day and global fairness optimization are not yet modeled; add these only after unit-policy verification.

## Known limitations and integration

- Internet connection is needed to load Bootstrap, Icons and Google Fonts from CDN; for closed LAN, download approved copies into `public/vendor` and change the View URLs.
- The per-session login rate limit is not account-wide. Additional controls are mandatory for Internet exposure.
- Generated PDF is via browser Print dialog (Save as PDF), not server-side binary PDF generation. This supports correct Thai fonts without bundling font licenses but requires user interaction.
- A `LEAVE` shift type exists only as a roster label and is not a complete leave-request/approval module.
- The auto planner does not infer legal rest rules beyond provided constraints; human review is required before publishing.
- Holidays are stored as dates, not auto-imported; verify annual Thai holiday schedules from an official source before populating.
- Individual qualification constraints are not part of the supplied schema. If clinical coverage must distinguish RN vs medical officer, add shift skill constraints prior to real operation.
- Existing Roster Pro or Hospital Information System databases must be mapped with migrations. Do not deploy these CREATE TABLEs against an unrelated production schema without review.
