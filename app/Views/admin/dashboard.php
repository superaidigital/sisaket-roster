<?php $pageTitle='ศูนย์จัดการข้อมูลตารางเวร'; $active='dashboard';require __DIR__.'/_head.php'; ?>
<div class="row g-3 mb-4">
  <div class="col-md-4"><div class="admin-stat"><i class="bi bi-hospital stat-icon"></i><div><span>หน่วยบริการที่เปิดใช้งาน</span><strong><?=count(array_filter($hospitals,static fn($h)=>(int)$h['is_active']===1))?></strong><small>รพ.สต.</small></div></div></div>
  <div class="col-md-4"><div class="admin-stat"><i class="bi bi-people stat-icon"></i><div><span>บุคลากรที่จัดเวรได้</span><strong><?=array_sum(array_map(static fn($r)=>(int)$r['staff_count'],$counts))?></strong><small>คน (ไม่รวมผู้ดูแลระบบและ HR)</small></div></div></div>
  <div class="col-md-4"><div class="admin-stat"><i class="bi bi-calendar-check stat-icon"></i><div><span>หน่วยบริการที่มีตารางเวร</span><strong><?=count(array_filter($hospitals,static fn($h)=>(int)$h['roster_count']>0))?></strong><small>หน่วย</small></div></div></div>
</div>
<div class="row g-3">
  <div class="col-lg-6"><section class="admin-card h-100"><div class="admin-section-title"><i class="bi bi-person-plus"></i><h2>จัดการบุคลากร</h2></div>
    <p class="text-secondary">เพิ่มบุคลากร กำหนดหน่วยบริการ ตำแหน่ง ประเภทบุคลากร และสิทธิ์การใช้งาน ตารางเวรจะดึงรายชื่อที่มีสิทธิ์จัดเวรโดยอัตโนมัติ</p>
    <a href="index.php?c=admin&a=staff" class="btn btn-primary"><i class="bi bi-arrow-right-circle"></i> ไปหน้าจัดการบุคลากร</a></section></div>
  <div class="col-lg-6"><section class="admin-card h-100"><div class="admin-section-title"><i class="bi bi-building-add"></i><h2>จัดการหน่วยบริการ รพ.สต.</h2></div>
    <p class="text-secondary">ดูหน่วยบริการทั้งหมด เพิ่มหรือแก้ไขข้อมูลหน่วยบริการ และกำหนดเกณฑ์จัดเวรแยกตาม รพ.สต. (เพิ่ม/แก้ไขหน่วยบริการโดย SUPERADMIN)</p>
    <a href="index.php?c=admin&a=hospitals" class="btn btn-outline-primary"><i class="bi bi-arrow-right-circle"></i> ไปหน้าจัดการหน่วยบริการ</a></section></div>
</div>
<section class="admin-card mt-3"><h2 class="h5 mb-3">สถานะจำนวนบุคลากรแยกหน่วยบริการ</h2>
  <div class="table-responsive"><table class="table align-middle admin-table"><thead><tr><th>หน่วยบริการ</th><th class="text-center">ผู้จัดเวรได้</th><th></th></tr></thead><tbody>
    <?php foreach($counts as $row): ?><tr><td><?=e($row['name'])?></td><td class="text-center fw-bold"><?=(int)$row['staff_count']?></td><td class="text-end"><a href="index.php?c=admin&amp;a=staff&amp;hospital_id=<?=(int)$row['hospital_id']?>" class="btn btn-outline-primary btn-sm">ดูรายชื่อ</a></td></tr><?php endforeach; ?>
    <?php if(!$counts): ?><tr><td colspan="3" class="text-center text-secondary p-4">ยังไม่มีหน่วยบริการที่เปิดใช้งาน</td></tr><?php endif; ?>
  </tbody></table></div>
</section>
<?php require __DIR__.'/_foot.php'; ?>
