// Tutor → Upgrade Plan (tutor/upgrade.php). One page, no pop-ups, built to move a tutor one plan up when it pays off:
//   1. why now: the feature or limit they hit (?feature= / ?limit=), else what the next plan adds
//   2. what they use: students and every monthly limit, with what the next plan gives
//   3. the plans side by side (monthly / yearly), the recommended one marked; "Ask for a 7-day free trial" (the ENLIXO admin approves it), "Upgrade"
//      opens an inline confirm with the price and what happens next
//   4. every feature per plan, then answers to the usual worries (data kept, how to pay, going back)
// Data: GET /me/access (plan, limits, usage, features per plan, preview) + GET /tutor/subscription (prices, status).
import { QuickGuide } from '../components/QuickGuide.js';
import { api, h, fill, icon, lkr, day, plural, Toast } from './admin-common.js';

QuickGuide.attachAll();
const root = document.getElementById('up-page');
const actions = document.getElementById('up-actions');
const q = new URLSearchParams(location.search);
const ENLIXO_PHONE = '0769679778';
const ORDER = ['free', 'start', 'grow', 'pro'];
let A = null; // access
let S = null; // subscription
let cycle = 'monthly';
let confirming = null; // plan key with the confirm panel open
let TR = { pending: null, last: null }; // trial request (the ENLIXO admin approves it before the trial starts)

fill(root, h('div', { class: 'component-theme p-5 text-center sub-text' }, 'Loading…'));
load();

async function load() {
  try {
    [A, S, TR] = await Promise.all([api.get('/me/access').then((r) => r.data), api.get('/tutor/subscription').then((r) => r.data),
      api.get('/tutor/subscription/trial-request').then((r) => r.data).catch(() => ({ pending: null, last: null }))]);
  } catch (e) {
    fill(root, h('div', { class: 'component-theme p-4 text-dark-red' }, e.message));
    return;
  }
  if (S.tutor.cycle === 'yearly') cycle = 'yearly';
  LIMITS = Object.fromEntries(A.limits.map((l) => [l.key, l.plans ?? {}]));
  draw();
}

const rank = (k) => ORDER.indexOf(k);
const planOf = (k) => S.plans.find((p) => p.key === k);
const nameOf = (k) => planOf(k)?.name ?? (A.plans[k]?.name ?? k.toUpperCase());
const featureByKey = (k) => A.features.find((f) => f.key === k);
const paidKey = () => A.paid_plan?.key ?? A.plan.key;
const limitText = (l, plan) => {
  const v = l.limitsByPlan?.[plan];
  return v === null || v === undefined ? 'Unlimited' : `${v} ${l.unit}${l.period === 'month' ? ' a month' : ''}`;
};

/** The plan to recommend: the one the feature / limit needs, else ?plan=, else the next plan. */
function recommended() {
  const f = q.get('feature') ? featureByKey(q.get('feature')) : null;
  if (f?.required_plan) return f.required_plan;
  const l = q.get('limit') ? A.limits.find((x) => x.key === q.get('limit')) : null;
  if (l?.next) return l.next;
  if (q.get('limit') === 'students') return A.next_plan?.key ?? null;
  if (q.get('plan') && rank(q.get('plan')) > rank(paidKey())) return q.get('plan');
  return A.next_plan?.key ?? null;
}

function monthlyPrice(p) {
  return cycle === 'yearly' ? Math.max(0, p.monthly - p.yearly_discount) : p.monthly;
}

function draw() {
  const mine = paidKey();
  const rec = recommended();
  const t = S.tutor;
  const canPreview = !A.preview_used && !A.preview && !TR.pending && t.status !== 'trial' && !['suspended', 'cancelled'].includes(t.status);

  fill(actions,
    h('a', { class: 'custom-btn ex-btn-lg px-4 text-decoration-none d-inline-flex align-items-center gap-2', href: '#up-compare' }, icon('checklist', { size: 18 }), 'Compare Plans'),
    rec && rec !== 'pro' ? h('button', { type: 'button', class: 'custom-btn ex-btn-lg px-5', onclick: () => openConfirm(rec) }, `Upgrade to ${nameOf(rec)}`)
      : h('a', { class: 'custom-btn ex-btn-lg px-5 text-decoration-none', href: 'subscription' }, 'My Subscription'));

  fill(root,
    whyCard(rec, canPreview),
    A.preview ? previewNote() : null,
    !A.preview ? trialRequestNote() : null,
    usageCard(rec),
    plansCard(rec, canPreview),
    compareCard(),
    faqCard());
  if (confirming) setTimeout(() => document.getElementById('up-confirm')?.scrollIntoView({ behavior: 'smooth', block: 'center' }), 60);
}

