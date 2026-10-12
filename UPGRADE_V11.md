# คู่มืออัปเกรด Sisaket Roster Management V11 — Security & Production Readiness

V11 ต่อยอด V10 โดยเพิ่มการตัดสินสิทธิ์รวมศูนย์, ตรวจ Revision ก่อนสร้าง Version ใหม่ใน Transaction, และเครื่องมือทดสอบแบบไม่แก้ไขฐานข้อมูล

## สิ่งที่เพิ่ม/แก้ไข

1. `app/Services/SecurityPolicy.php` — แยกกฎสิทธิ์ตาม Role + Hospital + Status เพื่อทดสอบแยกได้
2. `app/Core/Auth.php` — ตรวจ Hospital ID โดยใช้กฎฝั่งเซิร์ฟเวอร์; ไม่เชื่อ Role หรือ Hospital จาก Frontend
3. `app/Controllers/RosterController.php` — ใช้ SecurityPolicy สำหรับดู/แก้ตารางและดู Version History
4. `app/Models/RosterModel.php` — ตรวจ Revision ซ้ำหลัง `SELECT ... FOR UPDATE` ก่อนสร้าง Version ใหม่
5. `tests/security_v11_test.php` — ชุดทดสอบสิทธิ์/Workflow โดยไม่ใช้ฐานข้อมูล
6. `bin/security_preflight_readonly.php` — อ่านสถานะหน่วยบริการ ตารางเวร และการแสดงผลต่อ STAFF จาก MySQL (อ่านอย่างเดียว)
7. `tests/security_http_smoke.ps1` — ตรวจ URL ที่ไม่ควรถูกเปิดเผย และ endpoint ประวัติที่ยังไม่ login (อ่านอย่างเดียว)

**ไม่ต้องรัน SQL Migration, ไม่ต้องเปลี่ยน config.php และไม่ต้องสร้างบัญชีผู้ใช้ใหม่**

## ติดตั้งบน XAMPP (อัปเกรดจาก V10)

