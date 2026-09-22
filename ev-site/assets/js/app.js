/* e-carscompare — public front-end. No build step, no dependencies. */
(function () {
  'use strict';

  const APP = window.APP || { base: '', page: '', currency: '$', maxCompare: 4 };
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  // ------------------------------------------------------------------ utils
  const h = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const url = (p) => APP.base + '/' + String(p).replace(/^\//, '');
  const cap = (s) => String(s || '').charAt(0).toUpperCase() + String(s || '').slice(1);
  const label = (s) => (String(s) === 'suv' ? 'SUV' : cap(s));

  async function api(path, params) {
    const qs = params ? '?' + new URLSearchParams(params).toString() : '';
    const res = await fetch(url('api/' + path) + qs, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    const body = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error((body.error && body.error.message) || 'Request failed (' + res.status + ')');
    return body;
  }

  const nf = new Intl.NumberFormat(undefined, { maximumFractionDigits: 1 });
  function fmt(value, unit) {
    if (value === null || value === undefined || value === '') return '—';
    if (unit === 'year') return String(value);
    if (unit === 'currency') return APP.currency + new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 }).format(value);
    if (typeof value === 'number') return nf.format(value) + (unit ? ' ' + unit : '');
    if (unit === '' && /^[a-z]+$/.test(value)) return label(value);
    return String(value) + (unit ? ' ' + unit : '');
  }

  function toast(msg) {
    const el = $('[data-toast]');
    if (!el) return;
    el.textContent = msg;
    el.hidden = false;
    clearTimeout(toast.t);
    toast.t = setTimeout(() => { el.hidden = true; }, 2600);
  }

  function debounce(fn, ms) {
    let t;
    return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); };
  }

  const carSvg = '<svg class="placeholder-car" viewBox="0 0 64 32" aria-hidden="true"><path d="M8 22c-3 0-5-2-5-4v-3c0-2 2-3 4-4l8-4c3-2 6-3 10-3h12c4 0 7 1 10 4l5 5 6 1c2 0 3 2 3 4v2c0 1-1 2-2 2h-3a6 6 0 0 0-12 0H22a6 6 0 0 0-12 0Zm8 2a4 4 0 1 1 0 8 4 4 0 0 1 0-8Zm30 0a4 4 0 1 1 0 8 4 4 0 0 1 0-8Z"/></svg>';

  // --------------------------------------------------------- compare store
  const Compare = {
    key: 'ev_compare',
    read() {
      try { const v = JSON.parse(localStorage.getItem(this.key) || '[]'); return Array.isArray(v) ? v.slice(0, APP.maxCompare) : []; }
      catch (e) { return this._mem || []; }
    },
    write(list) {
      this._mem = list;
      try { localStorage.setItem(this.key, JSON.stringify(list)); } catch (e) { /* private mode */ }
      this.render();
      document.dispatchEvent(new CustomEvent('compare:change'));
    },
    has(id) { return this.read().some((x) => x.id === id); },
    add(ev) {
      const list = this.read();
      if (list.some((x) => x.id === ev.id)) return true;
      if (list.length >= APP.maxCompare) { toast('You can compare up to ' + APP.maxCompare + ' EVs. Remove one first.'); return false; }
      list.push({ id: ev.id, title: ev.title });
      this.write(list);
      toast('Added ' + ev.title + ' to comparison');
      return true;
    },
    remove(id) { this.write(this.read().filter((x) => x.id !== id)); },
    toggle(ev) { if (this.has(ev.id)) { this.remove(ev.id); return false; } return this.add(ev); },
    clear() { this.write([]); },
    render() {
      const list = this.read();
      $$('[data-compare-count]').forEach((b) => { b.textContent = list.length; b.hidden = list.length === 0; });
      const tray = $('[data-compare-tray]');
      if (tray) {
        tray.hidden = list.length === 0;
        document.body.classList.toggle('has-tray', list.length > 0);
        $('[data-compare-items]', tray).innerHTML = list.map((x) =>
          `<span class="tray-item">${h(x.title)} <button type="button" data-tray-remove="${x.id}" aria-label="Remove ${h(x.title)}">&times;</button></span>`
        ).join('');
      }
      $$('[data-compare-id]').forEach((cb) => { cb.checked = list.some((x) => x.id === Number(cb.dataset.compareId)); });
      $$('[data-compare-toggle]').forEach((btn) => {
        const on = list.some((x) => x.id === Number(btn.dataset.compareToggle));
        btn.textContent = on ? '✓ In comparison' : 'Add to compare';
        btn.classList.toggle('is-active', on);
      });
    },
  };

  // ------------------------------------------------------------- components
  function card(ev) {
    const img = ev.image_url ? `<img src="${h(ev.image_url)}" alt="" loading="lazy">` : carSvg;
    const checked = Compare.has(ev.id) ? 'checked' : '';
    return `<article class="card">
      <div class="card-media">${img}</div>
      ${ev.is_featured ? '<span class="card-tag">Featured</span>' : ''}
      <div class="card-body">
        <p class="card-eyebrow">${h(ev.brand.name)} · ${h(ev.model_year)} · ${h(label(ev.body_type))}</p>
        <h3 class="card-title"><a href="${h(url('ev/' + ev.slug))}">${h(ev.model)} <span class="variant">${h(ev.variant || '')}</span></a></h3>
        <p class="card-price">${fmt(ev.price_usd, 'currency')}</p>
        <dl class="card-specs">
          <div><dt>Range</dt><dd>${fmt(ev.range_km, 'km')}</dd></div>
          <div><dt>0–100</dt><dd>${fmt(ev.acceleration_0_100, 's')}</dd></div>
          <div><dt>DC</dt><dd>${fmt(ev.charging_dc_kw, 'kW')}</dd></div>
        </dl>
      </div>
      <div class="card-actions">
        <label class="compare-check"><input type="checkbox" data-compare-id="${ev.id}" data-compare-title="${h(ev.title)}" ${checked}> Compare</label>
      </div>
    </article>`;
  }

  function resultsTable(evs) {
    return `<div class="results-table-wrap"><table class="data-table">
      <thead><tr><th>EV</th><th>Body</th><th class="num">Price</th><th class="num">Range</th><th class="num">Battery</th>
      <th class="num">0–100</th><th class="num">DC kW</th><th>Drive</th><th>Compare</th></tr></thead>
      <tbody>${evs.map((ev) => `<tr>
        <td><a href="${h(url('ev/' + ev.slug))}">${h(ev.title)}</a></td>
        <td>${h(label(ev.body_type))}</td>
        <td class="num">${fmt(ev.price_usd, 'currency')}</td>
        <td class="num">${fmt(ev.range_km, 'km')}</td>
        <td class="num">${fmt(ev.battery_kwh, 'kWh')}</td>
        <td class="num">${fmt(ev.acceleration_0_100, 's')}</td>
        <td class="num">${fmt(ev.charging_dc_kw, '')}</td>
        <td>${h(ev.drivetrain)}</td>
        <td><label class="compare-check"><input type="checkbox" data-compare-id="${ev.id}" data-compare-title="${h(ev.title)}" ${Compare.has(ev.id) ? 'checked' : ''}><span class="sr-only">Compare ${h(ev.title)}</span></label></td>
      </tr>`).join('')}</tbody></table></div>`;
  }

  function pagination(el, meta, onPage) {
    if (!el) return;
    if (meta.pages <= 1) { el.innerHTML = ''; return; }
    const p = meta.page, n = meta.pages, items = [];
    const add = (i) => items.push(`<button type="button" data-page="${i}" ${i === p ? 'aria-current="page"' : ''}>${i}</button>`);
    items.push(`<button type="button" data-page="${p - 1}" ${p <= 1 ? 'disabled' : ''} aria-label="Previous page">‹</button>`);
    for (let i = 1; i <= n; i++) {
      if (i === 1 || i === n || Math.abs(i - p) <= 1) add(i);
      else if (items[items.length - 1] !== '…') items.push('…');
    }
    items.push(`<button type="button" data-page="${p + 1}" ${p >= n ? 'disabled' : ''} aria-label="Next page">›</button>`);
    el.innerHTML = items.map((x) => (x === '…' ? '<span aria-hidden="true">…</span>' : x)).join('');
    el.onclick = (e) => { const b = e.target.closest('[data-page]'); if (b && !b.disabled) onPage(Number(b.dataset.page)); };
  }

  // ------------------------------------------------------------ global UI
  function initGlobal() {
    const toggle = $('.nav-toggle'), nav = $('#site-nav');
    if (toggle && nav) {
      toggle.addEventListener('click', () => {
        const open = nav.classList.toggle('open');
        toggle.setAttribute('aria-expanded', String(open));
      });
    }
    document.addEventListener('change', (e) => {
      const cb = e.target.closest('[data-compare-id]');
      if (!cb) return;
      const ev = { id: Number(cb.dataset.compareId), title: cb.dataset.compareTitle };
      if (cb.checked) { if (!Compare.add(ev)) cb.checked = false; } else Compare.remove(ev.id);
    });
    document.addEventListener('click', (e) => {
      const rm = e.target.closest('[data-tray-remove]');
      if (rm) Compare.remove(Number(rm.dataset.trayRemove));
      if (e.target.closest('[data-compare-clear]')) Compare.clear();
    });
    window.addEventListener('storage', (e) => { if (e.key === Compare.key) Compare.render(); });
    Compare.render();
  }

  // ------------------------------------------------------------------ home
  async function initHome() {
    try {
      const [meta, featured, brands] = await Promise.all([api('meta'), api('evs', { featured: 1, per_page: 4 }), api('brands')]);
      let evs = featured.data;
      if (!evs.length) evs = (await api('evs', { per_page: 4 })).data;
      $('[data-featured]').innerHTML = evs.length ? evs.map(card).join('') : '<p class="empty-state">No EVs published yet.</p>';

      const b = meta.bounds || {};
      $('[data-hero-stats]').innerHTML = b.total ? `
        <div><strong>${h(b.total)}</strong>EVs listed</div>
        <div><strong>${fmt(b.max_range, 'km')}</strong>longest range</div>
        <div><strong>${fmt(b.min_price, 'currency')}</strong>starting price</div>` : '';

      $('[data-body-types]').innerHTML = meta.body_types.map((t) => `<a class="chip" href="${h(url('evs?body_type=' + t))}">${h(label(t))}</a>`).join('');
      $('[data-brands]').innerHTML = brands.data.filter((x) => x.ev_count > 0).map((x) =>
        `<a class="brand-tile" href="${h(url('evs?brand=' + x.slug))}"><strong>${h(x.name)}</strong><span>${x.ev_count} EV${x.ev_count === 1 ? '' : 's'}${x.country ? ' · ' + h(x.country) : ''}</span></a>`
      ).join('');
    } catch (err) {
      $('[data-featured]').innerHTML = `<p class="empty-state">${h(err.message)}</p>`;
    }
  }

  // --------------------------------------------------------------- catalog
  async function initCatalog() {
    const form = $('[data-filter-form]');
    const resultsEl = $('[data-results]');
    const sortEl = $('[data-sort]');
    const multi = ['brand', 'body_type', 'drivetrain'];
    const scalar = ['q', 'min_price', 'max_price', 'min_range', 'min_seats', 'min_dc'];
    let view = 'grid';
    try { view = localStorage.getItem('ev_view') || 'grid'; } catch (e) { /* ignore */ }

    // State from URL
    const params = new URLSearchParams(location.search);
    const state = { page: Number(params.get('page')) || 1, sort: params.get('sort') || 'featured' };
    multi.forEach((k) => { state[k] = (params.get(k) || '').split(',').filter(Boolean); });
    scalar.forEach((k) => { state[k] = params.get(k) || ''; });

    const [meta, brands] = await Promise.all([api('meta'), api('brands')]);
    const checks = (name, items) => items.map((it) =>
      `<label><input type="checkbox" name="${name}" value="${h(it.value)}" ${state[name].includes(String(it.value)) ? 'checked' : ''}> ${h(it.label)}${it.count !== undefined ? ` <small>${it.count}</small>` : ''}</label>`
    ).join('');
    $('[data-filter-brands]').innerHTML = checks('brand', brands.data.filter((b) => b.ev_count > 0).map((b) => ({ value: b.slug, label: b.name, count: b.ev_count })));
    $('[data-filter-bodies]').innerHTML = checks('body_type', meta.body_types.map((t) => ({ value: t, label: label(t) })));
    $('[data-filter-drives]').innerHTML = checks('drivetrain', meta.drivetrains.map((t) => ({ value: t, label: t })));

    const rangeInput = form.elements.min_range;
    if (meta.bounds && meta.bounds.max_range) rangeInput.max = String(Math.ceil(meta.bounds.max_range / 50) * 50);
    scalar.forEach((k) => { if (form.elements[k]) form.elements[k].value = state[k]; });
    sortEl.value = state.sort;
    const rangeOut = $('[data-range-out]');
    const showRange = () => { rangeOut.textContent = Number(rangeInput.value) > 0 ? rangeInput.value + ' km' : 'any'; };
    showRange();

    const brandNames = Object.fromEntries(brands.data.map((b) => [b.slug, b.name]));

    function readForm() {
      multi.forEach((k) => { state[k] = $$(`input[name="${k}"]:checked`, form).map((i) => i.value); });
      scalar.forEach((k) => { state[k] = form.elements[k] ? String(form.elements[k].value).trim() : ''; });
      if (state.min_range === '0') state.min_range = '';
    }

    function query() {
      const q = {};
      multi.forEach((k) => { if (state[k].length) q[k] = state[k].join(','); });
      scalar.forEach((k) => { if (state[k] !== '') q[k] = state[k]; });
      if (state.sort && state.sort !== 'featured') q.sort = state.sort;
      if (state.page > 1) q.page = state.page;
      return q;
    }

    function renderChips() {
      const chips = [];
      multi.forEach((k) => state[k].forEach((v) => chips.push({ k, v, label: k === 'brand' ? (brandNames[v] || v) : v })));
      const labels = { q: (v) => '“' + v + '”', min_price: (v) => 'From ' + fmt(Number(v), 'currency'), max_price: (v) => 'Up to ' + fmt(Number(v), 'currency'),
        min_range: (v) => v + '+ km', min_seats: (v) => v + '+ seats', min_dc: (v) => v + '+ kW DC' };
      scalar.forEach((k) => { if (state[k] !== '') chips.push({ k, v: state[k], label: labels[k](state[k]) }); });
      $('[data-active-chips]').innerHTML = chips.map((c) =>
        `<button type="button" class="chip chip-remove" data-chip-k="${h(c.k)}" data-chip-v="${h(c.v)}" aria-label="Remove filter ${h(c.label)}">${h(c.label)}</button>`
      ).join('');
      const badge = $('[data-active-filters]');
      badge.textContent = chips.length;
      badge.hidden = chips.length === 0;
    }

    let reqId = 0;
    async function load(push) {
      const id = ++reqId;
      const q = query();
      const qs = new URLSearchParams(q).toString();
      history[push ? 'pushState' : 'replaceState'](null, '', location.pathname + (qs ? '?' + qs : ''));
      renderChips();
      resultsEl.setAttribute('aria-busy', 'true');
      try {
        const res = await api('evs', q);
        if (id !== reqId) return;
        if (res.meta.page > res.meta.pages && res.meta.total > 0) { state.page = res.meta.pages; return load(false); }
        $('[data-result-count]').textContent = res.meta.total + ' electric vehicle' + (res.meta.total === 1 ? '' : 's') + ' found';
        if (!res.data.length) {
          resultsEl.className = '';
          resultsEl.innerHTML = '<div class="empty-state"><h2>No EVs match these filters</h2><p>Try removing a filter or widening the price range.</p></div>';
        } else if (view === 'table') {
          resultsEl.className = '';
          resultsEl.innerHTML = resultsTable(res.data);
        } else {
          resultsEl.className = 'card-grid';
          resultsEl.innerHTML = res.data.map(card).join('');
        }
        pagination($('[data-pagination]'), res.meta, (p) => { state.page = p; load(true); window.scrollTo({ top: 0, behavior: 'smooth' }); });
      } catch (err) {
        resultsEl.innerHTML = `<p class="empty-state">${h(err.message)}</p>`;
      } finally {
        resultsEl.removeAttribute('aria-busy');
      }
    }

    const onChange = () => { readForm(); state.page = 1; load(false); };
    form.addEventListener('change', onChange);
    form.addEventListener('input', debounce((e) => { if (e.target.name === 'q' || e.target.type === 'number') onChange(); }, 350));
    rangeInput.addEventListener('input', showRange);
    form.addEventListener('submit', (e) => { e.preventDefault(); onChange(); });
    form.addEventListener('reset', () => setTimeout(() => { showRange(); onChange(); }, 0));
    sortEl.addEventListener('change', () => { state.sort = sortEl.value; state.page = 1; load(false); });

    $('[data-active-chips]').addEventListener('click', (e) => {
      const c = e.target.closest('[data-chip-k]');
      if (!c) return;
      const k = c.dataset.chipK, v = c.dataset.chipV;
      if (multi.includes(k)) { const cb = $(`input[name="${k}"][value="${CSS.escape(v)}"]`, form); if (cb) cb.checked = false; }
      else if (form.elements[k]) form.elements[k].value = k === 'min_range' ? '0' : '';
      showRange();
      onChange();
    });

    // View toggle
    const setView = (v) => {
      view = v;
      try { localStorage.setItem('ev_view', v); } catch (e) { /* ignore */ }
      $$('[data-view]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.view === v)));
    };
    setView(view);
    $$('[data-view]').forEach((b) => b.addEventListener('click', () => { setView(b.dataset.view); load(false); }));

    // Mobile drawer
    const panel = $('[data-filters]'), backdrop = $('[data-filters-backdrop]');
    const openF = () => { panel.classList.add('open'); backdrop.hidden = false; $('input, select', panel).focus(); };
    const closeF = () => { panel.classList.remove('open'); backdrop.hidden = true; };
    $('[data-filters-open]').addEventListener('click', openF);
    $('[data-filters-close]').addEventListener('click', closeF);
    backdrop.addEventListener('click', closeF);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeF(); });

    window.addEventListener('popstate', () => location.reload());
    document.addEventListener('compare:change', () => Compare.render());
    load(false);
  }

  // ---------------------------------------------------------------- detail
  async function initDetail() {
    const root = $('[data-detail]');
    try {
      const [res, meta] = await Promise.all([api('evs/' + encodeURIComponent(root.dataset.evSlug)), api('meta')]);
      const ev = res.data;
      $('[data-detail-price]').textContent = ev.price_usd ? 'From ' + fmt(ev.price_usd, 'currency') : 'Price TBA';
      if (!ev.image_url) $('[data-detail-image]').innerHTML = carSvg;

      const keys = [['range_km', 'Range', 'km'], ['acceleration_0_100', '0–100 km/h', 's'], ['battery_kwh', 'Battery', 'kWh'], ['charging_dc_kw', 'DC charging', 'kW']];
      $('[data-key-stats]').innerHTML = keys.map(([k, l, u]) =>
        `<div class="stat"><dt>${h(l)}</dt><dd>${fmt(ev[k], u)}</dd></div>`).join('');

      const groups = {};
      meta.specs.forEach((s) => { (groups[s.group] = groups[s.group] || []).push(s); });
      $('[data-spec-groups]').innerHTML = Object.entries(groups).map(([g, specs]) => `
        <section class="spec-group"><h3>${h(g)}</h3><dl>${specs.map((s) =>
          `<div class="row"><dt>${h(s.label)}</dt><dd>${fmt(ev[s.key], s.unit)}</dd></div>`).join('')}</dl></section>`).join('');

      $('[data-related]').innerHTML = res.related.length ? res.related.map(card).join('') : '<p class="muted">No similar EVs yet.</p>';
      $$('[data-compare-toggle]').forEach((btn) => btn.addEventListener('click', () => { Compare.toggle({ id: ev.id, title: ev.title }); }));
      Compare.render();
    } catch (err) {
      $('[data-spec-groups]').innerHTML = `<p class="empty-state">${h(err.message)}</p>`;
    }
  }

  // --------------------------------------------------------------- compare
  async function initCompare() {
    const root = $('[data-compare-root]');
    const diffOnly = $('[data-diff-only]');
    $$('[data-max-compare]').forEach((el) => { el.textContent = APP.maxCompare; });

    // A shared link (?ids=1,2) replaces the stored selection
    const shared = new URLSearchParams(location.search).get('ids');
    if (shared) {
      const ids = shared.split(',').map(Number).filter((n) => n > 0).slice(0, APP.maxCompare);
      Compare.write(ids.map((id) => ({ id, title: '#' + id })));
    }

    async function render() {
      const list = Compare.read();
      const ids = list.map((x) => x.id);
      history.replaceState(null, '', location.pathname + (ids.length ? '?ids=' + ids.join(',') : ''));
      if (!ids.length) {
        root.innerHTML = `<div class="empty-state"><h2>Nothing to compare yet</h2><p>Add EVs with the search box above, or tick “Compare” on any EV card.</p>
          <p><a class="btn btn-primary" href="${h(url('evs'))}">Browse EVs</a></p></div>`;
        return;
      }
      try {
        const res = await api('compare', { ids: ids.join(',') });
        // Sync titles and drop EVs that were unpublished/deleted
        const found = res.data.map((ev) => ({ id: ev.id, title: ev.title }));
        if (JSON.stringify(found) !== JSON.stringify(list)) { Compare._mem = found; try { localStorage.setItem(Compare.key, JSON.stringify(found)); } catch (e) { /* ignore */ } Compare.render(); }
        if (!res.data.length) { Compare.clear(); return render(); }
        root.innerHTML = table(res);
      } catch (err) {
        root.innerHTML = `<p class="empty-state">${h(err.message)}</p>`;
      }
    }

    function table(res) {
      const evs = res.data;
      const slots = APP.maxCompare - evs.length;
      const head = evs.map((ev) => `<th scope="col"><div class="compare-head">
          <div class="thumb">${ev.image_url ? `<img src="${h(ev.image_url)}" alt="">` : carSvg}</div>
          <a href="${h(url('ev/' + ev.slug))}">${h(ev.title)}</a>
          <button type="button" class="remove" data-remove="${ev.id}">Remove</button>
        </div></th>`).join('') + Array.from({ length: slots }, () => '<th scope="col"><div class="compare-slot">Add another EV<br>using the search above</div></th>').join('');

      let lastGroup = '';
      const rows = res.specs.map((s) => {
        const vals = evs.map((ev) => ev[s.key]);
        const same = vals.every((v) => String(v) === String(vals[0]));
        const hide = diffOnly.checked && evs.length > 1 && same;
        let out = '';
        if (s.group !== lastGroup) { lastGroup = s.group; out += `<tr class="group-row"><th colspan="${evs.length + slots + 1}">${h(s.group)}</th></tr>`; }
        if (hide) return out;
        const best = res.best[s.key] || [];
        out += `<tr><th scope="row">${h(s.label)}</th>${evs.map((ev) =>
          `<td class="${best.includes(ev.id) ? 'best' : ''}">${fmt(ev[s.key], s.unit)}</td>`).join('')}${'<td></td>'.repeat(slots)}</tr>`;
        return out;
      }).join('');
      return `<table class="compare-table"><thead><tr><th scope="col"><span class="sr-only">Specification</span></th>${head}</tr></thead><tbody>${rows}</tbody></table>`;
    }

    root.addEventListener('click', (e) => { const b = e.target.closest('[data-remove]'); if (b) Compare.remove(Number(b.dataset.remove)); });
    diffOnly.addEventListener('change', render);
    document.addEventListener('compare:change', render);

    // Autocomplete
    const input = $('#compare-add'), list = $('#compare-suggest');
    let items = [], active = -1;
    const close = () => { list.hidden = true; input.setAttribute('aria-expanded', 'false'); active = -1; };
    const paint = () => {
      list.innerHTML = items.length ? items.map((ev, i) =>
        `<li role="option" id="sg-${i}" data-i="${i}" aria-selected="${i === active}">${h(ev.title)}<small>${fmt(ev.price_usd, 'currency')} · ${fmt(ev.range_km, 'km')}</small></li>`).join('')
        : '<li aria-disabled="true">No matches</li>';
      list.hidden = false;
      input.setAttribute('aria-expanded', 'true');
      if (active >= 0) input.setAttribute('aria-activedescendant', 'sg-' + active); else input.removeAttribute('aria-activedescendant');
    };
    const choose = (i) => { const ev = items[i]; if (ev && Compare.add({ id: ev.id, title: ev.title })) { input.value = ''; close(); } };
    input.addEventListener('input', debounce(async () => {
      const q = input.value.trim();
      if (q.length < 1) return close();
      try {
        const res = await api('evs', { q, per_page: 8, sort: 'name_asc' });
        const inList = Compare.read().map((x) => x.id);
        items = res.data.filter((ev) => !inList.includes(ev.id));
        active = -1;
        paint();
      } catch (e) { close(); }
    }, 200));
    input.addEventListener('keydown', (e) => {
      if (list.hidden) return;
      if (e.key === 'ArrowDown') { active = Math.min(items.length - 1, active + 1); paint(); e.preventDefault(); }
      else if (e.key === 'ArrowUp') { active = Math.max(0, active - 1); paint(); e.preventDefault(); }
      else if (e.key === 'Enter') { e.preventDefault(); choose(active >= 0 ? active : 0); }
      else if (e.key === 'Escape') close();
    });
    list.addEventListener('mousedown', (e) => { const li = e.target.closest('[data-i]'); if (li) { e.preventDefault(); choose(Number(li.dataset.i)); } });
    input.addEventListener('blur', () => setTimeout(close, 100));

    render();
  }

  // ------------------------------------------------------------------ boot
  document.addEventListener('DOMContentLoaded', () => {
    initGlobal();
    const pages = { home: initHome, catalog: initCatalog, detail: initDetail, compare: initCompare };
    if (pages[APP.page]) pages[APP.page]().catch((err) => { console.error(err); toast(err.message); });
  });
})();
