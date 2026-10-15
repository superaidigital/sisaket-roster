<?php declare(strict_types=1);use App\Core\Csrf; ?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>การลาอิเล็กทรอนิกส์ | อบจ.ศรีสะเกษ</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head><body class="bg-light"><main class="container py-4">
<div class="d-flex align-items-center justify-content-between gap-2 mb-3"><div><div class="text-primary small fw-bold">SISAKET PAO • E-LEAVE V13</div><h1 class="h3">ระบบคำขอลาอิเล็กทรอนิกส์</h1><p class="text-muted small">ยื่นคำขอและติดตามสถานะ ไม่ใช่เอกสารอนุมัติคำสั่งราชการ</p></div><a href="index.php?c=duty&a=index" class="btn btn-outline-primary">ตารางเวร ย/บ/ร</a></div>
<section class="card shadow-sm mb-3"><div class="card-body">
<h2 class="h5">ยื่นคำขอลา</h2>
<form id="leave-form" class="row g-3">
<div class="col-md-4"><label for="type" class="form-label">ประเภทการลา</label><select id="type" class="form-select" required></select></div>
<div class="col-md-4"><label for="start" class="form-label">เริ่มวันที่/เวลา</label><input id="start" class="form-control" type="datetime-local" step="60" required></div>
<div class="col-md-4"><label for="end" class="form-label">สิ้นสุดวันที่/เวลา</label><input id="end" class="form-control" type="datetime-local" step="60" required></div>
<div class="col-12"><label for="reason" class="form-label">เหตุผลการลา (ไม่บังคับ)</label><textarea id="reason" class="form-control" rows="3" maxlength="1000" placeholder="โปรดระบุเฉพาะข้อมูลที่จำเป็น"></textarea><div class="form-text">เหตุผลถูกเข้ารหัสในฐานข้อมูลเมื่อกำหนดคีย์อย่างถูกต้อง และไม่รวมใน Audit Log</div></div>
<div class="col-12"><button id="submit" class="btn btn-primary" type="submit">ส่งคำขอลา</button></div>
</form></div></section>
<div id="message" class="alert alert-info" role="status" aria-live="polite">กำลังโหลด...</div>
<section class="card shadow-sm"><div class="card-body"><h2 class="h5">ประวัติคำขอลา</h2><div class="table-responsive"><table class="table table-sm align-middle">
<thead><tr><th>เลขที่</th><th>ผู้ขอ</th><th>ประเภท</th><th>ตั้งแต่</th><th>ถึง</th><th>สถานะ</th><th>ดำเนินการ</th></tr></thead><tbody id="requests"></tbody>
</table></div></div></section>
<p class="text-muted small mt-3">กรณีอนุมัติวันลาที่ชนกับเวรซึ่งอนุมัติหรือเผยแพร่แล้ว ระบบจะหยุดการอนุมัติและแจ้งให้ตรวจเวรก่อน</p>
</main><script>window.LEAVE_CSRF=<?=json_encode(Csrf::token(),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;</script><script src="js/leave-v13.js" defer></script></body></html>