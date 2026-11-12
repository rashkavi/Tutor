// Tutor → Dashboard (tutor/dashboard.php). Same order and look as the ENLIXO V3 dashboards:
// header card → today strip → stat cards → chart panels → lists → class performance table card.
// Data: GET /api/v1/tutor/dashboard (one request). Charts are Chart.js, colours from the V3 palette.
import { h, fill, fmt } from '../core/dom.js';
import { ApiClient } from '../core/ApiClient.js';
import { icon } from '../core/icons.js';
import { StatCard } from '../components/StatCard.js';
import { ActionMenu } from '../components/Popover.js';
import { DashboardKit } from '../components/DashboardKit.js';
import { QuickGuide } from '../components/QuickGuide.js';
import { EmptyState } from '../components/EmptyState.js';
import { DataView } from '../components/DataView.js';
import { infoTip } from '../components/InfoTip.js';
import { BsModal } from '../components/BsModal.js';
import { Toast } from '../components/Toast.js';

const api = new ApiClient();
const root = document.getElementById('tutor-dashboard');
const HEX = { primary: '#0061D2', success: '#48CD5D', orange: '#FF9500', danger: '#D85E44', purple: '#AF52DE', grey: '#C3C8D1', light: '#B9CFF2' };
const dark = () => document.documentElement.getAttribute('data-custom-theme') === 'dark';
const T = () => ({ tick: dark() ? '#A9B4C6' : '#6E7485', grid: dark() ? 'rgba(169,180,198,.12)' : 'rgba(110,116,133,.12)' });
const lkr = (n) => fmt.money(Math.round(n));
const short = (n) => (n >= 1e6 ? `${(n / 1e6).toFixed(1)}M` : n >= 1e3 ? `${Math.round(n / 1e3)}K` : String(n));
const pct = (a, b) => (b ? Math.round((a / b) * 100) : 0);
const perc = (v) => (v === null || v === undefined ? '-' : `${v}%`);
const plural = (n, one, many = `${one}s`) => `${n} ${n === 1 ? one : many}`;

QuickGuide.attachAll();
const ACTIONS = [
  { label: 'Create a Class', icon: 'add', href: 'create-class' },
  { label: 'Add New Student', icon: 'user-add', href: 'add-student' },
  { label: 'Mark Attendance', icon: 'attendance', href: 'attendance' },
  { label: 'Send a Message', icon: 'send', href: 'message-center' },
];

// ---------- small building blocks (V3 dashboard panels) ----------
const panel = (title, info, body, { link = null, cls = '' } = {}) => h('div', { class: `component-theme p-4 h-100 d-flex flex-column ${cls}` },
  h('div', { class: 'd-flex justify-content-between align-items-center gap-2' },
    h('span', { class: 'sub-topic d-inline-flex align-items-center gap-2' }, title, infoTip(info, title)),
    link ? h('a', { class: 'text-14 dark-theme text-decoration-none', href: link[1] }, link[0]) : null),
  body);
const chartBox = (id, round = false, empty = null) => h('div', { class: `td-chart-box mt-3 ${round ? 'td-chart-round' : 'flex-grow-1'}` },
  empty ? h('div', { class: 'td-chart-empty' }, EmptyState.empty(empty)) : h('canvas', { id }));
const legend = (rows, fmtVal = (v) => v) => {
  const total = rows.reduce((a, r) => a + r.value, 0);
  return h('div', { class: 'td-legend mt-3' }, rows.map((r) => h('div', { class: 'td-legend-row' },
    h('span', { class: 'td-legend-dot', style: { background: HEX[r.color] } }),
    h('span', { class: 'text-14 main-text flex-grow-1' }, r.label),
    h('span', { class: 'text-14 main-text fw-medium' }, fmtVal(r.value)),
    h('span', { class: 'text-12 sub-text td-legend-pct' }, `${pct(r.value, total)}%`))));
};
const draw = (id, cfg) => { const el = document.getElementById(id); if (el && window.Chart) new window.Chart(el, cfg); };
const sum = (a) => (a ?? []).reduce((x, y) => x + (Number(y) || 0), 0);
const link = (href, external, attrs, ...kids) => h('a', { href, ...(external ? { target: '_blank', rel: 'noopener' } : {}), ...attrs }, ...kids);
const axes = (stacked = false, money = false) => ({
  x: { stacked, ticks: { color: T().tick }, grid: { color: 'transparent' } },
  y: { stacked, beginAtZero: true, ticks: { color: T().tick, precision: 0, callback: money ? (v) => short(v) : undefined }, grid: { color: T().grid } },
});
const legendTop = { position: 'top', align: 'end', labels: { color: T().tick, boxWidth: 12, boxHeight: 12 } };

