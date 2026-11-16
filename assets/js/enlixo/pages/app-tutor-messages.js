// Tutor → Message Center (tutor/message-center.php): write a message (channel, classes, who, subject, text with
// {name} {class} {tutor}), a live preview of who gets it, Send = create it and open its page (tutor/message.php).
// Below: the last 30 days in numbers and the Sent Messages table. API: /api/v1/tutor/messages* (MessageService).
import { QuickGuide } from '../components/QuickGuide.js';
import { StatCard } from '../components/StatCard.js';
import { api, h, fill, icon, plural, field, showErrors, simpleTable, Toast, EmptyState } from './admin-common.js';

QuickGuide.attachAll();
const root = document.getElementById('mc-page');
const actions = document.getElementById('mc-actions');
const MODE = {
  phone: ['From your phone', 'WhatsApp opens with the message for each person; you press Send.'],
  enlixo: ['ENLIXO WhatsApp number', 'Sent automatically from the ENLIXO number.'],
  own: ['Your WhatsApp Business number', 'Sent automatically from your own number.'],
};
const fieldErrors = (e) => e.details?.fields ?? e.details ?? {};
let S = null; // setup
const form = { channel: 'whatsapp', audience: 'students', classes: new Set(), subject: '', body: '' };

fill(actions,
  h('a', { href: 'whatsapp-settings', class: 'ex-gear', title: 'WhatsApp settings', 'aria-label': 'WhatsApp settings' }, icon('settings', { size: 28 })),
  h('a', { href: '#compose', class: 'custom-btn ex-btn-lg px-5 text-decoration-none', onclick: (e) => { e.preventDefault(); document.getElementById('compose')?.scrollIntoView({ behavior: 'smooth' }); document.getElementById('mc-body')?.focus(); } }, 'New Message'));
fill(root, h('div', { class: 'component-theme p-5 text-center sub-text' }, 'Loading…'));
load();

async function load() {
  try {
    S = (await api.get('/tutor/messages/setup')).data;
    S.tutor = S.tutor ?? 'your tutor';
  } catch (e) {
    fill(root, EmptyState.error ? EmptyState.error(e.message, load) : h('div', { class: 'component-theme p-4' }, e.message));
    return;
  }
  if (!S.email.allowed) form.channel = 'whatsapp';
  if (S.classes.length === 1) form.classes.add(S.classes[0].id);
  draw();
  history();
}

function tile(name, value, ico, title, sub, { disabled = false, extra = null } = {}) {
  const input = h('input', { type: 'radio', name, value, checked: form[name] === value, disabled, onchange: () => { form[name] = value; draw(); } });
  return h('label', { class: `mc-tile${form[name] === value ? ' is-on' : ''}${disabled ? ' is-off' : ''}` }, input,
    h('span', { class: 'mc-tile-ico' }, icon(ico, { size: 22 })),
    h('span', { class: 'min-w-0' }, h('strong', {}, title), h('small', {}, sub), extra));
}

