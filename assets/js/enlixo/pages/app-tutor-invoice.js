// Tutor → one invoice page (tutor/invoice.php?invoice-id=): the invoice on the left, "Pay this invoice" on the right
// while it is open (bank accounts, slip upload, paid on, reference). Replaces the Pay Your Invoice / invoice pop-ups.
import { QuickGuide } from '../components/QuickGuide.js';
import { DetailPage } from '../components/DetailPage.js';
import { api, h, fill, icon, lkr, day, inDays, chip, facts, hero, previewNote, field, showErrors, trySave, Toast } from './admin-common.js';

QuickGuide.attachAll();
const root = document.getElementById('inv-page');
const actions = document.getElementById('inv-actions');
const id = Number(new URLSearchParams(location.search).get('invoice-id') || 0);
fill(root, DetailPage.loading());
load();

async function load() {
  let D;
  try { ({ data: D } = await api.get('/tutor/subscription')); } catch (e) { fill(root, DetailPage.notFound(`Couldn't load the invoice: ${e.message}`, ['Back to My Subscription', 'subscription'])); return; }
  const inv = D.invoices.find((x) => Number(x.id) === id);
  if (!inv) { fill(root, DetailPage.notFound('This invoice was not found.', ['Back to My Subscription', 'subscription'])); return; }
  document.title = `${inv.number} - Tutor`;
  const open = ['open', 'overdue', 'part'].includes(inv.status);
  const waiting = D.payments.some((p) => p.status === 'waiting' && p.invoice_id === inv.id);

  const lines = h('div', { class: 'ad-lines' },
    inv.lines.map((l) => h('div', { class: 'ad-line' }, h('span', {}, l.text), h('span', {}, lkr(l.amount)))),
    inv.tax ? h('div', { class: 'ad-line' }, h('span', {}, 'Tax'), h('span', {}, lkr(inv.tax))) : null,
    h('div', { class: 'ad-line ad-line-total' }, h('span', {}, 'Total'), h('span', {}, lkr(inv.total))),
    inv.paid ? h('div', { class: 'ad-line' }, h('span', {}, 'Paid'), h('span', {}, lkr(inv.paid))) : null);
  const invoiceCard = DetailPage.card(inv.number, [
    hero('Invoice total', lkr(inv.total), chip(inv.status), inv.status === 'paid' ? 'Paid in full' : inv.status === 'void' ? `Void: ${inv.void_reason}` : `Due ${day(inv.due)} (${inDays(inv.due)})`),
    facts([['Plan', inv.plan], ['Period', `${day(inv.period_from)} - ${day(inv.period_to)}`, true], ['Issued', day(inv.issued)], ['Due date', day(inv.due)]]),
    h('div', { class: 'text-15 fw-semibold main-text mb-2' }, 'What it is for'), lines,
  ]);

  let pay = null;
  if (open && waiting) {
    pay = DetailPage.card('Payment', h('div', { class: 'ex-panel-soft p-3 text-14' }, icon('clock', { size: 16 }), ' Your slip for this invoice is being checked by ENLIXO (within one working day). Your account stays on.'));
  } else if (open) {
    pay = payCard(D, inv);
  }
  fill(actions,
    h('button', { type: 'button', class: 'custom-outline-btn ex-btn-lg px-4 d-inline-flex align-items-center gap-2', onclick: () => window.print() }, icon('printer', { size: 18 }), 'Print'),
    open && !waiting ? h('a', { class: 'custom-btn ex-btn-lg px-5 text-decoration-none', href: '#pay', onclick: (e) => { e.preventDefault(); document.getElementById('pay')?.scrollIntoView({ behavior: 'smooth' }); } }, 'Pay Now')
      : h('a', { class: 'custom-btn ex-btn-lg px-5 text-decoration-none', href: 'subscription' }, 'My Subscription'));
  fill(root, previewNote(), h('div', { class: 'row g-4' },
    h('div', { class: pay ? 'col-12 col-xl-6' : 'col-12' }, invoiceCard),
    pay ? h('div', { class: 'col-12 col-xl-6', id: 'pay' }, pay) : null));
  if (location.hash === '#pay') setTimeout(() => document.getElementById('pay')?.scrollIntoView({ behavior: 'smooth' }), 100);
}

