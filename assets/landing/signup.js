// Online tutor sign-up (tutor-registration-application.php). Four steps: About you → Your classes → Plan → Review.
// GET /public/tutor-signup gives plans, add-ons, titles and districts; POST /public/tutor-signup sends the request.
// ?plan=grow&cycle=yearly (from the landing page) pre-selects the plan. A draft is kept in this tab while typing.
import { h, fill } from '../js/enlixo/core/dom.js';
import { ApiClient } from '../js/enlixo/core/ApiClient.js';

const api = new ApiClient();
const app = document.getElementById('su-app');
const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
const lkr = (n) => `LKR ${Math.round(n).toLocaleString('en-US')}`;
const ic = (d, s = 16) => h('span', { class: 'lp-ic', 'aria-hidden': 'true', html: `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${d}</svg>` });
const I = {
  check: '<path d="m5 12 4.5 4.5L19 7"/>',
  arrow: '<path d="M5 12h14M13 6l6 6-6 6"/>',
  back: '<path d="M19 12H5M11 6l-6 6 6 6"/>',
  gift: '<rect x="3" y="8" width="18" height="13" rx="1"/><path d="M12 8v13M3 12h18M12 8c-2-4-6-4-6-1.5S9 8 12 8c3 0 6-.5 6-1.5S14 4 12 8"/>',
  phone: '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
  mail: '<rect x="3" y="5" width="18" height="14" rx="1"/><path d="m3 7 9 6 9-6"/>',
  rocket: '<path d="M5 15c-1.5 1.5-2 5-2 5s3.5-.5 5-2M9 15l-3-3c1-4 4.5-8 12-9-1 7.5-5 11-9 12z"/><circle cx="15" cy="9" r="1.5"/>',
  building: '<path d="M4 21V5l8-3v19M12 9h8v12M8 8h.01M8 12h.01M8 16h.01M16 13h.01M16 17h.01"/>',
  wifi: '<path d="M2 9a15 15 0 0 1 20 0M5 12.5a10 10 0 0 1 14 0M8.5 16a5 5 0 0 1 7 0M12 19.5h.01"/>',
  both: '<rect x="3" y="4" width="12" height="9" rx="1"/><path d="M7 17h4M9 13v4M17 9h4v11h-6v-4"/>',
};

const STEPS = [
  { key: 'you', title: 'About you', sub: 'Who you are' },
  { key: 'classes', title: 'Your classes', sub: 'What you teach' },
  { key: 'plan', title: 'Plan', sub: 'Pick what fits' },
  { key: 'review', title: 'Review', sub: 'Check and send' },
];
const FIELD_STEP = { title: 0, first_name: 0, last_name: 0, email: 0, mobile: 0, whatsapp: 0, nic: 0, subject: 1, grades: 1, institute: 1, mode: 1, district: 1, students: 1, heard_from: 1, plan: 2, cycle: 2, addons: 2, message: 3, agree: 3 };
const DRAFT = 'enlixo-tutor-signup';

const q = new URLSearchParams(location.search);
let D = null; // lookups
let step = 0;
let errors = {};
const v = { title: '', first_name: '', last_name: '', email: '', mobile: '', whatsapp: '', nic: '', subject: '', grades: '', institute: '', mode: '', district: '', students: '', heard_from: '', plan: 'grow', cycle: 'monthly', addons: [], message: '', agree: false, website: '' };
try { Object.assign(v, JSON.parse(sessionStorage.getItem(DRAFT) || '{}')); } catch { /* private mode */ }
if (q.get('plan')) v.plan = q.get('plan');
if (q.get('promo')) v.promo = q.get('promo').toUpperCase();
if (q.get('cycle') === 'yearly' || q.get('cycle') === 'monthly') v.cycle = q.get('cycle');
const save = () => { try { sessionStorage.setItem(DRAFT, JSON.stringify({ ...v, agree: false, website: '' })); } catch { /* ignore */ } };

// language buttons (same choice as the landing page and the app)
const setButtons = (lang) => document.querySelectorAll('[data-set-lang]').forEach((b) => b.classList.toggle('on', b.dataset.setLang === lang));
setButtons(window.LP_LANG || 'en');
if ((window.LP_LANG || 'en') !== 'en') window.EnlixoI18n?.apply(window.LP_LANG);
document.querySelectorAll('[data-set-lang]').forEach((b) => b.addEventListener('click', () => {
  setButtons(b.dataset.setLang);
  window.EnlixoI18n?.apply(b.dataset.setLang);
  try { localStorage.setItem('lang', b.dataset.setLang); } catch { /* private mode */ }
}));

