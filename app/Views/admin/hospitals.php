<?php $pageTitle='จัดการหน่วยบริการ รพ.สต.'; $active='hospitals';require __DIR__.'/_head.php'; ?>
<section class="admin-card"><div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
  <p class="text-secondary mb-0">ข้อมูลหน่วยบริการทั้งหมด รวมหน่วยที่ปิดใช้งาน (เก็บประวัติไว้)</p>
  <?php if($user['role']==='SUPERADMIN'): ?><a href="index.php?c=admin&a=hospital_form" class="btn btn-primary"><i class="bi bi-plus-lg"></i> เพิ่ม รพ.สต.</a><?php endif; ?>
</div>
<div class="table-responsive"><table class="table admin-table align-middle"><thead><tr><th>รหัส</th><th>หน่วยบริการ</th><th>อำเภอ/ตำบล</th><th class="text-center">บัญชีใช้งาน</th><th class="text-center">ตารางเวร</th><th>สถานะ</th><th class="text-end">ดำเนินการ</th></tr></thead><tbody>
<?php foreach($hospitals as $h): ?><tr>
<td class="font-monospace text-nowrap"><?=e($h['hospital_code'])?></td><td class="fw-semibold"><?=e($h['name'])?></td><td><?=e($h['district'])?> / <?=e($h['subdistrict'])?></td>
<td class="text-center"><?=(int)$h['active_users']?></td><td class="text-center"><?=(int)$h['roster_count']?></td>
<td><span class="badge text-bg-<?=(int)$h['is_active']?'success':'secondary'?>"><?=(int)$h['is_active']?'เปิดใช้งาน':'ปิดใช้งาน'?></span></td>
<td class="text-end text-nowrap"><?php if($user['role']==='SUPERADMIN'): ?><a href="index.php?c=admin&amp;a=hospital_form&amp;id=<?=(int)$h['id']?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i> แก้ไข</a><?php endif; ?> <a href="index.php?c=admin&amp;a=staff&amp;hospital_id=<?=(int)$h['id']?>" class="btn btn-outline-primary btn-sm">บุคลากร</a></td>
</tr><?php endforeach; ?><?php if(!$hospitals): ?><tr><td colspan="7" class="text-center text-secondary p-4">ไม่มีข้อมูลหน่วยบริการ</td></tr><?php endif; ?>
</tbody></table></div></section>
<p class="small text-secondary mt-3"><i class="bi bi-shield-lock"></i> เพื่อรักษาข้อมูลย้อนหลัง ระบบไม่รองรับการลบหน่วยบริการถาวร และไม่อนุญาตให้ปิดหน่วยที่ยังมีบัญชีผู้ใช้ที่เปิดใช้งาน</p>
<?php require __DIR__.'/_foot.php'; ?>
