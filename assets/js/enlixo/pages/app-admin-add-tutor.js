// Admin → Add Tutor (admin/add-tutor.php). One page: details → plan and billing → how to start, with a live summary.
// Saves with POST /admin/tutors; field errors come back by key (first_name, email, custom_price_reason ...).
import { QuickGuide } from '../components/QuickGuide.js';
import { api, h, fill, icon, lkr, day, plural, infoTip, previewNote, Toast } from './admin-common.js';

QuickGuide.attachAll();
const root = document.getElementById('add-tutor');
const { data: L } = await api.get('/admin/lookups');
const R = L.rules;
// Approving an online sign-up request (Requested Tutors → Approve): the form starts with the request's details,
// and saving makes the tutor through POST /admin/tutor-requests/{id}/approve, which also closes the request.
const requestId = Number(new URLSearchParams(location.search).get('request')) || null;
let REQ = null;
if (requestId) {
  try {
    REQ = (await api.get(`/admin/tutor-requests/${requestId}`)).data;
    if (REQ.status !== 'pending') { Toast.error(`This request was already ${REQ.status}.`); REQ = null; }
  } catch (e) { Toast.error(e.message); }
}
const plan = (k) => L.plans.find((p) => p.key === k);
const today = new Date(new Date().toDateString());
const plusDays = (n) => new Date(today.getTime() + n * 86400000).toISOString().slice(0, 10);
const plusMonths = (n) => { const d = new Date(today); d.setMonth(d.getMonth() + n); return d.toISOString().slice(0, 10); };

// ---------- small form helpers (V3 form look: label + star + (i), placeholder, error under the field) ----------
const inputs = {};
const fieldEl = (key, label, el, { required = false, info = null, col = 'col-12 col-md-6 col-lg-4' } = {}) => {
  el.id = `at-${key}`; el.dataset.key = key; inputs[key] = el;
  el.addEventListener('input', () => { el.classList.remove('is-invalid'); document.getElementById(`at-${key}-err`)?.classList.add('d-none'); summary(); });
  el.addEventListener('change', summary);
  return h('div', { class: col },
    h('label', { class: `text-14 main-text mb-1 d-inline-flex align-items-center gap-2 ${required ? 'required' : ''}`, for: el.id }, label, info ? infoTip(info, label) : null),
    el, h('div', { class: 'text-12 text-dark-red mt-1 d-none', id: `at-${key}-err` }));
};
const input = (placeholder, attrs = {}) => h('input', { class: 'form-control custom-input', placeholder, ...attrs });
const select = (opts, attrs = {}) => h('select', { class: 'form-select custom-select', ...attrs }, h('option', { value: '' }, '- Select -'), opts.map(([v, t]) => h('option', { value: v }, t)));
const section = (title, info, ...body) => h('div', { class: 'component-theme p-4 mb-4' },
  h('div', { class: 'sub-topic d-inline-flex align-items-center gap-2 mb-3' }, title, infoTip(info, title)), ...body);
const radio = (name, value, checked) => h('input', { type: 'radio', name, value, checked, onchange: summary });

// ---------- 1. tutor details ----------
const details = section('Tutor Details', 'Who the tutor is and how to reach them. The tutor signs in with this e-mail address: the first time, the sign-in page sends a code and asks them to set a password.',
  h('div', { class: 'row gx-4 gy-3' },
    fieldEl('title', 'Title', select(L.titles.map((t) => [String(t.id), t.name])), { required: true }),
    fieldEl('first_name', 'First Name', input('Enter the first name', { 'data-limit': '45' }), { required: true }),
    fieldEl('last_name', 'Last Name', input('Enter the last name', { 'data-limit': '45' }), { required: true }),
    fieldEl('email', 'E-mail Address', input('Enter the e-mail address', { type: 'email', 'data-limit': 'email' }), { required: true, info: 'The tutor signs in with this address and gets invoices and payment reminders here. It must not be used by another account. For example ruwan.perera@gmail.com.' }),
    fieldEl('mobile', 'Mobile Number', input('Enter the mobile number', { type: 'tel', 'data-limit': '15' }), { required: true, info: 'A Sri Lankan mobile number for calls about billing. 10 digits, for example 0771234567.' }),
    fieldEl('whatsapp', 'WhatsApp Number', input('Enter the WhatsApp number', { type: 'tel', 'data-limit': '15' }), { info: 'Payment reminders and invoices are also sent here. Leave empty when it is the same as the mobile number.' }),
    fieldEl('brand', 'Institute / Class Name', input('Enter the name students know the classes by', { 'data-limit': '100' }), { info: 'Shown on invoices and on the tutor\'s student pages. For example Perera Physics Academy.' }),
    fieldEl('subject', 'Main Subject', input('Enter the main subject taught', { 'data-limit': '60' })),
    fieldEl('note', 'Note for the Team', input('Enter a note only admins see', { 'data-limit': '200' }), { info: 'Only ENLIXO admins see this. For example "Met at the Colombo education fair".' })));

