// Tutor → Create a Class (tutor/create-class.php) and Edit Class (tutor/edit-class.php?class-id=), ENLIXO V3 form style.
// Fields come from the server (GET /tutor/class-form[?class=id]), so the tutor's own fields and hidden fields apply here.
// Saved with POST /tutor/classes (new) or POST /tutor/classes/{id} (edit): class + weekly times + monthly fee in one go.
import { h, fill } from '../core/dom.js';
import { ApiClient, ApiError, FeatureLockedError } from '../core/ApiClient.js';
import { FormBuilder } from '../components/FormBuilder.js';
import { OptionDialog } from '../components/OptionDialog.js';
import { ThumbnailMaker } from '../components/ThumbnailMaker.js';
import { QuickGuide } from '../components/QuickGuide.js';
import { FeatureGate } from '../components/FeatureGate.js';
import { Toast } from '../components/Toast.js';
import { icon } from '../core/icons.js';

const api = new ApiClient();
const root = document.getElementById('class-form-root');
const TYPE_LABEL = { theory: 'Theory', revision: 'Revision', paper: 'Paper', other: '' };
const classId = Number(root.dataset.classId || 0) || null; // set on the Edit Class page
const COVERS = new URL('../../../imgs/classes-covers/', import.meta.url).href;

let gate = null;
let schema = null;
let form = null;

QuickGuide.attachAll();

// Header: gear (opens the form settings page) + Save, like V3's Add New Student
const saveBtn = h('button', { type: 'button', class: 'custom-btn ex-btn-lg px-5 border-0', onclick: () => save() }, classId ? 'Save Changes' : 'Save Class');
const gearBtn = h('a', { href: 'class-form-settings', class: 'ex-gear', 'aria-label': 'Customise this form', title: 'Customise this form' }, icon('settings', { size: 26 }));
document.getElementById('class-actions').append(gearBtn, saveBtn);

fill(root, h('div', { class: 'ex-root' }, h('div', { class: 'placeholder-glow' }, [70, 45, 85, 60].map((w) => h('span', { class: 'placeholder col-12 mb-3', style: { width: `${w}%`, height: '40px' } })))));
load();

async function load() {
  try {
    [gate, schema] = await Promise.all([FeatureGate.load(api).catch(() => null), api.get('/tutor/class-form', classId ? { class: String(classId) } : {}).then((r) => r.data)]);
    draw();
  } catch (e) {
    fill(root, h('div', { class: 'text-center py-5' },
      h('p', { class: 'main-text mb-1' }, 'Couldn\'t load the form'),
      h('p', { class: 'text-14 sub-text' }, e.message),
      h('button', { type: 'button', class: 'custom-btn ex-btn-md px-4 border-0', onclick: load }, 'Try again')));
  }
}

const label = (list, v) => (schema.lookups[list] ?? []).find((o) => String(o.value) === String(v))?.label ?? '';

function suggestedName(v) {
  if (!v.subject) return '';
  const parts = [label('years', v.year), label('exam_types', v.exam_type), label('subjects', v.subject), TYPE_LABEL[v.class_type] ?? ''].filter(Boolean);
  const medium = label('mediums', v.medium);
  return `${parts.join(' ')}${medium ? ` (${medium})` : ''}`;
}

const nameRequired = () => schema.fields.some((f) => f.key === 'name' && f.required);

function details(v) {
  return {
    subject: label('subjects', v.subject), exam: label('exam_types', v.exam_type), year: label('years', v.year),
    medium: label('mediums', v.medium), type: TYPE_LABEL[v.class_type] ?? '',
  };
}

function draw() {
  const previous = form?.values() ?? schema.class?.values ?? {};
  const host = h('div');
  form = new FormBuilder({
    sections: schema.sections,
    fields: schema.fields,
    lookups: schema.lookups,
    values: previous,
    file: form?.coverFile() ?? null,
    imageUrl: form ? form.imageUrl : (schema.class?.cover ? COVERS + encodeURIComponent(schema.class.cover) : null),
    onAddOption: (f) => OptionDialog.open({ api, list: f.options }),
    onGenerateImage: async () => {
      const v = form.values();
      if (!v.subject) {
        form.showErrors({ subject: 'Choose the subject first, so the thumbnail matches your class.' });
        return null;
      }
      const { exam_type, year, subject, medium, class_type, name, description } = v;
      return ThumbnailMaker.open({ api, details: { exam_type, year, subject, medium, class_type, name, description }, labels: details(v), aiReady: !!schema.ai_thumbnails });
    },
    lockBadge: (f) => gate?.badge(f.feature) ?? null,
    onLocked: (f) => gate?.openUpgrade(f.feature),
    onChange: (v, key) => {
      if (key === 'delivery_mode') form.redrawSchedule?.();
      const nameInput = document.getElementById('f-name');
      if (nameInput && !nameRequired()) nameInput.placeholder = suggestedName(v) || 'Leave empty to use the suggested name';
    },
  }).mount(host);
  fill(root, host);
}

async function save() {
  if (!form) return;
  const v = form.values();
  if (!v.name?.trim() && !nameRequired()) v.name = suggestedName(v);
  const body = new FormData();
  body.append('data', JSON.stringify(v));
  if (form.coverFile()) body.append('cover', form.coverFile());
  else if (classId && form.coverRemoved()) { v.remove_cover = true; body.set('data', JSON.stringify(v)); }
  saveBtn.disabled = true;
  saveBtn.textContent = 'Saving…';
  try {
    const { data } = await api.post(classId ? `/tutor/classes/${classId}` : '/tutor/classes', body);
    Toast.success(classId ? `"${data.name}" saved.` : `"${data.name}" created${data.slots ? ` with ${data.slots} weekly ${data.slots === 1 ? 'time' : 'times'}` : ''}.`);
    setTimeout(() => { location.href = `class-details?class-id=${encodeURIComponent(data.id)}`; }, 900);
  } catch (e) {
    if (e instanceof FeatureLockedError) gate?.openUpgrade(e.details);
    else if (e instanceof ApiError && e.details?.fields) { form.showErrors(e.details.fields); Toast.error('Please check the highlighted fields.'); }
    else Toast.error(e.message);
    saveBtn.disabled = false;
    saveBtn.textContent = classId ? 'Save Changes' : 'Save Class';
  }
}
