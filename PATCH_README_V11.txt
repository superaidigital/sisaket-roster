Sisaket Roster V11 — Security PATCH for V10
1) สำรองฐานข้อมูลและโฟลเดอร์เดิม
2) แตก ZIP ลง C:\xampp\htdocs\ (รวม/แทนไฟล์ใน sisaket-roster)
3) ไม่ต้องรัน SQL Migration, อย่านำเข้า schema.sql/seed.sql ใหม่
4) ทดสอบ php tests\security_v11_test.php
5) อ่าน UPGRADE_V11.md ก่อนใช้งานจริง
