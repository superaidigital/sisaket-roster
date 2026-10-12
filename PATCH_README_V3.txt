Sisaket Roster Management V3 - STAFF IMPORT PATCH

ติดตั้งทับ V2 ที่ใช้งานได้แล้วเท่านั้น:
1) Backup ฐานข้อมูล และโฟลเดอร์ C:\xampp\htdocs\sisaket-roster
2) ปิด Apache
3) แตก PATCH ลง C:\xampp\htdocs\ (Merge เข้าโฟลเดอร์ sisaket-roster)
4) ห้ามลบ config.php / ห้ามรัน schema.sql หรือ seed.sql ใหม่
5) เปิด Apache/MySQL แล้วไปที่: http://localhost/sisaket-roster/public/index.php?c=admin&a=staff_import

อ่าน UPGRADE_V3.md และใช้แม่แบบ Excel/CSV ใน public/templates
