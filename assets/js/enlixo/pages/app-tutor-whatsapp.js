// Tutor → Settings → WhatsApp (tutor/whatsapp-settings.php): choose how Message Center sends WhatsApp messages.
//   From my phone        free, every plan, nothing to set up (WhatsApp opens with the text for each person)
//   ENLIXO number        add-on: sent automatically from ENLIXO's WhatsApp number
//   My Business number   GROW: sent automatically from the tutor's own number (Cloud API). Connect with Facebook
//                        (Meta Embedded Signup with "keep using the WhatsApp Business app", when ENLIXO set it up) or
//                        type the Phone number ID + access token + template.
// API: /api/v1/tutor/messages/setup, /api/v1/tutor/whatsapp/* (MessageService). The token never comes back to the page.
import { QuickGuide } from '../components/QuickGuide.js';
import { api, h, fill, icon, field, showErrors, Toast } from './admin-common.js';

QuickGuide.attachAll();
const root = document.getElementById('wa-page');
const actions = document.getElementById('wa-actions');
let S = null;
let showForm = false;
const fieldErrors = (e) => e.details?.fields ?? e.details ?? {};

fill(actions, h('a', { class: 'custom-btn ex-btn-lg px-5 text-decoration-none', href: 'message-center' }, 'Message Center'));
fill(root, h('div', { class: 'component-theme p-5 text-center sub-text' }, 'Loading…'));
load();

async function load() {
  try { S = (await api.get('/tutor/messages/setup')).data.whatsapp; } catch (e) { fill(root, h('div', { class: 'component-theme p-4 text-dark-red' }, e.message)); return; }
  draw();
}

const WAYS = [
  ['phone', 'smartphone', 'From my phone', 'Free · ready now', [
    'Message Center opens WhatsApp with the message already typed for each student or parent.',
    'You press Send in WhatsApp (phone or WhatsApp Web). Good for a few people at a time.',
  ]],
  ['own', 'whatsapp', 'My WhatsApp Business number', 'GROW plan · automatic', [
    'Messages go out by themselves from your own number, to the whole class at once.',
    'Connect once. You can keep using the WhatsApp Business app on your phone.',
    'Meta charges a small fee per message (about LKR 1–25 depending on the type); you pay Meta directly.',
  ]],
  ['enlixo', 'send', 'ENLIXO WhatsApp number', 'Add-on · automatic', [
    'Messages go out by themselves from the ENLIXO number with your name or institute in them.',
    'Nothing to connect. Ask ENLIXO to switch on the WhatsApp add-on.',
  ]],
];

function draw() {
  const way = ([key, ico, title, sub, points]) => {
    const m = S.modes[key];
    const on = S.mode === key;
    const lock = !m.allowed ? (key === 'own' ? 'On the GROW plan' : 'Add-on: ask ENLIXO') : !m.ready ? (key === 'own' ? 'Connect your number below' : 'Not ready yet') : null;
    return h('div', { class: `mc-way${on ? ' is-on' : ''}${lock ? ' is-locked' : ''}` },
      h('div', { class: 'd-flex align-items-center gap-3 mb-2' }, h('span', { class: 'mc-tile-ico' }, icon(ico, { size: 22 })),
        h('div', { class: 'min-w-0' }, h('strong', {}, title), h('small', {}, sub))),
      h('ul', {}, points.map((p) => h('li', {}, p))),
      on ? h('span', { class: 'mc-way-state is-on' }, icon('check', { size: 16 }), 'In use')
        : lock ? h('div', { class: 'd-flex align-items-center gap-3 flex-wrap' }, h('span', { class: 'mc-way-state' }, icon('lock', { size: 16 }), lock), !m.allowed && key === 'own' ? h('a', { class: 'custom-btn table-btn px-3 text-decoration-none', href: 'subscription?tab=plans' }, 'See plans') : null)
          : h('button', { type: 'button', class: 'custom-btn px-4', onclick: () => useMode(key) }, 'Use this'));
  };

  const own = S.own;
  const connected = own
    ? h('div', { class: 'mc-connected' },
      h('span', { class: 'mc-big-ico is-whatsapp' }, icon('whatsapp', { size: 26 })),
      h('div', { class: 'min-w-0 flex-grow-1' }, h('strong', {}, own.number ?? 'Connected'), h('small', {}, [own.name, `Template: ${own.template} (${own.lang})`, own.via === 'embedded' ? 'Connected with Facebook' : 'Connected with the API details'].filter(Boolean).join(' · ')),
        own.last_error ? h('small', { class: 'text-dark-red' }, `Last problem: ${own.last_error}`) : null),
      h('button', { type: 'button', class: 'custom-btn px-4', onclick: () => { showForm = !showForm; draw(); } }, showForm ? 'Close' : 'Change'),
      h('button', { type: 'button', class: 'custom-danger-btn custom-btn px-4', onclick: disconnect }, 'Disconnect'))
    : null;

  const ownCard = S.modes.own.allowed ? h('div', { class: 'component-theme p-4 mt-4', id: 'own' },
    h('div', { class: 'sub-topic mb-1' }, 'Connect my WhatsApp Business number'),
    h('p', { class: 'text-14 sub-text' }, 'Your number sends the messages, so students see your name and can reply to you.'),
    connected,
    !own || showForm ? [
      S.embedded ? h('div', { class: 'mc-fb' },
        h('div', { class: 'min-w-0' }, h('strong', {}, 'Easiest: connect with Facebook'), h('small', {}, 'Log in with Facebook, pick or create your WhatsApp Business account and confirm your number with a code. Your WhatsApp Business app keeps working.')),
        h('button', { type: 'button', class: 'custom-btn px-4', id: 'wa-fb', onclick: fbConnect }, icon('link', { size: 18 }), 'Connect with Facebook')) : null,
      steps(),
      manualForm(),
    ] : null) : null;

  fill(root,
    h('div', { class: 'component-theme p-4' }, h('div', { class: 'sub-topic mb-3' }, 'How should WhatsApp messages be sent?'), h('div', { class: 'mc-ways' }, WAYS.map(way))),
    ownCard,
    h('div', { class: 'component-theme p-4 mt-4' },
      h('div', { class: 'sub-topic mb-1' }, 'Send a test message'),
      h('p', { class: 'text-14 sub-text' }, 'Sends one message to a number you choose with the way that is in use, so you can see how it looks.'),
      testForm()));
}

