<?php declare(strict_types=1);use App\Core\Csrf;?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ตั้งค่าเกณฑ์เวร ย/บ/ร | อบจ.ศรีสะเกษ</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"></head>
<body class="bg-light"><main class="container py-4">
<header class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3"><div>
<div class="text-primary small fw-bold">SISAKET PAO • DUTY COVERAGE</div><h1 class="h3">เกณฑ์จำนวนบุคลากรและทักษะประจำเวร</h1>
<p class="text-muted small">ต้องอ้างอิงคำสั่งหรือเกณฑ์ที่หน่วยงานตรวจสอบแล้ว ห้ามใช้การคาดเดาจากชื่อตำแหน่ง</p>
</div><a class="btn btn-outline-primary" href="index.php?c=duty&a=index">กลับตารางเวร</a></header>
<div class="alert alert-warning">การบันทึกที่นี่มีผลต่อการตรวจสอบ Draft ใหม่ โปรดทบทวน Version ที่ส่งตรวจหรือเผยแพร่แล้วทุกครั้งหลังเปลี่ยนเกณฑ์</div>
<div class="card shadow-sm mb-3"><div class="card-body">
<label class="form-label" for="hospital">หน่วยบริการ</label>
<select id="hospital" class="form-select">
<?php foreach($hospitals as $h):?><option value="<?=(int)$h['id']?>"><?=e($h['name'])?></option><?php endforeach;?>
</select>
</div></div>
<div id="message" class="alert alert-info" role="status" aria-live="polite">กำลังโหลด...</div>
<form id="coverage" class="card shadow-sm" hidden><div class="card-body">
<div id="policies"></div><button id="save" class="btn btn-primary" type="submit">ยืนยันบันทึกเกณฑ์ทุกเวร</button></div></form>
<p class="text-muted small mt-3">ทักษะบุคลากรแต่ละคนตรวจยืนยันในเมนู “ทักษะเวร” ของ V11 ซึ่งใช้หลักฐานทักษะร่วมกัน</p>
</main>
<script>window.COVERAGE_CSRF=<?=json_encode(Csrf::token(),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;</script>
<script src="js/duty-coverage-v13.js" defer></script></body></html>