(()=>{'use strict';const $=id=>document.getElementById(id);let busy=false,types=[],skills={},requirements=[];
function notify(t,error=false){const el=$('message');el.className='alert '+(error?'alert-danger':'alert-info');el.textContent=t;}
async function api(action,body){const ctx=body?'':'&hospital_id='+encodeURIComponent($('hospital').value);
const r=await fetch('index.php?c=duty_coverage&a='+action+ctx,{method:body?'POST':'GET',credentials:'same-origin',
headers:{Accept:'application/json',...(body?{'Content-Type':'application/json','X-CSRF-Token':window.COVERAGE_CSRF}:{})},
...(body?{body:JSON.stringify(body)}:{})});const d=await r.json();if(!r.ok||!d.success)throw Error(d.message||'คำขอไม่สำเร็จ');return d;}
async function load(){try{const d=await api('data');types=d.types;skills=d.skills;requirements=d.requirements;render();notify('แสดงเกณฑ์ที่บันทึกอยู่');}catch(e){notify(e.message,true);$('coverage').hidden=true;}}
function fieldInput(label,value,min,max){const div=document.createElement('div');div.className='col-sm-6 col-lg-3';const l=document.createElement('label');l.className='form-label';l.textContent=label;
const input=document.createElement('input');input.className='form-control';input.type='number';input.min=String(min);input.max=String(max);input.step='1';input.value=String(value);input.required=true;div.append(l,input);return {div,input};}
function render(){const el=$('policies');el.replaceChildren();for(const t of types){
const box=document.createElement('section');box.className='border rounded p-3 mb-3';box.dataset.code=t.code;
const head=document.createElement('h2');head.className='h5';head.textContent='เวร '+t.code+' — '+t.name;box.append(head);
const row=document.createElement('div');row.className='row g-2 mb-3';
const saved=requirements.filter(r=>r.duty_code===t.code);const values=Object.fromEntries(saved.map(r=>[r.skill_code,Number(r.min_count)]));
const minimums={};for(const [key,label] of Object.entries({'__TOTAL__':'จำนวนเจ้าหน้าที่รวมขั้นต่ำ',...skills})){
const {div,input}=fieldInput(label,values[key]??0,0,100);minimums[key]=input;row.append(div);}
box.append(row);box._minimums=minimums;
const refWrap=document.createElement('div');const label=document.createElement('label');label.className='form-label';label.textContent='เลขที่หนังสือ/หลักฐานอ้างอิงเกณฑ์ (อย่างน้อย 8 ตัวอักษร)';
const ref=document.createElement('input');ref.type='text';ref.maxLength=500;ref.required=true;ref.className='form-control';ref.value=saved[0]?.evidence_reference??'';refWrap.append(label,ref);box._evidence=ref;box.append(refWrap);el.append(box);}
$('coverage').hidden=false;}
$('hospital').addEventListener('change',load);
$('coverage').addEventListener('submit',async e=>{e.preventDefault();if(busy)return;busy=true;$('save').disabled=true;
try{const rules=[];for(const box of $('policies').children){const minima={};for(const [key,input] of Object.entries(box._minimums)){const n=Number(input.value);if(!Number.isInteger(n))throw Error('จำนวนขั้นต่ำไม่ถูกต้อง');minima[key]=n;}rules.push({code:box.dataset.code,minimums:minima,evidence_reference:box._evidence.value.trim()});}
const d=await api('save',{hospital_id:Number($('hospital').value),rules});await load();notify(d.message);}catch(e){notify(e.message,true);}finally{busy=false;$('save').disabled=false;}});
load();})();