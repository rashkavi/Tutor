// Tutor → Settings → Class Form (tutor/class-form-settings.php), ENLIXO V3 settings layout:
// stat cards, a section menu on the left, and a fields table with Order / Field / Type / Required / Enabled / Actions.
// Field settings save straight away (PUT /tutor/class-form). Dropdown lists use /tutor/class-form/options.
import { h, fill, debounce } from '../core/dom.js';
import { ApiClient } from '../core/ApiClient.js';
import { fieldInfo } from '../components/FormBuilder.js';
import { QuickGuide } from '../components/QuickGuide.js';
import { BsModal } from '../components/BsModal.js';
import { OptionDialog, OWN_LISTS } from '../components/OptionDialog.js';
import { Toast } from '../components/Toast.js';

const api = new ApiClient();
const root = document.getElementById('form-settings-root');
const actions = document.getElementById('form-settings-actions');

const SECTION_ICON = { basics: 'solar:notebook-linear', running: 'solar:calendar-linear', fee: 'solar:wallet-money-linear', enrolment: 'solar:users-group-rounded-linear', custom: 'solar:widget-add-linear' };
const LIST_ICON = { exam_types: 'solar:diploma-linear', subjects: 'solar:book-2-linear', mediums: 'solar:translation-linear' };
const SECTION_SUB = {
  basics: 'What you teach and for which exam, the class name, description and cover.',
  running: 'How and when the class runs: physical or online, hall, weekly times and Zoom.',
  fee: 'The monthly fee and the day it is due.',
  enrolment: 'Who can join the class and how many students.',
  custom: 'Your own fields that don\'t fit the sections above.',
};
const CUSTOM_TYPE_NAMES = { text: 'Short text', number: 'Number', date: 'Date', select: 'List of choices', yesno: 'Yes / No' };
const ICON = {
  lock: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>',
  grip: '<svg viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="6" r="1.6"/><circle cx="15" cy="6" r="1.6"/><circle cx="9" cy="12" r="1.6"/><circle cx="15" cy="12" r="1.6"/><circle cx="9" cy="18" r="1.6"/><circle cx="15" cy="18" r="1.6"/></svg>',
  plus: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>',
  reset: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 11a8 8 0 1 1 2.3 5.7"/><path d="M4 4v7h7"/></svg>',
  search: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>',
};

let schema = null;   // GET /tutor/class-form?all=1
let options = null;  // GET /tutor/class-form/options
let current = 'basics';
let optionQuery = '';

QuickGuide.attachAll();

// Header actions: save status + open the form
const status = h('span', { class: 'text-12 sub-text', role: 'status', 'aria-live': 'polite' }, '');
actions.append(status, h('a', { href: 'create-class', class: 'custom-outline-btn ex-btn-md px-4 d-inline-flex align-items-center text-decoration-none' }, 'Open the form'));

fill(root, h('div', { class: 'placeholder-glow' },
  h('div', { class: 'row g-3 mb-4' }, [0, 1, 2, 3].map(() => h('div', { class: 'col-12 col-md-6 col-xl-3' }, h('span', { class: 'placeholder col-12', style: { height: '86px' } })))),
  h('span', { class: 'placeholder col-12', style: { height: '420px' } })));
load();

async function load() {
  try {
    const [f, o] = await Promise.all([api.get('/tutor/class-form', { all: '1' }), api.get('/tutor/class-form/options')]);
    schema = f.data;
    options = o.data;
    draw();
  } catch (e) {
    fill(root, h('div', { class: 'component-theme text-center py-5' },
      h('p', { class: 'main-text mb-1' }, 'Couldn\'t load the form settings'),
      h('p', { class: 'text-14 sub-text' }, e.message),
      h('button', { type: 'button', class: 'custom-btn ex-btn-md px-4 border-0', onclick: load }, 'Try again')));
  }
}

const fieldsOf = (section) => schema.fields.filter((f) => f.section === section);
const ownCount = () => Object.keys(OWN_LISTS).reduce((n, l) => n + options[l].filter((o) => o.own).length, 0);