// ---------- 2. plan and billing ----------
const planCards = h('div', { class: 'row g-3' }, L.plans.map((p) => h('div', { class: 'col-12 col-md-4' },
  h('label', { class: 'ad-plan-card' }, radio('plan', p.key, p.key === 'grow'),
    h('span', { class: 'ad-pick-check' }, icon('check', { size: 16 })),
    h('span', { class: `ad-plan-tile ad-plan-${p.key}`, style: { width: '40px', height: '40px' } }, icon('crown', { size: 20 })),
    h('span', { class: 'main-topic mt-2' }, p.name),
    h('span', { class: 'text-12 sub-text' }, p.tagline),
    h('span', { class: 'ad-plan-price mt-1' }, lkr(p.monthly), h('span', { class: 'text-14 sub-text fw-normal' }, ' / month')),
    h('span', { class: 'text-13 main-text' }, `Up to ${p.limit} students`),
    h('ul', {}, p.features.map((f) => h('li', {}, f)))))));
const cycle = h('div', { class: 'ad-seg', role: 'radiogroup', 'aria-label': 'Billing' },
  h('label', {}, radio('cycle', 'monthly', true), 'Monthly'),
  h('label', {}, radio('cycle', 'yearly', false), 'Yearly'));
const yearlyNote = h('span', { class: 'text-12 sub-text' });
const packs = input('0', { type: 'number', min: 0, max: 20, value: 0 });
const addons = h('div', { class: 'row g-2' }, L.addons.filter((a) => a.on).map((a) => h('div', { class: 'col-12 col-md-6' },
  h('label', { class: 'ex-choice d-flex align-items-start gap-3 p-3 h-100' },
    h('input', { type: 'checkbox', class: 'form-check-input mt-1', name: 'addon', value: a.key, onchange: summary }),
    h('span', {}, h('span', { class: 'text-14 main-text fw-medium d-block' }, a.name), h('span', { class: 'text-12 sub-text' }, a.note))))));
const customOn = h('input', { type: 'checkbox', class: 'form-check-input', role: 'switch', id: 'at-custom-on', onchange: () => { customBox.classList.toggle('d-none', !customOn.checked); summary(); } });
const customBox = h('div', { class: 'row gx-4 gy-3 mt-1 d-none' },
  fieldEl('custom_price', 'Custom Monthly Price (LKR)', input('Enter the agreed monthly price', { type: 'number', min: 0 }), { required: true, info: 'The price this tutor pays instead of the plan price, for every month. For example 4500.' }),
  fieldEl('custom_price_reason', 'Reason', input('Say why this tutor gets a different price', { 'data-limit': '150' }), { required: true, col: 'col-12 col-lg-8', info: 'Kept in the tutor\'s history so the team knows why. For example "Launch offer for the first year".' }));

const planSection = section('Plan and Billing', 'What the tutor pays for. Prices come from Plans & Prices; a custom price is only for this tutor.',
  planCards,
  h('div', { class: 'row gx-4 gy-3 mt-2 align-items-end' },
    h('div', { class: 'col-12 col-lg-6' },
      h('div', { class: 'text-14 main-text mb-1 d-inline-flex align-items-center gap-2' }, 'Billing', infoTip('Monthly: an invoice every month. Yearly: one invoice for 12 months with the plan\'s yearly discount.', 'Billing')),
      h('div', { class: 'd-flex align-items-center gap-3 flex-wrap' }, cycle, yearlyNote)),
    fieldEl('packs', 'Extra Students (packs of 50)', packs, { col: 'col-12 col-lg-6', info: 'Each pack adds 50 students to the plan limit for LKR 1,000 a month (STARTER and GROW). Above 500 students use CUSTOM with an agreed price.' })),
  h('div', { class: 'text-14 main-text mt-4 mb-2' }, 'Add-ons'), addons,
  h('div', { class: 'form-check form-switch ex-switch-bs mt-3' }, customOn, h('label', { class: 'text-14 main-text', for: 'at-custom-on' }, 'Give this tutor a custom price')),
  customBox);