// ---------- 1. why now ----------
function whyCard(rec, canPreview) {
  const f = q.get('feature') ? featureByKey(q.get('feature')) : null;
  const l = q.get('limit') ? A.limits.find((x) => x.key === q.get('limit')) : null;
  const recName = rec ? nameOf(rec) : null;
  let title; let text; let ico = 'crown';
  if (f && !f.unlocked) {
    title = `Unlock ${f.name}`;
    text = `${f.description} It comes with ${recName ?? 'a bigger plan'}.`;
    ico = 'lock';
  } else if (l) {
    title = l.limit === null ? `${l.name}: unlimited on your plan`
      : l.used > l.limit ? `You have ${l.used} ${l.unit}; ${nameOf(paidKey())} allows ${l.limit}`
        : l.used === l.limit ? `You used all ${l.limit} ${l.unit}${l.period === 'month' ? ' of this month' : ''}` : `You used ${l.used} of ${l.limit} ${l.unit}`;
    text = `${l.help} ${recName ? `${recName} gives you ${limitText(withPlans(l), rec).toLowerCase()}.` : ''}`;
    ico = 'growth';
  } else if (q.get('limit') === 'students') {
    title = `${A.students.count} of ${A.students.limit} students`;
    text = recName ? `${recName} takes up to ${planOf(rec)?.limit ?? 'unlimited'} students, so new students can join without waiting.` : '';
    ico = 'students';
  } else if (rec) {
    title = `What ${recName} adds to your ${nameOf(paidKey())} plan`;
    text = (A.next_plan?.key === rec ? A.next_plan.adds : []).slice(0, 4).join(' · ') || planOf(rec)?.tagline || '';
  } else {
    title = `You are on ${nameOf(paidKey())}, our biggest plan`;
    text = 'Need more? WhatsApp ENLIXO and we make a CUSTOM plan with you.';
  }
  const price = rec && planOf(rec) && rec !== 'pro' ? planOf(rec) : null;
  return h('div', { class: 'up-why mb-4' },
    h('span', { class: 'up-why-ico' }, icon(ico, { size: 28 })),
    h('div', { class: 'min-w-0 flex-grow-1' },
      h('div', { class: 'up-why-tag' }, `Your plan: ${nameOf(paidKey())}`),
      h('h2', {}, title),
      h('p', {}, text)),
    price ? h('div', { class: 'up-why-cta' },
      h('div', { class: 'up-why-price' }, h('b', {}, lkr(price.monthly)), h('span', {}, '/ month')),
      h('button', { type: 'button', class: 'custom-btn px-4', onclick: () => openConfirm(rec) }, `Upgrade to ${nameOf(rec)}`),
      canPreview ? h('button', { type: 'button', class: 'up-try', onclick: () => startPreview(rec) }, icon('magic', { size: 16 }), 'Ask for a 7-day free trial') : null) : null);
}

/** The trial request waiting for the ENLIXO team, or the answer to the last one. */
function trialRequestNote() {
  if (TR.pending) {
    return h('div', { class: 'up-note mb-4', role: 'status' }, icon('hourglass', { size: 20 }),
      h('span', { class: 'flex-grow-1' }, h('b', {}, `Your request to try ${nameOf(TR.pending.plan)} free is with the ENLIXO team.`),
        ' The 7-day trial starts as soon as they approve it, and this page shows their answer.'),
      h('button', { type: 'button', class: 'custom-outline-btn px-3', onclick: withdrawRequest }, 'Withdraw'));
  }
  if (TR.last?.status === 'rejected' && !A.preview_used) {
    return h('div', { class: 'up-note is-warn mb-4', role: 'status' }, icon('info', { size: 20 }),
      h('span', {}, h('b', {}, `Your request to try ${nameOf(TR.last.plan)} was not approved.`), TR.last.note ? ` ${TR.last.note}` : '',
        ' You can ask again, upgrade, or WhatsApp us on ', h('a', { href: `https://wa.me/94${ENLIXO_PHONE.slice(1)}`, target: '_blank', rel: 'noopener' }, ENLIXO_PHONE), '.'));
  }
  return null;
}

