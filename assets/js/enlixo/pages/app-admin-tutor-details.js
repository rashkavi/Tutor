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

/** Every plan action is its own page now: admin/tutor-plan.php?tutor-id=&do=<action>. */
const go = (action) => () => { location.href = `tutor-plan?tutor-id=${id}&do=${action}`; };
const actions = { trial: go('trial'), extend: go('extend'), plan: go('plan'), packs: go('packs'), price: go('price'), suspend: go('suspend'), reactivate: go('reactivate'), cancel: go('cancel') };

/** Right after Add Tutor / Approve: the welcome went out (or not) and the set-password link to copy or WhatsApp. */
function welcomeBanner() {
  let w = null;
  try { w = JSON.parse(sessionStorage.getItem(`enlixo-welcome-${id}`) || 'null'); } catch { w = null; }
  if (!w?.link) return null;
  const close = () => { try { sessionStorage.removeItem(`enlixo-welcome-${id}`); } catch { /* ignore */ } box.remove(); };
  const wa = `https://wa.me/94${String(w.whatsapp ?? '').replace(/\D/g, '').replace(/^(94|0)/, '')}?text=${encodeURIComponent(`Hi ${w.name}, your ENLIXO Tutor account is ready. Set your password here: ${w.link}`)}`;
  const box = h('div', { class: `col-12 ad-welcome ${w.sent ? 'is-ok' : 'is-warn'} mb-4` },
    h('span', { class: 'ad-welcome-ico' }, icon(w.sent ? 'check' : 'warning', { size: 22 })),
    h('div', { class: 'min-w-0 flex-grow-1' },
      h('strong', {}, w.sent ? `Welcome e-mail sent to ${w.email}` : `The welcome e-mail could not be sent to ${w.email}`),
      h('small', {}, w.sent ? 'It has the "Set my password" link (works for 7 days). You can also send the link on WhatsApp.' : 'Send the "Set my password" link on WhatsApp instead (works for 7 days).'),
      h('code', {}, w.link)),
    h('div', { class: 'd-flex gap-2 flex-wrap' },
      h('button', { type: 'button', class: 'custom-btn px-3', onclick: async () => { try { await navigator.clipboard.writeText(w.link); Toast.success('Link copied.'); } catch { Toast.error('Select the link and copy it.'); } } }, icon('copy', { size: 16 }), 'Copy Link'),
      h('a', { class: 'custom-btn px-3 text-decoration-none', href: wa, target: '_blank', rel: 'noopener' }, icon('whatsapp', { size: 16 }), 'Send on WhatsApp'),
      h('button', { type: 'button', class: 'custom-btn px-3', onclick: close }, 'Done')));
  return box;
}

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
    { label: 'Give a Free Trial', icon: 'gift', onClick: actions.trial },
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
    { id: 'invoices', label: 'Invoices', badge: D.invoices.length, render: (p) => p.append(simpleTable(invoiceColumns({ admin: true, open: (r) => invoiceDialog(r) }).filter((c) => c.key !== 'tutor'), D.invoices, { title: 'No invoices yet', text: 'Invoices appear here when the trial ends or a paid period starts.' })) },
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
      : open ? h('button', { type: 'button', class: 'custom-btn px-4', onclick: () => { location.href = `invoice?invoice-id=${open.id}&do=pay`; } }, 'Record Payment') : null);
  fill(root, previewNote(), welcomeBanner(),
    h('div', { class: 'row g-4' },
      h('div', { class: 'col-12 col-xl-4' }, profile(t)),
      h('div', { class: 'col-12 col-xl-8 d-flex flex-column' }, subscription(),
        open ? h('div', { class: `ad-hero mt-4 mb-0 ${open.status === 'overdue' ? 'ad-hero-red' : ''}` },
          h('div', {}, h('div', { class: 'text-13 sub-text' }, `${open.number} · ${open.status === 'overdue' ? 'overdue' : 'open'}`), h('div', { class: 'ad-hero-amount' }, lkr(open.total)), h('div', { class: 'text-13 sub-text mt-1' }, `Due ${day(open.due)}, ${inDays(open.due)}${waiting ? '. A slip is waiting for you to check.' : ''}`)),
          h('button', { type: 'button', class: 'custom-btn-outline px-4', onclick: () => invoiceDialog(open) }, 'View Invoice')) : null,
        money())),
    tabs());
  if (params.get('do') === 'extend' && t.status === 'trial') { actions.extend(); }
}
load();
