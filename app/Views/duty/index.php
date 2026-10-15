<?php
declare(strict_types=1);
use App\Core\Csrf;
?>
<!doctype html><html lang="th"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ตารางเวร ย/บ/ร (DEV) | อบจ.ศรีสะเกษ</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<style>
body{background:#f4f7fb;font-family:system-ui,'Sarabun',sans-serif;color:#183657}
main{max-width:1500px;margin:auto;padding:1rem}.panel{background:white;border:1px solid #dce5f2;border-radius:14px;padding:1rem;box-shadow:0 5px 20px #1232}
.scroll{overflow:auto;max-height:72vh}.duty-grid{border-collapse:separate;border-spacing:0;font-size:.78rem}
.duty-grid th,.duty-grid td{border:1px solid #e2eaf4;padding:.35rem;min-width:68px;text-align:center}
.duty-grid thead th{position:sticky;top:0;background:#e8f1ff;z-index:2}
.duty-grid th:first-child{position:sticky;left:0;z-index:3;min-width:190px;background:#eef4fd;text-align:left}
.duty-cell{display:flex;flex-wrap:wrap;gap:3px;justify-content:center}
.duty-cell button{min-width:25px;border-radius:5px;border:1px solid #c7d6e9;background:white}
.duty-cell button.active{background:#1666ea;color:white}
.duty-cell button:disabled{opacity:.7;cursor:default}
#msg{min-height:2.5rem}
</style></head><body>
<main>
<div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
<div><small class="text-primary fw-bold">SISAKET PAO · DEV PHASE 2</small><h1 class="h4">ตารางเวรปฏิบัติงาน ย / บ / ร</h1>
<div class="text-secondary">ทดลองจัดตารางเวร Draft เท่านั้น · ยังไม่ใช้เบิกจ่ายเงินหรืออนุมัติคำสั่ง</div></div>
<a class="btn btn-outline-primary" href="index.php?c=roster&a=index">กลับตารางเวร V11</a>
</div>
<div class="panel mb-3">
<div class="row g-2 align-items-end">
<div class="col-lg-5"><label class="form-label" for="hospital">หน่วยบริการ</label>
<select id="hospital" class="form-select">
<?php if($hospitals):foreach($hospitals as $hospital): ?>
<option value="<?=(int)$hospital['id']?>"><?=e($hospital['name'])?></option>
<?php endforeach;else: ?>
<option value="<?=(int)$user['hospital_id']?>">หน่วยบริการของฉัน</option>
<?php endif; ?>
</select></div>
<div class="col-4 col-lg-2"><label class="form-label" for="month">เดือน</label>
<select id="month" class="form-select">
<?php for($m=1;$m<=12;$m++):?><option value="<?=$m?>" <?=$m===(int)date('n')?'selected':''?>><?=$m?></option><?php endfor; ?>
</select></div>
<div class="col-4 col-lg-2"><label class="form-label" for="year">ปี ค.ศ.</label><input id="year" type="number" class="form-control" min="2000" max="2100" value="<?=date('Y')?>"></div>
<div class="col-4 col-lg-3 d-flex gap-1"><button id="refresh" class="btn btn-outline-primary flex-grow-1">แสดง</button><button id="create" class="btn btn-primary flex-grow-1">สร้าง Draft</button></div>
</div>
</div>
<div id="msg" role="status" aria-live="polite"></div>
<div class="panel"><div class="d-flex justify-content-between flex-wrap mb-2 gap-2"><strong id="heading">รอโหลดตาราง</strong><span class="text-secondary" id="meta"></span></div>
<div class="scroll"><table class="duty-grid" id="grid"><caption class="visually-hidden">ตารางเวรปฏิบัติงานรายเดือน</caption></table></div>
<div class="text-secondary small mt-3">ย: 08.30–16.30 · บ: 16.31–20.30 · ร: 20.31–08.29 วันถัดไป ต้องตรวจสอบการปฏิบัติงานจริงก่อนคำนวณค่าตอบแทน</div>
</div>
</main>
<script>
(()=>{'use strict';
const csrf=<?=json_encode(Csrf::token(),JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)?>;
const E=id=>document.getElementById(id);let snapshot=null,busy=false;
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const query=()=>({hospital_id:+E('hospital').value,month:+E('month').value,year:+E('year').value});
function status(s,bad=false){E('msg').innerHTML='<div class="alert alert-'+(bad?'danger':'info')+' py-2">'+esc(s)+'</div>';}
async function request(action,data=null){
 const url='index.php?c=duty&a='+action+(data?'':'&'+new URLSearchParams(query()));
 const response=await fetch(url,{method:data?'POST':'GET',credentials:'same-origin',
 headers:{'Accept':'application/json',...(data?{'Content-Type':'application/json','X-CSRF-Token':csrf}:{})},
 ...(data?{body:JSON.stringify({...query(),...data})}:{})});
 const obj=await response.json();if(!response.ok||!obj.success)throw new Error(obj.message||'เกิดข้อผิดพลาด');
 return obj;
}
function render(){
 const r=snapshot?.roster,people=snapshot?.staff||[],types=snapshot?.types||[],entries=snapshot?.entries||[];
 const editable=snapshot?.can_edit&&r?.status==='DRAFT';
 E('create').hidden=!snapshot?.can_edit||!!r;
 E('heading').textContent=r?'Version '+r.version+' · '+r.status:'ยังไม่มีตารางเวร';
 E('meta').textContent=r?'Revision '+r.revision:'';
 const max=new Date(+E('year').value,+E('month').value,0).getDate();
 const marks=new Set(entries.map(v=>v.user_id+'|'+v.work_date+'|'+v.code));
 let html='<thead><tr><th scope="col">บุคลากร</th>';
 for(let d=1;d<=max;d++)html+='<th scope="col">'+d+'</th>';html+='</tr></thead><tbody>';
 for(const person of people){
 html+='<tr><th scope="row">'+esc(person.full_name)+'<small class="d-block text-secondary">'+esc(person.position)+'</small></th>';
 for(let d=1;d<=max;d++){
 const date=E('year').value+'-'+String(E('month').value).padStart(2,'0')+'-'+String(d).padStart(2,'0');
 html+='<td><div class="duty-cell">';
 for(const t of types){const active=marks.has(person.id+'|'+date+'|'+t.code);
 html+='<button type="button" class="'+(active?'active':'')+'" data-user="'+person.id+'" data-date="'+esc(date)+'" data-code="'+esc(t.code)+'" data-active="'+(active?'1':'0')+'" '+(editable?'':'disabled')+' aria-label="'+esc(person.full_name+' '+date+' '+t.name)+'" aria-pressed="'+(active?'true':'false')+'">'+esc(t.code)+'</button>';}
 html+='</div></td>';}
 html+='</tr>';}
 html+='</tbody>';E('grid').innerHTML=html;
}
async function load(){if(busy)return;try{status('กำลังโหลดข้อมูล...');snapshot=await request('data');render();status('โหลดตารางเรียบร้อย');}catch(e){status(e.message,true);}}
async function save(button){
 if(busy||!snapshot?.roster||!snapshot?.can_edit)return;busy=true;
 try{status('กำลังตรวจสอบและบันทึก...');const result=await request('save',{
 user_id:+button.dataset.user,work_date:button.dataset.date,code:button.dataset.code,
 enabled:button.dataset.active!=='1',expected_revision:+snapshot.roster.revision});
 snapshot.roster.revision=result.revision;
 snapshot=await request('data');render();status('บันทึก Draft สำเร็จ');}
 catch(e){status(e.message,true);try{snapshot=await request('data');render();}catch(_){}}
 finally{busy=false;}
}
E('grid').addEventListener('click',e=>{const btn=e.target.closest('button[data-user]');if(btn)save(btn);});
E('refresh').addEventListener('click',load);
E('create').addEventListener('click',async()=>{if(busy)return;busy=true;try{await request('create',{});status('สร้าง Draft สำเร็จ');}catch(e){status(e.message,true);}finally{busy=false;load();}});
load();
})();
</script></body></html>
