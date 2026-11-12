// Tutor → Fee Settings (tutor/fee-settings.php). One card with tabs, like V3 settings pages; each tab saves on its own
// (PUT /tutor/fee-settings with { part, ... , summary }) and every change goes to the Activity Log.
import { Tabs } from '../components/Tabs.js';
import { QuickGuide } from '../components/QuickGuide.js';
import { ActionMenu } from '../components/Popover.js';
import {
  api, has, lockBadge, guard, h, fill, icon, lkr, plural, cell, field, showErrors, simpleTable, BsModal, Toast, infoTip, EmptyState, previewNote, feeChip,
} from './fees-common.js';

QuickGuide.attachAll();
const root = document.getElementById('settings-page');
let D;

const sw = (checked, onchange = null, disabled = false) => h('div', { class: 'form-check form-switch m-0' }, h('input', { class: 'form-check-input', type: 'checkbox', role: 'switch', checked, disabled, onchange }));
const rule = (title, help, control, badge = null) => h('div', { class: 'ad-rule' },
  h('div', { class: 'pe-3' }, h('div', { class: 'text-14 main-text fw-medium d-flex align-items-center gap-2' }, title, badge), h('div', { class: 'text-12 sub-text' }, help)), control);
const num = (value, attrs = {}) => h('input', { class: 'form-control custom-input', type: 'number', value, ...attrs });
const save = (part, summary) => h('div', { class: 'd-flex justify-content-end mt-4' }, h('button', { type: 'button', class: 'custom-btn px-5', onclick: async () => { await api.put('/tutor/fee-settings', { part, summary }); Toast.success('Saved.'); } }, 'Save Changes'));
const add = (p, ...els) => p.append(...els.filter(Boolean));
const sel = (opts, value) => h('select', { class: 'form-select custom-select' }, opts.map(([v, t]) => h('option', { value: v, selected: v === value }, t)));

function pay(p) {
  const s = D.settings;
  const banks = h('div', { class: 'row g-3' }, s.banks.map((b) => h('div', { class: 'col-md-6' }, h('div', { class: 'ad-soft p-3 h-100 d-flex gap-3 align-items-start' },
    h('span', { class: 'ad-todo-icon' }, icon('bank', { size: 20 })),
    h('div', { class: 'flex-grow-1' }, h('div', { class: 'text-14 main-text fw-semibold' }, b.bank), h('div', { class: 'text-12 sub-text' }, `${b.branch} branch · ${b.name}`), h('div', { class: 'text-16 main-text fw-semibold mt-2' }, b.number)),
    ActionMenu.kebab([{ label: 'Edit Account', icon: 'edit', onClick: () => bankDialog(b) }, { label: b.on ? 'Hide From Students' : 'Show To Students', icon: b.on ? 'deactivate' : 'activate' }, { label: 'Delete Account', icon: 'delete', danger: true }], `Actions for ${b.bank}`)))),
  h('div', { class: 'col-md-6' }, h('button', { type: 'button', class: 'fe-add h-100 w-100', onclick: () => bankDialog(null) }, icon('add', { size: 22 }), 'Add Bank Account')));
  p.append(
    h('div', { class: 'sub-topic mb-1' }, 'Ways to pay'),
    rule('Bank slip in the app', 'Students pay into your bank and upload the slip. You check it under Slips to Check.', sw(s.methods.slip)),
    rule('Cash at the class', 'You or your assistant take cash and press Record Payment. The receipt number is given at once.', sw(s.methods.cash)),
    rule('Card in the app', has('payments.online_card') ? 'Students pay by card. The fee is marked paid by itself, no slip to check.' : 'Students pay by card in the app. Available on GROW with your own gateway or the ENVIIZ Payment Gateway add-on.', sw(s.methods.card, null, !has('payments.online_card')), lockBadge('payments.online_card')),
    h('div', { class: 'sub-topic mt-4 mb-1 d-flex align-items-center gap-2' }, 'Your bank accounts', infoTip('Students see these accounts in the app when they pay a fee. Hide an account you no longer use instead of deleting it.', 'Your bank accounts')),
    h('div', { class: 'text-12 sub-text mb-3' }, 'Shown to students on the Pay Fee screen of the app.'), banks,
    h('div', { class: 'mt-4' }, field('Note shown with the accounts', h('textarea', { class: 'form-control custom-input', rows: 2, placeholder: 'For example: write your Student ID on the slip', 'data-limit': '200' }, s.slip_note), { info: 'A short instruction students read before they pay. For example: "Write your Student ID on the slip and upload a clear photo".' })),
    save('pay', 'Payment methods saved'));
}

