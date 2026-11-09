// Tutor → Create a Class (tutor/create-class.php). One page: details, weekly times, fee, who can join, cover.
// The fields come from the server (GET /tutor/class-form), so the tutor's own fields and hidden fields apply here.
import { h, fill, fmt } from '../core/dom.js';
import { ApiClient, ApiError, FeatureLockedError } from '../core/ApiClient.js';
import { FormBuilder } from '../components/FormBuilder.js';
import { ActionMenu } from '../components/Popover.js';
import { FeatureGate } from '../components/FeatureGate.js';
import { Modal } from '../components/Modal.js';
import { Toast } from '../components/Toast.js';
import { infoTip } from '../components/InfoTip.js';
import { LoadingState } from '../components/LoadingState.js';
import { EmptyState } from '../components/EmptyState.js';

const api = new ApiClient();
const root = document.getElementById('class-form-root');
const DAY = ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const TYPE_LABEL = { theory: 'Theory', revision: 'Revision', paper: 'Paper', other: '' };
const MODE_LABEL = { physical: 'Physical', online: 'Online', hybrid: 'Physical + Online' };
const ENROL_LABEL = { open: 'Anyone with the link', approval: 'You approve each student', invite: 'Only students you add' };

let gate = null;
let schema = null;
let form = null;

document.getElementById('class-actions').append(ActionMenu.button('Action', [
  { label: 'Customise this form', onClick: () => openCustomiser() },
  { label: 'All classes', href: 'classes' },
]));

fill(root, h('div', { class: 'ex-root ex-card ex-card-pad' }, LoadingState.list(4)));
load();

async function load() {
  try {
    [gate, schema] = await Promise.all([FeatureGate.load(api).catch(() => null), api.get('/tutor/class-form').then((r) => r.data)]);
    draw();
  } catch (e) {
    fill(root, h('div', { class: 'ex-root ex-card' }, EmptyState.error(e.message, load)));
  }
}

const label = (list, v) => (schema.lookups[list] ?? []).find((o) => String(o.value) === String(v))?.label ?? '';

function suggestedName(v) {
  if (!v.subject) return '';
  const parts = [label('years', v.year), label('exam_types', v.exam_type), label('subjects', v.subject), TYPE_LABEL[v.class_type] ?? ''].filter(Boolean);
  if (!parts.length) return '';
  const medium = label('mediums', v.medium);
  return `${parts.join(' ')}${medium ? ` (${medium})` : ''}`;
}

function draw() {
  const previous = form?.values() ?? {};
  const preview = h('aside', { class: 'ex-card ex-class-preview', 'aria-label': 'Preview' });
  const formHost = h('div');
  const saveBtn = h('button', { type: 'submit', class: 'ex-btn ex-btn-primary ex-btn-save' }, 'Save Class');
  const page = h('form', { class: 'ex-root ex-create-class', novalidate: true, onsubmit: (e) => { e.preventDefault(); save(saveBtn); } },
    h('div', { class: 'ex-create-grid' }, formHost, h('div', { class: 'ex-preview-col' }, preview)),
    h('div', { class: 'ex-savebar' },
      h('span', { class: 'ex-muted ex-small' }, h('span', { class: 'ex-req' }, '*'), ' required. Everything else can be added later.'),
      h('a', { class: 'ex-btn', href: 'classes' }, 'Cancel'), saveBtn));

  form = new FormBuilder({
    sections: schema.sections,
    fields: schema.fields,
    lookups: schema.lookups,
    values: previous,
    lockBadge: (f) => gate?.badge(f.feature) ?? null,
    onLocked: (f) => gate?.openUpgrade(f.feature),
    onChange: (v, key) => {
      if (key === 'delivery_mode') form.redrawSchedule?.();
      renderPreview(preview, v);
    },
  }).mount(formHost);
  fill(root, page);
  renderPreview(preview, form.values());
}

