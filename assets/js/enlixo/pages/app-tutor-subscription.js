// Tutor → Finance → My Subscription (tutor/subscription.php). The tutor's side of the admin billing:
// plan card, the open invoice with how to pay (bank slip upload), Invoices and Payment History tabs.
// Data: GET /tutor/subscription; slip: POST /tutor/subscription/payments (multipart). Same cards and columns as the admin pages.
import { QuickGuide } from '../components/QuickGuide.js';
import { Tabs } from '../components/Tabs.js';
import {
  api, h, fill, icon, lkr, day, inDays, plural, chip, cell, facts, hero, simpleTable, subscriptionCard, invoiceColumns, paymentColumns,
  invoiceDialog, previewNote, field, showErrors, trySave, BsModal, Toast, EmptyState,
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
  const banks = D.banks.length
    ? h('div', { class: 'row g-3 mb-3' }, D.banks.map((b) => h('div', { class: 'col-md-6' }, h('div', { class: 'ad-soft p-3 h-100' },
      h('div', { class: 'text-14 main-text fw-semibold' }, b.bank), h('div', { class: 'text-12 sub-text' }, `${b.branch} branch`),
      h('div', { class: 'text-16 main-text fw-semibold mt-2' }, b.number), h('div', { class: 'text-12 sub-text' }, b.name)))))
    : h('div', { class: 'ad-soft p-3 mb-3 text-14 main-text' }, 'ENLIXO has not added its bank account here yet. Please call ENLIXO for the account number.');
  const toPay = inv.total - inv.paid;
  const body = h('div', {},
    hero('Amount to pay', lkr(toPay), chip(inv.status), `${inv.number} · due ${day(inv.due)}`),
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
      if (file.files[0].size > 5 * 1024 * 1024) { showErrors(m.el, { slip: 'The file is larger than 5 MB. Take a smaller photo or scan.' }); return; }
      const form = new FormData();
      form.append('invoice', inv.id); form.append('date', date.value); form.append('reference', ref.value); form.append('slip', file.files[0]);
      if (!await trySave(m.el, () => api.post('/tutor/subscription/payments', form))) return;
      m.close();
      Toast.success('Slip sent. ENLIXO checks it within one working day; your account stays on while it is checked.'); load();
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
      const res = await trySave(m.el, () => api.post('/tutor/subscription/change', { plan: p.key }));
      if (!res) return;
      m.close(); Toast.success(res.data.message); load();
    } }, p.monthly > D.plan.monthly ? `Upgrade to ${p.name}` : `Move to ${p.name}`) : null))));
  const m = BsModal.open({ title: 'Change Plan', subtitle: D.tutor.status === 'trial' ? ['Choose the plan you pay for after the trial'] : ['Upgrades start today', 'Downgrades at the next renewal'], icon: 'plans', body: cards, size: 'xl', footerEnd: [BsModal.button('Close', { onclick: () => m.close() })] });
}

async function load() {
  try { ({ data: D } = await api.get('/tutor/subscription')); } catch (e) { fill(root, EmptyState.error(e.message, load)); return; }
  const open = [...D.invoices].reverse().find((i) => ['open', 'overdue', 'part'].includes(i.status));
  const waiting = open && D.payments.find((p) => p.status === 'waiting' && p.invoice_id === open.id);
  const rejected = open && !waiting && D.payments.find((p) => p.status === 'rejected' && p.invoice_id === open.id);
  const locked = ['suspended', 'cancelled'].includes(D.tutor.status);
  fill(document.getElementById('subscription-actions'),
    h('button', { type: 'button', class: 'custom-btn-outline px-4', onclick: plansDialog }, 'Change Plan'),
    open && !waiting ? h('button', { type: 'button', class: 'custom-btn px-4', onclick: () => payDialog(open) }, 'Pay Now') : null);
  const tabs = h('div', { class: 'col-12 component-theme p-4 mt-4' });
  new Tabs({ tabs: [
    { id: 'invoices', label: 'Invoices', badge: D.invoices.length, render: (p) => p.append(simpleTable(invoiceColumns({ admin: false, open: (r) => invoiceDialog(r, { onPay: payDialog }) }), D.invoices, { title: 'No invoices yet', text: 'Your first invoice appears here when your free trial ends.' })) },
    { id: 'payments', label: 'Payment History', badge: D.payments.length, render: (p) => p.append(simpleTable(paymentColumns({ admin: false }), D.payments, { title: 'No payments yet', text: 'Slips you upload appear here with their status.' })) },
  ] }).mount(tabs);
  const paid = D.payments.filter((p) => p.status === 'approved');
  const heroText = waiting ? `Sent ${day(waiting.date)}. Your account keeps working while ENLIXO checks it.`
    : rejected ? `Your last slip was not accepted: ${rejected.reason}. Please upload the right slip.`
      : locked ? 'Your account is paused. Pay this invoice and everything switches back on as soon as ENLIXO approves the slip.'
        : `Due ${day(open?.due)} (${inDays(open?.due)}). Pay to keep your classes running without a break.`;
  fill(root, previewNote(),
    locked && !open ? h('div', { class: 'ad-hero ad-hero-red mb-4' }, h('div', {}, h('div', { class: 'text-13 sub-text' }, D.tutor.status === 'cancelled' ? 'Your subscription has ended' : 'Your account is paused by ENLIXO'), h('div', { class: 'text-14 main-text mt-1' }, D.tutor.status === 'cancelled' ? 'Call ENLIXO to start it again. Your classes and students are kept.' : 'There is no invoice to pay. Call ENLIXO to switch it back on. Your classes and students are kept.'))) : null,
    open ? h('div', { class: `ad-hero ${open.status === 'overdue' || locked || rejected ? 'ad-hero-red' : ''} mb-4` },
      h('div', {}, h('div', { class: 'text-13 sub-text' }, waiting ? 'Slip sent, ENLIXO is checking it' : `Invoice ${open.number} is ${open.status === 'overdue' ? 'overdue' : open.status === 'part' ? 'part paid' : 'open'}`),
        h('div', { class: 'ad-hero-amount' }, lkr(open.total - open.paid)),
        h('div', { class: 'text-13 sub-text mt-1' }, heroText)),
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
