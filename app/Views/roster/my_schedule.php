<?php
/** V7 - Read-only and session-scoped individual schedule. No user_id is accepted from URL. */
$months=['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
$shortDays=['อา.','จ.','อ.','พ.','พฤ.','ศ.','ส.'];
$longDays=['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์'];
$shiftNames=['MORNING'=>'เช้า','AFTERNOON'=>'บ่าย','NIGHT'=>'ดึก','OFF'=>'หยุด (OFF)','LEAVE'=>'ลา'];
$counts=['MORNING'=>0,'AFTERNOON'=>0,'NIGHT'=>0,'OFF'=>0,'LEAVE'=>0];
$byDate=[];$hours=0.0;
foreach($entries as $item) {
    $byDate[(string)$item['work_date']]=$item;
    $code=(string)$item['code'];$counts[$code]=($counts[$code]??0)+1;
    if((int)$item['is_working']) {
        $start=strtotime('2026-01-01 '.($item['start_time']??'00:00:00'));
        $end=strtotime('2026-01-01 '.($item['end_time']??'00:00:00'));
        $duration=($end-$start)/3600;if($duration<=0)$duration+=24;
        $hours+=$duration;
    }
}
$base=new \DateTimeImmutable(sprintf('%04d-%02d-01',$year,$month));
$prev=$base->modify('-1 month');$next=$base->modify('+1 month');
$prevLink='index.php?c=roster&amp;a=my_schedule&amp;year='.$prev->format('Y').'&amp;month='.$prev->format('n');
$nextLink='index.php?c=roster&amp;a=my_schedule&amp;year='.$next->format('Y').'&amp;month='.$next->format('n');
$yearRange=range(max(2000,$year-3),min(2100,$year+3));
$today=date('Y-m-d');
?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>เวรของฉัน — <?=e($h['name'])?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{color-scheme:light;--ink:#12354c;--line:#d9e4ef;--blue:#1669cf;--bg:#f6f9fd}
*{box-sizing:border-box}body{font-family:Sarabun,Tahoma,sans-serif;color:var(--ink);background:var(--bg)}
.sheet{max-width:1100px;margin:0 auto;padding:24px 16px 50px}.cardish{background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:0 4px 22px rgba(21,57,87,.04)}
.hero{padding:22px}.eyebrow{font-size:.7rem;letter-spacing:.12em;color:#346aa0;font-weight:700;text-transform:uppercase}
.hero h1{font-weight:700;font-size:clamp(1.5rem,3vw,2rem)}.subtext{color:#576f83}.status{font-size:.84rem;background:#eaf5ed;border-radius:30px;color:#175e35;padding:3px 12px;display:inline-block;margin-top:8px}
.month-nav{display:flex;align-items:center;gap:10px;justify-content:space-between;margin:20px 0 0}.month-nav .btn{min-width:110px}
.summary-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:11px;margin:16px 0 24px}.metric{padding:16px}.metric span{font-size:.84rem;color:#536f86}.metric strong{font-size:1.7rem;display:block;color:#123b59}
.cal{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:9px}.weekday{text-align:center;padding:8px;font-weight:700;color:#576d80}
.day{min-width:0;min-height:120px;padding:12px;background:#fff;border:1px solid var(--line);border-radius:12px;overflow-wrap:anywhere}.day.today{outline:2px solid #176ff1;outline-offset:1px}
.day.weekend{border-top:3px solid #c79536}.day.off{background:#f0f4f8}.day.leave{background:#fff7e8}.day.unassigned{border-style:dashed}.day-head{display:flex;gap:8px;justify-content:space-between;align-items:center;font-weight:700}
.day-head .dow{font-size:.81rem;color:#677e91}.shift{display:inline-flex;background:#e6f2ff;border:1px solid #bfdaff;border-radius:8px;padding:3px 9px;font-weight:700;margin-top:12px}
.shift.night{background:#f0eaff;border-color:#d8c7fb;color:#5c2f99}.shift.afternoon{background:#fff3e2;border-color:#f5d5a2;color:#844900}.shift.off{background:#e6edf3;border-color:#d4dce6;color:#455b6f}.shift.leave{background:#fff1d2;border-color:#f4dca2;color:#775109}
.shift-time{display:block;color:#516a80;font-size:.82rem;margin-top:6px}.note{font-size:.84rem;color:#4f6373;word-break:break-word;margin-top:5px}.empty{font-size:.85rem;color:#718397;margin-top:12px}.legend{font-size:.85rem;color:#5c7588;margin-top:18px}
a,button,select{min-height:44px}a:focus-visible,button:focus-visible,select:focus-visible{outline:3px solid #005fcc;outline-offset:2px}.mobile-date{display:none}
@media(max-width:750px){.sheet{padding:12px 12px 40px}.hero{padding:17px}.header-actions{width:100%;justify-content:stretch}.header-actions>*{flex:1}.summary-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.metric{padding:12px}.metric strong{font-size:1.5rem}.calendar-title{font-size:1.13rem}.cal{display:grid;grid-template-columns:1fr;gap:9px}.weekday,.blank{display:none}.day{min-height:0;display:flex;align-items:center;gap:12px;padding:12px}.day .day-head{display:flex;flex-direction:column;justify-content:center;align-items:center;border-right:1px solid #e4ecf4;min-width:67px;padding:2px 8px 2px 0}.day-head .number{font-size:1.45rem}.day-head .dow{display:block}.day .day-content{flex:1;min-width:0}.shift,.empty{margin-top:0}.month-nav .btn{min-width:0;flex:1}.month-nav strong{text-align:center;flex:2}.mobile-date{display:inline}}
@media(max-width:370px){.month-nav{gap:5px}.month-nav .btn{font-size:.82rem}.header-actions{flex-direction:column}}
@media print{body{background:white;color:black}.sheet{max-width:none;padding:0}.no-print{display:none!important}.cardish{box-shadow:none;border:0}.summary-grid{grid-template-columns:repeat(5,minmax(0,1fr));gap:5px}.metric{border:1px solid #ccc;padding:8px}.cal{grid-template-columns:repeat(7,minmax(0,1fr));gap:5px}.weekday{display:block}.blank{display:block}.day{display:block;min-height:100px;break-inside:avoid;padding:6px}.day .day-head{display:flex;flex-direction:row;border:0;align-items:baseline;padding:0;min-width:0}.day-head .number{font-size:1rem}.day .shift{margin-top:9px}.hero{padding:0 0 8px}.month-nav{display:none}a{color:inherit!important;text-decoration:none!important}}
@media(prefers-reduced-motion:reduce){*{scroll-behavior:auto!important;transition:none!important}}
</style></head><body>
<main class="sheet">
<header class="hero cardish"><div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
<div><div class="eyebrow">PERSONAL ROSTER</div><h1 class="mb-1">เวรของฉัน</h1><div><?=e((string)$user['full_name'])?> · <?=e((string)$h['name'])?></div><div class="subtext">ประจำเดือน <?=e($months[$month-1])?> พ.ศ. <?=$year+543?></div><?php if($r):?><span class="status">เผยแพร่แล้ว · Version <?=(int)$r['version']?></span><?php endif;?></div>
<div class="d-flex flex-wrap gap-2 header-actions no-print"><a class="btn btn-outline-secondary" href="index.php?c=roster&amp;a=index">← ตารางหลัก</a><button type="button" class="btn btn-primary" onclick="window.print()">พิมพ์ / PDF</button></div>
</div>
<form class="row g-2 align-items-end mt-3 no-print" method="get" action="index.php">
<input type="hidden" name="c" value="roster"><input type="hidden" name="a" value="my_schedule">
<div class="col-6 col-md-4"><label class="form-label" for="month">เดือน</label><select class="form-select" id="month" name="month"><?php foreach($months as $i=>$name):?><option value="<?=$i+1?>" <?=$month===$i+1?'selected':''?>><?=e($name)?></option><?php endforeach;?></select></div>
<div class="col-6 col-md-4"><label class="form-label" for="year">ปี พ.ศ.</label><select class="form-select" id="year" name="year"><?php foreach($yearRange as $value):?><option value="<?=$value?>" <?=$year===$value?'selected':''?>><?=$value+543?></option><?php endforeach;?></select></div>
<div class="col-12 col-md-4"><button type="submit" class="btn btn-primary w-100">แสดงตาราง</button></div></form></header>
<nav aria-label="เลือกเดือน" class="month-nav no-print">
<?php if((int)$prev->format('Y')>=2000):?><a class="btn btn-outline-secondary" href="<?=$prevLink?>">← เดือนก่อน</a><?php else:?><span></span><?php endif;?><strong><?=e($months[$month-1])?> <?=$year+543?></strong><?php if((int)$next->format('Y')<=2100):?><a class="btn btn-outline-secondary" href="<?=$nextLink?>">เดือนถัดไป →</a><?php else:?><span></span><?php endif;?></nav>
<?php if(!$r):?><div class="alert alert-info mt-3" role="status">ยังไม่มีตารางเวรที่เผยแพร่สำหรับเดือนนี้ โปรดติดต่อผู้จัดเวรหากมีข้อสงสัย</div>
<?php else:?>
<section class="summary-grid" aria-label="สรุปเวรของฉัน">
<?php foreach(['MORNING'=>'เช้า','AFTERNOON'=>'บ่าย','NIGHT'=>'ดึก','OFF'=>'OFF'] as $code=>$label):?><div class="metric cardish"><span><?=e($label)?></span><strong><?= (int)($counts[$code]??0)?></strong></div><?php endforeach;?><div class="metric cardish"><span>ชั่วโมงทำงาน</span><strong><?=number_format($hours,1)?></strong></div></section>
<h2 class="h5 calendar-title mb-3">ปฏิทินตารางเวรส่วนบุคคล</h2>
<div class="cal" aria-hidden="true"><?php foreach($shortDays as $label):?><div class="weekday"><?=e($label)?></div><?php endforeach;?></div>
<div class="cal" aria-label="เวรประจำเดือน <?=e($months[$month-1])?> พ.ศ. <?=$year+543?>">
<?php $weekdayStart=(int)$base->format('w');for($i=0;$i<$weekdayStart;$i++):?><div class="blank" aria-hidden="true"></div><?php endfor;?>
<?php for($day=1;$day<=$days;$day++):
$date=sprintf('%04d-%02d-%02d',$year,$month,$day);$entry=$byDate[$date]??null;$code=(string)($entry['code']??'');
$dow=(int)(new \DateTimeImmutable($date))->format('w');$dayName=$longDays[$dow];
$css=match($code){'OFF'=>'off','LEAVE'=>'leave',default=>($entry?'working':'unassigned')};
$shiftLabel=$entry ? ($shiftNames[$code]??(string)$entry['shift_name']) : 'ยังไม่กำหนดเวร';
$time=$entry && (int)$entry['is_working']?substr((string)$entry['start_time'],0,5).'–'.substr((string)$entry['end_time'],0,5):'';
?>
<article class="day <?=$css?> <?=$date===$today?'today':''?> <?=$dow===0||$dow===6?'weekend':''?>" aria-label="วัน<?=$dayName?>ที่ <?=$day?> <?=e($months[$month-1])?> <?=$year+543?> <?=e($shiftLabel)?>">
<div class="day-head"><span class="number"><?=$day?></span><span class="dow"><?=e($shortDays[$dow])?></span></div>
<div class="day-content"><?php if($entry):?><span class="shift <?=$css==='working'?strtolower($code):$css?>"><?=e($shiftLabel)?></span><?php if($time!==''):?><span class="shift-time"><?=e($time)?> น.</span><?php endif;?><?php if(trim((string)$entry['note'])!==''):?><div class="note"><?=e((string)$entry['note'])?></div><?php endif;?><?php else:?><div class="empty">ยังไม่กำหนดเวร</div><?php endif;?></div>
</article>
<?php endfor;?></div>
<p class="legend">แสดงเฉพาะเวรของบัญชีที่เข้าสู่ระบบ จาก Version ล่าสุดที่เผยแพร่หรือถูกล็อกแล้วเท่านั้น · OFF คือวันหยุดที่กำหนดไว้ ส่วน “ยังไม่กำหนดเวร” ไม่ใช่วันหยุด</p>
<?php endif;?></main></body></html>