// ---------- sections ----------
function today(d) {
  const next = d.today.find((c) => !c.done);
  const waiting = d.waiting.total ? [' ', h('b', {}, plural(d.waiting.total, 'thing')), ' waiting for you.'] : '';
  const line = d.today.length
    ? ['You have ', h('b', {}, `${plural(d.today.length, 'class', 'classes')} today`),
      next ? ['. Next: ', h('b', {}, next.name), `, ${next.time} (${next.mode}).`] : '. All done for today.', waiting]
    : ['No classes today.', waiting];
  return DashboardKit.greeting({
    name: root.dataset.name,
    line,
    extra: h('div', { class: 'd-flex flex-wrap align-items-center gap-2 mt-2' },
      d.exams.map((x) => h('span', { class: 'td-countdown', title: `${x.name} starts ${fmt.date(x.date)}` }, icon('hourglass', { size: 16 }), h('b', {}, plural(x.days, 'day')), ` to the ${x.name} exam`)),
      h('button', { type: 'button', class: 'ex-link-add td-exam-link', onclick: () => examDates() }, d.exams.length ? 'Exam dates' : 'Add your exam dates for a countdown')),
    actions: ACTIONS,
    primary: next ? link(next.href, next.external, { class: 'custom-btn px-4 d-inline-flex align-items-center gap-2 text-decoration-none' }, icon(next.mode === 'Online' ? 'online' : 'attendance', { size: 18 }), next.mode === 'Online' ? 'Start Class' : 'Mark Attendance') : null,
  });
}

async function examDates() {
  let data;
  try { data = (await api.get('/tutor/exam-dates')).data; } catch (e) { Toast.error(e.message); return; }
  if (!data.ready) { Toast.error('This section is not available yet. Please contact your system administrator.'); return; }
  if (!data.exams.length) { Toast.error('Create a class first. Its exam appears here.'); return; }
  const inputs = data.exams.map((x, i) => h('input', { type: 'date', class: 'form-control custom-input', id: `td-exam-${i}`, value: x.date ?? '', placeholder: 'Choose the first day of the exam' }));
  const body = h('div', {},
    h('p', { class: 'text-14 sub-text mb-3' }, 'The first day of each exam your classes prepare for. The dashboard counts the days down. Leave a date empty to hide its countdown.'),
    h('div', { class: 'row gx-4 gy-3' }, data.exams.map((x, i) => h('div', { class: 'col-12 col-md-6' },
      h('label', { class: 'text-14 main-text mb-1 d-inline-flex align-items-center gap-2', for: `td-exam-${i}` }, `${x.name} exam`,
        infoTip('The day the exam starts, from the Department of Examinations time table (doenets.lk) or your school. For example 2027-02-02.', `${x.name} exam`)),
      inputs[i], h('div', { class: 'text-12 text-dark-red mt-1 d-none', id: `td-exam-${i}-err` })))));
  const save = BsModal.button('Save', { primary: true, onclick: async () => {
    inputs.forEach((el, i) => { el.classList.remove('is-invalid'); document.getElementById(`td-exam-${i}-err`).classList.add('d-none'); });
    try {
      await api.put('/tutor/exam-dates', { exams: data.exams.map((x, i) => ({ exam_type_id: x.exam_type_id, year_id: x.year_id, date: inputs[i].value || null })) });
      m.close();
      Toast.success('Exam dates saved.');
      load();
    } catch (e) {
      const errs = e.details ?? {};
      Object.entries(errs).forEach(([k, msg]) => { const i = Number(k.replace('date_', '')); inputs[i]?.classList.add('is-invalid'); const el = document.getElementById(`td-exam-${i}-err`); if (el) { el.textContent = msg; el.classList.remove('d-none'); } });
      if (!Object.keys(errs).length) Toast.error(e.message);
    }
  } });
  const m = BsModal.open({ title: 'Exam Dates', subtitle: 'Countdown on your dashboard', icon: 'calendar', body, footerEnd: [BsModal.button('Cancel', { onclick: () => m.close() }), save] });
}