async function withdrawRequest() {
  try {
    const { data } = await api.delete('/tutor/subscription/trial-request');
    Toast.success(data.message);
    TR.pending = null;
    draw();
  } catch (e) {
    Toast.error(e.message);
  }
}

function previewNote() {
  const left = Math.max(0, Math.round((new Date(`${A.preview.until}T00:00:00`) - new Date(new Date().toDateString())) / 86400000));
  return h('div', { class: 'up-note mb-4', role: 'status' }, icon('magic', { size: 20 }),
    h('span', {}, h('b', {}, `You are trying ${nameOf(A.preview.plan)} free: ${left > 0 ? `${plural(left, 'day')} left` : 'last day today'} (until ${day(A.preview.until)}).`),
      ` Upgrade to keep everything you set up with it. When the week ends you go back to ${nameOf(paidKey())} and nothing is deleted.`));
}

// ---------- 2. what you use ----------
// limit key => { plan => limit (null = unlimited) }, from the API (FeatureCatalog::limits())
let LIMITS = {};
const withPlans = (l) => ({ ...l, limitsByPlan: l.plans ?? {} });

function usageCard(rec) {
  const rows = [
    { key: 'students', name: 'Students', unit: 'students', period: 'total', used: A.students.count, limit: A.students.limit, help: 'Active students in your classes.', next: planOf(rec)?.limit ?? null },
    ...A.limits.map((l) => ({ ...l, next: rec ? (LIMITS[l.key]?.[rec] ?? null) : undefined })),
  ];
  const meterRow = (r) => {
    const pct = r.limit ? Math.min(100, Math.round((r.used / r.limit) * 100)) : 0;
    const tone = r.limit === null ? 'is-free' : pct >= 100 ? 'is-full' : pct >= 80 ? 'is-near' : '';
    return h('div', { class: `up-use ${tone}` },
      h('div', { class: 'd-flex justify-content-between gap-2' },
        h('span', { class: 'up-use-name' }, r.name, r.period === 'month' ? h('small', {}, ' this month') : null),
        h('span', { class: 'up-use-num' }, r.limit === null ? `${r.used} · Unlimited` : `${r.used} of ${r.limit}`)),
      h('span', { class: 'up-meter', role: 'progressbar', 'aria-label': r.name, 'aria-valuenow': r.used, 'aria-valuemin': 0, 'aria-valuemax': r.limit ?? r.used }, h('span', { style: { width: `${r.limit === null ? 100 : pct}%` } })),
      rec && r.limit !== null && r.next !== undefined ? h('small', { class: 'up-use-next' }, icon('arrow-right', { size: 12 }), `${nameOf(rec)}: ${r.next === null ? 'unlimited' : `${r.next} ${r.unit}`}`) : null);
  };
  return h('div', { class: 'component-theme p-4 mb-4' },
    h('div', { class: 'd-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap' },
      h('div', { class: 'sub-topic main-text' }, 'What you use now'),
      h('span', { class: 'text-13 sub-text' }, 'Monthly limits start again on the 1st')),
    h('div', { class: 'up-use-grid' }, rows.map(meterRow)));
}

