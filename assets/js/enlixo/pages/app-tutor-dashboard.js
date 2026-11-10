// Tutor → Dashboard (tutor/dashboard.php). Same order and look as the ENLIXO V3 dashboards:
// header card → today strip → stat cards → chart panels → lists → class performance table card.
// Data: GET /api/v1/tutor/dashboard (one request). Charts are Chart.js, colours from the V3 palette.
import { h, fill, fmt } from '../core/dom.js';
import { ApiClient } from '../core/ApiClient.js';
import { icon } from '../core/icons.js';
import { StatCard } from '../components/StatCard.js';
import { ActionMenu } from '../components/Popover.js';
import { QuickGuide } from '../components/QuickGuide.js';
import { EmptyState } from '../components/EmptyState.js';
import { DataView } from '../components/DataView.js';
import { infoTip } from '../components/InfoTip.js';

const api = new ApiClient();
const root = document.getElementById('tutor-dashboard');
const HEX = { primary: '#0061D2', success: '#48CD5D', orange: '#FF9500', danger: '#D85E44', purple: '#AF52DE', grey: '#C3C8D1', light: '#B9CFF2' };
const dark = () => document.documentElement.getAttribute('data-custom-theme') === 'dark';
const T = () => ({ tick: dark() ? '#A9B4C6' : '#6E7485', grid: dark() ? 'rgba(169,180,198,.12)' : 'rgba(110,116,133,.12)' });
const lkr = (n) => fmt.money(Math.round(n));
const short = (n) => (n >= 1e6 ? `${(n / 1e6).toFixed(1)}M` : n >= 1e3 ? `${Math.round(n / 1e3)}K` : String(n));
const pct = (a, b) => (b ? Math.round((a / b) * 100) : 0);

QuickGuide.attachAll();
document.getElementById('dashboard-actions').append(ActionMenu.button('Action', [
  { label: 'Create a Class', icon: 'add', href: 'create-class' },
  { label: 'Add New Student', icon: 'user-add', href: 'add-student' },
  { label: 'Mark Attendance', icon: 'attendance', href: 'attendance' },
  { label: 'Send a Message', icon: 'send', href: 'message-center' },
]));

// ---------- small building blocks (V3 dashboard panels) ----------
const panel = (title, info, body, { link = null, cls = '' } = {}) => h('div', { class: `component-theme p-4 h-100 d-flex flex-column ${cls}` },
  h('div', { class: 'd-flex justify-content-between align-items-center gap-2' },
    h('span', { class: 'sub-topic d-inline-flex align-items-center gap-2' }, title, infoTip(info, title)),
    link ? h('a', { class: 'text-14 dark-theme text-decoration-none', href: link[1] }, link[0]) : null),
  body);
const chartBox = (id, round = false) => h('div', { class: `td-chart-box mt-3 ${round ? 'td-chart-round' : 'flex-grow-1'}` }, h('canvas', { id }));
const chip = (text, tone = '') => h('span', { class: `td-chip ${tone}` }, text);
const legend = (rows, fmtVal = (v) => v) => {
  const total = rows.reduce((a, r) => a + r.value, 0);
  return h('div', { class: 'td-legend mt-3' }, rows.map((r) => h('div', { class: 'td-legend-row' },
    h('span', { class: 'td-legend-dot', style: { background: HEX[r.color] } }),
    h('span', { class: 'text-14 main-text flex-grow-1' }, r.label),
    h('span', { class: 'text-14 main-text fw-medium' }, fmtVal(r.value)),
    h('span', { class: 'text-12 sub-text td-legend-pct' }, `${pct(r.value, total)}%`))));
};
const draw = (id, cfg) => { const el = document.getElementById(id); if (el && window.Chart) new window.Chart(el, cfg); };
const axes = (stacked = false, money = false) => ({
  x: { stacked, ticks: { color: T().tick }, grid: { color: 'transparent' } },
  y: { stacked, beginAtZero: true, ticks: { color: T().tick, precision: 0, callback: money ? (v) => short(v) : undefined }, grid: { color: T().grid } },
});
const legendTop = { position: 'top', align: 'end', labels: { color: T().tick, boxWidth: 12, boxHeight: 12 } };