function renderPreview(el, v) {
  const name = v.name?.trim() || suggestedName(v) || 'Your class name';
  const cover = form?.coverFile();
  fill(el,
    h('div', { class: 'ex-preview-head' }, h('strong', {}, 'Preview'), infoTip('This is how the class card looks to you and to students.')),
    h('div', { class: 'ex-preview-cover' }, cover ? h('img', { src: URL.createObjectURL(cover), alt: '' }) : h('span', {}, (label('subjects', v.subject) || 'Class').slice(0, 1))),
    h('div', { class: 'ex-preview-body' },
      h('h3', {}, name),
      h('div', { class: 'ex-preview-tags' },
        [label('exam_types', v.exam_type), label('mediums', v.medium), TYPE_LABEL[v.class_type], MODE_LABEL[v.delivery_mode]].filter(Boolean).map((t) => h('span', { class: 'ex-tag' }, t))),
      h('dl', { class: 'ex-preview-list' },
        h('dt', {}, 'Every week'),
        h('dd', {}, (v.schedule ?? []).length ? v.schedule.map((s) => h('div', {}, `${DAY[s.weekday]} ${fmt.time(s.start)} – ${fmt.time(s.end)}${v.delivery_mode === 'hybrid' ? ` · ${s.mode === 'online' ? 'Online' : 'Hall'}` : ''}`)) : h('span', { class: 'ex-muted' }, 'No fixed times yet')),
        v.location && v.delivery_mode !== 'online' ? [h('dt', {}, 'Where'), h('dd', {}, v.location)] : null,
        h('dt', {}, 'Fee'),
        h('dd', {}, v.is_free ? 'Free class' : v.fee ? `${fmt.money(v.fee)} / month${v.due_day ? `, due on the ${ordinal(v.due_day)}` : ''}` : h('span', { class: 'ex-muted' }, 'Set later')),
        v.enrollment_mode ? [h('dt', {}, 'Joining'), h('dd', {}, ENROL_LABEL[v.enrollment_mode])] : null,
        v.capacity ? [h('dt', {}, 'Seats'), h('dd', {}, `Up to ${v.capacity} students`)] : null)));
}

const ordinal = (n) => { n = Number(n); const s = ['th', 'st', 'nd', 'rd'], v = n % 100; return n + (s[(v - 20) % 10] || s[v] || s[0]); };

async function save(btn) {
  const v = form.values();
  if (!v.name?.trim()) v.name = suggestedName(v);
  const body = new FormData();
  body.append('data', JSON.stringify(v));
  if (form.coverFile()) body.append('cover', form.coverFile());
  btn.disabled = true;
  btn.textContent = 'Saving…';
  try {
    const { data } = await api.post('/tutor/classes', body);
    Toast.success(`"${data.name}" created${data.slots ? ` with ${data.slots} weekly ${data.slots === 1 ? 'time' : 'times'}` : ''}.`);
    setTimeout(() => { location.href = `class-details?class-id=${encodeURIComponent(data.id)}`; }, 900);
  } catch (e) {
    if (e instanceof FeatureLockedError) gate?.openUpgrade(e.details);
    else if (e instanceof ApiError && e.details?.fields) { form.showErrors(e.details.fields); Toast.error('Please check the highlighted fields.'); }
    else Toast.error(e.message);
    btn.disabled = false;
    btn.textContent = 'Save Class';
  }
}

