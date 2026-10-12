Sisaket Roster V8 — Shift Rest Safety PATCH (อัปเกรดจาก V7)

1) สำรองฐานข้อมูล MySQL และโฟลเดอร์ sisaket-roster เดิม
2) รัน database/migrations/20261012_v8_min_rest_hours.sql ใน phpMyAdmin ก่อน
3) แตกไฟล์ ZIP PATCH ลง C:\xampp\htdocs\ แล้วรวมกับโฟลเดอร์ sisaket-roster เดิม
4) ห้ามลบ config.php และห้ามนำเข้า schema.sql หรือ seed.sql ใหม่
5) เปิด Apache/MySQL, กด Ctrl+F5; ตั้งค่า min_rest_hours (ค่าเริ่มต้น 11 ชั่วโมง)
6) ตรวจ Critical ใหม่บน Draft; อย่าแก้ Published/Locked ฉบับเดิมโดยตรง
7) ทดสอบหน้าเวรของฉัน 375px/430px และสิทธิ์ API ในสภาพแวดล้อมทดสอบ

อ่านคู่มือทั้งหมด: UPGRADE_V8.md
