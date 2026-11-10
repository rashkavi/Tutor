// Tutor → Students → Requested Students → one application (tutor/student-application.php?id=), like V3:
// header with Actions + Approve, a profile card on the left, the answers on the right (progress, stepper,
// one panel per form section), and the history underneath.
// Data: GET /tutor/applications/{id}; moves: POST /tutor/applications/{id}/status (ApplicationActions).
import { h, fill, fmt, debounce } from '../core/dom.js';
import { ApiClient } from '../core/ApiClient.js';
import { icon } from '../core/icons.js';
import { ActionMenu } from '../components/Popover.js';
import { QuickGuide } from '../components/QuickGuide.js';
import { ApplicationActions, MOVE_LABEL, MOVE_ICON, STATUS, statusPill, fullName } from '../components/ApplicationActions.js';

const api = new ApiClient();
const root = document.getElementById('application-root');
const actions = document.getElementById('application-actions');
const id = Number(root.dataset.id || new URLSearchParams(location.search).get('id') || 0);
const PHOTOS = new URL('../../../imgs/students/', import.meta.url).href;
const SECTION_ICON = { class: 'classes', personal: 'id-card', contact: 'communication', guardian: 'students', custom: 'class-form' };

let d = null; // { application, sections, fields, lookups, history, moves }
const open = new Set(['class', 'personal']);
let historyQuery = '';

QuickGuide.attachAll();
fill(root, h('div', { class: 'row g-4 placeholder-glow' },
  h('div', { class: 'col-12 col-xl-4' }, h('span', { class: 'placeholder col-12', style: { height: '520px' } })),
  h('div', { class: 'col-12 col-xl-8' }, h('span', { class: 'placeholder col-12', style: { height: '520px' } }))));
load();

async function load() {
  try {
    d = (await api.get(`/tutor/applications/${id}`)).data;
    draw();
  } catch (e) {
    fill(root, h('div', { class: 'component-theme text-center py-5' },
      h('p', { class: 'main-text mb-1' }, e.status === 404 ? 'This application was not found' : 'Couldn\'t load the application'),
      h('p', { class: 'text-14 sub-text' }, e.message),
      h('a', { class: 'custom-btn ex-btn-md px-4 text-decoration-none', href: 'requested-students' }, 'Back to Requested Students')));
  }
}

const a = () => d.application;
const date = (s) => (s ? s.slice(0, 10) : '');
const stamp = (s) => (s ? s.slice(0, 16) : '');

function valueOf(f) {
  const v = a().data[f.key];
  if (v === undefined || v === null || v === '') return null;
  if (f.type === 'select') {
    const opts = Array.isArray(f.options) ? f.options : d.lookups[f.options] ?? [];
    return opts.find((o) => String(o.value) === String(v))?.label ?? (f.key === 'class_id' ? a().class_name : v);
  }
  return String(v);
}

async function move(to) {
  const done = await ApplicationActions.move(api, { ...a(), id }, to, { classes: d.lookups.classes });
  if (done) load();
}

function draw() {
  const app = a();
  // header buttons
  const others = d.moves.filter((m) => m !== 'approved' && m !== 'pending');
  const main = app.status === 'approved' && app.students_student_id
    ? h('a', { class: 'custom-btn ex-btn-lg px-5 text-decoration-none', href: `edit-student?student-id=${encodeURIComponent(app.students_student_id)}` }, 'Open Student')
    : d.moves.includes('approved') ? h('button', { type: 'button', class: 'custom-btn ex-btn-lg px-5 border-0', onclick: () => move('approved') }, 'Approve')
      : d.moves.includes('pending') ? h('button', { type: 'button', class: 'custom-btn ex-btn-lg px-5 border-0', onclick: () => move('pending') }, 'Reopen') : null;
  fill(actions,
    others.length ? ActionMenu.button('Actions', others.map((m) => ({ label: MOVE_LABEL[m], icon: MOVE_ICON[m], danger: m === 'rejected', onClick: () => move(m) })), { className: 'custom-outline-btn ex-btn-lg px-4 d-inline-flex align-items-center gap-2' }) : null,
    main);

  fill(root,
    h('div', { class: 'row g-4 mb-4' },
      h('div', { class: 'col-12 col-xl-4' }, profile()),
      h('div', { class: 'col-12 col-xl-8' }, answers())),
    history());
}

