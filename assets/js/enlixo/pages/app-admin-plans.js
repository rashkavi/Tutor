// Admin → Plans & Billing (admin/plans.php?tab=plans|addons|rules|banks). V3 settings layout: left menu, right panel.
// Data: GET /admin/plans; saves: PUT /admin/plans/{key}, /admin/addons/{key}, /admin/billing-rules, /admin/bank-accounts.
import { QuickGuide } from '../components/QuickGuide.js';
import { api, h, fill, icon, lkr, plural, infoTip, simpleTable, cell, field, previewNote, BsModal, Toast } from './admin-common.js';

QuickGuide.attachAll();
const root = document.getElementById('plans-page');
const { data: D } = await api.get('/admin/plans');
const TABS = [
  ['plans', 'Plans & Prices', 'plans'], ['addons', 'Add-ons', 'box'], ['rules', 'Trial & Billing Rules', 'hourglass'], ['banks', 'Bank Accounts', 'bank'],
];
let tab = new URLSearchParams(location.search).get('tab');
if (!TABS.some(([k]) => k === tab)) tab = 'plans';

const num = (value, ph, attrs = {}) => h('input', { class: 'form-control custom-input', type: 'number', min: 0, value: value ?? '', placeholder: ph, ...attrs });
const text = (value, ph, attrs = {}) => h('input', { class: 'form-control custom-input', value: value ?? '', placeholder: ph, 'data-limit': 'text', ...attrs });
const sw = (checked, label) => { const id = `sw-${Math.random().toString(36).slice(2, 7)}`; return h('div', { class: 'form-check form-switch ex-switch-bs m-0' }, h('input', { type: 'checkbox', class: 'form-check-input', role: 'switch', id, checked }), h('label', { class: 'visually-hidden', for: id }, label)); };
const head = (title, info, action = null) => h('div', { class: 'd-flex justify-content-between align-items-center gap-3 mb-3' },
  h('span', { class: 'sub-topic d-inline-flex align-items-center gap-2' }, title, infoTip(info, title)), action);