function steps() {
  return h('details', { class: 'mc-steps', open: !S.embedded },
    h('summary', {}, S.embedded ? 'Or connect with the API details (advanced)' : 'Connect with the API details: step by step'),
    h('ol', {},
      h('li', {}, 'Open ', h('a', { href: 'https://business.facebook.com/', target: '_blank', rel: 'noopener' }, 'Meta Business Suite'), ' and create a WhatsApp Business account with your number (it must not be used in the normal WhatsApp app; the WhatsApp Business app can stay with Facebook connect).'),
      h('li', {}, 'In WhatsApp Manager › Account tools › Message templates, create a template (category Utility, language English) with this text:',
        h('pre', { class: 'mc-template' }, S.template_text), h('button', { type: 'button', class: 'custom-btn table-btn px-3', onclick: () => copy(S.template_text) }, icon('copy', { size: 14 }), 'Copy text'), ' Wait until it is Approved.'),
      h('li', {}, 'In ', h('a', { href: 'https://developers.facebook.com/apps/', target: '_blank', rel: 'noopener' }, 'Meta for Developers'), ' › your app › WhatsApp › API Setup, copy the Phone number ID and the WhatsApp Business Account ID.'),
      h('li', {}, 'In Business Settings › System users, make a system user, give it your WhatsApp account and create a permanent token with whatsapp_business_messaging. Paste it below.')));
}

function manualForm() {
  const phone = h('input', { class: 'form-control custom-input', inputmode: 'numeric', placeholder: 'Enter the Phone number ID, for example 109876543210987', 'data-key': 'phone_number_id', value: S.own?.phone_number_id ?? '' });
  const waba = h('input', { class: 'form-control custom-input', inputmode: 'numeric', placeholder: 'Enter the WhatsApp Business Account ID (optional)', 'data-key': 'business_account_id', value: S.own?.business_account_id ?? '' });
  const token = h('input', { class: 'form-control custom-input', type: 'password', autocomplete: 'off', placeholder: S.own ? 'Leave empty to keep the saved token' : 'Paste the permanent access token', 'data-key': 'token' });
  const tpl = h('input', { class: 'form-control custom-input', placeholder: 'Enter the template name, for example class_notice', 'data-key': 'template', value: S.own?.template ?? 'class_notice' });
  const lang = h('input', { class: 'form-control custom-input', placeholder: 'Enter the template language, for example en', 'data-key': 'lang', value: S.own?.lang ?? 'en' });
  const box = h('div', { class: 'mt-3' });
  const save = h('button', { type: 'button', class: 'custom-btn px-5', id: 'wa-save', onclick: async () => {
    showErrors(box, {});
    save.disabled = true;
    try {
      S = (await api.post('/tutor/whatsapp/connect', { phone_number_id: phone.value, business_account_id: waba.value, token: token.value, template: tpl.value, lang: lang.value })).data.whatsapp;
      showForm = false;
      Toast.success('Your WhatsApp number is connected. Message Center now sends from it.');
      draw();
    } catch (e) { save.disabled = false; showErrors(box, fieldErrors(e)); Toast.error(e.message); }
  } }, 'Check & Connect');
  fill(box,
    h('div', { class: 'row gx-3' },
      h('div', { class: 'col-md-6' }, field('Phone number ID', phone, { required: true, info: 'Not your phone number: the long ID WhatsApp gives it, in Meta for Developers › WhatsApp › API Setup. Numbers only, for example 109876543210987.' })),
      h('div', { class: 'col-md-6' }, field('WhatsApp Business Account ID', waba, { info: 'Shown under the Phone number ID in API Setup. Kept for support; for example 102345678901234.' })),
      h('div', { class: 'col-12' }, field('Access token', token, { required: !S.own, info: 'A permanent System User token with the whatsapp_business_messaging permission. It is stored encrypted and never shown again.' })),
      h('div', { class: 'col-md-8' }, field('Template name', tpl, { required: true, info: 'The approved template with one variable {{1}} for your message, exactly as in WhatsApp Manager, for example class_notice.' })),
      h('div', { class: 'col-md-4' }, field('Template language', lang, { required: true, info: 'The language code of that template, for example en or en_US.' }))),
    h('div', { class: 'd-flex justify-content-end' }, save));
  return box;
}