// ---------- left: profile ----------
function profile() {
  const app = a();
  const name = fullName(app);
  const photo = app.photo
    ? h('img', { src: PHOTOS + encodeURIComponent(app.photo), alt: '', onerror: (e) => e.target.replaceWith(document.createTextNode(fmt.initials(name))) })
    : fmt.initials(name || '?');
  const dotColor = { approved: 'var(--dark-green)', rejected: 'var(--dark-red)', on_hold: 'var(--dark-purple)', in_review: 'var(--dark-theme)', pending: 'var(--dark-orange)' }[app.status];
  const wa = (app.whatsapp ?? '').replace(/\D/g, '');
  const contact = (ico, label, href) => h(href ? 'a' : 'span', { class: `ex-contact-btn${href ? '' : ' opacity-50'}`, href: href ?? null, target: href?.startsWith('http') ? '_blank' : null, rel: 'noopener', 'aria-disabled': href ? null : 'true' }, icon(ico, { size: 20 }), label);
  const line = (ico, k, v) => (v ? h('div', { class: 'ex-info-line' }, h('span', { class: 'ex-info-ico' }, icon(ico, { size: 18 })), h('div', { class: 'min-w-0' }, h('div', { class: 'text-12 sub-text' }, k), h('div', { class: 'text-14 main-text text-break' }, v))) : null);
  const meta = (k, v) => (v ? h('div', { class: 'ex-meta-row' }, h('span', {}, k), h('span', {}, v)) : null);
  const mobile = app.data.mobile || null;
  return h('div', { class: 'ex-profile h-100' },
    h('div', { class: 'ex-profile-banner' }),
    h('div', { class: 'px-4 pb-4 text-center' },
      h('div', { class: 'ex-profile-photo' }, photo, h('span', { class: 'ex-profile-dot', style: { background: dotColor } })),
      h('div', { class: 'ex-profile-name mt-3' }, name),
      h('div', { class: 'text-12 sub-text' }, app.class_name ?? 'No class chosen'),
      h('div', { class: 'd-flex justify-content-center gap-2 mt-3' }, h('span', { class: 'ex-tag-v3 gap-1' }, icon('hashtag', { size: 14 }), `Ref. ${app.ref_no}`), statusPill(app.status)),
      h('div', { class: 'd-flex gap-2 mt-4' },
        contact('phone', 'Call', mobile || app.whatsapp ? `tel:${mobile || app.whatsapp}` : null),
        contact('whatsapp', 'WhatsApp', wa ? `https://wa.me/${wa}` : null),
        contact('mail', 'E-mail', app.email ? `mailto:${app.email}` : null)),
      h('div', { class: 'text-start mt-3' },
        line('phone', 'Mobile', mobile),
        line('whatsapp', 'WhatsApp', app.whatsapp),
        line('mail', 'E-mail', app.email)),
      h('div', { class: 'text-start mt-3 ex-panel-soft px-3 py-2' },
        meta('Applied through', 'Application link'),
        meta('Applied on', stamp(app.created_at)),
        meta('Reviewed by', app.reviewed_by_name),
        meta(app.status === 'rejected' ? 'Rejected by' : 'Approved by', ['approved', 'rejected'].includes(app.status) ? app.decided_by_name : null),
        meta('Last update', stamp(app.updated_at)))));
}

// ---------- right: answers ----------
function answers() {
  const app = a();
  const shown = d.fields;
  const filled = shown.filter((f) => valueOf(f) !== null).length;
  const pct = shown.length ? Math.round((filled / shown.length) * 100) : 0;
  const sections = d.sections.map((s) => ({ s, fields: shown.filter((f) => f.section === s.key) })).filter((x) => x.fields.length);
  const message = app.message ? h('div', { class: 'ex-panel-soft p-3 mt-3' }, h('div', { class: 'text-12 sub-text mb-1' }, 'Message from the student'), h('div', { class: 'text-14 main-text' }, app.message)) : null;
  return h('div', { class: 'component-theme p-4 h-100' },
    h('div', { class: 'd-flex flex-wrap align-items-start gap-3' },
      h('div', { class: 'flex-grow-1' },
        h('div', { class: 'sub-topic' }, 'Application'),
        h('div', { class: 'd-flex align-items-center gap-3 mt-2' },
          h('div', { class: 'ex-progress', role: 'progressbar', 'aria-valuenow': String(pct), 'aria-valuemin': '0', 'aria-valuemax': '100', 'aria-label': 'Fields filled' }, h('span', { style: { width: `${pct}%` } })),
          h('span', { class: 'text-12 sub-text' }, `${filled} of ${shown.length} fields filled · ${pct}%`))),
      h('div', { class: 'd-flex gap-2' },
        h('button', { type: 'button', class: 'custom-outline-btn ex-btn-sm px-3', onclick: () => { sections.forEach((x) => open.add(x.s.key)); draw(); } }, icon('expand', { size: 16 }), 'Expand all'),
        h('button', { type: 'button', class: 'custom-outline-btn ex-btn-sm px-3', onclick: () => { open.clear(); draw(); } }, icon('collapse', { size: 16 }), 'Collapse all'))),
    h('div', { class: 'mt-4' }, stepper()),
    message,
    h('div', { class: 'mt-4' }, sections.map(({ s, fields }) => panel(s, fields))));
}