// ---------- 3. the plans ----------
function plansCard(rec, canPreview) {
  const mine = paidKey();
  const plans = S.plans.filter((p) => ORDER.includes(p.key));
  const best = Math.max(0, ...plans.filter((p) => p.monthly > 0).map((p) => Math.round((p.yearly_discount / p.monthly) * 100)));
  // Monthly / Yearly switch and pricing cards: the same design as My Subscription → Plans (.ex-plan in enlixo-ui.css)
  const toggle = h('div', { class: 'ex-plan-cycle', role: 'group', 'aria-label': 'Pay monthly or yearly' },
    ['monthly', 'yearly'].map((c) => h('button', { type: 'button', class: `ex-plan-cycle-btn${cycle === c ? ' active' : ''}`, 'aria-pressed': String(cycle === c), onclick: () => { cycle = c; draw(); } },
      c === 'monthly' ? 'Monthly' : 'Yearly', c === 'yearly' && best ? h('span', { class: 'ex-plan-save' }, `Save ${best}%`) : null)));
  const tick = (text, strong = false) => h('li', { class: strong ? 'fw-semibold main-text' : '' }, h('span', { class: 'ex-plan-tick' }, icon('check', { size: 16 })), h('span', {}, text));
  const card = (p) => {
    const isMine = p.key === mine;
    const higher = rank(p.key) > rank(mine);
    const isRec = p.key === rec;
    const custom = p.key === 'pro';
    const adds = A.features.filter((f) => f.plans.includes(p.key) && !f.plans.includes(ORDER[rank(p.key) - 1] ?? '')).map((f) => f.name);
    const limitLines = Object.entries(LIMITS).map(([k, v]) => {
      const l = A.limits.find((x) => x.key === k);
      return l ? `${v[p.key] === null || v[p.key] === undefined ? 'Unlimited' : v[p.key]} ${l.unit}${l.period === 'month' && v[p.key] !== null ? ' a month' : ''}` : null;
    }).filter(Boolean);
    const tone = { start: 'blue', grow: 'green', pro: 'purple' }[p.key] ?? 'blue';
    const yearlyMonthly = Math.max(0, p.monthly - p.yearly_discount);
    const action = isMine ? h('div', { class: 'ex-plan-current mt-auto' }, icon('check', { size: 18 }), 'This is your plan')
      : custom ? h('a', { class: 'custom-btn w-100 mt-auto text-decoration-none d-inline-flex align-items-center justify-content-center gap-2', href: waLink('I would like a CUSTOM plan for my classes.'), target: '_blank', rel: 'noopener' }, icon('whatsapp', { size: 18 }), 'Talk to ENLIXO')
        : higher ? h('button', { type: 'button', class: 'custom-btn w-100 mt-auto', onclick: () => openConfirm(p.key) }, `Upgrade to ${p.name}`)
          : h('a', { class: 'custom-outline-btn w-100 mt-auto text-decoration-none d-inline-flex justify-content-center', href: 'subscription?tab=plans' }, `Move to ${p.name}`);
    return h('div', { class: 'col-12 col-md-6 col-xl-3' }, h('div', { class: `ex-plan tone-${tone}${isMine ? ' is-mine' : ''}${isRec ? ' is-rec' : ''}` },
      isMine ? h('div', { class: 'ex-plan-ribbon' }, 'Your plan') : isRec ? h('div', { class: 'ex-plan-ribbon' }, 'Best next step') : null,
      h('div', { class: 'ex-plan-head' },
        h('span', { class: 'ex-plan-ico' }, icon(p.key === 'pro' ? 'crown' : p.key === 'grow' ? 'growth' : 'classes', { size: 22 })),
        h('div', { class: 'min-w-0' }, h('div', { class: 'ex-plan-name' }, p.name), h('div', { class: 'ex-plan-tagline' }, p.tagline || ''))),
      custom ? h('div', { class: 'ex-plan-price' }, h('span', { class: 'ex-plan-amount' }, "Let's talk"))
        : h('div', { class: 'ex-plan-price' }, h('span', { class: 'ex-plan-cur' }, 'LKR'), h('span', { class: 'ex-plan-amount' }, lkr(monthlyPrice(p)).replace(/^LKR\s*/, '')), h('span', { class: 'ex-plan-per' }, p.monthly ? '/ month' : 'forever')),
      h('div', { class: 'ex-plan-note' }, custom || !p.yearly_discount ? '\u00a0'
        : cycle === 'yearly' ? [`${lkr(yearlyMonthly * 12)} a year, `, h('b', {}, `you save ${lkr(p.yearly_discount * 12)}`)]
          : ['or ', h('b', {}, `${lkr(yearlyMonthly)} a month`), ' when you pay yearly']),
      h('ul', { class: 'ex-plan-list' },
        tick(p.limit ? `Up to ${p.limit} students` : 'Any number of students', true),
        rank(p.key) > 0 ? tick(`Everything in ${plans[rank(p.key) - 1]?.name ?? ''}`, true) : null,
        adds.slice(0, rank(p.key) > 0 ? 5 : 6).map((n) => tick(n)),
        !custom && limitLines.length ? tick(limitLines.join(' · ')) : null),
      action,
      higher && !custom && canPreview ? h('button', { type: 'button', class: 'up-try w-100 justify-content-center mt-2', onclick: () => startPreview(p.key) }, icon('magic', { size: 16 }), 'Ask for a 7-day free trial') : null));
  };
  return h('div', { class: 'component-theme p-4 mb-4', id: 'up-plans' },
    h('div', { class: 'ex-plan-top' },
      h('div', { class: 'ex-plan-info' }, icon('info', { size: 18 }), h('span', {}, 'Prices in LKR. Your classes, students and records stay when you change plan.')),
      toggle),
    confirming ? confirmPanel(confirming) : null,
    h('div', { class: 'row g-4 align-items-stretch' }, plans.map(card)));
}

