'use strict';
document.querySelectorAll('[data-confirm]').forEach((form) => {
  form.addEventListener('submit', (event) => {
    if (!window.confirm(form.dataset.confirm || 'ยืนยันทำรายการนี้?')) event.preventDefault();
  });
});
document.querySelectorAll('[data-copy-target]').forEach((button) => {
  button.addEventListener('click', async () => {
    const input = document.getElementById(button.dataset.copyTarget);
    if (!input) return;
    try {
      await navigator.clipboard.writeText(input.value);
      button.textContent = 'คัดลอกแล้ว';
    } catch (_) {
      input.select();
      button.textContent = 'เลือกข้อความแล้ว — กด Ctrl+C';
    }
  });
});
// Secrets appear in the server response once; never store them in browser storage.
document.querySelectorAll('[data-reveal-target]').forEach((button) => {
  button.addEventListener('click', () => {
    const input=document.getElementById(button.dataset.revealTarget);
    if (input) input.type=input.type==='password'?'text':'password';
  });
});
