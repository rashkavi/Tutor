// Admin → Dashboard (admin/dashboard.php). V3 dashboard order: header card → stat cards → chart panels → lists.
// Data: GET /admin/dashboard (one request).
import { StatCard } from '../components/StatCard.js';
import { DashboardKit } from '../components/DashboardKit.js';
import { QuickGuide } from '../components/QuickGuide.js';
import { api, h, fill, icon, lkr, short, day, inDays, plural, panel, chip, cell, person, meter, simpleTable, previewNote, paymentDialog, EmptyState } from './admin-common.js';

const root = document.getElementById('admin-dashboard');
const HEX = { primary: '#0061D2', success: '#48CD5D', orange: '#FF9500', danger: '#D85E44', purple: '#AF52DE', light: '#B9CFF2' };
const dark = () => document.documentElement.getAttribute('data-custom-theme') === 'dark';
const T = () => ({ tick: dark() ? '#A9B4C6' : '#6E7485', grid: dark() ? 'rgba(169,180,198,.12)' : 'rgba(110,116,133,.12)' });
const pct = (a, b) => (b ? Math.round((a / b) * 100) : 0);

QuickGuide.attachAll();
const ACTIONS = [
  { label: 'Add Tutor', icon: 'user-add', href: 'add-tutor' },
  { label: 'Check Payment Slips', icon: 'receipt', href: 'tutor-payments?status=waiting' },
  { label: 'Invoices', icon: 'invoice', href: 'invoices' },
  { label: 'Plans & Prices', icon: 'plans', href: 'plans' },
];

const chartBox = (id, round = false) => h('div', { class: `ad-chart-box ${round ? 'ad-chart-round' : 'flex-grow-1'}` }, h('canvas', { id }));
const draw = (id, cfg) => { const el = document.getElementById(id); if (el && window.Chart) new window.Chart(el, cfg); };
const axes = (money = false) => ({
  x: { ticks: { color: T().tick }, grid: { color: 'transparent' } },
  y: { beginAtZero: true, ticks: { color: T().tick, precision: 0, callback: money ? (v) => short(v) : undefined }, grid: { color: T().grid } },
});
const legendTop = { position: 'top', align: 'end', labels: { color: T().tick, boxWidth: 12, boxHeight: 12 } };
const legend = (rows, colors) => {
  const total = rows.reduce((a, r) => a + r.value, 0);
  return h('div', { class: 'ad-legend mt-3' }, rows.map((r, i) => h('div', { class: 'ad-legend-row' },
    h('span', { class: 'ad-legend-dot', style: { background: colors[i] } }),
    h('span', { class: 'text-14 main-text flex-grow-1' }, r.label),
    h('span', { class: 'text-14 main-text fw-medium' }, plural(r.value, 'tutor')),
    h('span', { class: 'text-12 sub-text', style: { width: '40px', textAlign: 'right' } }, `${pct(r.value, total)}%`))));
};

// Header: greeting with what needs the admin today, and the Action menu (no separate header card).
function greeting(d) {
  const s = d.stats;
  const todo = (d.todo ?? []).reduce((n, t) => n + (Number(t.count) || 0), 0);
  const line = todo
    ? [h('b', {}, plural(todo, 'thing')), ' waiting for you', s.slips.value ? [', including ', h('b', {}, plural(s.slips.value, 'payment slip')), ' to check.'] : '.']
    : ['Nothing is waiting for you. ', h('b', {}, plural(s.paying.value, 'paying tutor')), ' and ', h('b', {}, plural(s.trials.value, 'tutor')), ' on a free trial.'];
  return DashboardKit.greeting({ name: root.dataset.name, line, actions: ACTIONS, iconName: 'dashboard' });
}

