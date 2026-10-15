/* Catatan front-end. Vanilla JS, no build step. Every feature degrades to plain HTML forms. */
(() => {
  'use strict';
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => [...el.querySelectorAll(s)];
  const csrf = () => $('meta[name="csrf-token"]')?.content ?? '';

  /* ---- Theme (auto -> dark -> light) -------------------------------------------------- */
  document.addEventListener('click', (e) => {
    if (!e.target.closest('[data-theme-toggle]')) return;
    const root = document.documentElement;
    const isDark = root.dataset.theme === 'dark' ||
      (root.dataset.theme === 'auto' && matchMedia('(prefers-color-scheme: dark)').matches);
    root.dataset.theme = isDark ? 'light' : 'dark';
    try { localStorage.setItem('theme', root.dataset.theme); } catch (_) {}
  });

  /* ---- Mobile filter sheet ------------------------------------------------------------- */
  document.addEventListener('click', (e) => {
    if (e.target.closest('[data-sheet-toggle]')) document.body.classList.toggle('sheet-open');
  });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') document.body.classList.remove('sheet-open'); });

  /* ---- Auto-growing textareas ---------------------------------------------------------- */
  const grow = (t) => { t.style.height = 'auto'; t.style.height = t.scrollHeight + 'px'; };
  $$('textarea.autosize').forEach((t) => { grow(t); t.addEventListener('input', () => grow(t)); });

  /* ---- Ctrl/Cmd + Enter saves ---------------------------------------------------------- */
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey) && e.target.matches('textarea')) {
      e.target.form?.requestSubmit();
    }
  });

  /* ---- Attachment picker: previews + max-count guard ------------------------------------ */
  $$('[data-file]').forEach((input) => {
    const form = input.closest('form');
    const box = $('[data-previews]', form);
    const max = Number(input.dataset.max || 6);
    input.addEventListener('change', () => {
      box.replaceChildren();
      let files = [...input.files];
      if (files.length > max) {
        alert(`You can attach up to ${max} files per post.`);
        input.value = '';
        files = [];
      }
      files.forEach((f) => {
        if (f.type.startsWith('image/')) {
          const img = document.createElement('img');
          img.src = URL.createObjectURL(f);
          img.alt = f.name;
          img.onload = () => URL.revokeObjectURL(img.src);
          box.append(img);
        } else {
          const chip = document.createElement('span');
          chip.className = 'preview-file';
          chip.textContent = (f.type.startsWith('video/') ? '🎬 ' : '📄 ') + f.name;
          box.append(chip);
        }
      });
    });
  });

  /* ---- Videos behave like reels: play (muted) while on screen, pause when scrolled away -- */
  const reelIO = 'IntersectionObserver' in window
    ? new IntersectionObserver((entries) => entries.forEach((en) => {
        const v = en.target;
        if (en.isIntersecting && !navigator.connection?.saveData) v.play().catch(() => {}); else v.pause();
      }), { threshold: 0.6 })
    : null;
  const observeReels = () => $$('video[data-reel]').forEach((v) => {
    if (!v.dataset.watched) { v.dataset.watched = '1'; reelIO?.observe(v); }
  });
  observeReels();

  /* ---- Copy-to-clipboard buttons (invite link) ----------------------------------------- */
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-copy]');
    if (!btn) return;
    const input = $(btn.dataset.copy);
    if (!input) return;
    try { await navigator.clipboard.writeText(input.value); } catch (_) { input.select(); document.execCommand('copy'); }
    const old = btn.innerHTML;
    btn.textContent = 'Copied ✓';
    setTimeout(() => (btn.innerHTML = old), 1500);
  });

  /* ---- Reminder / due-date quick presets ("+1h", "+1d@09:00", "clear") -------------------- */
  const pad = (n) => String(n).padStart(2, '0');
  const toLocalInput = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  document.addEventListener('click', (e) => {
    const chip = e.target.closest('[data-preset]');
    if (!chip) return;
    const input = chip.closest('form').querySelector(`[name="${chip.dataset.target}"]`);
    if (!input) return;
    if (chip.dataset.preset === 'clear') { input.value = ''; return; }
    const m = /^\+(\d+)([hdwM])(?:@(\d{2}):(\d{2}))?$/.exec(chip.dataset.preset);
    if (!m) return;
    const d = new Date();
    const n = Number(m[1]);
    if (m[2] === 'h') d.setHours(d.getHours() + n);
    if (m[2] === 'd') d.setDate(d.getDate() + n);
    if (m[2] === 'w') d.setDate(d.getDate() + 7 * n);
    if (m[2] === 'M') d.setMonth(d.getMonth() + n);
    if (m[3]) d.setHours(Number(m[3]), Number(m[4]), 0, 0);
    input.value = toLocalInput(d);
  });

  /* ---- Live due-date countdown (same rules as Note::countdown() in PHP) ----------------- */
  const tickDue = () => $$('.due[data-due]').forEach((el) => {
    const secs = Math.round((new Date(el.dataset.due) - Date.now()) / 1000);
    const abs = Math.abs(secs);
    const d = Math.floor(abs / 86400), h = Math.floor((abs % 86400) / 3600), m = Math.floor((abs % 3600) / 60);
    const span = d > 0 ? `${d}d ${h}h` : h > 0 ? `${h}h ${m}m` : `${Math.max(m, 1)}m`;
    $('b', el).textContent = secs < 0 ? `Overdue ${span}` : `Due in ${span}`;
    el.classList.remove('ok', 'warn', 'soon', 'over');
    el.classList.add(secs < 0 ? 'over' : secs < 86400 ? 'soon' : secs < 3 * 86400 ? 'warn' : 'ok');
  });
  tickDue();
  setInterval(tickDue, 30000);

  /* ---- Prevent double submits ---------------------------------------------------------- */
  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (form.hasAttribute('data-toggle')) return;
    if (form.dataset.confirm && !confirm(form.dataset.confirm)) { e.preventDefault(); return; }
    const uploading = $$('input[type=file]', form).some((i) => i.files.length);
    $$('button[type=submit], button:not([type])', form).forEach((b) => setTimeout(() => {
      b.disabled = true;
      if (uploading && b.type === 'submit') b.textContent = 'Uploading…';
    }, 0));
  });

  // Back/forward cache can restore a page with buttons we disabled on submit.
  addEventListener('pageshow', (e) => e.persisted && $$('button:disabled').forEach((b) => (b.disabled = false)));

  /* ---- Star / pin without page reload -------------------------------------------------- */
  document.addEventListener('submit', async (e) => {
    const form = e.target.closest('form[data-toggle]');
    if (!form) return;
    e.preventDefault();
    const btn = $('button', form);
    try {
      const res = await fetch(form.action, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      if (!res.ok) throw new Error(res.status);
      const { on } = await res.json();
      btn.classList.toggle('is-on', on);
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    } catch (_) {
      form.submit(); // fall back to a normal request (e.g. session expired -> login)
    }
  });

  /* ---- "Load more" (button + auto-load when scrolled near it) -------------------------- */
  const loadMore = async (btn) => {
    if (btn.dataset.busy) return;
    btn.dataset.busy = '1';
    btn.textContent = 'Loading…';
    try {
      const res = await fetch(btn.dataset.next, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin',
      });
      if (!res.ok) throw new Error(res.status);
      const tpl = document.createElement('template');
      tpl.innerHTML = await res.text();
      btn.replaceWith(tpl.content);
      observe();
      observeReels();
      tickDue();
    } catch (_) {
      btn.textContent = 'Failed — tap to retry';
      delete btn.dataset.busy;
    }
  };
  const io = 'IntersectionObserver' in window
    ? new IntersectionObserver((entries) => entries.forEach((en) => en.isIntersecting && loadMore(en.target)), { rootMargin: '400px' })
    : null;
  const observe = () => $$('.load-more').forEach((b) => io?.observe(b));
  document.addEventListener('click', (e) => { const b = e.target.closest('.load-more'); if (b) loadMore(b); });
  observe();

  /* ---- Lightbox ------------------------------------------------------------------------ */
  document.addEventListener('click', (e) => {
    const a = e.target.closest('a[data-lightbox]');
    if (!a) return;
    e.preventDefault();
    const box = document.createElement('div');
    box.className = 'lightbox';
    box.innerHTML = '<img alt="">';
    box.firstChild.src = a.href;
    const close = () => { box.remove(); document.removeEventListener('keydown', onKey); };
    const onKey = (ev) => ev.key === 'Escape' && close();
    box.addEventListener('click', close);
    document.addEventListener('keydown', onKey);
    document.body.append(box);
  });

  /* ---- Jump straight into the composer from "New note" --------------------------------- */
  if (location.hash === '#composer') $('#composer textarea')?.focus();
  document.addEventListener('click', (e) => {
    const a = e.target.closest('a[href$="#composer"]');
    const ta = $('#composer textarea');
    if (a && ta && a.pathname === location.pathname) { e.preventDefault(); ta.scrollIntoView({ block: 'center' }); ta.focus(); }
  });
})();
