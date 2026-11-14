// Tutor → Finance → Student Fees → one fee (tutor/fee-details.php?fee-id=), like V3's student payment page:
// header with Actions + the main button (Approve / Record Payment / View Receipt), the student's card on the left,
// the steps, the fee card, the slip and the breakdown on the right, the history below.
// "Check One by One" (?queue=1): Approve opens the next waiting slip. Data: GET /tutor/fees/{id} (fees-common api).
import { QuickGuide } from '../components/QuickGuide.js';
import { ActionMenu } from '../components/Popover.js';
import { DetailPage } from '../components/DetailPage.js';
import {
  api, h, fill, icon, lkr, day, inDays, feeChip, previewNote, slipView, waLink, reminderText, recordPayment, receiptDialog, rejectDialog, Toast, feeUrl,
} from './fees-common.js';

QuickGuide.attachAll();
const root = document.getElementById('fee-page');
const actions = document.getElementById('fee-actions');
const q = new URLSearchParams(location.search);
const id = Number(q.get('fee-id') || 0);
const queue = q.get('queue') === '1';
let f = null;

fill(root, DetailPage.loading());
load();

async function load() {
  try {
    f = (await api.get(`/tutor/fees/${id}`)).data;
    document.title = `${f.month_label} fee · ${f.student.name} - Tutor`;
    draw();
  } catch (e) {
    fill(root, DetailPage.notFound(e.status === 404 ? 'This fee was not found.' : `Couldn't load the fee: ${e.message}`, ['Back to Student Fees', 'fees']));
  }
}

const TONE = { paid: 'ex-receipt-green', waiting: 'ex-receipt-orange', overdue: 'ex-receipt-red', rejected: 'ex-receipt-red' };
const DOT = { paid: 'var(--dark-green)', waiting: 'var(--dark-orange)', overdue: 'var(--dark-red)', rejected: 'var(--dark-red)', free: 'var(--dark-purple)' };

async function approve() {
  try {
    const { data } = await api.post(`/tutor/fees/${f.id}/approve`, {});
    Toast.success(`Approved. ${f.student.name} has paid ${f.month_label}; the receipt was sent on WhatsApp.`);
    if (queue && data.next) { location.href = feeUrl(data.next, true); return; }
    load();
  } catch (e) { Toast.error(e.message); }
}