load();

async function load() {
  try {
    D = (await api.get('/public/tutor-signup')).data;
  } catch (e) {
    fill(app, h('div', { class: 'su-done' }, h('h2', {}, 'The form could not load'), h('p', {}, 'Please check your connection and reload the page.'),
      h('button', { type: 'button', class: 'lp-btn lp-btn-primary', style: { marginTop: '20px' }, onclick: () => location.reload() }, 'Reload')));
    return;
  }
  if (!D.plans.some((p) => p.key === v.plan)) v.plan = D.plans[1]?.key ?? D.plans[0]?.key;
  const pill = document.getElementById('su-trial-pill');
  if (pill) pill.textContent = `Free up to ${D.plans.find((x) => x.free)?.limit ?? 50} students, no card needed`;
  render();
}

// ---------------------------------------------------------------- fields
function field(key, label, control, { req = false, opt = false, span = '', hint = null } = {}) {
  const id = `su-${key}`;
  control.id = id;
  if (errors[key]) { control.classList.add('bad'); control.setAttribute('aria-invalid', 'true'); control.setAttribute('aria-describedby', `${id}-err`); }
  return h('div', { class: `su-f ${span}` },
    h('label', { for: id }, label, req ? h('span', { class: 'req', 'aria-hidden': 'true' }, '*') : null, opt ? h('span', { class: 'opt' }, 'optional') : null),
    control,
    hint && !errors[key] ? h('span', { class: 'su-hint' }, hint) : null,
    errors[key] ? h('span', { class: 'su-err', id: `${id}-err`, role: 'alert' }, errors[key]) : null);
}
function input(key, attrs = {}) {
  return h('input', { class: 'su-in', value: v[key] ?? '', name: key, oninput: (e) => { v[key] = e.target.value; clearErr(key, e.target); save(); }, ...attrs });
}
function select(key, options, placeholder) {
  const el = h('select', { class: 'su-in', name: key, onchange: (e) => { v[key] = e.target.value; clearErr(key, e.target); save(); } },
    h('option', { value: '' }, placeholder), options.map(([val, text]) => h('option', { value: val, selected: String(v[key]) === String(val) }, text)));
  return el;
}
function clearErr(key, el) {
  if (!errors[key]) return;
  delete errors[key];
  el.classList.remove('bad');
  el.removeAttribute('aria-invalid');
  el.closest('.su-f')?.querySelector('.su-err')?.remove();
}

// ---------------------------------------------------------------- steps
function stepYou() {
  return [
    h('h2', {}, 'Tell us about you'),
    h('p', {}, 'We use these details to make your account and to call you about your setup.'),
    h('div', { class: 'su-grid' },
      field('title', 'Title', select('title', D.titles.map((t) => [t.id, t.name]), 'Select'), { req: true, span: 's2' }),
      field('first_name', 'First name', input('first_name', { autocomplete: 'given-name', maxlength: 45, placeholder: 'Ruwan' }), { req: true, span: 's2' }),
      field('last_name', 'Last name', input('last_name', { autocomplete: 'family-name', maxlength: 45, placeholder: 'Perera' }), { req: true, span: 's2' }),
      field('email', 'E-mail address', input('email', { type: 'email', autocomplete: 'email', maxlength: 100, placeholder: 'ruwan.perera@gmail.com' }), { req: true, span: 's4', hint: 'You will sign in with this address.' }),
      field('nic', 'NIC', input('nic', { maxlength: 12, placeholder: '199012345678' }), { opt: true, span: 's2' }),
      field('mobile', 'Mobile number', input('mobile', { type: 'tel', autocomplete: 'tel', maxlength: 15, placeholder: '0771234567' }), { req: true }),
      field('whatsapp', 'WhatsApp number', input('whatsapp', { type: 'tel', maxlength: 15, placeholder: 'Same as mobile' }), { opt: true, hint: 'Leave empty if it is the same as your mobile.' })),
  ];
}