function stats(d) {
  const s = d.stats;
  // Four cards, one colour each. "Waiting for You" and "Next 7 Days" have their own panels further down;
  // without fees (staff who can't see money) those two take the place of the fee cards.
  const cards = [
    { tone: 'blue', icon: 'students', label: 'Active Students', value: s.students.value, trend: s.students.now ? { text: `+${s.students.now}`, dir: 'up' } : null,
      caption: s.students.left === null ? 'joined this month' : `joined this month, ${s.students.left} left`, bars: s.students.bars, menu: [{ label: 'Open students', icon: 'view', href: 'students' }] },
    d.money ? { tone: 'green', icon: 'payments', label: 'Fees Collected', value: lkr(s.collected.value), trend: null,
      caption: s.collected.expected ? `${pct(s.collected.value, s.collected.expected)}% of ${lkr(s.collected.expected)} this month` : 'no fees due this month', bars: s.collected.bars, menu: [{ label: 'Open payments', icon: 'view', href: 'payments' }] }
      : { tone: 'green', icon: 'bell', label: 'Waiting for You', value: d.waiting.total, caption: d.waiting.total ? d.waiting.items.map((w) => `${w.count} ${w.short}`).join(' · ') : 'all caught up',
        menu: d.waiting.items.length ? d.waiting.items.map((w) => ({ label: w.label, icon: w.icon, href: w.href })) : null },
    d.money ? { tone: 'orange', icon: 'danger', label: 'Unpaid Fees', value: lkr(s.unpaid.value), trend: s.unpaid.students ? { text: plural(s.unpaid.students, 'student'), dir: 'down' } : null,
      caption: s.unpaid.students ? `${s.unpaid.overdue} owe 2 months or more` : 'everyone has paid', bars: s.unpaid.bars, menu: [{ label: 'Open payments', icon: 'view', href: 'payments' }] }
      : { tone: 'orange', icon: 'timetable', label: 'Classes This Week', value: s.week.value, trend: s.week.value ? { text: `${s.week.hours} hours`, dir: 'flat' } : null,
        caption: s.week.value ? `${s.week.online} online, ${s.week.physical} physical` : 'nothing on the time table', bars: s.week.bars, menu: [{ label: 'Open time table', icon: 'view', href: 'time-table' }] },
    { tone: 'purple', icon: 'attendance', label: 'Attendance', value: perc(s.attendance.value), trend: s.attendance.value !== null && s.attendance.before !== null ? StatCard.trend(s.attendance.value, s.attendance.before) : null,
      caption: s.attendance.value === null ? 'no finished classes in the last 4 weeks' : 'last 4 weeks, watched recordings count', bars: s.attendance.bars, menu: [{ label: 'Open attendance', icon: 'view', href: 'attendance' }] },
  ];
  return StatCard.row(cards, { cols: 'col-12 col-md-6 col-xl-3' });
}

