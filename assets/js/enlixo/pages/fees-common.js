// Shared parts of the tutor's Student Fees pages (docs/FEES-PLAN.md): fee status chips, months, the slip check dialog,
// Record Payment, the receipt, Free Card / Discount and reminders. Student Fees, the student's Fee Card and Fee Settings
// all use these, so a fee looks and behaves the same everywhere. Layout helpers come from admin-common.js (same look as billing).
import { ApiClient } from '../core/ApiClient.js';
import { FeesMock } from '../mock/FeesMock.js';
import { FeatureGate } from '../components/FeatureGate.js';
import {
  h, fill, icon, lkr, short, day, inDays, plural, cell, person, facts, hero, panel, field, showErrors, simpleTable, BsModal, Toast, infoTip, EmptyState,
} from './admin-common.js';


// Step A (preview): sample data in the browser. Step B: drop the transport and the PHP API (src/Fees) answers.
export const PREVIEW = true;
export const api = new ApiClient(PREVIEW ? { transport: FeesMock.shared() } : {});
export const gate = await FeatureGate.load(api).catch(() => null);
export const has = (key) => !gate || gate.has(key);
export const lockBadge = (key) => gate?.badge(key) ?? null;
export const guard = (key, fn) => (gate ? gate.guard(key, fn) : fn);

export const today = () => new Date().toISOString().slice(0, 10);
export const thisMonth = () => today().slice(0, 7);

// One colour per fee state (V3 status chips). "Overdue" = not paid after the due date.
const FEE = {
  paid: ['success', 'Paid'], waiting: ['orange', 'Slip to Check'], unpaid: ['yellow', 'Not Paid Yet'], overdue: ['danger', 'Overdue'],
  rejected: ['danger', 'Slip Rejected'], free: ['purple', 'Free Card'], advance: ['primary', 'Pay in Advance'], valid: ['success', 'Valid'], void: ['grey', 'Cancelled'],
};
export const feeChip = (status, text = null) => {
  const [tone, label] = FEE[status] ?? ['primary', status];
  return h('span', { class: `table-btn table-btn-${tone === 'grey' ? 'grey ad-chip-grey' : tone} ad-chip` }, text ?? label);
};
export const STATUS_FILTER = [
  { value: 'paid', label: 'Paid' }, { value: 'waiting', label: 'Slip to Check' }, { value: 'unpaid', label: 'Not Paid (all)' },
  { value: 'overdue', label: 'Overdue' }, { value: 'rejected', label: 'Slip Rejected' }, { value: 'free', label: 'Free Card' },
];
export const METHOD_FILTER = [{ value: 'Bank slip', label: 'Bank slip (app)' }, { value: 'Cash', label: 'Cash' }, { value: 'Bank deposit', label: 'Bank deposit (recorded)' }, { value: 'Card', label: 'Card' }];

export const previewNote = () => (PREVIEW
  ? h('div', { class: 'col-12 ad-preview mb-4' }, icon('info', { size: 18 }),
    h('span', {}, h('b', {}, 'Preview with sample data. '), 'Buttons work, but nothing is saved yet. Your real students and fees come in the next step.',
      gate && gate.plan !== 'pro' ? ` Showing the ${gate.plan.toUpperCase()} plan.` : ''))
  : null);

/** WhatsApp link with the text filled in (works on every plan: it opens WhatsApp on this phone / computer). */
export const waLink = (number, text) => `https://wa.me/94${String(number).replace(/\D/g, '').replace(/^0/, '')}?text=${encodeURIComponent(text)}`;
export const reminderText = (r) => `Dear ${r.student.name.split(' ')[0]}, your ${r.class.name} fee for ${r.months.map((m) => m.label).join(', ')} (${lkr(r.total)}) is not paid yet. Please pay and upload the slip in the ENLIXO app, or pay at the next class. Thank you.`;

