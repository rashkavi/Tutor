// Public student application (apply.php?t=TOKEN), like the V3 Student Application page:
// title, four "how it works" steps, the tutor's student form in blue-bar sections, then Confirm and Submit.
// GET /public/apply/{token} gives the form; POST /public/apply/{token} (multipart: data = JSON, photo) sends it.
import { h, fill } from '../core/dom.js';
import { ApiClient, ApiError } from '../core/ApiClient.js';
import { icon } from '../core/icons.js';
import { FormBuilder } from '../components/FormBuilder.js';
import { Toast } from '../components/Toast.js';

const api = new ApiClient();
const token = document.getElementById('apply-root').dataset.token;
const body = document.getElementById('apply-body');
let form = null;

fill(body, h('div', { class: 'placeholder-glow' }, [40, 90, 70, 85].map((w) => h('span', { class: 'placeholder col-12 mb-3', style: { width: `${w}%`, height: '48px' } }))));
load();

function message(ico, title, text) {
  fill(body, h('div', { class: 'ex-bar-section p-5 text-center' },
    h('span', { class: 'ex-done-ico mb-3' }, icon(ico, { size: 36 })),
    h('h1', { class: 'ex-apply-title' }, title),
    h('p', { class: 'text-14 sub-text mt-3 mb-0' }, text)));
}

async function load() {
  if (!token) { message('danger', 'This link is not complete', 'Ask your tutor to send the application link again.'); return; }
  let d;
  try {
    d = (await api.get(`/public/apply/${encodeURIComponent(token)}`)).data;
  } catch (e) {
    message('danger', 'This link does not work', e.message);
    return;
  }
  if (!d.open) { message('clock', 'Not taking applications', `${d.tutor.name || 'This tutor'} is not taking new students through this link right now. Please contact them directly.`); return; }
  if (!d.lookups.classes.length) { message('clock', 'No classes open yet', `${d.tutor.name || 'This tutor'} has no classes open for applications right now.`); return; }
  draw(d);
}

function draw(d) {
  const steps = [
    ['Fill in the form', 'Your details and a photo'],
    ['Submit', 'You get a reference number at once'],
    ['Keep your number', 'Use it when you contact the tutor'],
    ['We contact you', `${d.tutor.name || 'The tutor'} checks your application`],
  ];
  const host = h('div');
  const classes = d.lookups.classes;
  const want = new URLSearchParams(location.search).get('class');
  const pick = classes.find((c) => c.value === want) ?? (classes.length === 1 ? classes[0] : null);
  form = new FormBuilder({ sections: d.sections, fields: d.fields, lookups: d.lookups, values: pick ? { class_id: pick.value } : {}, bars: true }).mount(host);

  const msg = h('textarea', { id: 'a-message', class: 'form-control custom-input mt-2', rows: '3', maxlength: '1000', placeholder: 'Anything you would like the tutor to know, for example the best time to call you' });
  const confirm = h('input', { class: 'form-check-input', type: 'checkbox', id: 'a-confirm' });
  const confirmErr = h('span', { class: 'text-12 text-dark-red d-block', role: 'alert' });
  const honeypot = h('input', { type: 'text', name: 'website', tabindex: '-1', autocomplete: 'off', class: 'position-absolute opacity-0', style: { left: '-9999px' }, 'aria-hidden': 'true' });
  const send = h('button', { type: 'button', class: 'custom-btn ex-btn-lg px-5 border-0', onclick: () => submit(send, msg, confirm, confirmErr, honeypot) }, icon('send', { size: 20 }), 'Submit Application');

  fill(body,
    h('div', { class: 'text-center mb-4' },
      h('h1', { class: 'ex-apply-title' }, 'Student Application'),
      h('p', { class: 'text-14 sub-text mt-3 mb-2' }, `Apply online to join ${d.tutor.name ? `${d.tutor.name}'s` : 'the'} classes. It takes about 5 minutes; keep your WhatsApp number handy.`),
      h('span', { class: 'ex-tag-v3' }, icon('user', { size: 14 }), ' DIRECT APPLICATION')),
    h('div', { class: 'ex-bar-section p-3 mb-4' }, h('div', { class: 'ex-apply-steps' }, steps.map(([t, s], i) => h('div', { class: 'ex-apply-step' },
      h('span', { class: 'ex-apply-num' }, String(i + 1)), h('div', {}, h('div', { class: 'text-14 main-text fw-medium' }, t), h('div', { class: 'text-12 sub-text' }, s)))))),
    host,
    h('section', { class: 'ex-bar-section ex-bsform mb-2', 'aria-label': 'Confirm and Submit' },
      h('div', { class: 'ex-bar-head' }, icon('check', { size: 20 }), h('span', {}, 'Confirm and Submit'), h('span', { class: 'ex-bar-sub d-none d-md-inline' }, 'Check your details before you send the application')),
      h('div', { class: 'p-4' },
        h('label', { class: 'text-14 main-text', for: 'a-message' }, 'Message to the tutor'), msg, honeypot,
        h('div', { class: 'form-check mt-3' }, confirm, h('label', { class: 'form-check-label text-14 main-text', for: 'a-confirm' }, 'I confirm that the details I submitted are true and correct.')),
        confirmErr,
        h('div', { class: 'd-flex flex-wrap align-items-center justify-content-between gap-3 mt-4 pt-3', style: { boxShadow: 'inset 0 1px 0 var(--line-color, #E9EAF0)' } },
          h('span', { class: 'text-12 sub-text' }, 'Fields marked with ', h('span', { class: 'text-dark-red' }, '*'), ' are required.'),
          send))));
}

async function submit(btn, msg, confirm, confirmErr, honeypot) {
  confirmErr.textContent = '';
  if (!confirm.checked) { confirmErr.textContent = 'Tick the box to confirm your details are correct.'; confirm.focus(); return; }
  const data = new FormData();
  data.append('data', JSON.stringify({ values: form.values(), message: msg.value.trim(), confirm: confirm.checked, website: honeypot.value }));
  if (form.coverFile()) data.append('photo', form.coverFile());
  btn.disabled = true;
  try {
    const { data: r } = await api.post(`/public/apply/${encodeURIComponent(token)}`, data);
    window.scrollTo({ top: 0, behavior: 'smooth' });
    message('check', r.duplicate ? 'You have already applied' : 'Application sent',
      `${r.duplicate ? 'We already have your application for this class.' : 'Thank you. Your application is with the tutor.'} Your reference number is ${r.ref}. Keep it, and use it if you contact the tutor.`);
  } catch (e) {
    btn.disabled = false;
    if (e instanceof ApiError && e.details?.fields) {
      const { confirm: c, ...rest } = e.details.fields;
      if (c) confirmErr.textContent = c;
      if (Object.keys(rest).length) form.showErrors(rest);
      Toast.error('Please check the highlighted fields.');
    } else {
      Toast.error(e.message);
    }
  }
}
