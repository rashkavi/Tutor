// Online tutor sign-up (tutor-registration-application.php): ONE short form.
//   plan (from the landing page ?plan=, can be changed here) → name, e-mail, mobile →
//   STARTER / GROW: the amount, our bank accounts and the slip of the first payment → Terms and Conditions → send.
// GET /public/tutor-signup gives plans, bank accounts and offers; POST /public/tutor-signup (multipart: `data` JSON +
// `slip`) sends the request. A draft of the typed text is kept in this tab (never the slip).
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
  gift: '<rect x="3" y="8" width="18" height="13" rx="1"/><path d="M12 8v13M3 12h18M12 8c-2-4-6-4-6-1.5S9 8 12 8c3 0 6-.5 6-1.5S14 4 12 8"/>',
  phone: '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
  mail: '<rect x="3" y="5" width="18" height="14" rx="1"/><path d="m3 7 9 6 9-6"/>',
  rocket: '<path d="M5 15c-1.5 1.5-2 5-2 5s3.5-.5 5-2M9 15l-3-3c1-4 4.5-8 12-9-1 7.5-5 11-9 12z"/><circle cx="15" cy="9" r="1.5"/>',
  bank: '<path d="M3 10h18M5 10v8M9 10v8M15 10v8M19 10v8M3 20h18M12 3l9 5H3z"/>',
  copy: '<rect x="9" y="9" width="12" height="12" rx="1"/><path d="M5 15V5a2 2 0 0 1 2-2h8"/>',
  upload: '<path d="M12 16V4M6 10l6-6 6 6M4 20h16"/>',
  file: '<path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5"/>',
  x: '<path d="M18 6 6 18M6 6l12 12"/>',
};
const DRAFT = 'enlixo-tutor-signup';
const FIELD_ORDER = ['plan', 'name', 'email', 'mobile', 'slip', 'agree'];

const q = new URLSearchParams(location.search);
let D = null; // plans, banks, offers
let errors = {};
let slip = null; // the chosen File (never kept in the draft)
let sending = false;
const v = { name: '', email: '', mobile: '', plan: 'grow', cycle: 'monthly', agree: false, website: '' };
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
  if (!D.plans.some((p) => p.key === v.plan)) v.plan = D.plans.find((p) => p.key === 'grow')?.key ?? D.plans[0]?.key;
  const pill = document.getElementById('su-trial-pill');
  if (pill) pill.textContent = `Free up to ${D.plans.find((x) => x.free)?.limit ?? 50} students, no card needed`;
  render();
}

// ---------------------------------------------------------------- money
const plan = () => D.plans.find((p) => p.key === v.plan);
const isPaid = (p) => p && !p.free && !p.custom;
const promoFor = (p) => { const pr = v.promo ? D.promos?.[v.promo] : null; return pr && pr.plan === p.key ? pr : null; };
function monthlyOf(p) {
  let m = v.cycle === 'yearly' ? Math.max(0, p.monthly - p.yearly_discount) : p.monthly;
  const pr = promoFor(p);
  if (pr) m = Math.round(m * (100 - pr.percent) / 1000) * 10;
  return m;
}
const toPay = (p) => (v.cycle === 'yearly' ? monthlyOf(p) * 12 : monthlyOf(p));

// ---------------------------------------------------------------- pieces
function field(key, label, control, { span = 's6', hint = null } = {}) {
  const id = `su-${key}`;
  control.id = id;
  if (errors[key]) { control.classList.add('bad'); control.setAttribute('aria-invalid', 'true'); control.setAttribute('aria-describedby', `${id}-err`); }
  return h('div', { class: `su-f ${span}` },
    h('label', { for: id }, label, h('span', { class: 'req', 'aria-hidden': 'true' }, '*')),
    control,
    hint && !errors[key] ? h('span', { class: 'su-hint' }, hint) : null,
    errors[key] ? h('span', { class: 'su-err', id: `${id}-err`, role: 'alert' }, errors[key]) : null);
}
function input(key, attrs = {}) {
  return h('input', { class: 'su-in', value: v[key] ?? '', name: key, oninput: (e) => { v[key] = e.target.value; clearErr(key, e.target); save(); }, ...attrs });
}
function clearErr(key, el) {
  if (!errors[key]) return;
  delete errors[key];
  el?.classList.remove('bad');
  el?.removeAttribute('aria-invalid');
  el?.closest('.su-f')?.querySelector('.su-err')?.remove();
}

