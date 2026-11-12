// Tutor → Class → Tutes → Deliveries (tutor/couriered-tute.php?class-id=&tute-id=).
// One row per student who gets a posted tute: address, step (to pack, packed, sent, delivered, returned), courier,
// tracking number. Tick rows to move them to the next step together; Edit one row to fix its address.
// Data: GET /tutor/classes/{id}/courier-tutes, GET/POST /tutor/tutes/{id}/deliveries, POST .../deliveries/sync.
import { h, fill, toCsv, download } from '../core/dom.js';
import { ApiClient } from '../core/ApiClient.js';
import { QuickGuide } from '../components/QuickGuide.js';
import { StatCard } from '../components/StatCard.js';
import { BsModal } from '../components/BsModal.js';
import { Toast } from '../components/Toast.js';
import { icon } from '../core/icons.js';

const api = new ApiClient();
const root = document.getElementById('deliveries-root');
const classId = Number(root.dataset.classId || 0);
const TONE = { pending: 'wait', packed: 'info', dispatched: 'blue', delivered: 'good', returned: 'bad' };
const NEXT = { pending: 'packed', packed: 'dispatched', dispatched: 'delivered' };

const state = { tutes: [], tuteId: Number(root.dataset.tuteId || 0), data: null, q: '', step: '', ticked: new Set() };

QuickGuide.attachAll();
const addBtn = h('a', { href: `add-tute?class-id=${classId}`, class: 'custom-btn ex-btn-lg px-4 border-0 d-inline-flex align-items-center gap-2 text-decoration-none' }, icon('add', { size: 20 }), 'Add Tute');
document.getElementById('deliveries-actions').append(addBtn);

skeleton();
loadTutes();

function skeleton() {
  const bone = (w, hgt) => h('span', { class: 'ex-skeleton d-block', style: { width: w, height: `${hgt}px` } });
  fill(root, h('div', { 'aria-busy': 'true' },
    h('div', { class: 'row g-3 mb-4' }, [0, 1, 2, 3].map(() => h('div', { class: 'col-12 col-md-6 col-xl-3' }, h('div', { class: 'ex-skel-card ex-skel-stat' }, bone('45%', 12), h('div', { class: 'mt-3' }, bone('30%', 26)))))),
    h('div', { class: 'ex-skel-card' }, bone('100%', 44), h('div', { class: 'mt-3' }, [0, 1, 2, 3, 4].map(() => h('div', { class: 'd-flex gap-3 py-3', style: { borderBottom: '1px solid var(--line-color)' } }, bone('18px', 18), bone('25%', 14), bone('35%', 14), bone('12%', 14)))))));
}

async function loadTutes() {
  try {
    const d = (await api.get(`/tutor/classes/${classId}/courier-tutes`)).data;
    state.tutes = d.tutes;
    if (!state.tutes.length) return drawNoTutes();
    if (!state.tutes.some((t) => t.id === state.tuteId)) state.tuteId = state.tutes[0].id;
    await loadDeliveries();
  } catch (e) {
    showError(e, loadTutes);
  }
}

async function loadDeliveries() {
  try {
    state.data = (await api.get(`/tutor/tutes/${state.tuteId}/deliveries`)).data;
    state.ticked.clear();
    history.replaceState(null, '', `?class-id=${classId}&tute-id=${state.tuteId}`);
    draw();
  } catch (e) {
    showError(e, loadDeliveries);
  }
}

function showError(e, retry) {
  fill(root, h('div', { class: 'component-theme text-center py-5' },
    h('p', { class: 'main-text mb-1' }, 'Couldn\'t load the deliveries'),
    h('p', { class: 'text-14 sub-text' }, e.message),
    h('button', { type: 'button', class: 'custom-btn ex-btn-md px-4 border-0', onclick: () => { skeleton(); retry(); } }, 'Try again')));
}

function drawNoTutes() {
  fill(root, h('div', { class: 'component-theme text-center py-5 px-3' },
    h('span', { class: 'ex-empty-ico mb-3' }, icon('location', { size: 28 })),
    h('div', { class: 'ex-panel-title mb-1' }, 'No posted tutes yet'),
    h('p', { class: 'text-14 sub-text mb-4' }, 'Add a tute and choose "Post to their home". A parcel is made for each student, with their address.'),
    h('a', { class: 'custom-btn ex-btn-md px-4 border-0 d-inline-flex align-items-center gap-2 text-decoration-none', href: `add-tute?class-id=${classId}` }, icon('add', { size: 18 }), 'Add Tute')));
}

