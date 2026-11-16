// Tutor → Renew (tutor/renew.php): the page a tutor sees while their ENLIXO subscription is suspended or cancelled
// (and a summary page otherwise). Big status card, what it means, three steps to switch everything back on, the
// open invoice with Pay Now, the plans, and ENLIXO's WhatsApp / call buttons. Phone first.
import { QuickGuide } from '../components/QuickGuide.js';
import { api, h, fill, icon, lkr, day, plural, chip } from './admin-common.js';

QuickGuide.attachAll();
const root = document.getElementById('rn-page');
const actions = document.getElementById('rn-actions');
const ENLIXO_PHONE = '0769679778';
fill(root, h('div', { class: 'component-theme p-5 text-center sub-text' }, 'Loading…'));
load();

async function load() {
  let D;
  try { D = (await api.get('/tutor/subscription')).data; } catch (e) { fill(root, h('div', { class: 'component-theme p-4 text-dark-red' }, e.message)); return; }
  const t = D.tutor;
  const locked = ['suspended', 'cancelled'].includes(t.status);
  const open = [...D.invoices].reverse().find((i) => ['open', 'overdue', 'part'].includes(i.status));
  const waiting = open && D.payments.some((p) => p.status === 'waiting' && p.invoice_id === open.id);
  const wa = `https://wa.me/94${ENLIXO_PHONE.replace(/^0/, '')}?text=${encodeURIComponent(`Hi ENLIXO, I am ${t.name} (Tutor ID T-${t.id}). I want to renew my subscription.`)}`;

  fill(actions,
    h('a', { class: 'custom-btn ex-btn-lg px-4 text-decoration-none d-inline-flex align-items-center gap-2', href: wa, target: '_blank', rel: 'noopener' }, icon('whatsapp', { size: 18 }), 'WhatsApp ENLIXO'),
    open && !waiting ? h('a', { class: 'custom-btn ex-btn-lg px-5 text-decoration-none', href: `invoice?invoice-id=${open.id}#pay` }, 'Pay Now')
      : h('a', { class: 'custom-btn ex-btn-lg px-5 text-decoration-none', href: 'subscription' }, 'My Subscription'));

  const title = !locked ? 'Your subscription is on' : t.status === 'cancelled' ? 'Your subscription has ended' : waiting ? 'We are checking your payment' : 'Your subscription is paused';
  const sub = !locked ? `${D.plan?.name ?? t.plan_name} plan · ${t.status_label}`
    : waiting ? 'Your slip was received. ENLIXO checks it within one working day and switches everything on again.'
      : t.status === 'cancelled' ? 'Your classes, students and records are kept safely. Renew to open them again.'
        : `The invoice${open ? ` of ${lkr(open.total - open.paid)}` : ''} was not paid in time. Pay it and everything opens again.`;

  const step = (n, done, now, head, text, cta) => h('li', { class: `rn-step${done ? ' is-done' : ''}${now ? ' is-now' : ''}` },
    h('span', { class: 'rn-step-n' }, done ? icon('check', { size: 16 }) : String(n)),
    h('span', { class: 'min-w-0' }, h('strong', {}, head), h('small', {}, text), cta));

  fill(root,
    h('div', { class: `rn-hero ${locked ? (waiting ? 'is-wait' : 'is-off') : 'is-on'}` },
      h('span', { class: 'rn-hero-ico' }, icon(locked ? (waiting ? 'hourglass' : 'lock') : 'check', { size: 30 })),
      h('div', { class: 'min-w-0' }, h('h2', {}, title), h('p', {}, sub),
        h('div', { class: 'd-flex flex-wrap gap-2 mt-2' }, chip(t.status, t.status_label), h('span', { class: 'rn-tag' }, `${D.plan?.name ?? t.plan_name} · ${t.cycle}`), t.status_reason ? h('span', { class: 'rn-tag' }, t.status_reason) : null))),
    h('div', { class: 'row g-4 mt-0' },
      h('div', { class: 'col-12 col-xl-7' },
        h('div', { class: 'component-theme p-4 h-100' },
          h('div', { class: 'sub-topic mb-3' }, locked ? 'Switch everything on again' : 'Your account'),
          h('ol', { class: 'rn-steps' },
            step(1, Boolean(open) || !locked, !open && locked, 'Get your invoice', open ? `${open.number} · ${lkr(open.total - open.paid)} to pay` : locked ? 'Ask ENLIXO on WhatsApp for your renewal invoice.' : 'Nothing to pay right now.',
              !open && locked ? h('a', { class: 'custom-btn table-btn px-3 mt-2 text-decoration-none d-inline-flex', href: wa, target: '_blank', rel: 'noopener' }, 'WhatsApp ENLIXO') : null),
            step(2, waiting || !locked, Boolean(open) && !waiting && locked, 'Pay and upload the slip', 'Bank transfer or deposit into an ENLIXO account, then upload the photo or PDF of the slip.',
              open && !waiting ? h('a', { class: 'custom-btn table-btn px-3 mt-2 text-decoration-none d-inline-flex', href: `invoice?invoice-id=${open.id}#pay` }, 'Pay Now') : null),
            step(3, !locked, waiting, 'ENLIXO checks it', 'Usually within one working day. Then your classes, students and parents are on again.', null)),
          h('div', { class: 'rn-note mt-3' }, icon('info', { size: 16 }), h('span', {}, locked
            ? 'While paused, your students and parents cannot sign in and see "The class portal is paused". Your classes, students, fees and recordings are kept.'
            : 'Your students and parents can use the portal.')))),
      h('div', { class: 'col-12 col-xl-5' },
        h('div', { class: 'component-theme p-4 h-100' },
          h('div', { class: 'sub-topic mb-3' }, 'Plans'),
          h('div', { class: 'rn-plans' }, D.plans.map((p) => h('div', { class: `rn-plan${p.key === t.plan ? ' is-on' : ''}` },
            h('div', { class: 'd-flex justify-content-between align-items-baseline gap-2' }, h('strong', {}, p.name), h('span', { class: 'rn-price' }, lkr(p.monthly), h('small', {}, '/month'))),
            h('small', {}, p.limit ? `Up to ${p.limit} students` : 'Unlimited students'),
            p.key === t.plan ? h('span', { class: 'rn-current' }, 'Your plan') : null))),
          h('p', { class: 'text-13 sub-text mt-3 mb-0' }, 'Want another plan? Choose it on ', h('a', { href: 'subscription?tab=plans' }, 'My Subscription › Plans'), locked ? ' or ask ENLIXO.' : '.'),
          h('div', { class: 'd-flex gap-2 mt-3 flex-wrap' },
            h('a', { class: 'custom-btn px-3 text-decoration-none d-inline-flex align-items-center gap-2', href: wa, target: '_blank', rel: 'noopener' }, icon('whatsapp', { size: 16 }), 'WhatsApp'),
            h('a', { class: 'custom-btn px-3 text-decoration-none d-inline-flex align-items-center gap-2', href: `tel:${ENLIXO_PHONE}` }, icon('phone', { size: 16 }), `Call ${ENLIXO_PHONE}`))))));
}