// "Your plan": only the plan chosen on the landing page (?plan=), with a link back to the plans to pick another
function planPicker() {
  const p = plan();
  const yearlyOn = isPaid(p) && p.yearly_discount > 0;
  return h('div', { class: 'su-block' },
    h('div', { class: 'su-block-head' }, h('span', { class: 'su-block-n' }, '1'), h('b', {}, 'Your plan'),
      yearlyOn ? h('div', { class: 'su-cycle su-cycle-sm', role: 'radiogroup', 'aria-label': 'Billing' },
        ['monthly', 'yearly'].map((c) => h('button', { type: 'button', role: 'radio', 'aria-checked': String(v.cycle === c), class: v.cycle === c ? 'on' : '', onclick: () => { v.cycle = c; save(); render(); } },
          c === 'monthly' ? 'Monthly' : 'Yearly', c === 'yearly' ? h('span', { class: 'lp-save' }, 'Save more') : null))) : null),
    h('div', { class: `su-chosen su-plan-${p.key}` },
      h('span', { class: 'su-plan-check' }, ic(I.check, 14)),
      h('div', { class: 'su-chosen-body' },
        h('span', { class: 'su-plan-name' }, p.name),
        h('span', { class: 'su-mini-price' }, p.free ? 'Free' : p.custom ? "Let's talk" : [lkr(monthlyOf(p)), h('small', {}, ' / month')],
          isPaid(p) && v.cycle === 'yearly' ? h('small', {}, ` · ${lkr(toPay(p))} a year`) : null),
        h('span', { class: 'su-mini-meta' }, p.custom ? 'Any size, price agreed with our team' : p.limit ? `Up to ${p.limit} students` : 'Unlimited students')),
      h('a', { class: 'su-change', href: './#pricing' }, 'Change plan')));
}

function payment() {
  const p = plan();
  if (p?.free) {
    return h('div', { class: 'su-trial su-trial-free' }, h('span', { class: 'lp-dot lp-green' }, ic(I.gift, 20)),
      h('span', {}, h('b', {}, `Free while you teach up to ${p.limit} students.`), ' Nothing to pay. When your class grows, you pick a paid plan and keep everything.'));
  }
  if (p?.custom) {
    return h('div', { class: 'su-trial' }, h('span', { class: 'lp-dot lp-blue' }, ic(I.phone, 20)),
      h('span', {}, h('b', {}, 'We call you to make your plan.'), ' Nothing to pay now. We agree one price that fits your classes.'));
  }
  const banks = D.banks ?? [];
  const copy = (text, btn) => {
    navigator.clipboard?.writeText(text).then(() => { btn.classList.add('ok'); btn.lastChild.textContent = 'Copied'; setTimeout(() => { btn.classList.remove('ok'); btn.lastChild.textContent = 'Copy'; }, 1500); });
  };
  const fileInput = h('input', { type: 'file', id: 'su-slip', accept: 'image/jpeg,image/png,image/webp,application/pdf', class: 'su-file', onchange: (e) => pick(e.target.files[0]) });
  const pick = (f) => {
    if (!f) return;
    const max = (D.slip?.max_mb ?? 5) * 1024 * 1024;
    if (!/^(image\/(jpeg|png|webp)|application\/pdf)$/.test(f.type)) { errors.slip = 'Upload a JPG, PNG, WebP or PDF file.'; render(); return; }
    if (f.size > max) { errors.slip = `The slip is larger than ${D.slip?.max_mb ?? 5} MB. Take a smaller photo or a PDF.`; render(); return; }
    slip = f;
    delete errors.slip;
    render();
  };
  const drop = h('label', { class: `su-drop${errors.slip ? ' bad' : ''}`, for: 'su-slip',
    ondragover: (e) => { e.preventDefault(); e.currentTarget.classList.add('over'); },
    ondragleave: (e) => e.currentTarget.classList.remove('over'),
    ondrop: (e) => { e.preventDefault(); e.currentTarget.classList.remove('over'); pick(e.dataTransfer.files[0]); } },
  h('span', { class: 'su-drop-ic' }, ic(I.upload, 22)),
  h('span', {}, h('b', {}, 'Drop the slip here or choose a file'), h('small', {}, `Photo or PDF of the bank slip, up to ${D.slip?.max_mb ?? 5} MB`)));
  return h('div', { class: 'su-block' },
    h('div', { class: 'su-block-head' }, h('span', { class: 'su-block-n' }, '3'), h('b', {}, 'Pay and upload the slip')),
    h('div', { class: 'su-pay' },
      h('div', { class: 'su-pay-amount' }, h('small', {}, `Pay for ${p.name}, ${v.cycle === 'yearly' ? 'one year' : 'the first month'}`), h('b', {}, lkr(toPay(p))),
        promoFor(p) ? h('span', { class: 'su-hint' }, `${promoFor(p).percent}% off with ${v.promo}`) : null),
      banks.length ? h('div', { class: 'su-banks' }, banks.map((b) => {
        const btn = h('button', { type: 'button', class: 'su-copy', onclick: (e) => copy(b.number, e.currentTarget), 'aria-label': `Copy account number ${b.number}` }, ic(I.copy, 14), h('span', {}, 'Copy'));
        return h('div', { class: 'su-bank' }, h('span', { class: 'lp-dot lp-blue' }, ic(I.bank, 18)),
          h('div', { class: 'su-bank-body' }, h('b', {}, b.bank + (b.branch ? ` · ${b.branch}` : '')), h('span', {}, b.name), h('span', { class: 'su-bank-no', 'data-no-i18n': '' }, b.number)), btn);
      })) : h('p', { class: 'su-hint' }, 'Our bank details come to you on WhatsApp. You can send the slip then.')),
    fileInput,
    slip ? h('div', { class: 'su-slip' }, h('span', { class: 'lp-dot lp-green' }, ic(I.file, 18)),
      h('div', { class: 'su-bank-body' }, h('b', {}, slip.name), h('span', {}, `${(slip.size / 1024 / 1024).toFixed(2)} MB · ready to send`)),
      h('button', { type: 'button', class: 'su-copy', onclick: () => { slip = null; render(); }, 'aria-label': 'Remove the slip' }, ic(I.x, 14), h('span', {}, 'Remove'))) : drop,
    errors.slip ? h('span', { class: 'su-err', role: 'alert' }, errors.slip) : null,
    h('p', { class: 'su-hint su-pay-note' }, 'Use your name or mobile number as the reference. We check the slip within one working day.'));
}