function bankDialog(b) {
  const f = (k, ph, v) => h('input', { class: 'form-control custom-input', placeholder: ph, value: v ?? '', 'data-key': k, 'data-limit': '60' });
  const body = h('div', { class: 'row gx-3' },
    h('div', { class: 'col-md-6' }, field('Bank', f('bank', 'Enter the bank name, for example Commercial Bank', b?.bank), { required: true })),
    h('div', { class: 'col-md-6' }, field('Branch', f('branch', 'Enter the branch, for example Maharagama', b?.branch), { required: true })),
    h('div', { class: 'col-md-6' }, field('Account name', f('name', 'Enter the name on the account', b?.name), { required: true, info: 'The name printed on the passbook. Students write it on the deposit slip.' })),
    h('div', { class: 'col-md-6' }, field('Account number', f('number', 'Enter the account number', b?.number), { required: true, info: 'Find it in your passbook or bank app, for example 8001 2345 6789.' })));
  const m = BsModal.open({ title: b ? 'Edit Bank Account' : 'Add Bank Account', subtitle: 'Shown to students in the app', icon: 'bank', body, footerEnd: [
    BsModal.button('Cancel', { onclick: () => m.close() }),
    BsModal.button('Save Account', { primary: true, onclick: async () => {
      const bank = Object.fromEntries([...m.el.querySelectorAll('[data-key]')].map((x) => [x.dataset.key, x.value]));
      try { await api.put('/tutor/fee-settings', { part: 'banks', bank, summary: `${b ? 'Edited' : 'Added'} bank account ${bank.bank}` }); } catch (e) { showErrors(m.el, e.details ?? {}); return; }
      m.close(); Toast.success('Bank account saved.');
    } }),
  ] });
}

function due(p) {
  const s = D.settings;
  p.append(
    rule('Fees are created on', 'The day each month\'s fee appears for every active student. Students see it in the app the same day.', sel([['1', '1st of the month']], '1')),
    rule('Due day for new classes', 'Each class can have its own due day (Class Fees tab). This one is filled in when you create a class.', num(s.default_due_day, { min: 1, max: 28, placeholder: 'Enter a day from 1 to 28' })),
    rule('Late fee', 'Added once to a fee that is not paid a few days after its due date. Off = no late fee.', sw(s.late_fee.on)),
    h('div', { class: 'row gx-3 ad-soft p-3 mt-2 mb-2' },
      h('div', { class: 'col-md-4' }, field('Late fee type', sel([['fixed', 'Fixed amount (LKR)'], ['percent', 'Percent of the fee']], s.late_fee.type))),
      h('div', { class: 'col-md-4' }, field('Late fee', num(s.late_fee.value, { placeholder: 'Enter the late fee, for example 200' }), { info: 'Rupees for a fixed late fee (200 = LKR 200), or the percent of the class fee (10 = 10%).' })),
      h('div', { class: 'col-md-4' }, field('Added after (days)', num(s.late_fee.after_days, { min: 0, max: 30, placeholder: 'Enter the days after the due date' }), { info: 'Days after the due date before the late fee is added. 5 = a fee due on the 10th gets the late fee on the 15th.' }))),
    rule('Close recordings and tutes of unpaid months', `Students who have not paid a month cannot open that month's recordings and tutes ${s.lock_unpaid.after_days} days after the due date. They open again as soon as the fee is paid or the slip is approved.`, sw(s.lock_unpaid.on)),
    save('due', 'Due dates and late fee saved'));
}

function free(p) {
  if (!has('fees.advanced')) { p.append(h('div', { class: 'ad-preview mb-3' }, icon('lock', { size: 18 }), h('span', {}, h('b', {}, 'Free cards and discounts are on PRO. '), 'On START and GROW every student pays the class fee.'))); }
  const s = D.settings;
  add(p, h('div', { class: 'text-14 sub-text mb-3' }, 'The kinds of free card and discount you give. You give one to a student from their Fee Card; it changes their fee from the month you choose.'),
    simpleTable([
      { label: 'Name', render: (t) => cell(t.name) },
      { label: 'What the student pays', render: (t) => cell(t.kind === 'free' ? 'Nothing' : t.kind === 'percent' ? `${100 - t.value}% of the fee` : `The fee - ${lkr(t.value)}`) },
      { label: 'Switched on', render: (t) => sw(t.on, null, !has('fees.advanced')) },
      { label: '', render: (t) => ActionMenu.kebab([{ label: 'Edit', icon: 'edit' }, { label: 'Delete', icon: 'delete', danger: true }], `Actions for ${t.name}`) },
    ], s.concession_types, { title: 'No free card types', text: 'Add the kinds of free card or discount you give.' }),
    h('div', { class: 'd-flex justify-content-end mt-3' }, h('button', { type: 'button', class: 'custom-btn-outline px-4', onclick: guard('fees.advanced', () => Toast.success('Preview: the add dialog comes with step B.')) }, 'Add a Type')));
}

