# อัปเกรด Sisaket Roster V3 → V4: ตรวจความพร้อมและ Workflow

## สิ่งที่เพิ่ม
- แผง **ตรวจความพร้อมก่อนส่งอนุมัติ** แสดงจำนวนบุคลากร/เกณฑ์ขั้นต่ำ/เวรที่บันทึก/ปัญหา Critical/Warning
- ระบบตรวจสิทธิ์การเปลี่ยนสถานะแบบรวมศูนย์ (`WorkflowPolicy`)
- ตรวจ Critical ภายหลังล็อกตารางใน Transaction ก่อน `SUBMIT`, `APPROVE`, `PUBLISH`
- ชุดทดสอบกฎ Workflow แบบไม่ต้องต่อฐานข้อมูล
- คำสั่ง Preflight อ่านอย่างเดียว ไม่แก้ไขฐานข้อมูล

## ก่อนติดตั้ง
1. Export ฐานข้อมูล `sisaket_roster` ผ่าน phpMyAdmin และตรวจว่าไฟล์สำรองเปิดอ่านได้
2. สำรองโฟลเดอร์ `C:\xampp\htdocs\sisaket-roster` เดิม
3. ตรวจว่าติดตั้ง V3 และใช้งานเมนูนำเข้าบุคลากรแล้ว

## ติดตั้ง PATCH
1. ปิด Apache ใน XAMPP ชั่วคราว
2. แตก `Sisaket_Roster_XAMPP_V4_Workflow_PATCH.zip` ลง `C:\xampp\htdocs\` ให้ไฟล์ใน `sisaket-roster` รวมและแทนที่ไฟล์เดิม (ดูชื่อโฟลเดอร์ใน ZIP)
3. ตรวจว่า `C:\xampp\htdocs\sisaket-roster\app\Services\WorkflowPolicy.php` มีอยู่
4. **ห้ามลบ** `config.php` และ **ไม่ต้องรัน** `database/schema.sql` หรือ `seed.sql` อีกครั้ง
5. เปิด Apache + MySQL
6. เปิด `http://localhost/sisaket-roster/public/index.php?c=roster&a=index` และกด `Ctrl+F5`

## ทดสอบโดยไม่แตะข้อมูลจริง
```powershell
cd C:\xampp\htdocs\sisaket-roster
C:\xampp\php\php.exe tests\workflow_v4_test.php
```
ควรเห็น `V4 workflow/readiness tests passed: 14 assertions`

## Preflight ฐานข้อมูล (อ่านอย่างเดียว)
```powershell
C:\xampp\php\php.exe bin\roster_preflight.php SSK-DEMO-001 2026 10
```
ใช้ปี **ค.ศ. 2026** ไม่ใช่ พ.ศ. 2569; เปลี่ยนรหัสหน่วยบริการตามที่เพิ่มไว้จริง
Exit code 0 = ผ่านเกณฑ์ขั้นต้น, 1 = ยังมีเงื่อนไขไม่ครบ, 2 = ใช้คำสั่งผิด/เชื่อมต่อไม่ได้

## แนะนำทดสอบ Workflow กับข้อมูลทดสอบ ไม่ใช่ตารางเวรจริง
1. นำเข้าบุคลากรสำหรับทดสอบอย่างน้อย 6–10 คนตามเกณฑ์อัตรากำลังแต่ละหน่วย
2. SUPERADMIN สร้าง Draft เดือนทดสอบ (ระวังห้ามสร้างในเดือนที่หน่วยใช้งานจริง)
3. จัดเวรและตรวจสอบการกระจายเวร; วงจรแบบสมบูรณ์ต้องไม่มี Critical
4. SCHEDULER ส่งอนุมัติ → DIRECTOR อนุมัติ → ADMIN/DIRECTOR เผยแพร่ → ล็อก
5. หลังเผยแพร่ให้ทดสอบ STAFF ดูตารางได้ (STAFF ไม่สามารถแก้ไข)
6. ทดสอบสร้าง Version ใหม่ พร้อมเหตุผล แล้วตรวจ Audit Log

## หมายเหตุ
- การตรวจจำนวนบุคลากรไม่ใช่การรับรองความเหมาะสมทางคลินิก ต้องให้ผู้รับผิดชอบตรวจเวรจริง
- V4 ไม่เพิ่มตารางหรือคอลัมน์ฐานข้อมูล จึงไม่มี SQL Migration
- เปลี่ยนกฎ **ส่งอนุมัติ** ให้ต้องไม่มี Critical (เดิมตรวจ Critical เมื่ออนุมัติ/เผยแพร่)
- หากได้ 500 Internal Server Error ให้ตรวจ `C:\xampp\apache\logs\error.log` และ `C:\xampp\php\logs\php_error_log` (ถ้ามี)