/** A bank slip as the student uploaded it (sample: drawn; real: the uploaded image or PDF). */
export function slipView(f) {
  const s = f.slip;
  if (!s) return h('div', { class: 'ad-slip' }, icon('money', { size: 40 }), h('div', { class: 'text-14 main-text fw-medium mt-2' }, `${f.method ?? 'Cash'}, recorded by ${f.checked_by ?? 'you'}`));
  const match = Math.round(s.amount) === Math.round(f.net);
  return h('div', { class: 'fe-slip' },
    h('div', { class: 'fe-slip-paper' },
      h('div', { class: 'd-flex justify-content-between align-items-start' }, h('b', {}, s.bank), h('span', { class: 'text-12' }, 'CASH DEPOSIT')),
      h('div', { class: 'fe-slip-row' }, h('span', {}, 'Date'), h('span', {}, s.uploaded.slice(0, 10))),
      h('div', { class: 'fe-slip-row' }, h('span', {}, 'Account'), h('span', {}, '8001 2345 6789')),
      h('div', { class: 'fe-slip-row' }, h('span', {}, 'Depositor'), h('span', {}, f.student.sid)),
      h('div', { class: 'fe-slip-row' }, h('span', {}, 'Reference'), h('span', {}, s.reference)),
      h('div', { class: 'fe-slip-amount' }, `LKR ${Number(s.amount).toLocaleString('en-LK')}.00`)),
    h('div', { class: `fe-check ${match ? 'ok' : 'bad'}` }, icon(match ? 'check' : 'warning', { size: 16 }),
      match ? 'The slip amount matches the fee' : `The slip shows ${lkr(s.amount)} but the fee is ${lkr(f.net)}`),
    h('div', { class: 'text-12 sub-text mt-2' }, `${s.file} · uploaded ${s.uploaded}`));
}

const breakdown = (f) => h('div', { class: 'ad-lines mb-3' },
  h('div', { class: 'ad-line' }, h('span', {}, `${f.month_label} class fee`), h('span', {}, lkr(f.amount))),
  f.discount ? h('div', { class: 'ad-line' }, h('span', {}, f.discount >= f.amount ? 'Free card' : 'Discount'), h('span', {}, `- ${lkr(f.discount)}`)) : null,
  f.penalty ? h('div', { class: 'ad-line' }, h('span', {}, 'Late fee'), h('span', {}, lkr(f.penalty))) : null,
  h('div', { class: 'ad-line ad-line-total' }, h('span', {}, 'To pay'), h('span', {}, lkr(f.net))));

const timeline = (rows) => h('ul', { class: 'ad-timeline' }, rows.map((r) => h('li', {},
  h('div', { class: 'text-14 main-text' }, r.what), h('div', { class: 'text-12 sub-text' }, `${r.when} · ${r.by}`))));

/** One fee opens on its own page (tutor/fee-details.php, like V3's student payment page). queue = "Check One by One". */
export const feeUrl = (id, queue = false) => `fee-details?fee-id=${id}${queue ? '&queue=1' : ''}`;
export function feeDialog(id, { queue = false } = {}) { location.href = feeUrl(id, queue); }

/** Reject a slip, drawn INSIDE the fee page (no pop-up): reason + note + Reject Slip / Cancel. */
export function rejectPanel(f, { onDone = () => {}, onCancel = () => {} } = {}) {
  const reason = h('select', { class: 'form-select custom-select', 'data-key': 'reason' }, h('option', { value: '' }, '- Select -'), f.reasons.map((x) => h('option', { value: x }, x)));
  const note = h('textarea', { class: 'form-control custom-input', rows: 3, placeholder: 'Add a short note for the student (optional)', 'data-limit': '200' });
  const panel = h('div', { class: 'fe-reject', id: 'fe-reject' },
    h('div', { class: 'd-flex align-items-center gap-2 mb-2' }, h('span', { class: 'fe-reject-ico' }, icon('danger', { size: 18 })), h('span', { class: 'text-16 main-text fw-semibold' }, 'Reject this slip')),
    h('p', { class: 'text-14 sub-text' }, `${f.student.name} sees the reason in the app and can upload a new slip. The fee stays unpaid.`),
    field('Reason', reason, { required: true, info: 'Why the slip cannot be accepted. The student reads this, so choose the closest reason, for example "The slip is not clear".' }),
    field('Note to the student', note),
    h('div', { class: 'd-flex justify-content-end gap-2 flex-wrap' },
      h('button', { type: 'button', class: 'custom-outline-btn px-4', onclick: onCancel }, 'Cancel'),
      h('button', { type: 'button', class: 'custom-danger-btn custom-btn px-4', id: 'fe-reject-go', onclick: async () => {
        try { await api.post(`/tutor/fees/${f.id}/reject`, { reason: reason.value, note: note.value }); } catch (e) { showErrors(panel, e.details ?? {}); return; }
        Toast.success(`Slip rejected. ${f.student.name} was told why.`); onDone();
      } }, 'Reject Slip')));
  return panel;
}

