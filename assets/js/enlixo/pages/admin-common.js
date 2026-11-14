// Shared parts of the Admin side (docs/ADMIN-PLAN.md) and the tutor's own Subscription page:
// status chips, money and dates, panels, the subscription card, invoice and payment dialogs, table columns.
// Both sides draw billing with these, so a tutor sees exactly what the admin sees.
import { h, fill } from '../core/dom.js';
import { ApiClient, DEFAULT_API_BASE } from '../core/ApiClient.js';
import { icon } from '../core/icons.js';
import { BsModal } from '../components/BsModal.js';
import { Toast } from '../components/Toast.js';
import { infoTip } from '../components/InfoTip.js';
import { EmptyState } from '../components/EmptyState.js';

// Real data from /api/v1 (src/Platform). PREVIEW stays for pages that still want the grey sample-data strip.
export const PREVIEW = false;
export const api = new ApiClient();

export const lkr = (n) => `LKR ${Math.round(Number(n) || 0).toLocaleString('en-LK')}`;
export const short = (n) => (n >= 1e6 ? `${(n / 1e6).toFixed(1)}M` : n >= 1e3 ? `${Math.round(n / 1e3)}K` : String(n));
export const day = (d) => (d ? new Date(`${d}T00:00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '-');
export const daysTo = (d) => Math.round((new Date(`${d}T00:00:00`) - new Date(new Date().toDateString())) / 86400000);
export const plural = (n, one, many = `${one}s`) => `${n} ${n === 1 ? one : many}`;
export const inDays = (d) => { const n = daysTo(d); return n === 0 ? 'today' : n > 0 ? `in ${plural(n, 'day')}` : `${plural(-n, 'day')} ago`; };

// One colour per state, V3 status chips (table-btn table-btn-<colour>)
const TONES = {
  trial: ['primary', 'Trial'], active: ['success', 'Active'], due: ['orange', 'Payment Due'], overdue: ['danger', 'Overdue'],
  suspended: ['danger', 'Suspended'], cancelled: ['dark', 'Cancelled'], complimentary: ['purple', 'Complimentary'],
  paid: ['success', 'Paid'], open: ['orange', 'Open'], part: ['yellow', 'Part Paid'], void: ['dark', 'Void'],
  waiting: ['orange', 'Waiting'], approved: ['success', 'Approved'], rejected: ['danger', 'Rejected'],
};
export const chip = (status, text = null) => {
  const [tone, label] = TONES[status] ?? ['primary', status];
  return h('span', { class: `table-btn table-btn-${tone === 'dark' ? 'grey ad-chip-grey' : tone} ad-chip` }, text ?? label);
};

export const STATUS_OPTIONS = ['trial', 'active', 'due', 'overdue', 'suspended', 'complimentary', 'cancelled'].map((v) => ({ value: v, label: TONES[v][1] }));
export const PLAN_OPTIONS = [{ value: 'start', label: 'START' }, { value: 'grow', label: 'GROW' }, { value: 'pro', label: 'PRO' }];

/** Grey strip under the header while the pages run on sample data. */
export const previewNote = () => (PREVIEW
  ? h('div', { class: 'col-12 ad-preview mb-4' }, icon('info', { size: 18 }),
    h('span', {}, h('b', {}, 'Preview with sample data. '), 'Buttons work, but nothing is saved yet. Real tutors and payments come in the next step.'))
  : null);

/** V3 dashboard panel: blue title + (i), optional link on the right, body. */
export const panel = (title, info, body, { link = null, cls = '' } = {}) => h('div', { class: `component-theme p-4 h-100 d-flex flex-column ${cls}` },
  h('div', { class: 'd-flex justify-content-between align-items-center gap-2 mb-3' },
    h('span', { class: 'sub-topic d-inline-flex align-items-center gap-2' }, title, info ? infoTip(info, title) : null),
    link ? h('a', { class: 'text-14 dark-theme text-decoration-none', href: link[1] }, link[0]) : null),
  body);

/** Main line + grey sub line in one cell (V3 table cells). */
export const cell = (main, sub = null) => h('div', { class: 'd-flex flex-column' }, h('span', { class: 'text-14 main-text' }, main), sub ? h('span', { class: 'text-12 sub-text' }, sub) : null);
export const person = (name, sub) => h('div', { class: 'ex-person' }, h('span', { class: 'ex-avatar' }, name.split(/\s+/).slice(0, 2).map((s) => s[0]).join('').toUpperCase()), cell(name, sub));

/** Students used against the plan limit. */
export const meter = (used, limit) => {
  const pct = limit ? Math.min(100, Math.round((used / limit) * 100)) : 0;
  return h('div', { class: 'd-flex align-items-center gap-2' },
    h('span', { class: 'ad-bar' }, h('i', { class: pct >= 95 ? 'bad' : pct >= 85 ? 'warn' : '', style: { width: `${pct}%` } })),
    h('span', { class: 'text-12 sub-text text-nowrap' }, `${used} / ${limit ?? '∞'}`));
};

/** Label / value grid used in cards and dialogs (no borders, soft background). */
export const facts = (rows) => h('div', { class: 'ad-facts' }, rows.filter(Boolean).map(([label, value, wide]) => h('div', { class: `ad-fact${wide ? ' wide' : ''}` },
  h('div', { class: 'text-12 sub-text' }, label), h('div', { class: 'text-14 main-text fw-medium' }, value ?? '-'))));

export const hero = (label, amount, side, meta = null) => h('div', { class: 'ad-hero' },
  h('div', {}, h('div', { class: 'text-13 sub-text' }, label), h('div', { class: 'ad-hero-amount' }, amount), meta ? h('div', { class: 'text-13 sub-text mt-1' }, meta) : null),
  h('div', { class: 'd-flex flex-column align-items-end gap-2' }, side));

/** Small V3 table for lists inside a card (no paging). */
export const simpleTable = (cols, rows, empty, { fit = false } = {}) => h('div', { class: 'table-responsive-wrapper' }, h('div', { class: 'table-responsive' },
  h('table', { class: `table bl-solid m-0 ex-v3-table${fit ? ' ex-fit' : ''}` },
    h('thead', {}, h('tr', {}, cols.map((c, i) => h('th', { class: `${i ? '' : 'px-4'} py-3 bg-table-header text-14 main-text fw-medium` }, c.label)))),
    h('tbody', {}, rows.length ? rows.map((r) => h('tr', {}, cols.map((c, i) => h('td', { class: `${i ? '' : 'px-4'} py-3 align-middle text-14 main-text` }, c.render(r)))))
      : h('tr', {}, h('td', { colspan: cols.length, class: 'p-0' }, EmptyState.empty(empty)))))));

// ---------------------------------------------------------------- subscription
/** What the tutor has now, in one card. Same card on the admin's Tutor details and the tutor's My Subscription. */
export function subscriptionCard(t, plan, { actions = null, forTutor = false } = {}) {
  const yearly = t.cycle === 'yearly';
  const price = yearly ? (t.price - plan.yearly_discount) * 12 : t.price;
  let when;
  if (t.status === 'trial') when = [`Free trial ends ${day(t.trial_ends)}`, `${inDays(t.trial_ends)}. ${forTutor ? `Then ${plan.name} at ${lkr(price)} ${yearly ? 'a year' : 'a month'}; the invoice comes 3 days before.` : `Then ${plan.name}; the first invoice is made 3 days before.`}`];
  else if (t.status === 'complimentary') when = [`Free until ${day(t.period_end)}`, 'Complimentary access given by ENLIXO'];
  else if (t.status === 'cancelled') when = ['Subscription ended', `on ${day(t.period_end)}`];
  else if (t.status === 'suspended') when = ['Suspended', forTutor ? 'Pay the open invoice to switch everything back on.' : (t.status_reason?.startsWith('Admin: ') ? `By ENLIXO: ${t.status_reason.slice(7)}` : 'Classes are paused until the invoice is paid.')];
  else if (t.status === 'overdue') when = [`Payment was due ${day(t.period_end)}`, `${inDays(t.period_end)}. Suspended after the grace period.`];
  else if (t.cancel_at_period_end) when = [`Ends ${day(t.period_end)}`, `${inDays(t.period_end)}. The subscription was cancelled; no new invoice.`];
  else when = [`Renews ${day(t.period_end)}`, t.status === 'due' ? `${inDays(t.period_end)}. The invoice is open.` : inDays(t.period_end)];

  return h('div', { class: 'component-theme p-4' },
    h('div', { class: 'd-flex align-items-start justify-content-between gap-3 flex-wrap' },
      h('div', { class: 'd-flex align-items-center gap-3' },
        h('span', { class: `ad-plan-tile ad-plan-${t.plan}` }, icon('crown', { size: 24 })),
        h('div', {},
          h('div', { class: 'd-flex align-items-center gap-2' }, h('span', { class: 'main-topic' }, `${plan.name} plan`), chip(t.status)),
          h('div', { class: 'text-14 sub-text' }, t.status === 'trial' ? 'Free trial with every feature of the trial plan' : t.status === 'complimentary' ? 'Free access given by ENLIXO' : `${lkr(price)} ${yearly ? 'a year' : 'a month'}${t.custom_price ? ' · custom price' : ''}`),
          t.next_plan ? h('div', { class: 'text-12 sub-text' }, `Moves to ${t.next_plan.toUpperCase()} at the next renewal`) : null)),
      actions),
    h('div', { class: 'row g-3 mt-2' },
      h('div', { class: 'col-12 col-md-6' }, h('div', { class: 'ad-soft p-3 h-100' },
        h('div', { class: 'text-12 sub-text' }, t.status === 'trial' ? 'Trial' : 'Billing period'),
        h('div', { class: 'text-14 main-text fw-semibold mt-1' }, when[0]),
        h('div', { class: 'text-12 sub-text' }, when[1]))),
      h('div', { class: 'col-12 col-md-6' }, h('div', { class: 'ad-soft p-3 h-100' },
        h('div', { class: 'text-12 sub-text mb-1' }, 'Students'),
        meter(t.students, t.limit),
        h('div', { class: 'text-12 sub-text mt-1' }, t.extra_packs ? `${t.limit} = ${plan.limit} in the plan + ${t.extra_packs} × 50 extra` : t.status === 'trial' ? 'Trial limit' : `${plan.limit} in the plan`)))),
    h('div', { class: 'd-flex flex-wrap gap-2 mt-3 align-items-center' },
      h('span', { class: 'text-12 sub-text me-1' }, 'Add-ons:'),
      (t.addon_names ?? t.addons).length ? (t.addon_names ?? t.addons).map((a) => h('span', { class: 'ad-tag' }, a)) : h('span', { class: 'text-12 sub-text' }, 'None'),
      t.custom_price ? h('span', { class: 'ad-tag ad-tag-purple', title: t.custom_price }, `Custom price: ${t.custom_price}`) : null));
}

// ---------------------------------------------------------------- invoices
export function invoiceDialog(inv, { admin = false, onPay = null, reload = () => {} } = {}) {
  const lines = h('div', { class: 'ad-lines' },
    inv.lines.map((l) => h('div', { class: 'ad-line' }, h('span', {}, l.text), h('span', {}, lkr(l.amount)))),
    inv.tax ? h('div', { class: 'ad-line' }, h('span', {}, 'Tax'), h('span', {}, lkr(inv.tax))) : null,
    h('div', { class: 'ad-line ad-line-total' }, h('span', {}, 'Total'), h('span', {}, lkr(inv.total))),
    inv.paid ? h('div', { class: 'ad-line' }, h('span', {}, 'Paid'), h('span', {}, lkr(inv.paid))) : null);
  const body = h('div', {},
    hero('Invoice total', lkr(inv.total), chip(inv.status), inv.status === 'paid' ? 'Paid in full' : inv.status === 'void' ? `Void: ${inv.void_reason}` : `Due ${day(inv.due)} (${inDays(inv.due)})`),
    facts([['Tutor', inv.tutor], ['Plan', inv.plan], ['Period', `${day(inv.period_from)} - ${day(inv.period_to)}`, true], ['Issued', day(inv.issued)], ['Due date', day(inv.due)]]),
    h('div', { class: 'text-15 fw-semibold main-text mb-2' }, 'What it is for'), lines);
  const open = ['open', 'overdue', 'part'].includes(inv.status);
  let m;
  const print = BsModal.button('Print', { onclick: () => window.print() });
  const end = [BsModal.button('Close', { onclick: () => m.close() })];
  if (open && admin) {
    end.push(BsModal.button('Record Payment', { primary: true, onclick: () => { m.close(); recordPayment(inv, reload); } }));
  } else if (open && onPay) {
    end.push(BsModal.button('Pay Now', { primary: true, onclick: () => { m.close(); onPay(inv); } }));
  }
  const start = [print];
  if (open && admin) {
    start.push(BsModal.button('Send Reminder', { onclick: () => sendReminder(inv.tutor_id) }));
    start.push(BsModal.button('Void', { danger: true, onclick: () => { m.close(); voidInvoice(inv, reload); } }));
  }
  m = BsModal.open({ title: inv.number, subtitle: [inv.tutor, inv.plan, day(inv.issued)], icon: 'invoice', body, size: 'lg', footerStart: start, footerEnd: end });
}

/** Payment reminder e-mail (the server says whether the e-mail went out). */
export async function sendReminder(tutorId) {
  try {
    const { data } = await api.post(`/admin/tutors/${tutorId}/remind`, {});
    (data.sent === false ? Toast.error : Toast.success)(data.message);
  } catch (e) { Toast.error(e.message); }
}

/** Runs a save; field errors go under the fields of the dialog, anything else becomes an error toast. */
export async function trySave(root, fn) {
  try { return await fn(); } catch (e) {
    if (e.details && Object.keys(e.details).length) showErrors(root, e.details);
    Toast.error(e.message);
    return undefined;
  }
}

/** Field with label, (i) and an error line, the FIN.form look. */
export function field(label, input, { info = null, required = false, id = null } = {}) {
  const fid = id ?? `ad-${Math.random().toString(36).slice(2, 8)}`;
  input.id = fid;
  return h('div', { class: 'mb-3' },
    h('label', { class: `text-14 main-text mb-1 d-inline-flex align-items-center gap-2 ${required ? 'required' : ''}`, for: fid }, label, info ? infoTip(info, label) : null),
    input, h('div', { class: 'text-12 text-dark-red mt-1 d-none', 'data-err': fid }));
}
export const showErrors = (root, map) => {
  root.querySelectorAll('[data-err]').forEach((e) => { e.classList.add('d-none'); document.getElementById(e.dataset.err)?.classList.remove('is-invalid'); });
  Object.entries(map).forEach(([id, msg]) => { const el = root.querySelector(`[data-key="${id}"]`); if (!el) return; el.classList.add('is-invalid'); const err = root.querySelector(`[data-err="${el.id}"]`); if (err) { err.textContent = msg; err.classList.remove('d-none'); } });
};

function recordPayment(inv, reload) {
  const amount = h('input', { class: 'form-control custom-input', type: 'number', value: inv.total - inv.paid, placeholder: 'Enter the amount received', 'data-key': 'amount' });
  const method = h('select', { class: 'form-select custom-select', 'data-key': 'method' }, ['Bank transfer', 'Bank deposit', 'Cash', 'Cheque', 'Card'].map((x) => h('option', { value: x }, x)));
  const date = h('input', { class: 'form-control custom-input', type: 'date', value: new Date().toISOString().slice(0, 10), 'data-key': 'date', placeholder: 'Choose the day the money arrived' });
  const ref = h('input', { class: 'form-control custom-input', placeholder: 'Enter the bank reference or cheque number', 'data-key': 'reference', 'data-limit': 'text' });
  const body = h('div', { class: 'row gx-3' },
    h('div', { class: 'col-md-6' }, field('Amount (LKR)', amount, { required: true, info: 'The money received for this invoice. Less than the total leaves the invoice Part Paid.' })),
    h('div', { class: 'col-md-6' }, field('Paid on', date, { required: true })),
    h('div', { class: 'col-md-6' }, field('Method', method, { required: true })),
    h('div', { class: 'col-md-6' }, field('Reference', ref, { info: 'Bank reference, cheque number or receipt book number, so finance can find it later. For example 004512.' })));
  const m = BsModal.open({ title: 'Record a Payment', subtitle: [inv.number, inv.tutor, lkr(inv.total)], icon: 'wallet', body, footerEnd: [
    BsModal.button('Cancel', { onclick: () => m.close() }),
    BsModal.button('Save Payment', { primary: true, onclick: async () => {
      const ok = await trySave(m.el, () => api.post(`/admin/invoices/${inv.id}/payments`, { amount: amount.value, method: method.value, date: date.value, reference: ref.value }));
      if (!ok) return;
      m.close(); Toast.success(Number(amount.value) >= inv.total - inv.paid ? 'Payment recorded. The invoice is paid and the tutor is active.' : 'Payment recorded. The invoice is part paid.'); reload();
    } }),
  ] });
}

async function voidInvoice(inv, reload) {
  const reason = h('textarea', { class: 'form-control custom-input', rows: 3, placeholder: 'Say why this invoice is cancelled', 'data-key': 'reason', 'data-limit': '200' });
  const body = h('div', {}, h('p', { class: 'text-14 sub-text' }, 'A void invoice stays in the list with its reason. The tutor no longer has to pay it.'), field('Reason', reason, { required: true }));
  const m = BsModal.open({ title: 'Void this Invoice?', subtitle: [inv.number, lkr(inv.total)], icon: 'danger', tone: 'danger', body, footerEnd: [
    BsModal.button('Cancel', { onclick: () => m.close() }),
    BsModal.button('Void Invoice', { primary: true, danger: true, onclick: async () => {
      if (!reason.value.trim()) { showErrors(m.el, { reason: 'Give a reason. It is kept with the invoice.' }); return; }
      if (!await trySave(m.el, () => api.post(`/admin/invoices/${inv.id}/void`, { reason: reason.value }))) return;
      m.close(); Toast.success('Invoice void.'); reload();
    } }),
  ] });
}

export const invoiceColumns = ({ admin = true, open = (r) => invoiceDialog(r, { admin }) } = {}) => [
  { key: 'number', label: 'Invoice', sortable: true, render: (r) => cell(r.number, `${day(r.period_from)} - ${day(r.period_to)}`) },
  admin && { key: 'tutor', label: 'Tutor', sortable: true, render: (r) => cell(r.tutor, r.brand) },
  { key: 'plan', label: 'Plan', render: (r) => cell(r.plan, r.lines.length > 1 ? `+ ${plural(r.lines.length - 1, 'more line')}` : 'plan only') },
  { key: 'issued', label: 'Issued', sortable: true, render: (r) => cell(day(r.issued)) },
  { key: 'due', label: 'Due', sortable: true, render: (r) => cell(day(r.due), ['open', 'overdue'].includes(r.status) ? inDays(r.due) : null) },
  { key: 'total', label: 'Amount', sortable: true, render: (r) => cell(lkr(r.total), r.paid && r.paid < r.total ? `${lkr(r.paid)} paid` : null) },
  { key: 'status', label: 'Status', render: (r) => chip(r.status) },
  { key: 'view', label: '', width: '96px', render: (r) => h('button', { type: 'button', class: 'custom-btn ex-btn-sm px-3', onclick: () => open(r) }, 'View') },
].filter(Boolean);

// ---------------------------------------------------------------- payments
export const slipUrl = (p, admin) => `${DEFAULT_API_BASE}${admin ? `/admin/payments/${p.id}/slip` : `/tutor/subscription/payments/${p.id}/slip`}`;

/** On the admin side a payment opens on its own page (admin/tutor-payment-details.php, like V3's payment page). */
export const paymentUrl = (p) => `tutor-payment-details?payment-id=${p.id ?? p}`;

export function paymentDialog(p, { admin = false, reload = () => {} } = {}) {
  if (admin) { location.href = paymentUrl(p); return; }
  const url = slipUrl(p, admin);
  const preview = h('img', { class: 'ad-slip-img', alt: `Bank slip of ${p.ref}`, src: url });
  preview.addEventListener('error', () => preview.replaceWith(h('div', { class: 'd-flex flex-column align-items-center' }, icon('receipt', { size: 40 }), h('div', { class: 'text-14 main-text fw-medium mt-2' }, 'PDF slip'))));
  const slip = p.slip
    ? h('div', { class: 'ad-slip' }, preview, h('a', { class: 'custom-btn-outline px-4 mt-3 text-decoration-none', href: url, target: '_blank', rel: 'noopener' }, 'Open Slip'))
    : h('div', { class: 'ad-slip' }, icon('wallet', { size: 40 }), h('div', { class: 'text-14 main-text fw-medium mt-2' }, `${p.method}, recorded by ENLIXO`));
  const body = h('div', {},
    hero('Amount', lkr(p.amount), chip(p.status), `${p.method} · ${day(p.date)}`),
    h('div', { class: 'row g-4' },
      h('div', { class: 'col-md-6' }, facts([['Tutor', p.tutor, true], ['For invoice', p.invoice, true], ['Payment', p.ref], ['Paid on', day(p.date)], ['Slip reference', p.reference || '-', true], ['Checked by', p.checked_by ?? 'Not checked yet', true], p.reason && ['Reason', p.reason, true]])),
      h('div', { class: 'col-md-6' }, slip)));
  let m;
  const end = [BsModal.button('Close', { onclick: () => m.close() })];
  if (admin && p.status === 'waiting') {
    end.unshift(BsModal.button('Reject', { danger: true, onclick: () => { m.close(); rejectPayment(p, reload); } }));
    end.push(BsModal.button('Approve', { success: true, onclick: async () => {
      if (!await trySave(m.el, () => api.post(`/admin/payments/${p.id}/approve`, {}))) return;
      m.close(); Toast.success(`Approved. ${p.invoice} is paid and ${p.tutor} is active.`); reload();
    } }));
  }
  m = BsModal.open({ title: `Payment ${p.ref}`, subtitle: [p.tutor, p.invoice], icon: 'receipt', body, size: 'lg', footerEnd: end });
}

export function rejectPayment(p, reload) {
  const reason = h('select', { class: 'form-select custom-select', 'data-key': 'reason' },
    h('option', { value: '' }, '- Select -'),
    ['The slip amount does not match the invoice', 'The slip is not clear', 'The money has not reached our account', 'The slip was used before', 'Other'].map((x) => h('option', { value: x }, x)));
  const note = h('textarea', { class: 'form-control custom-input', rows: 3, placeholder: 'Add a note for the tutor', 'data-key': 'note', 'data-limit': '200' });
  const body = h('div', {}, h('p', { class: 'text-14 sub-text' }, 'The tutor sees the reason on their Billing page and can upload the slip again.'),
    field('Reason', reason, { required: true }), field('Note to the tutor', note));
  const m = BsModal.open({ title: 'Reject this Payment?', subtitle: [p.ref, p.tutor, lkr(p.amount)], icon: 'danger', tone: 'danger', body, footerEnd: [
    BsModal.button('Cancel', { onclick: () => m.close() }),
    BsModal.button('Reject Payment', { primary: true, danger: true, onclick: async () => {
      if (!await trySave(m.el, () => api.post(`/admin/payments/${p.id}/reject`, { reason: reason.value, note: note.value }))) return;
      m.close(); Toast.success('Payment rejected. The tutor has been told why.'); reload();
    } }),
  ] });
}

export const paymentColumns = ({ admin = true, reload = () => {} } = {}) => [
  { key: 'ref', label: 'Payment', sortable: true, render: (r) => cell(r.ref, r.slip ? 'with bank slip' : r.method) },
  admin && { key: 'tutor', label: 'Tutor', sortable: true, render: (r) => cell(r.tutor, r.invoice) },
  !admin && { key: 'invoice', label: 'Invoice', render: (r) => cell(r.invoice) },
  { key: 'date', label: 'Paid On', sortable: true, render: (r) => cell(day(r.date)) },
  { key: 'method', label: 'Method', render: (r) => cell(r.method) },
  { key: 'amount', label: 'Amount', sortable: true, render: (r) => cell(lkr(r.amount)) },
  { key: 'status', label: 'Status', render: (r) => h('div', { class: 'd-flex flex-column align-items-start gap-1' }, chip(r.status), r.reason ? h('span', { class: 'text-12 sub-text', style: 'white-space: normal; max-width: 220px;' }, r.reason) : null) },
  { key: 'view', label: '', width: '110px', render: (r) => h('button', { type: 'button', class: 'custom-btn ex-btn-sm px-3', onclick: () => paymentDialog(r, { admin, reload }) }, admin && r.status === 'waiting' ? 'Check' : 'View') },
].filter(Boolean);

export { h, fill, icon, BsModal, Toast, infoTip, EmptyState };
