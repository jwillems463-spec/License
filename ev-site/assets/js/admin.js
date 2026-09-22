/* e-carscompare — admin dashboard (single-page app, hash routing, no dependencies). */
(function () {
  'use strict';

  const CFG = window.ADMIN || { base: '', csrf: '', siteName: 'e-carscompare', currency: '$' };
  const root = document.getElementById('admin-root');
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const h = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const url = (p) => CFG.base + '/' + String(p).replace(/^\//, '');
  const money = (v) => (v === null || v === undefined ? '—' : CFG.currency + new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 }).format(v));
  const num = (v, u) => (v === null || v === undefined ? '—' : new Intl.NumberFormat(undefined, { maximumFractionDigits: 1 }).format(v) + (u ? ' ' + u : ''));
  const dt = (s) => (s ? new Date(String(s).replace(' ', 'T')).toLocaleString() : '—');
  const bolt = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13 2 4 14h7l-1 8 9-12h-7z"/></svg>';

  const state = { user: null, csrf: CFG.csrf, brands: null };
  const can = (p) => !!state.user && (state.user.permissions.includes('*') || state.user.permissions.includes(p));

  // ------------------------------------------------------------------ API
  class ApiError extends Error {
    constructor(status, body) {
      super((body.error && body.error.message) || 'Request failed (' + status + ')');
      this.status = status;
      this.fields = (body.error && body.error.fields) || {};
    }
  }

  async function api(method, path, body, retried) {
    const opts = { method, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-Token': state.csrf } };
    if (body instanceof FormData) opts.body = body;
    else if (body !== undefined) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
    const res = await fetch(url('api/' + path), opts);
    const data = await res.json().catch(() => ({}));
    if (data.csrf_token) state.csrf = data.csrf_token;
    if (res.status === 403 && data.error && data.error.code === 'csrf_mismatch' && !retried) {
      const me = await fetch(url('api/auth/me'), { credentials: 'same-origin' }).then((r) => r.json());
      state.csrf = me.csrf_token;
      return api(method, path, body, true);
    }
    if (res.status === 401 && path.indexOf('auth/') !== 0) { state.user = null; renderLogin('Your session has ended. Please sign in again.'); throw new ApiError(401, data); }
    if (!res.ok) throw new ApiError(res.status, data);
    return data;
  }
  const GET = (p, q) => api('GET', p + (q ? '?' + new URLSearchParams(q) : ''));

  // ------------------------------------------------------------ UI helpers
  function toast(msg, type) {
    const el = document.createElement('div');
    el.className = 'toast' + (type ? ' ' + type : '');
    el.textContent = msg;
    $('#toasts').appendChild(el);
    setTimeout(() => el.remove(), 3800);
  }

  function confirmDialog(title, text, okLabel = 'Delete') {
    const d = $('#confirm-dialog');
    $('[data-confirm-title]', d).textContent = title;
    $('[data-confirm-text]', d).textContent = text;
    $('[data-confirm-ok]', d).textContent = okLabel;
    return new Promise((resolve) => {
      d.returnValue = '';
      d.addEventListener('close', () => resolve(d.returnValue === 'ok'), { once: true });
      d.showModal();
    });
  }

  function showErrors(form, err) {
    $$('.f.has-error', form).forEach((f) => { f.classList.remove('has-error'); const e = $('.error', f); if (e) e.remove(); });
    const alert = $('[data-form-alert]', form);
    if (alert) { alert.hidden = false; alert.textContent = err.message; }
    Object.entries(err.fields || {}).forEach(([name, msg]) => {
      const input = form.elements[name];
      const f = input && (input.closest ? input.closest('.f') : null);
      if (f) { f.classList.add('has-error'); f.insertAdjacentHTML('beforeend', `<div class="error">${h(msg)}</div>`); }
    });
    const first = $('.has-error input, .has-error select, .has-error textarea', form);
    if (first) first.focus();
  }
  function clearErrors(form) {
    $$('.f.has-error', form).forEach((f) => { f.classList.remove('has-error'); const e = $('.error', f); if (e) e.remove(); });
    const alert = $('[data-form-alert]', form);
    if (alert) alert.hidden = true;
  }

  function field(f, value) {
    const id = 'f_' + f.name;
    const v = value ?? '';
    let input;
    if (f.type === 'select') {
      input = `<select id="${id}" name="${f.name}" ${f.required ? 'required' : ''}>${f.empty ? `<option value="">${h(f.empty)}</option>` : ''}${f.options.map((o) => {
        const [val, label] = Array.isArray(o) ? o : [o, o];
        return `<option value="${h(val)}" ${String(val) === String(v) ? 'selected' : ''}>${h(label)}</option>`;
      }).join('')}</select>`;
    } else if (f.type === 'textarea') {
      input = `<textarea id="${id}" name="${f.name}" maxlength="${f.max || ''}">${h(v)}</textarea>`;
    } else if (f.type === 'checkbox') {
      return `<div class="f ${f.full ? 'full' : ''}"><label class="check"><input type="checkbox" name="${f.name}" ${v ? 'checked' : ''}> ${h(f.label)}</label>${f.hint ? `<div class="hint">${h(f.hint)}</div>` : ''}</div>`;
    } else {
      const attrs = [f.required ? 'required' : '', f.max && f.type !== 'number' ? `maxlength="${f.max}"` : '', f.step ? `step="${f.step}"` : '',
        f.min !== undefined ? `min="${f.min}"` : '', f.placeholder ? `placeholder="${h(f.placeholder)}"` : '', f.autocomplete ? `autocomplete="${f.autocomplete}"` : ''].join(' ');
      input = `<input id="${id}" type="${f.type || 'text'}" name="${f.name}" value="${h(v)}" ${attrs}>`;
      if (f.unit) input = `<div class="input-unit">${input}<span>${h(f.unit)}</span></div>`;
    }
    return `<div class="f ${f.full ? 'full' : ''}"><label for="${id}">${h(f.label)}${f.required ? ' *' : ''}</label>${input}${f.hint ? `<div class="hint">${h(f.hint)}</div>` : ''}</div>`;
  }

  function formData(form, fields) {
    const out = {};
    fields.forEach((f) => {
      const el = form.elements[f.name];
      if (!el) return;
      if (f.type === 'checkbox') out[f.name] = el.checked;
      else if (f.type === 'number') out[f.name] = el.value === '' ? null : Number(el.value);
      else out[f.name] = el.value;
    });
    return out;
  }

  async function brands(force) {
    if (!state.brands || force) state.brands = (await GET('admin/brands')).data;
    return state.brands;
  }

  // ------------------------------------------------------------ Auth views
  function authCard(inner) {
    root.className = '';
    root.innerHTML = `<div class="auth-wrap"><div class="auth-card"><div class="brand">${bolt}<span>${h(CFG.siteName)} admin</span></div>${inner}</div></div>`;
  }

  function renderLogin(notice) {
    authCard(`<h1>Sign in</h1>
      ${notice ? `<div class="alert warn">${h(notice)}</div>` : ''}
      <form data-login novalidate>
        <div class="alert error" data-form-alert hidden></div>
        <div class="fields" style="grid-template-columns:1fr">
          ${field({ name: 'email', label: 'Email', type: 'email', required: true, autocomplete: 'username' })}
          ${field({ name: 'password', label: 'Password', type: 'password', required: true, autocomplete: 'current-password' })}
        </div>
        <div class="form-actions"><button class="btn btn-primary" type="submit" style="width:100%">Sign in</button></div>
      </form>
      <p class="muted" style="font-size:.85rem"><a href="${h(url('/'))}">← Back to site</a></p>`);
    const form = $('[data-login]');
    form.elements.email.focus();
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      clearErrors(form);
      const btn = $('button[type=submit]', form);
      btn.disabled = true;
      try {
        const res = await api('POST', 'auth/login', { email: form.elements.email.value, password: form.elements.password.value });
        state.user = res.data;
        start();
      } catch (err) { showErrors(form, err); } finally { btn.disabled = false; }
    });
  }

  function renderForcePassword() {
    authCard(`<h1>Choose a new password</h1>
      <div class="alert warn">For security you must replace the temporary password before continuing.</div>
      ${passwordForm()}
      <p><button class="btn-link" data-logout>Sign out</button></p>`);
    bindPasswordForm(() => { state.user.must_change_password = false; toast('Password updated'); start(); });
    $('[data-logout]').addEventListener('click', logout);
  }

  function passwordForm() {
    return `<form data-password novalidate>
      <div class="alert error" data-form-alert hidden></div>
      <div class="fields" style="grid-template-columns:1fr">
        ${field({ name: 'current_password', label: 'Current password', type: 'password', required: true, autocomplete: 'current-password' })}
        ${field({ name: 'new_password', label: 'New password', type: 'password', required: true, autocomplete: 'new-password', hint: 'At least 10 characters, with letters and numbers.' })}
        ${field({ name: 'confirm_password', label: 'Confirm new password', type: 'password', required: true, autocomplete: 'new-password' })}
      </div>
      <div class="form-actions"><button class="btn btn-primary" type="submit">Update password</button></div>
    </form>`;
  }

  function bindPasswordForm(onDone) {
    const form = $('[data-password]');
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      clearErrors(form);
      const v = (n) => form.elements[n].value;
      if (v('new_password') !== v('confirm_password')) return showErrors(form, { message: 'Passwords do not match.', fields: { confirm_password: 'Passwords do not match.' } });
      try {
        await api('POST', 'auth/password', { current_password: v('current_password'), new_password: v('new_password') });
        form.reset();
        onDone();
      } catch (err) { showErrors(form, err); }
    });
  }

  async function logout() {
    try { await api('POST', 'auth/logout'); } catch (e) { /* ignore */ }
    state.user = null;
    location.hash = '';
    renderLogin('You have been signed out.');
  }

  // ------------------------------------------------------------ Shell
  const icons = {
    dashboard: '<path d="M3 13h8V3H3zM13 21h8V11h-8zM3 21h8v-6H3zM13 3v6h8V3z"/>',
    evs: '<path d="M5 17h14M6 17l1.5-6h9L18 17M8 11l1-4h6l1 4"/><circle cx="8" cy="17" r="2"/><circle cx="16" cy="17" r="2"/>',
    brands: '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
    users: '<circle cx="9" cy="8" r="4"/><path d="M1 21v-1a7 7 0 0 1 14 0v1M17 11a3 3 0 1 0 0-6M23 21v-1a5 5 0 0 0-4-4.9"/>',
    settings: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
    audit: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h8M8 9h2"/>',
    account: '<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a8 8 0 0 1 16 0v1"/>',
  };

  function nav() {
    const items = [
      ['dashboard', 'Dashboard', 'dashboard.view'],
      ['evs', 'EVs', 'vehicles.view'],
      ['brands', 'Brands', 'brands.view'],
      ['users', 'Users', 'users.manage'],
      ['settings', 'Site settings', 'settings.manage'],
      ['audit', 'Activity log', 'audit.view'],
      ['account', 'My account', null],
    ];
    return items.filter(([, , p]) => !p || can(p)).map(([k, label]) =>
      `<a href="#/${k}" data-nav="${k}"><svg viewBox="0 0 24 24">${icons[k]}</svg>${label}</a>`).join('');
  }

  function renderShell() {
    root.className = '';
    root.innerHTML = `<div class="shell">
      <aside class="sidebar" data-sidebar>
        <a class="brand" href="#/dashboard">${bolt}<span>${h(CFG.siteName)}</span></a>
        <nav aria-label="Admin">${nav()}</nav>
        <div class="side-foot"><a href="${h(url('/'))}" target="_blank" rel="noopener">View live site ↗</a></div>
      </aside>
      <div class="backdrop" data-backdrop hidden></div>
      <div class="main">
        <header class="topbar">
          <button class="menu-btn" data-menu aria-label="Open menu">☰</button>
          <div class="spacer"></div>
          <div class="user-chip"><span>${h(state.user.name)}</span><span class="role-badge">${h(state.user.role)}</span></div>
          <button class="btn btn-ghost btn-sm" data-logout>Sign out</button>
        </header>
        <main class="content" id="view" tabindex="-1"></main>
      </div>
    </div>`;
    const side = $('[data-sidebar]'), bd = $('[data-backdrop]');
    const close = () => { side.classList.remove('open'); bd.hidden = true; };
    $('[data-menu]').addEventListener('click', () => { side.classList.add('open'); bd.hidden = false; });
    bd.addEventListener('click', close);
    side.addEventListener('click', (e) => { if (e.target.closest('a')) close(); });
    $('[data-logout]').addEventListener('click', logout);
  }

  // ------------------------------------------------------------ Router
  const routes = [
    [/^\/?$/, () => { location.hash = '#/dashboard'; }],
    [/^\/dashboard$/, viewDashboard, 'dashboard'],
    [/^\/evs$/, viewEvs, 'evs'],
    [/^\/evs\/new$/, () => viewEvForm(null), 'evs'],
    [/^\/evs\/(\d+)$/, (m) => viewEvForm(Number(m[1])), 'evs'],
    [/^\/brands$/, viewBrands, 'brands'],
    [/^\/users$/, viewUsers, 'users'],
    [/^\/settings$/, viewSettings, 'settings'],
    [/^\/audit$/, viewAudit, 'audit'],
    [/^\/account$/, viewAccount, 'account'],
  ];

  let dirty = false;
  window.addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  async function route() {
    if (!state.user) return;
    const path = location.hash.replace(/^#/, '') || '/';
    const view = $('#view');
    for (const [re, fn, key] of routes) {
      const m = path.match(re);
      if (!m) continue;
      $$('[data-nav]').forEach((a) => a.classList.toggle('active', a.dataset.nav === key));
      dirty = false;
      view.onclick = null;
      view.onchange = null;
      view.innerHTML = '<div class="empty">Loading…</div>';
      try { await fn(m, view); } catch (err) {
        if (err.status !== 401) view.innerHTML = `<div class="alert error">${h(err.message)}</div>`;
      }
      view.focus({ preventScroll: true });
      return;
    }
    view.innerHTML = '<div class="empty">Page not found.</div>';
  }
  let lastHash = location.hash;
  window.addEventListener('hashchange', () => {
    if (location.hash === lastHash) return;
    if (dirty && !confirm('You have unsaved changes. Leave this page?')) {
      history.replaceState(null, '', lastHash || '#/dashboard');
      return;
    }
    lastHash = location.hash;
    route();
  });

  // ------------------------------------------------------------ Dashboard
  async function viewDashboard(m, view) {
    const s = (await GET('admin/stats')).data;
    const bars = (rows) => {
      const max = Math.max(1, ...rows.map((r) => r.value));
      return rows.length ? `<div class="bars">${rows.map((r) => `<div class="bar-row"><span class="label" title="${h(r.label)}">${h(r.label)}</span>
        <div class="bar-track"><div class="bar-fill" style="width:${(r.value / max) * 100}%"></div></div><span class="num">${r.value}</span></div>`).join('')}</div>`
        : '<p class="muted">No published EVs yet.</p>';
    };
    view.innerHTML = `<div class="page-head"><h1>Dashboard</h1>
        ${can('vehicles.create') ? '<a class="btn btn-primary" href="#/evs/new">+ Add EV</a>' : ''}</div>
      <div class="stat-grid">
        <div class="panel stat-tile"><div class="label">Total EVs</div><div class="value">${s.vehicles.total}</div><div class="sub">${s.vehicles.published} published · ${s.vehicles.drafts} drafts</div></div>
        <div class="panel stat-tile"><div class="label">Featured</div><div class="value">${s.vehicles.featured}</div><div class="sub">shown on homepage</div></div>
        <div class="panel stat-tile"><div class="label">Brands</div><div class="value">${s.brands}</div><div class="sub">${s.users} active user(s)</div></div>
        <div class="panel stat-tile"><div class="label">Avg. range</div><div class="value">${num(s.vehicles.avg_range || null, 'km')}</div><div class="sub">avg. price ${money(s.vehicles.avg_price || null)}</div></div>
      </div>
      <div class="dash-grid">
        <section class="panel"><h2>Published by body type</h2>${bars(s.by_body.map((r) => ({ ...r, label: r.label === 'suv' ? 'SUV' : r.label.charAt(0).toUpperCase() + r.label.slice(1) })))}</section>
        <section class="panel"><h2>Published by brand</h2>${bars(s.by_brand)}</section>
        <section class="panel"><h2>Recent activity</h2>
          ${s.recent.length ? `<ul class="activity">${s.recent.map((a) => `<li>${h(a.summary || a.action + ' ' + a.entity)}<small>${h(a.user_name || 'System')} · ${dt(a.created_at)}</small></li>`).join('')}</ul>` : '<p class="muted">No activity yet.</p>'}
          ${can('audit.view') ? '<p><a href="#/audit">View full activity log →</a></p>' : ''}
        </section>
      </div>`;
  }

  // ------------------------------------------------------------ EV list
  const evQuery = { q: '', status: '', brand: '', sort: 'newest', page: 1 };

  async function viewEvs(m, view) {
    const bl = await brands();
    view.innerHTML = `<div class="page-head"><h1>EVs</h1>${can('vehicles.create') ? '<a class="btn btn-primary" href="#/evs/new">+ Add EV</a>' : ''}</div>
      <div class="toolbar">
        <input type="search" data-q placeholder="Search brand, model, variant…" value="${h(evQuery.q)}" aria-label="Search">
        <select data-status aria-label="Status"><option value="">All statuses</option><option value="published">Published</option><option value="draft">Draft</option></select>
        <select data-brand aria-label="Brand"><option value="">All brands</option>${bl.map((b) => `<option value="${b.id}">${h(b.name)}</option>`).join('')}</select>
        <select data-sort aria-label="Sort">
          <option value="newest">Newest</option><option value="name_asc">Name A–Z</option><option value="price_asc">Price ↑</option>
          <option value="price_desc">Price ↓</option><option value="range_desc">Range ↓</option>
        </select>
      </div>
      <div class="bulk-bar" data-bulk hidden>
        <strong data-bulk-count></strong>
        <button class="btn btn-ghost btn-sm" data-bulk-action="publish">Publish</button>
        <button class="btn btn-ghost btn-sm" data-bulk-action="unpublish">Unpublish</button>
        <button class="btn btn-ghost btn-sm" data-bulk-action="feature">Feature</button>
        <button class="btn btn-ghost btn-sm" data-bulk-action="unfeature">Unfeature</button>
        ${can('vehicles.delete') ? '<button class="btn btn-danger btn-sm" data-bulk-action="delete">Delete</button>' : ''}
      </div>
      <div class="table-wrap" data-table><div class="empty">Loading…</div></div>
      <div class="pager" data-pager></div>`;

    $('[data-status]', view).value = evQuery.status;
    $('[data-brand]', view).value = evQuery.brand;
    $('[data-sort]', view).value = evQuery.sort;
    const selected = new Set();

    const updateBulk = () => {
      $('[data-bulk]', view).hidden = selected.size === 0;
      $('[data-bulk-count]', view).textContent = selected.size + ' selected';
    };

    async function load() {
      const q = { per_page: 20, page: evQuery.page, sort: evQuery.sort };
      ['q', 'status', 'brand'].forEach((k) => { if (evQuery[k]) q[k] = evQuery[k]; });
      const res = await GET('admin/evs', q);
      selected.clear(); updateBulk();
      const t = $('[data-table]', view);
      if (!res.data.length) { t.innerHTML = '<div class="empty">No EVs found.</div>'; $('[data-pager]', view).innerHTML = ''; return; }
      t.innerHTML = `<table class="tbl"><thead><tr>
          <th><input type="checkbox" data-all aria-label="Select all"></th><th></th><th>EV</th><th>Year</th>
          <th class="num">Price</th><th class="num">Range</th><th>Status</th><th>Updated</th><th class="actions">Actions</th></tr></thead>
        <tbody>${res.data.map((ev) => `<tr>
          <td><input type="checkbox" data-sel="${ev.id}" aria-label="Select ${h(ev.title)}"></td>
          <td>${ev.image_url ? `<img class="thumb" src="${h(ev.image_url)}" alt="">` : '<span class="thumb"></span>'}</td>
          <td><a href="#/evs/${ev.id}"><strong>${h(ev.brand.name)} ${h(ev.model)}</strong></a><br><span class="muted">${h(ev.variant || '')}</span></td>
          <td>${ev.model_year}</td>
          <td class="num">${money(ev.price_usd)}</td>
          <td class="num">${num(ev.range_km, 'km')}</td>
          <td><span class="pill ${ev.status}">${ev.status}</span> ${ev.is_featured ? '<span class="pill featured">featured</span>' : ''}</td>
          <td>${dt(ev.updated_at)}</td>
          <td class="actions">
            ${ev.status === 'published' ? `<a href="${h(url('ev/' + ev.slug))}" target="_blank" rel="noopener">View</a>` : ''}
            <a href="#/evs/${ev.id}">Edit</a>
            ${can('vehicles.delete') ? `<button class="btn-link danger" data-del="${ev.id}" data-title="${h(ev.title)}">Delete</button>` : ''}
          </td></tr>`).join('')}</tbody></table>`;
      const m = res.meta;
      $('[data-pager]', view).innerHTML = `<span>${m.total} EV(s) · page ${m.page} of ${m.pages}</span>
        <span class="btns"><button class="btn btn-ghost btn-sm" data-pg="${m.page - 1}" ${m.page <= 1 ? 'disabled' : ''}>‹ Prev</button>
        <button class="btn btn-ghost btn-sm" data-pg="${m.page + 1}" ${m.page >= m.pages ? 'disabled' : ''}>Next ›</button></span>`;
    }

    let t;
    $('[data-q]', view).addEventListener('input', (e) => { clearTimeout(t); t = setTimeout(() => { evQuery.q = e.target.value.trim(); evQuery.page = 1; load(); }, 300); });
    ['status', 'brand', 'sort'].forEach((k) => $(`[data-${k}]`, view).addEventListener('change', (e) => { evQuery[k] = e.target.value; evQuery.page = 1; load(); }));

    view.onclick = async (e) => {
      const pg = e.target.closest('[data-pg]');
      if (pg && !pg.disabled) { evQuery.page = Number(pg.dataset.pg); return load(); }
      const del = e.target.closest('[data-del]');
      if (del) {
        if (!(await confirmDialog('Delete EV?', `“${del.dataset.title}” will be permanently removed from the website.`))) return;
        try { await api('DELETE', 'admin/evs/' + del.dataset.del); toast('EV deleted'); load(); } catch (err) { toast(err.message, 'error'); }
        return;
      }
      const ba = e.target.closest('[data-bulk-action]');
      if (ba) {
        const action = ba.dataset.bulkAction;
        if (action === 'delete' && !(await confirmDialog('Delete selected EVs?', `${selected.size} EV(s) will be permanently removed.`))) return;
        try {
          const r = await api('POST', 'admin/evs/bulk', { ids: Array.from(selected), action });
          toast(`${r.data.affected} EV(s) updated`); load();
        } catch (err) { toast(err.message, 'error'); }
      }
    };
    view.onchange = (e) => {
      if (e.target.matches('[data-all]')) {
        $$('[data-sel]', view).forEach((cb) => { cb.checked = e.target.checked; cb.checked ? selected.add(Number(cb.dataset.sel)) : selected.delete(Number(cb.dataset.sel)); });
        updateBulk();
      } else if (e.target.matches('[data-sel]')) {
        e.target.checked ? selected.add(Number(e.target.dataset.sel)) : selected.delete(Number(e.target.dataset.sel));
        updateBulk();
      }
    };
    await load();
  }

  // ------------------------------------------------------------ EV form
  const BODY_TYPES = ['sedan', 'suv', 'hatchback', 'crossover', 'pickup', 'van', 'coupe', 'wagon'];
  function evFieldGroups(bl) {
    return [
      ['Basics', [
        { name: 'brand_id', label: 'Brand', type: 'select', required: true, empty: 'Select a brand…', options: bl.map((b) => [b.id, b.name]) },
        { name: 'model', label: 'Model', required: true, max: 120, placeholder: 'e.g. IONIQ 5' },
        { name: 'variant', label: 'Variant / trim', max: 120, placeholder: 'e.g. Long Range AWD' },
        { name: 'model_year', label: 'Model year', type: 'number', required: true, min: 1990, step: 1 },
        { name: 'body_type', label: 'Body type', type: 'select', required: true, empty: 'Select…', options: BODY_TYPES.map((b) => [b, b === 'suv' ? 'SUV' : b.charAt(0).toUpperCase() + b.slice(1)]) },
        { name: 'drivetrain', label: 'Drivetrain', type: 'select', required: true, empty: 'Select…', options: ['FWD', 'RWD', 'AWD'] },
        { name: 'slug', label: 'URL slug', max: 180, hint: 'Leave blank to generate automatically from brand, model, variant and year.', full: true },
      ]],
      ['Price & battery', [
        { name: 'price_usd', label: 'Price', type: 'number', unit: CFG.currency, min: 0, step: '0.01' },
        { name: 'battery_kwh', label: 'Usable battery', type: 'number', unit: 'kWh', min: 0, step: '0.1' },
        { name: 'range_km', label: 'Range (WLTP)', type: 'number', unit: 'km', min: 0, step: 1 },
        { name: 'efficiency_wh_km', label: 'Efficiency', type: 'number', unit: 'Wh/km', min: 0, step: 1 },
      ]],
      ['Performance', [
        { name: 'acceleration_0_100', label: '0–100 km/h', type: 'number', unit: 's', min: 0, step: '0.1' },
        { name: 'top_speed_kmh', label: 'Top speed', type: 'number', unit: 'km/h', min: 0, step: 1 },
        { name: 'power_kw', label: 'Power', type: 'number', unit: 'kW', min: 0, step: 1 },
        { name: 'torque_nm', label: 'Torque', type: 'number', unit: 'Nm', min: 0, step: 1 },
      ]],
      ['Charging', [
        { name: 'charging_dc_kw', label: 'DC fast charging (max)', type: 'number', unit: 'kW', min: 0, step: 1 },
        { name: 'charge_10_80_min', label: '10–80% charge time', type: 'number', unit: 'min', min: 0, step: 1 },
        { name: 'charging_ac_kw', label: 'AC onboard charger', type: 'number', unit: 'kW', min: 0, step: '0.1' },
      ]],
      ['Practicality', [
        { name: 'seats', label: 'Seats', type: 'number', min: 1, step: 1 },
        { name: 'cargo_l', label: 'Cargo space', type: 'number', unit: 'L', min: 0, step: 1 },
        { name: 'weight_kg', label: 'Curb weight', type: 'number', unit: 'kg', min: 0, step: 1 },
      ]],
      ['Description', [
        { name: 'description', label: 'Description', type: 'textarea', max: 10000, full: true },
      ]],
    ];
  }
  const sideFields = [
    { name: 'status', label: 'Status', type: 'select', options: [['draft', 'Draft (hidden)'], ['published', 'Published (live)']] },
    { name: 'is_featured', label: 'Feature on homepage', type: 'checkbox' },
    { name: 'image_url', label: 'Image URL', type: 'text', max: 500, placeholder: 'https://… or upload below' },
  ];

  async function viewEvForm(id, view = $('#view')) {
    const bl = await brands();
    const ev = id ? (await GET('admin/evs/' + id)).data : { model_year: new Date().getFullYear(), status: 'draft', is_featured: false, seats: 5 };
    const groups = evFieldGroups(bl);
    const allFields = groups.flatMap(([, f]) => f).concat(sideFields);
    const editable = id ? can('vehicles.update') : can('vehicles.create');

    view.innerHTML = `<div class="page-head">
        <div><a href="#/evs" class="muted">← All EVs</a><h1>${id ? h(ev.title) : 'Add EV'}</h1></div>
        ${id && ev.status === 'published' ? `<a class="btn btn-ghost" href="${h(url('ev/' + ev.slug))}" target="_blank" rel="noopener">View on site ↗</a>` : ''}
      </div>
      ${!bl.length ? '<div class="alert warn">Create a brand first (Brands page) before adding EVs.</div>' : ''}
      <form data-ev-form novalidate>
        <div class="alert error" data-form-alert hidden></div>
        <div class="form-grid two">
          <div>${groups.map(([title, fields]) => `<fieldset class="fieldset"><legend>${h(title)}</legend>
            <div class="fields ${fields.length > 3 && title !== 'Basics' ? '' : ''}">${fields.map((f) => field(f, ev[f.name])).join('')}</div></fieldset>`).join('')}
          </div>
          <div>
            <fieldset class="fieldset"><legend>Publishing</legend><div class="fields" style="grid-template-columns:1fr">
              ${field(sideFields[0], ev.status)}${field(sideFields[1], ev.is_featured)}
              ${id ? `<p class="muted" style="margin:0;font-size:.85rem">Created ${dt(ev.created_at)}<br>Updated ${dt(ev.updated_at)}</p>` : ''}
            </div></fieldset>
            <fieldset class="fieldset"><legend>Image</legend>
              <div class="image-preview" data-preview>${ev.image_url ? `<img src="${h(ev.image_url)}" alt="">` : 'No image'}</div>
              <div class="fields" style="grid-template-columns:1fr">
                ${field(sideFields[2], ev.image_url)}
                ${can('uploads.create') ? `<div class="f"><label for="f_upload">Upload image</label><input id="f_upload" type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-upload><div class="hint">JPG, PNG, WEBP or GIF · max 5 MB</div></div>` : ''}
              </div>
            </fieldset>
          </div>
        </div>
        <div class="form-actions">
          ${editable ? `<button class="btn btn-primary" type="submit">${id ? 'Save changes' : 'Create EV'}</button>` : '<span class="muted">You have read-only access.</span>'}
          <a class="btn btn-ghost" href="#/evs">Cancel</a>
        </div>
      </form>`;

    const form = $('[data-ev-form]', view);
    form.addEventListener('input', () => { dirty = true; });
    const preview = $('[data-preview]', view);
    form.elements.image_url.addEventListener('change', (e) => {
      const v = e.target.value.trim();
      preview.innerHTML = v ? `<img src="${h(v)}" alt="">` : 'No image';
    });
    const up = $('[data-upload]', view);
    if (up) up.addEventListener('change', async () => {
      if (!up.files[0]) return;
      const fd = new FormData();
      fd.append('image', up.files[0]);
      preview.textContent = 'Uploading…';
      try {
        const r = await api('POST', 'admin/uploads', fd);
        form.elements.image_url.value = r.data.url;
        preview.innerHTML = `<img src="${h(r.data.url)}" alt="">`;
        dirty = true;
        toast('Image uploaded — remember to save');
      } catch (err) { preview.textContent = 'Upload failed'; toast(err.message, 'error'); }
      up.value = '';
    });

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      clearErrors(form);
      const body = formData(form, allFields);
      const btn = $('button[type=submit]', form);
      btn.disabled = true;
      try {
        const res = id ? await api('PUT', 'admin/evs/' + id, body) : await api('POST', 'admin/evs', body);
        dirty = false;
        toast(id ? 'Changes saved — live on the site now' : 'EV created');
        if (!id) location.hash = '#/evs/' + res.data.id;
        else viewEvForm(id, view);
      } catch (err) { showErrors(form, err); window.scrollTo({ top: 0, behavior: 'smooth' }); } finally { btn.disabled = false; }
    });
  }

  // ------------------------------------------------------------ Brands
  const brandFields = [
    { name: 'name', label: 'Name', required: true, max: 100, full: true },
    { name: 'slug', label: 'URL slug', max: 120, hint: 'Blank = generated from name' },
    { name: 'country', label: 'Country', max: 80 },
    { name: 'website', label: 'Website', max: 255, placeholder: 'https://…', full: true },
    { name: 'logo_url', label: 'Logo URL', max: 500, placeholder: 'https://… or /uploads/…', full: true },
  ];

  async function viewBrands(m, view) {
    const bl = await brands(true);
    view.innerHTML = `<div class="page-head"><h1>Brands</h1>${can('brands.create') ? '<button class="btn btn-primary" data-new>+ Add brand</button>' : ''}</div>
      <div class="table-wrap">${bl.length ? `<table class="tbl"><thead><tr><th>Name</th><th>Slug</th><th>Country</th><th class="num">EVs</th><th class="actions">Actions</th></tr></thead>
      <tbody>${bl.map((b) => `<tr><td><strong>${h(b.name)}</strong></td><td class="muted">${h(b.slug)}</td><td>${h(b.country || '—')}</td><td class="num">${b.ev_count}</td>
        <td class="actions">${can('brands.update') ? `<button class="btn-link" data-edit="${b.id}">Edit</button>` : ''}
        ${can('brands.delete') ? `<button class="btn-link danger" data-del="${b.id}">Delete</button>` : ''}</td></tr>`).join('')}</tbody></table>` : '<div class="empty">No brands yet.</div>'}</div>`;

    view.onclick = async (e) => {
      if (e.target.closest('[data-new]')) return brandDialog(null, view);
      const ed = e.target.closest('[data-edit]');
      if (ed) return brandDialog(bl.find((b) => b.id === Number(ed.dataset.edit)), view);
      const del = e.target.closest('[data-del]');
      if (del) {
        const b = bl.find((x) => x.id === Number(del.dataset.del));
        if (!(await confirmDialog('Delete brand?', `“${b.name}” will be removed. Brands that still have EVs cannot be deleted.`))) return;
        try { await api('DELETE', 'admin/brands/' + b.id); toast('Brand deleted'); viewBrands(m, view); } catch (err) { toast(err.message, 'error'); }
      }
    };
  }

  function modalForm(title, fields, values, onSubmit, extraHtml = '') {
    const d = document.createElement('dialog');
    d.className = 'dialog';
    d.innerHTML = `<form novalidate><h2>${h(title)}</h2><div class="alert error" data-form-alert hidden></div>
      <div class="fields">${fields.map((f) => field(f, values[f.name])).join('')}</div>${extraHtml}
      <div class="dialog-actions"><button type="button" class="btn btn-ghost" data-cancel>Cancel</button><button type="submit" class="btn btn-primary">Save</button></div></form>`;
    document.body.appendChild(d);
    const form = $('form', d);
    $('[data-cancel]', d).addEventListener('click', () => d.close());
    d.addEventListener('close', () => d.remove());
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      clearErrors(form);
      try { await onSubmit(formData(form, fields), form); d.close(); } catch (err) { showErrors(form, err); }
    });
    d.showModal();
    return d;
  }

  function brandDialog(b, view) {
    modalForm(b ? 'Edit brand' : 'Add brand', brandFields, b || {}, async (data) => {
      if (b) await api('PUT', 'admin/brands/' + b.id, data); else await api('POST', 'admin/brands', data);
      toast(b ? 'Brand updated' : 'Brand created');
      state.brands = null;
      viewBrands(null, view);
    });
  }

  // ------------------------------------------------------------ Users
  async function viewUsers(m, view) {
    const users = (await GET('admin/users')).data;
    view.innerHTML = `<div class="page-head"><h1>Users</h1><button class="btn btn-primary" data-new>+ Add user</button></div>
      <p class="muted"><strong>Admin</strong>: full control. <strong>Editor</strong>: create and edit EVs and brands, upload images — cannot delete, manage users or change settings.</p>
      <div class="table-wrap"><table class="tbl"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th><th class="actions">Actions</th></tr></thead>
      <tbody>${users.map((u) => `<tr><td><strong>${h(u.name)}</strong>${u.id === state.user.id ? ' <span class="muted">(you)</span>' : ''}</td><td>${h(u.email)}</td>
        <td><span class="role-badge">${h(u.role)}</span></td>
        <td><span class="pill ${u.is_active ? 'active' : 'inactive'}">${u.is_active ? 'active' : 'disabled'}</span>${u.must_change_password ? ' <span class="pill featured">temp password</span>' : ''}</td>
        <td>${dt(u.last_login_at)}</td>
        <td class="actions"><button class="btn-link" data-edit="${u.id}">Edit</button>
        ${u.id !== state.user.id ? `<button class="btn-link danger" data-del="${u.id}">Delete</button>` : ''}</td></tr>`).join('')}</tbody></table></div>`;

    const fields = (isNew) => [
      { name: 'name', label: 'Name', required: true, max: 100 },
      { name: 'email', label: 'Email', type: 'email', required: true },
      { name: 'role', label: 'Role', type: 'select', options: [['editor', 'Editor'], ['admin', 'Admin']] },
      { name: 'password', label: isNew ? 'Temporary password' : 'Reset password', type: 'password', required: isNew, autocomplete: 'new-password', hint: isNew ? 'Min. 10 chars, letters + numbers' : 'Leave blank to keep current password' },
      { name: 'is_active', label: 'Account active', type: 'checkbox' },
      { name: 'must_change_password', label: 'Require password change at next login', type: 'checkbox' },
    ];
    view.onclick = async (e) => {
      if (e.target.closest('[data-new]')) {
        return modalForm('Add user', fields(true), { role: 'editor', is_active: true, must_change_password: true }, async (data) => {
          await api('POST', 'admin/users', data); toast('User created'); viewUsers(m, view);
        });
      }
      const ed = e.target.closest('[data-edit]');
      if (ed) {
        const u = users.find((x) => x.id === Number(ed.dataset.edit));
        return modalForm('Edit user', fields(false), u, async (data) => {
          if (!data.password) delete data.password;
          await api('PUT', 'admin/users/' + u.id, data); toast('User updated'); viewUsers(m, view);
        });
      }
      const del = e.target.closest('[data-del]');
      if (del) {
        const u = users.find((x) => x.id === Number(del.dataset.del));
        if (!(await confirmDialog('Delete user?', `${u.name} (${u.email}) will lose access immediately.`))) return;
        try { await api('DELETE', 'admin/users/' + u.id); toast('User deleted'); viewUsers(m, view); } catch (err) { toast(err.message, 'error'); }
      }
    };
  }

  // ------------------------------------------------------------ Settings
  async function viewSettings(m, view) {
    const res = await GET('admin/settings');
    const fields = res.schema.map((s) => ({
      name: s.key, label: s.label, max: s.max,
      type: ['hero_subtitle', 'footer_text'].includes(s.key) ? 'textarea' : (['max_compare', 'items_per_page'].includes(s.key) ? 'number' : 'text'),
      full: ['hero_subtitle', 'footer_text', 'site_tagline'].includes(s.key),
    }));
    view.innerHTML = `<div class="page-head"><h1>Site settings</h1></div>
      <form data-settings novalidate><div class="alert error" data-form-alert hidden></div>
        <fieldset class="fieldset"><legend>General</legend><div class="fields">${fields.map((f) => field(f, res.data[f.name])).join('')}</div></fieldset>
        <div class="form-actions"><button class="btn btn-primary" type="submit">Save settings</button></div>
      </form>`;
    const form = $('[data-settings]', view);
    form.addEventListener('input', () => { dirty = true; });
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      clearErrors(form);
      const body = {};
      fields.forEach((f) => { body[f.name] = form.elements[f.name].value; });
      try {
        const r = await api('PUT', 'admin/settings', body);
        dirty = false;
        CFG.siteName = r.data.site_name; CFG.currency = r.data.currency_symbol;
        toast('Settings saved — live on the site now');
      } catch (err) { showErrors(form, err); }
    });
  }

  // ------------------------------------------------------------ Audit
  async function viewAudit(m, view, page = 1) {
    const res = await GET('admin/audit', { page });
    view.innerHTML = `<div class="page-head"><h1>Activity log</h1></div>
      <div class="table-wrap">${res.data.length ? `<table class="tbl"><thead><tr><th>When</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
      <tbody>${res.data.map((a) => `<tr><td>${dt(a.created_at)}</td><td>${h(a.user_name || '—')}</td><td><span class="pill draft">${h(a.action)}</span> ${h(a.entity)}</td>
        <td style="white-space:normal">${h(a.summary || '')}</td><td class="muted">${h(a.ip_address || '')}</td></tr>`).join('')}</tbody></table>` : '<div class="empty">No activity yet.</div>'}</div>
      <div class="pager"><span>${res.meta.total} entries · page ${res.meta.page} of ${res.meta.pages}</span>
        <span class="btns"><button class="btn btn-ghost btn-sm" data-pg="${page - 1}" ${page <= 1 ? 'disabled' : ''}>‹ Newer</button>
        <button class="btn btn-ghost btn-sm" data-pg="${page + 1}" ${page >= res.meta.pages ? 'disabled' : ''}>Older ›</button></span></div>`;
    view.onclick = (e) => { const b = e.target.closest('[data-pg]'); if (b && !b.disabled) viewAudit(m, view, Number(b.dataset.pg)); };
  }

  // ------------------------------------------------------------ Account
  async function viewAccount(m, view) {
    view.innerHTML = `<div class="page-head"><h1>My account</h1></div>
      <div class="form-grid two"><div>
        <fieldset class="fieldset"><legend>Change password</legend>${passwordForm()}</fieldset>
      </div><div>
        <div class="panel"><h2>${h(state.user.name)}</h2><p class="muted">${h(state.user.email)}<br>Role: <span class="role-badge">${h(state.user.role)}</span><br>Last login: ${dt(state.user.last_login_at)}</p></div>
      </div></div>`;
    bindPasswordForm(() => toast('Password updated'));
  }

  // ------------------------------------------------------------ Boot
  function start() {
    if (state.user.must_change_password) return renderForcePassword();
    renderShell();
    if (!location.hash || location.hash === '#/' || location.hash === '#') location.hash = '#/dashboard';
    else route();
  }

  (async function boot() {
    try {
      const res = await fetch(url('api/auth/me'), { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then((r) => r.json());
      if (res.csrf_token) state.csrf = res.csrf_token;
      state.user = res.data;
      if (state.user) start(); else renderLogin();
    } catch (err) {
      root.innerHTML = `<div class="auth-wrap"><div class="alert error">Could not reach the API: ${h(err.message)}</div></div>`;
    }
  })();
})();
