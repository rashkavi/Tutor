// Admin → one invoice (admin/invoice.php?invoice-id=): the invoice card and lines, its payments, and on the page
// itself Record a Payment / Send Reminder / Void (they were pop-ups). Data: GET /admin/invoices/{id}.
import { QuickGuide } from '../components/QuickGuide.js';
import { DetailPage } from '../components/DetailPage.js';
import { api, h, fill, icon, lkr, day, inDays, chip, facts, hero, field, showErrors, sendReminder, paymentUrl, Toast } from './admin-common.js';

QuickGuide.attachAll();
const root = document.getElementById('inv-page');
const actions = document.getElementById('inv-actions');
const id = Number(new URLSearchParams(location.search).get('invoice-id') || 0);
const errs = (e) => e.details?.fields ?? e.details ?? {};
let D = null;
let panel = new URLSearchParams(location.search).get('do') || ''; // 'pay' | 'void'

fill(root, DetailPage.loading());
load();

async function load() {
  try { D = (await api.get(`/admin/invoices/${id}`)).data; } catch (e) {
    fill(root, DetailPage.notFound(e.status === 404 ? 'This invoice was not found.' : `Couldn't load the invoice: ${e.message}`, ['Back to Invoices', 'invoices']));
    return;
  }
  document.title = `${D.invoice.number} - Admin`;
  draw();
}

function open(which) {
  panel = which;
  draw();
  setTimeout(() => document.getElementById('inv-panel')?.scrollIntoView({ behavior: 'smooth', block: 'center' }), 60);
}

function draw() {
  const inv = D.invoice;
  const isOpen = ['open', 'overdue', 'part'].includes(inv.status);
  fill(actions,
    h('button', { type: 'button', class: 'custom-btn ex-btn-lg px-4 d-inline-flex align-items-center gap-2', onclick: () => window.print() }, icon('printer', { size: 18 }), 'Print'),
    isOpen ? h('button', { type: 'button', class: 'custom-btn ex-btn-lg px-5', onclick: () => open('pay') }, 'Record a Payment')
      : h('a', { class: 'custom-btn ex-btn-lg px-5 text-decoration-none', href: `tutor-details?tutor-id=${inv.tutor_id}` }, 'Open Tutor'));

  const lines = h('div', { class: 'ad-lines' },
    inv.lines.map((l) => h('div', { class: 'ad-line' }, h('span', {}, l.text), h('span', {}, lkr(l.amount)))),
    inv.tax ? h('div', { class: 'ad-line' }, h('span', {}, 'Tax'), h('span', {}, lkr(inv.tax))) : null,
    h('div', { class: 'ad-line ad-line-total' }, h('span', {}, 'Total'), h('span', {}, lkr(inv.total))),
    inv.paid ? h('div', { class: 'ad-line' }, h('span', {}, 'Paid'), h('span', {}, lkr(inv.paid))) : null,
    isOpen ? h('div', { class: 'ad-line ad-line-total' }, h('span', {}, 'Left to pay'), h('span', {}, lkr(inv.total - inv.paid))) : null);

  const pays = D.payments.length
    ? h('ul', { class: 'ad-pay-list' }, D.payments.map((p) => h('li', {},
      h('span', { class: 'min-w-0 flex-grow-1' }, h('strong', {}, `${lkr(p.amount)} · ${p.method}`), h('small', {}, `${day(p.date)}${p.reference ? ` · Ref ${p.reference}` : ''}`)),
      chip(p.status), h('a', { class: 'custom-btn table-btn px-3 text-decoration-none', href: paymentUrl(p) }, 'View'))))
    : h('div', { class: 'empty-state' }, h('div', { class: 'empty-state-icon' }, icon('inbox', { size: 28 })), h('div', { class: 'empty-state-title' }, 'No payment yet'), h('div', { class: 'empty-state-text' }, 'Slips the tutor uploads and payments you record appear here.'));

  const quick = isOpen ? h('div', { class: 'ld-actions mb-4' },
    act('wallet', 'Record a Payment', 'Bank, cash or cheque received', () => open('pay')),
    act('send', 'Send Reminder', 'E-mail about this invoice', async () => { await sendReminder(inv.tutor_id); }),
    act('close', 'Void Invoice', 'Made by mistake', () => open('void'), true),
    h('a', { class: 'ld-act', href: `tutor-details?tutor-id=${inv.tutor_id}` }, h('span', { class: 'ld-act-ico' }, icon('tutors', { size: 22 })), h('span', {}, h('strong', {}, 'Open Tutor'), h('small', {}, inv.tutor)))) : null;

  fill(root,
    quick,
    panel === 'pay' && isOpen ? payPanel(inv) : panel === 'void' && isOpen ? voidPanel(inv) : null,
    h('div', { class: 'row g-4' },
      h('div', { class: 'col-12 col-xl-7' }, DetailPage.card(inv.number, [
        hero('Invoice total', lkr(inv.total), chip(inv.status), inv.status === 'paid' ? 'Paid in full' : inv.status === 'void' ? `Void: ${inv.void_reason}` : `Due ${day(inv.due)} (${inDays(inv.due)})`),
        facts([['Tutor', inv.brand ? `${inv.tutor} · ${inv.brand}` : inv.tutor, true], ['Plan', `${inv.plan} · ${inv.cycle}`], ['For', inv.kind === 'upgrade' ? 'Plan upgrade' : 'Subscription period'],
          ['Period', `${day(inv.period_from)} - ${day(inv.period_to)}`, true], ['Issued', day(inv.issued)], ['Due date', day(inv.due)]]),
        h('div', { class: 'text-15 fw-semibold main-text mb-2' }, 'What it is for'), lines,
      ])),
      h('div', { class: 'col-12 col-xl-5' }, DetailPage.card('Payments', pays))));
}