function draw() {
  const wa = S.whatsapp;
  const mode = MODE[wa.mode];
  const channel = h('div', { class: 'mc-tiles' },
    tile('channel', 'whatsapp', 'whatsapp', 'WhatsApp', mode[0], { extra: h('a', { class: 'mc-tile-link', href: 'whatsapp-settings' }, 'Change') }),
    tile('channel', 'email', 'mail', 'E-mail', S.email.allowed ? (S.email.ready ? 'To the e-mail address of each person' : 'Not set up on the server yet') : 'Not on your plan', { disabled: !S.email.allowed }));
  const who = h('div', { class: 'mc-tiles' },
    tile('audience', 'students', 'students', 'Students', 'Every active student of the classes'),
    tile('audience', 'parents', 'users', 'Parents', 'The parents linked to those students'),
    tile('audience', 'both', 'communication', 'Both', 'Students and their parents'));
  const allOn = S.classes.length && S.classes.every((c) => form.classes.has(c.id));
  const classes = S.classes.length
    ? h('div', {},
      h('label', { class: 'mc-check mc-check-all' }, h('input', { type: 'checkbox', checked: allOn, onchange: (e) => { S.classes.forEach((c) => (e.target.checked ? form.classes.add(c.id) : form.classes.delete(c.id))); draw(); } }), 'All classes'),
      h('div', { class: 'mc-checks', 'data-key': 'classes', id: 'mc-classes' }, S.classes.map((c) => h('label', { class: `mc-check${form.classes.has(c.id) ? ' is-on' : ''}` },
        h('input', { type: 'checkbox', checked: form.classes.has(c.id), onchange: (e) => { if (e.target.checked) form.classes.add(c.id); else form.classes.delete(c.id); draw(); } }),
        h('span', { class: 'min-w-0' }, h('strong', {}, c.name), h('small', {}, plural(c.students, 'student')))))),
      h('div', { class: 'text-12 text-dark-red mt-1 d-none', 'data-err': 'mc-classes' }))
    : h('div', { class: 'ex-panel-soft p-3 text-14' }, 'You have no active class yet. ', h('a', { href: 'create-class' }, 'Create a class'), ' first.');

  const subject = h('input', { class: 'form-control custom-input', value: form.subject, placeholder: 'Enter the subject, for example "No class this Saturday"', 'data-key': 'subject', maxlength: 150, oninput: (e) => { form.subject = e.target.value; preview(); } });
  const max = form.channel === 'email' ? 5000 : 1000;
  const counter = h('span', { class: 'text-12 sub-text' }, `${form.body.length} / ${max}`);
  const body = h('textarea', { class: 'form-control custom-input', id: 'mc-body', rows: 7, value: form.body, maxlength: max, 'data-key': 'body',
    placeholder: 'Write your message, for example "Hi {name}, there is no {class} class this Saturday. We meet again next Saturday at 2 pm. – {tutor}"',
    oninput: (e) => { form.body = e.target.value; counter.textContent = `${form.body.length} / ${max}`; preview(); } });
  body.value = form.body;
  const insert = (tag) => { const s = body.selectionStart ?? body.value.length; body.setRangeText(tag, s, body.selectionEnd ?? s, 'end'); body.focus(); body.dispatchEvent(new Event('input')); };
  const tags = h('div', { class: 'mc-tags' }, h('span', { class: 'text-12 sub-text' }, 'Add:'),
    [['{name}', 'Name'], ['{class}', 'Class'], ['{tutor}', 'Your name'], ['{student}', 'Student (for parents)']].map(([t, l]) => h('button', { type: 'button', class: 'mc-tag', onclick: () => insert(t) }, l)));

  const send = h('button', { type: 'button', class: 'custom-btn px-5 ex-btn-lg', id: 'mc-send', onclick: () => go(send) },
    icon(form.channel === 'email' ? 'send' : 'whatsapp', { size: 18 }), form.channel === 'email' ? 'Send E-mail' : (wa.mode === 'phone' ? 'Continue to WhatsApp' : 'Send WhatsApp'));

  fill(root,
    h('div', { class: 'row g-4 mb-4', id: 'mc-stats' }),
    h('div', { class: 'row g-4', id: 'compose' },
      h('div', { class: 'col-12 col-xl-8' }, h('div', { class: 'component-theme p-4', id: 'mc-form' },
        h('div', { class: 'mc-step' }, h('span', {}, '1'), 'How do you want to send it?'), channel,
        h('div', { class: 'text-12 text-dark-red mt-1 d-none', 'data-err': 'mc-channel' }), h('span', { 'data-key': 'channel', id: 'mc-channel', class: 'd-none' }),
        form.channel === 'whatsapp' ? h('div', { class: 'mc-note' }, icon('info', { size: 16 }), mode[1], wa.mode === 'phone' && (wa.modes.own.allowed || wa.modes.enlixo.allowed) ? [' ', h('a', { href: 'whatsapp-settings' }, 'Send automatically instead')] : null) : null,
        h('div', { class: 'mc-step' }, h('span', {}, '2'), 'Which classes?'), classes,
        h('div', { class: 'mc-step' }, h('span', {}, '3'), 'Who gets it?'), who, h('span', { 'data-key': 'audience', id: 'mc-audience', class: 'd-none' }),
        h('div', { class: 'mc-step' }, h('span', {}, '4'), 'Your message'),
        form.channel === 'email' ? field('Subject', subject, { required: true, id: 'mc-subject', info: 'The subject line people see in their inbox. Keep it short, for example "Exam date changed".' }) : null,
        field('Message', body, { required: true, id: 'mc-body', info: 'Write it once. {name} becomes each person\'s first name, {class} the class, {tutor} your name or institute. WhatsApp messages are up to 1000 characters.' }),
        h('div', { class: 'd-flex justify-content-between align-items-center flex-wrap gap-2 mb-2' }, tags, counter),
        h('div', { class: 'd-flex justify-content-end mt-3' }, send))),
      h('div', { class: 'col-12 col-xl-4' }, h('div', { class: 'component-theme p-4 mc-preview-card', id: 'mc-preview' }))),
    h('div', { class: 'component-theme p-4 mt-4', id: 'mc-history' }, h('div', { class: 'sub-topic mb-3' }, 'Sent Messages'), h('div', { id: 'mc-history-body' }, h('div', { class: 'sub-text text-14' }, 'Loading…'))));
  preview();
  stats();
}