function draw() {
  const scrollY = document.querySelector('.contentBodyArea')?.scrollTop;
  fill(root, stats(), h('div', { class: 'component-theme' }, h('div', { class: 'row g-0' }, nav(), h('div', { class: 'col-12 col-lg-9 col-xl-10 p-4' }, current in OWN_LISTS ? listPanel(current) : sectionPanel(current)))));
  if (scrollY !== undefined) document.querySelector('.contentBodyArea').scrollTop = scrollY;
}

// ---------- Stat cards ----------
function stats() {
  const on = schema.fields.filter((f) => f.enabled);
  const card = (n, title, sub) => h('div', { class: 'col-12 col-md-6 col-xl-3' },
    h('div', { class: 'component-theme px-4 py-3 h-100' },
      h('div', { class: 'ex-stat-num' }, String(n)),
      h('div', { class: 'text-14 main-text' }, title),
      h('div', { class: 'text-12 sub-text' }, sub)));
  return h('div', { class: 'row g-3 mb-4' },
    card(on.length, 'Fields on the form', `${schema.fields.length - on.length} turned off`),
    card(on.filter((f) => f.required).length, 'Required fields', `${schema.fields.filter((f) => f.core).length} are always needed`),
    card(schema.settings.custom.length, 'Your own fields', `up to ${schema.max_custom}`),
    card(ownCount(), 'Your own options', 'exam types, subjects and mediums'));
}

// ---------- Left menu ----------
function nav() {
  const item = (key, label, icon, count) => h('button', { type: 'button', class: `ex-side-item${current === key ? ' active' : ''}`, 'aria-current': current === key ? 'true' : null, onclick: () => { current = key; optionQuery = ''; draw(); } },
    h('iconify-icon', { icon, width: '18', 'aria-hidden': 'true' }), h('span', { class: 'flex-grow-1 text-start' }, label), h('span', { class: 'ex-side-count' }, String(count)));
  return h('nav', { class: 'col-12 col-lg-3 col-xl-2 ex-side-nav p-3', 'aria-label': 'Form settings' },
    h('div', { class: 'ex-side-group' }, 'Form sections'),
    schema.sections.map((s) => item(s.key, s.title, SECTION_ICON[s.key] ?? 'solar:list-linear', fieldsOf(s.key).filter((f) => f.enabled).length)),
    h('div', { class: 'ex-side-group mt-3' }, 'Dropdown lists'),
    Object.entries(OWN_LISTS).map(([key, m]) => item(key, m.title, LIST_ICON[key], options[key].length)));
}

// ---------- Section: fields table ----------
function sectionPanel(key) {
  const section = schema.sections.find((s) => s.key === key);
  const rows = fieldsOf(key);
  const on = rows.filter((f) => f.enabled);
  const tbody = h('tbody', {}, rows.length ? rows.map((f, i) => fieldRow(f, i, rows)) : h('tr', {}, h('td', { colspan: '6', class: 'text-center text-14 sub-text py-5' }, 'No fields here yet. Add your own field.')));
  return h('div', {},
    h('div', { class: 'd-flex align-items-center gap-2' }, h('h2', { class: 'ex-panel-title m-0' }, section.title), fieldInfo(section.info, section.title)),
    h('p', { class: 'text-12 sub-text mt-1 mb-4' }, SECTION_SUB[key] ?? ''),
    h('div', { class: 'bl-solid p-3' },
      h('div', { class: 'd-flex flex-wrap align-items-center gap-3 mb-3' },
        h('div', { class: 'flex-grow-1' },
          h('div', { class: 'd-flex align-items-center gap-2' }, h('span', { class: 'text-14 main-text fw-medium' }, 'Fields'), fieldInfo('Turn fields on or off, make them required, rename them or change their order. Locked fields are always on the form.', 'Fields')),
          h('span', { class: 'text-12 sub-text' }, `${on.length} of ${rows.length} fields are on the form, ${on.filter((f) => f.required).length} required.`)),
        schema.settings.custom.length < schema.max_custom
          ? BsModal.button('Add your own field', { icon: ICON.plus, onclick: () => editCustom(null, key) }) : null,
        BsModal.button('Reset to Default', { icon: ICON.reset, onclick: () => resetSection(key) })),
      h('div', { class: 'table-responsive' },
        h('table', { class: 'table ex-v3-table align-middle m-0' },
          h('thead', {}, h('tr', {},
            h('th', { scope: 'col', class: 'ex-col-order' }, 'Order'), h('th', { scope: 'col' }, 'Field'), h('th', { scope: 'col' }, 'Type'),
            h('th', { scope: 'col', class: 'text-center' }, 'Required'), h('th', { scope: 'col', class: 'text-center' }, 'Enabled'), h('th', { scope: 'col', class: 'text-center' }, 'Actions'))),
          tbody))));
}