function charts(d) {
  const c = d.charts;
  const noFees = !sum(c.fees?.due) && !sum(c.fees?.collected);
  const noMonth = !sum(c.month?.map((r) => r.value));
  const noAtt = !sum(c.attendance.present) && !sum(c.attendance.watched) && !sum(c.attendance.absent);
  const rows = [];
  if (d.money) {
    rows.push(h('div', { class: 'row g-4 mb-4' },
      h('div', { class: 'col-12 col-xl-8' }, panel('Fee Collection', 'Class fees paid each month (approved payments) against the fees that were due that month.', chartBox('tdFees', false, noFees && { icon: 'payments', title: 'No fees yet', text: 'Monthly class fees appear here once students are in a paid class.' }), { link: ['Payments', 'payments'] })),
      h('div', { class: 'col-12 col-xl-4' }, panel(`${c.monthName} Fees`, 'Where this month\'s class fees stand. Free cards are fees you gave free (shown at the normal fee).',
        noMonth ? chartBox('tdMonth', false, { icon: 'payments', title: 'No fees this month', text: 'This month\'s fees appear here.' }) : [chartBox('tdMonth', true), legend(c.month, (v) => lkr(v))]))));
  }
  rows.push(h('div', { class: 'row g-4 mb-4' },
    h('div', { class: 'col-12 col-md-6 col-xl-4' }, panel('Attendance by Week', 'Students at your classes each week. Watched = was absent but watched the recording.', chartBox('tdAtt', false, noAtt && { icon: 'attendance', title: 'No attendance yet', text: 'Finished classes of the last 6 weeks appear here.' }))),
    h('div', { class: 'col-12 col-md-6 col-xl-4' }, panel('Students by Class', 'Active students in each of your active classes.', chartBox('tdClasses', false, !c.classes.length && { icon: 'classes', title: 'No classes yet', text: 'Create a class and add students.' }))),
    h('div', { class: 'col-12 col-xl-4' }, panel(c.growth.left ? 'Joined and Left' : 'Students Joined', c.growth.left ? 'Students who joined or left your classes each month.' : 'Students who joined your classes each month.', chartBox('tdGrowth', false, !sum(c.growth.joined) && !sum(c.growth.left) && { icon: 'students', title: 'No new students yet', text: 'Students who join your classes appear here.' })))));
  return rows;
}

function drawCharts(d) {
  const c = d.charts;
  if (d.money) draw('tdFees', { type: 'bar', data: { labels: c.fees.labels, datasets: [
    { type: 'line', label: 'Due', data: c.fees.due, borderColor: HEX.orange, backgroundColor: HEX.orange, borderWidth: 2, pointRadius: 3, tension: 0.3 },
    { label: 'Collected', data: c.fees.collected, backgroundColor: HEX.primary, borderRadius: 0, maxBarThickness: 26 },
  ] }, options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, plugins: { legend: legendTop, tooltip: { callbacks: { label: (x) => `${x.dataset.label}: ${lkr(x.raw)}` } } }, scales: axes(false, true) } });
  if (d.money) draw('tdMonth', { type: 'doughnut', data: { labels: c.month.map((r) => r.label), datasets: [{ data: c.month.map((r) => r.value), backgroundColor: c.month.map((r) => HEX[r.color]), borderWidth: 0 }] }, options: { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: { display: false } } } });
  draw('tdAtt', { type: 'bar', data: { labels: c.attendance.labels, datasets: [
    { label: 'Present', data: c.attendance.present, backgroundColor: HEX.success, maxBarThickness: 22 },
    { label: 'Watched', data: c.attendance.watched, backgroundColor: HEX.purple, maxBarThickness: 22 },
    { label: 'Absent', data: c.attendance.absent, backgroundColor: HEX.grey, maxBarThickness: 22 },
  ] }, options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, plugins: { legend: legendTop }, scales: axes(true) } });
  draw('tdClasses', { type: 'bar', data: { labels: c.classes.map((x) => x.label), datasets: [{ data: c.classes.map((x) => x.value), backgroundColor: HEX.primary, maxBarThickness: 22 }] }, options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { color: T().tick, precision: 0 }, grid: { color: T().grid } }, y: { ticks: { color: T().tick, callback(v) { const l = String(this.getLabelForValue(v)); return l.length > 26 ? `${l.slice(0, 25)}…` : l; } }, grid: { color: 'transparent' } } } } });
  draw('tdGrowth', { type: 'bar', data: { labels: c.growth.labels, datasets: [
    { label: 'Joined', data: c.growth.joined, backgroundColor: HEX.primary, maxBarThickness: 16 },
    ...(c.growth.left ? [{ label: 'Left', data: c.growth.left.map((v) => -v), backgroundColor: HEX.danger, maxBarThickness: 16 }] : []),
  ] }, options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, plugins: { legend: legendTop, tooltip: { callbacks: { label: (x) => `${x.dataset.label}: ${Math.abs(x.raw)}` } } }, scales: { x: { stacked: true, ticks: { color: T().tick }, grid: { color: 'transparent' } }, y: { stacked: true, ticks: { color: T().tick, precision: 0, callback: (v) => Math.abs(v) }, grid: { color: T().grid } } } } });
}

