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
import { StatCard } from '../components/StatCard.js';
import { EmptyState } from '../components/EmptyState.js';

const api = new ApiClient();
const root = document.getElementById('classes-root');
const COVERS = new URL('../../../imgs/classes-covers/', import.meta.url).href;
const DAYS = ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const TYPE = { theory: 'Theory', revision: 'Revision', paper: 'Paper class', other: 'Other' };
const MODE = { physical: 'Physical', online: 'Online', hybrid: 'Physical & online' };
const VIEWS = [['grid', 'Cards', 'view-cards'], ['rows', 'Rows', 'view-rows'], ['table', 'Table', 'view-details']];

const state = {
  classes: [],
  view: VIEWS.some(([k]) => k === localStorage.getItem('enlixo.classes.view2')) ? localStorage.getItem('enlixo.classes.view2') : 'grid',
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
  // grey blocks in the shape of the page: 4 stat cards, the toolbar, then cards
  const bone = (w, hgt, extra = {}) => h('span', { class: 'ex-skeleton d-block', style: { width: w, height: `${hgt}px`, ...extra } });
  fill(root, h('div', { 'aria-busy': 'true', 'aria-label': 'Loading your classes' },
    h('div', { class: 'row g-3 mb-4' }, [0, 1, 2, 3].map(() => h('div', { class: 'col-12 col-md-6 col-xl-3' },
      h('div', { class: 'ex-skel-card ex-skel-stat' }, bone('45%', 12, { marginBottom: '18px' }), bone('35%', 26, { marginBottom: '10px' }), bone('65%', 10))))),
    h('div', { class: 'ex-skel-card mb-4' }, bone('100%', 40)),
    h('div', { class: 'row g-4' }, [0, 1, 2, 3].map(() => h('div', { class: 'col-12 col-md-6 col-xl-4 col-xxl-3' },
      h('div', { class: 'ex-pcard' }, bone('100%', 0, { aspectRatio: '16 / 9', height: 'auto', borderRadius: 0 }),
        h('div', { class: 'ex-pcard-body' }, bone('30%', 10), bone('80%', 16), bone('60%', 12), bone('100%', 40, { marginTop: '12px', borderRadius: '20px' }))))))));
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
  fill(root, stats(), toolbar(list), viewBar(), list.length ? body(list) : empty());
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
  const showStatus = (status) => [{ label: status ? `Show ${status} classes` : 'Show all classes', icon: 'filter', onClick: () => { state.filters.status = status; draw(); } }];
  return StatCard.row([
    { icon: 'classes', label: 'Active Classes', value: active.length, caption: `${all.length - active.length} inactive`, menu: [...showStatus('active'), ...showStatus('inactive')] },
    { icon: 'students', label: 'Students', value: students, caption: 'Active in your classes', menu: [{ label: 'Open students', icon: 'students', href: 'students' }] },
    { icon: 'money', label: 'Expected Each Month', value: money(income), caption: 'Monthly fee × students' },
    { icon: 'clock', label: 'Teaching Each Week', value: `${Math.round(hours * 10) / 10} h`, caption: `${active.reduce((n, c) => n + c.times.length, 0)} weekly class times`, menu: [{ label: 'Open the timetable', icon: 'timetable', href: 'time-table' }] },
  ]);
}

// ---------- toolbar: search and filters ----------
function options(key, idKey) {
  const seen = new Map();
  for (const c of state.classes) seen.set(c[idKey], c[key]);
  return [...seen].sort((a, b) => String(a[1]).localeCompare(String(b[1]), undefined, { numeric: true }));
}

const clearAll = () => { state.q = ''; Object.keys(state.filters).forEach((k) => { state.filters[k] = ''; }); draw(); };
let filtersOpen = null; // null until first drawn: then open only if a filter is already set