function stepper() {
  const app = a();
  const st = app.status;
  const reviewed = st !== 'pending';
  const step = (cls, dot, title, sub) => h('div', { class: `ex-step ${cls}` }, h('span', { class: 'ex-step-dot' }, dot), h('span', { class: 'ex-step-title' }, title), h('span', { class: 'ex-step-sub' }, sub));
  const tick = () => icon('check', { size: 18 });
  const by = (name, when) => [name ? `By ${name}` : '', date(when)].filter(Boolean).join(' · ');
  let third;
  if (st === 'approved') third = step('ex-done', tick(), 'Approved', by(app.decided_by_name, app.decided_at));
  else if (st === 'rejected') third = step('ex-bad', icon('close', { size: 18 }), 'Rejected', app.status_note ?? by(app.decided_by_name, app.decided_at));
  else if (st === 'on_hold') third = step('ex-wait', icon('deactivate', { size: 18 }), 'On Hold', app.status_note ?? '');
  else third = step('', '3', 'Approved', 'Waiting for your decision');
  return h('div', { class: 'ex-stepper' },
    step('ex-done', tick(), 'Received', date(app.created_at)),
    reviewed ? step('ex-done', tick(), 'Reviewed', by(app.reviewed_by_name, app.reviewed_at)) : step('', '2', 'Reviewed', 'Not opened yet'),
    third,
    st === 'approved' ? step('ex-done', tick(), 'Student registered', 'Added to the class') : step('', '4', 'Student registered', 'After approval'));
}

function panel(s, fields) {
  const isOpen = open.has(s.key);
  const n = fields.filter((f) => valueOf(f) !== null).length;
  return h('div', { class: `ex-acc${isOpen ? ' ex-open' : ''}` },
    h('button', { type: 'button', class: 'ex-acc-head', 'aria-expanded': String(isOpen), onclick: () => { isOpen ? open.delete(s.key) : open.add(s.key); draw(); } },
      h('span', { class: 'ex-acc-ico' }, icon(SECTION_ICON[s.key] ?? 'view-details', { size: 18 })),
      h('span', {}, s.title),
      h('span', { class: 'ex-acc-count' }, `${n} of ${fields.length} filled`),
      h('span', { class: 'ex-acc-chev' }, icon('chevron-down', { size: 18 }))),
    isOpen ? h('div', { class: 'ex-acc-grid' }, fields.map((f) => {
      const v = valueOf(f);
      return h('div', { class: 'ex-acc-cell' },
        h('div', { class: 'ex-acc-k' }, f.label, f.required ? h('span', { class: 'text-dark-red' }, ' *') : null),
        h('div', { class: `ex-acc-v${v === null ? ' ex-empty-v' : ''}` }, v ?? 'Not provided'));
    })) : null);
}

// ---------- history ----------
function history() {
  const q = historyQuery.trim().toLowerCase();
  const rows = d.history.filter((r) => !q || `${r.user_name ?? 'applicant'} ${r.action} ${r.remark ?? ''} ${r.created_at}`.toLowerCase().includes(q));
  const who = (r) => r.user_name ?? 'Applicant / system';
  const search = h('input', { type: 'search', class: 'form-control custom-input ex-h48 ps-5', placeholder: 'Search the history', value: historyQuery, 'aria-label': 'Search the history',
    oninput: debounce((e) => { historyQuery = e.target.value; const pos = e.target.selectionStart; draw(); const el = document.querySelector('#application-history input[type=search]'); el?.focus(); el?.setSelectionRange(pos, pos); }, 200) });
  return h('div', { class: 'component-theme p-4', id: 'application-history' },
    h('div', { class: 'd-flex flex-wrap align-items-center gap-3 mb-3' },
      h('span', { class: 'ex-count-pill' }, `${d.history.length} ${d.history.length === 1 ? 'Entry' : 'Entries'}`),
      h('div', { class: 'position-relative flex-grow-1' }, h('span', { class: 'position-absolute top-50 translate-middle-y sub-text', style: { left: '16px' } }, icon('search', { size: 18 })), search)),
    h('div', { class: 'table-responsive' },
      h('table', { class: 'table ex-v3-table align-middle m-0 text-14' },
        h('thead', {}, h('tr', {}, h('th', { scope: 'col' }, 'User'), h('th', { scope: 'col' }, 'Date'), h('th', { scope: 'col' }, 'Action'), h('th', { scope: 'col' }, 'Remark'))),
        h('tbody', {}, rows.length ? rows.map((r) => h('tr', {},
          h('td', {}, h('div', { class: 'ex-person' }, h('span', { class: 'ex-avatar' }, fmt.initials(r.user_name ?? 'A P')), h('span', {}, who(r)))),
          h('td', { class: 'text-nowrap' }, stamp(r.created_at)),
          h('td', {}, r.action),
          h('td', {}, r.remark ?? ''))) : h('tr', {}, h('td', { colspan: '4', class: 'text-center text-14 sub-text py-4' }, 'Nothing matches your search.'))))));
}