let pTimer = null;
function preview() {
  clearTimeout(pTimer);
  pTimer = setTimeout(async () => {
    const box = document.getElementById('mc-preview');
    if (!box) return;
    const sampleText = (name, cls) => (form.body || 'Your message appears here.').replaceAll('{name}', name.split(' ')[0]).replaceAll('{class}', cls || 'your class').replaceAll('{tutor}', S.tutor ?? 'your tutor').replaceAll('{student}', name);
    if (!form.classes.size) { fill(box, h('div', { class: 'sub-topic mb-2' }, 'Preview'), h('p', { class: 'text-14 sub-text m-0' }, 'Choose a class to see who gets it.')); return; }
    let p;
    try { p = (await api.post('/tutor/messages/preview', { channel: form.channel, audience: form.audience, classes: [...form.classes] })).data; } catch (e) { fill(box, h('p', { class: 'text-14 text-dark-red m-0' }, e.message)); return; }
    const first = p.sample[0];
    const bubble = form.channel === 'email'
      ? h('div', { class: 'mc-mail' }, h('div', { class: 'mc-mail-head' }, h('strong', {}, form.subject || 'Subject'), h('small', {}, `To: ${first?.address ?? '—'}`)), h('div', { class: 'mc-mail-body' }, sampleText(first?.name ?? 'Nimal', first?.class ?? '')))
      : h('div', { class: 'mc-wa' }, h('div', { class: 'mc-wa-bubble' }, sampleText(first?.name ?? 'Nimal', first?.class ?? ''), h('small', {}, new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }))));
    fill(box,
      h('div', { class: 'sub-topic mb-3' }, 'Preview'),
      h('div', { class: 'mc-reach' },
        h('div', {}, h('strong', {}, String(p.reachable)), h('small', {}, `will get it`)),
        h('div', {}, h('strong', {}, String(p.students)), h('small', {}, 'students')),
        h('div', {}, h('strong', {}, String(p.parents)), h('small', {}, 'parents'))),
      p.missing ? h('div', { class: 'mc-warn' }, icon('warning', { size: 16 }), `${plural(p.missing, 'person', 'people')} without ${form.channel === 'email' ? 'an e-mail address' : 'a WhatsApp number'} will be skipped.`) : null,
      h('div', { class: 'text-12 sub-text mb-2 mt-3' }, first ? `How ${first.name.split(' ')[0]} sees it` : 'How it looks'), bubble);
  }, 350);
}