// ---------- 3. how to start ----------
const startChoice = (value, title, text, checked) => h('div', { class: 'col-12 col-md-4' }, h('label', { class: 'ex-choice d-flex align-items-start gap-3 p-3 h-100' },
  h('input', { type: 'radio', class: 'form-check-input mt-1', name: 'start', value, checked, onchange: () => { showStart(); summary(); } }),
  h('span', {}, h('span', { class: 'text-14 main-text fw-semibold d-block' }, title), h('span', { class: 'text-12 sub-text' }, text))));
const trialBox = h('div', { class: 'row gx-4 gy-3 mt-1' },
  fieldEl('trial_days', 'Trial Length (days)', input('Enter the number of days', { type: 'number', min: 1, max: 60, value: R.trial_days }), { required: true, info: `How many days the tutor can use ENLIXO Tutor free. The default comes from Trial & Billing Rules (${R.trial_days} days).` }),
  h('div', { class: 'col-12 col-lg-8 d-flex align-items-end' }, h('div', { class: 'ad-soft p-3 w-100 text-13 main-text' },
    `During the trial the tutor gets every ${plan(R.trial_plan).name} feature for up to ${R.trial_student_cap} students. No payment is needed. The first invoice is made 3 days before the trial ends.`)));
const paidBox = h('div', { class: 'row gx-4 gy-3 mt-1 d-none' },
  fieldEl('start_date', 'First Day of the Plan', input('Choose the first day', { type: 'date', value: plusDays(0) }), { required: true }),
  fieldEl('paid_now', 'Payment', select([['no', 'Not paid yet: send the invoice'], ['bank', 'Already paid by bank transfer'], ['cash', 'Already paid in cash']]), { required: true, info: 'If the tutor already paid, the first invoice is marked paid now. Otherwise it is sent and waits for the slip.' }),
  fieldEl('reference', 'Payment Reference', input('Enter the bank reference or receipt number', { 'data-limit': '60' }), { info: 'Bank reference or receipt number of the money received, for example 004512.' }));
const compBox = h('div', { class: 'row gx-4 gy-3 mt-1 d-none' },
  fieldEl('comp_until', 'Free Until', input('Choose the last free day', { type: 'date', value: plusMonths(3) }), { required: true }),
  fieldEl('comp_reason', 'Reason', input('Say why the access is free', { 'data-limit': '150' }), { required: true, col: 'col-12 col-lg-8' }));
inputs.paid_now.value = 'no';
const startSection = section('How to Start', 'Free trial: no payment now. Paid from today: the first invoice is made at once. Complimentary: free access until a date (partners, demos).',
  h('div', { class: 'row g-3' },
    startChoice('trial', 'Free trial', `${R.trial_days} days free on ${plan(R.trial_plan).name}, then choose a plan`, true),
    startChoice('paid', 'Paid from today', 'The first invoice is made now'),
    startChoice('comp', 'Complimentary', 'Free until a date you choose')),
  trialBox, paidBox, compBox);
function showStart() {
  const v = val('start');
  trialBox.classList.toggle('d-none', v !== 'trial');
  paidBox.classList.toggle('d-none', v !== 'paid');
  compBox.classList.toggle('d-none', v !== 'comp');
}

