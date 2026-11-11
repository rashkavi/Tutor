// Tutor → Finance → Student Fees (tutor/fees.php). V3 order: header card → month / class strip → stat cards →
// chart + classes panels → one table card with tabs (This Month, Slips to Check, Unpaid, Receipts).
// Data: GET /tutor/fees/overview (cards, chart, counts) and one list endpoint per tab.
import { DataView } from '../components/DataView.js';
import { StatCard } from '../components/StatCard.js';
import { Tabs } from '../components/Tabs.js';
import { ActionMenu } from '../components/Popover.js';
import { QuickGuide } from '../components/QuickGuide.js';
import {
  api, gate, has, lockBadge, guard, h, fill, icon, lkr, short, day, inDays, plural, cell, person, panel, EmptyState, Toast,
  feeChip, STATUS_FILTER, METHOD_FILTER, previewNote, feeDialog, recordPayment, receiptDialog, reminderDialog, waLink, reminderText, thisMonth,
} from './fees-common.js';

QuickGuide.attachAll();
const root = document.getElementById('fees-page');
const params = new URLSearchParams(location.search);
const state = { month: params.get('month') ?? thisMonth(), class: params.get('class') ?? '' };
const HEX = { primary: '#0061D2', success: '#48CD5D', orange: '#FF9500', light: '#B9CFF2' };
const dark = () => document.documentElement.getAttribute('data-custom-theme') === 'dark';
let D; let tabs;

fill(document.getElementById('fees-actions'),
  h('div', { class: 'col-6 d-flex justify-content-end align-items-center' },
    h('a', { class: 'bg-transparent border-0 dark-theme', href: 'fee-settings', title: 'Fee Settings', 'aria-label': 'Fee Settings' }, icon('settings', { size: 30 }))),
  h('div', { class: 'col-6' }, h('button', { type: 'button', class: 'custom-btn w-100', onclick: () => recordPayment({ reload }) }, 'Record Payment')));

const writeUrl = () => { const p = new URLSearchParams(location.search); p.set('month', state.month); if (state.class) p.set('class', state.class); else p.delete('class'); history.replaceState(null, '', `${location.pathname}?${p}`); };

function strip() {
  const i = D.months.findIndex((x) => x.value === state.month);
  const go = (d) => { const n = D.months[i - d]; if (n) { state.month = n.value; load(); } };
  const cls = h('select', { class: 'form-select custom-select', 'aria-label': 'Class', onchange: (e) => { state.class = e.target.value; load(); } },
    h('option', { value: '' }, 'All classes'), D.classes.map((c) => h('option', { value: c.id, selected: String(c.id) === String(state.class) }, c.name)));
  return h('div', { class: 'col-12 component-theme p-3 mb-4 d-flex flex-wrap align-items-center gap-3' },
    h('div', { class: 'd-flex align-items-center gap-2' },
      h('button', { type: 'button', class: 'fe-step', 'aria-label': 'Previous month', disabled: i >= D.months.length - 1, onclick: () => go(-1) }, icon('chevron-left', { size: 18 })),
      h('div', { class: 'text-center', style: { minWidth: '170px' } }, h('div', { class: 'sub-topic' }, D.month_label), h('div', { class: 'text-12 sub-text' }, D.is_current ? 'This month' : 'Past month')),
      h('button', { type: 'button', class: 'fe-step', 'aria-label': 'Next month', disabled: i <= 0, onclick: () => go(1) }, icon('chevron-right', { size: 18 }))),
    h('div', { class: 'flex-grow-1' }),
    h('div', { style: { minWidth: '280px' } }, cls));
}

function stats() {
  const s = D.stats;
  const arrears = has('payments.arrears')
    ? { icon: 'warning', label: 'Owed From Earlier Months', value: lkr(s.arrears.value), trend: s.arrears.students ? { text: plural(s.arrears.students, 'student'), dir: 'down' } : null, caption: 'still not paid', menu: [{ label: 'Show unpaid students', icon: 'filter', onClick: () => tabs.show('unpaid') }] }
    : { icon: 'lock', label: 'Owed From Earlier Months', value: 'PRO', caption: 'Arrears tracking is on PRO', onClick: () => gate.openUpgrade('payments.arrears') };
  return StatCard.row([
    { icon: 'wallet', label: 'Collected', value: lkr(s.collected.value), trend: StatCard.trend(s.collected.value, s.collected.before), caption: 'against last month', bars: s.collected.bars, menu: [{ label: 'Show receipts', icon: 'view', onClick: () => tabs.show('receipts') }] },
    { icon: 'hourglass', label: 'Still to Collect', value: lkr(s.to_collect.value), trend: s.to_collect.overdue ? { text: `${s.to_collect.overdue} overdue`, dir: 'down' } : null, caption: `${plural(s.to_collect.students, 'student')} not paid`, onClick: () => { tabs.show('month'); } },
    { icon: 'receipt', label: 'Slips to Check', value: s.slips.value, caption: s.slips.value ? `${lkr(s.slips.amount)} waiting for you` : 'all checked', onClick: () => tabs.show('slips') },
    arrears,
    { icon: 'gift', label: 'Free Cards & Discounts', value: lkr(s.free.value), caption: `${plural(s.free.students, 'student')}, ${s.free.full} free`, menu: [{ label: 'Show free cards', icon: 'filter', onClick: () => { tabs.show('month', { status: 'free' }); } }] },
    { icon: 'growth', label: 'Collection Rate', value: `${s.rate.value}%`, caption: 'paid or slip sent', bars: s.rate.bars },
  ], { cols: 'col-12 col-md-6 col-xl-4' });
}