function remind(p) {
  const r = D.settings.reminders;
  const text = h('textarea', { class: 'form-control custom-input', rows: 4, placeholder: 'Write the reminder message', 'data-limit': '500' }, r.text);
  const preview = h('div', { class: 'fe-wa' });
  const draw = () => { preview.textContent = text.value.replace('{student}', 'Sahan').replace('{class}', '2026 A/L Combined Maths Theory').replace('{month}', 'November').replace('{amount}', '3,000').replace('{due_date}', '10 Nov').replace('{tutor}', 'Perera Maths Academy'); };
  text.addEventListener('input', draw); draw();
  add(p,
    !has('comms.whatsapp') ? h('div', { class: 'ad-preview mb-3' }, icon('lock', { size: 18 }), h('span', {}, h('b', {}, 'Automatic reminders need WhatsApp on GROW. '), 'On START you remind students one by one from Student Fees: WhatsApp opens with the message ready.')) : null,
    rule('Before the due date', 'Days before the due date. 0 = do not send.', num(r.before_days, { min: 0, max: 7, placeholder: 'Days before' }), lockBadge('comms.whatsapp')),
    rule('On the due date', 'A short message on the day the fee is due.', sw(r.on_due, null, !has('comms.whatsapp'))),
    rule('After the due date', 'Days after the due date for the first reminder, then again every few days until paid.', h('div', { class: 'd-flex gap-2' }, num(r.after_days, { min: 1, max: 30, placeholder: 'Days after' }), num(r.repeat_days, { min: 0, max: 30, placeholder: 'Repeat every' }))),
    h('div', { class: 'row g-4 mt-1' },
      h('div', { class: 'col-lg-7' }, field('Message', text, { info: 'Words in { } are filled in for each student: {student}, {class}, {month}, {amount}, {due_date}, {tutor}.' })),
      h('div', { class: 'col-lg-5' }, h('div', { class: 'text-14 main-text mb-1' }, 'What the student sees'), preview)),
    save('reminders', 'Reminder settings saved'));
}

function receipts(p) {
  const r = D.settings.receipts;
  p.append(
    rule('Receipt number', `Every payment gets the next number. The next receipt will be ${r.prefix}${String(r.next).padStart(5, '0')}.`, h('input', { class: 'form-control custom-input', value: r.prefix, placeholder: 'Enter a prefix, for example RC-', 'data-limit': '10' })),
    rule('Send the receipt on WhatsApp', 'When you approve a slip or record a payment, the student gets the receipt at once.', sw(r.auto_send)),
    rule('Who may check slips and take payments', `${D.settings.approvers.join(', ')}. Change it in Settings → Roles (permission "Approve / reject payments").`, h('a', { class: 'custom-btn-outline px-4 text-decoration-none', href: 'roles' }, 'Open Roles')),
    h('div', { class: 'mt-3' }, field('Text at the bottom of the receipt', h('textarea', { class: 'form-control custom-input', rows: 2, placeholder: 'For example: Thank you. Keep this receipt until the end of the year.', 'data-limit': '200' }, r.footer))),
    save('receipts', 'Receipt settings saved'));
}

function classes(p) {
  p.append(h('div', { class: 'text-14 sub-text mb-3' }, 'The monthly fee and due day of each class. Change them on the class form; a new fee applies from next month, fees already created keep their amount.'),
    simpleTable([
      { label: 'Class', render: (c) => cell(c.name, c.mode) },
      { label: 'Monthly Fee', render: (c) => cell(lkr(c.fee)) },
      { label: 'Due Day', render: (c) => cell(`${c.due_day}th of the month`) },
      { label: 'Students', render: (c) => cell(String(c.students)) },
      { label: '', render: (c) => h('a', { class: 'custom-btn ex-btn-sm px-3 text-decoration-none', href: `edit-class?class-id=${c.id}` }, 'Edit') },
    ], D.classes, { title: 'No classes yet', text: 'Create a class with a monthly fee to start collecting fees.' }));
}

function log(p) {
  p.append(simpleTable([
    { label: 'When', render: (l) => cell(l.when) },
    { label: 'Who', render: (l) => cell(l.by) },
    { label: 'What changed', render: (l) => cell(l.what) },
  ], D.log, { title: 'No changes yet', text: 'Every change to these settings is listed here.' }));
}

async function load() {
  try {
    ({ data: D } = await api.get('/tutor/fee-settings'));
    const card = h('div', { class: 'col-12 component-theme p-4' });
    new Tabs({ tabs: [
      { id: 'pay', label: 'How Students Pay', render: pay },
      { id: 'due', label: 'Due Dates & Late Fees', render: due },
      { id: 'free', label: 'Free Cards & Discounts', badge: lockBadge('fees.advanced'), render: free },
      { id: 'reminders', label: 'Reminders', badge: lockBadge('comms.whatsapp'), render: remind },
      { id: 'receipts', label: 'Receipts', render: receipts },
      { id: 'classes', label: 'Class Fees', badge: D.classes.length, render: classes },
      { id: 'log', label: 'Activity Log', render: log },
    ] }).mount(card);
    fill(root, previewNote(), card);
  } catch (e) { fill(root, EmptyState.error(e.message, load)); }
}
load();
