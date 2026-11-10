// Tutor → Finance → My Subscription (tutor/subscription.php). The tutor's side of the admin billing:
// plan card, the open invoice with how to pay (bank slip upload), Invoices and Payment History tabs.
// Data: GET /tutor/subscription; slip: POST /tutor/subscription/payments (multipart). Same cards and columns as the admin pages.
import { QuickGuide } from '../components/QuickGuide.js';
import { Tabs } from '../components/Tabs.js';
import {
  api, h, fill, icon, lkr, day, inDays, plural, chip, cell, facts, hero, simpleTable, subscriptionCard, invoiceColumns, paymentColumns,
  invoiceDialog, previewNote, field, showErrors, BsModal, Toast, EmptyState,
} from './admin-common.js';

QuickGuide.attachAll();
const root = document.getElementById('tutor-subscription');
let D;

function payDialog(inv) {
  const file = h('input', { type: 'file', class: 'd-none', accept: '.jpg,.jpeg,.png,.pdf', 'data-key': 'slip' });
  const chosen = h('div', { class: 'text-14 main-text fw-medium mt-2' });
  const drop = h('label', { class: 'ad-slip w-100', for: null, style: { minHeight: '150px', cursor: 'pointer' } }, icon('upload', { size: 32 }),
    h('div', { class: 'text-14 main-text mt-2' }, 'Drag and drop the slip here or ', h('span', { class: 'dark-theme fw-semibold' }, 'Browse File')),
    h('div', { class: 'text-12 sub-text' }, 'JPG, PNG or PDF, up to 5 MB'), chosen);
  drop.addEventListener('click', () => file.click());
  drop.addEventListener('dragover', (e) => e.preventDefault());
  drop.addEventListener('drop', (e) => { e.preventDefault(); file.files = e.dataTransfer.files; file.dispatchEvent(new Event('change')); });
  file.addEventListener('change', () => { chosen.textContent = file.files[0] ? `${file.files[0].name} · ready to upload` : ''; });
  const date = h('input', { type: 'date', class: 'form-control custom-input', value: new Date().toISOString().slice(0, 10), placeholder: 'Choose the day you paid', 'data-key': 'date' });
  const ref = h('input', { class: 'form-control custom-input', placeholder: 'Enter the reference on the slip', 'data-key': 'reference', 'data-limit': '60' });
  const banks = h('div', { class: 'row g-3 mb-3' }, D.banks.map((b) => h('div', { class: 'col-md-6' }, h('div', { class: 'ad-soft p-3 h-100' },
    h('div', { class: 'text-14 main-text fw-semibold' }, b.bank), h('div', { class: 'text-12 sub-text' }, `${b.branch} branch`),
    h('div', { class: 'text-16 main-text fw-semibold mt-2' }, b.number), h('div', { class: 'text-12 sub-text' }, b.name)))));
  const body = h('div', {},
    hero('Amount to pay', lkr(inv.total), chip(inv.status), `${inv.number} · due ${day(inv.due)}`),
    h('div', { class: 'text-14 main-text fw-semibold mb-2' }, '1. Pay into one of these accounts'), banks,
    h('div', { class: 'text-14 main-text fw-semibold mb-2' }, '2. Upload the slip'), drop, file,
    h('div', { class: 'text-12 text-dark-red mt-1 d-none', 'data-err': 'slip-file' }),
    h('div', { class: 'row gx-3 mt-3' },
      h('div', { class: 'col-md-6' }, field('Paid on', date, { required: true })),
      h('div', { class: 'col-md-6' }, field('Slip reference', ref, { info: 'The reference or transaction number printed on the slip, so ENLIXO can find your payment quickly. For example 004512.' }))));
  file.id = 'slip-file';
  const m = BsModal.open({ title: 'Pay Your Invoice', subtitle: [inv.number, `${D.plan.name} plan`], icon: 'wallet', body, size: 'lg', footerEnd: [
    BsModal.button('Cancel', { onclick: () => m.close() }),
    BsModal.button('Send Slip', { primary: true, onclick: async () => {
      if (!file.files[0]) { showErrors(m.el, { slip: 'Choose the photo or PDF of your bank slip.' }); return; }
      await api.post('/tutor/subscription/payments', {}); m.close();
      Toast.success('Slip sent. ENLIXO checks it within one working day and e-mails you the receipt.'); load();
    } }),
  ] });
}

