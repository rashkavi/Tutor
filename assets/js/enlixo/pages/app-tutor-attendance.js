// Tutor → Academic → Attendance → Attendance Dashboard (tutor/attendance-dashboard.php).
// V3 order: header card → period + class strip (Day / Week / Month / Custom range, any class) → stat cards →
// chart panels (attendance per day, status split, classes) → students to follow up → one table card with tabs
// (Class Days, Students). Data: GET /tutor/attendance/overview + one list endpoint per tab, all with class / from / to.
import { DataView } from '../components/DataView.js';
import { StatCard } from '../components/StatCard.js';
import { Tabs } from '../components/Tabs.js';
import { ActionMenu } from '../components/Popover.js';
import { QuickGuide } from '../components/QuickGuide.js';
import {
  api, gate, has, lockBadge, h, fill, icon, lkr, plural, cell, person, panel, EmptyState, Toast, previewNote, waLink,
  statusChip, stateChip, rateBar, HEX, periodRange, stepPeriod, today, addDays, dm, ampm,
} from './attendance-common.js';

QuickGuide.attachAll();
const root = document.getElementById('attendance-page');
const params = new URLSearchParams(location.search);
const state = {
  type: ['day', 'week', 'month', 'range'].includes(params.get('period')) ? params.get('period') : 'month',
  date: params.get('date') ?? today(),
  from: params.get('from') ?? addDays(today(), -29),
  to: params.get('to') ?? today(),
  class: params.get('class') ?? '',
};
const dark = () => document.documentElement.getAttribute('data-custom-theme') === 'dark';
let D; let tabs; const charts = [];

fill(document.getElementById('attendance-actions'),
  h('div', { class: 'col-6 d-flex justify-content-end align-items-center' },
    h('a', { class: 'bg-transparent border-0 dark-theme', href: 'attendance-settings', title: 'Attendance Settings', 'aria-label': 'Attendance Settings' }, icon('settings', { size: 30 }))),
  h('div', { class: 'col-6' }, h('a', { class: 'custom-btn w-100 text-decoration-none', href: 'mark-attendance' }, 'Mark Attendance')));

const range = () => periodRange(state);
function writeUrl() {
  const p = new URLSearchParams(location.search);
  p.set('period', state.type);
  if (state.type === 'range') { p.set('from', state.from); p.set('to', state.to); p.delete('date'); } else { p.set('date', state.date); p.delete('from'); p.delete('to'); }
  if (state.class) p.set('class', state.class); else p.delete('class');
  history.replaceState(null, '', `${location.pathname}?${p}`);
}
const query = () => { const r = range(); return { from: r.from, to: r.to, ...(state.class ? { class: state.class } : {}) }; };