1. สำรองฐานข้อมูล `sisaket_roster` ผ่าน **phpMyAdmin → Export → Quick → SQL** และทดสอบว่าไฟล์สำรองเปิดอ่านได้
2. สำรองโฟลเดอร์ `C:\xampp\htdocs\sisaket-roster` (โดยเฉพาะ `config.php`)
3. หยุด Apache ชั่วคราว; ไม่จำเป็นต้องลบฐานข้อมูล
4. แตก `Sisaket_Roster_XAMPP_V11_Security_PATCH.zip` ลงใน `C:\xampp\htdocs\` โดยคงชื่อโฟลเดอร์ `sisaket-roster`
5. เปิด Apache/MySQL แล้วกด Ctrl+F5
6. ตรวจว่าระบบ V2 DRAFT / Rev 3 และ V1 LOCKED / Rev 10 ยังเปิดได้ตามสิทธิ์เดิม

> PATCH แทนที่เฉพาะไฟล์ที่เปลี่ยนแปลงและเพิ่มไฟล์ใหม่; ไม่มี schema.sql หรือ seed.sql สำหรับนำเข้าอีกครั้ง

## ทดสอบโค้ดบน PowerShell

```powershell
cd C:\xampp\htdocs\sisaket-roster
C:\xampp\php\php.exe tests\security_v11_test.php
C:\xampp\php\php.exe bin\security_preflight_readonly.php SSK-DEMO-001 2026 10
powershell -NoProfile -ExecutionPolicy Bypass -File tests\security_http_smoke.ps1
```

คาดว่าผลชุดแรก: `PASS ... 309 assertions` สำหรับตรรกะ Unit Test

คาดว่าชุด preflight จะรายงาน Version 1 LOCKED และ Version 2 DRAFT (ตามข้อมูลเครื่องที่ทดสอบก่อนหน้า) และ **Visible to STAFF: Version 1 LOCKED**; รายงานไม่แก้ข้อมูลใด ๆ

HTTP Smoke Test ควรแสดง 403/404 เมื่อเข้าถึง `config.php`, `app/`, `database/`, `bin/` และ HTTP 302/401/403 เมื่อยังไม่เข้าสู่ระบบแล้วขอ `version_history` โดยตรง

## ทดสอบเชิงบูรณาการจริง (ยังต้องทำบน XAMPP ที่มีบัญชีทดสอบ)

| กรณี | ผู้ทดสอบ | ผลที่ต้องได้ |
|---|---|---|
| เปิด `roster.data` เดือนที่มี Version 2 DRAFT | STAFF | เห็นเฉพาะ Version 1 LOCKED |
| เรียก `version_history` หรือ `version_compare` | STAFF | HTTP 403 |
| POST `save_shift` โดยมี CSRF ถูกต้องบน Version 2 | STAFF | HTTP 403 และข้อมูลไม่เปลี่ยน |
| POST `save_shift` โดยมี CSRF ถูกต้องบน Version 1 LOCKED | SUPERADMIN | HTTP 409 และข้อมูลไม่เปลี่ยน |
| เข้าถึงตารางของ รพ.สต. B | STAFF/SCHEDULER ของ รพ.สต. A | HTTP 403 หรือ 404 และไม่เปิดเผยข้อมูล |
| บันทึก Draft จากสอง Session ด้วย expected_revision เดียวกัน | SCHEDULER/ADMIN | สำเร็จครั้งแรก; ครั้งถัดไป HTTP 409 conflict |
| สร้าง Version ใหม่ด้วย expected_revision ที่เก่า | SUPERADMIN | HTTP 409 conflict |
| Logout แล้วเข้าหน้า Version History | ทุก Role | Redirect ไปหน้า Login หรือ HTTP 401 |

**สำคัญ:** ต้องใช้ข้อมูลทดสอบและใช้ Session/CSRF จริง ห้ามอาศัยเพียงการซ่อนปุ่มใน UI หรือเปลี่ยน Role ด้วย Developer Tools; HTTP status กับข้อมูลในฐานข้อมูลต้องตรงตามเกณฑ์ด้วย

### วิธีอ่านสถานะ HTTP
- `401` ยังไม่ได้เข้าสู่ระบบ
- `403` ไม่มีสิทธิ์
- `409` สถานะหรือ Revision ขัดแย้ง
- `419` CSRF ไม่ถูกต้อง (ไม่ได้พิสูจน์ว่า RBAC ผ่าน)

### ข้อจำกัดและความพร้อมระบบจริง
- Unit Tests และ Static checks **ไม่ใช่** การทดสอบเจาะระบบหรือ Security Audit อย่างสมบูรณ์
- การตรวจข้าม รพ.สต. ต้องมี **หน่วยบริการทดสอบแห่งที่ 2** และบัญชีแยกจริง; การมีหน่วยบริการเดียวไม่สามารถยืนยัน isolation ผ่าน HTTP ได้
- XAMPP HTTP localhost ใช้ `cookie_secure=false` ได้สำหรับทดสอบเท่านั้น; ระบบภายนอกต้องใช้ HTTPS และตั้ง `cookie_secure=true`
- ควรเลิกใช้บัญชีเดโม/รหัสผ่านสั้น, จำกัดการเข้าถึงผู้ดูแล, ใช้รหัสผ่านฐานข้อมูลเฉพาะระบบและกำหนดสิทธิ์ขั้นต่ำ, ตรวจการสำรอง/กู้คืนจริง
- ตาราง **Version 1 ที่ล็อกอยู่ยังมี Critical 20** จากข้อจำกัดช่วงพักขั้นต่ำตามที่รายงาน ไม่ควรถือว่าปลอดภัยสำหรับปฏิบัติงานจริงโดยอัตโนมัติ
- การเปลี่ยนทักษะมีผลต่อการตรวจย้อนหลัง ณ ปัจจุบัน; ระบบยังไม่มีข้อมูลคุณสมบัติที่มีผลตามวันย้อนหลัง (effective-dated staff skills)
- การปรับสมดุล 9 คู่ใน V10 ที่ Preview แล้ว ยังต้องยืนยันว่าบันทึก Revision 4 จริงหรือไม่; Preflight ก่อนหน้านี้ยังพบ Revision 3

## วิธีคืนกลับ

1. หยุด Apache แล้วคืนโฟลเดอร์สำรอง V10
2. ไม่ต้อง Restore SQL หากยังไม่ได้ทำการแก้ไขข้อมูลอื่นหลังอัปเกรด (V11 ไม่เปลี่ยน schema หรือข้อมูล)
3. หากข้อมูลใน DB มีการแก้ไขหลังอัปเกรด ให้ตรวจรายการ Transaction/Audit และวางแผน Restore อย่างระมัดระวัง (การ Restore จะทำให้ข้อมูลใหม่สูญหาย)