function plansDialog() {
  const cards = h('div', { class: 'row g-3' }, D.plans.map((p) => h('div', { class: 'col-md-4' }, h('div', { class: 'ad-soft p-3 h-100 d-flex flex-column gap-1' },
    h('span', { class: 'main-topic' }, p.name, p.key === D.tutor.plan ? h('span', { class: 'ad-tag ms-2' }, 'Your plan') : null),
    h('span', { class: 'text-12 sub-text' }, p.tagline),
    h('span', { class: 'ad-hero-amount fs-5' }, lkr(p.monthly), h('span', { class: 'text-13 sub-text fw-normal' }, ' / month')),
    h('span', { class: 'text-13 main-text' }, `Up to ${p.limit} students`),
    h('ul', { class: 'text-12 sub-text ps-3 mb-2' }, p.features.map((f) => h('li', {}, f))),
    p.key !== D.tutor.plan ? h('button', { type: 'button', class: 'custom-btn-outline mt-auto', onclick: async () => {
      await api.post('/tutor/subscription/change', { plan: p.key }); m.close();
      Toast.success(p.limit > D.plan.limit ? `${p.name} is on. You pay only the difference for the days left this month.` : `You move to ${p.name} at your next renewal.`);
    } }, p.limit > D.plan.limit ? `Upgrade to ${p.name}` : `Move to ${p.name}`) : null))));
  const m = BsModal.open({ title: 'Change Plan', subtitle: ['Upgrades start today', 'Downgrades at the next renewal'], icon: 'plans', body: cards, size: 'xl', footerEnd: [BsModal.button('Close', { onclick: () => m.close() })] });
}

async function load() {
  try { ({ data: D } = await api.get('/tutor/subscription')); } catch (e) { fill(root, EmptyState.error(e.message, load)); return; }
  const open = D.invoices.find((i) => ['open', 'overdue'].includes(i.status));
  const waiting = D.payments.find((p) => p.status === 'waiting');
  fill(document.getElementById('subscription-actions'),
    h('button', { type: 'button', class: 'custom-btn-outline px-4', onclick: plansDialog }, 'Change Plan'),
    open && !waiting ? h('button', { type: 'button', class: 'custom-btn px-4', onclick: () => payDialog(open) }, 'Pay Now') : null);
  const tabs = h('div', { class: 'col-12 component-theme p-4 mt-4' });
  new Tabs({ tabs: [
    { id: 'invoices', label: 'Invoices', badge: D.invoices.length, render: (p) => p.append(simpleTable(invoiceColumns({ admin: false, open: (r) => invoiceDialog(r, { onPay: payDialog }) }), D.invoices, { title: 'No invoices yet', text: 'Your first invoice appears here when your free trial ends.' })) },
    { id: 'payments', label: 'Payment History', badge: D.payments.length, render: (p) => p.append(simpleTable(paymentColumns({ admin: false }), D.payments, { title: 'No payments yet', text: 'Slips you upload appear here with their status.' })) },
  ] }).mount(tabs);
  const paid = D.payments.filter((p) => p.status === 'approved');
  fill(root, previewNote(),
    open ? h('div', { class: `ad-hero ${open.status === 'overdue' ? 'ad-hero-red' : ''} mb-4` },
      h('div', {}, h('div', { class: 'text-13 sub-text' }, waiting ? 'Slip sent, ENLIXO is checking it' : `Invoice ${open.number} is ${open.status === 'overdue' ? 'overdue' : 'open'}`),
        h('div', { class: 'ad-hero-amount' }, lkr(open.total)),
        h('div', { class: 'text-13 sub-text mt-1' }, waiting ? `Sent ${day(waiting.date)}. You get the receipt by e-mail once it is approved.` : `Due ${day(open.due)} (${inDays(open.due)}). Pay to keep your classes running without a break.`)),
      waiting ? chip('waiting', 'Being checked') : h('button', { type: 'button', class: 'custom-btn px-4', onclick: () => payDialog(open) }, 'Pay Now')) : null,
    h('div', { class: 'row g-4' },
      h('div', { class: 'col-12 col-xl-8' }, subscriptionCard(D.tutor, D.plan, { forTutor: true })),
      h('div', { class: 'col-12 col-xl-4' }, h('div', { class: 'component-theme p-4 h-100' },
        h('div', { class: 'sub-topic mb-3' }, 'Your Payments'),
        facts([
          [`Paid in ${new Date().getFullYear()}`, lkr(paid.filter((p) => p.date.startsWith(String(new Date().getFullYear()))).reduce((a, p) => a + p.amount, 0)), true],
          ['Last payment', paid[0] ? `${lkr(paid[0].amount)}, ${day(paid[0].date)}` : 'None yet', true],
          ['Next invoice', D.tutor.period_end ? day(D.tutor.period_end) : '-', true],
        ]),
        h('a', { class: 'text-14 dark-theme text-decoration-none', href: '?tab=payments' }, 'See all payments')))),
    tabs);
}
load();