// ------------------------------------------------------------------ period + class strip
function strip() {
  const r = range();
  const seg = h('div', { class: 'ad-seg' }, [['day', 'Day'], ['week', 'Week'], ['month', 'Month'], ['range', 'Custom']].map(([v, t]) => h('label', {},
    h('input', { type: 'radio', name: 'at-period', value: v, checked: state.type === v, onchange: () => {
      if (v === 'range' && state.type !== 'range') { const cur = range(); state.from = cur.from; state.to = cur.to > today() ? today() : cur.to; }
      state.type = v; load();
    } }), t)));
  const go = (d) => { Object.assign(state, stepPeriod(state, d)); load(); };
  const nav = state.type === 'range'
    ? h('div', { class: 'd-flex align-items-center gap-2 flex-wrap' },
      h('input', { type: 'date', class: 'form-control custom-input at-date', value: state.from, max: state.to, 'aria-label': 'From', placeholder: 'Choose the first day', onchange: (e) => { if (e.target.value) { state.from = e.target.value; load(); } } }),
      h('span', { class: 'text-13 sub-text' }, 'to'),
      h('input', { type: 'date', class: 'form-control custom-input at-date', value: state.to, min: state.from, 'aria-label': 'To', placeholder: 'Choose the last day', onchange: (e) => { if (e.target.value) { state.to = e.target.value; load(); } } }))
    : h('div', { class: 'd-flex align-items-center gap-2' },
      h('button', { type: 'button', class: 'fe-step', 'aria-label': `Previous ${state.type}`, onclick: () => go(-1) }, icon('chevron-left', { size: 18 })),
      h('div', { class: 'text-center', style: { minWidth: '210px' } }, h('div', { class: 'sub-topic' }, r.title), h('div', { class: 'text-12 sub-text' }, r.sub)),
      h('button', { type: 'button', class: 'fe-step', 'aria-label': `Next ${state.type}`, disabled: r.to >= today(), onclick: () => go(1) }, icon('chevron-right', { size: 18 })),
      state.type === 'day' ? h('input', { type: 'date', class: 'form-control custom-input at-date', value: state.date, max: today(), 'aria-label': 'Pick a day', placeholder: 'Choose a day', onchange: (e) => { if (e.target.value) { state.date = e.target.value; load(); } } })
        : r.to < today() ? h('button', { type: 'button', class: 'custom-btn-outline px-3', onclick: () => { state.date = today(); load(); } }, `This ${state.type}`) : null);
  const cls = h('select', { class: 'form-select custom-select', 'aria-label': 'Class', onchange: (e) => { state.class = e.target.value; load(); } },
    h('option', { value: '' }, 'All classes'), D.classes.map((c) => h('option', { value: c.id, selected: String(c.id) === String(state.class) }, c.name)));
  return h('div', { class: 'col-12 component-theme p-3 mb-4 d-flex flex-wrap align-items-center gap-3' }, seg, nav, h('div', { class: 'flex-grow-1' }), h('div', { style: { minWidth: '280px' } }, cls));
}

// ------------------------------------------------------------------ stat cards
function stats() {
  const s = D.stats;
  const diff = s.rate.value - s.rate.before;
  return StatCard.row([
    { icon: 'attendance', label: 'Attendance Rate', value: s.held.value ? `${s.rate.value}%` : '-', trend: s.held.value && s.rate.before ? { text: `${diff >= 0 ? '+' : ''}${diff}%`, dir: diff >= 0 ? 'up' : 'down' } : null, caption: s.held.value ? 'present, late or joined online' : 'no finished class day yet', bars: s.rate.bars },
    { icon: 'calendar', label: 'Class Days Held', value: s.held.value, caption: [s.held.cancelled ? `${s.held.cancelled} cancelled` : null, s.held.upcoming ? `${s.held.upcoming} still to come` : null].filter(Boolean).join(' · ') || 'in this period', onClick: () => tabs.show('days') },
    { icon: 'user-check', label: 'Present', value: s.present.value, caption: `${s.present.physical} at the class · ${s.present.online} online` },
    { icon: 'late', label: 'Late', value: s.late.value, caption: `${s.late.share}% of those who came` },
    { icon: 'user-cross', label: 'Absent', value: s.absent.value, caption: `${plural(s.absent.students, 'student')} missed a class`, menu: [{ label: 'Students who missed 2 in a row', icon: 'filter', onClick: () => tabs.show('students', { streak: 'yes' }) }] },
    { icon: 'recordings', label: 'Watched Recording', value: s.watched.value, caption: `${s.watched.share}% of online absences caught up` },
  ], { cols: 'col-12 col-md-6 col-xl-4' });
}

