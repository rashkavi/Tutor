// Admin → Plans & Billing (admin/plans.php?tab=plans|addons|rules|banks). V3 settings layout: left menu, right panel.
// Data: GET /admin/plans; saves: PUT /admin/plans/{key}, /admin/addons/{key}, /admin/billing-rules, POST / PUT / DELETE /admin/bank-accounts.
import { QuickGuide } from '../components/QuickGuide.js';
import { api, h, fill, icon, lkr, plural, infoTip, simpleTable, cell, field, previewNote, trySave, BsModal, Toast } from './admin-common.js';

QuickGuide.attachAll();
const root = document.getElementById('plans-page');
let { data: D } = await api.get('/admin/plans');
const TABS = [
  ['plans', 'Plans & Prices', 'plans'], ['addons', 'Add-ons', 'box'], ['rules', 'Trial & Billing Rules', 'hourglass'], ['banks', 'Bank Accounts', 'bank'],
];
let tab = new URLSearchParams(location.search).get('tab');
if (!TABS.some(([k]) => k === tab)) tab = 'plans';

const num = (value, ph, attrs = {}) => h('input', { class: 'form-control custom-input', type: 'number', min: 0, value: value ?? '', placeholder: ph, ...attrs });
const text = (value, ph, attrs = {}) => h('input', { class: 'form-control custom-input', value: value ?? '', placeholder: ph, 'data-limit': 'text', ...attrs });
const sw = (checked, label, onchange = null) => { const id = `sw-${Math.random().toString(36).slice(2, 7)}`; return h('div', { class: 'form-check form-switch ex-switch-bs m-0' }, h('input', { type: 'checkbox', class: 'form-check-input', role: 'switch', id, checked, onchange }), h('label', { class: 'visually-hidden', for: id }, label)); };
const reload = async () => { ({ data: D } = await api.get('/admin/plans')); show(tab); };
const head = (title, info, action = null) => h('div', { class: 'd-flex justify-content-between align-items-center gap-3 mb-3' },
  h('span', { class: 'sub-topic d-inline-flex align-items-center gap-2' }, title, infoTip(info, title)), action);
/** fields: [key, label, input, info, required, col]; save(values) returns the request promise. */
const editDialog = (title, sub, ico, fields, save, done) => {
  fields.forEach(([key, , el]) => { el.dataset.key = key; });
  const m = BsModal.open({ title, subtitle: sub, icon: ico, body: h('div', { class: 'row gx-3' }, fields.map(([, label, el, info, req, col]) => h('div', { class: col ?? 'col-md-6' }, field(label, el, { info, required: req })))),
    footerEnd: [BsModal.button('Cancel', { onclick: () => m.close() }), BsModal.button('Save', { primary: true, onclick: async () => {
      const values = Object.fromEntries(fields.map(([key, , el]) => [key, el.value]));
      if (!await trySave(m.el, () => save(values))) return;
      m.close(); Toast.success(done); reload();
    } })] });
};
const toggle = async (req, okText) => { if (await trySave(document.body, req)) { Toast.success(okText); reload(); } else reload(); };

