// Tutor → one message (tutor/message.php?id=). E-mail and connected WhatsApp: sends the waiting people batch by batch
// (POST /tutor/messages/{id}/send) with a progress bar until nobody is waiting. WhatsApp from the tutor's phone: one
// "Open WhatsApp" button per person; opening it marks that person as sent. Then the full list with each status.
import { QuickGuide } from '../components/QuickGuide.js';
import { api, h, fill, icon, plural, Toast } from './admin-common.js';

QuickGuide.attachAll();
const root = document.getElementById('msg-page');
const actions = document.getElementById('msg-actions');
const id = Number(new URLSearchParams(location.search).get('id') || 0);
const MODE = { email: 'E-mail', phone: 'WhatsApp from your phone', enlixo: 'WhatsApp · ENLIXO number', own: 'WhatsApp · your number' };
const STATUS = { sent: ['Sent', 'is-good'], pending: ['Waiting', 'is-wait'], failed: ['Failed', 'is-bad'], skipped: ['Skipped', 'is-muted'] };
let M = null;
let sending = false;
let filter = 'all';

fill(root, h('div', { class: 'component-theme p-5 text-center sub-text' }, 'Loading…'));
load();

async function load() {
  try { M = (await api.get(`/tutor/messages/${id}`)).data; } catch (e) {
    fill(root, h('div', { class: 'component-theme p-5' }, h('div', { class: 'empty-state' }, h('div', { class: 'empty-state-icon' }, icon('inbox', { size: 28 })),
      h('div', { class: 'empty-state-title' }, 'Message not found'), h('div', { class: 'empty-state-text' }, e.message, ' ', h('a', { href: 'message-center' }, 'Back to Message Center')))));
    return;
  }
  draw();
  if (M.mode !== 'phone' && M.pending > 0) run();
}

async function run() {
  if (sending) return;
  sending = true;
  draw();
  while (M.pending > 0) {
    try { M = { ...M, ...(await api.post(`/tutor/messages/${id}/send`, {})).data }; } catch (e) { Toast.error(e.message); break; }
    draw();
  }
  sending = false;
  draw();
  if (M.pending === 0) Toast.success(M.failed ? `Done. ${plural(M.sent, 'person', 'people')} got it, ${M.failed} failed.` : `Done. ${plural(M.sent, 'person', 'people')} got the message.`);
}