function payCard(D, inv) {
  const file = h('input', { type: 'file', class: 'd-none', accept: '.jpg,.jpeg,.png,.pdf', 'data-key': 'slip', id: 'slip-file' });
  const chosen = h('div', { class: 'text-14 main-text fw-medium mt-2' });
  const drop = h('label', { class: 'ad-slip w-100', style: { minHeight: '150px', cursor: 'pointer' } }, icon('upload', { size: 32 }),
    h('div', { class: 'text-14 main-text mt-2' }, 'Drag and drop the slip here or ', h('span', { class: 'dark-theme fw-semibold' }, 'Browse File')),
    h('div', { class: 'text-12 sub-text' }, 'JPG, PNG or PDF, up to 5 MB'), chosen);
  drop.addEventListener('click', () => file.click());
  drop.addEventListener('dragover', (e) => e.preventDefault());
  drop.addEventListener('drop', (e) => { e.preventDefault(); file.files = e.dataTransfer.files; file.dispatchEvent(new Event('change')); });
  file.addEventListener('change', () => { chosen.textContent = file.files[0] ? `${file.files[0].name} · ready to upload` : ''; });
  const date = h('input', { type: 'date', class: 'form-control custom-input', value: new Date().toISOString().slice(0, 10), placeholder: 'Choose the day you paid', 'data-key': 'date' });
  const ref = h('input', { class: 'form-control custom-input', placeholder: 'Enter the reference on the slip', 'data-key': 'reference', 'data-limit': '60' });
  const banks = D.banks.length
    ? h('div', { class: 'row g-3 mb-3' }, D.banks.map((b) => h('div', { class: 'col-md-6' }, h('div', { class: 'ad-soft p-3 h-100' },
      h('div', { class: 'text-14 main-text fw-semibold' }, b.bank), h('div', { class: 'text-12 sub-text' }, `${b.branch} branch`),
      h('div', { class: 'text-16 main-text fw-semibold mt-2' }, b.number), h('div', { class: 'text-12 sub-text' }, b.name)))))
    : h('div', { class: 'ad-soft p-3 mb-3 text-14 main-text' }, 'ENLIXO has not added its bank account here yet. Please call ENLIXO for the account number.');
  const card = h('div', {});
  const send = async (btn) => {
    if (!file.files[0]) { showErrors(card, { slip: 'Choose the photo or PDF of your bank slip.' }); return; }
    if (file.files[0].size > 5 * 1024 * 1024) { showErrors(card, { slip: 'The file is larger than 5 MB. Take a smaller photo or scan.' }); return; }
    const form = new FormData();
    form.append('invoice', inv.id); form.append('date', date.value); form.append('reference', ref.value); form.append('slip', file.files[0]);
    btn.disabled = true;
    if (!await trySave(card, () => api.post('/tutor/subscription/payments', form))) { btn.disabled = false; return; }
    Toast.success('Slip sent. ENLIXO checks it within one working day; your account stays on while it is checked.');
    setTimeout(() => { location.href = 'subscription'; }, 900);
  };
  fill(card,
    hero('Amount to pay', lkr(inv.total - inv.paid), chip(inv.status), `${inv.number} · due ${day(inv.due)}`),
    h('div', { class: 'text-14 main-text fw-semibold mb-2' }, '1. Pay into one of these accounts'), banks,
    h('div', { class: 'text-14 main-text fw-semibold mb-2' }, '2. Upload the slip'), drop, file,
    h('div', { class: 'text-12 text-dark-red mt-1 d-none', 'data-err': 'slip-file' }),
    h('div', { class: 'row gx-3 mt-3' },
      h('div', { class: 'col-md-6' }, field('Paid on', date, { required: true })),
      h('div', { class: 'col-md-6' }, field('Slip reference', ref, { info: 'The reference or transaction number printed on the slip, so ENLIXO can find your payment quickly. For example 004512.' }))),
    h('div', { class: 'd-flex justify-content-end mt-2' }, h('button', { type: 'button', class: 'custom-btn px-5', id: 'send-slip', onclick: (e) => send(e.currentTarget) }, 'Send Slip')));
  return DetailPage.card('Pay this invoice', card);
}
