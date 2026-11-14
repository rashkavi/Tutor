// Admin → Finance → Payments → one payment (admin/tutor-payment-details.php?payment-id=), like V3's student payment page:
// header with Actions + Approve, the tutor's card on the left, the steps, the payment card, the slip and the invoice
// on the right, the tutor's billing history below. Data: GET /admin/payments/{id}.
import { QuickGuide } from '../components/QuickGuide.js';
import { ActionMenu } from '../components/Popover.js';
import { DetailPage } from '../components/DetailPage.js';
import { api, h, fill, icon, lkr, day, chip, invoiceDialog, rejectPayment, slipUrl, trySave, Toast } from './admin-common.js';

QuickGuide.attachAll();
const root = document.getElementById('payment-page');
const actions = document.getElementById('payment-actions');
const id = Number(new URLSearchParams(location.search).get('payment-id') || 0);
let d = null;

fill(root, DetailPage.loading());
load();

async function load() {
  try {
    d = (await api.get(`/admin/payments/${id}`)).data;
    document.title = `Payment ${d.payment.ref} - Admin`;
    draw();
  } catch (e) {
    fill(root, DetailPage.notFound(e.status === 404 ? 'This payment was not found.' : `Couldn't load the payment: ${e.message}`, ['Back to Payments', 'tutor-payments']));
  }
}

async function approve() {
  const p = d.payment;
  if (!await trySave(root, () => api.post(`/admin/payments/${p.id}/approve`, {}))) return;
  Toast.success(`Approved. ${p.invoice} is paid and ${p.tutor} is active.`);
  load();
}

const TONE = { approved: 'ex-receipt-green', waiting: 'ex-receipt-orange', rejected: 'ex-receipt-red' };
const DOT = { approved: 'var(--dark-green)', waiting: 'var(--dark-orange)', rejected: 'var(--dark-red)' };

