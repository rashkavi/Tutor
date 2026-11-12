// Admin → Tutor details (admin/tutor-details.php?tutor-id=). Profile card | subscription card + tabs (Invoices, Payments, History).
// Data: GET /admin/tutors/{id}; plan actions: POST /admin/tutors/{id}/{extend|plan|packs|price|suspend|reactivate|cancel|remind}.
import { QuickGuide } from '../components/QuickGuide.js';
import { ActionMenu } from '../components/Popover.js';
import { Tabs } from '../components/Tabs.js';
import {
  api, h, fill, icon, lkr, day, inDays, plural, chip, cell, facts, simpleTable, subscriptionCard, invoiceColumns, paymentColumns,
  previewNote, field, BsModal, Toast, EmptyState, invoiceDialog, paymentDialog, sendReminder, trySave,
} from './admin-common.js';

QuickGuide.attachAll();
const root = document.getElementById('tutor-details');
const params = new URLSearchParams(location.search);
const id = Number(params.get('tutor-id') ?? 0);
let D;

/** One dialog for every plan action: fields + a reason (when needed) → POST, then reload. */
function act(action, { title, sub, ico = 'edit', tone = '', intro, fields = [], reason = true, yes = 'Save', danger = false, done }) {
  const els = {};
  const body = h('div', {}, intro ? h('p', { class: 'text-14 sub-text' }, intro) : null,
    fields.map((f) => { els[f.key] = f.el; f.el.dataset.key = f.key; return field(f.label, f.el, { required: f.required, info: f.info }); }),
    reason ? field('Reason', (els.reason = h('textarea', { class: 'form-control custom-input', rows: 2, placeholder: 'Say why. It is kept in the tutor\'s history', 'data-key': 'reason', 'data-limit': '200' })), { required: true }) : null);
  const m = BsModal.open({ title, subtitle: [D.tutor.name, sub ?? `${D.plan.name} plan`], icon: ico, tone, body, footerEnd: [
    BsModal.button('Cancel', { onclick: () => m.close() }),
    BsModal.button(yes, { primary: true, danger, onclick: async () => {
      const payload = Object.fromEntries(Object.entries(els).map(([k, el]) => [k, el.value]));
      const res = await trySave(m.el, () => api.post(`/admin/tutors/${id}/${action}`, payload));
      if (!res) return;
      m.close(); Toast.success(done(payload, res.data)); load();
    } }),
  ] });
}
const num = (value, attrs = {}) => h('input', { class: 'form-control custom-input', type: 'number', value, ...attrs });
const sel = (opts, value) => { const s = h('select', { class: 'form-select custom-select' }, opts.map(([v, t]) => h('option', { value: v }, t))); s.value = value; return s; };

const actions = {
  extend: () => act('extend', { title: 'Extend the Free Trial', ico: 'hourglass', sub: `Trial ends ${day(D.tutor.trial_ends)}`, intro: 'Give the tutor more days to try ENLIXO Tutor. Use it once, for example when they started late or are waiting for a bank transfer.',
    fields: [{ key: 'days', label: 'Extra days', required: true, el: num(7, { min: 1, max: 30, placeholder: 'Enter the number of extra days' }), info: 'Days added to the current trial end date. Up to 30.' }], yes: 'Extend Trial', done: (p) => `Trial extended by ${plural(Number(p.days), 'day')}.` }),
  plan: () => act('plan', { title: 'Change Plan', ico: 'plans', intro: 'Upgrade: starts today; the tutor pays the difference for the days left in this period. Downgrade: starts at the next renewal, and only when the students fit the new limit.',
    fields: [
      { key: 'plan', label: 'New plan', required: true, el: sel(D.plans.map((p) => [p.key, `${p.name}, ${lkr(p.monthly)} a month, up to ${p.limit} students`]), D.tutor.plan) },
      { key: 'cycle', label: 'Billing', required: true, el: sel([['monthly', 'Monthly'], ['yearly', 'Yearly']], D.tutor.cycle) },
    ], reason: false, yes: 'Change Plan', done: (p, r) => r.message }),
  packs: () => act('packs', { title: 'Extra Students', ico: 'students', intro: `Each pack adds 50 students for ${lkr(D.plan.pack_price ?? 0)} a month, from the next invoice.`,
    fields: [{ key: 'packs', label: 'Packs of 50', required: true, el: num(D.tutor.extra_packs, { min: 0, max: 20, placeholder: 'Enter the number of packs' }), info: `0 = only the ${D.plan.limit} students of the plan. For example 2 = ${D.plan.limit + 100} students.` }], reason: false, yes: 'Save', done: (p) => `Extra students set to ${p.packs} × 50.` }),
  price: () => act('price', { title: 'Custom Price', ico: 'price', intro: 'This tutor pays this price instead of the plan price, from the next invoice. Leave it empty to go back to the plan price.',
    fields: [{ key: 'price', label: 'Monthly price (LKR)', el: num(D.tutor.custom_price ? D.tutor.price : '', { min: 0, placeholder: `Plan price is ${lkr(D.plan.monthly)}` }) }], yes: 'Save Price', done: () => 'Custom price saved.' }),
  suspend: () => act('suspend', { title: 'Suspend this Tutor?', ico: 'pause', tone: 'danger', intro: 'The tutor can still sign in, see Billing and pay. Everything else is paused and students see "This class is paused".', yes: 'Suspend', danger: true, done: () => 'Tutor suspended.' }),
  reactivate: () => act('reactivate', { title: 'Reactivate this Tutor?', ico: 'play', tone: 'success', intro: `Classes and student access switch back on at once. Open invoices stay open, and the tutor gets ${plural(D.rules.grace_days, 'more day')} to pay before the system pauses the account again.`, yes: 'Reactivate', done: () => 'Tutor reactivated.' }),
  cancel: () => act('cancel', { title: 'Cancel the Subscription?', ico: 'stop', tone: 'danger', intro: 'At the end of the period: the tutor keeps access until the paid period ends. Now: access stops today and the open invoice is void.',
    fields: [{ key: 'when', label: 'When', required: true, el: sel([['end', `At the end of the period (${day(D.tutor.period_end)})`], ['now', 'Now']], 'end') }], yes: 'Cancel Subscription', danger: true, done: () => 'Subscription cancelled.' }),
};