function testForm() {
  const to = h('input', { class: 'form-control custom-input', inputmode: 'tel', placeholder: 'Enter a WhatsApp number, for example 0771234567', 'data-key': 'to' });
  const box = h('div', {});
  const btn = h('button', { type: 'button', class: 'custom-btn px-4', id: 'wa-test', onclick: async () => {
    showErrors(box, {});
    try {
      const r = (await api.post('/tutor/whatsapp/test', { to: to.value })).data;
      if (r.link) window.open(r.link, '_blank'); else Toast.success('Test message sent. Check WhatsApp on that phone.');
    } catch (e) { showErrors(box, fieldErrors(e)); Toast.error(e.message); }
  } }, icon('send', { size: 16 }), 'Send Test');
  fill(box, h('div', { class: 'row gx-3 align-items-end' }, h('div', { class: 'col-md-8' }, field('WhatsApp number', to, { info: 'Your own number is best. Sri Lankan numbers like 0771234567 work; other countries need the country code, for example +447911123456.' })),
    h('div', { class: 'col-md-4 mb-3' }, btn)));
  return box;
}

async function useMode(mode) {
  try { S = (await api.post('/tutor/whatsapp/mode', { mode })).data.whatsapp; Toast.success('Saved. Message Center uses this now.'); draw(); } catch (e) { Toast.error(Object.values(fieldErrors(e))[0] ?? e.message); }
}

async function disconnect() {
  if (!window.confirm('Disconnect your WhatsApp number? Message Center goes back to sending from your phone.')) return;
  try { S = (await api.post('/tutor/whatsapp/disconnect', {})).data.whatsapp; Toast.success('Disconnected.'); draw(); } catch (e) { Toast.error(e.message); }
}

async function copy(text) {
  try { await navigator.clipboard.writeText(text); Toast.success('Copied.'); } catch { Toast.error('Select the text and copy it.'); }
}

/** Meta Embedded Signup (v3/v4) with WhatsApp Business app onboarding (coexistence). */
function fbConnect() {
  const cfg = S.embedded;
  let info = null;
  const onMsg = (ev) => {
    if (!/facebook\.com$/.test(new URL(ev.origin).hostname)) return;
    try {
      const d = typeof ev.data === 'string' ? JSON.parse(ev.data) : ev.data;
      if (d?.type === 'WA_EMBEDDED_SIGNUP' && /^FINISH/.test(d.event)) info = d.data;
      if (d?.type === 'WA_EMBEDDED_SIGNUP' && d.event === 'CANCEL') Toast.error('Connecting was stopped before the end.');
    } catch { /* other messages */ }
  };
  window.addEventListener('message', onMsg);
  const start = () => window.FB.login((res) => {
    window.removeEventListener('message', onMsg);
    const code = res?.authResponse?.code;
    if (!code || !info?.phone_number_id) { Toast.error('Facebook did not finish the connection. Please try again.'); return; }
    api.post('/tutor/whatsapp/embedded', { code, phone_number_id: info.phone_number_id, business_account_id: info.waba_id })
      .then((r) => { S = r.data.whatsapp; Toast.success('Your WhatsApp number is connected.'); draw(); })
      .catch((e) => Toast.error(Object.values(fieldErrors(e))[0] ?? e.message));
  }, { config_id: cfg.config_id, response_type: 'code', override_default_response_type: true,
    extras: { setup: {}, featureType: 'whatsapp_business_app_onboarding', sessionInfoVersion: '3' } });
  if (window.FB) { start(); return; }
  window.fbAsyncInit = () => { window.FB.init({ appId: cfg.app_id, autoLogAppEvents: true, xfbml: false, version: 'v22.0' }); start(); };
  const s = document.createElement('script');
  s.src = 'https://connect.facebook.net/en_US/sdk.js';
  s.async = true; s.defer = true; s.crossOrigin = 'anonymous';
  s.onerror = () => Toast.error('Facebook could not be reached. Check the internet connection and try again.');
  document.head.append(s);
}