function toggle(label, checked, disabled, onchange) {
  return h('div', { class: 'form-check form-switch d-flex justify-content-center m-0 p-0' },
    h('input', { class: 'form-check-input m-0', type: 'checkbox', role: 'switch', 'aria-label': label, checked, disabled, onchange }));
}

function fieldRow(f, i, rows) {
  const move = (to) => {
    if (to < 0 || to >= rows.length) return;
    const keys = rows.map((r) => r.key);
    keys.splice(to, 0, keys.splice(i, 1)[0]);
    reorder(f.section, keys);
    requestAnimationFrame(() => document.querySelector(`[data-grip="${f.key}"]`)?.focus());
  };
  const grip = h('button', { type: 'button', class: 'ex-grip', 'data-grip': f.key, 'aria-label': `Move ${f.label}. Use the arrow keys.`, html: ICON.grip,
    onkeydown: (e) => { if (e.key === 'ArrowUp') { e.preventDefault(); move(i - 1); } if (e.key === 'ArrowDown') { e.preventDefault(); move(i + 1); } } });
  const tr = h('tr', { class: f.enabled ? '' : 'ex-row-off', draggable: 'true', 'data-key': f.key },
    h('td', { class: 'ex-col-order' }, grip),
    h('td', {},
      h('div', { class: 'd-flex align-items-center gap-2' },
        h('span', { class: 'text-14 main-text' }, f.label),
        f.core ? h('span', { class: 'ex-lock', title: 'Always on the form', 'aria-label': 'Always on the form', html: ICON.lock }) : null,
        f.custom ? h('span', { class: 'ex-tag-v3' }, 'Your field') : null),
      f.label !== (f.default_label ?? f.label) ? h('div', { class: 'text-12 sub-text' }, `Default name: ${f.default_label}`) : null,
      f.info ? h('div', { class: 'text-12 sub-text ex-clamp-2' }, f.info) : null),
    h('td', { class: 'text-14 main-text' }, f.typeName),
    h('td', {}, toggle(`${f.label} required`, !!f.required, f.core || !f.canRequire || !f.enabled, (e) => setRequired(f, e.target.checked))),
    h('td', {}, toggle(`${f.label} on the form`, !!f.enabled, !f.canHide, (e) => setEnabled(f, e.target.checked))),
    h('td', { class: 'text-center' }, h('button', { type: 'button', class: 'custom-btn ex-btn-sm px-4 border-0', disabled: !f.enabled || null, onclick: () => (f.custom ? editCustom(f) : editBuiltIn(f)) }, 'Edit')));

  // Drag to reorder (whole row; the grip shows where to hold)
  tr.addEventListener('dragstart', (e) => { e.dataTransfer.setData('text/plain', f.key); e.dataTransfer.effectAllowed = 'move'; tr.classList.add('ex-dragging'); });
  tr.addEventListener('dragend', () => tr.classList.remove('ex-dragging'));
  tr.addEventListener('dragover', (e) => { e.preventDefault(); tr.classList.add('ex-drop'); });
  tr.addEventListener('dragleave', () => tr.classList.remove('ex-drop'));
  tr.addEventListener('drop', (e) => {
    e.preventDefault();
    tr.classList.remove('ex-drop');
    const from = rows.findIndex((r) => r.key === e.dataTransfer.getData('text/plain'));
    if (from < 0 || from === i) return;
    const keys = rows.map((r) => r.key);
    keys.splice(i, 0, keys.splice(from, 1)[0]);
    reorder(f.section, keys);
  });
  return tr;
}

