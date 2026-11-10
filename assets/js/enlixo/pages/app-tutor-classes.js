// Tutor → Classes (tutor/classes.php): stat cards, search + filters, three views (cards, table, details)
// and drag-and-drop ordering. Data: GET /tutor/classes/overview. Order: POST /tutor/classes/order.
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
const JOIN = { open: 'Anyone with the link', approval: 'You approve each student', invite: 'Only students you add' };
const VIEWS = [['cards', 'Cards', 'view-cards'], ['table', 'Table', 'view-table'], ['details', 'Details', 'view-details']];
const SORTS = [['mine', 'My order'], ['newest', 'Newest first'], ['name', 'Name A to Z'], ['students', 'Most students']];

const state = {
  classes: [],
  view: localStorage.getItem('enlixo.classes.view') ?? 'cards',
  sort: 'mine',
  q: '',
  filters: { exam: '', year: '', subject: '', medium: '', status: '' },
};

QuickGuide.attachAll();
document.getElementById('classes-actions').append(
  ActionMenu.button('More', [
    { label: 'Class form settings', icon: 'class-form', href: 'class-form-settings' },
    { label: 'Export classes (CSV)', icon: 'export', onClick: () => exportCsv() },
  ], { className: 'custom-outline-btn ex-btn-lg px-4 d-inline-flex align-items-center gap-2' }),
  h('a', { href: 'create-class', class: 'custom-btn ex-btn-lg px-4 border-0 d-inline-flex align-items-center gap-2' }, icon('add', { size: 20 }), 'Create a Class'),
);

skeleton();
load();