// ------------------------------------------------------------------ charts + panels
function panels() {
  const r = range();
  const trendTitle = r.from === r.to ? 'Attendance by Class' : D.span > 31 ? 'Attendance by Week' : 'Attendance by Day';
  const trend = h('div', { class: 'ad-chart-box flex-grow-1' }, h('canvas', { id: 'atTrend' }));
  const donut = h('div', { class: 'ad-chart-box ad-chart-round' }, h('canvas', { id: 'atSplit' }));
  const total = Object.values(D.split).reduce((a, b) => a + b, 0);
  const legend = h('div', { class: 'mt-3' }, [['present', 'Present'], ['late', 'Late'], ['joined', 'Joined Online'], ['watched', 'Watched Recording'], ['absent', 'Absent']].map(([k, t]) => h('div', { class: 'ad-legend-row' },
    h('span', { class: 'd-flex align-items-center gap-2' }, h('span', { class: 'ad-legend-dot', style: { background: HEX[k] } }), t), h('span', { class: 'main-text fw-medium' }, `${D.split[k]} · ${total ? Math.round((D.split[k] / total) * 100) : 0}%`))));
  const lock = (el) => (has('reports.attendance') ? el : gate.wrap('reports.attendance', h('div', { style: { height: '260px' } })));
  const classes = h('div', {}, D.by_class.length ? D.by_class.map((c) => h('a', { class: 'ad-todo', href: '#', onclick: (e) => { e.preventDefault(); state.class = String(c.id); load(); } },
    h('span', { class: 'ad-todo-icon' }, icon(c.mode === 'Online' ? 'online' : 'physical', { size: 20 })),
    h('span', { class: 'flex-grow-1 min-w-0' }, h('span', { class: 'text-14 main-text d-block text-truncate' }, c.short), h('span', { class: 'text-12 sub-text' }, c.held ? `${plural(c.held, 'class day')} · ${c.schedule}` : `No class day · ${c.schedule}`)),
    h('span', { class: 'd-flex flex-column align-items-end gap-1' }, h('span', { class: 'text-13 main-text fw-medium' }, c.held ? `${c.rate}%` : '-'), h('span', { class: 'ad-bar' }, h('i', { class: c.rate < 50 ? 'bad' : c.rate < 80 ? 'warn' : '', style: { width: `${c.rate}%` } })))))
    : EmptyState.empty({ title: 'No classes', text: 'Create a class to see its attendance.' }));
  return h('div', { class: 'row g-4 mb-4' },
    h('div', { class: 'col-12 col-xl-8' }, panel(trendTitle, 'How many students came to each class day in this period: present, late, joined online, watched the recording later, or absent.', lock(trend), { link: ['Class Days', '#at-tables'] })),
    h('div', { class: 'col-12 col-xl-4' }, panel('Present or Absent', 'Every student mark in this period, by status.', !total ? EmptyState.empty({ icon: 'calendar-empty', title: 'No marks yet', text: 'Marks show here once a class day in this period is over.' }) : has('reports.attendance') ? h('div', {}, donut, legend) : lock(donut))),
    h('div', { class: 'col-12 col-xl-6' }, panel(state.class ? 'This Class' : 'Classes', 'Attendance rate per class in this period. Click a class to see only that class.', classes)),
    h('div', { class: 'col-12 col-xl-6' }, panel('Students to Follow Up', 'Students who missed the last 2 or more class days in a row. Send a WhatsApp message, and check the fees they owe.', followUp(), { link: D.follow_total > D.follow_up.length ? [`See all ${D.follow_total}`, '#at-tables'] : null })));
}

function followUp() {
  if (!D.follow_up.length) return EmptyState.empty({ icon: 'user-check', title: 'Nobody to follow up', text: 'No student missed two class days in a row in this period.' });
  return h('div', {}, D.follow_up.map((f) => h('div', { class: 'ad-todo' },
    person(f.student.name, `${f.class.short} · last came ${f.last ? dm(f.last) : 'never'}`),
    h('span', { class: 'flex-grow-1' }),
    h('span', { class: 'd-flex flex-column align-items-end gap-1' },
      h('span', { class: 'table-btn table-btn-danger ad-chip' }, `Missed ${f.streak}`),
      f.owed ? h('span', { class: 'text-12 text-dark-red' }, `owes ${lkr(f.owed)}`) : h('span', { class: 'text-12 sub-text' }, 'fees paid')),
    h('a', { class: 'custom-btn-outline ex-btn-sm px-2 ms-2 text-decoration-none', href: waLink(f.student.whatsapp, `Dear ${f.student.name.split(' ')[0]}, we missed you at the last ${f.streak} classes of ${f.class.name}. Is everything all right? Please join the next class.`), target: '_blank', title: `WhatsApp ${f.student.name}`, 'aria-label': `WhatsApp ${f.student.name}` }, icon('whatsapp', { size: 16 })))));
}