function draw() {
  const { payment: p, tutor: t, invoice: inv } = d;
  const items = [
    p.status === 'waiting' && { label: 'Reject Payment', icon: 'close', danger: true, onClick: () => rejectPayment(p, load) },
    p.slip && { label: 'Open Slip', icon: 'view', href: slipUrl(p, true) },
    inv && { label: 'View Invoice', icon: 'invoice', onClick: () => invoiceDialog(inv, { admin: true, reload: load }) },
    { label: 'Open Tutor', icon: 'tutors', href: `tutor-details?tutor-id=${t.id}` },
    { label: 'WhatsApp', icon: 'whatsapp', href: DetailPage.waLink(t.whatsapp || t.mobile) },
  ].filter(Boolean);
  const main = p.status === 'waiting' ? h('button', { type: 'button', class: 'custom-btn ex-btn-lg px-5 border-0', onclick: approve }, 'Approve')
    : h('a', { class: 'custom-btn ex-btn-lg px-5 text-decoration-none d-inline-flex align-items-center', href: `tutor-details?tutor-id=${t.id}` }, 'Open Tutor');
  fill(actions, ActionMenu.button('Actions', items, { className: 'custom-outline-btn ex-btn-lg px-4 d-inline-flex align-items-center gap-2' }), main);

  const steps = [
    inv ? { title: 'Invoice made', sub: `${inv.number} · ${day(inv.issued)}`, state: 'done' } : { title: 'Invoice', sub: 'Old subscription payment', state: 'done' },
    p.recorded ? { title: 'Recorded', sub: `${p.method} · ${day(p.date)}`, state: 'done' } : { title: 'Slip uploaded', sub: day(p.date), state: 'done' },
    p.status === 'waiting' ? { title: 'Check the slip', sub: 'Compare it with the bank', state: 'now' }
      : p.status === 'rejected' ? { title: 'Rejected', sub: p.reason ?? '', state: 'bad' }
        : { title: 'Approved', sub: [p.checked_by && `By ${p.checked_by}`, p.checked_at && day(String(p.checked_at).slice(0, 10))].filter(Boolean).join(' · ') || 'Done', state: 'done' },
    p.status === 'approved' ? { title: 'Tutor active', sub: t.status_label, state: 'done' } : { title: 'Tutor active', sub: 'After approval', state: 'todo' },
  ];

  const url = slipUrl(p, true);
  const img = h('img', { alt: `Bank slip of ${p.ref}`, src: url });
  img.addEventListener('error', () => img.replaceWith(h('div', { class: 'text-center' }, icon('receipt', { size: 44 }), h('div', { class: 'text-14 main-text fw-medium mt-2' }, 'PDF slip'))));
  const slip = p.slip
    ? h('div', { class: 'ex-slip-box' }, img, h('a', { class: 'custom-btn-outline px-4 text-decoration-none d-inline-flex align-items-center gap-2', href: url, target: '_blank', rel: 'noopener' }, icon('view', { size: 18 }), 'Open the slip'))
    : h('div', { class: 'ex-slip-box' }, icon('wallet', { size: 44 }), h('div', { class: 'text-14 main-text fw-medium' }, `${p.method}, recorded by ENLIXO`));
  const lines = inv ? h('div', { class: 'ex-lines' },
    inv.lines.map((l) => h('div', { class: 'ex-line' }, h('span', {}, l.text), h('span', {}, lkr(l.amount)))),
    inv.tax ? h('div', { class: 'ex-line' }, h('span', {}, 'Tax'), h('span', {}, lkr(inv.tax))) : null,
    h('div', { class: 'ex-line ex-line-total' }, h('span', {}, 'Invoice total'), h('span', {}, lkr(inv.total))),
    h('div', { class: 'ex-line' }, h('span', {}, 'Paid so far'), h('span', {}, lkr(inv.paid))),
    h('div', { class: 'd-flex justify-content-between align-items-center mt-3' }, chip(inv.status), h('button', { type: 'button', class: 'custom-btn-outline ex-btn-sm px-3', onclick: () => invoiceDialog(inv, { admin: true, reload: load }) }, 'View Invoice')))
    : h('div', { class: 'text-14 sub-text' }, 'This payment was made before invoices were used.');

  const right = [
    DetailPage.card(p.status === 'waiting' ? 'Check Payment Slip' : 'Payment', [
      h('div', { class: 'mb-4' }, DetailPage.steps(steps)),
      DetailPage.receipt({
        label: 'Payment', ref: p.ref, amountLabel: p.status === 'approved' ? 'Total Paid' : 'Amount', amount: lkr(p.amount), chip: chip(p.status), tone: TONE[p.status] ?? '',
        facts: [['Paid on', day(p.date), 'calendar'], ['Method', p.method, 'wallet'], ['Slip reference', p.reference || '-', 'hashtag'], ['Checked by', p.checked_by ?? 'Not checked yet', 'user-check']],
      }),
      p.status === 'rejected' && p.reason ? h('div', { class: 'ex-panel-soft p-3 mt-3 text-14' }, h('b', {}, 'Why it was rejected: '), p.reason) : null,
    ]),
    h('div', { class: 'row g-4' },
      h('div', { class: 'col-12 col-lg-7' }, DetailPage.card('Payment Slip', slip, { cls: 'h-100' })),
      h('div', { class: 'col-12 col-lg-5' }, DetailPage.card(inv ? `Invoice ${inv.number}` : 'Invoice', lines, { cls: 'h-100' }))),
  ];

  fill(root, ...DetailPage.layout(
    DetailPage.profile({
      name: `${t.title ? `${t.title} ` : ''}${t.name}`, subtitle: [t.brand, t.subject].filter(Boolean).join(' · ') || 'Tutor', ref: t.id, refLabel: 'Tutor', status: chip(t.status, t.status_label), dot: DOT[p.status],
      mobile: t.mobile, whatsapp: t.whatsapp, email: t.email,
      facts: [['Plan', `${t.plan_name}${t.cycle ? `, ${t.cycle}` : ''}`], ['Students', `${t.students}${t.limit ? ` of ${t.limit}` : ''}`], ['Still to pay', t.balance ? lkr(t.balance) : 'Nothing'], ['Renews', t.period_end ? day(t.period_end) : null], ['Joined', day(t.joined)]],
    }),
    right,
    DetailPage.history(d.history.map((x) => ({ who: x.by, when: x.when, what: x.what, note: x.reason })), { title: 'Billing History' })));
}