// Four cards, one colour each. Overdue invoices and slips to check are in "Waiting for You" below.
function stats(d) {
  const s = d.stats;
  return StatCard.row([
    { tone: 'blue', icon: 'tutors', label: 'Paying Tutors', value: s.paying.value, trend: { text: `+${s.paying.new}`, dir: 'up' }, caption: 'joined or converted this month', bars: s.paying.bars, menu: [{ label: 'Open tutors', icon: 'view', href: 'tutors' }] },
    { tone: 'purple', icon: 'hourglass', label: 'On Free Trial', value: s.trials.value, trend: s.trials.ending ? { text: `${s.trials.ending} ending`, dir: 'down' } : null, caption: 'ending in the next 3 days', bars: s.trials.bars, menu: [{ label: 'Show trials', icon: 'filter', href: 'tutors?status=trial' }] },
    { tone: 'green', icon: 'growth', label: 'Monthly Revenue (MRR)', value: lkr(s.mrr.value), trend: StatCard.trend(s.mrr.value, s.mrr.before), caption: 'against last month', bars: s.mrr.bars },
    { tone: 'orange', icon: 'wallet', label: 'Collected This Month', value: lkr(s.collected.value), caption: `${pct(s.collected.value, s.collected.expected)}% of ${lkr(s.collected.expected)} due`, bars: s.collected.bars, menu: [{ label: 'Open payments', icon: 'view', href: 'tutor-payments' }] },
  ], { cols: 'col-12 col-md-6 col-xl-3' });
}

function charts(d) {
  const c = d.charts;
  return [
    h('div', { class: 'row g-4 mb-4' },
      h('div', { class: 'col-12 col-xl-8' }, panel('Revenue', 'Money received each month (approved payments) against what was invoiced for that month.', chartBox('adRevenue'), { link: ['Invoices', 'invoices'] })),
      h('div', { class: 'col-12 col-xl-4' }, panel('Tutors by Plan', 'Paying tutors on each plan (trials and complimentary not counted).', [chartBox('adPlans', true), legend(c.plans, [HEX.primary, HEX.success, HEX.purple])], { link: ['Plans', 'plans'] }))),
    h('div', { class: 'row g-4 mb-4' },
      h('div', { class: 'col-12 col-xl-6' }, panel('Free Trials', 'Trials started each month and how many became paying tutors.', chartBox('adTrials'))),
      h('div', { class: 'col-12 col-xl-6' }, panel('New and Cancelled Tutors', 'Tutors who started paying or cancelled each month.', chartBox('adGrowth')))),
  ];
}

function drawCharts(d) {
  const c = d.charts;
  draw('adRevenue', { type: 'bar', data: { labels: c.revenue.labels, datasets: [
    { label: 'Invoiced', data: c.revenue.invoiced, backgroundColor: HEX.light, borderRadius: 0, maxBarThickness: 22 },
    { label: 'Collected', data: c.revenue.collected, backgroundColor: HEX.primary, borderRadius: 0, maxBarThickness: 22 },
  ] }, options: { maintainAspectRatio: false, plugins: { legend: legendTop, tooltip: { callbacks: { label: (x) => `${x.dataset.label}: ${lkr(x.raw)}` } } }, scales: axes(true) } });
  draw('adPlans', { type: 'doughnut', data: { labels: c.plans.map((p) => p.label), datasets: [{ data: c.plans.map((p) => p.value), backgroundColor: [HEX.primary, HEX.success, HEX.purple], borderWidth: 0 }] }, options: { maintainAspectRatio: false, cutout: '70%', plugins: { legend: { display: false } } } });
  draw('adTrials', { type: 'bar', data: { labels: c.trials.labels, datasets: [
    { label: 'Trials started', data: c.trials.started, backgroundColor: HEX.light, maxBarThickness: 26 },
    { label: 'Became paying', data: c.trials.converted, backgroundColor: HEX.success, maxBarThickness: 26 },
  ] }, options: { maintainAspectRatio: false, plugins: { legend: legendTop }, scales: axes() } });
  draw('adGrowth', { type: 'line', data: { labels: c.growth.labels, datasets: [
    { label: 'New', data: c.growth.joined, borderColor: HEX.primary, backgroundColor: HEX.primary, tension: 0.35, pointRadius: 3 },
    { label: 'Cancelled', data: c.growth.cancelled, borderColor: HEX.danger, backgroundColor: HEX.danger, tension: 0.35, pointRadius: 3 },
  ] }, options: { maintainAspectRatio: false, plugins: { legend: legendTop }, scales: axes() } });
}