function stepClasses() {
  const modeIcon = { physical: I.building, online: I.wifi, both: I.both };
  return [
    h('h2', {}, 'Tell us about your classes'),
    h('p', {}, 'This helps us set up your account the way you teach.'),
    h('div', { class: 'su-grid' },
      field('subject', 'Main subject', input('subject', { maxlength: 60, placeholder: 'Physics' }), { req: true }),
      field('grades', 'Grades or exams', input('grades', { maxlength: 100, placeholder: 'A/L 2026, 2027' }), { opt: true }),
      field('institute', 'Institute or class name', input('institute', { maxlength: 100, placeholder: 'Perera Physics Academy' }), { opt: true, span: 's6', hint: 'The name your students know your classes by.' }),
      h('div', { class: 'su-f s6' },
        h('label', { id: 'su-mode-l' }, 'How do you teach?', h('span', { class: 'req', 'aria-hidden': 'true' }, '*')),
        h('div', { class: 'su-choices', role: 'radiogroup', 'aria-labelledby': 'su-mode-l' },
          Object.entries(D.modes).map(([k, label]) => h('label', { class: 'su-choice' },
            h('input', { type: 'radio', name: 'mode', value: k, checked: v.mode === k, onchange: () => { v.mode = k; delete errors.mode; save(); document.querySelector('.su-f .su-err[data-for=mode]')?.remove(); } }),
            h('span', { class: 'lp-dot lp-blue' }, ic(modeIcon[k], 18)), label))),
        errors.mode ? h('span', { class: 'su-err', role: 'alert', 'data-for': 'mode' }, errors.mode) : null),
      field('district', 'District', select('district', D.districts.map((d) => [d, d]), 'Select'), { opt: true, span: 's2' }),
      field('students', 'About how many students?', input('students', { type: 'number', min: 1, inputmode: 'numeric', placeholder: '120' }), { opt: true, span: 's2' }),
      field('heard_from', 'How did you hear about us?', select('heard_from', D.heard.map((x) => [x, x]), 'Select'), { opt: true, span: 's2' })),
  ];
}

function promoFor(p) {
  const pr = v.promo ? D.promos?.[v.promo] : null;
  return pr && pr.plan === p.key ? pr : null;
}

function priceOf(p) {
  return v.cycle === 'yearly' ? Math.max(0, p.monthly - p.yearly_discount) : p.monthly;
}

