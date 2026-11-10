// Tutor → Classes (tutor/classes.php), laid out like ENLIXO V3 Branches: stat cards, search + filters,
// three views (rows, table, grid) and ordering by dragging the ⋮⋮ handle.
// Data: GET /tutor/classes/overview. Order: POST /tutor/classes/order.
import { h, fill, debounce } from '../core/dom.js';
import { ApiClient } from '../core/ApiClient.js';
import { icon } from '../core/icons.js';
import { QuickGuide } from '../components/QuickGuide.js';
import { ActionMenu } from '../components/Popover.js';
import { BsModal } from '../components/BsModal.js';
import { Toast } from '../components/Toast.js';

const api = new ApiClient();
const root = document.getElementById('classes-root');
const COVERS = new URL('../../../imgs/classes-covers/', import.meta.url).href;
const DAYS = ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const TYPE = { theory: 'Theory', revision: 'Revision', paper: 'Paper class', other: 'Other' };
const MODE = { physical: 'Physical', online: 'Online', hybrid: 'Physical & online' };
const VIEWS = [['rows', 'Rows', 'view-rows'], ['table', 'Table', 'view-details'], ['grid', 'Grid', 'view-cards']];

const state = {
  classes: [],
  view: VIEWS.some(([k]) => k === localStorage.getItem('enlixo.classes.view')) ? localStorage.getItem('enlixo.classes.view') : 'rows',
  q: '',
  filters: { exam: '', year: '', subject: '', medium: '', status: '' },
};

QuickGuide.attachAll();
document.getElementById('classes-actions').append(
  h('a', { href: 'class-form-settings', class: 'ex-gear', title: 'Class form settings', 'aria-label': 'Class form settings' }, icon('settings', { size: 26 })),
  h('a', { href: 'create-class', class: 'custom-btn ex-btn-lg px-4 border-0 d-inline-flex align-items-center gap-2' }, icon('add', { size: 20 }), 'Create a Class'),
);

skeleton();
load();

function skeleton() {
  fill(root, h('div', { class: 'placeholder-glow' },
    h('div', { class: 'row g-3 mb-4' }, [0, 1, 2, 3].map(() => h('div', { class: 'col-12 col-md-6 col-xl-3' }, h('span', { class: 'placeholder col-12', style: { height: '92px' } })))),
    h('span', { class: 'placeholder col-12 mb-4', style: { height: '72px' } }),
    [0, 1, 2, 3].map(() => h('span', { class: 'placeholder col-12 mb-2', style: { height: '76px' } }))));
}

async function load() {
  try {
    state.classes = (await api.get('/tutor/classes/overview')).data;
    draw();
  } catch (e) {
    fill(root, h('div', { class: 'component-theme text-center py-5' },
      h('p', { class: 'main-text mb-1' }, 'Couldn\'t load your classes'),
      h('p', { class: 'text-14 sub-text' }, e.message),
      h('button', { type: 'button', class: 'custom-btn ex-btn-md px-4 border-0', onclick: () => { skeleton(); load(); } }, 'Try again')));
  }
}

// ---------- helpers ----------
const money = (n) => `LKR ${Number(n).toLocaleString('en-LK', { maximumFractionDigits: 2 })}`;
const feeText = (c) => (c.is_free ? 'Free' : c.fee == null ? 'Not set' : `${money(c.fee)} / month`);
const time12 = (t) => { const [hh, mm] = t.split(':').map(Number); return `${(hh % 12) || 12}:${String(mm).padStart(2, '0')}${hh < 12 ? 'am' : 'pm'}`; };
const timeText = (t) => `${DAYS[t.weekday]} ${time12(t.start)} to ${time12(t.end)}`;
// The class picture, or a grey box with a picture icon when there is none or the file is missing.
const noCover = (cls = '') => h('span', { class: `ex-cover-empty ${cls}` }, icon('image', { size: cls ? 20 : 44 }));
const cover = (c, cls = '') => {
  if (!c.cover) return noCover(cls);
  const img = h('img', { src: COVERS + encodeURIComponent(c.cover), alt: '', class: cls, loading: 'lazy' });
  img.addEventListener('error', () => img.replaceWith(noCover(cls)), { once: true });
  return img;
};
const filtering = () => state.q.trim() !== '' || Object.values(state.filters).some(Boolean);
const canDrag = () => !filtering();

