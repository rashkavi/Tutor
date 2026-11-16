// Admin → a tutor → Manage Plan (admin/tutor-plan.php?tutor-id=&do=): one page for every plan action (they were
// pop-ups on Tutor Details). Tiles choose the action; the form is on the page; Save goes back to the tutor.
// New: "Give a Free Trial" = a trial on a chosen plan for 1-60 days (PlatformBilling::action('trial')).
// Data: GET /admin/tutors/{id}; POST /admin/tutors/{id}/{action}.
import { QuickGuide } from '../components/QuickGuide.js';
import { DetailPage } from '../components/DetailPage.js';
import { api, h, fill, icon, lkr, day, plural, chip, field, showErrors, Toast } from './admin-common.js';

QuickGuide.attachAll();
const root = document.getElementById('tp-page');
const actions = document.getElementById('tp-actions');
const q = new URLSearchParams(location.search);
const id = Number(q.get('tutor-id') || 0);
let doing = q.get('do') || 'trial';
let D = null;
const errs = (e) => e.details?.fields ?? e.details ?? {};
const addDays = (n) => { const d = new Date(); d.setDate(d.getDate() + n); return d.toISOString().slice(0, 10); };

fill(root, DetailPage.loading());
load();

async function load() {
  try { D = (await api.get(`/admin/tutors/${id}`)).data; } catch (e) {
    fill(root, DetailPage.notFound(e.status === 404 ? 'This tutor was not found.' : `Couldn't load the tutor: ${e.message}`, ['Back to Tutors', 'tutors']));
    return;
  }
  document.title = `Manage Plan · ${D.tutor.name} - Admin`;
  const crumb = document.querySelector('.breadcrumb li:nth-child(2) a, .breadcrumb li:nth-child(2)');
  if (crumb) crumb.outerHTML = `<li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="tutor-details?tutor-id=${id}">${D.tutor.name.replace(/[<>&]/g, '')}</a></li>`;
  // open the first action that can be used when the asked one cannot (for example a trial over a paid period)
  const can = Object.fromEntries(list().map(([key, , , , ok]) => [key, ok]));
  if (!can[doing]) doing = Object.keys(can).find((k) => can[k] && k !== 'trial') ?? 'plan';
  draw();
}

function list() {
  const t = D.tutor;
  const paid = t.period_end && ['active', 'due'].includes(t.status) && t.period_end > new Date().toISOString().slice(0, 10);
  return [
    ['trial', 'gift', 'Give a Free Trial', paid ? `Paid until ${day(t.period_end)}` : 'A plan free for some days', !paid],
    ['extend', 'hourglass', 'Extend the Trial', t.status === 'trial' ? `Ends ${day(t.trial_ends)}` : 'Only during a trial', t.status === 'trial'],
    ['plan', 'plans', 'Change Plan', `${D.plan.name}, ${t.cycle}`, true],
    ['packs', 'students', 'Extra Students', `${t.extra_packs} × 50 now`, Boolean(D.plan.pack_price)],
    ['price', 'price', 'Custom Price', t.custom_price ? lkr(t.price) : 'Plan price', true],
    ['suspended', 'cancelled'].includes(t.status) ? ['reactivate', 'play', 'Reactivate', 'Switch everything on', true] : ['suspend', 'pause', 'Suspend', 'Pause tutor and students', true],
    ['cancel', 'stop', 'Cancel Subscription', t.cancel_at_period_end ? 'Already ends at period end' : 'Now or at period end', t.status !== 'cancelled' && !t.cancel_at_period_end],
  ];
}

function draw() {
  const t = D.tutor;
  fill(actions, h('a', { class: 'custom-btn ex-btn-lg px-5 text-decoration-none', href: `tutor-details?tutor-id=${id}` }, 'Back to Tutor'));
  const tiles = h('div', { class: 'ld-actions mb-4' }, list().map(([key, ico, title, sub, ok]) => h('button', {
    type: 'button', class: `ld-act text-start${doing === key ? ' is-on' : ''}${['suspend', 'cancel'].includes(key) ? ' is-danger' : ''}`, disabled: !ok, style: ok ? null : { opacity: 0.5 },
    onclick: () => { doing = key; q.set('do', key); history.replaceState(null, '', `?${q}`); draw(); },
  }, h('span', { class: 'ld-act-ico' }, icon(ico, { size: 22 })), h('span', {}, h('strong', {}, title), h('small', {}, sub)))));
  fill(root,
    h('div', { class: 'component-theme p-4 mb-4 d-flex align-items-center gap-3 flex-wrap' },
      h('span', { class: 'ad-profile-avatar m-0', style: { width: '52px', height: '52px', fontSize: '18px' } }, `${t.first_name[0]}${t.last_name[0]}`),
      h('div', { class: 'flex-grow-1 min-w-0' }, h('div', { class: 'text-18 main-text fw-semibold' }, `${t.title} ${t.name}`), h('div', { class: 'text-13 sub-text' }, [t.brand, t.email].filter(Boolean).join(' · '))),
      h('div', { class: 'd-flex align-items-center gap-2 flex-wrap' }, chip(t.status), h('span', { class: 'ad-tag' }, `${D.plan.name} · ${t.cycle}`),
        t.status === 'trial' ? h('span', { class: 'ad-tag' }, `Trial ends ${day(t.trial_ends)}`) : t.period_end ? h('span', { class: 'ad-tag' }, `Paid until ${day(t.period_end)}`) : null)),
    tiles,
    form());
}