function stepPlan() {
  const yearlyOn = D.plans.some((p) => p.yearly_discount > 0);
  const suggest = Number(v.students) > 0 ? D.plans.find((p) => p.limit === null || p.limit >= Number(v.students))?.key : null;
  const chosen = D.plans.find((p) => p.key === v.plan);
  return [
    h('h2', {}, 'Pick a plan'),
    h('p', {}, `FREE up to ${D.plans.find((x) => x.free)?.limit ?? 50} students. Paid plans start with a ${D.trial.days}-day free trial. You can change your plan later.`),
    yearlyOn ? h('div', { class: 'su-cycle', role: 'radiogroup', 'aria-label': 'Billing' },
      ['monthly', 'yearly'].map((c) => h('button', { type: 'button', role: 'radio', 'aria-checked': String(v.cycle === c), class: v.cycle === c ? 'on' : '', onclick: () => { v.cycle = c; save(); render(); } },
        c === 'monthly' ? 'Monthly' : 'Yearly', c === 'yearly' ? h('span', { class: 'lp-save' }, 'Save more') : null))) : null,
    h('div', { class: 'su-plans', role: 'radiogroup', 'aria-label': 'Plan' },
      D.plans.map((p) => h('label', { class: `su-plan su-plan-${p.key}` },
        h('input', { type: 'radio', name: 'plan', value: p.key, checked: v.plan === p.key, onchange: () => { v.plan = p.key; save(); render(); app.querySelector(`input[name=plan][value=${p.key}]`)?.focus(); } }),
        p.key === (suggest ?? 'grow') ? h('span', { class: `su-plan-pop${p.free ? ' su-plan-pop-free' : ''}` }, suggest ? 'Fits your class' : 'Most popular') : null,
        h('span', { class: 'su-plan-check' }, ic(I.check, 14)),
        h('span', { class: 'su-plan-name' }, p.name),
        h('span', { class: 'su-plan-price' }, p.free ? 'Free' : p.custom ? "Let's talk" : promoFor(p) ? [h('s', { class: 'su-old' }, lkr(priceOf(p))), ' ', lkr(Math.round(priceOf(p) * (100 - promoFor(p).percent) / 1000) * 10)] : lkr(priceOf(p)), p.custom ? null : h('small', {}, p.free ? ' always' : ' / month')),
        h('span', { class: 'su-plan-meta' }, p.custom ? 'Any size, price agreed with our team' : p.limit ? `Up to ${p.limit} students` : 'Unlimited students', p.free ? ' · STARTER features' : !p.custom && v.cycle === 'yearly' && p.yearly_discount ? ` · ${lkr(priceOf(p) * 12)} a year` : ''),
        h('ul', {},
          p.everything_in ? h('li', {}, ic(I.check, 13), h('b', {}, `Everything in ${p.everything_in}`)) : null,
          p.new_features.slice(0, p.everything_in ? 4 : 5).map((f) => h('li', {}, ic(I.check, 13), f)))))),
    errors.plan ? h('span', { class: 'su-err', role: 'alert' }, errors.plan) : null,
    chosen?.custom
      ? h('div', { class: 'su-trial' }, h('span', { class: 'lp-dot lp-blue' }, ic(I.phone, 20)),
        h('span', {}, h('b', {}, 'We call you to make your plan.'), ' Write your classes and the add-ons you need in the message on the last step, and we agree one price that fits.'))
      : chosen?.free
      ? h('div', { class: 'su-trial su-trial-free' }, h('span', { class: 'lp-dot lp-green' }, ic(I.gift, 20)),
        h('span', {}, h('b', {}, `Free while you teach up to ${chosen.limit} students.`), ' No card and no invoices. When your class grows, you pick a paid plan and keep everything.'))
      : h('div', { class: 'su-trial' }, h('span', { class: 'lp-dot lp-violet' }, ic(I.gift, 20)),
        h('span', {}, h('b', {}, `${D.trial.days} days free on ${D.trial.plan}`), ` for up to ${D.trial.students} students. No card needed. You pay only after the trial, for the plan you keep.`)),
    D.addons.length ? h('div', { class: 'su-section-t' }, 'Interested in an add-on? ', h('span', { class: 'su-hint' }, '(optional, we will talk about it)')) : null,
    D.addons.length ? h('div', { class: 'su-addons' }, D.addons.map((a) => h('label', { class: 'su-addon' },
      h('input', { type: 'checkbox', value: a.key, checked: v.addons.includes(a.key), onchange: (e) => { v.addons = e.target.checked ? [...new Set([...v.addons, a.key])] : v.addons.filter((x) => x !== a.key); save(); } }),
      h('span', {}, h('b', {}, a.name), h('small', {}, a.note))))) : null,
  ];
}

function stepReview() {
  const p = D.plans.find((x) => x.key === v.plan);
  const title = D.titles.find((t) => String(t.id) === String(v.title))?.name ?? '';
  const row = (k, val) => (val ? [h('dt', {}, k), h('dd', {}, val)] : null);
  const edit = (i) => h('button', { type: 'button', onclick: () => go(i) }, 'Edit');
  return [
    h('h2', {}, 'Check and send'),
    h('p', {}, 'Make sure everything is right. Our team will contact you on these numbers.'),
    h('div', { class: 'su-review' },
      h('div', { class: 'su-card' }, h('h3', {}, 'About you', edit(0)), h('dl', {},
        row('Name', `${title ? `${title}. ` : ''}${v.first_name} ${v.last_name}`), row('E-mail', v.email), row('Mobile', v.mobile), row('WhatsApp', v.whatsapp || 'Same as mobile'), row('NIC', v.nic))),
      h('div', { class: 'su-card' }, h('h3', {}, 'Your classes', edit(1)), h('dl', {},
        row('Subject', v.subject), row('Grades', v.grades), row('Institute', v.institute), row('Teaching', D.modes[v.mode]), row('District', v.district), row('Students', v.students))),
      h('div', { class: 'su-card', style: { gridColumn: '1 / -1' } }, h('h3', {}, 'Plan', edit(2)), h('dl', {},
        row('Plan', p ? (p.free ? `${p.name}, up to ${p.limit} students` : `${p.name}, ${v.cycle}`) : ''),
        row('Price', p ? (p.free ? 'Free' : p.custom ? 'Agreed on a call with our team' : `${lkr(priceOf(p))} a month${v.cycle === 'yearly' && p.yearly_discount ? `, billed ${lkr(priceOf(p) * 12)} a year` : ''}`) : ''),
        row('Start', p?.free ? 'Free from day one' : `${D.trial.days}-day free trial`), row('Add-ons', v.addons.map((k) => D.addons.find((a) => a.key === k)?.name).filter(Boolean).join(', '))))),
    h('div', { class: 'su-grid', style: { marginTop: '20px' } },
      field('message', 'Anything we should know?', h('textarea', { class: 'su-in', name: 'message', maxlength: 500, placeholder: 'For example: I have 3 classes and want to move my students from WhatsApp groups.', oninput: (e) => { v.message = e.target.value; save(); } }, v.message), { opt: true, span: 's6' })),
    h('div', { class: 'su-hp', 'aria-hidden': 'true' }, h('label', {}, 'Website', h('input', { tabindex: '-1', autocomplete: 'off', name: 'website', oninput: (e) => { v.website = e.target.value; } }))),
    h('label', { class: 'su-agree' }, h('input', { type: 'checkbox', checked: v.agree, onchange: (e) => { v.agree = e.target.checked; document.querySelector('.su-agree + .su-err')?.remove(); } }),
      h('span', {}, 'I agree that the ENLIXO team may call, WhatsApp and e-mail me about this request and keep these details to set up my account.')),
    errors.agree ? h('span', { class: 'su-err', role: 'alert' }, errors.agree) : null,
  ];
}