function panels() {
  const chart = h('div', { class: 'ad-chart-box flex-grow-1' }, h('canvas', { id: 'feChart' }));
  const classes = h('div', {}, D.by_class.map((c) => {
    const pct = c.due ? Math.round(((c.collected + c.waiting) / c.due) * 100) : 0;
    return h('a', { class: 'ad-todo', href: `?month=${state.month}&class=${c.id}` },
      h('span', { class: 'ad-todo-icon' }, icon('classes', { size: 20 })),
      h('span', { class: 'flex-grow-1 min-w-0' }, h('span', { class: 'text-14 main-text d-block text-truncate' }, c.short), h('span', { class: 'text-12 sub-text' }, c.unpaid ? `${plural(c.unpaid, 'student')} not paid` : 'everyone paid')),
      h('span', { class: 'd-flex flex-column align-items-end gap-1' }, h('span', { class: 'text-13 main-text fw-medium' }, `${lkr(c.collected)}`), h('span', { class: 'ad-bar' }, h('i', { class: pct < 60 ? 'bad' : pct < 85 ? 'warn' : '', style: { width: `${pct}%` } }))));
  }));
  return h('div', { class: 'row g-4 mb-4' },
    h('div', { class: 'col-12 col-xl-8' }, panel('Fees Collected', 'Each month: the fees that were due (after free cards and discounts) and what was paid. Slips waiting for you are shown on top.',
      has('reports.payments') ? chart : gate.wrap('reports.payments', h('div', { style: { height: '260px' } })), { link: ['Receipts', '#receipts'] })),
    h('div', { class: 'col-12 col-xl-4' }, panel(`Classes in ${D.month_label.split(' ')[0]}`, 'Money collected per class this month. The bar shows how much of the class fee is paid or sent.', classes)));
}

function drawChart() {
  const el = document.getElementById('feChart'); if (!el || !window.Chart) return;
  const c = D.chart; const tick = dark() ? '#A9B4C6' : '#6E7485'; const grid = dark() ? 'rgba(169,180,198,.12)' : 'rgba(110,116,133,.12)';
  new window.Chart(el, { type: 'bar', data: { labels: c.labels, datasets: [
    { label: 'Due', data: c.due, backgroundColor: HEX.light, maxBarThickness: 22, stack: 'due' },
    { label: 'Paid', data: c.collected, backgroundColor: HEX.primary, maxBarThickness: 22, stack: 'got' },
    { label: 'Slip to check', data: c.waiting, backgroundColor: HEX.orange, maxBarThickness: 22, stack: 'got' },
  ] }, options: { maintainAspectRatio: false, plugins: { legend: { position: 'top', align: 'end', labels: { color: tick, boxWidth: 12, boxHeight: 12 } }, tooltip: { callbacks: { label: (x) => `${x.dataset.label}: ${lkr(x.raw)}` } } },
    scales: { x: { stacked: true, ticks: { color: tick }, grid: { color: 'transparent' } }, y: { stacked: true, beginAtZero: true, ticks: { color: tick, callback: (v) => short(v) }, grid: { color: grid } } } } });
}

const classFilter = () => ({ key: 'class', label: 'Class', allLabel: 'All classes', options: async () => D.classes.map((c) => ({ value: c.id, label: c.name })) });
const amountCell = (f) => cell(lkr(f.net), f.discount ? (f.discount >= f.amount ? 'free card' : `${lkr(f.amount)} - ${lkr(f.discount)}`) : f.penalty ? `incl. ${lkr(f.penalty)} late fee` : 'class fee');