/** Cash / bank deposit / card taken at the class: opens the Record Payment page (tutor/record-payment.php). */
export function recordPayment({ student = null, select = [] } = {}) {
  const q = new URLSearchParams();
  if (student) q.set('student-id', student);
  if (select.length) q.set('months', select.join(','));
  location.href = `record-payment${q.toString() ? `?${q}` : ''}`;
}

/** The Record Payment page: pick the student, tick the months, how they paid; Save gives the receipt page. */
export async function mountRecordPayment(root, actions, { student = null, select = [] } = {}) {
  const { data: students } = await api.get('/tutor/fees/students');
  const pick = h('select', { class: 'form-select custom-select', 'data-key': 'student' }, h('option', { value: '' }, '- Select -'),
    students.map((s) => h('option', { value: s.id, selected: s.id === student }, `${s.name} · ${s.sid}`)));
  const months = h('div', { 'data-key': 'items', id: 'rp-items' });
  const total = h('span', {}, lkr(0));
  const count = h('span', { class: 'text-12 sub-text' }, 'Nothing ticked');
  const method = h('div', { class: 'fe-tiles', 'data-key': 'method', id: 'rp-method' }, [['Cash', 'money', 'Paid to you or your assistant'], ['Bank deposit', 'bank', 'Paid into your bank, slip shown'], ['Card', 'card', 'Card machine at the class']].map(([v, ico, sub], i) =>
    h('label', { class: 'fe-tile' }, h('input', { type: 'radio', name: 'rp-method', value: v, checked: i === 0 }), icon(ico, { size: 22 }), h('span', { class: 'text-14 main-text fw-medium' }, v), h('span', { class: 'text-12 sub-text' }, sub))));
  const date = h('input', { type: 'date', class: 'form-control custom-input', value: today(), max: today(), placeholder: 'Choose the day the money was received', 'data-key': 'paid_on' });
  const ref = h('input', { class: 'form-control custom-input', placeholder: 'Enter the reference on the slip or the receipt book number', 'data-key': 'reference', 'data-limit': '60' });
  const note = h('input', { class: 'form-control custom-input', placeholder: 'Add a note, for example "Paid by her mother"', 'data-limit': '200' });
  const send = h('input', { class: 'form-check-input', type: 'checkbox', role: 'switch', checked: true, id: 'rp-send' });
  let picked = new Map();

  const sum = () => { const t = [...picked.values()].reduce((a, x) => a + x.net, 0); total.textContent = lkr(t); count.textContent = picked.size ? `${plural(picked.size, 'month')} ticked` : 'Nothing ticked'; return t; };
  const row = (item, enrol) => {
    const box = h('input', { class: 'form-check-input mt-0', type: 'checkbox', checked: select.includes(item.id), onchange: (e) => { if (e.target.checked) picked.set(item.id, { ...item, enrolment: enrol }); else picked.delete(item.id); sum(); } });
    if (box.checked) picked.set(item.id, { ...item, enrolment: enrol });
    return h('label', { class: 'fe-month' }, box,
      h('span', { class: 'flex-grow-1' }, h('span', { class: 'text-14 main-text fw-medium d-block' }, item.label),
        h('span', { class: 'text-12 sub-text' }, [item.discount ? (item.discount >= item.amount ? 'free card' : `${lkr(item.discount)} discount`) : null, item.penalty ? `${lkr(item.penalty)} late fee` : null].filter(Boolean).join(' · ') || 'class fee')),
      feeChip(item.status), h('span', { class: 'text-14 main-text fw-semibold fe-month-amt' }, lkr(item.net)));
  };
  async function loadStudent() {
    picked = new Map(); sum();
    if (!pick.value) { fill(months, h('div', { class: 'ad-soft p-3 text-14 sub-text' }, 'Choose a student to see the months they can pay.')); return; }
    const { data } = await api.get(`/tutor/fees/students/${pick.value}/open`);
    fill(months, data.classes.length ? data.classes.map((c) => h('div', { class: 'mb-3' },
      h('div', { class: 'text-13 sub-text mb-2' }, `${c.class.name} · ${lkr(c.fee)} a month`),
      c.open.map((o) => row(o, c.enrolment)), row(c.advance, c.enrolment)))
      : h('div', { class: 'ad-soft p-3 text-14 sub-text' }, 'This student is not active in any of your classes.'));
    sum();
  }
  pick.addEventListener('change', loadStudent);

  const body = h('div', { class: 'row g-4' },
    h('div', { class: 'col-lg-7' },
      field('Student', pick, { required: true, info: 'Who paid. Type to search by name or Student ID, for example ST02412.' }),
      h('label', { class: 'text-14 main-text mb-1 required d-inline-flex align-items-center gap-2' }, 'Months paid', infoTip('Tick every month this money is for. Unpaid months are listed first; "Pay in Advance" adds next month.', 'Months paid')),
      months, h('div', { class: 'text-12 text-dark-red mt-1 d-none', 'data-err': 'rp-items' })),
    h('div', { class: 'col-lg-5' },
      h('label', { class: 'text-14 main-text mb-1 required' }, 'How did they pay?'), method, h('div', { class: 'text-12 text-dark-red mt-1 d-none', 'data-err': 'rp-method' }),
      h('div', { class: 'mt-3' }, field('Received on', date, { required: true })),
      field('Reference', ref, { info: 'The bank reference for a deposit, or the number in your paper receipt book. Needed for a bank deposit. For example 004512.' }),
      field('Note', note),
      h('div', { class: 'form-check form-switch d-flex align-items-center gap-2 mb-3' }, send, h('label', { class: 'form-check-label text-14 main-text', for: 'rp-send' }, 'Send the receipt to the student on WhatsApp')),
      h('div', { class: 'ad-hero mb-0' }, h('div', {}, h('div', { class: 'text-13 sub-text' }, 'Total received'), h('div', { class: 'ad-hero-amount' }, total), count))));
  const save = async (btn) => {
    const items = [...picked.keys()].filter((k) => typeof k === 'number');
    const payload = { student: pick.value, items: items.length || picked.size ? [...picked.keys()] : [], method: root.querySelector('input[name="rp-method"]:checked')?.value, paid_on: date.value, reference: ref.value, note: note.value, total: sum(), send: send.checked };
    btn.disabled = true;
    try {
      const { data } = await api.post('/tutor/fees/payments', payload);
      Toast.success(`Saved. Receipt ${data.no} is ready${send.checked ? ' and was sent on WhatsApp' : ''}.`);
      location.href = `fee-receipt?receipt-id=${data.receipt}&saved=1`;
    } catch (e) { btn.disabled = false; showErrors(root, e.details ?? {}); if (!e.details) Toast.error(e.message); }
  };
  const saveBtn = (cls) => h('button', { type: 'button', class: `custom-btn ${cls}`, 'data-save': '1', onclick: (e) => save(e.currentTarget) }, 'Save & Give Receipt');
  fill(actions, h('a', { class: 'custom-outline-btn ex-btn-lg px-4 text-decoration-none', href: student ? `student-fees?student-id=${student}` : 'fees' }, 'Cancel'), saveBtn('ex-btn-lg px-5'));
  fill(root, previewNote(), h('div', { class: 'component-theme p-4 fe-page-form' },
    h('div', { class: 'd-flex align-items-center gap-3 mb-4' }, h('span', { class: 'fe-form-ico' }, icon('wallet', { size: 22 })),
      h('div', {}, h('div', { class: 'text-18 main-text fw-semibold' }, 'Record a Payment'), h('div', { class: 'text-13 sub-text' }, 'Cash, bank deposit or card taken by you · a receipt number is given at once'))),
    body,
    h('div', { class: 'd-flex justify-content-end mt-4' }, saveBtn('px-5'))));
  loadStudent();
}