function visible() {
  const q = state.q.trim().toLowerCase();
  const f = state.filters;
  let list = state.classes.filter((c) => (!q || `${c.name} ${c.subject} ${c.exam} ${c.year} ${c.medium} ${c.location ?? ''}`.toLowerCase().includes(q))
    && (!f.exam || c.exam_id === f.exam) && (!f.year || c.year_id === f.year) && (!f.subject || c.subject_id === f.subject)
    && (!f.medium || c.medium_id === f.medium) && (!f.status || (f.status === 'active') === c.active));
  return list;
}

function draw() {
  const list = visible();
  const scroller = document.querySelector('.contentBodyArea');
  const y = scroller?.scrollTop;
  fill(root, stats(), toolbar(), viewBar(list), list.length ? body(list) : empty());
  if (scroller && y !== undefined) scroller.scrollTop = y;
}

// ---------- stat cards ----------
function stats() {
  const all = state.classes;
  const active = all.filter((c) => c.active);
  const students = active.reduce((n, c) => n + c.students, 0);
  const income = active.reduce((n, c) => n + (c.is_free || c.fee == null ? 0 : c.fee * c.students), 0);
  const hours = active.reduce((n, c) => n + c.times.reduce((m, t) => {
    const [a, b] = [t.start, t.end].map((x) => x.split(':').map(Number));
    return m + ((b[0] * 60 + b[1]) - (a[0] * 60 + a[1])) / 60;
  }, 0), 0);
  const card = (ico, n, title, sub) => h('div', { class: 'col-12 col-md-6 col-xl-3' },
    h('div', { class: 'component-theme px-4 py-3 h-100 d-flex align-items-center gap-3' },
      h('span', { class: 'ex-stat-ico' }, icon(ico, { size: 22 })),
      h('div', {}, h('div', { class: 'ex-stat-num' }, n), h('div', { class: 'text-14 main-text' }, title), h('div', { class: 'text-12 sub-text' }, sub))));
  return h('div', { class: 'row g-3 mb-4' },
    card('classes', String(active.length), 'Active classes', `${all.length - active.length} inactive`),
    card('students', String(students), 'Students', 'active in your classes'),
    card('money', money(income), 'Expected each month', 'monthly fee × students'),
    card('clock', `${Math.round(hours * 10) / 10} h`, 'Teaching each week', `${active.reduce((n, c) => n + c.times.length, 0)} weekly class times`));
}

// ---------- toolbar: search and filters ----------
function options(key, idKey) {
  const seen = new Map();
  for (const c of state.classes) seen.set(c[idKey], c[key]);
  return [...seen].sort((a, b) => String(a[1]).localeCompare(String(b[1]), undefined, { numeric: true }));
}

function toolbar() {
  const select = (label, key, opts) => h('select', { class: 'form-select custom-select ex-h40 ex-filter', 'aria-label': label, onchange: (e) => { state.filters[key] = e.target.value; draw(); } },
    h('option', { value: '' }, label), opts.map(([v, l]) => h('option', { value: v, selected: state.filters[key] === v }, l)));
  const search = h('input', { type: 'search', class: 'form-control custom-input ex-h40 ps-5', placeholder: 'Search classes', value: state.q, 'aria-label': 'Search classes',
    oninput: debounce((e) => { state.q = e.target.value; const pos = e.target.selectionStart; draw(); const el = root.querySelector('.ex-classes-search input'); el?.focus(); el?.setSelectionRange(pos, pos); }, 200) });
  return h('div', { class: 'component-theme p-3 mb-3' },
    h('div', { class: 'd-flex flex-wrap align-items-center gap-2' },
      h('div', { class: 'position-relative ex-classes-search flex-grow-1' }, h('span', { class: 'ex-search-ico' }, icon('search', { size: 16 })), search),
      select('All exams', 'exam', options('exam', 'exam_id')),
      select('All years', 'year', options('year', 'year_id')),
      select('All subjects', 'subject', options('subject', 'subject_id')),
      select('All mediums', 'medium', options('medium', 'medium_id')),
      select('Any status', 'status', [['active', 'Active'], ['inactive', 'Inactive']])));
}

