<?php $pageTitle='นำเข้าบุคลากรสำเร็จ';$active='staff_import';require __DIR__.'/_head.php'; ?>
<section class="admin-card">
  <div class="alert alert-success" role="status"><h2 class="h5 mb-1"><i class="bi bi-check-circle"></i> นำเข้าบุคลากรเรียบร้อยแล้ว</h2>
    <div>สร้างบัญชีใหม่ <strong><?=(int)$result['created']?></strong> บัญชี · ข้ามอีเมลซ้ำ <strong><?=(int)$result['skipped']?></strong> บัญชี</div></div>
  <div class="alert alert-warning" role="alert"><i class="bi bi-key"></i> <strong>รหัสผ่านชั่วคราวแสดงเพียงครั้งเดียว</strong> เมื่อออกจากหน้านี้จะไม่สามารถเปิดดูรหัสชุดนี้อีก โปรดส่งให้เจ้าของบัญชีแต่ละคนอย่างปลอดภัย ห้ามแชร์ภาพหน้าจอทั้งตาราง</div>
  <div class="table-responsive import-table-wrap"><table class="table admin-table align-middle"><thead><tr><th>ชื่อ</th><th>อีเมล</th><th>รหัสผ่านชั่วคราว</th></tr></thead><tbody>
    <?php foreach($result['credentials'] as $i=>$r): ?><tr><td><?=e($r['full_name'])?></td><td><?=e($r['email'])?></td>
      <td><div class="input-group input-group-sm import-password"><input id="secret-<?=(int)$i?>" type="password" class="form-control font-monospace" value="<?=e($r['temporary_password'])?>" readonly aria-label="รหัสผ่านชั่วคราวของ <?=e($r['full_name'])?>">
        <button class="btn btn-outline-secondary" type="button" data-reveal-target="secret-<?=(int)$i?>" aria-label="แสดงหรือซ่อนรหัสผ่าน"><i class="bi bi-eye"></i></button>
        <button class="btn btn-outline-secondary" type="button" data-copy-target="secret-<?=(int)$i?>">คัดลอก</button></div></td></tr><?php endforeach; ?></tbody></table></div>
  <div class="d-flex flex-wrap gap-2 mt-3"><a href="index.php?c=admin&amp;a=staff" class="btn btn-primary"><i class="bi bi-people"></i> ไปจัดการบุคลากร</a>
    <a href="index.php?c=roster&amp;a=index" class="btn btn-outline-primary"><i class="bi bi-calendar3"></i> กลับตารางเวร</a></div>
</section>
<?php require __DIR__.'/_foot.php'; ?>