function skeleton() {
  fill(root, h('div', { class: 'placeholder-glow' },
    h('div', { class: 'row g-3 mb-4' }, [0, 1, 2, 3].map(() => h('div', { class: 'col-12 col-md-6 col-xl-3' }, h('span', { class: 'placeholder col-12', style: { height: '92px' } })))),
    h('span', { class: 'placeholder col-12 mb-4', style: { height: '72px' } }),
    h('div', { class: 'row g-3' }, [0, 1, 2, 3].map(() => h('div', { class: 'col-12 col-md-6 col-xl-3' }, h('span', { class: 'placeholder col-12', style: { height: '320px' } }))))));
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
const canDrag = () => state.sort === 'mine' && !filtering();

function visible() {
  const q = state.q.trim().toLowerCase();
  const f = state.filters;
  let list = state.classes.filter((c) => (!q || `${c.name} ${c.subject} ${c.exam} ${c.year} ${c.medium} ${c.location ?? ''}`.toLowerCase().includes(q))
    && (!f.exam || c.exam_id === f.exam) && (!f.year || c.year_id === f.year) && (!f.subject || c.subject_id === f.subject)
    && (!f.medium || c.medium_id === f.medium) && (!f.status || (f.status === 'active') === c.active));
  if (state.sort === 'newest') list = [...list].sort((a, b) => b.id - a.id);
  if (state.sort === 'name') list = [...list].sort((a, b) => a.name.localeCompare(b.name));
  if (state.sort === 'students') list = [...list].sort((a, b) => b.students - a.students);
  return list;
}

function draw() {
  const list = visible();
  const scroller = document.querySelector('.contentBodyArea');
  const y = scroller?.scrollTop;
  fill(root, stats(), toolbar(list), list.length ? body(list) : empty());
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

// ---------- toolbar: search, filters, sort, view ----------
function options(key, idKey) {
  const seen = new Map();
  for (const c of state.classes) seen.set(c[idKey], c[key]);
  return [...seen].sort((a, b) => String(a[1]).localeCompare(String(b[1]), undefined, { numeric: true }));
}

function toolbar(list) {
  const select = (label, key, opts) => h('select', { class: 'form-select custom-select ex-h40 ex-filter', 'aria-label': label, onchange: (e) => { state.filters[key] = e.target.value; draw(); } },
    h('option', { value: '' }, label), opts.map(([v, l]) => h('option', { value: v, selected: state.filters[key] === v }, l)));
  const search = h('input', { type: 'search', class: 'form-control custom-input ex-h40 ps-5', placeholder: 'Search classes', value: state.q, 'aria-label': 'Search classes',
    oninput: debounce((e) => { state.q = e.target.value; const pos = e.target.selectionStart; draw(); const el = root.querySelector('.ex-classes-search input'); el?.focus(); el?.setSelectionRange(pos, pos); }, 200) });
  const viewSwitch = h('div', { class: 'ex-segmented', role: 'group', 'aria-label': 'View' }, VIEWS.map(([key, label, ico]) => h('button', {
    type: 'button', class: state.view === key ? 'active' : '', 'aria-pressed': String(state.view === key), title: label,
    onclick: () => { state.view = key; localStorage.setItem('enlixo.classes.view', key); draw(); },
  }, icon(ico, { size: 18 }), h('span', { class: 'd-none d-xxl-inline' }, label))));

  return h('div', { class: 'component-theme p-3 mb-4' },
    h('div', { class: 'd-flex flex-wrap align-items-center gap-2' },
      h('div', { class: 'position-relative ex-classes-search flex-grow-1' }, h('span', { class: 'ex-search-ico' }, icon('search', { size: 16 })), search),
      select('All exams', 'exam', options('exam', 'exam_id')),
      select('All years', 'year', options('year', 'year_id')),
      select('All subjects', 'subject', options('subject', 'subject_id')),
      select('All mediums', 'medium', options('medium', 'medium_id')),
      select('Any status', 'status', [['active', 'Active'], ['inactive', 'Inactive']]),
      h('select', { class: 'form-select custom-select ex-h40 ex-filter', 'aria-label': 'Sort', onchange: (e) => { state.sort = e.target.value; draw(); } },
        SORTS.map(([v, l]) => h('option', { value: v, selected: state.sort === v }, l))),
      viewSwitch),
    h('div', { class: 'd-flex flex-wrap align-items-center gap-2 mt-2 text-12 sub-text' },
      h('span', {}, `Showing ${list.length} of ${state.classes.length} ${state.classes.length === 1 ? 'class' : 'classes'}`),
      filtering() ? h('button', { type: 'button', class: 'ex-link-add', onclick: () => { state.q = ''; Object.keys(state.filters).forEach((k) => { state.filters[k] = ''; }); draw(); } }, 'Clear search and filters') : null,
      h('span', { class: 'ms-auto d-inline-flex align-items-center gap-1' }, icon('drag', { size: 14 }),
        canDrag() ? 'Drag the handle to change the order' : state.sort !== 'mine' ? 'Choose "My order" to drag classes' : 'Clear search and filters to drag classes')));
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
function menu(c) {
  const id = encodeURIComponent(c.id);
  return ActionMenu.kebab([
    { label: 'View class', icon: 'view', href: `class-details?class-id=${id}` },
    { label: 'Edit', icon: 'edit', href: `edit-class?class-id=${id}` },
    { label: 'Students', icon: 'students', href: `class-students?class-id=${id}` },
    { label: 'Time Table', icon: 'timetable', href: `class-time-table?class-id=${id}` },
    { label: 'Recordings', icon: 'recordings', href: `class-recordings?class-id=${id}` },
    { label: 'Pre-Recorded Classes', icon: 'pre-recorded', href: `class-pre-recordings?class-id=${id}` },
    { label: 'Attendance', icon: 'attendance', href: `class-attendance?class-id=${id}` },
    { label: 'Tutes', icon: 'tutes', href: `class-tutes?class-id=${id}` },
    { label: 'Payments', icon: 'payments', href: `class-payments?class-id=${id}` },
    c.active
      ? { label: 'Deactivate', icon: 'deactivate', danger: true, onClick: () => setActive(c, false) }
      : { label: 'Activate', icon: 'activate', onClick: () => setActive(c, true) },
  ], `Actions for ${c.name}`);
}

const badge = (c) => h('span', { class: `ex-class-badge ex-exam-${(c.exam || '').toLowerCase().replace(/[^a-z]+/g, '')}` }, `${c.year} · ${c.exam}`);
const statusPill = (c) => h('span', { class: `ex-status ${c.active ? '' : 'ex-off'}` }, c.active ? 'Active' : 'Inactive');
const handle = (c, list) => (canDrag() ? h('button', {
  type: 'button', class: 'ex-grip', 'aria-label': `Move ${c.name}. Use the arrow keys.`, 'data-grip': String(c.id),
  onkeydown: (e) => {
    const i = list.indexOf(c);
    if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') { e.preventDefault(); move(c, i - 1); }
    if (e.key === 'ArrowDown' || e.key === 'ArrowRight') { e.preventDefault(); move(c, i + 1); }
  },
}, icon('drag', { size: 18 })) : null);

/** Makes an element a drag source and drop target for reordering. */
function draggable(el, c) {
  if (!canDrag()) return el;
  el.draggable = true;
  el.addEventListener('dragstart', (e) => { e.dataTransfer.setData('text/plain', String(c.id)); e.dataTransfer.effectAllowed = 'move'; requestAnimationFrame(() => el.classList.add('ex-dragging')); });
  el.addEventListener('dragend', () => el.classList.remove('ex-dragging'));
  el.addEventListener('dragover', (e) => { e.preventDefault(); el.classList.add('ex-drop'); });
  el.addEventListener('dragleave', () => el.classList.remove('ex-drop'));
  el.addEventListener('drop', (e) => {
    e.preventDefault();
    el.classList.remove('ex-drop');
    const from = state.classes.find((x) => String(x.id) === e.dataTransfer.getData('text/plain'));
    if (from && from !== c) move(from, state.classes.indexOf(c));
  });
  return el;
}

// ---------- views ----------
function body(list) {
  if (state.view === 'table') return tableView(list);
  if (state.view === 'details') return detailsView(list);
  return cardsView(list);
}

function cardsView(list) {
  return h('div', { class: 'row g-3' }, list.map((c) => h('div', { class: 'col-12 col-md-6 col-xl-4 col-xxl-3' },
    draggable(h('article', { class: `ex-class-card2 h-100 ${c.active ? '' : 'ex-inactive'}`, 'data-id': String(c.id) },
      h('a', { href: `class-details?class-id=${encodeURIComponent(c.id)}`, class: 'ex-class-cover2', tabindex: '-1' },
        cover(c), badge(c), c.active ? null : h('span', { class: 'ex-cover-flag' }, 'Inactive')),
      h('div', { class: 'ex-class-body2' },
        h('div', { class: 'd-flex align-items-start gap-2' },
          h('div', { class: 'flex-grow-1 min-w-0' },
            h('span', { class: 'ex-class-subject' }, c.subject),
            h('a', { href: `class-details?class-id=${encodeURIComponent(c.id)}`, class: 'ex-class-name' }, c.name)),
          handle(c, list)),
        h('div', { class: 'ex-class-meta' },
          h('span', {}, icon('language', { size: 15 }), c.medium),
          h('span', {}, icon('students', { size: 15 }), `${c.students}${c.capacity ? ` / ${c.capacity}` : ''} students`),
          h('span', {}, icon('money', { size: 15 }), feeText(c))),
        c.times.length ? h('div', { class: 'ex-time-chips' }, c.times.slice(0, 3).map((t) => h('span', { class: `ex-time-chip ${t.mode}` }, timeText(t))), c.times.length > 3 ? h('span', { class: 'ex-time-chip' }, `+${c.times.length - 3}`) : null) : null,
        h('div', { class: 'ex-class-foot2' },
          h('a', { href: `class-details?class-id=${encodeURIComponent(c.id)}`, class: 'custom-btn ex-btn-md flex-grow-1 border-0' }, icon('view', { size: 18 }), 'View Class'),
          h('a', { href: `edit-class?class-id=${encodeURIComponent(c.id)}`, class: 'ex-icon-btn', 'aria-label': `Edit ${c.name}`, title: 'Edit' }, icon('edit', { size: 18 })),
          menu(c)))), c))));
}

function tableView(list) {
  const drag = canDrag();
  return h('div', { class: 'component-theme p-3' }, h('div', { class: 'table-responsive' },
    h('table', { class: 'table ex-v3-table align-middle m-0' },
      h('thead', {}, h('tr', {},
        drag ? h('th', { scope: 'col', class: 'ex-col-order' }, 'Order') : null,
        h('th', { scope: 'col' }, 'Class'), h('th', { scope: 'col' }, 'Exam'), h('th', { scope: 'col' }, 'Medium'),
        h('th', { scope: 'col' }, 'Students'), h('th', { scope: 'col' }, 'Monthly fee'), h('th', { scope: 'col' }, 'Weekly times'),
        h('th', { scope: 'col' }, 'Status'), h('th', { scope: 'col', class: 'text-center' }, 'Actions'))),
      h('tbody', {}, list.map((c) => draggable(h('tr', { 'data-id': String(c.id), class: c.active ? '' : 'ex-row-off' },
        drag ? h('td', { class: 'ex-col-order' }, handle(c, list)) : null,
        h('td', {}, h('a', { href: `class-details?class-id=${encodeURIComponent(c.id)}`, class: 'd-flex align-items-center gap-3 text-decoration-none' },
          cover(c, 'ex-thumb-sm'),
          h('span', { class: 'd-flex flex-column' }, h('span', { class: 'text-14 main-text fw-medium ex-clamp-1' }, c.name), h('span', { class: 'text-12 sub-text' }, `${c.subject} · ${TYPE[c.class_type] ?? 'Class'}`)))),
        h('td', { class: 'text-14' }, `${c.year} ${c.exam}`),
        h('td', { class: 'text-14' }, c.medium),
        h('td', { class: 'text-14' }, `${c.students}${c.capacity ? ` / ${c.capacity}` : ''}`),
        h('td', { class: 'text-14 text-nowrap' }, feeText(c)),
        h('td', { class: 'text-12' }, c.times.length ? c.times.map((t) => h('div', { class: 'text-nowrap' }, timeText(t))) : h('span', { class: 'sub-text' }, 'None yet')),
        h('td', {}, statusPill(c)),
        h('td', { class: 'text-center' }, menu(c))), c))))));
}

function detailsView(list) {
  return h('div', { class: 'd-flex flex-column gap-3' }, list.map((c) => draggable(h('article', { class: `component-theme ex-class-row ${c.active ? '' : 'ex-inactive'}`, 'data-id': String(c.id) },
    h('a', { href: `class-details?class-id=${encodeURIComponent(c.id)}`, class: 'ex-class-row-cover', tabindex: '-1' }, cover(c), badge(c)),
    h('div', { class: 'ex-class-row-main' },
      h('div', { class: 'd-flex align-items-start gap-2' },
        h('div', { class: 'flex-grow-1 min-w-0' },
          h('div', { class: 'd-flex flex-wrap align-items-center gap-2 mb-1' }, h('span', { class: 'ex-class-subject m-0' }, c.subject), h('span', { class: 'ex-tag-v3' }, TYPE[c.class_type] ?? 'Class'), statusPill(c)),
          h('a', { href: `class-details?class-id=${encodeURIComponent(c.id)}`, class: 'ex-class-name' }, c.name),
          c.description ? h('p', { class: 'text-12 sub-text m-0 mt-1 ex-clamp-2' }, c.description) : null),
        handle(c, list), menu(c)),
      h('div', { class: 'row g-3 mt-1' },
        info('timetable', 'Weekly times', c.times.length ? c.times.map((t) => h('div', {}, timeText(t), t.mode === 'online' ? h('span', { class: 'ex-mini-tag' }, 'Zoom') : null)) : 'None yet'),
        info(c.delivery_mode === 'online' ? 'online' : 'location', MODE[c.delivery_mode] ?? 'Where', c.delivery_mode === 'online' ? 'On Zoom' : (c.location || 'Hall not set')),
        info('money', 'Monthly fee', c.is_free ? 'Free' : c.fee == null ? 'Not set' : [money(c.fee), h('div', { class: 'text-12 sub-text' }, `due on day ${c.due_day ?? 10}`)]),
        info('students', 'Students', [`${c.students}${c.capacity ? ` of ${c.capacity}` : ''}`,
          c.capacity ? h('div', { class: 'ex-mini-meter mt-1' }, h('span', { style: { width: `${Math.min(100, Math.round((c.students / c.capacity) * 100))}%` } })) : h('div', { class: 'text-12 sub-text' }, JOIN[c.enrollment_mode] ?? '')])),
      h('div', { class: 'd-flex flex-wrap gap-2 mt-3' },
        h('a', { href: `class-details?class-id=${encodeURIComponent(c.id)}`, class: 'custom-btn ex-btn-md px-4 border-0' }, icon('view', { size: 18 }), 'View Class'),
        h('a', { href: `edit-class?class-id=${encodeURIComponent(c.id)}`, class: 'custom-outline-btn ex-btn-md px-4' }, icon('edit', { size: 18 }), 'Edit'),
        h('a', { href: `class-students?class-id=${encodeURIComponent(c.id)}`, class: 'custom-outline-btn ex-btn-md px-4' }, icon('students', { size: 18 }), 'Students'),
        h('a', { href: `class-time-table?class-id=${encodeURIComponent(c.id)}`, class: 'custom-outline-btn ex-btn-md px-4' }, icon('timetable', { size: 18 }), 'Time Table')))), c)));
}

function info(ico, label, value) {
  return h('div', { class: 'col-12 col-sm-6 col-xl-3' },
    h('div', { class: 'd-flex gap-2' },
      h('span', { class: 'ex-info-ico' }, icon(ico, { size: 18 })),
      h('div', { class: 'min-w-0' }, h('div', { class: 'text-12 sub-text' }, label), h('div', { class: 'text-14 main-text' }, value))));
}

// ---------- actions ----------
let saveTimer = null;
function move(c, to) {
  const list = state.classes;
  const from = list.indexOf(c);
  if (to < 0 || to >= list.length || from === to) return;
  list.splice(to, 0, list.splice(from, 1)[0]);
  draw();
  requestAnimationFrame(() => root.querySelector(`[data-grip="${c.id}"]`)?.focus({ preventScroll: true }));
  clearTimeout(saveTimer);
  saveTimer = setTimeout(async () => {
    try {
      await api.post('/tutor/classes/order', { ids: state.classes.map((x) => x.id) });
      Toast.success('Order saved.');
    } catch (e) { Toast.error(e.message); }
  }, 500);
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
