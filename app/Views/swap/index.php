<?php declare(strict_types=1);use App\Core\Csrf;?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>คำขอเปลี่ยนเวร | อบจ.ศรีสะเกษ</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"></head>
<body class="bg-light"><main class="container py-4">
<header class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"><div><div class="text-primary fw-bold small">SISAKET PAO • SHIFT SWAP V13</div><h1 class="h3">คำขอแลกเปลี่ยนเวร</h1><p class="text-muted small">การขอและตรวจรับยังไม่เปลี่ยนตารางเวรที่ล็อกแล้วหรือยอดค่าตอบแทน</p></div><a class="btn btn-outline-primary" href="index.php?c=duty&a=index">กลับตารางเวร</a></header>
<section class="card mb-3 shadow-sm"><div class="card-body"><h2 class="h5">สร้างคำขอเปลี่ยนเวร</h2>
<form id="swap-form" class="row g-3">
<div class="col-md-6"><label for="assignment" class="form-label">เวรของฉันที่ผ่านการรับรองแล้ว</label><select id="assignment" class="form-select" required></select></div>
<div class="col-md-6"><label for="replacement" class="form-label">ผู้รับเวรแทน</label><select id="replacement" class="form-select" required></select></div>
<div class="col-12"><label for="reason" class="form-label">เหตุผล (โปรดไม่ระบุข้อมูลเกินความจำเป็น)</label><textarea id="reason" class="form-control" rows="2" maxlength="400" required></textarea></div>
<div class="col-12"><button id="send" type="submit" class="btn btn-primary">ส่งให้ผู้รับแทนยืนยัน</button></div></form>
</div></section>
<div id="message" class="alert alert-info" role="status" aria-live="polite">กำลังโหลด...</div>
<section class="card shadow-sm"><div class="card-body"><h2 class="h5">คำขอที่เกี่ยวข้อง</h2>
<div class="table-responsive"><table class="table table-sm align-middle"><thead><tr>
<th>เลขที่</th><th>วันที่เวร</th><th>เวร</th><th>ผู้ขอ</th><th>ผู้รับแทน</th><th>สถานะ</th><th>การดำเนินการ</th>
</tr></thead><tbody id="requests"></tbody></table></div>
<p class="text-muted small">สถานะ PENDING_AUTHORITY หมายถึงรออำนาจอนุมัติและกระบวนการออกคำสั่งแก้ไข ไม่ใช่เวรที่เปลี่ยนแล้ว</p>
</div></section></main>
<script>window.SWAP_CSRF=<?=json_encode(Csrf::token(),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;</script>
<script src="js/swap-v13.js" defer></script></body></html>