function monthList(panelEl, preset = {}) {
  new DataView({
    api, endpoint: '/tutor/fees', key: 'fees-month', urlPrefix: 'm_', perPage: 10, perPageOptions: [10, 25, 50, 100], noun: ['fee', 'fees'],
    params: { month: state.month, ...(state.class ? { class: state.class } : {}) },
    searchPlaceholder: 'Search by student name, Student ID, WhatsApp or receipt number',
    filters: [{ key: 'status', label: 'Status', allLabel: '- All Status -', options: STATUS_FILTER }, { key: 'method', label: 'Paid By', allLabel: '- All Methods -', options: METHOD_FILTER }],
    exportCsv: { filename: `fees-${state.month}.csv`, columns: [['Student ID', (f) => f.student.sid], ['Student', (f) => f.student.name], ['Class', (f) => f.class.name], ['Month', (f) => f.month_label], ['Fee', (f) => f.amount], ['Discount', (f) => f.discount], ['Late Fee', (f) => f.penalty], ['To Pay', (f) => f.net], ['Due Date', (f) => f.due], ['Status', (f) => f.status], ['Paid On', (f) => f.paid_on ?? ''], ['Paid By', (f) => f.method ?? ''], ['Receipt', (f) => f.receipt ?? '']] },
    columns: [
      { key: 'student', label: 'Student', sortable: true, render: (f) => person(f.student.name, f.student.sid) },
      !state.class && { key: 'class', label: 'Class', wrap: true, render: (f) => cell(f.class.short, f.month_label) },
      { key: 'net', label: 'Fee', sortable: true, render: amountCell },
      { key: 'due', label: 'Due Date', sortable: true, render: (f) => cell(day(f.due), ['unpaid', 'overdue'].includes(f.status) ? inDays(f.due) : null) },
      { key: 'status', label: 'Status', render: (f) => h('div', { class: 'd-flex flex-column align-items-start gap-1' }, feeChip(f.status),
        f.reason ? h('span', { class: 'text-12 sub-text cell-wrap' }, f.reason) : f.paid_on ? h('span', { class: 'text-12 sub-text' }, `${f.status === 'waiting' ? 'sent' : 'paid'} ${day(f.paid_on)} · ${f.method}`) : null) },
      { key: 'go', label: '', width: '190px', render: (f) => rowAction(f) },
    ].filter(Boolean),
    empty: { title: 'No fees for this month', text: 'Fees are created on the 1st for every active student in a class with a monthly fee.' },
    onLocked: (e) => gate?.openUpgrade(e.details),
  }).mount(panelEl);
  if (preset.status) { /* stat card "Show free cards" */ const p = new URLSearchParams(location.search); p.set('m_status', preset.status); history.replaceState(null, '', `${location.pathname}?${p}`); }
}

function rowAction(f) {
  const owe = ['unpaid', 'overdue', 'rejected'].includes(f.status);
  const main = f.status === 'waiting' ? h('button', { type: 'button', class: 'custom-btn ex-btn-sm px-3', onclick: () => feeDialog(f.id, { reload, queue: true }) }, 'Check Slip')
    : owe ? h('button', { type: 'button', class: 'custom-btn ex-btn-sm px-3', onclick: () => recordPayment({ student: f.student.id, select: [f.id], reload }) }, 'Record Payment')
      : h('button', { type: 'button', class: 'custom-btn ex-btn-sm px-3', onclick: () => feeDialog(f.id, { reload }) }, 'View');
  return h('div', { class: 'd-flex align-items-center justify-content-end gap-1' }, main, ActionMenu.kebab([
    { label: 'View Fee', icon: 'view', onClick: () => feeDialog(f.id, { reload }) },
    { label: 'Student Fee Card', icon: 'id-card', href: `student-fees?student-id=${f.student.id}` },
    owe && { label: 'Send WhatsApp Reminder', icon: 'whatsapp', onClick: () => window.open(waLink(f.student.whatsapp, reminderText({ student: f.student, class: f.class, months: [{ label: f.month_label }], total: f.net })), '_blank') },
    f.receipt && { label: 'Print Receipt', icon: 'printer', onClick: () => feeDialog(f.id, { reload }) },
  ].filter(Boolean), `Actions for ${f.student.name}`));
}