// ---------- sections ----------
function today(d) {
  const next = d.today.find((c) => !c.done);
  const hello = `${new Date().getHours() < 12 ? 'Good morning' : new Date().getHours() < 17 ? 'Good afternoon' : 'Good evening'}${root.dataset.name ? `, ${root.dataset.name}` : ''}.`;
  return h('div', { class: 'col-12 component-theme p-4 mb-4' },
    h('div', { class: 'd-flex flex-wrap gap-3 align-items-center p-3 bg-light-theme td-today' },
      h('span', { class: 'td-today-icon' }, icon('calendar', { size: 22 })),
      h('div', { class: 'flex-grow-1 text-14 main-text' },
        h('div', { class: 'fw-semibold text-dark-color' }, hello),
        d.today.length
          ? h('div', {}, 'You have ', h('b', {}, `${d.today.length} class${d.today.length === 1 ? '' : 'es'} today`),
            next ? [`. Next: `, h('b', {}, next.name), `, ${next.time} (${next.mode})`] : '. All done for today.',
            d.waiting.total ? [' · ', h('b', {}, `${d.waiting.total} things`), ' waiting for you.'] : '')
          : h('div', {}, 'No classes today.'),
        d.exams.length ? h('div', { class: 'd-flex flex-wrap gap-2 mt-2' }, d.exams.map((x) => h('span', { class: 'td-countdown', title: `${x.name} starts ${fmt.date(x.date)}` }, icon('hourglass', { size: 16 }), h('b', {}, `${x.days} days`), ` to the ${x.name} exam`))) : null),
      next ? h('a', { class: 'custom-btn px-4 d-inline-flex align-items-center gap-2 text-decoration-none', href: next.href }, icon(next.mode === 'Online' ? 'online' : 'attendance', { size: 18 }), next.mode === 'Online' ? 'Start Class' : 'Mark Attendance') : null));
}

function stats(d) {
  const s = d.stats;
  return StatCard.row([
    { icon: 'students', label: 'Active Students', value: s.students.value, trend: { text: `+${s.students.now}`, dir: 'up' }, caption: `joined this month, ${s.students.left} left`, bars: s.students.bars, menu: [{ label: 'Open students', icon: 'view', href: 'students' }] },
    { icon: 'payments', label: 'Fees Collected', value: lkr(s.collected.value), trend: StatCard.trend(s.collected.value, s.collected.before), caption: `${pct(s.collected.value, s.collected.expected)}% of ${lkr(s.collected.expected)} this month`, bars: s.collected.bars, menu: [{ label: 'Open payments', icon: 'view', href: 'payments' }] },
    { icon: 'danger', label: 'Unpaid Fees', value: lkr(s.unpaid.value), trend: { text: `${s.unpaid.students} students`, dir: 'down' }, caption: `${s.unpaid.overdue} owe 2 months or more`, bars: s.unpaid.bars, menu: [{ label: 'Show unpaid students', icon: 'filter', href: 'payments?status=unpaid' }] },
    { icon: 'attendance', label: 'Attendance', value: `${s.attendance.value}%`, trend: StatCard.trend(s.attendance.value, s.attendance.before), caption: 'last 4 weeks, watched recordings count', bars: s.attendance.bars, menu: [{ label: 'Open attendance', icon: 'view', href: 'attendance' }] },
    { icon: 'bell', label: 'Waiting for You', value: d.waiting.total, caption: d.waiting.items.map((w) => `${w.count} ${w.short}`).join(' · '), menu: d.waiting.items.map((w) => ({ label: w.label, icon: w.icon, href: w.href })) },
    { icon: 'timetable', label: 'Classes This Week', value: s.week.value, trend: { text: `${s.week.hours} hours`, dir: 'flat' }, caption: s.week.caption, bars: s.week.bars, menu: [{ label: 'Open time table', icon: 'view', href: 'time-table' }] },
  ], { cols: 'col-12 col-md-6 col-xl-4' });
}

function charts(d) {
  const c = d.charts;
  return [
    h('div', { class: 'row g-4 mb-4' },
      h('div', { class: 'col-12 col-xl-8' }, panel('Fee Collection', 'Class fees collected each month (approved payments) against the fees that were due that month.', chartBox('tdFees'), { link: ['Payments', 'payments'] })),
      h('div', { class: 'col-12 col-xl-4' }, panel(`${c.monthName} Fees`, 'Where this month\'s class fees stand, per student fee.', [chartBox('tdMonth', true), legend(c.month, (v) => lkr(v))]))),
    h('div', { class: 'row g-4 mb-4' },
      h('div', { class: 'col-12 col-md-6 col-xl-4' }, panel('Attendance by Week', 'Students at your classes each week. Watched = was absent but watched the recording.', chartBox('tdAtt'))),
      h('div', { class: 'col-12 col-md-6 col-xl-4' }, panel('Students by Class', 'Active students in each class.', chartBox('tdClasses'))),
      h('div', { class: 'col-12 col-xl-4' }, panel('Joined and Left', 'Students who joined or left your classes each month.', chartBox('tdGrowth')))),
  ];
}