/** Opens the receipt page (tutor/fee-receipt.php). */
export function receiptDialog(id) { location.href = `fee-receipt?receipt-id=${id}`; }

/** The receipt page: same layout on screen, on paper (Print) and in the WhatsApp PDF. */
export async function mountReceipt(root, actions, id) {
  const { data: r } = await api.get(`/tutor/fees/receipts/${id}`);
  document.title = `Receipt ${r.no} - Tutor`;
  const paper = h('div', { class: 'fe-receipt', id: 'fe-receipt' },
    h('div', { class: 'd-flex justify-content-between align-items-start gap-3' },
      h('div', {}, h('div', { class: 'fe-receipt-brand' }, r.tutor.brand), h('div', { class: 'text-12 sub-text' }, r.tutor.address), h('div', { class: 'text-12 sub-text' }, r.tutor.phone)),
      h('div', { class: 'text-end' }, h('div', { class: 'text-12 sub-text' }, 'RECEIPT'), h('div', { class: 'fe-receipt-no' }, r.no), h('div', { class: 'text-12 sub-text' }, day(r.paid_on)))),
    h('div', { class: 'ad-facts mt-3' }, [['Student', `${r.student.name}`], ['Student ID', r.student.sid], ['Class', r.class.name, true], ['Paid by', r.method], ['Reference', r.reference ?? '-'], ['Received by', r.by]].map(([a, b, w]) => h('div', { class: `ad-fact${w ? ' wide' : ''}` }, h('div', { class: 'text-12 sub-text' }, a), h('div', { class: 'text-14 main-text fw-medium' }, b)))),
    h('div', { class: 'ad-lines' }, r.lines.map((l) => h('div', { class: 'ad-line' }, h('span', {}, l.text, l.discount ? h('span', { class: 'text-12 sub-text ms-2' }, `(${lkr(l.amount)} - ${lkr(l.discount)})`) : null, l.penalty ? h('span', { class: 'text-12 sub-text ms-2' }, `incl. late fee ${lkr(l.penalty)}`) : null), h('span', {}, lkr(l.net)))),
      h('div', { class: 'ad-line ad-line-total' }, h('span', {}, 'Total paid'), h('span', {}, lkr(r.amount)))),
    h('div', { class: 'text-12 sub-text mt-3 text-center' }, r.footer));
  const wa = () => window.open(waLink(r.student.whatsapp, `Payment received, thank you. Receipt ${r.no}: ${r.lines.map((l) => l.text).join(', ')}, ${lkr(r.amount)}. ${r.tutor.brand}`), '_blank');
  const print = () => { document.body.classList.add('fe-printing'); window.print(); document.body.classList.remove('fe-printing'); };
  fill(actions,
    h('button', { type: 'button', class: 'custom-outline-btn ex-btn-lg px-4 d-inline-flex align-items-center gap-2', onclick: wa }, icon('whatsapp', { size: 18 }), 'Send on WhatsApp'),
    h('button', { type: 'button', class: 'custom-btn ex-btn-lg px-5 d-inline-flex align-items-center gap-2', onclick: print }, icon('printer', { size: 18 }), 'Print'));
  fill(root, previewNote(), h('div', { class: 'fe-receipt-page' }, paper,
    h('div', { class: 'd-flex justify-content-center gap-2 flex-wrap mt-3 fe-no-print' },
      h('a', { class: 'custom-outline-btn px-4 text-decoration-none', href: `student-fees?student-id=${r.student.id ?? ''}` }, 'Student Fee Card'),
      h('a', { class: 'custom-outline-btn px-4 text-decoration-none', href: 'fees' }, 'All Student Fees'))));
}