function slipList(panelEl) {
  new DataView({
    api, endpoint: '/tutor/fees/slips', key: 'fees-slips', urlPrefix: 's_', perPage: 10, perPageOptions: [10, 25, 50], noun: ['slip', 'slips'],
    params: state.class ? { class: state.class } : {},
    searchPlaceholder: 'Search by student, Student ID or slip reference',
    toolbarExtra: h('button', { type: 'button', class: 'custom-btn-outline w-100', onclick: async () => { const { data } = await api.get('/tutor/fees/slips', { per_page: 1 }); if (data[0]) feeDialog(data[0].id, { reload, queue: true }); else Toast.success('No slips waiting.'); } }, 'Check One by One'),
    columns: [
      { key: 'student', label: 'Student', render: (f) => person(f.student.name, f.student.sid) },
      { key: 'class', label: 'Class / Month', wrap: true, render: (f) => cell(f.class.short, f.month_label) },
      { key: 'net', label: 'Fee', render: (f) => cell(lkr(f.net), Math.round(f.slip.amount) === Math.round(f.net) ? 'slip amount matches' : `slip shows ${lkr(f.slip.amount)}`) },
      { key: 'up', label: 'Uploaded', render: (f) => cell(f.slip.uploaded.slice(0, 10), `ref ${f.slip.reference}`) },
      { key: 'st', label: 'Status', render: () => feeChip('waiting') },
      { key: 'go', label: '', width: '110px', render: (f) => h('button', { type: 'button', class: 'custom-btn ex-btn-sm px-3', onclick: () => feeDialog(f.id, { reload, queue: true }) }, 'Check') },
    ],
    empty: { title: 'No slips to check', text: 'When a student uploads a bank slip in the app, it waits here for you.' },
  }).mount(panelEl);
}

function unpaidList(panelEl) {
  if (!has('payments.arrears')) panelEl.append(h('div', { class: 'ad-preview mb-3' }, icon('lock', { size: 18 }), h('span', {}, h('b', {}, 'This month only. '), 'Students who owe from earlier months are listed with PRO (Arrears Tracking). ', h('a', { href: '#', class: 'dark-theme', onclick: (e) => { e.preventDefault(); gate.openUpgrade('payments.arrears'); } }, 'See what PRO adds'))));
  let rows = [];
  const view = new DataView({
    api, endpoint: '/tutor/fees/unpaid', key: 'fees-unpaid', urlPrefix: 'u_', perPage: 10, perPageOptions: [10, 25, 50, 100], noun: ['student', 'students'],
    params: state.class ? { class: state.class } : {},
    searchPlaceholder: 'Search by student name, Student ID or class',
    filters: has('payments.arrears') ? [{ key: 'months', label: 'Months Not Paid', allLabel: '- Any -', options: [{ value: '1', label: '1 month' }, { value: '2', label: '2 months' }, { value: '3', label: '3 months or more' }] }] : [],
    bulk: { actions: [{ value: 'remind', label: 'Send WhatsApp reminder' }], onApply: async (ids) => { reminderDialog(rows.filter((r) => ids.includes(r.id)), { reload }); } },
    exportCsv: { filename: `unpaid-fees-${thisMonth()}.csv`, columns: [['Student ID', (r) => r.student.sid], ['Student', (r) => r.student.name], ['WhatsApp', (r) => r.student.whatsapp], ['Class', (r) => r.class.name], ['Months', (r) => r.months.map((m) => m.label).join(' ')], ['Owed (LKR)', (r) => r.total], ['Last Reminder', (r) => r.last_reminder ?? '']] },
    columns: [
      { key: 'student', label: 'Student', render: (r) => { rows = [...rows.filter((x) => x.id !== r.id), r]; return person(r.student.name, r.student.sid); } },
      { key: 'class', label: 'Class', wrap: true, render: (r) => cell(r.class.short, r.student.whatsapp) },
      { key: 'months', label: 'Months Not Paid', wrap: true, render: (r) => h('div', { class: 'd-flex flex-wrap gap-1' }, r.months.map((m) => h('span', { class: `fe-mchip ${m.status === 'rejected' ? 'rej' : ''}`, title: `${m.label}: ${lkr(m.net)}` }, m.label))) },
      { key: 'total', label: 'Owed', render: (r) => cell(lkr(r.total), `since ${day(r.oldest)}`) },
      { key: 'rem', label: 'Last Reminder', render: (r) => cell(r.last_reminder ? r.last_reminder.slice(0, 10) : 'Not reminded', r.last_reminder ? 'WhatsApp' : null) },
      { key: 'go', label: '', width: '150px', render: (r) => h('div', { class: 'd-flex align-items-center justify-content-end gap-1' },
        h('button', { type: 'button', class: 'custom-btn ex-btn-sm px-3', onclick: () => recordPayment({ student: r.student.id, select: r.months.map((m) => m.id), reload }) }, 'Record Payment'),
        ActionMenu.kebab([
          { label: 'Send WhatsApp Reminder', icon: 'whatsapp', onClick: () => reminderDialog([r], { reload }) },
          { label: 'Student Fee Card', icon: 'id-card', href: `student-fees?student-id=${r.student.id}` },
        ], `Actions for ${r.student.name}`)) },
    ],
    empty: { title: 'Everyone has paid', text: 'Students who did not pay after the due date are listed here.' },
    onLocked: (e) => gate?.openUpgrade(e.details),
  }).mount(panelEl);
  return view;
}

