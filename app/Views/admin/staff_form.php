<?php $pageTitle=$record?'แก้ไขบุคลากร':'เพิ่มบุคลากรสำหรับจัดเวร';$active='staff';require __DIR__.'/_head.php'; ?>
<section class="admin-card admin-form-card">
<form method="post" action="index.php?c=admin&a=staff_save" autocomplete="off">
<input type="hidden" name="csrf_token" value="<?=e(\App\Core\Csrf::token())?>">
<?php if($record): ?><input type="hidden" name="id" value="<?=(int)$record['id']?>"><input type="hidden" name="stamp" value="<?=e(\App\Models\AdminModel::userStamp($record))?>"><?php endif; ?>
<div class="row g-3">
<div class="col-lg-6"><label for="fullname" class="form-label">ชื่อ-นามสกุล <span class="text-danger">*</span></label><input id="fullname" name="full_name" maxlength="200" class="form-control" required value="<?=e($record['full_name']??'')?>" placeholder="ชื่อ-นามสกุล"></div>
<div class="col-lg-6"><label for="email" class="form-label">อีเมลเข้าสู่ระบบ <span class="text-danger">*</span></label><input id="email" name="email" type="email" maxlength="190" class="form-control" required value="<?=e($record['email']??'')?>" placeholder="user@example.go.th"></div>
<div class="col-lg-6"><label for="hospital" class="form-label">หน่วยบริการ <span class="text-danger">*</span></label><select id="hospital" name="hospital_id" class="form-select" required><option value="">เลือก รพ.สต.</option><?php foreach($hospitals as $h): ?><option value="<?=(int)$h['id']?>" <?=(int)$h['id']===(int)($record['hospital_id']??0)?'selected':''?>><?=e($h['name'])?><?=!(int)$h['is_active']?' (ปิดใช้งาน)':''?></option><?php endforeach; ?></select></div>
<div class="col-lg-6"><label for="role" class="form-label">สิทธิ์ในระบบ <span class="text-danger">*</span></label><select id="role" name="role" class="form-select" required><?php foreach($roles as $role): ?><option value="<?=e($role)?>" <?=$role===($record['role']??'STAFF')?'selected':''?>><?=e($role)?></option><?php endforeach; ?></select><div class="form-text">ADMIN/HR จะไม่แสดงใน Staff Pool</div></div>
<div class="col-lg-6"><label for="position" class="form-label">ตำแหน่ง</label><input id="position" name="position" maxlength="150" class="form-control" value="<?=e($record['position']??'')?>" placeholder="เช่น พยาบาลวิชาชีพ"></div>
<div class="col-lg-6"><label for="personnel" class="form-label">ประเภทบุคลากร</label><input id="personnel" name="personnel_type" maxlength="100" class="form-control" value="<?=e($record['personnel_type']??'')?>" placeholder="เช่น ข้าราชการ"></div>
<div class="col-12"><label for="photo" class="form-label">URL รูปภาพ (ถ้ามี)</label><input id="photo" type="url" name="photo_url" maxlength="500" class="form-control" value="<?=e($record['photo_url']??'')?>" placeholder="https://..."></div>
</div>
<div class="form-check form-switch mt-4"><input class="form-check-input" type="checkbox" role="switch" name="is_active" id="userActive" value="1" <?=!$record||(int)$record['is_active']?'checked':''?>><label for="userActive" class="form-check-label">เปิดใช้งานบัญชีบุคลากร</label></div>
<div class="alert alert-info small mt-3 mb-0"><i class="bi bi-shield-check"></i>
<?php if(!$record): ?>เมื่อเพิ่มบุคลากร ระบบจะสร้างรหัสผ่านชั่วคราวแบบสุ่มและแสดงเพียงครั้งเดียว บุคลากรต้องตั้งรหัสใหม่ตอนเข้าสู่ระบบครั้งแรก<?php else: ?>การปิดบัญชีหรือย้ายหน่วยจะไม่ลบเวรย้อนหลัง และระบบจะตรวจสอบเวรอนาคตที่ผ่านกระบวนการอนุมัติแล้วก่อนอนุญาตให้เปลี่ยน<?php endif; ?>
</div>
<div class="d-flex gap-2 justify-content-end mt-4"><a class="btn btn-outline-secondary" href="index.php?c=admin&a=staff">ยกเลิก</a><button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> บันทึกข้อมูลบุคลากร</button></div>
</form></section>
<?php require __DIR__.'/_foot.php'; ?>