function openConfirm(key) {
  confirming = key;
  draw();
}

/** Inline confirm (no pop-up): the price, what happens today and what happens if it is not paid. */
function confirmPanel(key) {
  const p = planOf(key);
  const t = S.tutor;
  const fromFree = paidKey() === 'free';
  const paying = !fromFree && t.period_end && ['active', 'due'].includes(t.status);
  const amount = cycle === 'yearly' ? monthlyPrice(p) * 12 : p.monthly;
  const steps = fromFree
    ? [`${p.name} works for you straight away.`, `Your first invoice of ${lkr(amount)} (${cycle}) is made today and is due in 7 days.`, 'Pay by bank transfer and upload the slip. Not paid in 7 days? You go back to FREE and nothing is lost.']
    : paying
      ? [`${p.name} works for you straight away.`, `You pay only the difference for the days left until ${day(t.period_end)}.`, `From then on, ${lkr(p.monthly)} a month${cycle === 'yearly' ? ' (yearly price applies at renewal)' : ''}.`]
      : [`${p.name} becomes your plan now.`, 'The open invoice is made again with the new price.', 'Pay it on the invoice page to keep everything on.'];
  const box = h('div', { class: 'up-confirm mb-4', id: 'up-confirm' });
  const go = h('button', { type: 'button', class: 'custom-btn px-5', onclick: async () => {
    go.disabled = true;
    try {
      const { data } = await api.post('/tutor/subscription/change', { plan: key, cycle });
      Toast.success(data.message);
      // an invoice was made (upgrade from FREE / difference): go and pay it
      const { data: sub } = await api.get('/tutor/subscription');
      const open = [...sub.invoices].reverse().find((i) => ['open', 'part', 'overdue'].includes(i.status));
      setTimeout(() => { location.href = open ? `invoice?invoice-id=${open.id}` : 'subscription'; }, 900);
    } catch (e) {
      go.disabled = false;
      Toast.error(e.message);
    }
  } }, `Confirm: Upgrade to ${p.name}`);
  fill(box,
    h('div', { class: 'd-flex align-items-center gap-3 mb-3' }, h('span', { class: 'up-confirm-ico' }, icon('crown', { size: 22 })),
      h('div', {}, h('div', { class: 'text-18 main-text fw-semibold' }, `Upgrade to ${p.name}`), h('div', { class: 'text-13 sub-text' }, `${lkr(monthlyPrice(p))} a month · ${cycle}`))),
    h('ol', { class: 'up-confirm-steps' }, steps.map((s) => h('li', {}, s))),
    h('div', { class: 'd-flex justify-content-end gap-2 flex-wrap' }, h('button', { type: 'button', class: 'custom-btn px-4', onclick: () => { confirming = null; draw(); } }, 'Not now'), go));
  return box;
}

async function startPreview(key) {
  try {
    const { data } = await api.post('/tutor/subscription/preview', { plan: key });
    Toast.success(data.message);
    TR.pending = data.request;
    draw();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  } catch (e) {
    Toast.error(e.message);
  }
}