function drawCharts() {
  charts.splice(0).forEach((c) => c.destroy());
  if (!window.Chart || !has('reports.attendance')) return;
  const tick = dark() ? '#A9B4C6' : '#6E7485'; const grid = dark() ? 'rgba(169,180,198,.12)' : 'rgba(110,116,133,.12)';
  const t = document.getElementById('atTrend');
  if (t) {
    const c = D.chart;
    const ds = [['present', 'Present'], ['late', 'Late'], ['joined', 'Joined Online'], ['watched', 'Watched Recording'], ['absent', 'Absent']].map(([k, label]) => ({ label, data: c[k], backgroundColor: HEX[k], maxBarThickness: 22, stack: 'a' }));
    charts.push(new window.Chart(t, { type: 'bar', data: { labels: c.labels, datasets: ds }, options: { maintainAspectRatio: false, plugins: { legend: { position: 'top', align: 'end', labels: { color: tick, boxWidth: 12, boxHeight: 12 } } },
      scales: { x: { stacked: true, ticks: { color: tick, maxRotation: 0, autoSkip: true }, grid: { color: 'transparent' } }, y: { stacked: true, beginAtZero: true, ticks: { color: tick, precision: 0 }, grid: { color: grid } } } } }));
  }
  const d = document.getElementById('atSplit');
  if (d) {
    const keys = ['present', 'late', 'joined', 'watched', 'absent'];
    charts.push(new window.Chart(d, { type: 'doughnut', data: { labels: ['Present', 'Late', 'Joined Online', 'Watched Recording', 'Absent'], datasets: [{ data: keys.map((k) => D.split[k]), backgroundColor: keys.map((k) => HEX[k]), borderWidth: 0 }] },
      options: { maintainAspectRatio: false, cutout: '68%', plugins: { legend: { display: false } } } }));
  }
}

// ------------------------------------------------------------------ tables
const classFilter = () => (state.class ? [] : [{ key: 'class', label: 'Class', allLabel: '- All Classes -', options: D.classes.map((c) => ({ value: c.id, label: c.name })) }]);
const exportLocked = () => (has('attendance.export') ? null : { onLocked: () => gate.openUpgrade('attendance.export') });

const marks = (s) => (s.mode === 'Online' ? 'Online · Zoom' : ['Physical', s.methods.QR && `QR ${s.methods.QR}`, s.methods.Barcode && `Barcode ${s.methods.Barcode}`, s.methods.Manual && `Manual ${s.methods.Manual}`].filter(Boolean).join(' · '));