// V3 table toolbar: [ N Classes ] [ search ] [ filter ] [ Export Report ], the filter row opens below
// (a label over each select, the refresh button clears them; the red badge counts the filters in use).
function toolbar(list) {
  if (filtersOpen === null) filtersOpen = Object.values(state.filters).some(Boolean);
  const field = (label, key, all, opts) => {
    const id = `cf-${key}`;
    return h('div', { class: 'col-12 col-md-4 col-lg-2' }, h('label', { class: 'text-12 sub-text mb-1', for: id }, label),
      h('select', { id, class: 'form-select custom-select', onchange: (e) => { state.filters[key] = e.target.value; draw(); } },
        h('option', { value: '' }, `- ${all} -`), opts.map(([v, l]) => h('option', { value: v, selected: String(state.filters[key]) === String(v) }, l))));
  };
  const search = h('input', { type: 'search', class: 'h-100 sub-text form-control border-0 shadow-none ex-search-input', placeholder: 'Search classes by name, subject or exam', value: state.q, 'aria-label': 'Search classes', 'data-limit': '100',
    oninput: debounce((e) => { state.q = e.target.value; const pos = e.target.selectionStart; draw(); const el = root.querySelector('.ex-search-input'); el?.focus(); el?.setSelectionRange(pos, pos); }, 200) });
  const set = Object.values(state.filters).filter(Boolean).length;
  const total = state.classes.length;
  const row = h('div', { class: 'col-12 ex-v3-filters', id: 'class-filters', hidden: !filtersOpen },
    h('div', { class: 'row justify-content-end align-items-end gy-3 gx-3' },
      field('Exam', 'exam', 'All Exams', options('exam', 'exam_id')),
      field('Year', 'year', 'All Years', options('year', 'year_id')),
      field('Subject', 'subject', 'All Subjects', options('subject', 'subject_id')),
      field('Medium', 'medium', 'All Mediums', options('medium', 'medium_id')),
      field('Status', 'status', 'All Status', [['active', 'Active'], ['inactive', 'Inactive']]),
      h('div', { class: 'col-auto d-flex flex-column justify-content-end' },
        h('button', { type: 'button', class: 'custom-btn-outline tf-clear d-flex align-items-center justify-content-center', title: 'Clear filters', 'aria-label': 'Clear filters', disabled: !set,
          onclick: () => { Object.keys(state.filters).forEach((k) => { state.filters[k] = ''; }); draw(); } }, icon('refresh', { size: 22 })))));
  const toggle = h('button', { type: 'button', class: 'custom-btn w-100 position-relative ex-filter-btn', 'aria-expanded': String(filtersOpen), 'aria-controls': 'class-filters', title: 'Filters', 'aria-label': 'Filters',
    onclick: () => { filtersOpen = !filtersOpen; toggle.setAttribute('aria-expanded', String(filtersOpen)); row.hidden = !filtersOpen; } },
    icon('sliders', { size: 24 }), h('span', { class: 'tf-badge', hidden: !set, title: 'Filters applied' }, String(set)));
  return h('div', { class: 'component-theme p-4 mb-4' },
    h('div', { class: 'row p-0 gy-3 gx-3 align-content-center justify-content-end' },
      h('div', { class: 'col-4 col-md-2 d-flex flex-column justify-content-start' },
        h('div', { class: 'd-flex custom-div align-items-center justify-content-center px-2', 'aria-live': 'polite' },
          h('span', { class: 'text-14 dark-theme fw-medium text-nowrap' }, filtering() ? `${list.length} of ${total} Classes` : `${total} ${total === 1 ? 'Class' : 'Classes'}`))),
      h('div', { class: 'col-8 col-md-8 col-lg-7 d-flex flex-column' },
        h('div', { class: 'h-100 d-flex align-items-center p-1 bl-solid ex-search-box' }, h('span', { class: 'px-3 d-inline-flex' }, icon('search', { size: 22 })), search)),
      h('div', { class: 'col-12 col-md-2 col-lg-1 d-flex flex-column justify-content-end' }, toggle),
      h('div', { class: 'col-12 col-md-2 col-lg-2 d-flex flex-column justify-content-end' },
        h('button', { type: 'button', class: 'custom-btn w-100 d-flex align-items-center justify-content-center gap-2', onclick: exportCsv },
          h('span', { class: 'text-14 fw-semibold' }, 'Export Report'), icon('download', { size: 22 }))),
      row));
}

// The line above the list: how to reorder on the left, the view switcher on the right.
function viewBar() {
  const total = state.classes.length;
  return h('div', { class: 'd-flex flex-wrap align-items-center gap-3 mb-3' },
    total > 1 ? (canDrag()
      ? h('span', { class: 'text-12 sub-text d-inline-flex align-items-center gap-1' }, 'To change the order, hold', h('span', { class: 'ex-grip-sample' }, icon('drag-dots', { size: 14 })), 'and drag a class up or down.')
      : h('span', { class: 'text-12 sub-text' }, 'Showing a filtered list. ', h('button', { type: 'button', class: 'ex-link-add', onclick: clearAll }, 'Clear search and filters'), ' to change the order.')) : null,
    h('div', { class: 'ms-auto d-flex align-items-center gap-2' },
      h('div', { class: 'ex-view-switch', role: 'group', 'aria-label': 'View' }, VIEWS.map(([key, label, ico]) => h('button', {
        type: 'button', class: `ex-view-btn ${state.view === key ? 'active' : ''}`, 'aria-pressed': String(state.view === key), title: label, 'aria-label': `${label} view`,
        onclick: () => { state.view = key; localStorage.setItem('enlixo.classes.view2', key); draw(); },
      }, icon(ico, { size: 18 }))))));
}

