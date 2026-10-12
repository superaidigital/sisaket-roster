# คู่มืออัปเกรด Sisaket Roster Management V5 → V6

V6: **Version History + Version Comparison + Audit by Version + My Schedule**

## 1. สำรองข้อมูล (ต้องทำก่อน)

1. ใช้ phpMyAdmin → ฐานข้อมูล `sisaket_roster` → Export → SQL → Go และเก็บไฟล์สำรองไว้ในที่ปลอดภัย
2. สำรองโฟลเดอร์ `C:\xampp\htdocs\sisaket-roster` เช่นเป็น `sisaket-roster-backup-v5`
3. ถ้ามีผู้ใช้คนอื่นกำลังจัดเวร ให้หยุดการแก้ไขชั่วคราวระหว่างอัปเกรด

## 2. อัปเกรดด้วย PATCH

1. ดาวน์โหลด `Sisaket_Roster_XAMPP_V6_History_PATCH.zip`
2. แตก ZIP ใน `C:\xampp\htdocs\` โดยต้องได้โครงสร้าง `C:\xampp\htdocs\sisaket-roster\app\...`
3. อนุญาตให้แทนที่เฉพาะไฟล์ใน PATCH; อย่าลบโฟลเดอร์โปรเจกต์เดิม
4. **ไม่ต้องนำเข้า schema.sql หรือ seed.sql อีกครั้ง** เนื่องจาก V6 อ่านตารางเดิม (`rosters`, `roster_entries`, `roster_versions`, `roster_audit_logs`) ไม่เพิ่มโครงสร้างฐานข้อมูล
5. เก็บ `config.php` เดิมและข้อมูล MySQL เดิมไว้ตามปกติ
6. เปิด Apache / MySQL → เข้าระบบ → กด `Ctrl + F5` เพื่อบังคับโหลด JS/CSS ใหม่

## 3. ทดสอบ Version History

1. เข้า SUPERADMIN → ตารางเดือน **ตุลาคม 2569** ของ **รพ.สต. ตัวอย่าง ศรีสะเกษ**
2. กด **ประวัติ Version** → ต้องเห็น Version 1 / LOCKED (Revision 10) และ Version 2 / DRAFT (Revision 2) ตามข้อมูลทดสอบที่รายงานล่าสุด
3. เลือก Version 1 → Version 2 แล้วกด **เปรียบเทียบ**
4. เพราะแก้เวรวันที่ 7 ตุลาคมแล้วเปลี่ยนกลับ ข้อมูลสุดท้ายของทั้งสอง Version ควรเหมือนกัน (`0 ช่องเปลี่ยน`) หากไม่มีการแก้ไขอื่น แต่ Audit Log ของ Version 2 ควรยังเก็บการแก้ไขทั้งสองครั้งไว้
5. เลือก Version 2 → **ดูประวัติ** เพื่อตรวจ `UPDATE_SHIFT` และข้อมูลก่อน/หลัง; ตรวจ Version 1 แยกกัน

## 4. ทดสอบสิทธิ์และเวรส่วนบุคคล

1. เข้าสู่ระบบด้วย `STAFF` → ต้องไม่มีปุ่ม **ประวัติ Version**
2. STAFF เปิดลิงก์ `?c=roster&a=version_history&year=2026&month=10` โดยตรง (เพิ่ม `hospital_id` ตามที่ใช้) → ต้องได้ HTTP 403
3. STAFF เปิด `?c=roster&a=version_compare&from_id=...&to_id=...` → ต้องได้ HTTP 403 (รหัส Version ต้องมาจากฐานข้อมูลทดสอบเท่านั้น)
4. STAFF เปิด **เวรของฉัน** → ต้องเห็นเฉพาะของตนใน Version 1 ที่ LOCKED; เปลี่ยนเดือนหรือสั่งพิมพ์ได้
5. เปิด Version 2 ที่ยังเป็น DRAFT ด้วย URL ส่งออก / API ในฐานะ STAFF → ต้องถูกปฏิเสธ
6. ผู้จัดเวร `SCHEDULER` และ `DIRECTOR` เห็นประวัติได้เฉพาะหน่วยบริการตนเอง; ไม่สามารถเปรียบเทียบข้ามโรงพยาบาลหรือข้ามเดือนได้
7. ตาราง `LOCKED` ต้องบันทึกผ่าน AJAX/API ไม่สำเร็จแม้มีสิทธิ์สูง (HTTP 409)

## 5. ทดลองกับหลาย Version

- ใช้ฐานข้อมูลทดสอบก่อนเท่านั้น
- หลังสร้าง Version ใหม่ไม่ควรสร้างซ้ำจาก Version เดิมเมื่อมี Version ใหม่กว่าแล้ว (HTTP 409)
- Audit Log ยังต้องเก็บผู้ดำเนินการ เวลาที่ทำ และข้อมูลก่อน/หลัง

## 6. ข้อจำกัดที่ควรทราบ

- V6 ไม่ได้ใช้เครื่องมือ browser end-to-end กับ XAMPP บนเครื่องคุณ; ผลการทดสอบในชุด ZIP เป็น static + pure PHP tests เท่านั้น
- แสดงรายการ diff สูงสุด 200 แถวบนจอ (API อาจคืนมากกว่านั้น); Audit Log แสดง 100 รายการล่าสุดต่อ Version
- ไม่มีปุ่มคืนค่า Version อัตโนมัติ เพราะการคืนค่าต้องผ่านการสร้าง Version ใหม่ การตรวจสอบ และอนุมัติ
- PDF ส่วนบุคคลใช้ฟังก์ชัน Print → Save as PDF ของเบราว์เซอร์
- กฎเวร 2 คน/ช่วงเป็นเพียงค่าทดสอบ มิใช่การรับรองอัตรากำลังในสถานบริการจริง

## 7. ทดสอบโค้ดใน Command Prompt

```bat
cd C:\xampp\htdocs\sisaket-roster
C:\xampp\php\php.exe tests\version_v6_test.php
C:\xampp\php\php.exe tests\workflow_v4_test.php
C:\xampp\php\php.exe tests\autoschedule_v5_test.php
```

**ถ้าระบบมีข้อมูลสำคัญจริง ให้สำรองฐานข้อมูลและลองกับสภาพแวดล้อมทดสอบก่อนนำขึ้น Production**