// ---------------------------------------------------------------- the form
function render(focusFirstError = false) {
  const promo = v.promo ? D.promos?.[v.promo] : null;
  const p = plan();
  fill(app,
    promo ? h('div', { class: 'su-promo', role: 'status' }, h('span', { class: 'su-promo-ic', 'aria-hidden': 'true' }, '🎁'),
      h('span', {}, h('b', { 'data-no-i18n': '' }, `${v.promo}: `), h('b', {}, promo.title), ' ', h('span', {}, 'is applied to the price below.'))) : null,
    h('form', { class: 'su-panel', novalidate: true, onsubmit: (e) => { e.preventDefault(); submit(); } },
      h('h2', {}, 'Create your account'),
      h('p', {}, 'Just a few details. Our team makes your account and sends your sign-in details by e-mail.'),
      planPicker(),
      h('div', { class: 'su-block' },
        h('div', { class: 'su-block-head' }, h('span', { class: 'su-block-n' }, '2'), h('b', {}, 'Your details')),
        h('div', { class: 'su-grid' },
          field('name', 'Your name', input('name', { autocomplete: 'name', maxlength: 90, placeholder: 'Enter your name, e.g. Ruwan Perera' })),
          field('email', 'E-mail address', input('email', { type: 'email', autocomplete: 'email', maxlength: 100, placeholder: 'Enter your e-mail, e.g. ruwan@gmail.com' }), { span: '', hint: 'You will sign in with this address.' }),
          field('mobile', 'Mobile number', input('mobile', { type: 'tel', autocomplete: 'tel', maxlength: 15, placeholder: 'Enter your mobile, e.g. 0771234567' }), { span: '', hint: 'We call or WhatsApp you on this number.' }))),
      payment(),
      h('div', { class: 'su-hp', 'aria-hidden': 'true' }, h('label', {}, 'Website', h('input', { tabindex: '-1', autocomplete: 'off', name: 'website', oninput: (e) => { v.website = e.target.value; } }))),
      h('label', { class: `su-agree${errors.agree ? ' bad' : ''}` }, h('input', { type: 'checkbox', id: 'su-agree', checked: v.agree, onchange: (e) => { v.agree = e.target.checked; delete errors.agree; document.querySelector('.su-agree')?.classList.remove('bad'); document.querySelector('.su-agree + .su-err')?.remove(); } }),
        h('span', {}, 'I accept the ', h('a', { href: 'terms', target: '_blank', rel: 'noopener' }, 'Terms and Conditions'), ' and agree that the ENLIXO team may call, WhatsApp and e-mail me about this request.')),
      errors.agree ? h('span', { class: 'su-err', role: 'alert' }, errors.agree) : null,
      h('div', { class: 'su-nav su-nav-end' },
        isPaid(p) && !slip ? h('span', { class: 'su-hint su-need-slip' }, ic(I.upload, 14), 'Upload the payment slip to continue') : null,
        h('button', { type: 'submit', class: 'lp-btn lp-btn-primary lp-shine', id: 'su-next', disabled: sending || (isPaid(p) && !slip) || null,
          title: isPaid(p) && !slip ? 'Upload the payment slip to continue' : null },
        sending ? 'Sending…' : isPaid(p) ? 'Send request and slip' : 'Send my request', ic(I.arrow)))));
  const target = focusFirstError ? app.querySelector('.bad, .su-err') : null;
  if (target) { target.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' }); target.focus?.({ preventScroll: true }); }
}

const PHONE = /^(?:\+?94|0)?7\d{8}$/;
function check() {
  const e = {};
  const s = (k) => String(v[k] ?? '').trim();
  if (!s('plan')) e.plan = 'Choose a plan.';
  if (s('name').length < 2) e.name = 'Enter your name.';
  if (!s('email')) e.email = 'Enter your e-mail address.';
  else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(s('email'))) e.email = 'Enter a valid e-mail address, for example ruwan@gmail.com.';
  if (!s('mobile')) e.mobile = 'Enter your mobile number.';
  else if (!PHONE.test(s('mobile').replace(/[\s-]/g, ''))) e.mobile = 'Enter a Sri Lankan mobile number, 10 digits, for example 0771234567.';
  if (isPaid(plan()) && !slip) e.slip = 'Upload the slip of your payment (a photo or PDF).';
  if (!v.agree) e.agree = 'Please accept the Terms and Conditions to send your request.';
  return e;
}

