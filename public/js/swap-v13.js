(()=>{'use strict';const $=id=>document.getElementById(id);let busy=false;
function notify(text,error=false){const el=$('message');el.className='alert '+(error?'alert-danger':'alert-info');el.textContent=text;}
async function api(a,body=null){const url='index.php?c=swap&a='+a;const res=await fetch(url,{method:body?'POST':'GET',credentials:'same-origin',
headers:{Accept:'application/json',...(body?{'Content-Type':'application/json','X-CSRF-Token':window.SWAP_CSRF}:{})},
...(body?{body:JSON.stringify(body)}:{})});const d=await res.json();if(!res.ok||!d.success)throw Error(d.message||'คำขอไม่สำเร็จ');return d;}
function button(name,fn){const b=document.createElement('button');b.type='button';b.className='btn btn-sm btn-outline-primary me-1 mb-1';b.textContent=name;b.addEventListener('click',fn);return b;}
async function load(){try{const d=await api('data');$('assignment').replaceChildren();$('replacement').replaceChildren();$('requests').replaceChildren();
for(const a of d.assignments){$('assignment').add(new Option(a.work_date+' • '+a.code+' • Version '+a.version,String(a.id)));}
for(const p of d.replacements){$('replacement').add(new Option(p.full_name+' — '+p.position,String(p.id)));}
for(const s of d.requests){const row=document.createElement('tr');for(const k of ['id','work_date','code','requester_name','replacement_name','status']){const c=document.createElement('td');c.textContent=s[k]??'';row.append(c);}
const actions=document.createElement('td');actions.append(button('เหตุผล',()=>readReason(s.id)));
if(s.status==='PENDING_CONSENT'&&Number(s.replacement_id)===Number(d.user_id)){
actions.append(button('ยินยอม',()=>act('consent',{swap_id:Number(s.id),accept:true})));
actions.append(button('ปฏิเสธ',()=>act('consent',{swap_id:Number(s.id),accept:false})));
}
if(s.status==='PENDING_DIRECTOR'&&['DIRECTOR','SUPERADMIN'].includes(d.role)){
actions.append(button('เห็นชอบ',()=>act('review',{swap_id:Number(s.id),endorse:true})));
actions.append(button('ไม่เห็นชอบ',()=>act('review',{swap_id:Number(s.id),endorse:false})));
}
row.append(actions);$('requests').append(row);}
notify('โหลดรายการคำขอเรียบร้อย');}catch(e){notify(e.message,true);}}
async function act(action,body){if(busy||!confirm('ยืนยันดำเนินการรายการนี้?'))return;busy=true;try{const d=await api(action,body);await load();notify(d.message);}catch(e){notify(e.message,true);}finally{busy=false;}}
async function readReason(id){try{const res=await fetch('index.php?c=swap&a=reason&swap_id='+encodeURIComponent(id),{headers:{Accept:'application/json'},credentials:'same-origin'});const d=await res.json();if(!res.ok||!d.success)throw Error(d.message||'ไม่มีสิทธิ์ดูเหตุผล');notify('เหตุผล: '+d.reason);}catch(e){notify(e.message,true);}}
$('swap-form').addEventListener('submit',async e=>{e.preventDefault();if(busy)return;busy=true;$('send').disabled=true;
try{const d=await api('request',{assignment_id:Number($('assignment').value),replacement_id:Number($('replacement').value),reason:$('reason').value.trim()});$('reason').value='';await load();notify(d.message);}catch(e){notify(e.message,true);}finally{busy=false;$('send').disabled=false;}});
load();})();