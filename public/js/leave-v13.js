(()=>{'use strict';
const $=id=>document.getElementById(id);let busy=false;
function notify(t,error=false){const el=$('message');el.className='alert '+(error?'alert-danger':'alert-info');el.textContent=t;}
async function call(action,body){const res=await fetch('index.php?c=leave&a='+action,{method:body?'POST':'GET',credentials:'same-origin',
 headers:{Accept:'application/json',...(body?{'Content-Type':'application/json','X-CSRF-Token':window.LEAVE_CSRF}:{})},
 ...(body?{body:JSON.stringify(body)}:{})});
const data=await res.json();if(!res.ok||!data.success)throw Error(data.message||'ดำเนินการไม่สำเร็จ');return data;}
function dt(str){if(!/^\d{4}-\d\d-\d\dT\d\d:\d\d$/.test(str))throw Error('วันเวลาไม่ถูกต้อง');return str.replace('T',' ')+':00';}
async function load(){try{const d=await call('data');$('type').replaceChildren();
for(const t of d.types){const op=new Option(t.name,String(t.id));$('type').add(op);}
const tbody=$('requests');tbody.replaceChildren();
for(const r of d.requests){const row=document.createElement('tr');for(const key of ['id','full_name','leave_type','starts_at','ends_at','status']){const td=document.createElement('td');td.textContent=r[key]??'';row.append(td);}
const actions=document.createElement('td');if(d.can_review&&r.status==='SUBMITTED'){for(const [label,decision] of [['อนุมัติ','APPROVED'],['ไม่อนุมัติ','REJECTED']]){const b=document.createElement('button');b.type='button';b.className='btn btn-sm btn-outline-secondary me-1';b.textContent=label;b.addEventListener('click',()=>decide(r.id,decision));actions.append(b);}}row.append(actions);tbody.append(row);}
notify('โหลดรายการเรียบร้อย');}catch(e){notify(e.message,true);}}
$('leave-form').addEventListener('submit',async e=>{e.preventDefault();if(busy)return;busy=true;$('submit').disabled=true;
try{const d=await call('submit',{leave_type_id:Number($('type').value),starts_at:dt($('start').value),ends_at:dt($('end').value),reason:$('reason').value});
$('leave-form').reset();await load();notify(d.message);}catch(e){notify(e.message,true);}finally{busy=false;$('submit').disabled=false;}});
async function decide(id,decision){if(busy||!confirm('ยืนยันผลการพิจารณา '+decision+' ?'))return;busy=true;
try{const d=await call('decide',{request_id:Number(id),decision});await load();notify(d.message);}catch(e){notify(e.message,true);}finally{busy=false;}}
load();
})();