function empty() {
  const none = !state.classes.length;
  return h('div', { class: 'component-theme' }, EmptyState.empty(none
    ? { icon: 'classes', title: 'No classes yet', text: 'Create your first class: subject, weekly times and fee in one form.', action: h('a', { href: 'create-class', class: 'custom-btn ex-btn-md px-4 border-0 d-inline-flex' }, 'Create a Class') }
    : { icon: 'search', title: 'No class matches', text: 'Try another search or clear the filters.', action: h('button', { type: 'button', class: 'custom-btn-outline px-4', onclick: clearAll }, 'Clear search and filters') }));
}

// ---------- shared bits ----------
const detailsUrl = (c) => `class-details?class-id=${encodeURIComponent(c.id)}`;
const subtitle = (c) => `${c.year} ${c.exam} · ${c.subject} · ${c.medium}`;
const facts = (c) => [c.times.length ? c.times.map(timeText).join(', ') : 'No weekly times yet', `${c.students} ${c.students === 1 ? 'student' : 'students'}`, feeText(c)].join(' · ');
const inactiveTag = (c) => (c.active ? null : h('span', { class: 'ex-tag-off' }, 'Inactive'));

/** Everything you can do with one class (the orange "Actions" menu and the ⋯ on a card). */
function menuItems(c) {
  const id = encodeURIComponent(c.id);
  return [
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
  ];
}

/** V3 "Actions" dropdown (orange) for one class. */
function actions(c, size = 'md') {
  return ActionMenu.button('Actions', menuItems(c), { className: `ex-btn-actions ex-btn-${size}` });
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

/** Colour of the "2027 • A/L" pill and of the cover drawn for a class without a picture. */
const examTone = (c) => {
  const e = `${c.exam}`.toLowerCase();
  return e.includes('a/l') || e.includes('advanced') ? 'pink' : e.includes('o/l') || e.includes('ordinary') ? 'orange' : e.includes('scholar') ? 'purple' : 'blue';
};

/** Cover for a class without a picture: a soft gradient with the subject, so no card shows a grey "no image" box. */
function pcardCover(c) {
  const drawn = () => h('span', { class: `ex-pcard-art tone-${examTone(c)}`, 'aria-hidden': 'true' },
    h('span', { class: 'ex-pcard-art-letter' }, (c.subject || c.name || '?').trim().charAt(0).toUpperCase()),
    h('span', { class: 'ex-pcard-art-name' }, c.subject));
  if (!c.cover) return drawn();
  const img = h('img', { src: COVERS + encodeURIComponent(c.cover), alt: '', loading: 'lazy' });
  img.addEventListener('error', () => img.replaceWith(drawn()), { once: true });
  return img;
}

/** Premium picture-first cards (default view): pill with year and exam, subject, name, medium and students, View Class. */
function gridView(list) {
  return h('div', { class: 'ex-sort-list row g-4' }, list.map((c) => h('div', { class: 'col-12 col-md-6 col-xl-4 col-xxl-3', 'data-id': String(c.id) },
    h('article', { class: `ex-pcard h-100 ${c.active ? '' : 'ex-inactive'}` },
      h('div', { class: 'ex-pcard-cover' },
        h('a', { href: detailsUrl(c), tabindex: '-1', 'aria-hidden': 'true' }, pcardCover(c)),
        h('span', { class: `ex-pcard-pill tone-${examTone(c)}` }, `${c.year} • ${c.exam}`),
        grip(c)),
      h('div', { class: 'ex-pcard-body' },
        h('span', { class: 'ex-pcard-subject' }, c.subject, c.active ? null : h('span', { class: 'ex-pcard-off' }, 'Inactive')),
        h('a', { href: detailsUrl(c), class: 'ex-pcard-title' }, c.name),
        h('div', { class: 'ex-pcard-meta' },
          h('span', {}, icon('language', { size: 18 }), c.medium),
          h('span', {}, icon('user', { size: 18 }), `${c.students}${c.capacity ? ` / ${c.capacity}` : ''} ${c.students === 1 ? 'Student' : 'Students'}`)),
        h('div', { class: 'ex-pcard-when' }, icon('clock', { size: 15 }), c.times.length ? c.times.map(timeText).join(', ') : 'No weekly times yet', h('span', { class: 'ex-pcard-fee' }, feeText(c))),
        h('div', { class: 'ex-pcard-foot' },
          h('a', { href: detailsUrl(c), class: 'ex-pcard-view' }, icon('view', { size: 18 }), 'View Class'),
          h('div', { class: 'ex-pcard-tools' },
            h('a', { href: `edit-class?class-id=${encodeURIComponent(c.id)}`, class: 'ex-pcard-round', title: 'Edit class', 'aria-label': `Edit ${c.name}` }, icon('edit', { size: 18 })),
            ActionMenu.kebab(menuItems(c), `More for ${c.name}`))))))));
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