const panels = {
  plans: () => [
    head('Plans & Prices', 'What each plan costs and how many students it allows. A new price applies from each tutor\'s next invoice; tutors with a custom price keep theirs.'),
    simpleTable([
      { label: 'Plan', render: (p) => h('div', { class: 'd-flex align-items-center gap-3' }, h('span', { class: `ad-plan-tile ad-plan-${p.key}`, style: { width: '36px', height: '36px' } }, icon('crown', { size: 18 })), cell(p.name, p.tagline)) },
      { label: 'Monthly', render: (p) => cell(lkr(p.monthly), 'a month') },
      { label: 'Yearly', render: (p) => cell(lkr((p.monthly - p.yearly_discount) * 12), `saves ${lkr(p.yearly_discount * 12)}`) },
      { label: 'Students', render: (p) => cell(`Up to ${p.limit}`, p.pack_price ? `+ ${lkr(p.pack_price)} per 50` : 'more: custom price') },
      { label: 'Tutors', render: (p) => cell(plural(p.tutors, 'tutor'), 'paying now') },
      { label: '', render: (p) => h('button', { type: 'button', class: 'custom-btn ex-btn-sm px-3', onclick: () => editDialog(`Edit ${p.name}`, ['Plans & Prices'], 'plans', [
        ['monthly', 'Monthly price (LKR)', num(p.monthly, 'Enter the monthly price'), 'What a tutor pays each month on this plan. The pricing sheet range is used as a guide, for example 5000.', true],
        ['yearly_discount', 'Yearly discount a month (LKR)', num(p.yearly_discount, 'Enter the discount per month'), 'Taken off each month when a tutor pays for 12 months at once. For example 500 = LKR 6,000 off a year.'],
        ['limit', 'Student limit', num(p.limit, 'Enter the most students allowed'), 'Most active students a tutor can have on this plan. For example 150.', true],
        ['pack_price', 'Extra 50 students (LKR a month)', num(p.pack_price, 'Leave empty for a custom price'), 'Price of one pack of 50 more students. Empty = not sold, use a custom price (CUSTOM).'],
        ['tagline', 'Short description', text(p.tagline, 'Enter one line shown under the plan name'), null, false, 'col-12'],
      ], (v) => api.put(`/admin/plans/${p.key}`, v), `${p.name} saved. New prices apply from each tutor's next invoice.`) }, 'Edit') },
    ], D.plans, { title: 'No plans', text: 'Plans appear here.' }, { fit: true }),
    h('p', { class: 'text-12 sub-text mt-3 mb-0' }, 'Each plan\'s features follow the ENLIXO Tutor pricing sheet. Tutors see the features of other plans with a lock badge and an Upgrade button.'),
  ],
  addons: () => [
    head('Add-ons', 'Extra services a tutor can buy on any plan. Monthly add-ons are added to every invoice; usage add-ons are billed for last month\'s use.'),
    simpleTable([
      { label: 'Add-on', render: (a) => cell(a.name, a.note) },
      { label: 'How It Is Charged', render: (a) => cell(a.model) },
      { label: 'Price', render: (a) => cell(a.price ? lkr(a.price) : 'By usage', a.model === 'One-time' ? 'once' : a.price ? 'a month' : null) },
      { label: 'Offered', render: (a) => sw(a.on, `Offer ${a.name}`, (e) => toggle(() => api.put(`/admin/addons/${a.key}`, { on: e.target.checked }), e.target.checked ? `${a.name} is offered.` : `${a.name} is no longer offered to new tutors.`)) },
      { label: '', render: (a) => h('button', { type: 'button', class: 'custom-btn ex-btn-sm px-3', onclick: () => editDialog(`Edit ${a.name}`, ['Add-ons'], 'box', [
        ['price', 'Price (LKR)', num(a.price, 'Enter the price'), 'Monthly add-ons: the price each month. One-time: the setup price. Empty for usage add-ons.'],
        ['model', 'How it is charged', h('select', { class: 'form-select custom-select' }, ['Monthly', 'Monthly + per payment', 'Pay as you go', 'Per conversation', 'One-time'].map((x) => h('option', { value: x, selected: x === a.model }, x))), null, true],
        ['note', 'Text shown to tutors', text(a.note, 'Enter how the price works in one line'), 'For example "LKR 2,000 a month + 2.5% of each card payment".', true, 'col-12'],
      ], (v) => api.put(`/admin/addons/${a.key}`, v), `${a.name} saved.`) }, 'Edit') },
    ], D.addons, { title: 'No add-ons', text: 'Add-ons appear here.' }, { fit: true }),
  ],
  rules: () => {
    const R = D.rules;
    const box = h('div');
    const rule = (title, info, control, key) => {
      if (key) { control.dataset.key = key; control.id = `rule-${key}`; }
      return h('div', { class: 'ad-rule' }, h('div', {}, h('div', { class: 'text-14 main-text fw-medium d-inline-flex align-items-center gap-2' }, title, infoTip(info, title))),
        h('div', {}, control, key ? h('div', { class: 'text-12 text-dark-red mt-1 d-none', 'data-err': `rule-${key}` }) : null));
    };
    const sel = (opts, v) => h('select', { class: 'form-select custom-select' }, opts.map(([k, t]) => h('option', { value: k, selected: k === v }, t)));
    const taxSw = sw(R.tax_on, 'Add VAT');
    const saveRules = async () => {
      const values = Object.fromEntries([...box.querySelectorAll('[data-key]')].map((el) => [el.dataset.key, el.value]));
      values.tax_on = taxSw.querySelector('input').checked;
      if (await trySave(box, () => api.put('/admin/billing-rules', values))) { Toast.success('Rules saved. They apply to new trials and the next invoices.'); reload(); }
    };
    fill(box,
      head('Trial & Billing Rules', 'The rules every tutor subscription follows. Changes apply to new trials and the next invoices.', h('button', { type: 'button', class: 'custom-btn px-4', onclick: saveRules }, 'Save Rules')),
      h('div', { class: 'text-14 main-text fw-semibold mt-2' }, 'Free trial'),
      rule('Trial length (days)', 'How long a new tutor can use ENLIXO Tutor free. The old system used 7 days.', num(R.trial_days, 'Enter the days'), 'trial_days'),
      rule('Trial plan', 'Which plan\'s features the tutor gets during the trial. GROW lets them see everything, which helps them choose.', sel(D.plans.map((p) => [p.key, p.name]), R.trial_plan), 'trial_plan'),
      rule('Students during the trial', 'Most students a tutor can add while on trial, so a trial is not used as a free plan. For example 30.', num(R.trial_student_cap, 'Enter the most students'), 'trial_student_cap'),
      h('div', { class: 'text-14 main-text fw-semibold mt-4' }, 'Invoices and payments'),
      rule('Make the renewal invoice (days before)', 'How many days before a period ends the next invoice is made. A free trial gets its first invoice at most 3 days before it ends. For example 7.', num(R.invoice_days_before, 'Enter the days'), 'invoice_days_before'),
      rule('Grace period (days after due)', 'Days a tutor can still work after the due date. After that the account is paused (suspended) until the payment is approved. For example 7.', num(R.grace_days, 'Enter the days'), 'grace_days'),
      rule('Reminders', 'How tutors hear about an invoice.', h('span', { class: 'text-14 sub-text text-end' }, R.reminders)),
      rule('Invoice number starts with', 'Letters in front of every invoice number. For example EN-INV gives EN-INV-2026-00001.', text(R.invoice_prefix, 'Enter the prefix'), 'invoice_prefix'),
      h('div', { class: 'text-14 main-text fw-semibold mt-4' }, 'Tax'),
      rule('Add VAT to invoices', 'Switch on only when ENVIIZ is VAT registered. VAT is then added to every new invoice.', taxSw),
      rule('VAT rate (%)', 'Sri Lanka VAT is 18% (2026). Used only while VAT is on.', num(R.tax_rate, 'Enter the rate'), 'tax_rate'));
    return [box];
  },
  banks: () => [
    head('Bank Accounts', 'Accounts tutors pay into. They are shown on the tutor\'s My Subscription page when they pay an invoice.', h('button', { type: 'button', class: 'custom-btn px-4 d-inline-flex align-items-center gap-2', onclick: () => bankDialog(null) }, icon('plus', { size: 18 }), 'Add Account')),
    simpleTable([
      { label: 'Bank', render: (b) => cell(b.bank, b.branch) },
      { label: 'Account', render: (b) => cell(b.number, b.name) },
      { label: 'Shown to Tutors', render: (b) => sw(b.on, `Show ${b.bank}`, (e) => toggle(() => api.put(`/admin/bank-accounts/${b.id}`, { on: e.target.checked }), e.target.checked ? 'Tutors see this account.' : 'Hidden from tutors.')) },
      { label: '', render: (b) => h('div', { class: 'd-flex gap-2' },
        h('button', { type: 'button', class: 'custom-btn ex-btn-sm px-3', onclick: () => bankDialog(b) }, 'Edit'),
        h('button', { type: 'button', class: 'custom-btn-outline ex-btn-sm px-3', onclick: () => removeBank(b) }, 'Remove')) },
    ], D.banks, { title: 'No bank accounts', text: 'Add the account tutors should pay into. Tutors cannot pay by bank until one is added.' }, { fit: true }),
  ],
};

