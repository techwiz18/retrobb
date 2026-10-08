// RetroBB tiny JS: BBCode toolbar + quote buttons. No deps.
// Quote button: append [quote=user]...[/quote] to the reply box.
document.addEventListener('click', (ev) => {
  const q = ev.target.closest('.quotebtn');
  if (!q) return;
  const bit = q.closest('.postbit');
  const ta = document.getElementById('replybox-ta');
  if (!bit || !ta) return;
  const user = bit.getAttribute('data-username') || 'user';
  const body = (bit.querySelector('.postright')?.innerText || '').trim().slice(0, 2000);
  const ins = '[quote=' + user + ']' + body + '[/quote]\n';
  ta.value = (ta.value ? ta.value.replace(/\s+$/, '') + '\n\n' : '') + ins;
  ta.focus();
  document.getElementById('reply')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
});
document.addEventListener('click', (ev) => {
  const btn = ev.target.closest('[data-bb]');
  if (!btn) return;
  const form = btn.closest('form');
  const ta = form ? form.querySelector('textarea') : document.querySelector('textarea');
  if (!ta) return;
  const snippet = btn.getAttribute('data-bb') || '';
  const half = Math.floor(snippet.length / 2);
  const start = ta.selectionStart ?? ta.value.length;
  const end = ta.selectionEnd ?? ta.value.length;
  const before = ta.value.slice(0, start);
  const sel = ta.value.slice(start, end);
  const after = ta.value.slice(end);
  // insert snippet around selection
  const open = snippet.slice(0, half);
  const close = snippet.slice(half);
  ta.value = before + open + (sel || '') + close + after;
  ta.focus();
});