function receiptList(panelEl) {
  new DataView({
    api, endpoint: '/tutor/fees/receipts', key: 'fees-receipts', urlPrefix: 'r_', perPage: 10, perPageOptions: [10, 25, 50, 100], noun: ['receipt', 'receipts'],
    params: state.class ? { class: state.class } : {},
    searchPlaceholder: 'Search by receipt number, student or reference',
    filters: [{ key: 'method', label: 'Paid By', allLabel: '- All Methods -', options: METHOD_FILTER }, { key: 'month', label: 'Paid In', allLabel: '- Any Month -', options: D.months.map((m) => ({ value: m.value, label: m.label })) }],
    exportCsv: { filename: 'fee-receipts.csv', columns: [['Receipt', (r) => r.no], ['Paid On', (r) => r.paid_on], ['Student ID', (r) => r.student.sid], ['Student', (r) => r.student.name], ['Class', (r) => r.class.name], ['Months', (r) => r.month_labels.join(' ')], ['Amount (LKR)', (r) => r.amount], ['Paid By', (r) => r.method], ['Reference', (r) => r.reference ?? ''], ['Received By', (r) => r.by]] },
    columns: [
      { key: 'no', label: 'Receipt', sortable: true, render: (r) => cell(r.no, day(r.paid_on)) },
      { key: 'student', label: 'Student', render: (r) => person(r.student.name, r.student.sid) },
      { key: 'class', label: 'Class / Months', wrap: true, render: (r) => cell(r.class.short, r.month_labels.join(', ')) },
      { key: 'amount', label: 'Amount', sortable: true, render: (r) => cell(lkr(r.amount)) },
      { key: 'method', label: 'Paid By', render: (r) => cell(r.method, r.reference ? `ref ${r.reference}` : null) },
      { key: 'by', label: 'Received By', render: (r) => cell(r.by) },
      { key: 'go', label: '', width: '96px', render: (r) => h('button', { type: 'button', class: 'custom-btn ex-btn-sm px-3', onclick: () => receiptDialog(r.id) }, 'View') },
    ],
    empty: { title: 'No receipts yet', text: 'Every approved slip and every payment you record gets a receipt number here.' },
  }).mount(panelEl);
}

function table() {
  const card = h('div', { class: 'col-12 component-theme p-4', id: 'receipts' });
  tabs = new Tabs({ tabs: [
    { id: 'month', label: `${D.month_label.split(' ')[0]} Fees`, badge: D.counts.month, render: (p) => monthList(p) },
    { id: 'slips', label: 'Slips to Check', badge: D.counts.slips, render: (p) => slipList(p) },
    { id: 'unpaid', label: 'Unpaid', badge: lockBadge('payments.arrears') ?? D.counts.unpaid, render: (p) => unpaidList(p) },
    { id: 'receipts', label: 'Receipts', badge: D.counts.receipts, render: (p) => receiptList(p) },
  ] }).mount(card);
  // stat cards and links switch tabs; Tabs keeps the open tab in ?tab=
  tabs.show = (id) => { card.querySelectorAll('.nav-link')[['month', 'slips', 'unpaid', 'receipts'].indexOf(id)]?.click(); card.scrollIntoView({ behavior: 'smooth', block: 'start' }); };
  return card;
}

async function reload() {
  const { data } = await api.get('/tutor/fees/overview', { month: state.month, class: state.class });
  D = data;
  const counts = document.querySelectorAll('#receipts .nav-link .tab-count');
  [D.counts.month, D.counts.slips, has('payments.arrears') ? D.counts.unpaid : null, D.counts.receipts].forEach((n, i) => { if (counts[i] && n != null) counts[i].textContent = String(n); });
  const active = document.querySelector('#receipts .nav-link.active'); active?.click();
}

async function load() {
  writeUrl();
  fill(root, h('div', { class: 'placeholder-glow' }, h('span', { class: 'placeholder col-12', style: { height: '140px' } })));
  try {
    ({ data: D } = await api.get('/tutor/fees/overview', { month: state.month, class: state.class }));
    fill(root, previewNote(), strip(), stats(), panels(), table());
    if (window.Chart) drawChart(); else window.addEventListener('load', drawChart, { once: true });
  } catch (e) {
    fill(root, EmptyState.error(e.message, load));
  }
}
load();
