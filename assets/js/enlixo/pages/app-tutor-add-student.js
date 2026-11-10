// Tutor → Students → Add New Student (tutor/add-student.php), ENLIXO V3 form style.
// The fields come from the tutor's student form (GET /tutor/student-form), the same one the application link uses.
// Saved with POST /tutor/students (multipart: data = JSON of the values, photo = the cropped picture).
import { h, fill } from '../core/dom.js';
import { ApiClient, ApiError, FeatureLockedError } from '../core/ApiClient.js';
import { FormBuilder } from '../components/FormBuilder.js';
import { QuickGuide } from '../components/QuickGuide.js';
import { FeatureGate } from '../components/FeatureGate.js';
import { Toast } from '../components/Toast.js';
import { icon } from '../core/icons.js';

const api = new ApiClient();
const root = document.getElementById('student-form-root');
let gate = null;
let schema = null;
let form = null;

QuickGuide.attachAll();

const saveBtn = h('button', { type: 'button', class: 'custom-btn ex-btn-lg px-5 border-0', onclick: () => save() }, 'Save Student');
document.getElementById('student-actions').append(
  h('a', { href: 'student-form-settings', class: 'ex-gear', 'aria-label': 'Customise this form', title: 'Customise this form' }, icon('settings', { size: 26 })),
  saveBtn,
);

fill(root, h('div', { class: 'placeholder-glow' }, [70, 45, 85, 60].map((w) => h('span', { class: 'placeholder col-12 mb-3', style: { width: `${w}%`, height: '40px' } }))));
load();

async function load() {
  try {
    [gate, schema] = await Promise.all([FeatureGate.load(api).catch(() => null), api.get('/tutor/student-form').then((r) => r.data)]);
    if (!schema.lookups.classes.length) {
      fill(root, h('div', { class: 'text-center py-5' },
        h('p', { class: 'main-text mb-1' }, 'Create a class first'),
        h('p', { class: 'text-14 sub-text' }, 'Every student joins one of your classes, and you have no active class yet.'),
        h('a', { href: 'create-class', class: 'custom-btn ex-btn-md px-4 text-decoration-none' }, 'Create a Class')));
      saveBtn.disabled = true;
      return;
    }
    const host = h('div');
    form = new FormBuilder({ sections: schema.sections, fields: schema.fields, lookups: schema.lookups, values: preset() }).mount(host);
    fill(root, host);
  } catch (e) {
    fill(root, h('div', { class: 'text-center py-5' },
      h('p', { class: 'main-text mb-1' }, 'Couldn\'t load the form'),
      h('p', { class: 'text-14 sub-text' }, e.message),
      h('button', { type: 'button', class: 'custom-btn ex-btn-md px-4 border-0', onclick: load }, 'Try again')));
  }
}

/** ?class=ID (from a class page) picks the class; with one class only, it is picked for you. */
function preset() {
  const want = new URLSearchParams(location.search).get('class');
  const classes = schema.lookups.classes;
  const pick = classes.find((c) => c.value === want) ?? (classes.length === 1 ? classes[0] : null);
  return pick ? { class_id: pick.value } : {};
}

async function save() {
  if (!form) return;
  const body = new FormData();
  body.append('data', JSON.stringify(form.values()));
  if (form.coverFile()) body.append('photo', form.coverFile());
  saveBtn.disabled = true;
  saveBtn.textContent = 'Saving…';
  try {
    const { data } = await api.post('/tutor/students', body);
    Toast.success(data.existing ? `This WhatsApp number belongs to ${data.name}, so we added them to the class.` : `${data.name} added.`);
    setTimeout(() => { location.href = 'students'; }, 1200);
  } catch (e) {
    if (e instanceof FeatureLockedError) gate?.openUpgrade(e.details);
    else if (e instanceof ApiError && e.details?.fields) { form.showErrors(e.details.fields); Toast.error('Please check the highlighted fields.'); }
    else Toast.error(e.message);
    saveBtn.disabled = false;
    saveBtn.textContent = 'Save Student';
  }
}