function drawCharts(d) {
  const c = d.charts;
  draw('tdFees', { type: 'bar', data: { labels: c.fees.labels, datasets: [
    { type: 'line', label: 'Due', data: c.fees.due, borderColor: HEX.orange, backgroundColor: HEX.orange, borderWidth: 2, pointRadius: 3, tension: 0.3 },
    { label: 'Collected', data: c.fees.collected, backgroundColor: HEX.primary, borderRadius: 0, maxBarThickness: 26 },
  ] }, options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, plugins: { legend: legendTop, tooltip: { callbacks: { label: (x) => `${x.dataset.label}: ${lkr(x.raw)}` } } }, scales: axes(false, true) } });
  draw('tdMonth', { type: 'doughnut', data: { labels: c.month.map((r) => r.label), datasets: [{ data: c.month.map((r) => r.value), backgroundColor: c.month.map((r) => HEX[r.color]), borderWidth: 0 }] }, options: { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: { display: false } } } });
  draw('tdAtt', { type: 'bar', data: { labels: c.attendance.labels, datasets: [
    { label: 'Present', data: c.attendance.present, backgroundColor: HEX.success, maxBarThickness: 22 },
    { label: 'Watched', data: c.attendance.watched, backgroundColor: HEX.purple, maxBarThickness: 22 },
    { label: 'Absent', data: c.attendance.absent, backgroundColor: HEX.grey, maxBarThickness: 22 },
  ] }, options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, plugins: { legend: legendTop }, scales: axes(true) } });
  draw('tdClasses', { type: 'bar', data: { labels: c.classes.map((x) => x.label), datasets: [{ data: c.classes.map((x) => x.value), backgroundColor: HEX.primary, maxBarThickness: 22 }] }, options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { color: T().tick, precision: 0 }, grid: { color: T().grid } }, y: { ticks: { color: T().tick, callback(v) { const l = String(this.getLabelForValue(v)); return l.length > 26 ? `${l.slice(0, 25)}…` : l; } }, grid: { color: 'transparent' } } } } });
  draw('tdGrowth', { type: 'bar', data: { labels: c.growth.labels, datasets: [
    { label: 'Joined', data: c.growth.joined, backgroundColor: HEX.primary, maxBarThickness: 16 },
    { label: 'Left', data: c.growth.left.map((v) => -v), backgroundColor: HEX.danger, maxBarThickness: 16 },
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
      ? h('a', { class: 'custom-btn table-btn px-3 text-decoration-none', href: c.href }, c.mode === 'Online' ? 'Start Class' : 'Mark Attendance')
      : h('a', { class: 'custom-btn-outline ex-btn-sm px-3 text-decoration-none', href: c.href }, 'View')))),
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
    h('td', { class: 'py-2 align-middle cell-wrap' }, h('div', { class: 'd-flex flex-wrap gap-1' }, s.reasons.map((r) => h('span', { class: `table-btn ${REASON[r.type]} px-2` }, r.text)))),
    h('td', { class: 'py-2 pe-3 align-middle text-nowrap' }, h('a', { class: 'custom-btn-outline ex-btn-sm px-3 d-inline-flex align-items-center gap-1 text-decoration-none', href: `https://wa.me/${s.whatsapp}`, target: '_blank', rel: 'noopener' }, icon('whatsapp', { size: 16 }), 'WhatsApp')))),
  { icon: 'user-check', title: 'Everyone is on track', text: 'Students who miss classes or fees appear here.' });

  const STATUS = { Approved: 'table-btn-success', Waiting: 'table-btn-orange', Rejected: 'table-btn-danger' };
  const pays = tableCard(['Student', 'Amount', 'Status'], d.payments.map((p) => h('tr', {},
    two(p.name, p.when, true),
    two(lkr(p.amount), `${p.className} · ${p.month}`),
    h('td', { class: 'py-2 pe-3 align-middle' }, h('span', { class: `table-btn ${STATUS[p.status]} px-3` }, p.status)))),
  { icon: 'payments', title: 'No payments yet', text: 'Fees students pay appear here.' });

  return [
    h('div', { class: 'row g-4 mb-4' },
      h('div', { class: 'col-12 col-xl-8' }, panel('This Week\'s Classes', 'Your classes from today to the end of the week. Online classes open their Zoom meeting from Start Class.', classes, { link: ['Time table', 'time-table'] })),
      h('div', { class: 'col-12 col-xl-4' }, panel('Waiting for You', 'Things only you can do: approve payment slips, look at new applications and answer requests.', waiting))),
    h('div', { class: 'row g-4 mb-4' },
      h('div', { class: 'col-12 col-xl-7' }, panel('Students to Follow Up', 'Students who missed the last 3 classes, owe 2 months of fees, or have not watched the recording of a class they missed.', follow, { link: ['All students', 'students'] })),
      h('div', { class: 'col-12 col-xl-5' }, panel('Recent Payments', 'The latest fees students paid or uploaded a slip for.', pays, { link: ['Payments', 'payments'] }))),
  ];
}

