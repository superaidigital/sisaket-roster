<?php
declare(strict_types=1);
require __DIR__.'/_head.php';
$matrix=[];
foreach($requirements as $r)$matrix[(int)$r['shift_type_id']][(string)$r['skill_code']]=(int)$r['min_count'];
?>
<section class="admin-card mb-3">
 <h2 class="h5">1. เลือกหน่วยบริการ</h2>
 <form method="get" action="index.php" class="d-flex gap-2 flex-wrap align-items-end">
  <input type="hidden" name="c" value="admin"><input type="hidden" name="a" value="skills">
  <div class="flex-grow-1"><label for="hospital_id" class="form-label">รพ.สต.</label>
  <select class="form-select" name="hospital_id" id="hospital_id">
  <?php foreach($hospitals as $h): ?><option value="<?=(int)$h['id']?>" <?=(int)$h['id']===$hospitalId?'selected':''?>><?=e($h['name'])?></option><?php endforeach; ?>
  </select></div><button class="btn btn-outline-primary">แสดงข้อมูล</button>
 </form>
</section>
<form method="post" action="index.php?c=admin&amp;a=skills_save">
 <input type="hidden" name="csrf_token" value="<?=e(\App\Core\Csrf::token())?>">
 <input type="hidden" name="hospital_id" value="<?=$hospitalId?>">
 <section class="admin-card mb-3">
  <h2 class="h5">2. ยืนยันคุณสมบัติของบุคลากรสำหรับจัดเวร</h2>
  <div class="alert alert-warning">การติ๊กเลือกหมายถึงผู้ดูแลได้ตรวจสอบเอกสาร/คุณสมบัติจริงแล้ว <strong>ระบบไม่ได้เชื่อมทะเบียนใบอนุญาตประกอบวิชาชีพ</strong> และไม่อนุมานจากชื่อตำแหน่ง กรุณายืนยันหลักฐานกับหน่วยบริการก่อนบันทึก</div>
  <div class="table-responsive"><table class="table table-bordered align-middle table-sm">
   <thead><tr><th scope="col">บุคลากร</th><?php foreach($catalog as $code=>$name): ?><th scope="col"><abbr title="<?=e($name)?>"><?=e($code)?></abbr><div class="small fw-normal"><?=e($name)?></div></th><?php endforeach; ?></tr></thead>
   <tbody><?php foreach($staff as $s): ?><tr>
    <th scope="row"><span class="fw-semibold"><?=e($s['full_name'])?></span><div class="small text-secondary"><?=e($s['position'])?> · <?=e($s['role'])?></div></th>
    <?php foreach($catalog as $code=>$name): ?><td class="text-center"><input class="form-check-input" type="checkbox" value="1" name="staff_skills[<?=(int)$s['id']?>][<?=e($code)?>]" aria-label="<?=e($s['full_name'].' – '.$name)?>" <?=!empty($assignments[(int)$s['id']][$code])?'checked':''?>></td><?php endforeach; ?>
   </tr><?php endforeach; ?>
   <?php if(!$staff): ?><tr><td colspan="<?=count($catalog)+1?>" class="text-center text-secondary">ยังไม่มีบุคลากรในหน่วยบริการนี้</td></tr><?php endif; ?></tbody>
  </table></div>
 </section>
 <section class="admin-card mb-3">
  <h2 class="h5">3. กำหนดขั้นต่ำผู้มีทักษะในแต่ละเวร</h2>
  <p class="text-secondary">ตั้งค่า 0 หมายถึงไม่บังคับทักษะประเภทนั้น ค่าที่มากกว่า 0 จะถูกตรวจเป็น Critical เมื่อจำนวนบุคลากรที่ได้รับการยืนยันไม่ครบ โดยนับคนที่ได้รับการยืนยันเท่านั้น</p>
  <div class="table-responsive"><table class="table table-bordered align-middle table-sm">
   <thead><tr><th scope="col">ประเภทเวร</th><?php foreach($catalog as $code=>$name): ?><th scope="col"><abbr title="<?=e($name)?>"><?=e($code)?></abbr></th><?php endforeach; ?></tr></thead>
   <tbody><?php foreach($shifts as $shift): ?><tr><th scope="row"><?=e($shift['name'])?></th>
    <?php foreach($catalog as $code=>$name): ?><td><input type="number" class="form-control form-control-sm" min="0" max="100" step="1" inputmode="numeric" name="requirements[<?=(int)$shift['id']?>][<?=e($code)?>]" value="<?=(int)($matrix[(int)$shift['id']][$code]??0)?>" aria-label="<?=e($shift['name'].' ขั้นต่ำ '.$name)?>"></td><?php endforeach; ?>
   </tr><?php endforeach; ?></tbody>
  </table></div>
  <div class="d-flex justify-content-end"><button class="btn btn-primary"><i class="bi bi-shield-check"></i> บันทึกทักษะและเกณฑ์</button></div>
 </section>
</form>
<p class="small text-secondary">คำแนะนำ: เริ่มจากยืนยันทักษะของบุคลากรให้ครบก่อนกำหนดขั้นต่ำ ระบบจะเก็บ Audit Log การเปลี่ยนแปลง และจะตรวจ Draft เดิมอีกครั้งก่อนส่งอนุมัติ</p>
<?php require __DIR__.'/_foot.php'; ?>