// ---------- Saving ----------
let saving = Promise.resolve();
const settingsBody = () => {
  const s = schema.settings;
  return { hidden: s.hidden, required: s.required, labels: s.labels, order: s.order, custom: s.custom };
};

/** Save the current settings; with reload, fetch the fields again (names, own fields). */
function save({ reload = false } = {}) {
  status.textContent = 'Saving…';
  saving = saving.then(async () => {
    try {
      const { data } = await api.put('/tutor/class-form', settingsBody());
      schema.settings = { ...schema.settings, ...data };
      if (reload) schema = (await api.get('/tutor/class-form', { all: '1' })).data;
      status.textContent = 'All changes saved';
      draw();
      return true;
    } catch (e) {
      status.textContent = '';
      Toast.error(e.message);
      schema = (await api.get('/tutor/class-form', { all: '1' })).data;
      draw();
      throw e;
    }
  });
  return saving;
}
const saveSoon = debounce(() => save().catch(() => {}), 350);

const toggleIn = (list, key, on) => (on ? [...new Set([...list, key])] : list.filter((k) => k !== key));

function setEnabled(f, on) {
  schema.settings.hidden = toggleIn(schema.settings.hidden, f.key, !on);
  f.enabled = on;
  draw();
  saveSoon();
}

function setRequired(f, on) {
  if (f.custom) schema.settings.custom = schema.settings.custom.map((c) => (c.key === f.key ? { ...c, required: on } : c));
  else schema.settings.required = toggleIn(schema.settings.required, f.key, on);
  f.required = on;
  draw();
  saveSoon();
}

function reorder(section, keys) {
  schema.settings.order = { ...schema.settings.order, [section]: keys };
  // only this section's rows move; the other sections stay where they are
  const byKey = new Map(schema.fields.map((f) => [f.key, f]));
  const inSection = keys.map((k) => byKey.get(k));
  let n = 0;
  schema.fields = schema.fields.map((f) => (f.section === section ? inSection[n++] : f));
  draw();
  saveSoon();
}

async function resetSection(key) {
  const title = schema.sections.find((s) => s.key === key)?.title ?? '';
  const ok = await BsModal.confirm({ title: 'Reset to default?', text: `Every built-in field in "${title}" goes back to its default name, help text, order and on/off setting. Your own fields stay.`, yes: 'Reset' });
  if (!ok) return;
  const builtIn = new Set(fieldsOf(key).filter((f) => !f.custom).map((f) => f.key));
  const s = schema.settings;
  s.hidden = s.hidden.filter((k) => !builtIn.has(k));
  s.required = s.required.filter((k) => !builtIn.has(k));
  s.labels = Object.fromEntries(Object.entries(s.labels).filter(([k]) => !builtIn.has(k)));
  const { [key]: _drop, ...order } = s.order;
  s.order = order;
  await save({ reload: true }).then(() => Toast.success('Section reset.')).catch(() => {});
}

// ---------- Edit a built-in field ----------
function input(id, label, value, { max, placeholder = '', textarea = false, required = false, help = '' } = {}) {
  const el = textarea
    ? h('textarea', { id, class: 'form-control custom-input mt-2', rows: '3', maxlength: max ? String(max) : null, placeholder }, value ?? '')
    : h('input', { id, class: 'form-control custom-input ex-h48 mt-2', maxlength: max ? String(max) : null, placeholder, value: value ?? '' });
  const col = h('div', { class: 'col-12 d-flex flex-column' },
    h('label', { class: `text-14 main-text ${required ? 'required' : ''}`, for: id }, label), el,
    help ? h('span', { class: 'text-12 sub-text mt-1' }, help) : null,
    h('span', { class: 'text-12 text-dark-red', role: 'alert' }));
  return { col, el, err: col.lastChild };
}

