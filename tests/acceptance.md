# Acceptance Test Cases — Roster Module

เริ่มจากทดสอบใน XAMPP แยกจากข้อมูลจริง ใช้ข้อมูลสมมติอย่างน้อย 9–12 บุคลากรต่อหน่วยบริการ และสร้าง 2 รพ.สต. สำหรับทดสอบสิทธิ์

| ID | กรณีทดสอบ | ผลที่คาดหวัง |
|---|---|---|
| T01 | Login ด้วยรหัสผ่านผิด 5 ครั้ง | จำกัดการลอง 10 นาทีต่อ Session |
| T02 | Session ไม่ได้เข้าสู่ระบบ เปิด `data` | ได้ 401 |
| T03 | POST ไม่มี CSRF | ได้ 419 |
| T04 | SCHEDULER เปลี่ยน hospital_id | ได้ 403 |
| T05 | STAFF ขอเปิด DRAFT ด้วย roster_id ตรง | ได้ 403 |
| T06 | STAFF เปิดตารางเดือนที่ยังไม่ Published | ไม่เห็น Draft |
| T07 | ADMIN เลือกอีกหน่วยบริการ | โหลดตารางของหน่วยนั้นได้ |
| T08 | Staff Pool รวม ADMIN / SUPERADMIN / HR | ไม่มี 3 Role นี้ใน Pool |
| T09 | สร้างตารางซ้ำ hospital/month/year | ถูกป้องกันด้วย unique + ข้อความ 409 |
| T10 | เซฟ 1 Cell | บันทึกครั้งเดียว, เพิ่ม lock_version |
| T11 | เซฟ 2 เวรคนเดียววันเดียว | constraint ป้องกันซ้ำ |
| T12 | Bulk save หลาย Cell ที่ถูกต้อง | Commit ทั้งชุด |
| T13 | Bulk save มีวันนอกเดือน | ไม่บันทึกแม้แต่ Cell เดียว |
| T14 | ผู้ใช้ 2 คน save ด้วย revision เดิม | คนหลัง 409; ไม่ทับข้อมูล |
| T15 | บันทึกให้ HR/ADMIN/SUPERADMIN | ได้ 422 |
| T16 | เจ้าหน้าที่ inactive หลังมีเวร | ระบบแจ้ง Critical และล้างเวรเก่าได้ |
| T17 | เปลี่ยนหน่วยบริการบุคลากรหลังจัดเวร | แสดงแถวประวัติ, ห้ามจัดใหม่ |
| T18 | ตั้งเวรดึกต่อเช้าวันถัดไป | CRITICAL NIGHT_MORNING |
| T19 | เวรเช้าคนต่ำกว่าเกณฑ์ | CRITICAL MIN_MORNING |
| T20 | ทำงานต่อเนื่อง 7 วัน เมื่อ max 6 | WARNING |
| T21 | เวรดึก 9 ครั้ง เมื่อ max 8 | WARNING |
| T22 | อัตโนมัติเมื่อบุคลากรไม่พอ | รายงาน unfilled; ไม่ปลอมว่าเวรครบ |
| T23 | Auto scheduling มีเวรเดิม/วันลา | ไม่เขียนทับช่องเดิม |
| T24 | คัดลอก ก.ย. → ต.ค. | Preview ก่อน; แมปวันที่; ไม่ทับช่องเดิม |
| T25 | คัดลอกจาก 31 วันสู่ 30 วัน | นับ invalid_days และไม่สร้างวันที่ 31 |
| T26 | Submit จาก DRAFT | PENDING_APPROVAL |
| T27 | DIRECTOR Approve โดยมี Critical | ปฏิเสธ 422 พร้อม Validation |
| T28 | DIRECTOR Approve เมื่อไม่มี Critical | APPROVED |
| T29 | Publish จาก APPROVED | PUBLISHED; STAFF เห็นตาราง |
| T30 | Edit PUBLISHED ตรง | ปฏิเสธ 409; ต้อง new_revision |
| T31 | New revision จาก Published | Version 2 DRAFT; Version 1 คงเดิม |
| T32 | STAFF ดูระหว่าง Version 2 ยังไม่ Published | ยังเห็น Version 1 Published |
| T33 | Lock PUBLISHED | LOCKED; เปลี่ยนเวรตรงไม่ได้ |
| T34 | Fullscreen > Esc | ออกจากโหมดเต็มจอ |
| T35 | บีบความกว้างน้อยกว่า 1180px | Staff Pool เปลี่ยนเป็น Drawer ไม่บีบตาราง |
| T36 | Mobile width 375px | Day View, เปลี่ยนวัน, แก้เวรได้ตามสิทธิ์ |
| T37 | Keyboard Tab / Enter / Ctrl+C / Ctrl+V | ใช้เครื่องมือจัดเวรด้วยคีย์บอร์ดได้ |
| T38 | Export Print/PDF | A3 แนวนอน พร้อมช่อง 3 ลายเซ็น |
| T39 | Export XLSX | Excel เปิดได้; ภาษาไทยแสดงถูกต้อง |
| T40 | SUPERADMIN ดู Audit Log | เห็นเหตุการณ์ล่าสุดของหน่วยที่เลือก |
| T41 | ADMIN อ่าน Audit Log | ปฏิเสธ 403 |
| T42 | กรอกชื่อบุคลากรเป็น HTML/script | ต้องแสดงเป็นข้อความ ไม่รันสคริปต์ |
| T43 | ปี ค.ศ. 2028 เดือนกุมภาพันธ์ | 29 วัน; แสดง พ.ศ. 2571 |
| T44 | 100 คน × 31 วัน | ตารางเลื่อนแกน x/y, หัวตาราง+คอลัมน์ชื่อ Sticky |
| T45 | เปิด Chrome DevTools ปรับความกว้าง | Responsive ตามพื้นที่จริง |
| T46 | ADMIN เปลี่ยนเกณฑ์ขั้นต่ำเวรของหน่วย | บันทึกแล้ว Validation / Auto ใช้เกณฑ์ใหม่; คนละ รพ.สต. ไม่เปลี่ยนตาม |

**ข้อจำกัดการทดสอบ:** ไฟล์ชุดนี้มี static test และ diagnose command แต่ผล DB integration, Browser E2E, Excel file validation และโหลดพร้อมกันต้องทดสอบกับ XAMPP/MySQL จริงก่อนใช้งานภาคสนาม