const num = (value, attrs = {}) => h('input', { class: 'form-control custom-input', type: 'number', value, ...attrs });
const sel = (opts, value) => { const s = h('select', { class: 'form-select custom-select' }, opts.map(([v, txt]) => h('option', { value: v }, txt))); s.value = value; return s; };
const reasonBox = (ph = 'Say why. It is kept in the tutor\'s history') => h('textarea', { class: 'form-control custom-input', rows: 2, placeholder: ph, 'data-key': 'reason', 'data-limit': '200' });

function form() {
  const t = D.tutor;
  const box = h('div', { class: `component-theme p-4 ad-panel${['suspend', 'cancel'].includes(doing) ? ' is-danger' : ''}`, id: 'tp-form' });
  const send = async (btn, payload, done) => {
    showErrors(box, {});
    btn.disabled = true;
    try {
      const res = await api.post(`/admin/tutors/${id}/${doing}`, payload);
      Toast.success(done(res.data));
      setTimeout(() => { location.href = `tutor-details?tutor-id=${id}`; }, 800);
    } catch (e) { btn.disabled = false; showErrors(box, errs(e)); Toast.error(e.message); }
  };
  const head = (ico, title, sub) => h('div', { class: 'd-flex align-items-center gap-3 mb-3' }, h('span', { class: ['suspend', 'cancel'].includes(doing) ? 'fe-reject-ico' : 'fe-form-ico' }, icon(ico, { size: 22 })),
    h('div', {}, h('div', { class: 'text-18 main-text fw-semibold' }, title), h('div', { class: 'text-13 sub-text' }, sub)));
  const foot = (label, onclick, danger = false) => h('div', { class: 'd-flex justify-content-end gap-2 mt-2' },
    h('a', { class: 'custom-btn px-4 text-decoration-none', href: `tutor-details?tutor-id=${id}` }, 'Cancel'),
    h('button', { type: 'button', class: `custom-btn ${danger ? 'custom-danger-btn' : ''} px-5`, id: 'tp-save', onclick: (e) => onclick(e.currentTarget) }, label));

  const blocked = list().find(([key]) => key === doing)?.[4] === false;
  if (blocked) {
    fill(box, h('p', { class: 'text-14 sub-text m-0' }, 'This action cannot be used for this tutor right now.'));
    return box;
  }
  if (doing === 'trial') {
    let plan = (D.plans.find((p) => p.key === t.plan) ?? D.plans[0])?.key;
    const days = num(14, { min: 1, max: 60, placeholder: 'Enter the number of days, for example 14', 'data-key': 'days' });
    const cycle = sel([['monthly', 'Monthly'], ['yearly', 'Yearly']], t.cycle || 'monthly');
    cycle.dataset.key = 'cycle';
    const reason = reasonBox('For example "Trying GROW before the A/L season"');
    const summary = h('ul', { class: 'tp-summary mb-3' });
    const plans = h('div', { class: 'tp-plans mb-3', 'data-key': 'plan', id: 'tp-plan' });
    const paint = () => {
      fill(plans, D.plans.map((p) => h('label', { class: `tp-plan${p.key === plan ? ' is-on' : ''}` },
        h('input', { type: 'radio', name: 'tp-plan', value: p.key, checked: p.key === plan, onchange: () => { plan = p.key; paint(); } }),
        h('strong', {}, p.name), h('small', {}, `${lkr(p.monthly)} a month`), h('small', {}, p.limit ? `Up to ${p.limit} students` : 'Unlimited students'))));
      const p = D.plans.find((x) => x.key === plan);
      const n = Math.max(1, Math.min(60, Number(days.value) || 0));
      fill(summary,
        h('li', {}, `${t.first_name} gets every ${p?.name ?? ''} feature for ${plural(n, 'day')}${p?.limit ? `, up to ${p.limit} students` : ''}.`),
        h('li', {}, `The trial ends on ${day(addDays(n))}. ENLIXO makes the first ${p?.name ?? ''} invoice (${lkr(p?.monthly ?? 0)} a month, ${cycle.value}) before that day.`),
        h('li', {}, 'Open unpaid invoices are void. Not paid after the grace days: the account is paused until the slip is approved.'));
    };
    days.addEventListener('input', paint);
    cycle.addEventListener('change', paint);
    paint();
    fill(box, head('gift', 'Give a Free Trial', 'Choose the plan and how long it is free.'),
      h('label', { class: 'text-14 main-text mb-1 required' }, 'Plan'), plans, h('div', { class: 'text-12 text-dark-red mb-2 d-none', 'data-err': 'tp-plan' }),
      h('div', { class: 'tp-days' }, [7, 14, 30, 60].map((n) => h('button', { type: 'button', onclick: () => { days.value = n; paint(); } }, plural(n, 'day')))),
      h('div', { class: 'row gx-3' },
        h('div', { class: 'col-md-6' }, field('Free days', days, { required: true, info: 'How long the trial lasts, 1 to 60 days, counted from today.' })),
        h('div', { class: 'col-md-6' }, field('Billing after the trial', cycle, { required: true, info: 'How the tutor pays when the trial ends: every month, or once a year.' }))),
      summary,
      field('Reason', reason, { info: 'Kept in the tutor\'s history, for example "Trying GROW before the A/L season".' }),
      foot('Give Free Trial', (b) => send(b, { plan, days: days.value, cycle: cycle.value, reason: reason.value }, (r) => r.message ?? 'Free trial given.')));
    return box;
  }
  if (doing === 'extend') {
    const days = num(7, { min: 1, max: 30, placeholder: 'Enter the number of extra days', 'data-key': 'days' });
    const reason = reasonBox();
    fill(box, head('hourglass', 'Extend the Free Trial', `Ends ${day(t.trial_ends)} now.`),
      field('Extra days', days, { required: true, info: 'Days added to the current trial end date. Up to 30.' }), field('Reason', reason, { required: true }),
      foot('Extend Trial', (b) => send(b, { days: days.value, reason: reason.value }, () => 'Trial extended.')));
    return box;
  }
  if (doing === 'plan') {
    const plan = sel(D.plans.map((p) => [p.key, `${p.name}, ${lkr(p.monthly)} a month, up to ${p.limit ?? 'any number of'} students`]), t.plan);
    plan.dataset.key = 'plan';
    const cycle = sel([['monthly', 'Monthly'], ['yearly', 'Yearly']], t.cycle);
    cycle.dataset.key = 'cycle';
    fill(box, head('plans', 'Change Plan', 'Upgrade: starts today, the difference for the days left is invoiced. Downgrade: at the next renewal, only when the students fit.'),
      h('div', { class: 'row gx-3' }, h('div', { class: 'col-md-8' }, field('New plan', plan, { required: true })), h('div', { class: 'col-md-4' }, field('Billing', cycle, { required: true }))),
      foot('Change Plan', (b) => send(b, { plan: plan.value, cycle: cycle.value }, (r) => r.message ?? 'Plan changed.')));
    return box;
  }
  if (doing === 'packs') {
    const packs = num(t.extra_packs, { min: 0, max: 20, placeholder: 'Enter the number of packs', 'data-key': 'packs' });
    fill(box, head('students', 'Extra Students', `Each pack adds 50 students for ${lkr(D.plan.pack_price ?? 0)} a month, from the next invoice.`),
      field('Packs of 50', packs, { required: true, info: `0 = only the ${D.plan.limit} students of the plan.` }),
      foot('Save', (b) => send(b, { packs: packs.value }, () => 'Saved.')));
    return box;
  }
  if (doing === 'price') {
    const price = num(t.custom_price ? t.price : '', { min: 0, placeholder: `Plan price is ${lkr(D.plan.monthly)}`, 'data-key': 'price' });
    const reason = reasonBox();
    fill(box, head('price', 'Custom Price', 'This tutor pays this price instead of the plan price, from the next invoice. Empty = the plan price.'),
      field('Monthly price (LKR)', price, { info: 'Numbers only, for example 2500.' }), field('Reason', reason, { required: true }),
      foot('Save Price', (b) => send(b, { price: price.value, reason: reason.value }, () => 'Custom price saved.')));
    return box;
  }
  if (doing === 'suspend' || doing === 'reactivate') {
    const reason = reasonBox();
    const sus = doing === 'suspend';
    fill(box, head(sus ? 'pause' : 'play', sus ? 'Suspend this Tutor?' : 'Reactivate this Tutor?',
      sus ? 'The tutor can still sign in, see Billing and pay. Their classes are paused and their students and parents cannot sign in until it is paid or reactivated.' : 'Classes, students and parents switch back on at once. Open invoices stay open.'),
      field('Reason', reason, { required: true }),
      foot(sus ? 'Suspend' : 'Reactivate', (b) => send(b, { reason: reason.value }, () => (sus ? 'Suspended.' : 'Reactivated.')), sus));
    return box;
  }
  if (doing === 'cancel') {
    const when = sel([['end', `At the end of the period (${day(t.period_end)})`], ['now', 'Now']], 'end');
    when.dataset.key = 'when';
    const reason = reasonBox();
    fill(box, head('stop', 'Cancel the Subscription?', 'At the end of the period: access until the paid period ends. Now: access stops today and open invoices are void.'),
      field('When', when, { required: true }), field('Reason', reason, { required: true }),
      foot('Cancel Subscription', (b) => send(b, { when: when.value, reason: reason.value }, () => 'Subscription cancelled.'), true));
    return box;
  }
  fill(box, h('p', { class: 'text-14 sub-text m-0' }, 'Choose what to do above.'));
  return box;
}
