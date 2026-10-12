USE sisaket_roster;
INSERT INTO hospitals (hospital_code,name,district,subdistrict)
VALUES ('SSK-DEMO-001','รพ.สต. ตัวอย่าง ศรีสะเกษ','เมืองศรีสะเกษ','เมืองเหนือ')
ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO shift_types (code,name,short_name,start_time,end_time,color_class,is_working,sort_order) VALUES
('MORNING','เช้า','ช','08:00:00','16:00:00','shift-morning',1,10),
('AFTERNOON','บ่าย','บ','16:00:00','00:00:00','shift-afternoon',1,20),
('NIGHT','ดึก','ด','00:00:00','08:00:00','shift-night',1,30),
('OFF','วันหยุด','OFF',NULL,NULL,'shift-off',0,40),
('LEAVE','ลา','ลา',NULL,NULL,'shift-leave',0,50)
ON DUPLICATE KEY UPDATE name=VALUES(name),short_name=VALUES(short_name),start_time=VALUES(start_time),end_time=VALUES(end_time),color_class=VALUES(color_class),is_working=VALUES(is_working),sort_order=VALUES(sort_order);
INSERT INTO roster_settings (hospital_id)
SELECT id FROM hospitals WHERE hospital_code='SSK-DEMO-001'
ON DUPLICATE KEY UPDATE hospital_id=VALUES(hospital_id);