function editBuiltIn(f) {
  const name = input('e-label', 'Field name', f.label, { max: 60, required: true, help: `Default: ${f.default_label}` });
  const info = input('e-info', '(i) What it does', f.info, { max: 300, textarea: true, help: 'Leave both (i) boxes empty on easy fields, so the icon only shows where it helps.' });
  const enter = input('e-enter', '(i) What to enter', f.enter, { max: 300, textarea: true });
  const hasPh = ['text', 'textarea', 'number'].includes(f.type);
  const ph = hasPh ? input('e-ph', 'Placeholder (grey example text)', f.placeholder ?? '', { max: 80 }) : null;
  const saveBtn = BsModal.button('Save', { primary: true, onclick: async () => {
    const label = name.el.value.trim();
    if (!label) { name.err.textContent = 'Give the field a name.'; name.el.classList.add('is-invalid'); return; }
    schema.settings.labels = { ...schema.settings.labels, [f.key]: { label, info: info.el.value.trim(), enter: enter.el.value.trim(), ...(hasPh ? { placeholder: ph.el.value.trim() } : {}) } };
    saveBtn.disabled = true;
    try { await save({ reload: true }); m.close(); Toast.success('Field updated.'); } catch { saveBtn.disabled = false; }
  } });
  const m = BsModal.open({
    title: `Edit ${f.default_label}`,
    subtitle: `${f.typeName}${f.core ? ' · always on the form' : ''}`,
    body: h('div', { class: 'row gy-3' }, name.col, info.col, enter.col, ph?.col),
    footerStart: [BsModal.button('Use default', { onclick: () => { name.el.value = f.default_label; info.el.value = ''; enter.el.value = ''; if (ph) ph.el.value = ''; } })],
    footerEnd: [BsModal.button('Cancel', { onclick: () => m.close() }), saveBtn],
  });
}

