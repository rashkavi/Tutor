// Tutor → a student's Fee Card (tutor/student-fees.php?student-id=). Profile strip → stat cards → one card per class
// with a box per month (the paper fee card) → receipts. Data: GET /tutor/students/{id}/fees.
import { StatCard } from '../components/StatCard.js';
import { QuickGuide } from '../components/QuickGuide.js';
import {
  api, h, fill, icon, lkr, day, plural, cell, panel, simpleTable, EmptyState, feeChip, previewNote, feeDialog, recordPayment, receiptDialog, concessionDialog, reminderDialog, lockBadge, waLink,
} from './fees-common.js';

QuickGuide.attachAll();
const root = document.getElementById('card-page');
const id = Number(new URLSearchParams(location.search).get('student-id')) || 2400;
let D;

const box = (m) => h('button', { type: 'button', class: `fe-box fe-box-${m.status}`, title: `${m.long}: ${m.status === 'paid' ? `paid ${day(m.paid_on)}` : m.status} · ${lkr(m.net)}`, onclick: () => feeDialog(m.id, { reload: load }) },
  h('span', { class: 'fe-box-m' }, m.label), h('span', { class: 'fe-box-v' }, m.status === 'free' ? 'Free' : m.status === 'paid' ? icon('check', { size: 16 }) : m.status === 'waiting' ? 'Slip' : lkr(m.net).replace('LKR ', '')));

function header() {
  const s = D.student;
  const owed = D.classes.flatMap((c) => c.months.filter((m) => ['unpaid', 'overdue', 'rejected'].includes(m.status) && m.status !== 'unpaid').map((m) => ({ ...m, cls: c })));
  fill(document.getElementById('card-actions'),
    h('div', { class: 'col-6' }, h('button', { type: 'button', class: 'custom-btn-outline w-100 d-inline-flex align-items-center justify-content-center gap-2', onclick: () => concessionDialog({ student: s, classes: D.classes, reload: load }) }, 'Free Card / Discount', lockBadge('fees.advanced'))),
    h('div', { class: 'col-6' }, h('button', { type: 'button', class: 'custom-btn w-100', onclick: () => recordPayment({ student: s.id, select: owed.map((m) => m.id), reload: load }) }, 'Record Payment')));
  return h('div', { class: 'col-12 component-theme p-4 mb-4 d-flex flex-wrap align-items-center gap-4' },
    h('span', { class: 'ad-profile-avatar m-0', style: { width: '64px', height: '64px', fontSize: '22px' } }, s.name.split(' ').map((x) => x[0]).join('')),
    h('div', { class: 'flex-grow-1' }, h('div', { class: 'main-topic' }, s.name), h('div', { class: 'text-14 sub-text' }, `${s.sid} · joined ${day(s.joined)} · ${plural(D.classes.length, 'class', 'classes')}`)),
    h('div', { class: 'd-flex flex-wrap gap-4' },
      cell(s.whatsapp, 'WhatsApp'), cell(s.email, 'E-mail'),
      h('a', { class: 'custom-btn-outline px-3 d-inline-flex align-items-center gap-2 text-decoration-none', href: waLink(s.whatsapp, `Hello ${s.name.split(' ')[0]},`), target: '_blank' }, icon('whatsapp', { size: 18 }), 'WhatsApp')));
}

function stats() {
  const t = D.stats;
  return StatCard.row([
    { icon: 'wallet', label: `Paid in ${new Date().getFullYear()}`, value: lkr(t.paid_year), caption: 'all classes' },
    { icon: 'warning', label: 'Owes Now', value: lkr(t.owed), caption: t.owed_months ? `${plural(t.owed_months, 'month')} after the due date` : 'nothing overdue' },
    { icon: 'receipt', label: 'Slips Waiting', value: t.waiting, caption: t.waiting ? 'waiting for you to check' : 'none' },
    { icon: 'gift', label: 'Free Cards & Discounts', value: lkr(t.saved), caption: t.last ? `last paid ${day(t.last.paid_on)}` : 'no payments yet' },
  ]);
}

function classes() {
  return D.classes.map((c) => h('div', { class: 'col-12 component-theme p-4 mb-4' },
    h('div', { class: 'd-flex flex-wrap justify-content-between align-items-start gap-2 mb-3' },
      h('div', {}, h('div', { class: 'sub-topic' }, c.name), h('div', { class: 'text-13 sub-text' }, `${lkr(c.fee)} a month · due on the ${c.due_day}th · in the class since ${c.since}`)),
      c.concession ? h('span', { class: 'ad-tag ad-tag-purple', title: c.concession.reason }, icon('gift', { size: 14 }), ` ${c.concession.kind === 'free' ? 'Free card' : 'Half card'} since ${c.concession.from}: ${c.concession.reason}`) : null),
    h('div', { class: 'fe-boxes' }, c.months.map(box)),
    h('div', { class: 'd-flex flex-wrap gap-3 mt-3' }, ['paid', 'waiting', 'unpaid', 'overdue', 'free'].map((st) => h('span', { class: 'd-inline-flex align-items-center gap-2 text-12 sub-text' }, h('span', { class: `fe-dot fe-box-${st}` }), feeChip(st).textContent)))));
}

function receipts() {
  return h('div', { class: 'col-12 component-theme p-4 mb-4' },
    h('div', { class: 'sub-topic mb-3' }, 'Receipts'),
    simpleTable([
      { label: 'Receipt', render: (r) => cell(r.no, day(r.paid_on)) },
      { label: 'Class / Months', render: (r) => cell(r.class.short, r.month_labels.join(', ')) },
      { label: 'Amount', render: (r) => cell(lkr(r.amount)) },
      { label: 'Paid By', render: (r) => cell(r.method, r.reference ? `ref ${r.reference}` : null) },
      { label: 'Received By', render: (r) => cell(r.by) },
      { label: '', render: (r) => h('button', { type: 'button', class: 'custom-btn ex-btn-sm px-3', onclick: () => receiptDialog(r.id) }, 'View') },
    ], D.receipts, { title: 'No receipts yet', text: 'Receipts appear here when a slip is approved or you record a payment.' }));
}

async function load() {
  try {
    ({ data: D } = await api.get(`/tutor/students/${id}/fees`));
    const crumb = document.querySelector('.breadcrumb li:last-child, .breadcrumb-item:last-child'); if (crumb) crumb.textContent = D.student.name;
    fill(root, previewNote(), header(), stats(), ...classes(), receipts());
  } catch (e) { fill(root, EmptyState.error(e.message, load)); }
}
load();
