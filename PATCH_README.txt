Sisaket Roster v2 - PATCH: จัดการบุคลากร + จัดการหน่วยบริการ
================================================================

สำหรับอัปเกรดจาก Sisaket_Roster_Management_XAMPP_ADMIN_1234_SETUP.zip

ก่อนติดตั้ง: BACKUP ฐานข้อมูล sisaket_roster และ C:\xampp\htdocs\sisaket-roster\config.php

วิธีใช้:
1. ปิด Apache ชั่วคราว
2. แตกไฟล์ PATCH นี้ลง C:\xampp\htdocs\ โดยให้ Windows รวมไฟล์กับ
   C:\xampp\htdocs\sisaket-roster\ (Merge / Replace เมื่อถามเฉพาะไฟล์ที่แก้ไข)
3. ห้ามลบฐานข้อมูลหรือ config.php เดิม
4. เปิด Apache/MySQL แล้วเข้าระบบด้วยบัญชี SUPERADMIN เดิม
5. คลิกปุ่ม "จัดการข้อมูล" ที่แถบด้านบน

ไม่ต้องนำเข้า schema.sql หรือ seed.sql ใหม่
ไม่ต้องรัน setup_admin_local.bat ใหม่

อ่าน UPGRADE_V2.md สำหรับขั้นตอนรายละเอียดและวิธีตรวจสอบ
