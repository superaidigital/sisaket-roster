(()=>{'use strict';
const $=id=>document.getElementById(id);
const fmt=new Intl.NumberFormat('th-TH',{useGrouping:false});
const dow=['อา.','จ.','อ.','พ.','พฤ.','ศ.','ส.'];
const shiftNames={'ย':'เวรวันหยุด','บ':'เวรบ่าย','ร':'เวรเรียก On Call'};
const state={snapshot:null,busy:false,loadSeq:0,period:null,view:'table',controller:null};
const h=s=>String(s??'').replace(/[&<>"']/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
const selected=()=>({hospital_id:Number($('hospital').value),year:Number($('year').value),month:Number($('month').value)});
const same=(a,b)=>a&&b&&a.hospital_id===b.hospital_id&&a.year===b.year&&a.month===b.month;
function notify(text,kind='info'){const el=$('msg');el.textContent=text;el.dataset.kind=kind;el.hidden=!text;}
function lock(value){state.busy=value;for(const id of ['refresh','create','previous','next','today','hospital','month','year'])$(id).disabled=value;for(const btn of document.querySelectorAll('.duty-toggle'))btn.disabled=value||btn.dataset.editable!=='1';}
async function api(action,context,payload=null,signal){const method=payload?'POST':'GET';const base='index.php?c=duty&a='+encodeURIComponent(action);const url=method==='GET'?base+'&'+new URLSearchParams(context):base;
const res=await fetch(url,{method,credentials:'same-origin',headers:{Accept:'application/json',...(payload?{'Content-Type':'application/json','X-CSRF-Token':window.DUTY_CSRF}:{})},...(payload?{body:JSON.stringify({...context,...payload})}:{}),...(signal?{signal}:{})});
let data;try{data=await res.json();}catch(_){throw Error('เซิร์ฟเวอร์ไม่ส่ง JSON กรุณาตรวจสอบ Session และ Error Log');}
if(!res.ok||!data?.success)throw Error(data?.message||'ไม่สามารถทำรายการได้');return data;
}
function dateOf(day,p=state.period){return p.year+'-'+String(p.month).padStart(2,'0')+'-'+String(day).padStart(2,'0');}
function days(){const p=state.period;return new Date(Date.UTC(p.year,p.month,0)).getUTCDate();}
function weekday(d){const p=state.period;return new Date(Date.UTC(p.year,p.month-1,d)).getUTCDay();}
function filtered(){let people=state.snapshot?.staff||[];const text=$('search').value.trim().toLocaleLowerCase('th');if(text)people=people.filter(p=>(p.full_name+' '+p.position).toLocaleLowerCase('th').includes(text));const code=$('shift-filter').value;if(code){const marked=marks();people=people.filter(p=>{for(let d=1;d<=days();d++){if(marked.has(p.id+'|'+dateOf(d)+'|'+code))return true;}return false;});}return people;}
function marks(){return new Set((state.snapshot?.entries||[]).map(e=>e.user_id+'|'+e.work_date+'|'+e.code));}
function editable(){return !!state.snapshot?.can_edit&&state.snapshot?.roster?.status==='DRAFT';}
function toggle(userId,person,day,code,mark){const date=dateOf(day);const active=mark.has(userId+'|'+date+'|'+code);const allow=editable();return '<button type="button" class="duty-toggle" data-user="'+userId+'" data-day="'+day+'" data-date="'+h(date)+'" data-code="'+h(code)+'" data-active="'+(active?'1':'0')+'" data-editable="'+(allow?'1':'0')+'" aria-pressed="'+(active?'true':'false')+'" aria-label="'+h(person+' วันที่ '+day+' '+shiftNames[code]+' '+(active?'ที่จัดแล้ว':'ยังไม่ได้จัด'))+'" title="'+h(shiftNames[code])+'" '+(allow?'':'disabled')+'>'+h(code)+'</button>';}
function totalsFor(id,mark){let y=0,b=0,r=0;for(let d=1;d<=days();d++){for(const code of ['ย','บ','ร'])if(mark.has(id+'|'+dateOf(d)+'|'+code)){if(code==='ย')y++;if(code==='บ')b++;if(code==='ร')r++;}}return {y,b,r,total:y+b+r};}
function stats(){const items=state.snapshot?.entries||[];let y=0,b=0,r=0;for(const e of items){if(e.code==='ย')y++;else if(e.code==='บ')b++;else if(e.code==='ร')r++;}
$('stat-people').textContent=fmt.format((state.snapshot?.staff||[]).length);
$('stat-all').textContent=fmt.format(items.length);
$('stat-y').textContent=fmt.format(y);$('stat-b').textContent=fmt.format(b);$('stat-r').textContent=fmt.format(r);
$('stat-revision').textContent=state.snapshot?.roster?.revision??'—';}
function renderTable(){const people=filtered(),mark=marks(),limit=days();let html='<thead><tr><th scope="col">บุคลากร / ตำแหน่ง</th>';
for(let d=1;d<=limit;d++){const w=weekday(d);html+='<th scope="col"'+(w===0||w===6?' class="weekend"':'')+'>'+d+'<span class="day-label">'+dow[w]+'</span></th>';}
html+='<th class="summary-cell" scope="col">รวม ย / บ / ร</th></tr></thead><tbody>';
for(const person of people){const total=totalsFor(person.id,mark);html+='<tr><th scope="row"><span class="person-name">'+h(person.full_name)+'</span><span class="person-position">'+h(person.position)+'</span></th>';
for(let d=1;d<=limit;d++){const w=weekday(d);html+='<td'+(w===0||w===6?' class="weekend"':'')+'><div class="duty-toggles">'+['ย','บ','ร'].map(c=>toggle(person.id,person.full_name,d,c,mark)).join('')+'</div></td>';}
html+='<td class="summary-cell">'+total.y+' / '+total.b+' / '+total.r+'<div class="day-label">รวม '+total.total+'</div></td></tr>';}
if(!people.length)html+='<tr><td class="empty-row" colspan="'+(limit+2)+'">ไม่พบบุคลากรตรงกับตัวกรอง</td></tr>';
html+='</tbody>';$('grid').innerHTML=html;$('footer-count').textContent='แสดง '+people.length+' จาก '+(state.snapshot?.staff||[]).length+' คน · '+limit+' วัน';}
function renderCalendar(){const people=filtered(),select=$('calendar-person'),prior=select.value;select.replaceChildren();
for(const person of people){const opt=new Option(person.full_name+' — '+person.position,String(person.id));select.add(opt);}
if(people.some(p=>String(p.id)===prior))select.value=prior;
const chosen=people.find(p=>String(p.id)===select.value),mark=marks(),limit=days();
if(!chosen){$('calendar-grid').innerHTML='<p>ไม่พบบุคลากรตรงกับตัวกรอง</p>';$('calendar-summary').textContent='—';return;}
const total=totalsFor(chosen.id,mark);$('calendar-summary').textContent='ย '+total.y+' · บ '+total.b+' · ร '+total.r+' (รวม '+total.total+' เวร)';
let html='';const first=weekday(1);for(let i=0;i<first;i++)html+='<div class="duty-day placeholder" aria-hidden="true"></div>';
for(let d=1;d<=limit;d++){const w=weekday(d);html+='<div class="duty-day" data-weekend="'+(w===0||w===6)+'"><div class="date">'+d+'<span class="dow">'+dow[w]+'</span></div><div class="duty-toggles">'+['ย','บ','ร'].map(c=>toggle(chosen.id,chosen.full_name,d,c,mark)).join('')+'</div></div>';}
$('calendar-grid').innerHTML=html;}
function render(){const s=state.snapshot;if(!s)return;const r=s.roster;
$('validate').hidden=!(r&&s.can_edit);$('submit').hidden=!(r&&r.status==='DRAFT'&&s.can_edit);
$('heading').textContent=r?'ตารางเวร Version '+r.version:'ยังไม่มีตารางเวรในเดือนที่เลือก';
$('status-badge').textContent=r?r.status:'ยังไม่มี Draft';$('status-badge').dataset.status=r?.status||'NONE';
$('meta').textContent=r?'Revision '+r.revision+' · '+state.period.month+'/'+(state.period.year+543):'เลือกสร้าง Draft เพื่อเริ่มจัดเวร';
$('create').hidden=!(s.can_edit&&!r);
stats();renderTable();renderCalendar();}
async function load(){if(state.busy)return;const p=selected(),seq=++state.loadSeq;state.controller?.abort();state.controller=new AbortController();const controller=state.controller;
notify('กำลังโหลดข้อมูล…','loading');
try{const data=await api('data',p,null,controller.signal);if(seq!==state.loadSeq)return;state.period=p;state.snapshot=data;$('validation-panel').hidden=true;render();notify('โหลดข้อมูลตารางเรียบร้อย','success');}
catch(e){if(e.name==='AbortError'||seq!==state.loadSeq)return;state.snapshot=null;state.period=null;$('grid').innerHTML='';$('calendar-grid').innerHTML='';notify(e.message,'error');}}
async function mutate(action,payload){if(state.busy||!same(selected(),state.period))return;const period={...state.period};lock(true);notify('กำลังตรวจสอบและบันทึกข้อมูล…','loading');try{
await api(action,period,payload);
const data=await api('data',period);state.snapshot=data;$('validation-panel').hidden=true;render();notify(action==='create'?'สร้าง Draft สำเร็จ':'บันทึกเวรสำเร็จ','success');
}catch(e){notify(e.message,'error');try{state.snapshot=await api('data',period);render();}catch(_){/* keep error visible */}}
finally{lock(false);}}
function step(delta){const d=new Date(Date.UTC(Number($('year').value),Number($('month').value)-1+delta,1));const yr=d.getUTCFullYear(),mo=d.getUTCMonth()+1;if(yr<Number($('year').options[0].value)||yr>Number($('year').options[$('year').options.length-1].value))return;$('year').value=yr;$('month').value=mo;load();}
function setView(v){state.view=v;$('duty-page').dataset.view=v;$('table-mode').setAttribute('aria-pressed',String(v==='table'));$('calendar-mode').setAttribute('aria-pressed',String(v==='calendar'));if(state.snapshot)renderCalendar();}
for(const id of ['hospital','month','year'])$(id).addEventListener('change',load);
$('refresh').addEventListener('click',load);$('previous').addEventListener('click',()=>step(-1));$('next').addEventListener('click',()=>step(1));
$('today').addEventListener('click',()=>{const d=new Date();$('year').value=d.getFullYear();$('month').value=d.getMonth()+1;load();});
$('search').addEventListener('input',()=>{if(state.snapshot){renderTable();renderCalendar();}});
$('shift-filter').addEventListener('change',()=>{if(state.snapshot){renderTable();renderCalendar();}});
$('calendar-person').addEventListener('change',renderCalendar);
$('table-mode').addEventListener('click',()=>setView('table'));$('calendar-mode').addEventListener('click',()=>setView('calendar'));
$('fullscreen').addEventListener('click',async()=>{try{if(document.fullscreenElement){await document.exitFullscreen();}else{await $('duty-page').requestFullscreen();}}catch(e){notify('เบราว์เซอร์ไม่อนุญาตให้แสดงเต็มหน้าจอ','error');}});
document.addEventListener('fullscreenchange',()=>{$('fullscreen').textContent=document.fullscreenElement?'ออกจากเต็มจอ':'⛶ เต็มจอ';});
$('create').addEventListener('click',()=>mutate('create',{}));
function cellClick(ev){const btn=ev.target.closest('.duty-toggle');if(!btn||!editable()||state.busy)return;mutate('save',{user_id:Number(btn.dataset.user),work_date:btn.dataset.date,code:btn.dataset.code,enabled:btn.dataset.active!=='1',expected_revision:Number(state.snapshot.roster.revision)});}
$('grid').addEventListener('click',cellClick);$('calendar-grid').addEventListener('click',cellClick);

function showValidation(result){const v=result.validation;const panel=$('validation-panel'),box=$('validation-issues');
panel.hidden=false;$('validation-summary').textContent='Critical '+v.critical+' · Warning '+v.warning+' · '+v.assignment_count+' รายการเวร · '+v.notice;
const table=document.createElement('table');table.className='table table-sm table-striped';
const head=document.createElement('thead');const tr=document.createElement('tr');for(const name of ['ระดับ','วันที่','บุคลากร','เหตุผล']){const th=document.createElement('th');th.textContent=name;tr.append(th);}head.append(tr);table.append(head);
const body=document.createElement('tbody');for(const item of v.issues){const row=document.createElement('tr');for(const key of ['severity','work_date','person','message']){const td=document.createElement('td');td.textContent=item[key];row.append(td);}body.append(row);}table.append(body);box.replaceChildren(table);
return v;}
$('validate').addEventListener('click',async()=>{if(state.busy||!state.period)return;lock(true);try{const result=await api('validate',state.period);const v=showValidation(result);notify(v.critical?'พบข้อผิดพลาด Critical '+v.critical+' รายการ':'ผ่านการตรวจทางเทคนิค ยังต้องตรวจความพร้อมของผู้ปฏิบัติงาน','info');}catch(e){notify(e.message,'error');}finally{lock(false);}});
$('submit').addEventListener('click',async()=>{if(state.busy||!editable()||!state.period)return;try{const result=await api('validate',state.period);const v=showValidation(result);if(v.critical>0){notify('ต้องแก้ Critical '+v.critical+' รายการก่อนส่งตรวจ','error');return;}if(!confirm('ยืนยันส่ง Draft เข้ารอตรวจสอบ? เมื่อส่งแล้วจะไม่สามารถแก้ไข Draft นี้ได้ และยังไม่ใช่การอนุมัติคำสั่ง'))return;await mutate('submit',{expected_revision:Number(state.snapshot.roster.revision)});}catch(e){notify(e.message,'error');}});
load();
})();