// ---------- Add / edit an own field ----------
function editCustom(f, section = 'custom') {
  const c = f ? structuredClone(schema.settings.custom.find((x) => x.key === f.key)) : { label: '', type: 'text', required: false, info: '', section, options: [] };
  const name = input('c-label', 'Field name', c.label, { max: 60, required: true, placeholder: 'e.g. Institute name' });
  const type = h('select', { id: 'c-type', class: 'form-select custom-select mt-2', onchange: (e) => { c.type = e.target.value; choices.col.classList.toggle('d-none', c.type !== 'select'); ph.col.classList.toggle('d-none', !['text', 'number'].includes(c.type)); } },
    Object.entries(CUSTOM_TYPE_NAMES).map(([v, n]) => h('option', { value: v, selected: v === c.type }, n)));
  const where = h('select', { id: 'c-section', class: 'form-select custom-select mt-2' }, schema.sections.map((s) => h('option', { value: s.key, selected: s.key === (c.section ?? 'custom') }, s.title)));
  const req = h('input', { class: 'form-check-input', type: 'checkbox', role: 'switch', id: 'c-req', checked: !!c.required });
  const info = input('c-info', '(i) What it does', c.info, { max: 300, textarea: true, placeholder: 'Optional. Only fill this in if the field needs explaining.' });
  const enter = input('c-enter', '(i) What to enter', c.enter ?? '', { max: 300, textarea: true, placeholder: 'Optional, e.g. "Your batch code, like PHY27-S"' });
  const ph = input('c-ph', 'Placeholder (grey example text)', c.placeholder ?? '', { max: 80, placeholder: 'Optional' });
  const choices = input('c-opts', 'Choices (one per line)', (c.options ?? []).map((o) => (typeof o === 'string' ? o : o.value)).join('\n'), { textarea: true, required: true, placeholder: 'Unit 1\nUnit 2' });
  if (c.type !== 'select') choices.col.classList.add('d-none');
  if (!['text', 'number'].includes(c.type)) ph.col.classList.add('d-none');
  const err = h('span', { class: 'text-12 text-dark-red', role: 'alert' });

  const saveBtn = BsModal.button(f ? 'Save' : 'Add field', { primary: true, onclick: async () => {
    const next = { ...c, label: name.el.value.trim(), type: type.value, section: where.value, required: req.checked, info: info.el.value.trim(), enter: enter.el.value.trim(), placeholder: ph.el.value.trim(), options: choices.el.value.split('\n') };
    if (!next.label) { name.err.textContent = 'Give the field a name.'; name.el.classList.add('is-invalid'); return; }
    const before = schema.settings.custom;
    schema.settings.custom = f ? before.map((x) => (x.key === f.key ? next : x)) : [...before, next];
    saveBtn.disabled = true;
    err.textContent = '';
    try {
      await save({ reload: true });
      m.close();
      Toast.success(f ? 'Field updated.' : 'Field added.');
      if (next.section !== current && !(current in OWN_LISTS)) { current = next.section; draw(); }
    } catch (e) {
      schema.settings.custom = before;
      err.textContent = Object.values(e.details?.fields ?? {}).join(' ') || e.message;
      saveBtn.disabled = false;
    }
  } });
  const remove = f ? BsModal.button('Delete field', { danger: true, onclick: async () => {
    m.close();
    const ok = await BsModal.confirm({ title: 'Delete this field?', text: `"${f.label}" is removed from the form. Values already saved on classes are kept but no longer shown.`, yes: 'Delete', danger: true });
    if (!ok) return;
    schema.settings.custom = schema.settings.custom.filter((x) => x.key !== f.key);
    schema.settings.hidden = schema.settings.hidden.filter((k) => k !== f.key);
    save({ reload: true }).then(() => Toast.success('Field deleted.')).catch(() => {});
  } }) : null;

  const m = BsModal.open({
    title: f ? `Edit ${f.label}` : 'Add your own field',
    subtitle: 'It shows on the Create a Class form and is saved with each class.',
    size: 'lg',
    body: h('div', { class: 'row gy-3' },
      name.col,
      h('div', { class: 'col-12 col-md-6 d-flex flex-column' }, h('label', { class: 'text-14 main-text', for: 'c-type' }, 'Type'), type),
      h('div', { class: 'col-12 col-md-6 d-flex flex-column' }, h('label', { class: 'text-14 main-text', for: 'c-section' }, 'Show it under'), where),
      h('div', { class: 'col-12' }, h('div', { class: 'form-check form-switch ex-switch-bs' }, req, h('label', { class: 'form-check-label text-14 main-text', for: 'c-req' }, 'Required'))),
      choices.col, ph.col, info.col, enter.col, h('div', { class: 'col-12' }, err)),
    footerStart: remove ? [remove] : [],
    footerEnd: [BsModal.button('Cancel', { onclick: () => m.close() }), saveBtn],
  });
  m.el.addEventListener('shown.bs.modal', () => name.el.focus(), { once: true });
}