function profile(t) {
  const row = (ico, text, href) => h(href ? 'a' : 'div', { class: 'ad-contact text-decoration-none', href }, icon(ico, { size: 18 }), h('span', { class: 'main-text' }, text));
  return h('div', { class: 'component-theme p-4 h-100' },
    h('div', { class: 'ad-profile-cover' }),
    h('div', { class: 'text-center' },
      h('span', { class: 'ad-profile-avatar' }, `${t.first_name[0]}${t.last_name[0]}`),
      h('div', { class: 'main-topic mt-2' }, `${t.title} ${t.name}`),
      h('div', { class: 'text-14 sub-text' }, t.brand || 'No institute name'),
      h('div', { class: 'd-flex justify-content-center gap-2 mt-2' }, h('span', { class: 'ad-tag' }, `ID T-${t.id}`), chip(t.status))),
    h('div', { class: 'mt-3' },
      row('mail', t.email, `mailto:${t.email}`),
      row('phone', t.mobile, `tel:${t.mobile}`),
      t.mobile ? row('whatsapp', 'Message on WhatsApp', `https://wa.me/94${(t.whatsapp || t.mobile).replace(/\D/g, '').replace(/^(94|0)/, '')}`) : null,
      t.subject ? row('subject', t.subject) : null),
    h('div', { class: 'mt-3' }, facts([['Joined', day(t.joined)], ['Last sign-in', t.last_login ? day(t.last_login) : 'Not yet'], ['Students', `${t.students} active`], ['Balance', t.balance ? lkr(t.balance) : 'Nothing due'], t.note ? ['Team note', t.note, true] : null])));
}

function subscription() {
  const t = D.tutor;
  const items = [
    { label: 'Change Plan', icon: 'plans', onClick: actions.plan },
    t.status === 'trial' ? { label: 'Extend Trial', icon: 'hourglass', onClick: actions.extend } : null,
    D.plan.pack_price ? { label: 'Extra Students', icon: 'students', onClick: actions.packs } : null,
    { label: 'Custom Price', icon: 'price', onClick: actions.price },
    t.balance ? { label: 'Send Payment Reminder', icon: 'send', onClick: () => sendReminder(id) } : null,
    ['suspended', 'cancelled'].includes(t.status) ? { label: 'Reactivate', icon: 'play', onClick: actions.reactivate } : { label: 'Suspend', icon: 'pause', danger: true, onClick: actions.suspend },
    t.status !== 'cancelled' && !t.cancel_at_period_end ? { label: 'Cancel Subscription', icon: 'stop', danger: true, onClick: actions.cancel } : null,
  ];
  return subscriptionCard(t, D.plan, { actions: ActionMenu.button('Manage Plan', items) });
}