/** Opens the Free Card / Discount page (tutor/fee-concession.php) for one student (PRO: fees.advanced). */
export function concessionDialog({ student }) {
  if (!has('fees.advanced')) return gate.openUpgrade('fees.advanced');
  location.href = `fee-concession?student-id=${student.id}`;
}

/** Free card / half card / discount for one student in one class, from a month, with a reason: the page body. */
export function mountConcession(root, actions, { student, classes }) {
  const cls = h('select', { class: 'form-select custom-select', 'data-key': 'enrolment' }, h('option', { value: '' }, '- Select -'), classes.map((c) => h('option', { value: c.enrolment, selected: classes.length === 1 }, `${c.name} · ${lkr(c.fee)}`)));
  const types = [['free', 'gift', 'Free card', 'Pays nothing'], ['half', 'sale', 'Half card', 'Pays 50%'], ['percent', 'price', 'Discount %', 'For example 10%'], ['fixed', 'money', 'Discount LKR', 'For example 500']];
  const value = h('input', { class: 'form-control custom-input', type: 'number', min: 1, placeholder: 'Enter the discount, for example 10', 'data-key': 'value' });
  const valueWrap = h('div', { class: 'd-none' }, field('Discount', value, { required: true, info: 'For "Discount %" the percent off the class fee (10 = 10%); for "Discount LKR" the rupees off every month (500 = LKR 500).' }));
  const tiles = h('div', { class: 'fe-tiles fe-tiles-4', 'data-key': 'type', id: 'cc-type' }, types.map(([v, ico, t, sub]) => h('label', { class: 'fe-tile' },
    h('input', { type: 'radio', name: 'cc-type', value: v, onchange: () => valueWrap.classList.toggle('d-none', !['percent', 'fixed'].includes(v)) }), icon(ico, { size: 22 }), h('span', { class: 'text-14 main-text fw-medium' }, t), h('span', { class: 'text-12 sub-text' }, sub))));
  const from = h('input', { type: 'month', class: 'form-control custom-input', value: thisMonth(), 'data-key': 'from', placeholder: 'Choose the first month' });
  const to = h('input', { type: 'month', class: 'form-control custom-input', 'data-key': 'to', placeholder: 'Leave empty to keep it until you stop it' });
  const reason = h('textarea', { class: 'form-control custom-input', rows: 2, placeholder: 'Say why, for example "Two brothers in this class"', 'data-key': 'reason', 'data-limit': '200' });
  const body = h('div', {},
    field('Class', cls, { required: true, info: 'The class this applies to. The student pays the normal fee in their other classes.' }),
    h('label', { class: 'text-14 main-text mb-1 required' }, 'What to give'), tiles, h('div', { class: 'text-12 text-dark-red mt-1 d-none', 'data-err': 'cc-type' }),
    h('div', { class: 'mt-3' }, valueWrap),
    h('div', { class: 'row gx-3' }, h('div', { class: 'col-md-6' }, field('From month', from, { required: true, info: 'The first fee month it applies to. A fee already paid is not changed.' })), h('div', { class: 'col-md-6' }, field('Until month', to, { info: 'Leave empty to keep it until you stop it from the student\'s Fee Card.' }))),
    field('Reason', reason, { required: true, info: 'Kept with the student and shown on their Fee Card, so you remember later why they pay less.' }));
  const back = `student-fees?student-id=${student.id}`;
  const save = async (btn) => {
    btn.disabled = true;
    try { await api.post('/tutor/fees/concessions', { enrolment: cls.value, type: root.querySelector('input[name="cc-type"]:checked')?.value, value: value.value, from: from.value, to: to.value, reason: reason.value }); } catch (e) { btn.disabled = false; showErrors(root, e.details ?? {}); return; }
    Toast.success(`Saved. ${student.name}'s fee changes from ${from.value}.`);
    setTimeout(() => { location.href = back; }, 600);
  };
  const saveBtn = (cls2) => h('button', { type: 'button', class: `custom-btn ${cls2}`, onclick: (e) => save(e.currentTarget) }, 'Save');
  fill(actions, h('a', { class: 'custom-outline-btn ex-btn-lg px-4 text-decoration-none', href: back }, 'Cancel'), saveBtn('ex-btn-lg px-5'));
  fill(root, previewNote(), h('div', { class: 'component-theme p-4 fe-page-form' },
    h('div', { class: 'd-flex align-items-center gap-3 mb-4' }, h('span', { class: 'fe-form-ico' }, icon('gift', { size: 22 })),
      h('div', {}, h('div', { class: 'text-18 main-text fw-semibold' }, 'Give a Free Card or Discount'), h('div', { class: 'text-13 sub-text' }, `${student.name} · ${student.sid}`))),
    body,
    h('div', { class: 'd-flex justify-content-end mt-4' }, saveBtn('px-5'))));
}