// ---------- Dropdown lists ----------
function listPanel(list) {
  const meta = OWN_LISTS[list];
  const all = options[list];
  const mine = all.filter((o) => o.own);
  const q = optionQuery.trim().toLowerCase();
  const shown = q ? all.filter((o) => o.label.toLowerCase().includes(q)) : all;
  const body = h('tbody', {}, shown.length ? shown.map((o) => optionRow(list, o)) : h('tr', {}, h('td', { colspan: '4', class: 'text-center text-14 sub-text py-5' }, q ? 'Nothing matches your search.' : 'Nothing here yet.')));
  const search = h('input', { type: 'search', class: 'form-control custom-input ex-h40 ps-5', placeholder: `Search ${meta.title.toLowerCase()}`, value: optionQuery, 'aria-label': `Search ${meta.title.toLowerCase()}`,
    oninput: debounce((e) => { optionQuery = e.target.value; const pos = e.target.selectionStart; draw(); const el = root.querySelector('input[type=search]'); el?.focus(); el?.setSelectionRange(pos, pos); }, 200) });
  return h('div', {},
    h('div', { class: 'd-flex align-items-center gap-2' }, h('h2', { class: 'ex-panel-title m-0' }, meta.title), fieldInfo(`The ${meta.title.toLowerCase()} you can pick on the Create a Class form.`, meta.title)),
    h('p', { class: 'text-12 sub-text mt-1 mb-4' }, 'The defaults are the same for every tutor and can\'t be changed. Add your own: only you see them.'),
    h('div', { class: 'bl-solid p-3' },
      h('div', { class: 'd-flex flex-wrap align-items-center gap-3 mb-3' },
        h('div', { class: 'flex-grow-1' },
          h('div', { class: 'text-14 main-text fw-medium' }, `${all.length} ${meta.title.toLowerCase()}`),
          h('span', { class: 'text-12 sub-text' }, `${all.length - mine.length} default, ${mine.length} yours (up to ${options.max_own}).`)),
        h('div', { class: 'position-relative ex-search-v3' }, h('span', { class: 'ex-search-ico', html: ICON.search, 'aria-hidden': 'true' }), search),
        mine.length < options.max_own ? BsModal.button(`Add ${meta.one}`, { icon: ICON.plus, onclick: () => addOption(list) }) : null),
      h('div', { class: 'table-responsive' },
        h('table', { class: 'table ex-v3-table align-middle m-0' },
          h('thead', {}, h('tr', {}, h('th', { scope: 'col' }, meta.title.slice(0, -1)), h('th', { scope: 'col' }, 'Who sees it'), h('th', { scope: 'col' }, 'Your classes using it'), h('th', { scope: 'col', class: 'text-center' }, 'Actions'))),
          body))));
}

function optionRow(list, o) {
  return h('tr', {},
    h('td', {}, h('div', { class: 'd-flex align-items-center gap-2' }, h('span', { class: 'text-14 main-text' }, o.label),
      o.own ? null : h('span', { class: 'ex-lock', title: 'Default option', 'aria-label': 'Default option', html: ICON.lock }))),
    h('td', {}, o.own ? h('span', { class: 'ex-tag-v3' }, 'Only you') : h('span', { class: 'text-14 sub-text' }, 'Every tutor (default)')),
    h('td', { class: 'text-14 main-text' }, o.used ? `${o.used} ${o.used === 1 ? 'class' : 'classes'}` : '-'),
    h('td', { class: 'text-center' }, o.own
      ? h('div', { class: 'd-inline-flex gap-2' },
        h('button', { type: 'button', class: 'custom-btn ex-btn-sm px-4 border-0', onclick: () => renameOption(list, o) }, 'Edit'),
        h('button', { type: 'button', class: 'custom-outline-btn ex-btn-sm px-3 ex-danger-outline', disabled: o.used > 0 || null, title: o.used ? 'Used by your classes, so it can\'t be deleted' : null, onclick: () => deleteOption(list, o) }, 'Delete'))
      : h('span', { class: 'text-12 sub-text' }, 'Locked')));
}

async function addOption(list) {
  const added = await OptionDialog.open({ api, list });
  if (!added) return;
  options[list] = [...options[list], added];
  optionQuery = '';
  draw();
  Toast.success(`"${added.label}" added. Only you see it.`);
}

async function renameOption(list, o) {
  const renamed = await OptionDialog.open({ api, list, option: o });
  if (!renamed) return;
  options[list] = options[list].map((x) => (x.value === o.value ? { ...x, label: renamed.label } : x));
  draw();
  Toast.success('Renamed.');
}

async function deleteOption(list, o) {
  const ok = await BsModal.confirm({ title: `Delete "${o.label}"?`, text: 'It is removed from your list. No class uses it.', yes: 'Delete', danger: true });
  if (!ok) return;
  try {
    await api.delete(`/tutor/class-form/options/${list}/${encodeURIComponent(o.value)}`);
    options[list] = options[list].filter((x) => x.value !== o.value);
    draw();
    Toast.success('Deleted.');
  } catch (e) { Toast.error(e.message); }
}
