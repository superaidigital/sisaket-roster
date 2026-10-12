/* Sisaket Roster - dependency-free controller for responsive roster UI */
(() => {
  'use strict';
  const cfg = window.ROSTER_APP;
  const $ = (id) => document.getElementById(id);
  const thMonths = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
  const state = {hospitalId: cfg.hospitalId, year:new Date().getFullYear(), month:new Date().getMonth()+1,
    payload:null, day:new Date().getDate(), view:'table', modalCell:null, clipboard:null, dragging:null,
    mine:false, queue:Promise.resolve(), busy:false, lastCell:null, validation:null, loadSeq:0};
  const canEdit = () => !!state.payload?.permissions.can_edit && state.payload?.roster?.status === 'DRAFT';
  const roster = () => state.payload?.roster;
  const safe = (t) => String(t ?? '').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const iso = (day=state.day) => `${state.year}-${String(state.month).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
  const dayCount = () => new Date(state.year,state.month,0).getDate();
  const fmtDate = (date) => new Intl.DateTimeFormat('th-TH-u-ca-buddhist',{day:'numeric',month:'short',year:'numeric'}).format(new Date(`${date}T12:00:00`));
  const isWeekend = (day) => [0,6].includes(new Date(`${iso(day)}T12:00:00`).getDay());
  const shiftById = (id) => state.payload?.shifts.find(s=>Number(s.id)===Number(id));
  const shiftByCode = (code) => state.payload?.shifts.find(s=>s.code===code);
  const personById = (id) => state.payload?.staff.find(s=>Number(s.id)===Number(id));
  const entry = (user,date) => state.payload?.entries.find(e=>Number(e.user_id)===Number(user) && e.work_date===date);
  const url = (action,params={}) => `index.php?c=roster&a=${encodeURIComponent(action)}&${new URLSearchParams(params)}`;
  const modal = (id) => bootstrap.Modal.getOrCreateInstance($(id));
  let toastTimer;
  function toast(text,type='info') {
    const box=$('toastRegion'); box.innerHTML=`<div class="app-toast app-toast-${type}" role="alert">${safe(text)}</div>`;
    clearTimeout(toastTimer); toastTimer=setTimeout(()=>box.replaceChildren(),4700);
  }
  async function get(action, params={}) {
    const r=await fetch(url(action,params),{credentials:'same-origin',headers:{Accept:'application/json'}});
    const data=await r.json(); if(!r.ok || !data.success) throw Object.assign(new Error(data.message||'เกิดข้อผิดพลาด'),{response:data,status:r.status});
    return data;
  }
  async function post(action,body) {
    const r=await fetch(url(action),{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-Token':cfg.csrf},body:JSON.stringify(body)});
    const data=await r.json(); if(!r.ok || !data.success) throw Object.assign(new Error(data.message||'เกิดข้อผิดพลาด'),{response:data,status:r.status});
    return data;
  }
  function handleError(error) {
    toast(error.message||'ไม่สามารถทำรายการได้','danger');
    if(error.status===409 || error.response?.conflict) {
      showInfo('ข้อมูลมีการเปลี่ยนแปลง',`<p>ตารางถูกแก้ไขจากอุปกรณ์หรือผู้ใช้อื่น กรุณาโหลดตารางล่าสุดก่อนทำรายการต่อ</p><button type="button" class="btn btn-primary" id="reloadConflict">โหลดตารางล่าสุด</button>`);
      $('reloadConflict')?.addEventListener('click',()=>{modal('infoModal').hide();load();});
    } else if(error.response?.validation) showValidation(error.response.validation);
  }
  function enqueue(fn) {
    state.queue=state.queue.catch(()=>{}).then(fn).catch(handleError);
    return state.queue;
  }
  async function load() {
    const requested=++state.loadSeq;
    $('saveStatus').textContent='กำลังโหลดตารางเวร…';
    try {
      const data=await get('data',{hospital_id:state.hospitalId,year:state.year,month:state.month});
      if(requested!==state.loadSeq)return; // Ignore stale responses after changing hospital/month.
      state.payload=data; state.day=Math.min(state.day,dayCount()); state.validation=null;
      $('hospitalSubtitle').textContent=`${data.hospital.name} · ${thMonths[state.month-1]} พ.ศ. ${state.year+543}`;
      $('focusTitle').textContent=`${data.hospital.name} · ${thMonths[state.month-1]} ${state.year+543}`;
      $('statusBadge').textContent=data.roster?.status || 'ยังไม่มีตาราง';
      $('statusBadge').dataset.status=data.roster?.status || 'EMPTY';
      $('versionLabel').textContent=data.roster ? `Version ${data.roster.version} · Revision ${data.roster.lock_version}` : 'ยังไม่มี Version';
      $('monthSelect').value=state.month; $('yearSelect').value=state.year;
      if($('myScheduleLink')) $('myScheduleLink').href=url('my_schedule',{year:state.year,month:state.month});
      renderSummary(); renderControls(); renderReadiness(); renderMain(); renderStaff();
      $('saveStatus').textContent=data.roster?'การแก้ไขแต่ละช่องจะบันทึกทันที · ใช้ปุ่มตรวจสอบก่อนส่งอนุมัติ':'เริ่มต้นด้วยการสร้าง Draft';
    } catch(error) { if(requested!==state.loadSeq)return; handleError(error); $('saveStatus').textContent='โหลดข้อมูลไม่สำเร็จ'; }
  }
  function renderControls() {
    const d=state.payload,r=d.roster,status=r?.status;
    $('emptyRoster').hidden=!!r;
    $('createBtn').hidden=!d.permissions.can_edit;
    $('tableScreen').hidden=!r || state.view!=='table';
    $('calendarScreen').hidden=!r || state.view!=='calendar';
    $('fairnessScreen').hidden=!r || state.view!=='fairness';
    $('mobileDay').hidden=!r || state.view!=='table';
    const statuses={copyBtn:canEdit(),autoBtn:canEdit(),repairBtn:canEdit(),balanceBtn:canEdit(),validateBtn:!!r,saveBtn:canEdit(),
      submitBtn:canEdit(),approveBtn:status==='PENDING_APPROVAL'&&d.permissions.can_approve,
      rejectBtn:status==='PENDING_APPROVAL'&&d.permissions.can_approve,
      publishBtn:status==='APPROVED'&&d.permissions.can_publish,
      lockBtn:status==='PUBLISHED'&&d.permissions.can_lock,
      revisionBtn:['PUBLISHED','LOCKED'].includes(status)&&d.permissions.can_edit,
      exportBtn:!!r};
    Object.entries(statuses).forEach(([id,show])=>$(id).hidden=!show);
    $('auditBtn').hidden=!d.permissions.can_audit;
    $('historyBtn').hidden=!['SUPERADMIN','ADMIN','SCHEDULER','DIRECTOR'].includes(cfg.role) || !r;
    $('focusSave').hidden=!canEdit();
    $('settingsBtn').hidden=!d.permissions.can_settings;
    $('submitBtn').hidden=!(status==='DRAFT'&&d.permissions.can_submit);
    ['submitBtn','approveBtn','publishBtn'].forEach(id=>{ $(id).disabled=!d.readiness?.ready; $(id).title=d.readiness?.ready?'':'ต้องผ่านการตรวจความพร้อมและไม่มี Critical Error ก่อน'; });
  }
  function renderReadiness() {
    const r=state.payload?.readiness;
    const el=$('readinessPanel');
    if (!r) { el.textContent=''; return; }
    const closed=['PUBLISHED','LOCKED'].includes(r.status);
    const stateLabel=r.status==='LOCKED'?'ล็อกแล้ว · ห้ามแก้ไข':r.status==='PUBLISHED'?'เผยแพร่แล้ว':r.ready?'ผ่านเกณฑ์เบื้องต้น':'ต้องตรวจสอบ';
    const tone=closed?'info':r.ready?'ok':'attention';
    const messages=r.issues?.length?r.issues.slice(0,4).map(i=>`<li>${safe(i)}</li>`).join(''):'<li>ไม่พบปัญหาเบื้องต้นตามกฎที่ตั้งค่าไว้</li>';
    const manage=['SUPERADMIN','ADMIN'].includes(cfg.role);
    const action=roster()?'<button type="button" class="btn btn-outline-primary btn-sm" id="readinessValidate"><i class="bi bi-shield-check"></i> ดูผลตรวจสอบทั้งหมด</button>':
      manage?'<a class="btn btn-outline-primary btn-sm" href="index.php?c=admin&a=staff">จัดการบุคลากร</a>':
        '<span class="small text-secondary">โปรดติดต่อผู้ดูแลระบบเพื่อสร้างตารางเวร</span>';
    el.innerHTML=`<div class="readiness-head"><div><span class="readiness-label">WORKFLOW CHECKPOINT</span><h2 class="h6 mb-0">ความพร้อมของตารางเวร <span class="readiness-status ${tone}">${stateLabel}</span></h2></div>${action}</div>`+
      `<div class="readiness-metrics"><span>บุคลากร <b>${r.eligible_staff}</b> คน</span><span>ต้องการขั้นต่ำ <b>${r.required_per_day}</b> คน/วัน</span><span>บันทึกแล้ว <b>${r.saved_entries}</b> ช่อง</span><span>Critical <b>${r.critical}</b></span><span>Warning <b>${r.warning}</b></span></div>`+
      `<ul class="readiness-issues">${messages}</ul><p class="readiness-next"><b>ขั้นตอนถัดไป:</b> ${safe(r.next_step)}</p>`;
    $('readinessValidate')?.addEventListener('click',validateRoster);
  }
  function renderSummary() {
    const t=state.payload?.stats.totals||{};
    const cards=[['เวรทั้งหมด',state.payload?.stats.all_shifts??0,'bi-calendar2-week','neutral'],
      ['เวรเช้า',t.MORNING??0,'bi-brightness-high','blue'],['เวรบ่าย',t.AFTERNOON??0,'bi-sun','amber'],
      ['เวรดึก',t.NIGHT??0,'bi-moon-stars','purple'],['วันหยุด (OFF)',t.OFF??0,'bi-house','grey'],
      ['ชั่วโมงทำงาน',`${t.working_hours??0} ชม.`,'bi-clock-history','teal']];
    $('summaryCards').innerHTML=cards.map(c=>`<article class="summary-card summary-${c[3]}"><span class="summary-icon"><i class="bi ${c[2]}"></i></span><div><div class="summary-caption">${c[0]}</div><div class="summary-value">${c[1]}</div></div></article>`).join('');
  }
  function renderMain() {
    if(!roster()) return;
    renderTable(); renderCalendar(); renderFairness(); renderMobile();
  }
  function renderTable() {
    const p=state.payload,days=dayCount(); const holidays=new Map(p.holidays.map(h=>[h.holiday_date,h.name]));
    let thead='<tr><th scope="col" class="staff-col"><div>บุคลากร</div><small>ตำแหน่ง / ประเภท</small></th>';
    for(let d=1;d<=days;d++) {
      const holiday=holidays.get(iso(d));
      thead+=`<th scope="col" class="date-head ${holiday?'is-holiday':isWeekend(d)?'is-weekend':''}" title="${safe(holiday||'')}"><span>${d}</span><small>${['อา','จ','อ','พ','พฤ','ศ','ส'][new Date(`${iso(d)}T12:00:00`).getDay()]}</small>${holiday?'<i class="bi bi-star-fill day-star"></i>':''}</th>`;
    }
    thead+='<th class="total-col">รวม</th></tr>';
    $('rosterTable').querySelector('thead').innerHTML=thead;
    let body='';
    for(const s of p.staff) {
      const total=p.entries.filter(e=>Number(e.user_id)===Number(s.id) && e.code!=='OFF' && e.code!=='LEAVE').length;
      body+=`<tr><th scope="row" class="staff-col"><div class="staff-name">${safe(s.full_name)} ${s.eligible?'':'<span class="inactive-pill">นอก Staff Pool</span>'}</div><div class="staff-meta">${safe(s.position)} · ${safe(s.personnel_type)}</div></th>`;
      for(let d=1;d<=days;d++) {
        const date=iso(d), e=entry(s.id,date),editable=canEdit()&&(!!s.eligible||!!e),klass=e?`type-${safe(e.code.toLowerCase())}`:'';
        body+=`<td class="shift-cell ${klass} ${isWeekend(d)?'weekend-cell':''}" data-user="${s.id}" data-date="${date}" tabindex="${editable?0:-1}" role="button" aria-label="${safe(s.full_name)} ${fmtDate(date)} ${e?safe(e.shift_name):'ยังไม่จัดเวร'}" aria-disabled="${!editable}" draggable="${editable&&!!e}">${e?`<span class="shift-chip">${safe(e.short_name)}</span>`:'<span class="empty-cell">+</span>'}</td>`;
      }
      body+=`<td class="total-col fw-semibold">${total}</td></tr>`;
    }
    $('rosterTable').querySelector('tbody').innerHTML=body||`<tr><td colspan="${days+2}" class="text-center p-5">ยังไม่มีบุคลากรที่สามารถจัดเวร</td></tr>`;
    let foot='<tr><th class="staff-col">รวมเวรที่ทำงาน</th>';
    for(let d=1;d<=days;d++) foot+=`<td>${p.entries.filter(e=>e.work_date===iso(d)&&e.is_working==1).length}</td>`;
    $('rosterTable').querySelector('tfoot').innerHTML=foot+'<td class="total-col">—</td></tr>';
  }
  function renderCalendar() {
    const p=state.payload,holidays=new Map(p.holidays.map(x=>[x.holiday_date,x.name]));
    const codes=['MORNING','AFTERNOON','NIGHT']; const names={MORNING:'เช้า',AFTERNOON:'บ่าย',NIGHT:'ดึก'};
    let html=`<div class="d-flex justify-content-end mb-2"><button type="button" class="btn btn-sm ${state.mine?'btn-primary':'btn-outline-primary'}" id="myCalendar">${state.mine?'แสดงเวรทุกคน':'แสดงเวรของฉัน'}</button></div><div class="calendar-grid">`;
    for(let d=1;d<=dayCount();d++) {
      const date=iso(d), dayEntries=p.entries.filter(e=>e.work_date===date&&(!state.mine||Number(e.user_id)===Number(cfg.userId)));
      html+=`<article class="calendar-day"><div class="calendar-day-title"><b>${fmtDate(date)}</b>${holidays.has(date)?`<small class="holiday-label">${safe(holidays.get(date))}</small>`:''}</div>`;
      for(const code of codes) {
        const list=dayEntries.filter(e=>e.code===code);
        html+=`<div class="cal-shift"><span class="cal-code type-${code.toLowerCase()}">${names[code]}</span><div>${list.length?list.map(e=>`<div>${safe(personById(e.user_id)?.full_name||'—')}</div>`).join(''):'<span class="text-secondary">—</span>'}</div></div>`;
      }
      html+='</article>';
    }
    $('calendarScreen').innerHTML=html+'</div>';
    $('myCalendar')?.addEventListener('click',()=>{state.mine=!state.mine;renderCalendar();});
  }
  function renderFairness() {
    const info=state.payload?.fairness||{},m=info.metrics||{};
    const people=state.payload.stats.per_person.filter(s=>personById(s.user_id)?.eligible);
    const sorted=[...people].sort((a,b)=>Number(b.working_hours)-Number(a.working_hours));
    const maxNight=Math.max(0,...people.map(s=>Number(s.NIGHT)));
    const summary=[['เวรทำงาน',m.work],['เวรดึก',m.night],['เสาร์–อาทิตย์',m.weekend],['วันหยุดราชการ',m.holiday]].map(([name,x])=>
      `<div class="fairness-summary"><small>${name}</small><strong>${x?.min??0}–${x?.max??0}</strong><span>ต่างกัน ${x?.gap??0}</span></div>`).join('');
    const skillRuleCount=Number(state.payload?.skill_requirements_count||0);
    const skillNote=skillRuleCount>0?`<p class="small text-secondary">มีเกณฑ์ทักษะที่ตั้งไว้ ${skillRuleCount} ข้อสำหรับ รพ.สต. นี้</p>`:
      '<p class="alert alert-warning py-2">ยังไม่ได้กำหนดเกณฑ์คุณสมบัติทักษะสำหรับหน่วยบริการนี้ คะแนนความเป็นธรรมไม่ใช่การรับรองว่าเวรมีผู้ปฏิบัติงานที่มีคุณสมบัติครบ</p>';
    $('fairnessScreen').innerHTML=skillNote+`<div class="fairness-summary-grid">${summary}</div>`+
      `${info.needs_review?'<p class="alert alert-warning py-2">จำนวนเวรหรือเวรดึกต่างกันค่อนข้างมาก ควรตรวจทักษะและเงื่อนไขของแต่ละคนก่อนปรับสมดุล</p>':''}`+
      `<div class="table-responsive"><table class="table table-hover align-middle fairness-table"><thead><tr><th>บุคลากร</th><th>เวรทำงาน</th><th>เช้า</th><th>บ่าย</th><th>ดึก</th><th>เสาร์–อาทิตย์</th><th>นักขัตฤกษ์</th><th>ชั่วโมง</th></tr></thead><tbody>${sorted.map(s=>`<tr><th>${safe(s.full_name)}</th><td><strong>${Number(s.MORNING)+Number(s.AFTERNOON)+Number(s.NIGHT)}</strong></td><td>${s.MORNING}</td><td>${s.AFTERNOON}</td><td><span class="fairness-bar" style="--bar:${Math.max(5,maxNight?100*Number(s.NIGHT)/maxNight:0)}%">${s.NIGHT}</span></td><td>${s.weekend_shifts}</td><td>${s.holiday_shifts}</td><td>${s.working_hours}</td></tr>`).join('')}</tbody></table></div>`+
      `<p class="small text-secondary">${safe(info.note||'ข้อมูลประกอบการตัดสินใจเท่านั้น')}</p>`;
  }
  function renderStaff() {
    const staff=state.payload.staff.filter(s=>s.eligible);
    $('staffCount').textContent=`${staff.length} คน (ไม่รวม SUPERADMIN / ADMIN / HR)`;
    const makeFilter=(id,key,label)=> {
      const values=[...new Set(staff.map(s=>s[key]).filter(Boolean))].sort((a,b)=>a.localeCompare(b,'th'));
      const select=$(id),selected=select.value;
      select.innerHTML=`<option value="">${label}</option>${values.map(s=>`<option value="${safe(s)}">${safe(s)}</option>`).join('')}`;
      select.value=values.includes(selected)?selected:'';
    };
    makeFilter('staffPosition','position','ทุกตำแหน่ง'); makeFilter('staffType','personnel_type','ทุกประเภท');
    filterStaff();
  }
  function filterStaff() {
    if(!state.payload) return;
    const q=$('staffSearch').value.toLocaleLowerCase('th'), pos=$('staffPosition').value,kind=$('staffType').value;
    const people=state.payload.staff.filter(s=>s.eligible&&s.full_name.toLocaleLowerCase('th').includes(q)&&(!pos||s.position===pos)&&(!kind||s.personnel_type===kind));
    const per=new Map(state.payload.stats.per_person.map(s=>[Number(s.user_id),s]));
    $('staffList').innerHTML=people.map(s=>{
      const count=per.get(Number(s.id))||{};
      const photo=typeof s.photo_url==='string' && /^https:\/\//i.test(s.photo_url) ? `<img src="${safe(s.photo_url)}" alt="" referrerpolicy="no-referrer" loading="lazy">` : safe(s.full_name.trim().slice(0,1));
      return `<div class="staff-card" tabindex="0" draggable="${canEdit()}" data-staff-id="${s.id}" role="listitem"><div class="avatar">${photo}</div><div class="staff-card-info"><strong>${safe(s.full_name)}</strong><small>${safe(s.position)}</small><small>${safe(s.personnel_type)}</small></div><div class="staff-count">${Number(count.MORNING||0)+Number(count.AFTERNOON||0)+Number(count.NIGHT||0)}<small>เวร</small></div></div>`;
    }).join('')||'<p class="text-secondary text-center p-3">ไม่พบบุคลากร</p>';
  }
  function renderMobile() {
    const date=iso(); $('mobileDate').textContent=fmtDate(date);
    const codes=['MORNING','AFTERNOON','NIGHT','OFF','LEAVE'];
    const names={MORNING:'เวรเช้า',AFTERNOON:'เวรบ่าย',NIGHT:'เวรดึก',OFF:'วันหยุด',LEAVE:'ลา'};
    const p=state.payload;
    $('mobileShiftCards').innerHTML=codes.map(code=>{
      const people=p.entries.filter(e=>e.work_date===date&&e.code===code);
      const assignedIds=new Set(p.entries.filter(e=>e.work_date===date).map(e=>Number(e.user_id)));
      const choices=p.staff.filter(s=>s.eligible&&!assignedIds.has(Number(s.id)));
      const add=canEdit()&&['MORNING','AFTERNOON','NIGHT'].includes(code) ? `<div class="mobile-add"><select class="form-select form-select-sm" aria-label="เพิ่มผู้ปฏิบัติงาน ${names[code]}" data-add-date="${date}" data-add-shift="${code}"><option value="">+ เพิ่มบุคลากรในเวรนี้</option>${choices.map(s=>`<option value="${s.id}">${safe(s.full_name)}</option>`).join('')}</select></div>`:'';
      return `<article class="mobile-shift-card"><div class="mobile-shift-title type-${code.toLowerCase()}">${names[code]} <span>${people.length} คน</span></div>${people.length?people.map(e=>`<button type="button" class="mobile-person" data-user="${e.user_id}" data-date="${date}" ${canEdit()?'':'disabled'}>${safe(personById(e.user_id)?.full_name||'—')} <i class="bi bi-pencil-square"></i></button>`).join(''):'<div class="text-secondary small p-3">ไม่มีรายชื่อ</div>'}${add}</article>`;
    }).join('');
  }
  function openPicker(userId,date,preselect=null) {
    if(!canEdit()) return;
    const person=personById(userId); if(!person||!person.eligible) {toast('ไม่สามารถจัดเวรให้บุคลากรนี้','danger');return;}
    state.modalCell={userId:Number(userId),date};
    const old=entry(userId,date);
    $('shiftModalTitle').textContent=`${person.full_name} · ${fmtDate(date)}`;
    $('shiftNote').value=old?.note||'';
    $('shiftPicker').innerHTML=state.payload.shifts.map(s=>`<button type="button" class="shift-option type-${safe(s.code.toLowerCase())} ${Number(preselect??old?.shift_type_id)===Number(s.id)?'selected':''}" data-shift-id="${s.id}" aria-label="เลือกเวร${safe(s.name)}"><span>${safe(s.short_name)}</span><b>${safe(s.name)}</b><small>${s.start_time?`${safe(s.start_time.slice(0,5))}–${safe(s.end_time.slice(0,5))}`:'ไม่มีช่วงเวลา'}</small></button>`).join('');
    modal('shiftModal').show();
  }
  function saveCell(userId,date,shiftId,note='') {
    if(!canEdit()) return Promise.resolve();
    $('saveStatus').textContent='กำลังบันทึกช่องที่แก้ไข…';
    return enqueue(async()=> {
      const r=roster();
      const result=await post('save_shift',{roster_id:r.id,expected_revision:r.lock_version,user_id:Number(userId),date,shift_type_id:shiftId,note});
      r.lock_version=result.revision; $('saveStatus').textContent='บันทึกแล้ว · '+new Date().toLocaleTimeString('th-TH');
      await load();
      toast(result.message,'success');
    });
  }
  function saveMany(changes) {
    return enqueue(async()=> {
      const result=await post('bulk_save',{roster_id:roster().id,expected_revision:roster().lock_version,changes});
      toast(`บันทึก ${result.saved} ช่องแล้ว`,'success'); await load();
    });
  }
  function showInfo(title,html) { $('infoTitle').textContent=title; $('infoBody').innerHTML=html; modal('infoModal').show(); }
  function showValidation(result) {
    if(!result) return;
    const items=result.items||[];
    showInfo('ผลการตรวจสอบตารางเวร',`<div class="validation-counts"><span class="sev-critical">Critical ${result.counts?.CRITICAL||0}</span><span class="sev-warning">Warning ${result.counts?.WARNING||0}</span><span class="sev-info">Info ${result.counts?.INFO||0}</span></div>${items.length?`<div class="validation-list">${items.slice(0,250).map(v=>`<div class="validation-item"><strong class="sev-${v.severity.toLowerCase()}">${safe(v.severity)}</strong><div><b>${safe(v.work_date?fmtDate(v.work_date):'ตลอดเดือน')}</b><p>${safe(v.message)}</p></div></div>`).join('')}${items.length>250?`<p>รายการเพิ่มเติม ${items.length-250} รายการ</p>`:''}</div>`:'<p class="text-success"><i class="bi bi-check-circle"></i> ไม่พบปัญหาตามกฎที่กำหนด</p>'}`);
  }
  async function validateRoster() {
    if(!roster())return;
    try { const result=await get('validate',{roster_id:roster().id});state.validation=result.validation;showValidation(result.validation); }
    catch(error){handleError(error);}
  }
  async function transition(action) {
    if(!roster()) return;
    const labels={submit:'ส่งอนุมัติ',approve:'อนุมัติ',reject:'ส่งคืนให้ผู้จัดเวร',publish:'เผยแพร่',lock:'ล็อกตาราง'};
    if(!confirm(`ยืนยันการ${labels[action]}ตารางปฏิบัติงาน?`))return;
    let reason='';
    if(action==='reject') {reason=prompt('เหตุผลที่ส่งคืนให้แก้ไข')||''; if(reason.trim().length<3)return;}
    enqueue(async()=>{const result=await post(action,{roster_id:roster().id,expected_revision:roster().lock_version,reason});toast(result.message,'success');await load();});
  }
  async function previewAuto() {
    if(!canEdit()) return;
    enqueue(async()=> {
      const body={roster_id:roster().id,expected_revision:roster().lock_version};
      const p=(await post('auto_preview',body)).plan;
      const v=p.preview||{};
      const totals=v.totals||{};
      const shiftNames={MORNING:'เช้า',AFTERNOON:'บ่าย',NIGHT:'ดึก',OFF:'OFF',LEAVE:'ลา'};
      const totalRows=['MORNING','AFTERNOON','NIGHT','OFF','LEAVE']
        .map(code=>`<tr><th>${shiftNames[code]}</th><td class="text-end">${Number(totals[code]||0)}</td></tr>`).join('');
      const people=(v.per_person||[]).map(s=>`<tr><td>${safe(s.name)}</td><td class="text-end">${Number(s.MORNING||0)}</td><td class="text-end">${Number(s.AFTERNOON||0)}</td><td class="text-end">${Number(s.NIGHT||0)}</td><td class="text-end">${Number(s.OFF||0)}</td><td class="text-end fw-semibold">${Number(s.working||0)}</td></tr>`).join('');
      const shortages=(p.unfilled||[]).slice(0,80).map(s=>`<li>${safe(fmtDate(s.date))}: เวร${safe(shiftNames[s.shift]||s.shift)} — ${safe(s.message)}</li>`).join('');
      const restIssues=v.rest_issues||[];
      const restRows=restIssues.slice(0,30).map(s=>`<li>บุคลากร #${Number(s.user_id)} · ${safe(fmtDate(s.date))}: ${safe(s.message)}</li>`).join('');
      const isIncomplete=(p.unfilled||[]).length>0 || restIssues.length>0;
      const severity=isIncomplete?'warning':'success';
      const coverage=(p.unfilled||[]).length>0?`พบช่วงเวรที่ยังจัดไม่ครบ ${(p.unfilled||[]).length} ช่วง ต้องตรวจสอบและเติมคนก่อนส่งอนุมัติ`:'จัดครบตามจำนวนขั้นต่ำที่ตั้งไว้ในแต่ละวัน (ยังต้องตรวจ Validation หลังบันทึก)';
      showInfo('ตัวอย่างจัดเวรอัตโนมัติ — ตรวจสอบก่อนบันทึก',
        `<div class="alert alert-info mb-3"><i class="bi bi-eye"></i> <b>Preview เท่านั้น:</b> ขั้นตอนนี้ยังไม่เขียนข้อมูลลงฐานข้อมูล และไม่เปลี่ยนเวรที่บันทึกไว้เดิม</div>`+
        `<div class="alert alert-${severity}" role="status">${safe(coverage)}<div class="small mt-1">พักขั้นต่ำระหว่างเวร ${Number(v.minimum_rest_hours??11)} ชั่วโมง (รวมเวรข้ามเที่ยงคืน)</div></div>`+
        `<div class="row g-3 mb-3"><div class="col-sm-6"><div class="card h-100"><div class="card-body"><h3 class="h6">จำนวนเวรหลังจัด (รวมเวรเดิม)</h3><table class="table table-sm mb-0"><tbody>${totalRows}</tbody></table></div></div></div>`+
        `<div class="col-sm-6"><div class="card h-100"><div class="card-body"><h3 class="h6">ภาพรวม</h3><p>บุคลากร <b>${Number(v.staff_count||0)}</b> คน · ${Number(v.days||0)} วัน</p><p>เวรทำงานใหม่ <b>${Number(p.working_assigned||0)}</b> ช่อง · OFF ใหม่และรายการอื่นรวม <b>${Number(p.total_changes||0)}</b> ช่อง</p><p>เวรทำงานต่อคน: <b>${Number(v.working_range?.min||0)}–${Number(v.working_range?.max||0)}</b> ครั้ง</p><p class="mb-0">เวรดึกต่อคน: <b>${Number(v.night_range?.min||0)}–${Number(v.night_range?.max||0)}</b> ครั้ง</p></div></div></div></div>`+
        `<h3 class="h6">การกระจายเวรรายบุคคล</h3><div class="table-responsive mb-3" style="max-height:260px;overflow:auto"><table class="table table-sm table-striped align-middle"><thead><tr><th>ชื่อ</th><th class="text-end">เช้า</th><th class="text-end">บ่าย</th><th class="text-end">ดึก</th><th class="text-end">OFF</th><th class="text-end">รวมงาน</th></tr></thead><tbody>${people||'<tr><td colspan="6">ยังไม่มีบุคลากรที่จัดเวรได้</td></tr>'}</tbody></table></div>`+
        (p.unfilled?.length?`<details class="mb-3"><summary class="fw-semibold">ดูวันที่/เวรที่ยังจัดไม่ครบ (${p.unfilled.length})</summary><ul class="small mt-2">${shortages}</ul>${p.unfilled.length>80?`<p>แสดง 80 รายการแรกจาก ${p.unfilled.length}</p>`:''}</details>`:'')+
        (restIssues.length?`<div class="alert alert-danger"><b>พบช่วงพักไม่ผ่านเกณฑ์ ${restIssues.length} รายการ</b> (เวรเดิมจะไม่ถูกเปลี่ยนอัตโนมัติ)</div><details class="mb-3"><summary>แสดงช่วงพักที่ต้องแก้ไข</summary><ul class="small mt-2">${restRows}</ul></details>`:'')+
        `<p class="small text-secondary">ระบบจะไม่ทับเวรเดิม การจัดอัตโนมัติเป็นข้อเสนอเบื้องต้น ไม่แทนการตรวจสอบวันลา ข้อจำกัดส่วนบุคคล และการอนุมัติของผู้รับผิดชอบ</p>`+
        `<button type="button" class="btn btn-primary" id="applyAutoPlan" ${p.total_changes===0?'disabled':''}><i class="bi bi-check2-circle"></i> ยืนยันบันทึกเวรตาม Preview</button>`);
      $('applyAutoPlan')?.addEventListener('click',()=>{
        if(isIncomplete && !confirm(`ยังมี ${(p.unfilled||[]).length} ช่วงเวรไม่ครบ และ ${restIssues.length} ช่วงพักไม่ผ่านเกณฑ์ ต้องแก้ไขก่อนส่งอนุมัติ\nยืนยันบันทึกเฉพาะช่องที่ระบบจัดได้หรือไม่?`))return;
        modal('infoModal').hide();
        enqueue(async()=>{
          const result=await post('auto_apply',body);
          toast(`บันทึกแล้ว ${Number(result.saved||0)} ช่อง · ช่วงเวรที่ยังขาด ${(result.unfilled||[]).length} ช่วง`,'success');
          await load();
        });
      },{once:true});
    });
  }
  function previewRepair() {
    if(!canEdit())return;
    enqueue(async()=>{
      const body={roster_id:roster().id,expected_revision:roster().lock_version};
      const p=(await post('repair_preview',body)).plan;
      const b=p.before||{}, a=p.after||{};
      const changes=(p.swaps||[]).map(x=>`<tr><td>${safe(fmtDate(x.date))}</td><td>${safe(x.first.name)}: ${safe(x.first.from_name)} → ${safe(x.first.to_name)}</td><td>${safe(x.second.name)}: ${safe(x.second.from_name)} → ${safe(x.second.to_name)}</td></tr>`).join('');
      const unresolved=(p.remaining||[]).slice(0,25).map(i=>`<li>${safe(i.work_date?fmtDate(i.work_date):'ทั้งเดือน')} · ${safe(i.message)}</li>`).join('');
      showInfo('V9 · เสนอซ่อมตารางเวร (Preview)',
        `<div class="alert alert-info">ยังไม่มีการแก้ไขฐานข้อมูล · เสนอเฉพาะการสลับเวรในวันเดียวกัน ไม่เปลี่ยนวันลา/หมายเหตุและไม่แก้ Version ที่ล็อกไว้</div>`+
        `<div class="row g-2 mb-3"><div class="col-6"><div class="border rounded p-3"><div class="text-secondary small">ก่อนซ่อม</div><b class="text-danger">Critical ${Number(b.CRITICAL||0)}</b> · Warning ${Number(b.WARNING||0)}</div></div><div class="col-6"><div class="border rounded p-3"><div class="text-secondary small">หลังซ่อม (จำลอง)</div><b class="${Number(a.CRITICAL||0)===0?'text-success':'text-danger'}">Critical ${Number(a.CRITICAL||0)}</b> · Warning ${Number(a.WARNING||0)}</div></div></div>`+
        `<p><b>เสนอการสลับ ${p.swaps.length} คู่ (${p.changes.length} ช่อง)</b> · จำกัดการประเมิน ${Number(p.evaluations||0)} แบบ</p>`+
        (changes?`<div class="table-responsive mb-3" style="max-height:320px"><table class="table table-sm"><thead><tr><th>วันที่</th><th>บุคลากรคนที่ 1</th><th>บุคลากรคนที่ 2</th></tr></thead><tbody>${changes}</tbody></table></div>`:'<div class="alert alert-warning">ไม่พบคู่สลับที่ลดข้อผิดพลาดได้โดยไม่เพิ่ม Critical/Warning</div>')+
        (unresolved?`<details class="mb-3"><summary>Critical ที่ยังเหลือ ${p.remaining.length} รายการ</summary><ul class="small mt-2">${unresolved}</ul></details>`:'<div class="text-success mb-3">ผ่านกฎที่ตรวจสอบทั้งหมดในผลจำลอง</div>')+
        `<p class="small text-secondary">จำกัดการแก้ให้ใช้ข้อมูลเวรที่มีอยู่ ไม่แตะเวรที่มีหมายเหตุหรือ LEAVE; อาจยังต้องแก้ไขด้วยมือและตรวจคุณสมบัติผู้ปฏิบัติงาน หากไม่มีข้อมูลรับรองทักษะ/ความพร้อมในระบบ การซ่อมไม่สามารถยืนยันคุณสมบัติเหล่านั้นได้</p>`+
        `<button type="button" id="applyRepairPlan" class="btn btn-primary" ${p.changes.length===0?'disabled':''}><i class="bi bi-check-circle"></i> ยืนยันบันทึก ${p.changes.length} ช่อง</button>`);
      $('applyRepairPlan')?.addEventListener('click',()=>{
        if(!confirm(`ยืนยันการสลับ ${p.swaps.length} คู่? Critical คาดว่าจะลดจาก ${b.CRITICAL} เป็น ${a.CRITICAL} และยังต้องตรวจสอบหลังบันทึก`))return;
        modal('infoModal').hide();
        enqueue(async()=>{
          const answer=await post('repair_apply',{...body,preview_hash:p.preview_hash});
          toast(`ซ่อม ${answer.saved} ช่อง · Critical คงเหลือ ${answer.remaining_critical}`,'success');
          await load();
        });
      },{once:true});
    });
  }
  function previewBalance() {
    if(!canEdit())return;
    enqueue(async()=>{
      const body={roster_id:roster().id,expected_revision:roster().lock_version};
      const p=(await post('fairness_preview',body)).plan;
      const changes=(p.swaps||[]).map(x=>`<tr><td>${safe(fmtDate(x.date))}</td><td>${safe(x.first.name)}: ${safe(x.first.from)} → ${safe(x.first.to)}</td><td>${safe(x.second.name)}: ${safe(x.second.from)} → ${safe(x.second.to)}</td></tr>`).join('');
      showInfo('V10 · ปรับสมดุลเวร (Preview)',
        `<div class="alert alert-info">ยังไม่เขียนข้อมูลลง MySQL การสลับเป็นภายในวันเดียวกันและต้องผ่านกฎเวลาพัก จำนวนคน และทักษะที่ตั้งไว้</div>`+
        `<div class="row g-2 mb-3"><div class="col-sm-6"><div class="border rounded p-3">จำนวนเวรต่างกันก่อน: <b>${Number(p.work_gap_before)}</b></div></div><div class="col-sm-6"><div class="border rounded p-3">หลัง (จำลอง): <b>${Number(p.work_gap_after)}</b></div></div></div>`+
        `<p>เสนอ ${p.swaps.length} คู่ · เปลี่ยนจริง ${p.changes.length} ช่อง · Critical ${Number(p.after.CRITICAL)} · Warning ${Number(p.after.WARNING)}</p>`+
        (changes?`<div class="table-responsive mb-3" style="max-height:290px;overflow:auto"><table class="table table-sm"><thead><tr><th>วันที่</th><th>บุคลากรคนที่ 1</th><th>บุคลากรคนที่ 2</th></tr></thead><tbody>${changes}</tbody></table></div>`:'<div class="alert alert-warning">ไม่พบการสลับที่ปรับสมดุลได้โดยไม่สร้างข้อผิดพลาด โปรดพิจารณาปรับด้วยตนเอง</div>')+
        `<p class="small text-secondary">${safe(p.note)}</p>`+
        `<button type="button" class="btn btn-primary" id="applyBalance" ${p.changes.length===0?'disabled':''}>ยืนยันบันทึก ${p.changes.length} ช่อง</button>`);
      $('applyBalance')?.addEventListener('click',()=>{
        if(!confirm(`ยืนยันปรับสมดุล ${p.swaps.length} คู่ โดยต้องตรวจทักษะและภาระงานรายคนก่อน?`))return;
        modal('infoModal').hide();enqueue(async()=>{const out=await post('fairness_apply',{...body,preview_hash:p.preview_hash});
          toast(`ปรับสมดุล ${out.saved} ช่องแล้ว กรุณาตรวจสอบตารางอีกครั้ง`,'success');await load();});
      },{once:true});
    });
  }
  function previewCopy() {
    if(!canEdit()) return;
    enqueue(async()=>{
      const body={roster_id:roster().id,expected_revision:roster().lock_version};
      const p=(await post('copy_preview',body)).preview;
      const msg=`คัดลอกจาก Version ${p.source_version} (${p.source_status})\nเพิ่ม ${p.to_copy} ช่อง\nพ้นสภาพ/ย้ายหน่วย ${p.inactive_count} คน\nวันที่ไม่มีในเดือนปลายทาง ${p.invalid_days} ช่อง\nช่องเดิมไม่ถูกทับ ${p.existing_skipped} ช่อง`;
      if(!confirm(`${msg}\n\nยืนยันคัดลอก?`))return;
      const result=await post('copy_apply',body);toast(result.message,'success');await load();
    });
  }
  function showContext(clientX,clientY,cell) {
    if(!canEdit()) return;
    state.lastCell=cell;
    const e=entry(cell.userId,cell.date);
    const actions=[['pick','เปลี่ยนเวร'],['clear','ล้างเวร'],['copy','คัดลอก'],['paste','วาง'],['details','รายละเอียด']];
    $('contextMenu').innerHTML=actions.map(([a,title])=>`<button type="button" role="menuitem" data-context="${a}" ${a==='paste'&&!state.clipboard?'disabled':''}>${title}</button>`).join('');
    const menu=$('contextMenu'); menu.hidden=false;
    menu.style.left=`${Math.max(8,Math.min(clientX,innerWidth-190))}px`;
    menu.style.top=`${Math.max(8,Math.min(clientY,innerHeight-210))}px`;
  }
  function contextAction(action) {
    const cell=state.lastCell;if(!cell)return; $('contextMenu').hidden=true;
    const e=entry(cell.userId,cell.date);
    if(action==='pick')openPicker(cell.userId,cell.date);
    if(action==='clear')saveCell(cell.userId,cell.date,null);
    if(action==='copy'){state.clipboard=e?{shift_type_id:Number(e.shift_type_id),note:e.note}:null;toast(state.clipboard?'คัดลอกประเภทเวรแล้ว':'ไม่มีเวรให้คัดลอก');}
    if(action==='paste'&&state.clipboard)saveCell(cell.userId,cell.date,state.clipboard.shift_type_id,state.clipboard.note);
    if(action==='details')showInfo('รายละเอียดเวร',`<p><b>${safe(personById(cell.userId)?.full_name)}</b> · ${fmtDate(cell.date)}</p><p>เวร: ${safe(e?.shift_name||'ไม่ได้จัด')}</p><p>หมายเหตุ: ${safe(e?.note||'—')}</p>`);
  }
  function updateView(view) {
    state.view=view;
    [['tableTab','table'],['calendarTab','calendar'],['fairnessTab','fairness']].forEach(([id,v])=>{
      $(id).className=`btn ${v===view?'btn-primary':'btn-outline-secondary'}`;
      $(id).setAttribute('aria-pressed',String(v===view));
    });
    if(state.payload){renderControls(); renderMain();}
  }
  function changeMonth(delta) {
    const date=new Date(state.year,state.month-1+delta,1);
    state.year=date.getFullYear();state.month=date.getMonth()+1;state.day=1;
    if(![...$('yearSelect').options].some(o=>Number(o.value)===state.year)) addYear(state.year);
    load();
  }
  function addYear(year){$('yearSelect').insertAdjacentHTML('beforeend',`<option value="${year}">${year+543}</option>`);}
  function initSelectors() {
    $('monthSelect').innerHTML=thMonths.map((m,i)=>`<option value="${i+1}">${m}</option>`).join('');
    for(let y=new Date().getFullYear()-5;y<=new Date().getFullYear()+6;y++)addYear(y);
    $('monthSelect').value=state.month;$('yearSelect').value=state.year;
  }
  function openStaff() { $('rosterWorkspace').classList.toggle('staff-open'); $('drawerBackdrop').hidden=!$('rosterWorkspace').classList.contains('staff-open'); }
  function closeStaff() { $('rosterWorkspace').classList.remove('staff-open'); $('drawerBackdrop').hidden=true; }
  function updateResponsive() {const compact=$('rosterWorkspace').clientWidth<1180;$('rosterWorkspace').classList.toggle('compact-panel',compact);if(!compact)closeStaff();}
  function toggleFullscreen() {
    const elem=$('rosterWorkspace');
    if(document.fullscreenElement) document.exitFullscreen().catch(()=>toast('ไม่สามารถออกจากเต็มจอ','danger'));
    else elem.requestFullscreen?.().catch(()=>toast('เบราว์เซอร์ไม่อนุญาตให้เปิดเต็มจอ','danger'));
  }
  function openSettings() {
    const c=state.payload.settings;
    const fields=[['min_morning','ขั้นต่ำเวรเช้า',0,100],['min_afternoon','ขั้นต่ำเวรบ่าย',0,100],
      ['min_night','ขั้นต่ำเวรดึก',0,100],['max_consecutive_work_days','วันทำงานติดต่อกันสูงสุด',1,31],
      ['max_night_shifts','เวรดึกต่อเดือนสูงสุด',0,31],
      ['min_rest_hours','พักขั้นต่ำระหว่างเวร (ชั่วโมง)',8,24]];
    showInfo('ตั้งค่าเกณฑ์ตารางเวร · '+state.payload.hospital.name,
      `<form id="settingsForm"><div class="row g-3">${fields.map(([key,label,min,max])=>`<div class="col-md-6"><label class="form-label" for="setting_${key}">${label}</label><input type="number" class="form-control" id="setting_${key}" name="${key}" min="${min}" max="${max}" value="${Number(c[key])}" required></div>`).join('')}</div><p class="small text-secondary mt-3">เกณฑ์มีผลต่อการตรวจสอบและจัดเวรอัตโนมัติ โปรดตรวจสอบความเหมาะสมของอัตรากำลังจริง</p><button type="submit" class="btn btn-primary">บันทึกเกณฑ์</button></form>`);
    $('settingsForm').addEventListener('submit',event=>{
      event.preventDefault();const form=event.currentTarget;const values=Object.fromEntries(new FormData(form).entries());
      enqueue(async()=>{const response=await post('settings_save',{hospital_id:state.hospitalId,...values});
        modal('infoModal').hide();toast(response.message,'success');await load();});
    });
  }
  function v6VersionOption(v) {
    return `<option value="${Number(v.id)}">Version ${Number(v.version)} · ${safe(v.status)} · ${Number(v.entry_count)} ช่อง</option>`;
  }
  function v6ChangedLabel(shift) {
    if(!shift) return '<span class="text-secondary">ไม่มีเวร</span>';
    return `<b>${safe(shift.shift_name||shift.shift_code)}</b>${shift.note?` <span class="text-secondary">(${safe(shift.note)})</span>`:''}`;
  }
  function v7AuditRow(event) {
    const d=event.display||{};
    const original=event.old_value||'null', updated=event.new_value||'null';
    const detail=`<div class="v7-audit-details"><div class="v7-change"><div><small>ก่อนแก้ไข</small><strong>${safe(d.before||'—')}</strong>${d.before_note?`<span class="v7-note">${safe(d.before_note)}</span>`:''}</div><span class="v7-arrow" aria-hidden="true">→</span><div><small>หลังแก้ไข</small><strong>${safe(d.after||'—')}</strong>${d.after_note?`<span class="v7-note">${safe(d.after_note)}</span>`:''}</div></div>${d.reason?`<p class="v7-reason"><b>เหตุผล:</b> ${safe(d.reason)}</p>`:''}<details class="v7-raw-audit"><summary>ข้อมูลต้นฉบับ (JSON) สำหรับผู้ดูแล</summary><div class="v7-json-pair"><div><b>ก่อนแก้</b><pre class="v6-audit-json">${safe(original)}</pre></div><div><b>หลังแก้</b><pre class="v6-audit-json">${safe(updated)}</pre></div></div></details></div>`;
    return `<article class="v7-audit-card"><div class="v7-audit-heading"><div><strong>${safe(d.summary||event.action)}</strong><span class="v7-audit-meta">${safe(event.actor_name)} · ${safe(event.created_at)}</span></div><span class="v7-audit-action">${safe(event.action)}</span></div>${d.staff_name?`<div class="v7-audit-staff">${safe(d.staff_name)}</div>`:''}${event.work_date?`<div class="v7-audit-date">วันที่จัดเวร ${safe(fmtDate(event.work_date))}</div>`:''}<details class="v7-event"><summary aria-label="ดูค่าก่อนและหลังของ ${safe(d.summary||event.action)}">ดูการเปลี่ยนแปลง</summary>${detail}</details></article>`;
  }
  async function showHistory() {
    if(!roster())return;
    try {
      const result=await get('version_history',{hospital_id:state.hospitalId,year:state.year,month:state.month});
      const versions=result.versions||[];
      if(!versions.length){showInfo('ประวัติ Version','<p>ยังไม่มีตารางสำหรับเดือนนี้</p>');return;}
      const rows=versions.map(v=>`<tr><td><b>Version ${Number(v.version)}</b><div class="small text-secondary">Rev. ${Number(v.lock_version)}</div></td><td>${safe(v.status)}</td><td>${safe(v.creator_name)}<div class="small text-secondary">${safe(v.created_at)}</div></td><td>${safe(v.reason||'ฉบับแรก')}</td><td>${Number(v.entry_count)}</td></tr>`).join('');
      const oldest=versions[versions.length-1].id,newest=versions[0].id;
      const options=versions.map(v6VersionOption).join('');
      showInfo('ประวัติ Version · '+state.payload.hospital.name,
        `<p class="small text-secondary">${safe(thMonths[state.month-1])} ${state.year+543} — แสดงทุกฉบับรวม Draft เฉพาะผู้มีสิทธิ์จัดการ (STAFF ไม่สามารถเปิดหน้านี้)</p>`+
        `<div class="table-responsive v6-history-table"><table class="table table-sm align-middle"><thead><tr><th>Version</th><th>สถานะ</th><th>ผู้สร้าง/วันที่</th><th>เหตุผล</th><th>ช่องเวร</th></tr></thead><tbody>${rows}</tbody></table></div>`+
        `<h3 class="h6 mt-3">เปรียบเทียบ 2 Version</h3><div class="row g-2 align-items-end mb-3"><div class="col-sm-5"><label for="v6From" class="form-label">ก่อนแก้ไข</label><select id="v6From" class="form-select">${options}</select></div><div class="col-sm-5"><label for="v6To" class="form-label">หลังแก้ไข</label><select id="v6To" class="form-select">${options}</select></div><div class="col-sm-2"><button type="button" class="btn btn-primary w-100" id="v6Compare">เปรียบเทียบ</button></div></div>`+
        `<div class="d-flex flex-wrap gap-2 align-items-center mb-2"><label for="v6AuditVersion" class="form-label mb-0">Audit Log:</label><select id="v6AuditVersion" class="form-select form-select-sm" style="max-width:230px">${options}</select><button id="v6AuditShow" class="btn btn-outline-secondary btn-sm" type="button">ดูประวัติ</button></div><div id="v6Results" role="status" aria-live="polite"></div>`);
      $('v6From').value=String(oldest);$('v6To').value=String(newest);$('v6AuditVersion').value=String(newest);
      $('v6Compare').addEventListener('click',async()=>{
        const out=$('v6Results');out.textContent='กำลังเปรียบเทียบ…';
        try {
          const d=await get('version_compare',{from_id:$('v6From').value,to_id:$('v6To').value});
          const summary=d.diff.summary;
          const list=d.diff.changes||[];
          const rows=list.slice(0,200).map(x=>`<tr><td>${safe(fmtDate(x.date))}</td><td>${safe(x.staff_name)}</td><td>${v6ChangedLabel(x.before)}</td><td>${v6ChangedLabel(x.after)}</td><td>${safe(x.type)}</td></tr>`).join('');
          out.innerHTML=`<div class="alert alert-info py-2">Version ${Number(d.from.version)} → ${Number(d.to.version)} · เปลี่ยน ${Number(d.diff.total)} ช่อง (เพิ่ม ${Number(summary.added)}, ลบ ${Number(summary.removed)}, แก้ ${Number(summary.changed)})</div>`+
            (list.length?`<div class="table-responsive v6-diff-table"><table class="table table-sm table-striped align-middle"><thead><tr><th>วันที่</th><th>บุคลากร</th><th>ก่อน</th><th>หลัง</th><th>ประเภท</th></tr></thead><tbody>${rows}</tbody></table></div>${list.length>200?`<p class="small text-secondary">แสดง 200 จาก ${list.length} รายการ</p>`:''}`:'<p class="text-success"><i class="bi bi-check-circle"></i> ไม่มีรายการเวรเปลี่ยนแปลงระหว่างสอง Version</p>');
        }catch(error){out.textContent=error.message||'ตรวจสอบไม่สำเร็จ';}
      });
      $('v6AuditShow').addEventListener('click',async()=>{
        const out=$('v6Results');out.textContent='กำลังโหลด Audit Log…';
        try {
          const d=await get('version_audit',{roster_id:$('v6AuditVersion').value});
          const items=d.items||[];
          out.innerHTML=`<div class="v7-audit-toolbar"><h3 class="h6 mb-0">Audit Log — Version ${Number(d.version)}</h3><span class="small text-secondary">แสดงล่าสุด ${items.length} รายการ</span></div><div class="v7-audit-list">${items.length?items.map(v7AuditRow).join(''):'<p class="text-secondary p-3">ยังไม่มีประวัติการดำเนินการ</p>'}</div>`;
        }catch(error){out.textContent=error.message||'ไม่สามารถโหลดประวัติ';}
      });
    }catch(error){handleError(error);}
  }
  async function showAudit() {
    try {const a=await get('audit',{hospital_id:state.hospitalId});
      showInfo('Audit Log (ล่าสุด 100 รายการ)',`<div class="table-responsive"><table class="table table-sm"><thead><tr><th>เวลา</th><th>ผู้ใช้</th><th>การกระทำ</th><th>วันที่เวร</th></tr></thead><tbody>${a.items.map(x=>`<tr><td>${safe(x.created_at)}</td><td>${safe(x.full_name)}</td><td>${safe(x.action)}</td><td>${safe(x.work_date||'—')}</td></tr>`).join('')}</tbody></table></div>`);
    }catch(error){handleError(error);}
  }
  function openExport() {
    if(!roster())return;
    const print=url('export_print',{roster_id:roster().id}),excel=url('export_excel',{roster_id:roster().id});
    showInfo('ส่งออกตารางปฏิบัติงาน',`<p>รูปแบบเอกสารราชการ พร้อมช่องลงนามผู้จัดทำ ผู้ตรวจสอบ และผู้อนุมัติ</p><div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-primary" href="${print}" target="_blank" rel="noopener noreferrer"><i class="bi bi-printer"></i> Print / บันทึก PDF</a><a class="btn btn-success" href="${excel}"><i class="bi bi-file-earmark-excel"></i> Excel (.xlsx)</a></div><p class="small text-secondary mt-3">การบันทึก PDF ใช้ปุ่มพิมพ์จากเบราว์เซอร์ แล้วเลือก “Save as PDF”</p>`);
  }
  function bindEvents() {
    $('monthSelect').addEventListener('change',e=>{state.month=Number(e.target.value);state.day=1;load();});
    $('yearSelect').addEventListener('change',e=>{state.year=Number(e.target.value);state.day=1;load();});
    $('hospitalSelect')?.addEventListener('change',e=>{state.hospitalId=Number(e.target.value);load();});
    $('prevMonth').addEventListener('click',()=>changeMonth(-1));
    $('nextMonth').addEventListener('click',()=>changeMonth(1));
    $('todayBtn').addEventListener('click',()=>{const now=new Date();state.year=now.getFullYear();state.month=now.getMonth()+1;state.day=now.getDate();load();});
    $('createBtn').addEventListener('click',()=>enqueue(async()=>{const r=await post('create',{hospital_id:state.hospitalId,year:state.year,month:state.month});toast(`สร้าง Draft Version ${r.roster.version} แล้ว`,'success');await load();}));
    $('saveBtn').addEventListener('click',()=>toast('ทุกช่องที่แก้ไขบันทึก Draft ผ่าน AJAX แล้ว','success'));
    $('copyBtn').addEventListener('click',previewCopy);$('autoBtn').addEventListener('click',previewAuto);$('repairBtn').addEventListener('click',previewRepair);$('balanceBtn').addEventListener('click',previewBalance);$('validateBtn').addEventListener('click',validateRoster);
    ['submit','approve','reject','publish','lock'].forEach(a=>$(a+'Btn').addEventListener('click',()=>transition(a)));
    $('revisionBtn').addEventListener('click',()=>{const reason=prompt('กรุณาระบุเหตุผลในการสร้าง Version ใหม่');if(!reason||reason.trim().length<3)return;enqueue(async()=>{const result=await post('new_revision',{roster_id:roster().id,expected_revision:roster().lock_version,reason});toast('สร้าง Version '+result.roster.version+' สำเร็จ','success');await load();});});
    $('historyBtn').addEventListener('click',showHistory);
    $('exportBtn').addEventListener('click',openExport);$('auditBtn').addEventListener('click',showAudit);$('settingsBtn').addEventListener('click',openSettings);
    $('tableTab').addEventListener('click',()=>updateView('table'));$('calendarTab').addEventListener('click',()=>updateView('calendar'));
    $('fairnessTab').addEventListener('click',()=>updateView('fairness'));
    $('staffSearch').addEventListener('input',filterStaff);$('staffPosition').addEventListener('change',filterStaff);$('staffType').addEventListener('change',filterStaff);
    $('openStaff').addEventListener('click',openStaff);$('closeStaff').addEventListener('click',closeStaff);$('drawerBackdrop').addEventListener('click',closeStaff);
    $('fullscreenBtn').addEventListener('click',toggleFullscreen);$('exitFullscreen').addEventListener('click',toggleFullscreen);
    $('focusStaff').addEventListener('click',openStaff);$('focusValidate').addEventListener('click',validateRoster);
    $('focusSave').addEventListener('click',()=>toast('การแก้ไขแต่ละช่องบันทึกทันทีแล้ว','success'));
    $('prevDay').addEventListener('click',()=>{if(state.day>1)state.day--;else{changeMonth(-1);state.day=dayCount();}renderMobile();});
    $('nextDay').addEventListener('click',()=>{if(state.day<dayCount())state.day++;else{changeMonth(1);state.day=1;}renderMobile();});
    $('mobileToday').addEventListener('click',()=>{const n=new Date();state.year=n.getFullYear();state.month=n.getMonth()+1;state.day=n.getDate();load();});
    $('mobileDay').addEventListener('click',event=>{const b=event.target.closest('button.mobile-person');if(b){const person=personById(b.dataset.user);if(person?.eligible)openPicker(b.dataset.user,b.dataset.date);else if(canEdit()&&confirm('ล้างเวรของบุคลากรที่พ้นสภาพหรือไม่?'))saveCell(b.dataset.user,b.dataset.date,null);}});
    $('mobileDay').addEventListener('change',event=>{const s=event.target.closest('select[data-add-shift]');if(s&&s.value){const target=shiftByCode(s.dataset.addShift);openPicker(s.value,s.dataset.addDate,target?.id);s.value='';}});
    $('shiftPicker').addEventListener('click',event=>{const b=event.target.closest('button[data-shift-id]');if(!b||!state.modalCell)return;const {userId,date}=state.modalCell;const note=$('shiftNote').value;modal('shiftModal').hide();saveCell(userId,date,Number(b.dataset.shiftId),note);});
    $('clearShift').addEventListener('click',()=>{if(!state.modalCell)return;const {userId,date}=state.modalCell;modal('shiftModal').hide();saveCell(userId,date,null);});
    const table=$('rosterTable');
    table.addEventListener('click',event=>{const cell=event.target.closest('td[data-user]');if(cell){const person=personById(cell.dataset.user);if(person?.eligible)openPicker(cell.dataset.user,cell.dataset.date);else if(canEdit()&&entry(cell.dataset.user,cell.dataset.date)&&confirm('บุคลากรพ้นสภาพ: ล้างเวรเดิมของบุคคลนี้หรือไม่?'))saveCell(cell.dataset.user,cell.dataset.date,null);}});
    table.addEventListener('keydown',event=>{
      const cell=event.target.closest('td[data-user]');if(!cell)return;
      if(['Enter',' '].includes(event.key)){event.preventDefault();const person=personById(cell.dataset.user);if(person?.eligible)openPicker(cell.dataset.user,cell.dataset.date);else if(entry(cell.dataset.user,cell.dataset.date)&&confirm('บุคลากรพ้นสภาพ: ล้างเวรเดิมของบุคคลนี้หรือไม่?'))saveCell(cell.dataset.user,cell.dataset.date,null);}
      if(event.ctrlKey&&(event.key==='c'||event.key==='v')){event.preventDefault();state.lastCell={userId:Number(cell.dataset.user),date:cell.dataset.date};contextAction(event.key==='c'?'copy':'paste');}
    });
    table.addEventListener('contextmenu',event=>{const cell=event.target.closest('td[data-user]');if(!cell)return;event.preventDefault();showContext(event.clientX,event.clientY,{userId:Number(cell.dataset.user),date:cell.dataset.date});});
    let longPress;
    table.addEventListener('touchstart',event=>{const cell=event.target.closest('td[data-user]');if(!cell)return;const touch=event.touches[0];const x=touch.clientX,y=touch.clientY;longPress=setTimeout(()=>showContext(x,y,{userId:Number(cell.dataset.user),date:cell.dataset.date}),550);},{passive:true});
    ['touchend','touchcancel','touchmove'].forEach(type=>table.addEventListener(type,()=>clearTimeout(longPress),{passive:true}));
    document.addEventListener('click',event=>{if(!event.target.closest('#contextMenu'))$('contextMenu').hidden=true;});
    $('contextMenu').addEventListener('click',event=>{const btn=event.target.closest('button[data-context]');if(btn)contextAction(btn.dataset.context);});
    $('staffList').addEventListener('dragstart',event=>{const card=event.target.closest('[data-staff-id]');if(!card||!canEdit()){event.preventDefault();return;}event.dataTransfer.setData('text/plain',JSON.stringify({kind:'staff',userId:Number(card.dataset.staffId)}));event.dataTransfer.effectAllowed='copy';});
    table.addEventListener('dragstart',event=>{const cell=event.target.closest('td[data-user]');if(!cell||!canEdit()){event.preventDefault();return;}event.dataTransfer.setData('text/plain',JSON.stringify({kind:'shift',userId:Number(cell.dataset.user),date:cell.dataset.date}));event.dataTransfer.effectAllowed='move';});
    table.addEventListener('dragover',event=>{if(event.target.closest('td[data-user]')&&canEdit())event.preventDefault();});
    table.addEventListener('drop',event=>{const cell=event.target.closest('td[data-user]');if(!cell||!canEdit())return;event.preventDefault();try{
      const source=JSON.parse(event.dataTransfer.getData('text/plain'));
      const target={userId:Number(cell.dataset.user),date:cell.dataset.date};
      if(source.kind==='staff')openPicker(source.userId,target.date);
      if(source.kind==='shift'){
        const old=entry(source.userId,source.date);if(!old)return;
        if(source.userId===target.userId&&source.date===target.date)return;
        if(!confirm('ย้ายเวรนี้ไปยังช่องปลายทาง (แทนค่าเดิม)?'))return;
        saveMany([{user_id:source.userId,date:source.date,shift_type_id:null},
          {user_id:target.userId,date:target.date,shift_type_id:Number(old.shift_type_id),note:old.note}]);
      }
    }catch(_){toast('ข้อมูลลากวางไม่ถูกต้อง','danger');}});
    const observer=new ResizeObserver(updateResponsive);observer.observe($('rosterWorkspace'));
    document.addEventListener('fullscreenchange',updateResponsive);
  }
  initSelectors();bindEvents();load();
})();