const editDialog = (title, sub, ico, fields, done) => {
  const m = BsModal.open({ title, subtitle: sub, icon: ico, body: h('div', { class: 'row gx-3' }, fields.map(([label, el, info, req, col]) => h('div', { class: col ?? 'col-md-6' }, field(label, el, { info, required: req })))),
    footerEnd: [BsModal.button('Cancel', { onclick: () => m.close() }), BsModal.button('Save', { primary: true, onclick: async () => { await api.put('/admin/settings', {}); m.close(); Toast.success(done); } })] });
};

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
        ['Monthly price (LKR)', num(p.monthly, 'Enter the monthly price'), 'What a tutor pays each month on this plan. The pricing sheet range is used as a guide, for example 5000.', true],
        ['Yearly discount a month (LKR)', num(p.yearly_discount, 'Enter the discount per month'), 'Taken off each month when a tutor pays for 12 months at once. For example 500 = LKR 6,000 off a year.'],
        ['Student limit', num(p.limit, 'Enter the most students allowed'), 'Most active students a tutor can have on this plan. For example 150.', true],
        ['Extra 50 students (LKR a month)', num(p.pack_price, 'Leave empty for a custom price'), 'Price of one pack of 50 more students. Empty = not sold, use a custom price (PRO).'],
        ['Short description', text(p.tagline, 'Enter one line shown under the plan name'), null, false, 'col-12'],
      ], `${p.name} saved. New prices apply from each tutor's next invoice.`) }, 'Edit') },
    ], D.plans, { title: 'No plans', text: 'Plans appear here.' }, { fit: true }),
    h('p', { class: 'text-12 sub-text mt-3 mb-0' }, 'Each plan\'s features follow the ENLIXO Tutor pricing sheet. Tutors see the features of other plans with a lock badge and an Upgrade button.'),
  ],
  addons: () => [
    head('Add-ons', 'Extra services a tutor can buy on any plan. Monthly add-ons are added to every invoice; usage add-ons are billed for last month\'s use.'),
    simpleTable([
      { label: 'Add-on', render: (a) => cell(a.name, a.note) },
      { label: 'How It Is Charged', render: (a) => cell(a.model) },
      { label: 'Price', render: (a) => cell(a.price ? lkr(a.price) : 'By usage', a.model === 'One-time' ? 'once' : a.price ? 'a month' : null) },
      { label: 'Offered', render: (a) => sw(a.on, `Offer ${a.name}`) },
      { label: '', render: (a) => h('button', { type: 'button', class: 'custom-btn ex-btn-sm px-3', onclick: () => editDialog(`Edit ${a.name}`, ['Add-ons'], 'box', [
        ['Price (LKR)', num(a.price, 'Enter the price'), 'Monthly add-ons: the price each month. One-time: the setup price. Empty for usage add-ons.'],
        ['How it is charged', h('select', { class: 'form-select custom-select' }, ['Monthly', 'Monthly + per payment', 'Pay as you go', 'Per conversation', 'One-time'].map((x) => h('option', { selected: x === a.model }, x))), null, true],
        ['Text shown to tutors', text(a.note, 'Enter how the price works in one line'), 'For example "LKR 2,000 a month + 2.5% of each card payment".', true, 'col-12'],
      ], `${a.name} saved.`) }, 'Edit') },
    ], D.addons, { title: 'No add-ons', text: 'Add-ons appear here.' }, { fit: true }),
  ],
  rules: () => {
    const R = D.rules;
    const rule = (title, info, control) => h('div', { class: 'ad-rule' }, h('div', {}, h('div', { class: 'text-14 main-text fw-medium d-inline-flex align-items-center gap-2' }, title, infoTip(info, title))), control);
    const sel = (opts, v) => h('select', { class: 'form-select custom-select' }, opts.map(([k, t]) => h('option', { value: k, selected: k === v }, t)));
    return [
      head('Trial & Billing Rules', 'The rules every tutor subscription follows. Changes apply to new trials and the next invoices.', h('button', { type: 'button', class: 'custom-btn px-4', onclick: async () => { await api.put('/admin/billing-rules', {}); Toast.success('Rules saved.'); } }, 'Save Rules')),
      h('div', { class: 'text-14 main-text fw-semibold mt-2' }, 'Free trial'),
      rule('Trial length (days)', 'How long a new tutor can use ENLIXO Tutor free. The old system used 7 days.', num(R.trial_days, 'Enter the days')),
      rule('Trial plan', 'Which plan\'s features the tutor gets during the trial. PRO lets them see everything, which helps them choose.', sel([['start', 'START'], ['grow', 'GROW'], ['pro', 'PRO']], R.trial_plan)),
      rule('Students during the trial', 'Most students a tutor can add while on trial, so a trial is not used as a free plan. For example 30.', num(R.trial_student_cap, 'Enter the most students')),
      h('div', { class: 'text-14 main-text fw-semibold mt-4' }, 'Invoices and payments'),
      rule('Make the renewal invoice (days before)', 'How many days before a period ends the next invoice is made and sent. For example 7.', num(R.invoice_days_before, 'Enter the days')),
      rule('Grace period (days after due)', 'Days a tutor can still work after the due date before being suspended. For example 7.', num(R.grace_days, 'Enter the days')),
      rule('Reminders', 'When payment reminders go out by e-mail and WhatsApp.', h('span', { class: 'text-14 sub-text text-end' }, R.reminders)),
      rule('Invoice number starts with', 'Letters in front of every invoice number. For example EN-INV gives EN-INV-2026-00001.', text(R.invoice_prefix, 'Enter the prefix')),
      h('div', { class: 'text-14 main-text fw-semibold mt-4' }, 'Tax'),
      rule('Add VAT to invoices', 'Switch on only when ENVIIZ is VAT registered. The VAT number then shows on every invoice.', sw(R.tax_on, 'Add VAT')),
      rule('VAT rate (%)', 'Sri Lanka VAT is 18% (2026). Used only while VAT is on.', num(R.tax_rate, 'Enter the rate')),
    ];
  },
  banks: () => [
    head('Bank Accounts', 'Accounts tutors pay into. They are shown on the tutor\'s Billing page and on every invoice.', h('button', { type: 'button', class: 'custom-btn px-4 d-inline-flex align-items-center gap-2', onclick: () => editDialog('Add Bank Account', ['Shown to tutors'], 'bank', [
      ['Bank', text('', 'Enter the bank name'), null, true], ['Branch', text('', 'Enter the branch'), null, true],
      ['Account name', text('', 'Enter the name on the account'), null, true], ['Account number', text('', 'Enter the account number'), 'Exactly as on the passbook, for example 1000 4567 8901.', true],
    ], 'Bank account added.') }, icon('plus', { size: 18 }), 'Add Account')),
    simpleTable([
      { label: 'Bank', render: (b) => cell(b.bank, b.branch) },
      { label: 'Account', render: (b) => cell(b.number, b.name) },
      { label: 'Shown to Tutors', render: (b) => sw(b.on, `Show ${b.bank}`) },
    ], D.banks, { title: 'No bank accounts', text: 'Add the account tutors should pay into.' }, { fit: true }),
  ],
};

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
