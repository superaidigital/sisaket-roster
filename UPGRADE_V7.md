# Sisaket Roster Management V7 — อ่าน Audit Log ง่ายขึ้น และเวรของฉันบนมือถือ

> อัปเกรดจาก **V6** โดยไม่ลบตารางเวร บัญชี ผู้ใช้ หรือประวัติ Version เดิม

## ความสามารถใหม่

1. **Audit Log แบบอ่านง่าย** ในหน้าประวัติ Version: แสดงชื่อบุคลากร, วันที่เวร, เวลากระทำ, ค่าก่อนและหลังเป็นชื่อเวร (เช้า/บ่าย/ดึก/OFF) โดยกดดูได้ในแต่ละรายการ ไม่ใช่เพียง `shift_type_id`.
2. **เก็บ JSON ต้นฉบับไว้** ภายใต้ปุ่ม “ข้อมูลต้นฉบับ (JSON) สำหรับผู้ดูแล” เพื่อสืบค้นและตรวจสอบย้อนหลัง โดยไม่เปลี่ยนแปลง Audit Log ในฐานข้อมูล.
3. **รองรับเวรประเภทที่ปิดใช้งานแล้ว** ในการอ้างชื่อเวรย้อนหลัง; หากไม่มีประเภทเวรในฐานข้อมูล จะแสดงรหัสอย่างระมัดระวังแทนการคาดเดา.
4. **หน้าเวรของฉัน (My Schedule) ใช้งานบนมือถือได้ง่ายขึ้น**: การ์ดเรียงวัน 1 คอลัมน์, ชื่อวัน, สีเวร, ชั่วโมงงาน, หมายเหตุ, ปุ่มเปลี่ยนเดือน, พิมพ์/PDF, touch target >=44px และการนำเสนอสำหรับการพิมพ์ 7 คอลัมน์.
5. **แยกวัน OFF จากวันที่ไม่กำหนดเวร** และแสดง Version ที่เผยแพร่แล้วให้เห็นชัดเจน.
6. **คงกฎสิทธิ์เดิม**: STAFF ดูเฉพาะตาราง PUBLISHED/LOCKED ล่าสุดของโรงพยาบาลตนเอง ไม่รับ `user_id` จาก URL; ไม่สามารถดู API ประวัติ Version หรือ Draft ผ่านหน้าเว็บนี้ได้.

## ก่อนติดตั้ง

1. สำรองฐานข้อมูลผ่าน phpMyAdmin เป็น `.sql` โดยเก็บไฟล์ไว้ที่ปลอดภัย.
2. สำรองโฟลเดอร์ `C:\xampp\htdocs\sisaket-roster` เช่น `sisaket-roster-backup-v6`.
3. ตรวจสอบว่าติดตั้ง **V6** แล้วและทดสอบประวัติ Version/ Audit Log ผ่านตามภาพก่อนหน้า.

## ติดตั้ง PATCH V7

1. หยุด Apache ใน XAMPP ชั่วคราว (MySQL จะเปิดอยู่ก็ได้).
2. แตก `Sisaket_Roster_XAMPP_V7_Audit_Mobile_PATCH.zip` ใน `C:\xampp\htdocs\` แล้วรวมโฟลเดอร์ `sisaket-roster` กับของเดิมโดยยอมแทนที่ไฟล์ที่ตรงกัน.
3. **ห้ามลบหรือเขียนทับ `config.php`** และ **ไม่ต้องรัน `schema.sql` / `seed.sql` / migrations ใด ๆ**.
4. เปิด Apache, MySQL แล้วเข้า `http://localhost/sisaket-roster/`.
5. กด `Ctrl+F5` เพื่อรีเฟรช JavaScript/CSS.

## ทดสอบ V7 แบบไม่กระทบตารางเดิม

### A. Audit Log

1. เข้าสู่ระบบ `SUPERADMIN` และเลือกเดือนตุลาคม 2569 ที่มี Version 1 LOCKED และ Version 2 DRAFT.
2. กด **ประวัติ Version** → เลือก Version 2 ใน **Audit Log** → **ดูประวัติ**.
3. เปิดรายการ `UPDATE_SHIFT` เวลา 14:39:11 และ 14:43:00 ของวันที่ 7 ต.ค. 2569.
4. ตรวจว่าแสดง **ดึก → บ่าย**, และ **บ่าย → ดึก** พร้อมชื่อบุคลากร/ผู้แก้ไข/เวลา.
5. เปิด “ข้อมูลต้นฉบับ (JSON) สำหรับผู้ดูแล” และตรวจว่ายังมีข้อมูลเดิม โดยไม่แสดง `<script>` เป็นโค้ด HTML.

### B. My Schedule (Desktop / Mobile)

1. ออกจาก SUPERADMIN และเข้าสู่ระบบด้วยบัญชีบุคลากรทดสอบ 01 (`STAFF`).
2. คลิกปุ่ม **เวรของฉัน** บนหน้า Dashboard.
3. เลือกตุลาคม 2569; ต้องแสดง **Version 1 ที่ LOCKED** ไม่ใช่ Draft Version 2.
4. ใช้ Chrome DevTools → Responsive 375px และ 768px; การ์ดวันที่บนจอเล็กต้องเรียงคอลัมน์เดียว ไม่มีข้อความล้น.
5. ตรวจ OFF และ “ยังไม่กำหนดเวร” ต้องแยกกัน, และทดสอบพิมพ์เป็น PDF.
6. **ความปลอดภัยเพิ่มเติม**: ตรวจผ่าน Network/การทดสอบ Integration ว่าคำขอ API ที่พยายามขอดู Draft หรือบันทึก LOCKED ถูกปฏิเสธ (การตรวจหน้าจออย่างเดียวไม่เพียงพอ).

## ไฟล์ที่อัปเดต

- `app/Services/AuditLogPresenter.php` (ใหม่)
- `app/Controllers/RosterController.php`
- `app/Models/RosterModel.php`
- `public/js/roster.js`
- `public/css/roster.css`
- `app/Views/roster/my_schedule.php`
- `tests/audit_v7_test.php` (ใหม่)
- `UPGRADE_V7.md`, `PATCH_README_V7.txt` และส่วน V7 ใน `README.md`

## ตรวจสอบในเครื่องพัฒนา (CLI)

```bat
cd C:\xampp\htdocs\sisaket-roster
C:\xampp\php\php.exe tests\audit_v7_test.php
C:\xampp\php\php.exe tests\version_v6_test.php
C:\xampp\php\php.exe tests\workflow_v4_test.php
```

**ขอบเขตการรับรอง:** ผ่านการตรวจ PHP/JavaScript Syntax และการทดสอบจำลอง/Static เท่านั้น ยังไม่ได้รันทดสอบแบบครบวงจรกับฐานข้อมูล MySQL จริงบน XAMPP ของคุณ ข้อมูลทดสอบในระบบควรแยกจากข้อมูลบุคลากรจริง.
