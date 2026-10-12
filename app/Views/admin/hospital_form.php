<?php $pageTitle=$record?'แก้ไขหน่วยบริการ':'เพิ่มหน่วยบริการ';$active='hospitals';require __DIR__.'/_head.php'; ?>
<section class="admin-card admin-form-card">
<form method="post" action="index.php?c=admin&a=hospital_save" autocomplete="off">
<input type="hidden" name="csrf_token" value="<?=e(\App\Core\Csrf::token())?>">
<?php if($record): ?><input type="hidden" name="id" value="<?=(int)$record['id']?>"><input type="hidden" name="stamp" value="<?=e(\App\Models\AdminModel::hospitalStamp($record))?>"><?php endif; ?>
<div class="row g-3"><div class="col-lg-4"><label class="form-label" for="code">รหัสหน่วยบริการ <span class="text-danger">*</span></label>
<input type="text" id="code" name="hospital_code" class="form-control" maxlength="30" pattern="[A-Za-z0-9][A-Za-z0-9_-]{2,29}" placeholder="SSK-001" required value="<?=e($record['hospital_code']??'')?>" <?=$record?'readonly':''?>>
<div class="form-text">รหัสต้องไม่ซ้ำ และแก้ไขไม่ได้หลังสร้างแล้ว</div></div>
<div class="col-lg-8"><label class="form-label" for="hname">ชื่อ รพ.สต. <span class="text-danger">*</span></label><input id="hname" name="name" class="form-control" maxlength="200" required value="<?=e($record['name']??'')?>" placeholder="รพ.สต. ..."></div>
<div class="col-md-6"><label class="form-label" for="district">อำเภอ</label><input id="district" name="district" class="form-control" maxlength="120" value="<?=e($record['district']??'')?>"></div>
<div class="col-md-6"><label class="form-label" for="subdistrict">ตำบล</label><input id="subdistrict" name="subdistrict" class="form-control" maxlength="120" value="<?=e($record['subdistrict']??'')?>"></div></div>
<div class="form-check form-switch mt-4"><input class="form-check-input" type="checkbox" role="switch" id="hactive" name="is_active" value="1" <?=!$record||(int)$record['is_active']?'checked':''?>><label class="form-check-label" for="hactive">เปิดใช้งานหน่วยบริการ</label></div>
<div class="alert alert-info small mt-3 mb-0"><i class="bi bi-info-circle"></i> หน่วยบริการใหม่จะได้รับค่าเกณฑ์จัดเวรเริ่มต้นอัตโนมัติ สามารถปรับเกณฑ์ในหน้าตารางเวรได้</div>
<div class="d-flex gap-2 justify-content-end mt-4"><a class="btn btn-outline-secondary" href="index.php?c=admin&a=hospitals">ยกเลิก</a><button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> บันทึกหน่วยบริการ</button></div>
</form></section>
<?php require __DIR__.'/_foot.php'; ?>