// ---------- 4. every feature, and the usual worries ----------
function compareCard() {
  const cats = [...new Set(A.features.map((f) => f.category))];
  const plans = ['free', 'start', 'grow'];
  const mark = (on) => h('span', { class: on ? 'up-yes' : 'up-no', 'aria-label': on ? 'Included' : 'Not included' }, on ? icon('check', { size: 16 }) : '–');
  const head = h('tr', {}, h('th', { scope: 'col' }, 'Feature'), plans.map((k) => h('th', { scope: 'col', class: `text-center${k === paidKey() ? ' is-mine' : ''}` }, nameOf(k))));
  const rows = [];
  rows.push(h('tr', { class: 'up-cat' }, h('th', { colspan: 4 }, 'Limits')));
  rows.push(h('tr', {}, h('td', {}, 'Students'), plans.map((k) => h('td', { class: 'text-center' }, String(planOf(k)?.limit ?? 'Any')))));
  for (const [k, v] of Object.entries(LIMITS)) {
    const l = A.limits.find((x) => x.key === k);
    if (!l) continue;
    rows.push(h('tr', {}, h('td', {}, l.name, l.period === 'month' ? h('small', { class: 'sub-text' }, ' a month') : null), plans.map((p) => h('td', { class: 'text-center' }, v[p] === null ? 'Unlimited' : String(v[p])))));
  }
  for (const c of cats) {
    const fs = A.features.filter((f) => f.category === c && f.plans.some((p) => plans.includes(p)));
    if (!fs.length) continue;
    rows.push(h('tr', { class: 'up-cat' }, h('th', { colspan: 4 }, c)));
    fs.forEach((f) => rows.push(h('tr', { class: q.get('feature') === f.key ? 'is-hl' : '' }, h('td', {}, h('span', { title: f.description }, f.name)), plans.map((p) => h('td', { class: 'text-center' }, mark(f.plans.includes(p)))))));
  }
  return h('div', { class: 'component-theme p-4 mb-4', id: 'up-compare' },
    h('div', { class: 'sub-topic main-text mb-3' }, 'Every feature, plan by plan'),
    h('div', { class: 'table-responsive-wrapper' }, h('div', { class: 'table-responsive' }, h('table', { class: 'table ex-v3-table align-middle m-0 up-table' }, h('thead', {}, head), h('tbody', {}, rows)))));
}

function faqCard() {
  const qa = [
    ['What happens to my students and records?', 'Nothing is deleted when you change plan, try a plan or go back. Your classes, students, fees and recordings stay exactly as they are.'],
    ['How do I pay?', 'Pay the invoice by bank transfer or deposit into an ENLIXO account and upload the slip on the invoice page. ENLIXO checks it within one working day.'],
    ['What if I do not pay after upgrading from FREE?', 'After 7 days you simply go back to FREE. Your class portal stays open for students.'],
    ['How does the 7-day free trial work?', 'Ask for it here. Once the ENLIXO team approves your request you get every feature of that plan for a week, once, with no invoice. When the week ends, you are back on your own plan unless you upgrade.'],
    ['Can I go back to a smaller plan?', 'Yes, from My Subscription. A paid month runs to its end first, and your students must fit the smaller plan.'],
  ];
  return h('div', { class: 'component-theme p-4 mb-4' },
    h('div', { class: 'sub-topic main-text mb-3' }, 'Questions tutors ask'),
    h('div', { class: 'up-faq' }, qa.map(([qq, a]) => h('details', {}, h('summary', {}, qq), h('p', {}, a)))),
    h('div', { class: 'd-flex flex-wrap gap-2 mt-3' },
      h('a', { class: 'custom-btn px-4 text-decoration-none d-inline-flex align-items-center gap-2', href: waLink('I have a question about upgrading my plan.'), target: '_blank', rel: 'noopener' }, icon('whatsapp', { size: 18 }), 'Ask ENLIXO on WhatsApp'),
      h('a', { class: 'custom-btn px-4 text-decoration-none d-inline-flex align-items-center gap-2', href: `tel:${ENLIXO_PHONE}` }, icon('phone', { size: 18 }), `Call ${ENLIXO_PHONE}`)));
}

function waLink(text) {
  const t = S.tutor;
  return `https://wa.me/94${ENLIXO_PHONE.replace(/^0/, '')}?text=${encodeURIComponent(`Hi ENLIXO, I am ${t.name} (Tutor ID T-${t.id}). ${text}`)}`;
}
