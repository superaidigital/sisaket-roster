PATCH V9: Smart Roster Repair (ใช้กับ V8 ที่ติดตั้งอยู่แล้ว)

1. Export ฐานข้อมูลผ่าน phpMyAdmin และสำรองโฟลเดอร์โปรเจกต์เดิม
2. แตก ZIP ลงใน C:\xampp\htdocs\ และรวมไฟล์ sisaket-roster เดิม
3. ห้ามลบ config.php; ไม่ต้องรัน SQL migration/schema/seed ใหม่
4. เปิดหน้า Version 2 — DRAFT, กด Ctrl+F5, เลือกปุ่ม 'ซ่อมตารางอัตโนมัติ'
5. ตรวจ Preview ก่อนยืนยันบันทึก และตรวจ Validation/Audit Log อีกครั้ง

คู่มือฉบับเต็ม: UPGRADE_V9.md