// ---------- Customise form: hide optional fields, add your own ----------
function openCustomiser() {
  if (!schema) return;
  const hidden = new Set(schema.settings.hidden ?? []);
  let custom = structuredClone(schema.settings.custom ?? []);
  const sections = Object.fromEntries(schema.sections.map((s) => [s.key, s.title]));
  const customHost = h('div', { class: 'ex-custom-list' });
  const errorsEl = h('div', { class: 'ex-error', role: 'alert', hidden: true });

  const typeNames = { text: 'Text', number: 'Number', date: 'Date', select: 'Dropdown', yesno: 'Yes / No' };
  const drawCustom = () => fill(customHost,
    custom.length ? null : h('p', { class: 'ex-muted ex-small' }, 'No fields yet. Example: "Institute name", "Hall number", "Syllabus unit", "Batch code".'),
    custom.map((c, i) => h('div', { class: 'ex-custom-field' },
      h('div', { class: 'ex-custom-line' },
        h('input', { class: 'ex-input', placeholder: 'Field name, e.g. Institute name', 'aria-label': 'Field name', value: c.label ?? '', maxlength: '60', oninput: (e) => { c.label = e.target.value; } }),
        h('select', { class: 'ex-select', 'aria-label': 'Field type', onchange: (e) => { c.type = e.target.value; drawCustom(); } },
          schema.custom_types.map((t) => h('option', { value: t, selected: t === c.type }, typeNames[t] ?? t))),
        h('label', { class: 'ex-check-label' }, h('input', { type: 'checkbox', class: 'ex-check', checked: !!c.required, onchange: (e) => { c.required = e.target.checked; } }), 'Required'),
        h('button', { type: 'button', class: 'ex-btn ex-btn-ghost ex-btn-sm', 'aria-label': 'Move up', disabled: i === 0, onclick: () => { [custom[i - 1], custom[i]] = [custom[i], custom[i - 1]]; drawCustom(); } }, '↑'),
        h('button', { type: 'button', class: 'ex-btn ex-btn-ghost ex-btn-sm', 'aria-label': 'Remove field', onclick: () => { custom.splice(i, 1); drawCustom(); } }, '✕')),
      h('input', { class: 'ex-input', placeholder: 'Help text for the (i) icon (optional)', 'aria-label': 'Help text', value: c.info ?? '', maxlength: '200', oninput: (e) => { c.info = e.target.value; } }),
      c.type === 'select' ? h('textarea', { class: 'ex-input', rows: '2', placeholder: 'Choices, one per line', 'aria-label': 'Dropdown choices',
        oninput: (e) => { c.options = e.target.value.split('\n'); } }, (c.options ?? []).map((o) => (typeof o === 'string' ? o : o.value)).join('\n')) : null)),
    custom.length < schema.max_custom ? h('button', { type: 'button', class: 'ex-btn ex-btn-sm', onclick: () => { custom.push({ label: '', type: 'text', required: false, info: '' }); drawCustom(); customHost.querySelector('.ex-custom-field:last-of-type input')?.focus(); } }, '+ Add your own field') : null);
  drawCustom();

  const body = h('div', { class: 'ex-root ex-customiser' },
    h('h3', {}, 'Show or hide fields', infoTip('Hidden fields disappear from the form. Exam type, year, subject, medium and how you teach are always needed.')),
    h('div', { class: 'ex-hide-grid' }, schema.hideable.map((f) => h('label', { class: 'ex-switch ex-switch-row' },
      h('input', { type: 'checkbox', role: 'switch', checked: !hidden.has(f.key), onchange: (e) => (e.target.checked ? hidden.delete(f.key) : hidden.add(f.key)) }),
      h('span', { class: 'ex-switch-track', 'aria-hidden': 'true' }),
      h('span', { class: 'ex-switch-text' }, f.label, h('small', { class: 'ex-muted' }, ` · ${sections[f.section]}`))))),
    h('h3', { class: 'mt-3' }, 'Your own fields', infoTip('Add details you always collect for a class. They show under "More details" and are saved with each class.')),
    customHost, errorsEl);

  const saveBtn = h('button', { class: 'ex-btn ex-btn-primary', onclick: async () => {
    saveBtn.disabled = true;
    try {
      const { data } = await api.put('/tutor/class-form', { hidden: [...hidden], custom });
      schema.settings = data;
      modal.close();
      schema = (await api.get('/tutor/class-form')).data;
      draw();
      Toast.success('Form updated.');
    } catch (e) {
      const msgs = Object.values(e.details?.fields ?? {});
      errorsEl.textContent = msgs.length ? msgs.join(' ') : e.message;
      errorsEl.hidden = false;
    } finally { saveBtn.disabled = false; }
  } }, 'Save form');
  const modal = new Modal({ title: 'Customise the class form', body, wide: true, footer: [h('button', { class: 'ex-btn', onclick: () => modal.close() }, 'Cancel'), saveBtn] }).open();
}