/** Reminder for students who owe fees. One student: opens WhatsApp with the text (every plan). Many: sent by the WhatsApp API (GROW+). */
export function reminderDialog(rows, { reload = () => {} } = {}) {
  const one = rows.length === 1;
  const text = h('textarea', { class: 'form-control custom-input', rows: 4, placeholder: 'Write the reminder message', 'data-limit': '500' }, reminderText(rows[0]));
  const body = h('div', {},
    one ? facts([['Student', `${rows[0].student.name} (${rows[0].student.sid})`, true], ['Owes', `${lkr(rows[0].total)} for ${rows[0].months.map((x) => x.label).join(', ')}`, true], ['WhatsApp', rows[0].student.whatsapp]])
      : h('div', { class: 'ad-hero' }, h('div', {}, h('div', { class: 'text-13 sub-text' }, 'Reminder to'), h('div', { class: 'ad-hero-amount' }, plural(rows.length, 'student')), h('div', { class: 'text-13 sub-text mt-1' }, `${lkr(rows.reduce((a, r) => a + r.total, 0))} owed in total. Each one gets their own months and amount.`))),
    field(one ? 'Message' : 'Message (first student shown)', text, { info: 'Each student gets their own name, class, months and amount. Keep it short and polite.' }),
    one ? null : h('div', { class: 'text-12 sub-text' }, has('comms.whatsapp') ? 'Sent from your WhatsApp API number, one message per student.' : 'Sending to many students at once needs WhatsApp on GROW. You can still remind one student at a time from their row.'));
  let m;
  const end = [BsModal.button('Cancel', { onclick: () => m.close() })];
  if (one) end.push(BsModal.button('Open WhatsApp', { primary: true, onclick: async () => { window.open(waLink(rows[0].student.whatsapp, text.value), '_blank'); await api.post('/tutor/fees/reminders', { enrolments: [rows[0].id], channel: 'link' }); m.close(); reload(); } }));
  else end.push(BsModal.button(`Send to ${plural(rows.length, 'student')}`, { primary: true, onclick: guard('comms.whatsapp', async () => { await api.post('/tutor/fees/reminders', { enrolments: rows.map((r) => r.id), channel: 'whatsapp' }); m.close(); Toast.success(`Reminder sent to ${plural(rows.length, 'student')}.`); reload(); }) }));
  m = BsModal.open({ title: 'Send a Fee Reminder', subtitle: one ? [rows[0].student.name, rows[0].class.short] : [plural(rows.length, 'student'), 'WhatsApp'], icon: 'whatsapp', body, size: 'lg', footerEnd: end });
}

export { h, fill, icon, lkr, short, day, inDays, plural, cell, person, facts, hero, panel, field, showErrors, simpleTable, BsModal, Toast, infoTip, EmptyState };