function daysList(p) {
  new DataView({
    api, endpoint: '/tutor/attendance/sessions', key: 'att-days', urlPrefix: 'd_', perPage: 10, perPageOptions: [10, 25, 50, 100], noun: ['class day', 'class days'],
    params: query(),
    searchPlaceholder: 'Search by class name or date',
    filters: [...classFilter(), { key: 'state', label: 'Status', allLabel: '- All Status -', options: [{ value: 'live', label: 'Going On Now' }, { value: 'done', label: 'Finished' }, { value: 'upcoming', label: 'Not Started' }, { value: 'cancelled', label: 'Cancelled' }] },
      { key: 'mode', label: 'Class Type', allLabel: '- All Types -', options: [{ value: 'Physical', label: 'Physical' }, { value: 'Online', label: 'Online' }] }],
    exportCsv: has('attendance.export') ? { filename: `class-days-${range().from}-to-${range().to}.csv`, columns: [['Date', (s) => s.date], ['Day', (s) => s.day], ['Start', (s) => s.start], ['End', (s) => s.end], ['Class', (s) => s.class.name], ['Type', (s) => s.mode], ['Students', (s) => s.expected], ['Present', (s) => s.present], ['Late', (s) => s.late], ['Joined Online', (s) => s.joined], ['Watched', (s) => s.watched], ['Absent', (s) => s.absent], ['Not Marked', (s) => s.unmarked], ['Rate %', (s) => s.rate]] } : null,
    toolbarExtra: has('attendance.export') ? null : h('button', { type: 'button', class: 'custom-btn-outline w-100 d-inline-flex align-items-center justify-content-center gap-2', onclick: () => gate.openUpgrade('attendance.export') }, 'Export Report', lockBadge('attendance.export')),
    columns: [
      { key: 'date', label: 'Class Day', sortable: true, render: (s) => cell(`${s.day} ${dm(s.date)} ${s.date.slice(0, 4)}`, `${ampm(s.start)} - ${ampm(s.end)}`) },
      { key: 'class', label: state.class ? 'Marked By' : 'Class', wrap: true, render: (s) => cell(state.class ? s.mode : s.class.short, marks(s)) },
      { key: 'rate', label: 'Attended', sortable: true, render: (s) => (['upcoming', 'cancelled'].includes(s.state) ? cell('-', `${plural(s.expected, 'student')}`) : rateBar(s.rate, `${s.attended} of ${s.expected}${s.late ? ` · ${s.late} late` : ''}`)) },
      { key: 'absent', label: 'Absent', render: (s) => (['upcoming', 'cancelled'].includes(s.state) ? cell('-') : cell(String(s.absent), s.unmarked ? `${s.unmarked} not marked` : s.watched ? `${s.watched} watched later` : null)) },
      { key: 'state', label: 'Status', render: (s) => stateChip(s.state) },
      { key: 'go', label: '', width: '140px', render: (s) => (s.state === 'cancelled' || s.state === 'upcoming' ? h('span', {})
        : h('a', { class: 'custom-btn ex-btn-sm px-3 text-decoration-none', href: `mark-attendance?session=${s.id}${s.state === 'done' ? '&tab=manual' : ''}` }, s.state === 'live' ? 'Mark Now' : 'View')) },
    ].filter(Boolean),
    empty: { icon: 'calendar-empty', title: 'No class days in this period', text: 'Class days come from the Time Table. Try another period or class.' },
  }).mount(p);
}

function studentsList(p, preset = {}) {
  if (preset.streak) { const u = new URLSearchParams(location.search); u.set('s_streak', preset.streak); history.replaceState(null, '', `${location.pathname}?${u}`); }
  new DataView({
    api, endpoint: '/tutor/attendance/students', key: 'att-students', urlPrefix: 's_', perPage: 10, perPageOptions: [10, 25, 50, 100], noun: ['student', 'students'],
    params: query(),
    searchPlaceholder: 'Search by student name, Student ID, WhatsApp or class',
    filters: [...classFilter(),
      { key: 'band', label: 'Attendance', allLabel: '- Any Rate -', options: [{ value: 'low', label: 'Below 50%' }, { value: 'mid', label: '50% - 79%' }, { value: 'high', label: '80% and above' }] },
      { key: 'streak', label: 'Missed in a Row', allLabel: '- Any -', options: [{ value: 'yes', label: '2 or more class days' }] },
      { key: 'owes', label: 'Fees', allLabel: '- Any -', options: [{ value: 'yes', label: 'Owes fees' }, { value: 'no', label: 'Paid up' }] }],
    exportCsv: has('attendance.export') ? { filename: `student-attendance-${range().from}-to-${range().to}.csv`, columns: [['Student ID', (r) => r.student.sid], ['Student', (r) => r.student.name], ['WhatsApp', (r) => r.student.whatsapp], ['Class', (r) => r.class.name], ['Class Days', (r) => r.held], ['Attended', (r) => r.attended], ['Late', (r) => r.late], ['Watched', (r) => r.watched], ['Absent', (r) => r.absent], ['Rate %', (r) => r.rate], ['Last Came', (r) => r.last ?? ''], ['Missed in a Row', (r) => r.streak], ['Fees Owed (LKR)', (r) => r.owed]] } : null,
    toolbarExtra: has('attendance.export') ? null : h('button', { type: 'button', class: 'custom-btn-outline w-100 d-inline-flex align-items-center justify-content-center gap-2', onclick: () => gate.openUpgrade('attendance.export') }, 'Export Report', lockBadge('attendance.export')),
    columns: [
      { key: 'name', label: 'Student', sortable: true, render: (r) => person(r.student.name, r.student.sid) },
      !state.class && { key: 'class', label: 'Class', wrap: true, render: (r) => cell(r.class.short, r.class.mode) },
      { key: 'rate', label: 'Attendance', sortable: true, render: (r) => rateBar(r.rate, `${r.attended} of ${r.held}${r.late ? ` · ${r.late} late` : ''}`) },
      { key: 'absent', label: 'Absent', sortable: true, render: (r) => cell(String(r.absent), r.watched ? `${r.watched} watched later` : null) },
      { key: 'last', label: 'Last Came', render: (r) => h('div', { class: 'd-flex flex-column align-items-start gap-1' }, h('span', { class: 'text-14 main-text' }, r.last ? dm(r.last) : 'Not in this period'),
        r.streak >= 2 ? h('span', { class: 'table-btn table-btn-danger ad-chip' }, `Missed ${r.streak} in a row`) : null) },
      { key: 'owed', label: 'Fees', sortable: true, render: (r) => (r.owed ? cell(h('span', { class: 'text-dark-red' }, lkr(r.owed)), plural(r.owed_months, 'month')) : cell('Paid', 'nothing due')) },
      { key: 'go', label: '', width: '60px', render: (r) => ActionMenu.kebab([
        { label: 'Student Fee Card', icon: 'id-card', href: `student-fees?student-id=${r.student.id}` },
        { label: 'Send WhatsApp Message', icon: 'whatsapp', onClick: () => window.open(waLink(r.student.whatsapp, `Dear ${r.student.name.split(' ')[0]}, your attendance in ${r.class.name} is ${r.rate}% (${r.attended} of ${r.held} class days). Please come to every class.`), '_blank') },
      ], `Actions for ${r.student.name}`) },
    ].filter(Boolean),
    empty: { icon: 'students', title: 'No students in this period', text: 'Students show here once their class has a class day in this period.' },
  }).mount(p);
}