function draw() {
  const reach = M.total - M.skipped;
  const done = M.sent + M.failed;
  const pct = reach ? Math.round((M.sent / reach) * 100) : 100;
  const phone = M.mode === 'phone';
  fill(actions,
    M.failed && !phone ? h('button', { type: 'button', class: 'custom-btn ex-btn-lg px-4', disabled: sending, onclick: retry }, 'Try Failed Again') : null,
    h('a', { class: 'custom-btn ex-btn-lg px-5 text-decoration-none', href: 'message-center' }, 'New Message'));

  const recips = M.recipients.filter((r) => filter === 'all' || r.status === filter);
  const tab = (key, label, n) => h('button', { type: 'button', class: `nav-link${filter === key ? ' active' : ''}`, onclick: () => { filter = key; draw(); } }, label, ' ', h('span', { class: 'tab-count' }, String(n)));
  const count = (s) => M.recipients.filter((r) => r.status === s).length;

  fill(root,
    h('div', { class: 'component-theme p-4 mb-4 mc-msg-head' },
      h('div', { class: 'd-flex align-items-start gap-3 flex-wrap' },
        h('span', { class: `mc-big-ico is-${M.channel}` }, icon(M.channel === 'email' ? 'mail' : 'whatsapp', { size: 26 })),
        h('div', { class: 'min-w-0 flex-grow-1' },
          h('div', { class: 'd-flex flex-wrap gap-2 mb-1' }, h('span', { class: `mc-chip is-${M.channel}` }, MODE[M.mode] ?? M.mode),
            h('span', { class: 'mc-chip' }, { students: 'Students', parents: 'Parents', both: 'Students & parents' }[M.audience]),
            M.classes.map((c) => h('span', { class: 'mc-chip' }, c))),
          M.subject ? h('div', { class: 'text-18 main-text fw-semibold' }, M.subject) : null,
          h('div', { class: 'mc-msg-body' }, M.body))),
      h('div', { class: 'mc-progress mt-4' },
        h('div', { class: 'd-flex justify-content-between text-14 mb-2' },
          h('strong', { class: 'main-text' }, phone ? `${M.sent} of ${reach} opened in WhatsApp` : sending ? `Sending… ${done} of ${reach}` : M.pending ? `${M.pending} waiting` : 'Finished'),
          h('span', { class: 'sub-text' }, `${pct}%`)),
        h('div', { class: 'mc-bar' }, h('span', { style: { width: `${pct}%` } }), h('span', { class: 'is-bad', style: { width: `${reach ? Math.round((M.failed / reach) * 100) : 0}%` } })),
        h('div', { class: 'mc-legend' },
          h('span', {}, h('i', { class: 'is-good' }), `${M.sent} sent`), h('span', {}, h('i', { class: 'is-bad' }), `${M.failed} failed`),
          h('span', {}, h('i', {}), `${M.pending} waiting`), M.skipped ? h('span', {}, h('i', { class: 'is-muted' }), `${M.skipped} skipped (no ${M.channel === 'email' ? 'e-mail' : 'WhatsApp number'})`) : null)),
      phone && M.pending ? h('div', { class: 'mc-note mt-3' }, icon('info', { size: 16 }), 'Press Open WhatsApp for each person. WhatsApp opens with the message ready; press Send there and come back for the next one.') : null),
    h('div', { class: 'component-theme p-4' },
      h('ul', { class: 'nav nav-tabs mb-3' }, h('li', { class: 'nav-item' }, tab('all', 'Everyone', M.recipients.length)),
        h('li', { class: 'nav-item' }, tab('pending', 'Waiting', count('pending'))), h('li', { class: 'nav-item' }, tab('sent', 'Sent', count('sent'))),
        h('li', { class: 'nav-item' }, tab('failed', 'Failed', count('failed'))), h('li', { class: 'nav-item' }, tab('skipped', 'Skipped', count('skipped')))),
      recips.length ? h('ul', { class: 'mc-people' }, recips.map(person))
        : h('div', { class: 'empty-state' }, h('div', { class: 'empty-state-icon' }, icon('inbox', { size: 28 })), h('div', { class: 'empty-state-title' }, 'Nobody here'), h('div', { class: 'empty-state-text' }, 'Choose another tab.'))));
}

function person(r) {
  const [label, cls] = STATUS[r.status];
  const open = r.link && r.status !== 'skipped'
    ? h('a', { class: 'custom-btn table-btn px-3 text-decoration-none d-inline-flex align-items-center gap-2', href: r.link, target: '_blank', rel: 'noopener',
      onclick: () => setTimeout(() => mark(r), 300) }, icon('whatsapp', { size: 16 }), r.status === 'sent' ? 'Open Again' : 'Open WhatsApp')
    : null;
  return h('li', { class: `mc-person is-${r.status}` },
    h('span', { class: 'mc-avatar' }, r.name.split(/\s+/).slice(0, 2).map((s) => s[0] ?? '').join('').toUpperCase()),
    h('span', { class: 'min-w-0 flex-grow-1' }, h('strong', {}, r.name), h('small', {}, [r.kind === 'parent' ? 'Parent' : 'Student', r.class, r.address].filter(Boolean).join(' · ')),
      r.error ? h('small', { class: 'text-dark-red' }, r.error) : null),
    h('span', { class: `ac-badge mc-status ${cls}` }, label), open);
}

async function mark(r) {
  if (r.status === 'sent') return;
  try { M = { ...M, ...(await api.post(`/tutor/messages/${id}/recipients/${r.id}/sent`, {})).data }; draw(); } catch (e) { Toast.error(e.message); }
}

async function retry() {
  try { M = { ...M, ...(await api.post(`/tutor/messages/${id}/retry`, {})).data }; draw(); run(); } catch (e) { Toast.error(e.message); }
}
