<?php
declare(strict_types=1);
use App\Core\Csrf;
?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ตารางเวรปฏิบัติงาน ย/บ/ร | อบจ.ศรีสะเกษ</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="css/duty-roster-v13.css"></head>
<body><main class="duty-page" id="duty-page" data-view="table"><div class="duty-container">
<header class="duty-header">
<div><span class="duty-eyebrow">SISAKET PAO · DUTY MANAGEMENT</span><h1>ตารางเวรปฏิบัติงาน ย / บ / ร</h1>
<p class="duty-subtitle">ระบบทดลองจัดเวรนอกเวลาราชการ — บันทึกเฉพาะ Draft ไม่ใช่คำสั่งอนุมัติหรือหลักฐานเบิกค่าตอบแทน</p></div>
<div class="duty-header-actions"><a class="btn btn-outline-primary btn-sm" href="index.php?c=roster&a=index">← กลับตารางเวร V11</a><a class="btn btn-outline-success btn-sm" href="index.php?c=leave&a=index">แบบฟอร์มวันลา</a><a class="btn btn-outline-primary btn-sm" href="index.php?c=swap&a=index">คำขอเปลี่ยนเวร</a></div>
</header>
<section class="surface duty-selectors" aria-label="เลือกหน่วยบริการและเดือน">
<div class="duty-form-grid">
<div><label for="hospital">หน่วยบริการ</label><select id="hospital" class="form-select">
<?php if($hospitals):foreach($hospitals as $h): ?>
<option value="<?=(int)$h['id']?>"><?=e($h['name'])?></option>
<?php endforeach;else: ?><option value="<?=(int)$user['hospital_id']?>">หน่วยบริการของฉัน</option><?php endif; ?>
</select></div>
<div><label for="month">เดือน</label><select id="month" class="form-select">
<?php $months=['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];for($m=1;$m<=12;$m++):?><option value="<?=$m?>" <?=$m===(int)date('n')?'selected':''?>><?=$months[$m-1]?></option><?php endfor; ?></select></div>
<div><label for="year">ปี พ.ศ.</label><select id="year" class="form-select">
<?php for($y=(int)date('Y')-2;$y<=(int)date('Y')+3;$y++):?><option value="<?=$y?>" <?=$y===(int)date('Y')?'selected':''?>><?=$y+543?></option><?php endfor; ?></select></div>
<div class="duty-period-actions"><button class="btn btn-outline-secondary" id="previous" type="button" title="เดือนก่อน">←</button><button class="btn btn-outline-secondary" id="today" type="button">เดือนนี้</button><button class="btn btn-outline-secondary" id="next" type="button" title="เดือนถัดไป">→</button><button class="btn btn-primary" id="refresh" type="button">แสดงตาราง</button></div>
</div></section>
<div class="duty-workflow">สถานะการทำงาน: เลือกหน่วยบริการและเดือน → สร้าง Draft → คลิกเวร ย/บ/ร เพื่อบันทึกทีละรายการ → ตรวจข้อมูลก่อนนำเสนออนุมัติ</div>
<section class="duty-stats" aria-label="สถิติตารางเวร">
<div class="surface duty-stat" data-type="people"><div class="duty-stat-icon">คน</div><div><div class="label">บุคลากร</div><strong id="stat-people">0</strong></div></div>
<div class="surface duty-stat"><div class="duty-stat-icon">∑</div><div><div class="label">รายการเวรทั้งหมด</div><strong id="stat-all">0</strong></div></div>
<div class="surface duty-stat" data-type="ย"><div class="duty-stat-icon">ย</div><div><div class="label">เวรวันหยุด</div><strong id="stat-y">0</strong></div></div>
<div class="surface duty-stat" data-type="บ"><div class="duty-stat-icon">บ</div><div><div class="label">เวรบ่าย</div><strong id="stat-b">0</strong></div></div>
<div class="surface duty-stat" data-type="ร"><div class="duty-stat-icon">ร</div><div><div class="label">เวร On Call</div><strong id="stat-r">0</strong></div></div>
<div class="surface duty-stat"><div class="duty-stat-icon">#</div><div><div class="label">Revision</div><strong id="stat-revision">—</strong></div></div>
</section>
<div class="duty-alert" id="msg" data-kind="loading" role="status" aria-live="polite">กำลังโหลดข้อมูล...</div>
<section class="surface" aria-labelledby="heading">
<div class="duty-toolbar"><div class="duty-toolbar-title"><h2 id="heading">ตารางเวร</h2><span class="duty-badge" id="status-badge">ยังไม่มี Draft</span><small id="meta"></small></div>
<div class="duty-toolbar-actions"><button class="btn btn-sm btn-outline-secondary" id="table-mode" type="button" aria-pressed="true">▦ ตาราง</button><button class="btn btn-sm btn-outline-secondary" id="calendar-mode" type="button" aria-pressed="false">▣ รายบุคคล</button><button class="btn btn-sm btn-outline-secondary" id="fullscreen" type="button">⛶ เต็มจอ</button><button class="btn btn-sm btn-outline-secondary" id="review-approve" type="button" hidden>เห็นชอบ</button><button class="btn btn-sm btn-outline-danger" id="review-reject" type="button" hidden>ส่งแก้ไข</button><button class="btn btn-sm btn-outline-success" id="review-publish" type="button" hidden>เผยแพร่</button><button class="btn btn-sm btn-outline-secondary" id="review-lock" type="button" hidden>ล็อกตาราง</button><button class="btn btn-sm btn-outline-primary" id="validate" type="button" hidden>ตรวจสอบตาราง</button><button class="btn btn-sm btn-success" id="submit" type="button" hidden>ส่งตรวจ</button><button class="btn btn-sm btn-primary" id="create" type="button" hidden>+ สร้าง Draft</button></div></div>
<div class="duty-filterbar">
<div class="duty-filter"><label for="search">ค้นหาบุคลากร</label><input id="search" class="form-control" type="search" placeholder="ชื่อ / ตำแหน่ง"></div>
<div class="duty-filter small"><label for="shift-filter">กรองเวร</label><select id="shift-filter" class="form-select"><option value="">เวรทั้งหมด</option><option value="ย">เวร ย</option><option value="บ">เวร บ</option><option value="ร">เวร ร</option></select></div>
<div class="duty-legend"><span class="duty-pill" data-type="ย">ย · 08:30–16:30</span><span class="duty-pill" data-type="บ">บ · 16:31–20:30</span><span class="duty-pill" data-type="ร">ร · 20:31–08:29 (+1 วัน)</span></div>
</div>
<div id="validation-panel" class="p-3 border-bottom" hidden><h3 class="h6">ผลตรวจสอบตารางเวร</h3><p id="validation-summary" class="small mb-2"></p><div id="validation-issues" class="table-responsive"></div><p class="text-secondary small">ผ่านการตรวจด้วยระบบไม่ได้หมายถึงได้รับอนุมัติ ต้องตรวจทักษะและอัตรากำลังโดยผู้มีอำนาจ</p></div><div class="duty-scroll" id="table-scroll" tabindex="0" aria-label="เลื่อนดูตารางเวรแนวนอน"><table id="grid" class="duty-table"><caption class="visually-hidden">ตารางเวรรายเดือน บุคลากรตามแถว วันที่ตามคอลัมน์</caption></table></div>
<div id="calendar" class="duty-calendar">
<div class="duty-calendar-toolbar"><div class="duty-filter"><label for="calendar-person">เลือกบุคลากร</label><select id="calendar-person" class="form-select"></select></div><div><strong id="calendar-summary">—</strong></div></div>
<div class="duty-week-grid" id="calendar-grid"></div>
</div>
<footer class="duty-footer"><span id="footer-count">—</span><span>การบันทึกแต่ละครั้งตรวจสิทธิ์ เวรซ้อน วันลา วันหยุด และ Revision ที่ฝั่งเซิร์ฟเวอร์</span></footer>
</section><p class="duty-note">เวร ย ใช้เฉพาะวันหยุดที่เข้าหลักเกณฑ์ • ยอดนับจากรายการเวรที่บันทึกแล้ว ไม่รวมเงินค่าตอบแทน • ร่างนี้ไม่ได้แก้ไขตาราง V11</p>
</div></main>
<script>window.DUTY_USER_ROLE=<?=json_encode((string)$user['role'],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;window.DUTY_CSRF=<?=json_encode(Csrf::token(),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;</script>
<script src="js/duty-roster-v13.js" defer></script></body></html>