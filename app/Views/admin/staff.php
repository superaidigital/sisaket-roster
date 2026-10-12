<?php $pageTitle='จัดการบุคลากรสำหรับจัดเวร';$active='staff';require __DIR__.'/_head.php'; ?>
<?php if($tempPassword): ?><section class="alert alert-warning temp-secret" role="status">
<div class="fw-bold mb-1"><i class="bi bi-key"></i> รหัสผ่านชั่วคราว (แสดงเพียงครั้งเดียว)</div>
<p class="small mb-2">ส่งให้เจ้าของบัญชีผ่านช่องทางที่ปลอดภัย ระบบจะบังคับเปลี่ยนรหัสผ่านครั้งแรก ห้ามใช้รหัสผ่านเดียวกันทุกบัญชี</p>
<div class="input-group"><input type="text" class="form-control font-monospace" value="<?=e($tempPassword)?>" readonly aria-label="รหัสผ่านชั่วคราว" id="tempPassword"><button type="button" class="btn btn-outline-dark" data-copy-target="tempPassword"><i class="bi bi-clipboard"></i> คัดลอก</button></div>
</section><?php endif; ?>
<section class="admin-card"><div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
  <p class="text-secondary mb-0">ค้นหาหรือเพิ่มบุคลากรในหน่วยบริการ เพื่อให้ปรากฏใน Staff Pool ของตารางเวร</p>
  <div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-primary" href="index.php?c=admin&amp;a=staff_import"><i class="bi bi-file-earmark-arrow-up"></i> นำเข้าจาก Excel/CSV</a>
  <a class="btn btn-primary" href="index.php?c=admin&a=staff_form"><i class="bi bi-person-plus"></i> เพิ่มบุคลากร</a></div>
</div>
<form method="get" action="index.php" class="row g-2 align-items-end mb-3">
<input type="hidden" name="c" value="admin"><input type="hidden" name="a" value="staff">
<div class="col-lg-5"><label for="q" class="form-label">ค้นหา</label><input id="q" name="q" class="form-control" maxlength="100" value="<?=e($search)?>" placeholder="ชื่อ อีเมล หรือตำแหน่ง"></div>
<div class="col-lg-5"><label for="hospital" class="form-label">หน่วยบริการ</label><select id="hospital" name="hospital_id" class="form-select"><option value="">ทุกหน่วยบริการ</option><?php foreach($hospitals as $h): ?><option value="<?=(int)$h['id']?>" <?=(int)$h['id']===$hospitalId?'selected':''?>><?=e($h['name'])?><?=!(int)$h['is_active']?' (ปิดใช้งาน)':''?></option><?php endforeach; ?></select></div>
<div class="col-lg-2 d-flex gap-2"><button type="submit" class="btn btn-outline-primary flex-fill"><i class="bi bi-search"></i> ค้นหา</button></div></form>
<div class="table-responsive"><table class="table admin-table align-middle"><thead><tr><th>ชื่อ-นามสกุล</th><th>ตำแหน่ง/ประเภท</th><th>หน่วยบริการ</th><th>สิทธิ์</th><th>สถานะ</th><th class="text-end">จัดการ</th></tr></thead><tbody>
<?php foreach($staff as $s): ?><tr><td><div class="fw-semibold"><?=e($s['full_name'])?></div><small class="text-secondary"><?=e($s['email'])?></small></td>
<td><div><?=e($s['position'])?></div><small class="text-secondary"><?=e($s['personnel_type'])?></small></td>
<td><?=e($s['hospital_name'])?></td><td><span class="badge text-bg-light border text-dark"><?=e($s['role'])?></span></td>
<td><?php if(!(int)$s['is_active']): ?><span class="badge text-bg-secondary">ปิดบัญชี</span><?php elseif((int)$s['must_change_password']): ?><span class="badge text-bg-warning">รอเปลี่ยนรหัส</span><?php else: ?><span class="badge text-bg-success">ใช้งาน</span><?php endif; ?></td>
<td class="text-end text-nowrap">
<?php if($user['role']==='SUPERADMIN'||in_array($s['role'],['STAFF','SCHEDULER','DIRECTOR'],true)): ?>
<a href="index.php?c=admin&amp;a=staff_form&amp;id=<?=(int)$s['id']?>" class="btn btn-outline-secondary btn-sm" aria-label="แก้ไข <?=e($s['full_name'])?>"><i class="bi bi-pencil"></i> แก้ไข</a>
<form action="index.php?c=admin&a=staff_reset_password" method="post" class="d-inline" data-confirm="ยืนยันรีเซ็ตรหัสผ่านของบุคลากรรายนี้? รหัสผ่านเดิมจะใช้ไม่ได้ทันที">
<input type="hidden" name="csrf_token" value="<?=e(\App\Core\Csrf::token())?>"><input type="hidden" name="id" value="<?=(int)$s['id']?>"><button class="btn btn-outline-warning btn-sm" type="submit"><i class="bi bi-key"></i> รีเซ็ต</button></form>
<?php else: ?><span class="text-secondary small">SUPERADMIN เท่านั้น</span><?php endif; ?></td></tr>
<?php endforeach; ?><?php if(!$staff): ?><tr><td colspan="6" class="text-center text-secondary p-5"><i class="bi bi-people fs-2 d-block mb-2"></i> ไม่พบบุคลากรในเงื่อนไขที่ค้นหา</td></tr><?php endif; ?>
</tbody></table></div><p class="text-secondary small mb-0">แสดงไม่เกิน 500 รายการต่อครั้ง กรุณาระบุหน่วยบริการเพื่อค้นหาเฉพาะพื้นที่</p>
</section><p class="small text-secondary mt-3"><i class="bi bi-info-circle"></i> บทบาท STAFF, SCHEDULER และ DIRECTOR ที่เปิดใช้งานเท่านั้นที่สามารถปรากฏใน Staff Pool ส่วน SUPERADMIN, ADMIN และ HR ถูกตัดออกเสมอ</p>
<?php require __DIR__.'/_foot.php'; ?>
