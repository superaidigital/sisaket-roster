<?php
use App\Services\DutyDocumentPreviewService as Docs;
$roster=$data['roster']??null;
$hospitalName=$type==='swap'?$data['swap']['hospital_name']:$roster['hospital_name'];
$monthName=Docs::monthTitle($year,$month,$type!=='memo');
$title=['annex'=>'บัญชีแนบท้ายคำสั่งปฏิบัติงานนอกเวลาราชการ','memo'=>'บันทึกข้อความขออนุมัติจัดเวร','swap'=>'ใบขอเปลี่ยนเวรปฏิบัติงานนอกเวลาราชการ'][$type];
?><!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=e($title)?> — ตัวอย่าง</title>
<style>
*{box-sizing:border-box}body{font-family:"TH Sarabun New","Sarabun",Tahoma,sans-serif;color:#111;background:#e9eef4;margin:0;font-size:16px}
.tools{position:sticky;top:0;z-index:2;padding:12px 24px;background:#fff;border-bottom:1px solid #cbd5e1;display:flex;justify-content:space-between;gap:12px;align-items:center}
.tools button{background:#155eef;border:0;border-radius:5px;color:white;padding:10px 20px;cursor:pointer}.tools a{color:#184887}
.paper{background:#fff;margin:20px auto;box-shadow:0 3px 20px #9aa7b9;position:relative;padding:24mm 20mm;min-height:260mm;width:210mm}
.paper.landscape{width:420mm;min-height:270mm;padding:15mm 12mm}
.watermark{position:absolute;top:40%;left:10%;right:10%;font-size:55px;text-align:center;transform:rotate(-24deg);color:#cc2222;opacity:.14;font-weight:800;pointer-events:none}
h1{font-size:24px;text-align:center;margin:0 0 12px}h2{font-size:20px}p{line-height:1.65;margin:7px 0}.muted{color:#666}.center{text-align:center}.right{text-align:right}
table{width:100%;border-collapse:collapse;table-layout:fixed}th,td{border:1px solid #555;padding:3px 4px;font-size:12px;vertical-align:middle;overflow-wrap:anywhere}th{background:#f1f5f9}
.grid .person{width:58mm}.grid th.day{width:7.7mm}.grid th.total{width:10mm}.grid td{text-align:center}.grid td.name{text-align:left}
.sign{display:flex;gap:12px;margin-top:22px}.sign>div{flex:1;text-align:center;min-height:80px}
.notice{border:1px solid #d68c2a;background:#fff8e9;padding:10px;margin-bottom:15px;font-size:14px}
.field{border-bottom:1px dotted #555;display:inline-block;min-width:120px}
@page{size:A4 portrait;margin:12mm}@media print{body{background:white;font-size:15px}.tools{display:none}.paper,.paper.landscape{box-shadow:none;margin:0;padding:0;width:auto;min-height:0}.watermark{color:#a22;opacity:.10}th{background:white}thead{display:table-header-group}tr{break-inside:avoid;page-break-inside:avoid}}
@media screen and (max-width:900px){.paper{transform-origin:top left;margin:12px;width:210mm}.tools{flex-wrap:wrap}body{overflow-x:auto}}
</style>
<?php if($type==='annex'): ?><style>@page{size:A3 landscape;margin:10mm}</style><?php endif; ?>
</head><body><div class="tools"><strong><?=e($title)?> — Preview (ยังไม่อนุมัติ)</strong><span><a href="index.php?c=duty&a=index">กลับตารางเวร</a> <button type="button" onclick="window.print()">พิมพ์ / บันทึก PDF</button></span></div>
<main class="paper <?=$type==='annex'?'landscape':''?>"><div class="watermark">ตัวอย่าง — ไม่ใช่คำสั่งอนุมัติ</div>
<?php if($type==='annex'):
$grid=$data['table']; ?>
<h1>บัญชีแนบท้ายคำสั่งจัดเวรปฏิบัติงานนอกเวลาราชการ (ตัวอย่าง)</h1>
<p class="center"><?=e($hospitalName)?> ประจำเดือน <?=e($monthName)?> — Version <?=(int)$roster['version']?> Revision <?=(int)$roster['revision']?></p>
<div class="notice">ช่องค่าตอบแทนยังไม่คำนวณ จนกว่าจะตรวจการปฏิบัติงานจริงและอัตราที่ได้รับอนุมัติ เอกสารนี้ไม่ใช่หลักฐานเบิกจ่าย</div>
<table class="grid"><thead><tr><th class="person" rowspan="2">บุคลากร / ตำแหน่ง</th><th colspan="31">วันปฏิบัติงาน (1–31)</th><th class="total" rowspan="2">ย</th><th class="total" rowspan="2">บ</th><th class="total" rowspan="2">ร</th><th class="total" rowspan="2">รวม</th><th rowspan="2" style="width:20mm">ค่าตอบแทน</th></tr><tr>
<?php for($d=1;$d<=31;$d++):?><th class="day"><?=$d?></th><?php endfor;?></tr></thead><tbody>
<?php foreach($grid['rows'] as $row):?><tr><td class="name"><?=e($row['name'])?><div class="muted"><?=e($row['position'])?></div></td>
<?php foreach($row['cells'] as $cell):?><td><?=e($cell)?></td><?php endforeach;?>
<td><?=$row['totals']['ย']?></td><td><?=$row['totals']['บ']?></td><td><?=$row['totals']['ร']?></td><td><?=$row['total']?></td><td>รอตรวจสอบ</td></tr><?php endforeach;?>
<?php if(!$grid['rows']):?><tr><td colspan="37">ยังไม่มีรายการเวรใน Draft/Version นี้</td></tr><?php endif; ?>
</tbody></table>
<div class="sign"><div>ลงชื่อ..............................................<br>ผู้จัดทำ</div><div>ลงชื่อ..............................................<br>ผู้ตรวจสอบ</div><div>ลงชื่อ..............................................<br>ผู้ควบคุม</div></div>
<?php elseif($type==='memo'): ?>
<h1>บันทึกข้อความ</h1>
<p><strong>ส่วนราชการ</strong> <?=e($hospitalName)?> สังกัดองค์การบริหารส่วนจังหวัดศรีสะเกษ</p>
<p><strong>ที่</strong> <span class="field">&nbsp;</span> <strong>วันที่</strong> <span class="field">&nbsp;</span></p>
<p><strong>เรื่อง</strong> ขออนุมัติจัดเวรปฏิบัติงานนอกเวลาราชการ ประจำเดือน <?=e(Docs::monthTitle($year,$month))?></p><hr>
<p><strong>เรียน</strong> ผู้มีอำนาจอนุมัติตามคำสั่งมอบอำนาจที่ใช้บังคับ</p>
<p>๑. ข้อเท็จจริง หน่วยบริการ <?=e($hospitalName)?> ได้จัดทำร่างตารางปฏิบัติงานนอกเวลาราชการ ประจำเดือน <?=e(Docs::monthTitle($year,$month))?> ตามบัญชีแนบท้ายที่เสนอเพื่อพิจารณา</p>
<p>๒. ข้อกฎหมายและระเบียบที่เกี่ยวข้อง <span class="field">โปรดระบุหนังสือสั่งการและเลขที่คำสั่งที่ตรวจสอบแล้ว</span></p>
<p>๓. ข้อพิจารณา ร่างตารางเวรฉบับนี้ยังไม่เป็นคำสั่งอนุมัติ และยังไม่ใช้เป็นหลักฐานเบิกจ่ายค่าตอบแทน</p>
<p>๔. ข้อเสนอ จึงเรียนมาเพื่อโปรดพิจารณาตามอำนาจหน้าที่</p>
<div class="sign"><div></div><div>ลงชื่อ..............................................<br>ผู้เสนอเรื่อง</div></div>
<?php else: $s=$data['swap']; ?>
<h1>ใบขอเปลี่ยนเวรปฏิบัติงานนอกเวลาราชการ</h1>
<p class="right"><?=e($hospitalName)?> วันที่ <?=e(Docs::dateThai(substr($s['created_at'],0,10),true))?></p>
<p>ข้าพเจ้า <strong><?=e($s['requester_name'])?></strong> ตำแหน่ง <?=e($s['requester_position'])?> ขอเปลี่ยนเวรวันที่ <?=e(Docs::dateThai($s['work_date'],true))?> ประเภท <strong><?=e($s['duty_code'])?></strong></p>
<p>ผู้รับปฏิบัติงานแทน <strong><?=e($s['replacement_name'])?></strong> ตำแหน่ง <?=e($s['replacement_position'])?></p>
<p>เหตุผล: <?=e($s['reason'])?></p>
<p>ความประสงค์ในการเบิกค่าตอบแทน: <span class="field">รอยืนยันตามสิทธิ์และหลักฐาน</span></p>
<p>สถานะคำขอ: <?=e($s['status'])?> — เอกสารตัวอย่าง ยังไม่ใช่หลักฐานอนุมัติ</p>
<div class="sign"><div>ลงชื่อ..............................................<br>ผู้ขอเปลี่ยนเวร</div><div>ลงชื่อ..............................................<br>ผู้รับปฏิบัติงานแทน</div></div>
<div class="sign"><div>ลงชื่อ..............................................<br>ผู้อำนวยการ รพ.สต. / ผู้ให้ความเห็นชอบ</div><div>ลงชื่อ..............................................<br>ผู้มีอำนาจอนุมัติ / ผู้รับมอบอำนาจ</div></div>
<?php endif; ?>
</main></body></html>
