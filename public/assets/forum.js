// RetroBB tiny JS: BBCode toolbar + quote buttons + user dropdown + compose guard. No deps.
// Compose guard: PM compose/draft forms warn on navigating away with
// unsent changes (compared against the values present at page load, so a
// prefilled reply quote doesn't count until edited). Submitting (Send or
// Save draft) persists the content, so no warning after that.
document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('form[data-compose-guard]');
  if (!form) return;
  const fields = () => Array.from(form.querySelectorAll('input[name="to"], input[name="subject"], textarea[name="body"]'));
  const snapshot = () => fields().map((f) => f.value).join('\n');
  const initial = snapshot();
  let submitted = false;
  form.addEventListener('submit', () => { submitted = true; });
  window.addEventListener('beforeunload', (ev) => {
    if (!submitted && snapshot() !== initial) {
      ev.preventDefault();
    }
  });
});
// User dropdown (alerts/messages): click toggles for touch, Esc/outside closes.
// (Hover/focus-within reveals it with no JS via CSS.)
document.addEventListener('click', (ev) => {
  const wrap = document.querySelector('.dropwrap');
  if (!wrap) return;
  const btn = ev.target.closest('#userdrop-btn');
  if (btn) {
    const open = wrap.classList.toggle('open');
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    return;
  }
  if (!ev.target.closest('.dropwrap')) {
    wrap.classList.remove('open');
    document.getElementById('userdrop-btn')?.setAttribute('aria-expanded', 'false');
  }
});
document.addEventListener('keydown', (ev) => {
  if (ev.key !== 'Escape') return;
  document.querySelector('.dropwrap')?.classList.remove('open');
  document.getElementById('userdrop-btn')?.setAttribute('aria-expanded', 'false');
});
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