// The line above the list: count and how to reorder on the left, export and the view switcher on the right (as in V3).
function viewBar(list) {
  const total = state.classes.length;
  const clear = () => { state.q = ''; Object.keys(state.filters).forEach((k) => { state.filters[k] = ''; }); draw(); };
  return h('div', { class: 'd-flex flex-wrap align-items-center gap-3 mb-3' },
    h('span', { class: 'text-14 main-text' }, filtering() ? `${list.length} of ${total} classes` : `${total} ${total === 1 ? 'class' : 'classes'}`),
    total > 1 ? (canDrag()
      ? h('span', { class: 'text-12 sub-text d-inline-flex align-items-center gap-1' }, 'To change the order, hold', h('span', { class: 'ex-grip-sample' }, icon('drag-dots', { size: 14 })), 'and drag a class up or down.')
      : h('span', { class: 'text-12 sub-text' }, 'Showing a filtered list. ', h('button', { type: 'button', class: 'ex-link-add', onclick: clear }, 'Clear search and filters'), ' to change the order.')) : null,
    h('div', { class: 'ms-auto d-flex align-items-center gap-2' },
      h('button', { type: 'button', class: 'ex-view-btn', title: 'Export classes (CSV)', 'aria-label': 'Export classes (CSV)', onclick: exportCsv }, icon('export', { size: 18 })),
      h('div', { class: 'ex-view-switch', role: 'group', 'aria-label': 'View' }, VIEWS.map(([key, label, ico]) => h('button', {
        type: 'button', class: `ex-view-btn ${state.view === key ? 'active' : ''}`, 'aria-pressed': String(state.view === key), title: label, 'aria-label': `${label} view`,
        onclick: () => { state.view = key; localStorage.setItem('enlixo.classes.view', key); draw(); },
      }, icon(ico, { size: 18 }))))));
}

function empty() {
  const none = !state.classes.length;
  return h('div', { class: 'component-theme text-center py-5 px-3' },
    h('span', { class: 'ex-empty-ico' }, icon('classes', { size: 30 })),
    h('p', { class: 'main-text fw-medium mt-3 mb-1' }, none ? 'No classes yet' : 'No class matches'),
    h('p', { class: 'text-14 sub-text mb-3' }, none ? 'Create your first class: subject, weekly times and fee in one form.' : 'Try another search or clear the filters.'),
    none ? h('a', { href: 'create-class', class: 'custom-btn ex-btn-md px-4 border-0 d-inline-flex' }, 'Create a Class') : null);
}

// ---------- shared bits ----------
const detailsUrl = (c) => `class-details?class-id=${encodeURIComponent(c.id)}`;
const subtitle = (c) => `${c.year} ${c.exam} · ${c.subject} · ${c.medium}`;
const facts = (c) => [c.times.length ? c.times.map(timeText).join(', ') : 'No weekly times yet', `${c.students} ${c.students === 1 ? 'student' : 'students'}`, feeText(c)].join(' · ');
const inactiveTag = (c) => (c.active ? null : h('span', { class: 'ex-tag-off' }, 'Inactive'));

/** V3 "Actions" dropdown (orange) for one class. */
function actions(c, size = 'md') {
  const id = encodeURIComponent(c.id);
  return ActionMenu.button('Actions', [
    { label: 'Edit', icon: 'edit', href: `edit-class?class-id=${id}` },
    { label: 'Students', icon: 'students', href: `class-students?class-id=${id}` },
    { label: 'Time Table', icon: 'timetable', href: `class-time-table?class-id=${id}` },
    { label: 'Attendance', icon: 'attendance', href: `class-attendance?class-id=${id}` },
    { label: 'Payments', icon: 'payments', href: `class-payments?class-id=${id}` },
    { label: 'Recordings', icon: 'recordings', href: `class-recordings?class-id=${id}` },
    { label: 'Pre-Recorded Classes', icon: 'pre-recorded', href: `class-pre-recordings?class-id=${id}` },
    { label: 'Tutes', icon: 'tutes', href: `class-tutes?class-id=${id}` },
    c.active
      ? { label: 'Deactivate', icon: 'deactivate', danger: true, onClick: () => setActive(c, false) }
      : { label: 'Activate', icon: 'activate', onClick: () => setActive(c, true) },
  ], { className: `ex-btn-actions ex-btn-${size}` });
}
const viewBtn = (c, size = 'md') => h('a', { href: detailsUrl(c), class: `custom-btn ex-btn-${size} ex-btn-view border-0` }, 'View');

/** The ⋮⋮ handle. Dragging starts only from here; the arrow keys move the class too. */
function grip(c) {
  if (!canDrag() || state.classes.length < 2) return null;
  return h('button', {
    type: 'button', class: 'ex-grip', title: 'Drag to change the order', 'aria-label': `Change the order of ${c.name}: drag, or use the up and down arrow keys`, 'data-grip': String(c.id),
    onkeydown: (e) => {
      const i = state.classes.indexOf(c);
      if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') { e.preventDefault(); moveTo(c, i - 1); }
      if (e.key === 'ArrowDown' || e.key === 'ArrowRight') { e.preventDefault(); moveTo(c, i + 1); }
    },
  }, icon('drag-dots', { size: 20 }));
}

