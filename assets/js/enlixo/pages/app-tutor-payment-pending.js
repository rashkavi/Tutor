// Tutor → Payment Being Checked (tutor/payment-pending.php): the slip the tutor sent, the steps until ENLIXO approves it,
// and ENLIXO's WhatsApp / call / e-mail. When nothing is waiting any more the page says so and offers the dashboard.
// Data: GET /api/v1/tutor/subscription.
import { QuickGuide } from '../components/QuickGuide.js';
import { api, h, fill, icon, lkr, day, chip, slipUrl } from './admin-common.js';

QuickGuide.attachAll();
const root = document.getElementById('pp-page');
const actions = document.getElementById('pp-actions');
const ENLIXO_PHONE = '0769679778';
const ENLIXO_MAIL = 'support@enviiz.com'; // the support address on the ENLIXO e-mails (includes/phpmailer/student-invoice.php)
fill(root, h('div', { class: 'component-theme p-5 text-center sub-text' }, 'Loading…'));
load();

async function load() {
  let D;
  try { D = (await api.get('/tutor/subscription')).data; } catch (e) { fill(root, h('div', { class: 'component-theme p-4 text-dark-red' }, e.message)); return; }
  const t = D.tutor;
  const waiting = [...D.payments].reverse().find((p) => p.status === 'waiting');
  const inv = waiting ? D.invoices.find((i) => i.id === waiting.invoice_id) : null;
  const wa = `https://wa.me/94${ENLIXO_PHONE.replace(/^0/, '')}?text=${encodeURIComponent(`Hi ENLIXO, I am ${t.name} (Tutor ID T-${t.id}). I uploaded my payment slip${waiting ? ` (${waiting.ref}, ${lkr(waiting.amount)})` : ''}. Please check it.`)}`;

  fill(actions,
    h('a', { class: 'custom-btn ex-btn-lg px-4 text-decoration-none d-inline-flex align-items-center gap-2', href: wa, target: '_blank', rel: 'noopener' }, icon('whatsapp', { size: 18 }), 'WhatsApp ENLIXO'),
    h('button', { type: 'button', class: 'custom-btn ex-btn-lg px-5', onclick: () => location.reload() }, icon('refresh', { size: 18 }), ' Check Again'));

  if (!waiting) {
    fill(root, h('div', { class: 'rn-hero is-on' }, h('span', { class: 'rn-hero-ico' }, icon('check', { size: 30 })),
      h('div', { class: 'min-w-0' }, h('h2', {}, 'Your payment is checked'), h('p', {}, 'Nothing is waiting any more. Everything is open again.'),
        h('a', { class: 'custom-btn px-4 mt-2 text-decoration-none d-inline-flex', href: 'dashboard' }, 'Go to Dashboard'))));
    return;
  }

  const step = (n, state, head, text) => h('li', { class: `rn-step${state === 'done' ? ' is-done' : ''}${state === 'now' ? ' is-now' : ''}` },
    h('span', { class: 'rn-step-n' }, state === 'done' ? icon('check', { size: 16 }) : String(n)),
    h('span', { class: 'min-w-0' }, h('strong', {}, head), h('small', {}, text)));
  const row = (label, value) => h('div', { class: 'pp-row' }, h('span', {}, label), h('strong', {}, value));

  fill(root,
    h('div', { class: 'rn-hero is-wait' },
      h('span', { class: 'rn-hero-ico' }, icon('hourglass', { size: 30 })),
      h('div', { class: 'min-w-0' },
        h('h2', {}, 'We are checking your payment'),
        h('p', {}, `Your slip of ${lkr(waiting.amount)} was received on ${day(waiting.date)}. ENLIXO checks it against the bank, usually within one working day, and then everything opens again.`),
        h('div', { class: 'd-flex flex-wrap gap-2 mt-2' }, chip('waiting', 'Waiting for approval'), h('span', { class: 'rn-tag' }, `${D.plan?.name ?? t.plan_name} · ${t.cycle}`)))),
    h('div', { class: 'row g-4 mt-0' },
      h('div', { class: 'col-12 col-xl-7' },
        h('div', { class: 'component-theme p-4 h-100' },
          h('div', { class: 'sub-topic mb-3' }, 'Your payment'),
          h('div', { class: 'pp-facts' },
            row('Payment', waiting.ref),
            row('Amount', lkr(waiting.amount)),
            row('Paid on', day(waiting.date)),
            row('Method', waiting.method || 'Bank transfer'),
            waiting.reference ? row('Slip reference', waiting.reference) : null,
            inv ? row('For invoice', `${inv.number} · ${lkr(inv.total)}`) : null,
            inv ? row('Invoice due', day(inv.due)) : null),
          h('div', { class: 'd-flex flex-wrap gap-2 mt-3' },
            waiting.slip ? h('a', { class: 'custom-btn px-4 text-decoration-none d-inline-flex align-items-center gap-2', href: slipUrl(waiting, false), target: '_blank', rel: 'noopener' }, icon('view', { size: 16 }), 'Open My Slip') : null,
            inv ? h('a', { class: 'custom-btn px-4 text-decoration-none d-inline-flex align-items-center gap-2', href: `invoice?invoice-id=${inv.id}` }, icon('invoice', { size: 16 }), 'View Invoice') : null),
          h('div', { class: 'sub-topic mt-4 mb-3' }, 'What happens next'),
          h('ol', { class: 'rn-steps' },
            step(1, 'done', 'Slip uploaded', `${day(waiting.date)} · ${lkr(waiting.amount)}`),
            step(2, 'now', 'ENLIXO checks it', 'Compared with the ENLIXO bank account, usually within one working day.'),
            step(3, '', 'Everything opens again', 'Your classes, fees and messages; this page sends you to the dashboard.')),
          h('div', { class: 'rn-note mt-3' }, icon('info', { size: 16 }), h('span', {}, 'Your students and parents can still use the class portal while we check. If the slip is rejected, the reason is shown on the invoice page and you can upload a new one.')))),
      h('div', { class: 'col-12 col-xl-5' },
        h('div', { class: 'component-theme p-4 h-100' },
          h('div', { class: 'sub-topic mb-2' }, 'Talk to ENLIXO'),
          h('p', { class: 'text-14 sub-text' }, 'Paid in a hurry, or the bank shows something different? Tell us and we check it at once.'),
          h('div', { class: 'd-flex flex-column gap-2' },
            h('a', { class: 'custom-btn px-3 text-decoration-none d-inline-flex align-items-center justify-content-center gap-2', href: wa, target: '_blank', rel: 'noopener' }, icon('whatsapp', { size: 18 }), 'WhatsApp ENLIXO'),
            h('a', { class: 'custom-btn px-3 text-decoration-none d-inline-flex align-items-center justify-content-center gap-2', href: `tel:${ENLIXO_PHONE}` }, icon('phone', { size: 18 }), `Call ${ENLIXO_PHONE}`),
            h('a', { class: 'custom-btn px-3 text-decoration-none d-inline-flex align-items-center justify-content-center gap-2', href: `mailto:${ENLIXO_MAIL}?subject=${encodeURIComponent(`Payment ${waiting.ref} – Tutor T-${t.id}`)}` }, icon('mail', { size: 18 }), ENLIXO_MAIL)),
          h('div', { class: 'pp-hours mt-3' }, icon('clock', { size: 16 }), h('span', {}, 'Monday to Saturday, 8.30 AM – 6.00 PM'))))));
}
