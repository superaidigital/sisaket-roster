<?php $pageTitle='นำเข้าบุคลากรจาก Excel / CSV';$active='staff_import';require __DIR__.'/_head.php'; ?>
<section class="admin-card mb-3">
  <div class="d-flex flex-wrap gap-3 align-items-start justify-content-between">
    <div><h2 class="h5 mb-2"><i class="bi bi-file-earmark-spreadsheet"></i> ขั้นตอน 1 — เตรียมและอัปโหลดรายชื่อ</h2>
      <p class="text-secondary mb-2">นำเข้าบุคลากรสำหรับจัดเวรเฉพาะ รพ.สต. ที่เลือก สูงสุด <strong>200 คนต่อไฟล์</strong> ไม่ต้องแก้ฐานข้อมูลเอง</p>
      <p class="small mb-1">ใช้หัวคอลัมน์ <code>full_name, email, role, position, personnel_type</code></p>
      <p class="small text-secondary mb-0">เลือก Role ได้เฉพาะ STAFF / SCHEDULER / DIRECTOR เท่านั้น · Excel ต้องเป็นข้อความ ไม่ใช้สูตร</p>
    </div>
    <div class="d-flex flex-wrap gap-2"><a href="templates/roster_staff_import_template.xlsx" download class="btn btn-outline-primary"><i class="bi bi-file-earmark-excel"></i> แม่แบบ Excel</a>
      <a href="templates/roster_staff_import_template.csv" download class="btn btn-outline-secondary"><i class="bi bi-filetype-csv"></i> แม่แบบ CSV</a></div>
  </div>
  <form class="row g-3 mt-2" action="index.php?c=admin&amp;a=staff_import_preview" method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?=e(\App\Core\Csrf::token())?>">
    <div class="col-lg-5"><label class="form-label fw-bold" for="hospital_id">หน่วยบริการปลายทาง <span class="text-danger">*</span></label>
      <select id="hospital_id" name="hospital_id" class="form-select" required><option value="">-- เลือก รพ.สต. --</option>
      <?php foreach($hospitals as $h): ?><option value="<?=(int)$h['id']?>" <?=isset($preview['hospital_id']) && (int)$preview['hospital_id']===(int)$h['id']?'selected':''?>><?=e($h['name'])?> (<?=e($h['hospital_code'])?>)</option><?php endforeach; ?></select></div>
    <div class="col-lg-5"><label class="form-label fw-bold" for="staff_file">ไฟล์ Excel .xlsx หรือ CSV UTF-8 <span class="text-danger">*</span></label>
      <input id="staff_file" type="file" class="form-control" name="staff_file" accept=".xlsx,.csv" required aria-describedby="importHelp">
      <div class="form-text" id="importHelp">ขนาดไม่เกิน 1 MB · ใช้ Sheet แรกของแม่แบบ · ไม่รองรับ .xls / .xlsm / มาโคร</div></div>
    <div class="col-lg-2 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i> ตรวจสอบไฟล์</button></div>
  </form>
</section>
<?php if($preview): ?>
<section class="admin-card" aria-label="ตัวอย่างข้อมูลก่อนนำเข้า">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div><h2 class="h5 mb-1"><i class="bi bi-card-checklist"></i> ขั้นตอน 2 — ตรวจสอบก่อนบันทึก</h2>
      <p class="text-secondary small mb-0">หน่วยบริการ: <strong><?=e($preview['hospital_name'])?></strong> · ตัวอย่างหมดอายุภายใน 15 นาที</p></div>
    <div class="d-flex gap-2 flex-wrap"><span class="badge text-bg-success import-stat">พร้อมนำเข้า <?=(int)$preview['counts']['ready']?></span>
      <span class="badge text-bg-secondary import-stat">อีเมลซ้ำ (ข้าม) <?=(int)$preview['counts']['skip']?></span>
      <span class="badge text-bg-danger import-stat">ผิดพลาด <?=(int)$preview['counts']['error']?></span></div>
  </div>
  <div class="table-responsive import-table-wrap"><table class="table table-sm admin-table align-middle">
  <thead><tr><th>แถว</th><th>ชื่อ-นามสกุล</th><th>อีเมล</th><th>สิทธิ์</th><th>ตำแหน่ง</th><th>ประเภทบุคลากร</th><th>ผลตรวจ</th></tr></thead><tbody>
  <?php foreach($preview['rows'] as $r): ?><tr><td><?= (int)$r['line']?></td><td><?=e($r['full_name'])?></td><td><?=e($r['email'])?></td><td><?=e($r['role'])?></td><td><?=e($r['position'])?></td><td><?=e($r['personnel_type'])?></td>
    <td class="small"><?php if($r['status']==='ready'): ?><span class="badge text-bg-success">พร้อมเพิ่ม</span><?php elseif($r['status']==='skip'): ?><span class="badge text-bg-secondary">ข้าม</span><?php else: ?><span class="badge text-bg-danger">ผิดพลาด</span><?php endif; ?>
      <?php if($r['issues']): ?><div class="text-secondary mt-1"><?=e(implode(' • ',$r['issues']))?></div><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
  <div class="alert alert-info mt-3 small"><i class="bi bi-shield-check"></i> ระบบจะ <strong>สร้างเฉพาะบัญชีใหม่</strong> ไม่อัปเดตข้อมูลบัญชีเดิม ไม่ลบรายชื่อ และไม่แก้ตารางเวรย้อนหลัง รหัสผ่านของแต่ละบัญชีจะสุ่มแยกกันและต้องเปลี่ยนครั้งแรก</div>
  <?php if((int)$preview['counts']['error']>0): ?>
    <div class="alert alert-danger" role="alert">กรุณาแก้แถวที่ผิดพลาดใน Excel/CSV และอัปโหลดใหม่ก่อนยืนยัน</div>
  <?php elseif((int)$preview['counts']['ready']===0): ?>
    <div class="alert alert-warning" role="alert">ไม่มีบัญชีใหม่สำหรับนำเข้า อีเมลทั้งหมดอยู่ในระบบแล้ว</div>
  <?php else: ?><form action="index.php?c=admin&amp;a=staff_import_confirm" method="post" data-confirm="ยืนยันสร้างบัญชีใหม่ <?=(int)$preview['counts']['ready']?> บัญชีใน <?=e($preview['hospital_name'])?>?">
    <input type="hidden" name="csrf_token" value="<?=e(\App\Core\Csrf::token())?>"><input type="hidden" name="nonce" value="<?=e($preview['nonce'])?>">
    <button type="submit" class="btn btn-success"><i class="bi bi-check2-circle"></i> ยืนยันนำเข้า <?=(int)$preview['counts']['ready']?> บัญชี</button>
  </form><?php endif; ?>
</section>
<?php endif; ?>
<p class="small text-secondary mt-3"><i class="bi bi-info-circle"></i> หากไฟล์มีข้อมูลส่วนบุคคล กรุณาจำกัดการเข้าถึงไฟล์ต้นทางและส่งรหัสผ่านชั่วคราวให้เจ้าของบัญชีผ่านช่องทางที่ปลอดภัย</p>
<?php require __DIR__.'/_foot.php'; ?>