const tableCard = (head, rows, emptyArgs) => (rows.length
  ? h('div', { class: 'table-responsive-wrapper mt-3' }, h('div', { class: 'table-responsive' }, h('table', { class: 'table bl-solid m-0 ex-v3-table ex-fit' },
    h('thead', {}, h('tr', { class: 'br-t-5' }, head.map((t, i) => h('th', { scope: 'col', class: `${i ? '' : 'px-4 '}py-3 bg-table-header text-14 main-text fw-medium` }, t)))),
    h('tbody', {}, rows))))
  : h('div', { class: 'mt-3' }, EmptyState.empty(emptyArgs)));
const two = (main, sub, first = false) => h('td', { class: `${first ? 'px-4 ' : ''}py-2 align-middle cell-wrap` }, h('div', { class: 'text-14 main-text fw-medium' }, main), sub ? h('div', { class: 'text-12 sub-text' }, sub) : null);

function lists(d) {
  const MODE = { Online: 'table-btn-primary', Physical: 'table-btn-success', Both: 'table-btn-purple' };
  const classes = tableCard(['Class', 'When', 'Mode', 'Students', 'Action'], d.upcoming.map((c) => h('tr', {},
    two(c.name, c.subtitle, true),
    two(c.day, c.time),
    h('td', { class: 'py-2 align-middle' }, h('span', { class: `table-btn ${MODE[c.mode] ?? 'table-btn-primary'} px-3` }, c.mode)),
    h('td', { class: 'py-2 align-middle text-14 main-text' }, String(c.students)),
    h('td', { class: 'py-2 pe-3 align-middle text-nowrap' }, c.isToday
      ? link(c.href, c.external, { class: 'custom-btn table-btn px-3 text-decoration-none' }, c.mode === 'Online' ? 'Start Class' : 'Mark Attendance')
      : link(c.href, false, { class: 'custom-btn-outline ex-btn-sm px-3 text-decoration-none' }, 'View')))),
  { icon: 'calendar', title: 'No classes this week', text: 'Classes from your time table appear here.' });

  const waiting = d.waiting.items.length
    ? h('div', { class: 'mt-2' }, d.waiting.items.map((w) => h('a', { class: 'td-todo text-decoration-none', href: w.href },
      h('span', { class: `td-todo-icon ${w.tone}` }, icon(w.icon, { size: 20 })),
      h('span', { class: 'flex-grow-1' }, h('span', { class: 'd-block text-14 main-text fw-medium' }, w.label), h('span', { class: 'd-block text-12 sub-text' }, w.help)),
      h('span', { class: 'td-todo-count' }, String(w.count)),
      icon('chevron-right', { size: 18, className: 'sub-text' }))))
    : h('div', { class: 'mt-3' }, EmptyState.empty({ icon: 'check', title: 'All caught up', text: 'Slips to approve and new applications appear here.' }));

  const REASON = { absent: 'table-btn-danger', unpaid: 'table-btn-orange', watch: 'table-btn-purple' };
  const follow = tableCard(['Student', 'Class', 'Why', ''], d.followUp.map((s) => h('tr', {},
    two(s.name, s.phone, true),
    two(s.className, null),
    h('td', { class: 'py-2 align-middle cell-wrap' }, h('div', { class: 'd-flex flex-wrap gap-1' }, s.reasons.map((r) => h('span', { class: `table-btn ${REASON[r.type]} td-reason` }, r.text)))),
    h('td', { class: 'py-2 pe-3 align-middle text-nowrap' }, s.whatsapp ? h('a', { class: 'custom-btn-outline ex-btn-sm px-3 d-inline-flex align-items-center gap-1 text-decoration-none', href: `https://wa.me/${encodeURIComponent(s.whatsapp)}`, target: '_blank', rel: 'noopener' }, icon('whatsapp', { size: 16 }), 'WhatsApp') : null))),
  { icon: 'user-check', title: 'Everyone is on track', text: 'Students who miss classes or fees appear here.' });

  const STATUS = { Approved: 'table-btn-success', Waiting: 'table-btn-orange', Rejected: 'table-btn-danger' };
  const pays = tableCard(['Student', 'Amount', 'Status'], d.payments.map((p) => h('tr', {},
    two(p.name, p.when, true),
    two(lkr(p.amount), `${p.className} · ${p.month}`),
    h('td', { class: 'py-2 pe-3 align-middle' }, h('span', { class: `table-btn ${STATUS[p.status]} px-3` }, p.status)))),
  { icon: 'payments', title: 'No payments yet', text: 'Fees students pay appear here.' });

  return [
    h('div', { class: 'row g-4 mb-4' },
      h('div', { class: 'col-12 col-xl-8' }, panel('Next 7 Days', 'Your classes from today for the next 7 days. On the day, Start Class opens the Zoom meeting of an online class and Mark Attendance opens the attendance of a physical class.', classes, { link: ['Time table', 'time-table'] })),
      h('div', { class: 'col-12 col-xl-4' }, panel('Waiting for You', 'Things only you can do: approve payment slips, look at new applications and upload the recordings of online classes.', waiting))),
    h('div', { class: 'row g-4 mb-4' },
      h('div', { class: `col-12 ${d.money ? 'col-xl-7' : ''}` }, panel('Students to Follow Up', `Students who missed the last 3 classes${d.money ? ', owe 2 months of fees' : ''} or missed a recorded class in the last 14 days and have not watched the recording.`, follow, { link: ['All students', 'students'] })),
      d.money ? h('div', { class: 'col-12 col-xl-5' }, panel('Recent Payments', 'The latest fees students paid or uploaded a slip for.', pays, { link: ['Payments', 'payments'] })) : null),
  ];
}