// ---------- helpers ----------
const plural = (n, one, many = `${one}s`) => `${n} ${n === 1 ? one : many}`;
const day = (d) => (d ? new Date(`${d}T00:00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }) : '');
const pill = (status) => h('span', { class: `ex-hd-pill ex-hd-tone-${TONE[status] ?? ''}` }, state.data.steps[status] ?? status);
const address = (d) => [d.address_line_1, d.address_line_2, d.city, d.postal_code].filter(Boolean).join(', ');

function visible() {
  const q = state.q.trim().toLowerCase();
  return state.data.deliveries.filter((d) => (!state.step || (state.step === 'noaddr' ? !d.has_address : d.status === state.step))
    && (!q || `${d.name} ${d.student} ${d.phone} ${address(d)} ${d.tracking_no}`.toLowerCase().includes(q)));
}

// ---------- page ----------
function draw() {
  const { tute, deliveries } = state.data;
  const count = (fn) => deliveries.filter(fn).length;
  const toPack = count((d) => d.status === 'pending' || d.status === 'packed');
  const sent = count((d) => d.status === 'dispatched');
  const delivered = count((d) => d.status === 'delivered');
  const returned = count((d) => d.status === 'returned');
  const noAddr = count((d) => !d.has_address && d.status !== 'delivered');

  const picker = h('select', { class: 'form-select custom-select', 'aria-label': 'Choose a tute', onchange: (e) => { state.tuteId = Number(e.target.value); skeleton(); loadDeliveries(); } },
    state.tutes.map((t) => h('option', { value: t.id, selected: t.id === state.tuteId ? true : null }, `${t.title}${t.month ? ` (${t.month})` : ''}: ${t.delivered} of ${t.total} delivered`)));

  fill(root,
    h('div', { class: 'component-theme p-4 mb-4' },
      h('div', { class: 'row gx-4 gy-3 align-items-end' },
        h('div', { class: 'col-12 col-lg-5' }, h('label', { class: 'text-14 main-text mb-2' }, 'Posted tute'), picker),
        h('div', { class: 'col-12 col-lg-7' },
          h('div', { class: 'ex-dl-facts' },
            fact('payments', 'Who gets it', tute.month ? `Paid for ${tute.month}` : 'Every student'),
            fact('location', 'Courier', tute.courier_name || 'Not chosen yet'),
            fact('calendar', 'Posting on', tute.dispatch_on ? day(tute.dispatch_on) : 'Not set'))))),
    StatCard.row([
      { tone: 'blue', icon: 'tutes', label: 'Parcels', value: deliveries.length, caption: tute.month ? `students who paid for ${tute.month}` : 'one for each student' },
      { tone: 'orange', icon: 'clock', label: 'To pack and send', value: toPack, caption: noAddr ? `${plural(noAddr, 'student')} without an address` : 'all have an address' },
      { tone: 'purple', icon: 'location', label: 'Sent', value: sent, caption: 'on the way' },
      { tone: 'green', icon: 'check', label: 'Delivered', value: delivered, caption: returned ? `${returned} returned` : (deliveries.length ? `${Math.round((delivered / deliveries.length) * 100)}% done` : 'nothing yet') },
    ], { cols: 'col-12 col-md-6 col-xl-3' }),
    h('div', { class: 'component-theme p-4' }, toolbar(noAddr), table()));
}

const fact = (ico, k, v) => h('div', { class: 'ex-dl-fact' }, h('span', { class: 'ex-dl-fact-ico' }, icon(ico, { size: 18 })), h('span', {}, h('span', { class: 'ex-dl-fact-k' }, k), h('span', { class: 'ex-dl-fact-v' }, v)));

function toolbar(noAddr) {
  const steps = [['', 'All'], ...Object.entries(state.data.steps), ...(noAddr ? [['noaddr', 'No address']] : [])];
  const search = h('input', { type: 'search', class: 'form-control custom-input', placeholder: 'Search name, phone, town or tracking number', value: state.q,
    oninput: (e) => { state.q = e.target.value; redrawTable(); } });
  const ticked = state.ticked.size;
  return h('div', { class: 'mb-3' },
    h('div', { class: 'd-flex flex-wrap gap-3 align-items-center mb-3' },
      h('div', { class: 'ex-dl-search flex-grow-1' }, icon('search', { size: 18 }), search),
      h('button', { type: 'button', class: 'custom-outline-btn px-3 d-inline-flex align-items-center gap-2', onclick: sync, title: 'Students who paid or joined after the tute was added' }, icon('refresh', { size: 18 }), 'Add students who paid later'),
      h('button', { type: 'button', class: 'custom-outline-btn px-3 d-inline-flex align-items-center gap-2', onclick: printLabels }, icon('copy', { size: 18 }), 'Print labels'),
      h('button', { type: 'button', class: 'custom-outline-btn px-3 d-inline-flex align-items-center gap-2', onclick: exportCsv }, icon('export', { size: 18 }), 'Download list')),
    h('div', { class: 'ex-dl-steps', role: 'tablist', 'aria-label': 'Show deliveries by step' }, steps.map(([k, label]) => {
      const n = k === '' ? state.data.deliveries.length : k === 'noaddr' ? noAddr : state.data.deliveries.filter((d) => d.status === k).length;
      return h('button', { type: 'button', role: 'tab', class: `ex-dl-step${state.step === k ? ' active' : ''}${k === 'noaddr' ? ' warn' : ''}`, 'aria-selected': String(state.step === k), onclick: () => { state.step = k; draw(); } }, label, h('span', { class: 'ex-dl-step-n' }, String(n)));
    })),
    ticked ? bulkBar(ticked) : null);
}

function bulkBar(n) {
  const stepSel = h('select', { class: 'form-select custom-select', 'aria-label': 'Move ticked parcels to' },
    Object.entries(state.data.steps).map(([k, v]) => h('option', { value: k, selected: k === 'dispatched' ? true : null }, v)));
  const courierSel = h('select', { class: 'form-select custom-select', 'aria-label': 'Courier' },
    h('option', { value: '' }, '- Keep courier -'), state.data.couriers.map((c) => h('option', { value: c, selected: c === state.data.tute.courier_name ? true : null }, c)));
  return h('div', { class: 'ex-dl-bulk' },
    h('span', { class: 'text-14 main-text fw-medium' }, `${plural(n, 'parcel')} ticked`),
    h('div', { class: 'd-flex flex-wrap gap-2 align-items-center ms-auto' },
      h('span', { class: 'text-14 sub-text' }, 'Move to'), stepSel, courierSel,
      h('button', { type: 'button', class: 'custom-btn px-4 border-0', onclick: (e) => update([...state.ticked], { status: stepSel.value, ...(courierSel.value ? { courier_name: courierSel.value } : {}) }, e.currentTarget) }, 'Apply'),
      h('button', { type: 'button', class: 'custom-outline-btn px-3', onclick: () => { state.ticked.clear(); draw(); } }, 'Clear')));
}

let tableHost = null;
function table() {
  tableHost = h('div', {});
  redrawTable();
  return tableHost;
}

function redrawTable() {
  const rows = visible();
  if (!rows.length) {
    fill(tableHost, h('div', { class: 'text-center py-5' },
      h('div', { class: 'main-text fw-medium mb-1' }, state.data.deliveries.length ? 'No matching parcels' : 'No parcels yet'),
      h('div', { class: 'text-14 sub-text' }, state.data.deliveries.length ? 'Try another search or step.' : 'Nobody gets this tute yet. Press "Add students who paid later" after students pay.')));
    return;
  }
  const allTicked = rows.every((d) => state.ticked.has(d.id));
  const tick = (d) => h('input', { type: 'checkbox', class: 'form-check-input', 'aria-label': `Tick ${d.name}`, checked: state.ticked.has(d.id) ? true : null,
    onchange: (e) => { if (e.target.checked) state.ticked.add(d.id); else state.ticked.delete(d.id); draw(); } });
  fill(tableHost, h('div', { class: 'table-responsive' }, h('table', { class: 'table ex-v3-table align-middle m-0' },
    h('thead', {}, h('tr', {},
      h('th', { scope: 'col', class: 'ex-tick' }, h('input', { type: 'checkbox', class: 'form-check-input', 'aria-label': 'Tick all shown', checked: allTicked ? true : null,
        onchange: (e) => { rows.forEach((d) => (e.target.checked ? state.ticked.add(d.id) : state.ticked.delete(d.id))); draw(); } })),
      h('th', { scope: 'col' }, 'Student'), h('th', { scope: 'col' }, 'Address'), h('th', { scope: 'col' }, 'Step'),
      h('th', { scope: 'col' }, 'Courier and tracking'), h('th', { scope: 'col', class: 'text-end' }, h('span', { class: 'visually-hidden' }, 'Actions')))),
    h('tbody', {}, rows.map((d) => h('tr', {},
      h('td', { class: 'ex-tick' }, tick(d)),
      h('td', {}, h('div', { class: 'text-14 main-text fw-medium', 'data-no-i18n': '' }, d.name), h('div', { class: 'text-12 sub-text' }, d.phone || 'No phone')),
      h('td', { class: 'ex-dl-addr' }, d.has_address
        ? h('div', { class: 'text-13 main-text', 'data-no-i18n': '' }, address(d))
        : h('button', { type: 'button', class: 'ex-dl-noaddr', onclick: () => edit(d) }, icon('danger', { size: 16 }), 'No address: add one')),
      h('td', {}, pill(d.status), d.dispatched_on && d.status !== 'pending' && d.status !== 'packed' ? h('div', { class: 'text-12 sub-text mt-1' }, d.status === 'delivered' && d.delivered_on ? `Delivered ${day(d.delivered_on)}` : `Sent ${day(d.dispatched_on)}`) : null),
      h('td', {}, d.courier_name ? h('div', { class: 'text-13 main-text' }, d.courier_name) : h('div', { class: 'text-12 sub-text' }, '-'),
        d.tracking_no ? h('div', { class: 'text-12 ex-dl-track', 'data-no-i18n': '' }, d.tracking_no) : null),
      h('td', { class: 'text-end text-nowrap' },
        NEXT[d.status] ? h('button', { type: 'button', class: 'custom-btn ex-btn-sm px-3 border-0 me-2', onclick: (e) => (NEXT[d.status] === 'dispatched' ? edit(d, 'dispatched') : update([d.id], { status: NEXT[d.status] }, e.currentTarget)) },
          NEXT[d.status] === 'packed' ? 'Packed' : NEXT[d.status] === 'dispatched' ? 'Send' : 'Delivered') : null,
        h('button', { type: 'button', class: 'custom-outline-btn ex-btn-sm px-3', onclick: () => edit(d) }, 'Edit'))))))));
}

// ---------- actions ----------
async function update(ids, body, btn = null) {
  if (btn) btn.disabled = true;
  try {
    state.data = (await api.post(`/tutor/tutes/${state.tuteId}/deliveries`, { ids, ...body })).data;
    state.ticked.clear();
    Toast.success(ids.length === 1 ? 'Saved.' : `${plural(ids.length, 'parcel')} updated.`);
    refreshPicker();
    draw();
    return true;
  } catch (e) {
    Toast.error(e.message);
    if (btn) btn.disabled = false;
    return false;
  }
}

async function sync(e) {
  const btn = e.currentTarget;
  btn.disabled = true;
  try {
    const d = (await api.post(`/tutor/tutes/${state.tuteId}/deliveries/sync`, {})).data;
    state.data = d;
    Toast.success(d.message);
    refreshPicker();
    draw();
  } catch (err) {
    Toast.error(err.message);
    btn.disabled = false;
  }
}

function refreshPicker() {
  const t = state.tutes.find((x) => x.id === state.tuteId);
  if (t) {
    t.total = state.data.deliveries.length;
    t.delivered = state.data.deliveries.filter((d) => d.status === 'delivered').length;
  }
}

/** One parcel: address, step, courier, tracking. Opened from Edit, from "No address", and from Send (step preset). */
function edit(d, presetStep = null) {
  const f = (key, labelText, value, attrs = {}) => {
    const input = h('input', { class: 'form-control custom-input', value: value ?? '', ...attrs });
    return { key, input, el: h('div', { class: attrs.col ?? 'col-12 col-md-6' }, h('label', { class: 'text-14 main-text mb-2' }, labelText), input, h('div', { class: 'text-12 text-dark-red mt-1', 'data-err': key })) };
  };
  const step = h('select', { class: 'form-select custom-select' }, Object.entries(state.data.steps).map(([k, v]) => h('option', { value: k, selected: (presetStep ?? d.status) === k ? true : null }, v)));
  const courier = h('select', { class: 'form-select custom-select' }, h('option', { value: '' }, '- None -'),
    [...new Set([...state.data.couriers, d.courier_name].filter(Boolean))].map((c) => h('option', { value: c, selected: (d.courier_name || state.data.tute.courier_name) === c ? true : null }, c)));
  const fields = [
    f('recipient_name', 'Name on the parcel', d.name, { maxlength: '150' }),
    f('phone', 'Phone', d.phone, { maxlength: '20' }),
    f('address_line_1', 'Address line 1', d.address_line_1, { maxlength: '100', col: 'col-12' }),
    f('address_line_2', 'Address line 2', d.address_line_2, { maxlength: '100', col: 'col-12' }),
    f('city', 'Town', d.city, { maxlength: '45' }),
    f('postal_code', 'Postal code', d.postal_code, { maxlength: '10' }),
    f('tracking_no', 'Tracking number', d.tracking_no, { maxlength: '40', placeholder: 'e.g. BA123456789LK' }),
    f('note', 'Note (only you see it)', d.note, { maxlength: '255' }),
  ];
  const byKey = Object.fromEntries(fields.map((x) => [x.key, x]));
  const body = h('div', {},
    h('div', { class: 'row gx-3 gy-3' },
      h('div', { class: 'col-12 col-md-6' }, h('label', { class: 'text-14 main-text mb-2' }, 'Step'), step),
      h('div', { class: 'col-12 col-md-6' }, h('label', { class: 'text-14 main-text mb-2' }, 'Courier'), courier),
      byKey.tracking_no.el, byKey.note.el),
    h('div', { class: 'sub-topic mt-4 mb-3' }, 'Address'),
    h('div', { class: 'row gx-3 gy-3' }, ['recipient_name', 'phone', 'address_line_1', 'address_line_2', 'city', 'postal_code'].map((k) => byKey[k].el)),
    h('div', { class: 'text-12 sub-text mt-3' }, 'This changes the address on this parcel only. The student can update their own address in My Profile.'));
  const save = BsModal.button('Save', { primary: true, onclick: async () => {
    body.querySelectorAll('[data-err]').forEach((el) => { el.textContent = ''; });
    const payload = { status: step.value, courier_name: courier.value };
    fields.forEach((x) => { payload[x.key] = x.input.value; });
    save.disabled = true;
    try {
      state.data = (await api.post(`/tutor/tutes/${state.tuteId}/deliveries`, { ids: [d.id], ...payload })).data;
      m.close();
      Toast.success('Parcel saved.');
      refreshPicker();
      draw();
    } catch (e) {
      Object.entries(e.details ?? {}).forEach(([k, msg]) => { const el = body.querySelector(`[data-err="${k}"]`); if (el) el.textContent = msg; });
      Toast.error(e.message);
      save.disabled = false;
    }
  } });
  const m = BsModal.open({ title: d.name, subtitle: presetStep === 'dispatched' ? 'Add the courier and tracking number, then save' : 'Parcel details', icon: 'location', size: 'lg', body,
    footerEnd: [BsModal.button('Cancel', { onclick: () => m.close() }), save] });
  if (presetStep === 'dispatched') setTimeout(() => byKey.tracking_no.input.focus(), 300);
}

function exportCsv() {
  const rows = visible();
  download(`${state.data.tute.title.replace(/[^\w-]+/g, '_')}_addresses.csv`, toCsv([
    ['Name', 'Phone', 'Address line 1', 'Address line 2', 'Town', 'District', 'Postal code', 'Step', 'Courier', 'Tracking number'],
    ...rows.map((d) => [d.name, d.phone, d.address_line_1, d.address_line_2, d.city, d.district, d.postal_code, state.data.steps[d.status], d.courier_name, d.tracking_no]),
  ]));
}

/** Address labels to print and stick on the parcels: the ticked parcels, or every parcel still to send. */
function printLabels() {
  const pick = state.ticked.size ? state.data.deliveries.filter((d) => state.ticked.has(d.id)) : state.data.deliveries.filter((d) => d.status === 'pending' || d.status === 'packed');
  const list = pick.filter((d) => d.has_address);
  if (!list.length) { Toast.error('No parcels with an address to print. Tick some, or add the missing addresses first.'); return; }
  const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const w = window.open('', '_blank');
  if (!w) { Toast.error('Allow pop-ups for this site to print the labels.'); return; }
  w.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>${esc(state.data.tute.title)} labels</title><style>
    body{font-family:Inter,Arial,sans-serif;margin:12mm;color:#111} .grid{display:grid;grid-template-columns:1fr 1fr;gap:6mm}
    .l{border:1px dashed #999;padding:6mm;min-height:38mm;break-inside:avoid;font-size:13pt;line-height:1.45} .to{font-size:9pt;color:#666;text-transform:uppercase;letter-spacing:.08em}
    .n{font-weight:700} .t{margin-top:2mm;font-size:9pt;color:#666} @media print{button{display:none}}
  </style></head><body><button onclick="print()" style="margin-bottom:6mm;padding:8px 16px">Print</button><div class="grid">${list.map((d) => `<div class="l"><div class="to">To</div><div class="n">${esc(d.name)}</div>${[d.address_line_1, d.address_line_2, d.city, d.postal_code].filter(Boolean).map((x) => `<div>${esc(x)}</div>`).join('')}${d.phone ? `<div>${esc(d.phone)}</div>` : ''}<div class="t">${esc(state.data.tute.title)} · ${esc(state.data.tute.class)}</div></div>`).join('')}</div></body></html>`);
  w.document.close();
}