async function submit() {
  errors = check();
  if (Object.keys(errors).length) { render(true); return; }
  sending = true;
  render();
  const body = new FormData();
  body.append('data', JSON.stringify({ ...v }));
  if (slip && isPaid(plan())) body.append('slip', slip);
  try {
    const { data } = await api.post('/public/tutor-signup', body);
    try { sessionStorage.removeItem(DRAFT); } catch { /* ignore */ }
    done(data.ref);
  } catch (e) {
    sending = false;
    errors = e.details ?? {};
    if (errors.first_name) errors.name = errors.first_name;
    if (!FIELD_ORDER.some((k) => errors[k])) errors = { agree: e.message || 'Something went wrong. Please try again.' };
    render(true);
  }
}

function done(ref) {
  const p = plan();
  const first = String(v.name).trim().split(/\s+/)[0] || '';
  fill(app, h('div', { class: 'su-done', role: 'status' },
    h('div', { class: 'su-done-ic' }, ic(I.check, 44)),
    h('h2', {}, `Thank you, ${first}!`),
    h('p', {}, `Your request for ${p?.name ?? 'ENLIXO Tutor'} is with our team. Keep this number in case you call us:`),
    h('div', { class: 'su-ref' }, ref),
    h('ul', { class: 'su-next' },
      isPaid(p) ? h('li', {}, h('span', { class: 'lp-dot lp-blue' }, ic(I.bank, 16)), h('span', {}, 'We check your slip within one working day.')) : null,
      h('li', {}, h('span', { class: 'lp-dot lp-blue' }, ic(I.phone, 16)), h('span', {}, 'We call or WhatsApp you to set up your classes.')),
      h('li', {}, h('span', { class: 'lp-dot lp-green' }, ic(I.mail, 16)), h('span', {}, `Your sign-in details come to ${v.email}.`)),
      h('li', {}, h('span', { class: 'lp-dot lp-violet' }, ic(I.rocket, 16)), h('span', {}, p?.free ? 'Your FREE plan starts when your account is ready.' : p?.custom ? 'We agree your CUSTOM plan on the call.' : `${p?.name} starts as soon as your payment is confirmed.`))),
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