// ---------- views ----------
function body(list) {
  const view = state.view === 'table' ? tableView(list) : state.view === 'grid' ? gridView(list) : rowsView(list);
  if (canDrag()) sortable(view);
  return view;
}

/** Default view, like V3 Branches: one white row per class. */
function rowsView(list) {
  return h('div', { class: 'ex-sort-list d-flex flex-column gap-2' }, list.map((c) => h('article', { class: `ex-list-row ${c.active ? '' : 'ex-inactive'}`, 'data-id': String(c.id) },
    grip(c),
    h('a', { href: detailsUrl(c), class: 'ex-list-thumb', tabindex: '-1' }, cover(c)),
    h('div', { class: 'ex-list-text' },
      h('a', { href: detailsUrl(c), class: 'ex-list-title' }, c.name, inactiveTag(c)),
      h('div', { class: 'ex-list-sub' }, subtitle(c)),
      h('div', { class: 'ex-list-sub' }, facts(c))),
    h('div', { class: 'ex-list-actions' }, actions(c), viewBtn(c)))));
}

/** Compact table for many classes. */
function tableView(list) {
  const drag = canDrag() && state.classes.length > 1;
  return h('div', { class: 'component-theme p-3' }, h('div', { class: 'table-responsive' },
    h('table', { class: 'table ex-v3-table align-middle m-0' },
      h('thead', {}, h('tr', {},
        drag ? h('th', { scope: 'col', class: 'ex-col-order' }, h('span', { class: 'visually-hidden' }, 'Order')) : null,
        h('th', { scope: 'col' }, 'Class'), h('th', { scope: 'col' }, 'Weekly times'), h('th', { scope: 'col' }, 'Students'),
        h('th', { scope: 'col' }, 'Monthly fee'), h('th', { scope: 'col' }, 'Status'), h('th', { scope: 'col', class: 'text-end' }, 'Actions'))),
      h('tbody', { class: 'ex-sort-list' }, list.map((c) => h('tr', { 'data-id': String(c.id), class: c.active ? '' : 'ex-row-off' },
        drag ? h('td', { class: 'ex-col-order' }, grip(c)) : null,
        h('td', {}, h('a', { href: detailsUrl(c), class: 'd-flex align-items-center gap-3 text-decoration-none' },
          cover(c, 'ex-thumb-sm'),
          h('span', { class: 'd-flex flex-column min-w-0' }, h('span', { class: 'text-14 main-text fw-medium ex-clamp-1' }, c.name), h('span', { class: 'text-12 sub-text' }, subtitle(c))))),
        h('td', { class: 'text-12' }, c.times.length ? c.times.map((t) => h('div', { class: 'text-nowrap' }, timeText(t))) : h('span', { class: 'sub-text' }, 'None yet')),
        h('td', { class: 'text-14' }, `${c.students}${c.capacity ? ` / ${c.capacity}` : ''}`),
        h('td', { class: 'text-14 text-nowrap' }, feeText(c)),
        h('td', {}, h('span', { class: `ex-status ${c.active ? '' : 'ex-off'}` }, c.active ? 'Active' : 'Inactive')),
        h('td', {}, h('div', { class: 'd-flex justify-content-end gap-2' }, actions(c, 'sm'), viewBtn(c, 'sm')))))))));
}

/** Picture-first grid. */
function gridView(list) {
  return h('div', { class: 'ex-sort-list row g-3' }, list.map((c) => h('div', { class: 'col-12 col-md-6 col-xl-4 col-xxl-3', 'data-id': String(c.id) },
    h('article', { class: `ex-grid-card h-100 ${c.active ? '' : 'ex-inactive'}` },
      h('div', { class: 'ex-grid-cover' }, h('a', { href: detailsUrl(c), tabindex: '-1' }, cover(c)), grip(c)),
      h('div', { class: 'ex-grid-body' },
        h('a', { href: detailsUrl(c), class: 'ex-list-title' }, c.name, inactiveTag(c)),
        h('div', { class: 'ex-list-sub' }, subtitle(c)),
        h('div', { class: 'ex-list-sub' }, facts(c)),
        h('div', { class: 'ex-grid-actions' }, actions(c), viewBtn(c)))))));
}

// ---------- ordering ----------
/**
 * Drag by the handle: the item follows the pointer and the others move aside right away,
 * so what you see when you let go is the new order. Saved on drop.
 */