function money() {
  const paid = D.payments.filter((p) => p.status === 'approved');
  const year = new Date().getFullYear().toString();
  const last = paid[0];
  const next = D.invoices.find((i) => ['open', 'overdue'].includes(i.status));
  const tile = (ico, label, value, sub) => h('div', { class: 'col-6 col-lg-3' }, h('div', { class: 'ad-soft p-3 h-100' },
    h('div', { class: 'd-flex align-items-center gap-2 text-12 sub-text' }, icon(ico, { size: 16 }), label),
    h('div', { class: 'text-16 main-text fw-semibold mt-1' }, value), h('div', { class: 'text-12 sub-text' }, sub)));
  return h('div', { class: 'component-theme p-4 mt-4 flex-grow-1' },
    h('div', { class: 'sub-topic mb-3' }, 'Money'),
    h('div', { class: 'row g-3' },
      tile('wallet', `Paid in ${year}`, lkr(paid.filter((p) => p.date.startsWith(year)).reduce((a, p) => a + p.amount, 0)), plural(paid.filter((p) => p.date.startsWith(year)).length, 'payment')),
      tile('receipt', 'Last payment', last ? lkr(last.amount) : '-', last ? `${day(last.date)} · ${last.method}` : 'none yet'),
      tile('invoice', 'To pay now', next ? lkr(next.total) : lkr(0), next ? `due ${day(next.due)}` : 'nothing open'),
      tile('calendar', 'Customer for', plural(Math.max(0, Math.round((new Date() - new Date(D.tutor.joined)) / 2629800000)), 'month'), `since ${day(D.tutor.joined)}`)));
}

function historyList() {
  return D.history.length
    ? h('ul', { class: 'ad-timeline mt-2' }, D.history.map((x) => h('li', {},
      h('div', { class: 'text-14 main-text fw-medium' }, x.what),
      h('div', { class: 'text-12 sub-text' }, `${x.when} · ${x.by}`),
      x.reason ? h('div', { class: 'text-13 main-text mt-1' }, `Reason: ${x.reason}`) : null)))
    : EmptyState.empty({ title: 'No changes yet', text: 'Plan changes, trials, payments and reminders appear here.' });
}

function tabs() {
  const card = h('div', { class: 'col-12 component-theme p-4 mt-4' });
  new Tabs({ tabs: [
    { id: 'invoices', label: 'Invoices', badge: D.invoices.length, render: (p) => p.append(simpleTable(invoiceColumns({ admin: true, open: (r) => invoiceDialog(r, { admin: true, reload: load }) }).filter((c) => c.key !== 'tutor'), D.invoices, { title: 'No invoices yet', text: 'Invoices appear here when the trial ends or a paid period starts.' })) },
    { id: 'payments', label: 'Payments', badge: D.payments.length, render: (p) => p.append(simpleTable(paymentColumns({ admin: true, reload: load }).filter((c) => c.key !== 'tutor'), D.payments, { title: 'No payments yet', text: 'Slips the tutor uploads and payments you record appear here.' })) },
    { id: 'history', label: 'History', badge: D.history.length, render: (p) => p.append(historyList()) },
  ] }).mount(card);
  return card;
}

async function load() {
  if (!id) { fill(root, EmptyState.empty({ title: 'No tutor chosen', text: 'Open a tutor from All Tutors.' })); return; }
  try {
    ({ data: D } = await api.get(`/admin/tutors/${id}`));
  } catch (e) { fill(root, EmptyState.error(e.message, load)); return; }
  const t = D.tutor;
  document.title = `${t.name} - Admin`;
  const open = D.invoices.find((i) => ['open', 'overdue'].includes(i.status));
  const waiting = D.payments.find((p) => p.status === 'waiting');
  fill(document.getElementById('tutor-actions'),
    waiting ? h('button', { type: 'button', class: 'custom-btn px-4', onclick: () => paymentDialog(waiting, { admin: true, reload: load }) }, 'Check Payment Slip')
      : open ? h('button', { type: 'button', class: 'custom-btn px-4', onclick: () => invoiceDialog(open, { admin: true, reload: load }) }, 'Record Payment') : null);
  fill(root, previewNote(),
    h('div', { class: 'row g-4' },
      h('div', { class: 'col-12 col-xl-4' }, profile(t)),
      h('div', { class: 'col-12 col-xl-8 d-flex flex-column' }, subscription(),
        open ? h('div', { class: `ad-hero mt-4 mb-0 ${open.status === 'overdue' ? 'ad-hero-red' : ''}` },
          h('div', {}, h('div', { class: 'text-13 sub-text' }, `${open.number} · ${open.status === 'overdue' ? 'overdue' : 'open'}`), h('div', { class: 'ad-hero-amount' }, lkr(open.total)), h('div', { class: 'text-13 sub-text mt-1' }, `Due ${day(open.due)}, ${inDays(open.due)}${waiting ? '. A slip is waiting for you to check.' : ''}`)),
          h('button', { type: 'button', class: 'custom-btn-outline px-4', onclick: () => invoiceDialog(open, { admin: true, reload: load }) }, 'View Invoice')) : null,
        money())),
    tabs());
  if (params.get('do') === 'extend' && t.status === 'trial') { params.delete('do'); window.history.replaceState(null, '', `?${params}`); actions.extend(); }
}
load();