function lists(d) {
  const todo = h('div', {}, d.todo.map((t) => h('a', { class: 'ad-todo', href: t.href },
    h('span', { class: `ad-todo-icon ${t.tone}` }, icon(t.icon, { size: 20 })),
    h('span', { class: 'text-14 main-text flex-grow-1' }, t.label),
    h('span', { class: 'ad-todo-count' }, String(t.count)), icon('chevron-right', { size: 16 }))));
  const trials = d.trials_list.length
    ? h('div', {}, d.trials_list.map((t) => h('a', { class: 'ad-todo', href: `tutor-details?tutor-id=${t.id}` },
      person(t.name, t.brand), h('span', { class: 'flex-grow-1' }), cell(`Ends ${day(t.trial_ends)}`, inDays(t.trial_ends)))))
    : EmptyState.empty({ title: 'No trials running', text: 'Tutors you add with a free trial appear here.' });
  return h('div', { class: 'row g-4 mb-4' },
    h('div', { class: 'col-12 col-xl-4' }, panel('Waiting for You', 'Things only you can move forward. Each line opens the list behind it.', todo)),
    h('div', { class: 'col-12 col-xl-4' }, panel('Trials Ending Soon', 'Call or message these tutors before their trial ends, so they choose a plan.', trials, { link: ['All trials', 'tutors?status=trial'] })),
    h('div', { class: 'col-12 col-xl-4' }, panel('Near Their Student Limit', 'Tutors using 85% or more of their students. Offer extra students or a bigger plan.',
      h('div', {}, d.near_limit.map((t) => h('a', { class: 'ad-todo', href: `tutor-details?tutor-id=${t.id}` }, person(t.name, `${t.plan_name} plan`), h('span', { class: 'flex-grow-1' }), meter(t.students, t.limit)))))));
}

function recent(d) {
  return h('div', { class: 'col-12 component-theme p-4 mb-4' },
    h('div', { class: 'd-flex justify-content-between align-items-center mb-3' },
      h('span', { class: 'sub-topic' }, 'Latest Payments'), h('a', { class: 'text-14 dark-theme text-decoration-none', href: 'tutor-payments' }, 'All payments')),
    simpleTable([
      { label: 'Tutor', render: (p) => person(p.tutor, p.invoice) },
      { label: 'Paid On', render: (p) => cell(day(p.date), inDays(p.date)) },
      { label: 'Method', render: (p) => cell(p.method, p.ref) },
      { label: 'Amount', render: (p) => cell(lkr(p.amount)) },
      { label: 'Status', render: (p) => chip(p.status) },
      { label: '', render: (p) => h('button', { type: 'button', class: 'custom-btn ex-btn-sm px-3', onclick: () => paymentDialog(p, { admin: true, reload: load }) }, p.status === 'waiting' ? 'Check' : 'View') },
    ], d.recent, { title: 'No payments yet', text: 'Payments from tutors appear here.' }));
}

async function load() {
  fill(root, DashboardKit.skeleton());
  try {
    const { data } = await api.get('/admin/dashboard');
    fill(root, root.dataset.welcome === '1' ? DashboardKit.welcome(root.dataset.fullName, 'Welcome back! See how ENLIXO Tutor is doing today: tutors, trials, payments and what needs you.') : null, greeting(data), previewNote(), stats(data), ...charts(data), lists(data), recent(data));
    if (window.Chart) drawCharts(data); else window.addEventListener('load', () => drawCharts(data), { once: true });
  } catch (e) {
    fill(root, EmptyState.error(e.message, load));
  }
}
load();