// ---------------------------------------------------------------- layout and moving between steps
function render(focusFirstError = false) {
  const body = [stepYou, stepClasses, stepPlan, stepReview][step]();
  const last = step === STEPS.length - 1;
  const promo = v.promo ? D.promos?.[v.promo] : null;
  fill(app,
    promo ? h('div', { class: 'su-promo', role: 'status' }, h('span', { class: 'su-promo-ic', 'aria-hidden': 'true' }, '🎁'),
      h('span', {}, h('b', { 'data-no-i18n': '' }, `${v.promo}: `), h('b', {}, promo.title), ' ', h('span', {}, 'is saved with your request. Our team adds it when your account is made.'))) : null,
    h('ol', { class: 'su-steps', 'aria-label': 'Steps' }, STEPS.map((s, i) => h('li', { class: `su-step ${i < step ? 'done' : ''} ${i === step ? 'on' : ''}`, 'aria-current': i === step ? 'step' : null },
      `${i + 1}. ${s.title}`, h('small', {}, s.sub)))),
    h('form', { class: 'su-panel', novalidate: true, onsubmit: (e) => { e.preventDefault(); last ? submit() : next(); } },
      ...body,
      h('div', { class: 'su-nav' },
        step > 0 ? h('button', { type: 'button', class: 'lp-btn su-back', onclick: () => go(step - 1) }, ic(I.back), 'Back') : h('span'),
        h('button', { type: 'submit', class: `lp-btn lp-btn-primary ${last ? 'lp-shine' : ''}`, id: 'su-next' }, last ? 'Send my request' : 'Continue', ic(I.arrow)))));
  const target = focusFirstError ? app.querySelector('.bad, .su-err') : null;
  if (target) { target.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' }); target.focus?.({ preventScroll: true }); }
}

function go(i) {
  step = i;
  render();
  window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
  app.querySelector('input, select, textarea')?.focus({ preventScroll: true });
}

const PHONE = /^(?:\+?94|0)?7\d{8}$/;
function check(i) {
  const e = {};
  const s = (k) => String(v[k] ?? '').trim();
  if (i === 0) {
    if (!s('title')) e.title = 'Choose a title.';
    if (!s('first_name')) e.first_name = 'Enter your first name.';
    if (!s('last_name')) e.last_name = 'Enter your last name.';
    if (!s('email')) e.email = 'Enter your e-mail address.';
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(s('email'))) e.email = 'Enter a valid e-mail address, for example ruwan@gmail.com.';
    if (!s('mobile')) e.mobile = 'Enter your mobile number.';
    else if (!PHONE.test(s('mobile').replace(/[\s-]/g, ''))) e.mobile = 'Enter a Sri Lankan mobile number, 10 digits, for example 0771234567.';
    if (s('whatsapp') && !PHONE.test(s('whatsapp').replace(/[\s-]/g, ''))) e.whatsapp = 'Enter a Sri Lankan mobile number, or leave it empty.';
    if (s('nic') && !/^(\d{9}[vVxX]|\d{12})$/.test(s('nic'))) e.nic = 'Enter a valid NIC: 9 digits and V, or 12 digits.';
  } else if (i === 1) {
    if (!s('subject')) e.subject = 'Enter the main subject you teach.';
    if (!s('mode')) e.mode = 'Choose how you teach.';
    if (s('students') && !(Number(s('students')) >= 1)) e.students = 'Enter a number, for example 120.';
  } else if (i === 2) {
    if (!s('plan')) e.plan = 'Choose a plan.';
  } else if (i === 3) {
    if (!v.agree) e.agree = 'Please agree so that our team can contact you.';
  }
  return e;
}

