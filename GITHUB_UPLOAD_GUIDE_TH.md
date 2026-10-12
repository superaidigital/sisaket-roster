# อัปโหลด Sisaket Roster V11 ไป GitHub

Repository ที่สร้างแล้ว: `superaidigital/sisaket-roster`

## ขั้นตอน 1 — ตรวจสอบ Repository เปล่า (สร้างเรียบร้อยแล้ว)
1. เข้าสู่ https://github.com/new และใช้บัญชี `superaidigital`
2. Repository name: `sisaket-roster`
3. Visibility: **Public** (ตั้งไว้แล้ว — ห้ามใส่ข้อมูลผู้ใช้จริงและ Secrets)
4. **อย่าติ๊ก** Add a README, Add .gitignore หรือ Choose a license เพื่อให้ Repository ว่างจริง
5. กด Create repository

## ขั้นตอน 2 — Push ซอร์ส V11
1. แตก ZIP ชุด GitHub-ready นี้ไว้เช่น `C:\Projects\sisaket-roster` โดย **อย่า** ใช้สำเนาจาก `htdocs` ที่มี `config.php` จริงหรือข้อมูลผู้ใช้จริง
2. เปิด PowerShell ในโฟลเดอร์ `sisaket-roster` ที่มีไฟล์ README.md
3. รัน `powershell -ExecutionPolicy Bypass -File .\publish-to-github.ps1`
4. ตรวจรายการไฟล์ที่สคริปต์จะ Push และพิมพ์ `PUSH` เพื่อยืนยัน
5. Git อาจให้ล็อกอินผ่าน browser/Git Credential Manager เพื่อยืนยันสิทธิ์ Push
6. เปิด https://github.com/superaidigital/sisaket-roster เพื่อตรวจไฟล์

## หากต้องใช้ Git แบบคัดลอกคำสั่ง
```powershell
git init
git branch -M main
git remote add origin https://github.com/superaidigital/sisaket-roster.git
git add .
git status --short
git diff --cached --name-only
git commit -m "Initial import: Sisaket Roster Management V11"
git push -u origin main
```
ห้าม `git add -f config.php` หรือ `git push --force` เพื่อแก้ปัญหาการ Push

## การอัปเกรดในเครื่อง XAMPP
การ Push เป็นการเก็บ Source Code บน GitHub เท่านั้น **ไม่อัปเดตฐานข้อมูล MySQL หรือเครื่อง XAMPP โดยอัตโนมัติ**
ก่อน Deploy ต้อง Backup DB, ตั้ง config.php เฉพาะเครื่อง และทดสอบการเข้าใช้งานและสิทธิ์อีกครั้ง