function table() {
  const card = h('div', { class: 'col-12 component-theme p-4', id: 'at-tables' });
  tabs = new Tabs({ param: 'list', tabs: [
    { id: 'days', label: 'Class Days', badge: D.counts.sessions, render: (p) => daysList(p) },
    { id: 'students', label: 'Students', badge: D.counts.students, render: (p) => studentsList(p) },
  ] }).mount(card);
  tabs.show = (id, preset = {}) => {
    if (preset.streak) { const u = new URLSearchParams(location.search); u.set('s_streak', preset.streak); history.replaceState(null, '', `${location.pathname}?${u}`); }
    card.querySelectorAll('.nav-link')[['days', 'students'].indexOf(id)]?.click(); card.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };
  return card;
}

function liveNote() {
  if (!D.live.length) return null;
  return h('div', { class: 'col-12 ad-preview at-live mb-4' }, icon('attendance', { size: 18 }),
    h('span', { class: 'flex-grow-1' }, h('b', {}, `${D.live.map((s) => s.class.short).join(', ')} ${D.live.length === 1 ? 'is' : 'are'} going on now. `), `${D.live.reduce((a, s) => a + s.expected - s.unmarked, 0)} of ${D.live.reduce((a, s) => a + s.expected, 0)} students marked so far.`),
    h('a', { class: 'custom-btn ex-btn-sm px-3 text-decoration-none', href: `mark-attendance?session=${D.live[0].id}` }, 'Mark Attendance'));
}

async function load() {
  writeUrl();
  fill(root, h('div', { class: 'placeholder-glow' }, h('span', { class: 'placeholder col-12', style: { height: '140px' } })));
  try {
    ({ data: D } = await api.get('/tutor/attendance/overview', query()));
    fill(root, previewNote(), strip(), liveNote(), stats(), panels(), table());
    if (window.Chart) drawCharts(); else window.addEventListener('load', drawCharts, { once: true });
  } catch (e) {
    fill(root, EmptyState.error(e.message, load));
  }
}
load();