function next() {
  errors = check(step);
  if (Object.keys(errors).length) { render(true); return; }
  go(step + 1);
}

async function submit() {
  for (let i = 0; i < STEPS.length; i++) {
    errors = check(i);
    if (Object.keys(errors).length) { step = i; render(true); return; }
  }
  const btn = document.getElementById('su-next');
  btn.disabled = true;
  btn.textContent = 'Sending…';
  try {
    const { data } = await api.post('/public/tutor-signup', { ...v });
    try { sessionStorage.removeItem(DRAFT); } catch { /* ignore */ }
    done(data.ref);
  } catch (e) {
    errors = e.details ?? {};
    const first = Object.keys(errors).map((k) => FIELD_STEP[k]).filter((x) => x !== undefined).sort()[0];
    if (first === undefined) errors = { agree: e.message || 'Something went wrong. Please try again.' };
    step = first ?? STEPS.length - 1;
    render(true);
  }
}

function done(ref) {
  const p = D.plans.find((x) => x.key === v.plan);
  fill(app, h('div', { class: 'su-done', role: 'status' },
    h('div', { class: 'su-done-ic' }, ic(I.check, 44)),
    h('h2', {}, `Thank you, ${v.first_name}!`),
    h('p', {}, `Your request for ${p?.name ?? 'ENLIXO Tutor'} is with our team. Keep this number in case you call us:`),
    h('div', { class: 'su-ref' }, ref),
    h('ul', { class: 'su-next' },
      h('li', {}, h('span', { class: 'lp-dot lp-blue' }, ic(I.phone, 16)), h('span', {}, 'We call or WhatsApp you within one working day.')),
      h('li', {}, h('span', { class: 'lp-dot lp-green' }, ic(I.mail, 16)), h('span', {}, `Your sign-in details come to ${v.email}.`)),
      h('li', {}, h('span', { class: 'lp-dot lp-violet' }, ic(I.rocket, 16)), h('span', {}, p?.free ? 'Your FREE plan starts when your account is ready.' : `Your ${D.trial.days}-day free trial starts when your account is ready.`))),
    h('div', { class: 'lp-hero-cta lp-center' }, h('a', { href: './', class: 'lp-btn lp-btn-dark' }, 'Back to the home page'))));
  window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
  if (!reduce) confetti();
}

function confetti() {
  const c = h('canvas', { class: 'su-confetti', 'aria-hidden': 'true' });
  document.body.append(c);
  const ctx = c.getContext('2d');
  c.width = innerWidth; c.height = innerHeight;
  const colors = ['#2f7bff', '#22d3ee', '#7c5cff', '#22c55e', '#f59e0b'];
  const bits = Array.from({ length: 140 }, () => ({ x: innerWidth / 2 + (Math.random() - 0.5) * 200, y: innerHeight / 3, vx: (Math.random() - 0.5) * 14, vy: Math.random() * -14 - 4, s: Math.random() * 6 + 4, r: Math.random() * 6, vr: (Math.random() - 0.5) * 0.3, c: colors[Math.floor(Math.random() * colors.length)] }));
  const t0 = performance.now();
  (function frame(t) {
    ctx.clearRect(0, 0, c.width, c.height);
    for (const b of bits) {
      b.vy += 0.35; b.vx *= 0.99; b.x += b.vx; b.y += b.vy; b.r += b.vr;
      ctx.save(); ctx.translate(b.x, b.y); ctx.rotate(b.r); ctx.fillStyle = b.c; ctx.fillRect(-b.s / 2, -b.s / 4, b.s, b.s / 2); ctx.restore();
    }
    if (t - t0 < 3500) requestAnimationFrame(frame); else c.remove();
  }(t0));
}