function bankDialog(b) {
  editDialog(b ? `Edit ${b.bank}` : 'Add Bank Account', ['Shown to tutors'], 'bank', [
    ['bank', 'Bank', text(b?.bank, 'Enter the bank name'), null, true], ['branch', 'Branch', text(b?.branch, 'Enter the branch'), null, true],
    ['name', 'Account name', text(b?.name, 'Enter the name on the account'), null, true],
    ['number', 'Account number', text(b?.number, 'Enter the account number'), 'Exactly as on the passbook, so the tutor\'s transfer reaches it. For example 1000 4567 8901.', true],
  ], (v) => (b ? api.put(`/admin/bank-accounts/${b.id}`, v) : api.post('/admin/bank-accounts', v)), b ? 'Bank account saved.' : 'Bank account added.');
}
function removeBank(b) {
  const m = BsModal.open({ title: 'Remove this Bank Account?', subtitle: [b.bank, b.number], icon: 'danger', tone: 'danger',
    body: h('p', { class: 'text-14 sub-text m-0' }, 'Tutors will no longer see it. Payments already made to it are not changed.'),
    footerEnd: [BsModal.button('Cancel', { onclick: () => m.close() }), BsModal.button('Remove', { primary: true, danger: true, onclick: async () => {
      if (!await trySave(m.el, () => api.delete(`/admin/bank-accounts/${b.id}`))) return;
      m.close(); Toast.success('Bank account removed.'); reload();
    } })] });
}

const right = h('div', { class: 'col-12 col-lg-9 p-4' });
const nav = h('div', { class: 'col-12 col-lg-3 ex-side-nav p-3' });
function show(k) {
  tab = k;
  const u = new URLSearchParams(location.search); u.set('tab', k); history.replaceState(null, '', `?${u}`);
  fill(nav, h('div', { class: 'ex-side-group' }, 'Plans & Billing'), TABS.map(([key, label, ico]) => h('button', { type: 'button', class: `ex-side-item${key === k ? ' active' : ''}`, onclick: () => show(key) }, icon(ico, { size: 18 }), label)));
  fill(right, ...panels[k]());
  document.querySelectorAll('.ex-subnav-link').forEach((a) => { const on = a.getAttribute('href') === `plans?tab=${k}`; a.classList.toggle('ex-current', on); });
}
fill(root, previewNote(), h('div', { class: 'component-theme' }, h('div', { class: 'row g-0' }, nav, right)));
show(tab);