// ---------- summary ----------
const sumBox = h('div', { class: 'component-theme p-4 ad-summary' });
function val(name) { return root.querySelector(`input[name="${name}"]:checked`)?.value; }
function summary() {
  const p = plan(val('plan'));
  const yearly = val('cycle') === 'yearly';
  const n = Math.max(0, Number(packs.value) || 0);
  const monthly = customOn.checked && Number(inputs.custom_price.value) > 0 ? Number(inputs.custom_price.value) : p.monthly;
  const planCost = yearly ? (monthly - p.yearly_discount) * 12 : monthly;
  const months = yearly ? 12 : 1;
  const packCost = p.pack_price ? n * p.pack_price * months : 0;
  const picked = [...root.querySelectorAll('input[name="addon"]:checked')].map((x) => L.addons.find((a) => a.key === x.value));
  const monthlyAddon = (x) => x.price && !['One-time', 'Pay as you go', 'Per conversation'].includes(x.model);
  const addonCost = picked.reduce((a, x) => a + (monthlyAddon(x) ? x.price * months : 0), 0);
  const total = planCost + packCost + addonCost;
  packs.disabled = !p.pack_price;
  yearlyNote.textContent = `Yearly saves ${lkr(p.yearly_discount * 12)} on ${p.name}`;
  const start = val('start');
  const name = `${inputs.first_name.value} ${inputs.last_name.value}`.trim() || 'New tutor';
  const days = Math.max(1, Number(inputs.trial_days.value) || R.trial_days);
  const steps = {
    trial: [`${name} gets every ${plan(R.trial_plan).name} feature for ${plural(days, 'day')} (up to ${R.trial_student_cap} students).`, `The trial ends on ${day(plusDays(days))}. The first ${p.name} invoice of ${lkr(total)} is made 3 days before and shows on the tutor's My Subscription page.`, `Not paid ${plural(R.grace_days, 'day')} after the trial ends: the account is paused until the slip is approved.`],
    paid: [`The first invoice of ${lkr(total)} is made today for ${yearly ? '12 months' : '1 month'}.`, inputs.paid_now.value === 'no' ? `It is due on ${day(inputs.start_date.value)}; the tutor uploads the slip on their Billing page.` : 'It is marked paid, so the plan starts at once.', `Next renewal: ${day(plusMonths(months))}.`],
    comp: [`${name} uses ${p.name} free until ${day(inputs.comp_until.value)}.`, `No invoice now. The first invoice is made ${plural(R.invoice_days_before, 'day')} before the free period ends.`],
  }[start];
  const row = (a, b) => h('div', { class: 'ad-sum-row' }, h('span', { class: 'sub-text' }, a), h('span', { class: 'main-text fw-medium text-end' }, b));
  fill(sumBox,
    h('div', { class: 'sub-topic mb-2' }, 'Summary'),
    row('Tutor', name),
    row('Plan', `${p.name}, ${yearly ? 'yearly' : 'monthly'}`),
    row('Students', `Up to ${p.limit + (p.pack_price ? n * 50 : 0)}`),
    row(yearly ? 'Plan, 12 months' : 'Plan, 1 month', `${lkr(planCost)}${customOn.checked && inputs.custom_price.value ? ' (custom)' : ''}`),
    packCost ? row(`Extra students, ${n} × 50`, lkr(packCost)) : null,
    ...picked.map((a) => row(a.name, monthlyAddon(a) ? lkr(a.price * months) : a.model === 'One-time' ? `${lkr(a.price)} once, billed apart` : 'by usage')),
    h('div', { class: 'ad-sum-row' }, h('span', { class: 'main-text fw-semibold' }, start === 'comp' ? 'To pay' : 'Each invoice'), h('span', { class: 'main-topic' }, start === 'comp' ? lkr(0) : lkr(total))),
    h('div', { class: 'text-14 main-text fw-semibold mt-4 mb-2' }, 'What happens when you save'),
    h('ol', { class: 'ad-steps' }, steps.map((s, i) => h('li', {}, h('span', { class: 'ad-step-dot' }, String(i + 1)), h('span', {}, s)))),
    h('button', { type: 'button', class: 'custom-btn w-100 mt-3', onclick: save }, REQ ? 'Approve and Add Tutor' : 'Add Tutor'));
}

