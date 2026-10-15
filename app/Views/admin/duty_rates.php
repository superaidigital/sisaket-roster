<?php
/** @var array $user @var array $hospitals */
require dirname(__DIR__).'/admin/_head.php';
?>
<section class="admin-card p-3 mb-3">
  <div class="alert alert-warning" role="note"><strong>เฉพาะข้อเสนอ Draft:</strong> ระบบนี้ไม่อนุมัติอัตรา ไม่แก้ไขตาราง compensation_rates และไม่สร้างยอดเบิก</div>
  <h2 class="h5">เสนออัตราค่าตอบแทนใหม่</h2>
  <form id="rate-form" class="row g-3">
    <div class="col-md-6"><label for="hospital" class="form-label">หน่วยบริการ</label>
      <select class="form-select" id="hospital" required>
        <?php foreach ($hospitals as $h): ?><option value="<?=(int)$h['id']?>"><?=e($h['name'])?></option><?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label for="group" class="form-label">กลุ่มตำแหน่ง</label>
      <select class="form-select" id="group"><option value="1">1 วิชาชีพ</option><option value="2">2 เจ้าพนักงาน</option><option value="3">3 เจ้าหน้าที่อื่น</option></select></div>
    <div class="col-md-3"><label for="code" class="form-label">เวร</label>
      <select class="form-select" id="code"><option value="ย">ย</option><option value="บ">บ</option><option value="ร">ร — On Call</option></select></div>
    <div class="col-md-3"><label for="unit" class="form-label">หน่วยอัตรา</label>
      <select class="form-select" id="unit"><option value="PER_SHIFT">บาทต่อเวร</option><option value="PER_HOUR">บาทต่อชั่วโมง</option></select></div>
    <div class="col-md-3"><label for="amount" class="form-label">จำนวนเงิน (บาท)</label>
      <input class="form-control" id="amount" type="number" min="0" max="9999999.99" step=".01" required></div>
    <div class="col-md-3"><label for="from" class="form-label">วันที่เริ่มใช้ (ค.ศ.)</label><input class="form-control" id="from" type="date" required></div>
    <div class="col-md-3"><label for="to" class="form-label">วันที่สิ้นสุด (ถ้ามี)</label><input class="form-control" id="to" type="date"></div>
    <div class="col-12"><label for="eligible" class="form-label"><input id="eligible" type="checkbox" class="form-check-input"> อนุญาตสิทธิ์ On Call (เฉพาะเวร ร)</label></div>
    <div class="col-md-6"><label for="reference" class="form-label">เอกสารอ้างอิง / เลขคำสั่ง</label><input id="reference" class="form-control" required minlength="5" maxlength="500"></div>
    <div class="col-md-6"><label for="reason" class="form-label">เหตุผล</label><input id="reason" class="form-control" required minlength="5" maxlength="500"></div>
    <div class="col-12"><button id="submit" type="submit" class="btn btn-primary"><i class="bi bi-file-earmark-plus"></i> บันทึกข้อเสนอ Draft</button></div>
  </form>
  <div id="message" class="mt-3" role="status" aria-live="polite"></div>
</section>
<section class="admin-card p-3 mb-3"><h2 class="h5">ประวัติข้อเสนอของหน่วยบริการ</h2>
<div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>ID</th><th>เวอร์ชัน</th><th>กลุ่ม</th><th>เวร</th><th>เงิน</th><th>มีผลวันที่</th><th>สถานะ</th></tr></thead><tbody id="proposals"></tbody></table></div></section>
<section class="admin-card p-3 mb-4"><h2 class="h5">อัตราอ้างอิง (อ่านอย่างเดียว)</h2>
<div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>กลุ่ม</th><th>เวร</th><th>กฎ</th><th>หน่วย</th><th>จำนวน</th><th>เริ่มใช้</th><th>สถานะ</th></tr></thead><tbody id="rates"></tbody></table></div></section>
<script>
(()=>{'use strict';
const csrf=<?=json_encode(\App\Core\Csrf::token(),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
const el=x=>document.getElementById(x);
function cell(tr,value){const td=document.createElement('td');td.textContent=value??'—';tr.appendChild(td);}
function render(target,rows,fields){const body=el(target);body.replaceChildren();for(const obj of rows){const tr=document.createElement('tr');for(const f of fields)cell(tr,obj[f]);body.appendChild(tr);}if(!rows.length){const tr=document.createElement('tr');const td=document.createElement('td');td.colSpan=fields.length;td.textContent='ยังไม่มีรายการ';tr.appendChild(td);body.appendChild(tr);}}
async function refresh(){const resp=await fetch('index.php?c=duty_rates&a=data&hospital_id='+encodeURIComponent(el('hospital').value),{credentials:'same-origin',headers:{Accept:'application/json'}});const d=await resp.json();if(!resp.ok||!d.success)throw Error(d.message||'โหลดข้อมูลไม่สำเร็จ');render('proposals',d.proposals,['id','proposal_version','group_id','duty_code','proposed_rate_amount','proposed_effective_from','status']);render('rates',d.rates,['group_name','duty_code','rule_version','rate_unit','rate_amount','effective_from','approval_status']);}
el('hospital').addEventListener('change',()=>refresh().catch(err=>{el('message').textContent=err.message;}));
el('rate-form').addEventListener('submit',async e=>{e.preventDefault();el('submit').disabled=true;try{
const input={hospital_id:Number(el('hospital').value),group_id:Number(el('group').value),duty_code:el('code').value,rate_unit:el('unit').value,rate_amount:Number(el('amount').value).toFixed(2),effective_from:el('from').value,effective_to:el('to').value||null,on_call_pay_eligible:el('eligible').checked,legal_reference:el('reference').value,reason:el('reason').value};
const r=await fetch('index.php?c=duty_rates&a=propose',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf,Accept:'application/json'},body:JSON.stringify(input)});const d=await r.json();if(!r.ok||!d.success)throw Error(d.message||'บันทึกไม่สำเร็จ');el('message').textContent='บันทึกข้อเสนอ #'+d.proposal_id+' เป็น Draft แล้ว ยังไม่มีผลเบิกจ่าย';await refresh();
}catch(err){el('message').textContent=err.message;}finally{el('submit').disabled=false;}});
refresh().catch(err=>{el('message').textContent=err.message;});
})();
</script>
<?php require dirname(__DIR__).'/admin/_foot.php'; ?>