function draw() {
  const st = f.status;
  const remind = () => window.open(waLink(f.student.whatsapp, reminderText({ student: f.student, class: f.class, months: [{ label: f.month_label }], total: f.net })), '_blank');
  // ---- header buttons
  const items = [
    st === 'waiting' && { label: 'Reject Slip', icon: 'close', danger: true, onClick: () => rejectDialog(f, load) },
    ['unpaid', 'overdue', 'rejected'].includes(st) && { label: 'Send WhatsApp Reminder', icon: 'whatsapp', onClick: remind },
    ['unpaid', 'overdue', 'rejected'].includes(st) && { label: 'Record Payment', icon: 'wallet', onClick: () => recordPayment({ student: f.student.id, select: [f.id], reload: load }) },
    f.receipt_id && { label: 'Print Receipt', icon: 'printer', onClick: () => receiptDialog(f.receipt_id) },
    { label: 'Student Fee Card', icon: 'students', href: `student-fees?student-id=${f.student.id}` },
  ].filter(Boolean);
  const main = st === 'waiting' ? h('button', { type: 'button', class: 'custom-btn ex-btn-lg px-5 border-0', onclick: approve }, queue ? 'Approve & Next' : 'Approve')
    : ['unpaid', 'overdue', 'rejected'].includes(st) ? h('button', { type: 'button', class: 'custom-btn ex-btn-lg px-5 border-0', onclick: () => recordPayment({ student: f.student.id, select: [f.id], reload: load }) }, 'Record Payment')
      : f.receipt_id ? h('button', { type: 'button', class: 'custom-btn ex-btn-lg px-5 border-0', onclick: () => receiptDialog(f.receipt_id) }, 'View Receipt') : null;
  fill(actions, ActionMenu.button('Actions', items, { className: 'custom-outline-btn ex-btn-lg px-4 d-inline-flex align-items-center gap-2' }), main);

  // ---- steps: fee made → slip uploaded → checked → paid
  const first = f.history[f.history.length - 1];
  const steps = [
    { title: 'Fee made', sub: first ? first.when.slice(0, 10) : `${f.month}-01`, state: 'done' },
    f.slip ? { title: 'Slip uploaded', sub: f.slip.uploaded.slice(0, 10), state: 'done' }
      : st === 'paid' ? { title: 'Paid to you', sub: f.method ?? 'Cash', state: 'done' }
        : st === 'free' ? { title: 'Free card', sub: 'Nothing to pay', state: 'done' }
          : { title: 'Slip uploaded', sub: st === 'overdue' ? `Overdue ${inDays(f.due)}` : `Due ${day(f.due)}`, state: st === 'overdue' ? 'bad' : 'now' },
    st === 'waiting' ? { title: 'Check the slip', sub: 'Compare it with your bank', state: 'now' }
      : st === 'rejected' ? { title: 'Slip rejected', sub: f.reason ?? '', state: 'bad' }
        : ['paid', 'free'].includes(st) ? { title: 'Checked', sub: f.checked_by ? `By ${f.checked_by}` : 'Done', state: 'done' } : { title: 'Checked', sub: 'After the slip', state: 'todo' },
    st === 'paid' ? { title: 'Receipt', sub: f.receipt ?? 'Given', state: 'done' } : st === 'free' ? { title: 'Receipt', sub: 'Not needed', state: 'done' } : { title: 'Receipt', sub: 'After approval', state: 'todo' },
  ];

  const breakdown = h('div', { class: 'ex-lines' },
    h('div', { class: 'ex-line' }, h('span', {}, `${f.month_label} class fee`), h('span', {}, lkr(f.amount))),
    f.discount ? h('div', { class: 'ex-line' }, h('span', {}, f.discount >= f.amount ? 'Free card' : 'Discount'), h('span', {}, `- ${lkr(f.discount)}`)) : null,
    f.penalty ? h('div', { class: 'ex-line' }, h('span', {}, 'Late fee'), h('span', {}, lkr(f.penalty))) : null,
    h('div', { class: 'ex-line ex-line-total' }, h('span', {}, st === 'paid' ? 'Paid' : 'To pay'), h('span', {}, lkr(f.net))));

  const right = [
    DetailPage.card(st === 'waiting' ? 'Check Payment Slip' : 'Fee', [
      h('div', { class: 'mb-4' }, DetailPage.steps(steps)),
      DetailPage.receipt({
        label: f.receipt ? 'Receipt No' : 'Fee', ref: f.receipt ?? `${f.class.short} · ${f.month_label}`, amountLabel: st === 'paid' ? 'Total Paid' : 'To Pay', amount: lkr(f.net), chip: feeChip(st), tone: TONE[st] ?? '',
        facts: [
          ['Month', f.month_label, 'calendar'], ['Due date', `${day(f.due)}${['unpaid', 'overdue'].includes(st) ? ` (${inDays(f.due)})` : ''}`, 'clock'],
          ['Paid with', st === 'paid' ? (f.method ?? (f.slip ? 'Bank slip' : 'Cash')) : f.slip ? 'Bank slip (waiting)' : '-', 'wallet'],
          ['Checked by', f.checked_by ?? (st === 'waiting' ? 'Not checked yet' : '-'), 'user-check'],
        ],
      }),
      st === 'rejected' && f.reason ? h('div', { class: 'ex-panel-soft p-3 mt-3 text-14' }, h('b', {}, 'Why the slip was rejected: '), f.reason) : null,
    ]),
    h('div', { class: 'row g-4' },
      h('div', { class: 'col-12 col-lg-7' }, DetailPage.card('Payment Slip', slipView(f), { cls: 'h-100' })),
      h('div', { class: 'col-12 col-lg-5' }, DetailPage.card('Breakdown', breakdown, { cls: 'h-100' }))),
  ];

  fill(root, previewNote(), ...DetailPage.layout(
    DetailPage.profile({
      name: f.student.name, subtitle: f.class.name, ref: f.student.sid, refLabel: 'ID', status: feeChip(st), dot: DOT[st],
      mobile: f.student.whatsapp, whatsapp: f.student.whatsapp,
      facts: [['Class', f.class.short], ['Month', f.month_label], ['Fee', lkr(f.amount)], f.discount ? ['Discount', lkr(f.discount)] : ['Discount', null], ['To pay', lkr(f.net)]],
    }),
    right,
    DetailPage.history(f.history.map((x) => ({ who: x.by, when: x.when, what: x.what })), { title: 'History' })));
}