function act(ico, title, sub, fn, danger = false) {
  return h('button', { type: 'button', class: `ld-act text-start${danger ? ' is-danger' : ''}`, onclick: fn },
    h('span', { class: 'ld-act-ico' }, icon(ico, { size: 22 })), h('span', {}, h('strong', {}, title), h('small', {}, sub)));
}

function payPanel(inv) {
  const amount = h('input', { class: 'form-control custom-input', type: 'number', min: 1, value: inv.total - inv.paid, placeholder: 'Enter the amount received', 'data-key': 'amount' });
  const method = h('select', { class: 'form-select custom-select', 'data-key': 'method' }, ['Bank transfer', 'Bank deposit', 'Cash', 'Cheque', 'Card'].map((x) => h('option', { value: x }, x)));
  const date = h('input', { class: 'form-control custom-input', type: 'date', value: new Date().toISOString().slice(0, 10), max: new Date().toISOString().slice(0, 10), 'data-key': 'date', placeholder: 'Choose the day the money arrived' });
  const ref = h('input', { class: 'form-control custom-input', placeholder: 'Enter the bank reference or cheque number', 'data-key': 'reference', 'data-limit': 'text' });
  const box = h('div', { class: 'component-theme p-4 mb-4 ad-panel', id: 'inv-panel' });
  const save = h('button', { type: 'button', class: 'custom-btn px-5', id: 'inv-pay-save', onclick: async () => {
    showErrors(box, {});
    save.disabled = true;
    try {
      await api.post(`/admin/invoices/${inv.id}/payments`, { amount: amount.value, method: method.value, date: date.value, reference: ref.value });
      Toast.success(Number(amount.value) >= inv.total - inv.paid ? 'Payment recorded. The invoice is paid and the tutor is active.' : 'Payment recorded. The invoice is part paid.');
      panel = ''; load();
    } catch (e) { save.disabled = false; showErrors(box, errs(e)); Toast.error(e.message); }
  } }, 'Save Payment');
  fill(box,
    h('div', { class: 'd-flex align-items-center gap-3 mb-3' }, h('span', { class: 'fe-form-ico' }, icon('wallet', { size: 22 })),
      h('div', {}, h('div', { class: 'text-18 main-text fw-semibold' }, 'Record a Payment'), h('div', { class: 'text-13 sub-text' }, `${inv.number} · ${lkr(inv.total - inv.paid)} left to pay`))),
    h('div', { class: 'row gx-3' },
      h('div', { class: 'col-md-6 col-xl-3' }, field('Amount (LKR)', amount, { required: true, info: 'The money received for this invoice. Less than what is left keeps the invoice Part Paid.' })),
      h('div', { class: 'col-md-6 col-xl-3' }, field('Paid on', date, { required: true, info: 'The day the money reached ENLIXO, for example the date on the bank slip.' })),
      h('div', { class: 'col-md-6 col-xl-3' }, field('Method', method, { required: true })),
      h('div', { class: 'col-md-6 col-xl-3' }, field('Reference', ref, { info: 'Bank reference, cheque number or receipt book number, so finance can find it later. For example 004512.' }))),
    h('div', { class: 'd-flex justify-content-end gap-2' }, h('button', { type: 'button', class: 'custom-btn px-4', onclick: () => { panel = ''; draw(); } }, 'Cancel'), save));
  return box;
}

function voidPanel(inv) {
  const reason = h('textarea', { class: 'form-control custom-input', rows: 3, placeholder: 'Say why this invoice is cancelled', 'data-key': 'reason', 'data-limit': '200' });
  const box = h('div', { class: 'component-theme p-4 mb-4 ad-panel is-danger', id: 'inv-panel' });
  const go = h('button', { type: 'button', class: 'custom-danger-btn custom-btn px-5', onclick: async () => {
    showErrors(box, {});
    if (!reason.value.trim()) { showErrors(box, { reason: 'Give a reason. It is kept with the invoice.' }); return; }
    go.disabled = true;
    try { await api.post(`/admin/invoices/${inv.id}/void`, { reason: reason.value }); Toast.success('Invoice void.'); panel = ''; load(); } catch (e) { go.disabled = false; showErrors(box, errs(e)); Toast.error(e.message); }
  } }, 'Void Invoice');
  fill(box,
    h('div', { class: 'd-flex align-items-center gap-3 mb-3' }, h('span', { class: 'fe-reject-ico' }, icon('danger', { size: 18 })),
      h('div', {}, h('div', { class: 'text-18 main-text fw-semibold' }, 'Void this invoice?'), h('div', { class: 'text-13 sub-text' }, 'A void invoice stays in the list with its reason. The tutor no longer has to pay it.'))),
    field('Reason', reason, { required: true }),
    h('div', { class: 'd-flex justify-content-end gap-2' }, h('button', { type: 'button', class: 'custom-btn px-4', onclick: () => { panel = ''; draw(); } }, 'Cancel'), go));
  return box;
}