function sortable(view) {
  const box = view.classList.contains('ex-sort-list') ? view : view.querySelector('.ex-sort-list');
  let dragged = null;
  box.addEventListener('pointerdown', (e) => {
    const item = e.target.closest('.ex-grip') && e.target.closest('[data-id]');
    if (item) item.draggable = true;
  });
  box.addEventListener('pointerup', () => { if (!dragged) box.querySelectorAll('[draggable="true"]').forEach((el) => { el.draggable = false; }); });
  box.addEventListener('dragstart', (e) => {
    dragged = e.target.closest?.('[data-id]');
    if (!dragged) return;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', dragged.dataset.id);
    requestAnimationFrame(() => { dragged?.classList.add('ex-dragging'); box.classList.add('ex-sorting'); });
  });
  box.addEventListener('dragover', (e) => {
    if (!dragged) return;
    e.preventDefault();
    const over = e.target.closest('[data-id]');
    if (!over || over === dragged || over.parentElement !== box) return;
    const r = over.getBoundingClientRect();
    const grid = box.classList.contains('row');
    const after = grid ? e.clientX > r.left + r.width / 2 : e.clientY > r.top + r.height / 2;
    over[after ? 'after' : 'before'](dragged);
  });
  box.addEventListener('drop', (e) => e.preventDefault());
  box.addEventListener('dragend', () => {
    if (!dragged) return;
    dragged.classList.remove('ex-dragging');
    dragged.draggable = false;
    box.classList.remove('ex-sorting');
    const id = dragged.dataset.id;
    dragged = null;
    const ids = [...box.children].map((el) => el.dataset.id);
    const before = state.classes.map((c) => String(c.id));
    if (ids.join() === before.join()) return;
    state.classes.sort((a, b) => ids.indexOf(String(a.id)) - ids.indexOf(String(b.id)));
    saveOrder(id);
  });
}

function moveTo(c, to) {
  const list = state.classes;
  const from = list.indexOf(c);
  if (to < 0 || to >= list.length || from === to) return;
  list.splice(to, 0, list.splice(from, 1)[0]);
  saveOrder(String(c.id));
}

let saveTimer = null;
function saveOrder(movedId) {
  draw();
  const row = root.querySelector(`[data-id="${movedId}"]`);
  row?.classList.add('ex-just-moved');
  setTimeout(() => row?.classList.remove('ex-just-moved'), 900);
  root.querySelector(`[data-grip="${movedId}"]`)?.focus({ preventScroll: true });
  clearTimeout(saveTimer);
  saveTimer = setTimeout(async () => {
    try {
      await api.post('/tutor/classes/order', { ids: state.classes.map((x) => x.id) });
      Toast.success('Order saved.');
    } catch (e) { Toast.error(e.message); }
  }, 400);
}

async function setActive(c, active) {
  if (!active) {
    const ok = await BsModal.confirm({ title: `Deactivate "${c.name}"?`, text: 'Students can\'t join it and it is hidden from them. Nothing is deleted, and you can activate it again any time.', yes: 'Deactivate', danger: true });
    if (!ok) return;
  }
  try {
    await api.post('/tutor/classes/status', { ids: [c.id], active });
    c.active = active;
    draw();
    Toast.success(active ? 'Class activated.' : 'Class deactivated.');
  } catch (e) { Toast.error(e.message); }
}

function exportCsv() {
  const cell = (v) => { const s = String(v ?? ''); return /^[=+\-@]/.test(s) ? `'${s}` : s; };
  const rows = [['Class', 'Subject', 'Exam', 'Year', 'Medium', 'Type', 'How', 'Hall', 'Weekly times', 'Monthly fee', 'Students', 'Max students', 'Status'],
    ...visible().map((c) => [c.name, c.subject, c.exam, c.year, c.medium, TYPE[c.class_type] ?? '', MODE[c.delivery_mode] ?? '', c.location ?? '', c.times.map(timeText).join('; '),
      c.is_free ? 'Free' : c.fee ?? '', c.students, c.capacity ?? '', c.active ? 'Active' : 'Inactive'])];
  const csv = rows.map((r) => r.map((v) => `"${cell(v).replace(/"/g, '""')}"`).join(',')).join('\r\n');
  const a = h('a', { href: URL.createObjectURL(new Blob(['﻿', csv], { type: 'text/csv' })), download: `classes-${new Date().toISOString().slice(0, 10)}.csv` });
  document.body.append(a);
  a.click();
  a.remove();
}