async function go(btn) {
  showErrors(root, {});
  btn.disabled = true;
  try {
    const { data } = await api.post('/tutor/messages', { channel: form.channel, audience: form.audience, classes: [...form.classes], subject: form.subject, body: form.body });
    location.href = `message?id=${data.id}`;
  } catch (e) {
    btn.disabled = false;
    const f = fieldErrors(e);
    showErrors(root, f);
    Toast.error(e.message);
  }
}

let H = null;
async function history(page = 1) {
  try {
    const r = await api.get(`/tutor/messages?page=${page}&per_page=10`);
    H = r;
    stats();
    const box = document.getElementById('mc-history-body');
    if (!box) return;
    const when = (d) => new Date(d.replace(' ', 'T')).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
    fill(box, simpleTable([
      { label: 'Sent', render: (m) => h('span', { class: 'text-14 main-text' }, when(m.created_at)) },
      { label: 'Message', render: (m) => h('a', { class: 'mc-hist-msg', href: `message?id=${m.id}` }, h('strong', {}, m.subject || m.body.slice(0, 60)), h('small', {}, m.classes.join(', '))) },
      { label: 'By', render: (m) => h('span', { class: `mc-chip is-${m.channel}` }, icon(m.channel === 'email' ? 'mail' : 'whatsapp', { size: 14 }), m.channel === 'email' ? 'E-mail' : (MODE[m.mode]?.[0] ?? 'WhatsApp')) },
      { label: 'To', render: (m) => h('span', { class: 'text-14' }, { students: 'Students', parents: 'Parents', both: 'Students & parents' }[m.audience]) },
      { label: 'Delivered', render: (m) => h('span', { class: 'text-14 main-text' }, `${m.sent} / ${m.total - m.skipped}`, m.failed ? h('small', { class: 'text-dark-red ms-1' }, `${m.failed} failed`) : null) },
      { label: '', render: (m) => h('a', { class: 'custom-btn table-btn px-3 text-decoration-none', href: `message?id=${m.id}` }, m.status === 'sending' ? 'Continue' : 'View') },
    ], r.data, { title: 'No messages yet', text: 'Messages you send are listed here with who got them.' }),
    r.meta.pages > 1 ? h('div', { class: 'd-flex justify-content-end gap-2 mt-3' },
      h('button', { type: 'button', class: 'custom-btn table-btn px-3', disabled: page <= 1, onclick: () => history(page - 1) }, 'Previous'),
      h('span', { class: 'text-14 sub-text align-self-center' }, `${page} / ${r.meta.pages}`),
      h('button', { type: 'button', class: 'custom-btn table-btn px-3', disabled: page >= r.meta.pages, onclick: () => history(page + 1) }, 'Next')) : null);
  } catch (e) {
    const box = document.getElementById('mc-history-body');
    if (box) fill(box, h('p', { class: 'text-14 text-dark-red' }, e.message));
  }
}

function stats() {
  const box = document.getElementById('mc-stats');
  if (!box || !H) return;
  const t = H.meta.totals ?? {};
  fill(box, [
    ['communication', 'Messages', t.messages ?? 0, 'last 30 days'],
    ['check', 'Delivered', t.sent ?? 0, 'people reached, last 30 days'],
    ['whatsapp', 'WhatsApp', t.whatsapp ?? 0, MODE[S.whatsapp.mode][0]],
    ['mail', 'E-mail', t.email ?? 0, S.email.ready ? 'ready' : 'not set up'],
  ].map(([ico, label, value, caption]) => h('div', { class: 'col-12 col-md-6 col-xl-3' }, StatCard.card({ icon: ico, label, value: String(Number(value)), caption }))));
}