function performance(d) {
  const local = {
    get: async (_ep, p) => {
      const q = (p.q ?? '').toLowerCase();
      const key = (c) => (c[p.sort] === null ? -1 : c[p.sort]);
      let rows = d.classes.filter((c) => (!q || c.name.toLowerCase().includes(q)) && (!p.year || String(c.year) === p.year) && (!p.mode || c.mode === p.mode));
      if (p.sort) rows = [...rows].sort((a, b) => (key(a) > key(b) ? 1 : key(a) < key(b) ? -1 : 0) * (p.dir === 'desc' ? -1 : 1));
      const per = Number(p.per_page) || 10;
      return { data: rows.slice((p.page - 1) * per, p.page * per), meta: { total: rows.length, pages: Math.max(1, Math.ceil(rows.length / per)), per_page: per } };
    },
  };
  const bar = (v, tone) => (v === null ? h('span', { class: 'text-14 sub-text' }, '-')
    : h('div', { class: 'd-flex align-items-center gap-2' }, h('div', { class: 'td-bar' }, h('i', { class: tone, style: { width: `${Math.min(100, v)}%` } })), h('span', { class: 'text-14 main-text' }, `${v}%`)));
  const box = h('div', { class: 'col-12 component-theme p-4 mb-4' },
    h('span', { class: 'sub-topic d-inline-flex align-items-center gap-2 mb-3' }, 'Class Performance', infoTip('Every class side by side: students, attendance, fees this month and how many absent students watched the recording.', 'Class Performance')));
  const holder = h('div');
  box.append(holder);
  new DataView({
    api: local, endpoint: '/local/classes', key: 'td-classes', noun: ['class', 'classes'], perPage: 5, perPageOptions: [5, 10, 25],
    urlPrefix: 'cp_', searchPlaceholder: 'Search classes',
    filters: [
      { key: 'year', label: 'Exam Year', options: async () => [...new Set(d.classes.map((c) => c.year))].sort().map((y) => ({ value: String(y), label: String(y) })) },
      { key: 'mode', label: 'Mode', options: async () => ['Online', 'Physical', 'Both'].map((m) => ({ value: m, label: m })) },
    ],
    columns: [
      { key: 'name', label: 'Class', sortable: true, render: (c) => h('div', {}, h('div', { class: 'text-14 main-text fw-medium' }, c.name), h('div', { class: 'text-12 sub-text' }, c.subtitle)) },
      { key: 'students', label: 'Students', sortable: true, render: (c) => h('div', {}, h('div', { class: 'text-14 main-text' }, String(c.students)), h('div', { class: 'text-12 sub-text' }, c.newStudents ? `+${c.newStudents} this month` : 'none new this month')) },
      { key: 'attendance', label: 'Attendance', sortable: true, render: (c) => bar(c.attendance, c.attendance !== null && c.attendance < 70 ? 'warn' : '') },
      d.money && { key: 'paidPct', label: 'Fees This Month', sortable: true, render: (c) => (c.due ? h('div', {}, bar(c.paidPct, c.paidPct < 60 ? 'warn' : 'ok'), h('div', { class: 'text-12 sub-text mt-1' }, `${lkr(c.paid)} of ${lkr(c.due)}`)) : h('span', { class: 'text-14 sub-text' }, 'No fees due')) },
      d.money && { key: 'unpaid', label: 'Unpaid', sortable: true, render: (c) => h('div', {}, h('div', { class: 'text-14 main-text' }, lkr(c.unpaid)), h('div', { class: 'text-12 sub-text' }, plural(c.unpaidStudents, 'student'))) },
      { key: 'watched', label: 'Recordings Watched', sortable: true, render: (c) => (c.watched === null ? h('span', { class: 'text-14 sub-text' }, '-') : h('div', {}, h('div', { class: 'text-14 main-text' }, `${c.watched}%`), h('div', { class: 'text-12 sub-text' }, 'of absent students'))) },
      { key: 'actions', label: '', render: (c) => ActionMenu.kebab([
        { label: 'Open class', icon: 'view', href: `class-details?class-id=${c.id}` },
        { label: 'Students', icon: 'students', href: `class-students?class-id=${c.id}` },
        { label: 'Payments', icon: 'payments', href: `class-payments?class-id=${c.id}` },
        { label: 'Attendance', icon: 'attendance', href: `class-attendance?class-id=${c.id}` },
      ], `Actions for ${c.name}`) },
    ].filter(Boolean),
    exportCsv: { filename: 'class-performance.csv', columns: [['Class', (c) => c.name], ['Exam year', (c) => c.year], ['Mode', (c) => c.mode], ['Students', (c) => c.students], ['Attendance %', (c) => c.attendance ?? ''],
      ...(d.money ? [['Fees paid this month %', (c) => c.paidPct ?? ''], ['Paid this month (LKR)', (c) => c.paid], ['Due this month (LKR)', (c) => c.due], ['Unpaid (LKR)', (c) => c.unpaid], ['Students with unpaid fees', (c) => c.unpaidStudents]] : []),
      ['Recordings watched %', (c) => c.watched ?? '']] },
    empty: { icon: 'classes', title: 'No classes yet', text: 'Create a class to see how it is doing here.' },
  }).mount(holder);
  return box;
}

async function load() {
  fill(root, DashboardKit.skeleton());
  try {
    const d = (await api.get('/tutor/dashboard')).data;
    fill(root, today(d), stats(d), ...charts(d), ...lists(d), performance(d));
    drawCharts(d);
  } catch (e) {
    fill(root, h('div', { class: 'component-theme p-4' }, EmptyState.error(e.message, load)));
  }
}
load();