async function save() {
  const body = Object.fromEntries(Object.entries(inputs).map(([k, el]) => [k, el.value]));
  Object.assign(body, { plan: val('plan'), cycle: val('cycle'), start: val('start'), addons: [...root.querySelectorAll('input[name="addon"]:checked')].map((x) => x.value), custom_price: customOn.checked ? body.custom_price : '', custom_price_reason: customOn.checked ? body.custom_price_reason : '' });
  root.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
  root.querySelectorAll('[id$="-err"]').forEach((el) => el.classList.add('d-none'));
  try {
    const { data } = await api.post(REQ ? `/admin/tutor-requests/${REQ.id}/approve` : '/admin/tutors', body);
    Toast.success(`${body.first_name} ${body.last_name} added. They can sign in with ${body.email}.${REQ ? (data.mailed ? ' A welcome e-mail was sent.' : ' The welcome e-mail could not be sent; tell them by phone.') : ''}`);
    setTimeout(() => { location.href = `tutor-details?tutor-id=${data.id}`; }, 900);
  } catch (e) {
    const errs = e.details ?? {};
    let first = null;
    Object.entries(errs).forEach(([k, msg]) => { const el = inputs[k]; if (!el) return; el.classList.add('is-invalid'); const er = document.getElementById(`at-${k}-err`); er.textContent = msg; er.classList.remove('d-none'); first ??= el; });
    first?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    Toast.error(e.message);
  }
}

document.getElementById('add-tutor-actions').append(
  h('a', { class: 'ex-gear', href: 'plans?tab=rules', title: 'Trial & Billing Rules', 'aria-label': 'Trial & Billing Rules' }, icon('settings', { size: 30 })),
  h('button', { type: 'button', class: 'custom-btn px-5', onclick: save }, REQ ? 'Approve and Add Tutor' : 'Add Tutor'));

if (REQ) {
  const set = (k, val) => { if (inputs[k] && val !== null && val !== undefined && val !== '') inputs[k].value = String(val); };
  set('title', REQ.title_id); set('first_name', REQ.first_name); set('last_name', REQ.last_name); set('email', REQ.email);
  set('mobile', REQ.mobile); if (REQ.whatsapp && REQ.whatsapp !== REQ.mobile) set('whatsapp', REQ.whatsapp);
  set('brand', REQ.institute); set('subject', REQ.subject); set('note', `Online request ${REQ.ref}`);
  [planCards, cycle, addons].forEach((n) => n.querySelectorAll('input').forEach((x) => {
    if (x.name === 'plan' && REQ.plan) x.checked = x.value === (REQ.plan === 'free' ? 'start' : REQ.plan);
    if (x.name === 'cycle') x.checked = x.value === REQ.cycle;
    if (x.name === 'addon') x.checked = REQ.addons.includes(x.value);
  }));
}
// FREE (up to 50 students): START with complimentary access, so no invoice is made while the class stays small.
if (REQ?.plan === 'free') {
  startSection.querySelectorAll('input[name="start"]').forEach((x) => { x.checked = x.value === 'comp'; });
  inputs.comp_until.value = plusMonths(12);
  inputs.comp_reason.value = 'FREE plan: up to 50 students';
}
const reqBanner = REQ ? h('div', { class: 'col-12 ad-soft p-3 mb-4 d-flex align-items-center gap-3 flex-wrap' },
  icon('application', { size: 22 }),
  h('div', { class: 'flex-grow-1' },
    h('div', { class: 'text-14 main-text fw-semibold' }, `Approving request ${REQ.ref} from ${REQ.name}`),
    h('div', { class: 'text-13 sub-text' }, [REQ.subject, REQ.mode_label, REQ.district, REQ.students ? `~${REQ.students} students` : null, REQ.plan === 'free' ? 'asked for the FREE plan (up to 50 students): START, complimentary' : `asked for ${(REQ.plan ?? '').toUpperCase()} ${REQ.cycle}`].filter(Boolean).join(' · '),
      REQ.addons.length ? ` · add-ons: ${REQ.addons.join(', ').replaceAll('_', ' ')}` : ''),
    REQ.message ? h('div', { class: 'text-13 main-text mt-1' }, `"${REQ.message}"`) : null),
  h('a', { class: 'custom-btn-outline px-3 d-inline-flex align-items-center text-decoration-none', href: `requested-users?request=${REQ.id}` }, 'View request')) : null;
fill(root, previewNote(), reqBanner, h('div', { class: 'row g-4' },
  h('div', { class: 'col-12 col-xl-8' }, details, planSection, startSection),
  h('div', { class: 'col-12 col-xl-4' }, sumBox)));
summary();
showStart(); // after the form is on the page (a FREE request starts as Complimentary)
