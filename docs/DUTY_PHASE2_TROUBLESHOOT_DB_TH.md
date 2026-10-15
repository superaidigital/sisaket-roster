# แก้ไขหน้า Phase 2 แสดง JSON ไม่สามารถเชื่อมต่อฐานข้อมูล

อาการ: `index.php?c=duty&a=index` แสดง `success:false` และแจ้งไม่สามารถเชื่อมต่อฐานข้อมูลหรือทำรายการได้ หมายถึง PHP จับ `PDOException`; ยังแยกไม่ได้ว่าเป็นรหัสผ่านผิด ไม่มีตาราง หรือ SQL ผิด ต้องตรวจในเครื่อง

## ขั้นตอนปลอดภัย (ไม่แก้ฐานข้อมูล)
1. เปิด XAMPP Control Panel ให้ MySQL เป็น Running
2. เปิด PowerShell แล้วรัน:

```powershell
cd C:\xampp\htdocs\sisaket-duty-phase2
git status
git pull --ff-only origin feature/duty-phase2-draft-ui-20261015
C:\xampp\php\php.exe bin\duty_phase2_preflight.php
```

3. ตรวจ `config.php` ค่า `db.host=127.0.0.1`, พอร์ต MySQL จริง, `db.name` ฐาน DEV, user/password สำหรับฐาน DEV ห้ามเผยรหัสผ่าน
4. ตั้งค่า `app.session_name` ใน `config.php` เป็น `SSK_DUTY_PHASE2_SID` เพื่อไม่ให้ชน cookie กับ V11 บน `localhost` (ชื่อใน config.example.php ใหม่ไม่มีผลกับ config.php ที่คัดลอกไว้แล้ว)
5. หาก `Table duty_types` หรือ `duty_rosters` ไม่พบ ให้สำรองและตรวจฐาน DEV ก่อนติดตั้ง migration Phase 1 อย่านำเข้าทับฐานจริง
6. หาก `DB connect` ผ่าน แต่หน้ายังคงมีข้อผิดพลาด ให้ดู log ใน `C:\xampp\apache\logs\error.log` โดยลบข้อมูลลับก่อนส่งต่อ
7. เปิด `http://localhost/sisaket-duty-phase2/public/index.php?c=auth&a=login` เพื่อเข้าสู่ระบบ Phase 2 ด้วยบัญชีที่มีในฐาน DEV (การแยก cookie ต้องล็อกอินใหม่)

ไม่ให้แก้ `public/index.php` เพื่อพิมพ์ PDO exception ลงหน้าเว็บจริง ไม่ลบ/แก้ database V11 และอย่าทำ `git reset --hard` กับไฟล์ที่ยังไม่ได้ commit