function performance(d) {
  const local = {
    get: async (_ep, p) => {
      const q = (p.q ?? '').toLowerCase();
      let rows = d.classes.filter((c) => (!q || c.name.toLowerCase().includes(q)) && (!p.year || String(c.year) === p.year) && (!p.mode || c.mode === p.mode));
      if (p.sort) rows = [...rows].sort((a, b) => (a[p.sort] > b[p.sort] ? 1 : -1) * (p.dir === 'desc' ? -1 : 1));
      const per = Number(p.per_page) || 10;
      return { data: rows.slice((p.page - 1) * per, p.page * per), meta: { total: rows.length, pages: Math.max(1, Math.ceil(rows.length / per)), per_page: per } };
    },
  };
  const bar = (v, tone) => h('div', { class: 'd-flex align-items-center gap-2' }, h('div', { class: 'td-bar' }, h('i', { class: tone, style: { width: `${v}%` } })), h('span', { class: 'text-14 main-text' }, `${v}%`));
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
      { key: 'students', label: 'Students', sortable: true, render: (c) => h('div', {}, h('div', { class: 'text-14 main-text' }, String(c.students)), h('div', { class: 'text-12 sub-text' }, c.newStudents ? `+${c.newStudents} this month` : 'no new students')) },
      { key: 'attendance', label: 'Attendance', sortable: true, render: (c) => bar(c.attendance, c.attendance < 70 ? 'warn' : '') },
      { key: 'paidPct', label: 'Fees This Month', sortable: true, render: (c) => h('div', {}, bar(c.paidPct, c.paidPct < 60 ? 'warn' : 'ok'), h('div', { class: 'text-12 sub-text mt-1' }, `${lkr(c.paid)} of ${lkr(c.due)}`)) },
      { key: 'unpaid', label: 'Unpaid', sortable: true, render: (c) => h('div', {}, h('div', { class: 'text-14 main-text' }, lkr(c.unpaid)), h('div', { class: 'text-12 sub-text' }, `${c.unpaidStudents} students`)) },
      { key: 'watched', label: 'Recordings Watched', sortable: true, render: (c) => h('div', {}, h('div', { class: 'text-14 main-text' }, `${c.watched}%`), h('div', { class: 'text-12 sub-text' }, 'of absent students')) },
      { key: 'actions', label: '', render: (c) => ActionMenu.kebab([
        { label: 'Open class', icon: 'view', href: `class-details?class-id=${c.id}` },
        { label: 'Students', icon: 'students', href: `class-students?class-id=${c.id}` },
        { label: 'Payments', icon: 'payments', href: `class-payments?class-id=${c.id}` },
        { label: 'Attendance', icon: 'attendance', href: `class-attendance?class-id=${c.id}` },
      ], `Actions for ${c.name}`) },
    ],
    exportCsv: { filename: 'class-performance.csv', columns: [['Class', (c) => c.name], ['Students', (c) => c.students], ['Attendance %', (c) => c.attendance], ['Fees paid %', (c) => c.paidPct], ['Paid', (c) => c.paid], ['Due', (c) => c.due], ['Unpaid', (c) => c.unpaid], ['Recordings watched %', (c) => c.watched]] },
    empty: { icon: 'classes', title: 'No classes yet', text: 'Create a class to see how it is doing here.' },
  }).mount(holder);
  return box;
}

async function load() {
  fill(root, h('div', { class: 'row g-3 mb-4 placeholder-glow' }, [0, 1, 2, 3, 4, 5].map(() => h('div', { class: 'col-12 col-md-6 col-xl-4' }, h('span', { class: 'placeholder col-12', style: { height: '118px' } })))));
  try {
    const d = (await api.get('/tutor/dashboard')).data;
    fill(root, today(d), stats(d), ...charts(d), ...lists(d), performance(d));
    drawCharts(d);
  } catch (e) {
    fill(root, h('div', { class: 'component-theme p-4' }, EmptyState.error(e.message, load)));
  }
}
load();
