// Admin → Content → Classes → Create / Edit a class (admin/class-form.php, ?class-id= edits). A page, not a pop-up:
// tutor, subject, medium, exam year, exam, name (made from the others when empty), active, 16:9 cover.
// API: GET /admin/classes/form, POST /admin/classes (new), POST /admin/classes/{id} (change). Errors show under each field.
import { QuickGuide } from '../components/QuickGuide.js';
import { api, h, fill, icon, field, showErrors, Toast } from './admin-common.js';

QuickGuide.attachAll();
const root = document.getElementById('cf-page');
const actions = document.getElementById('cf-actions');
const classId = Number(new URLSearchParams(location.search).get('class-id') || 0);
const COVERS = new URL('../../../imgs/classes-covers/', import.meta.url).href;
let D = null;
let file = null;

fill(root, h('div', { class: 'component-theme p-5 text-center sub-text' }, 'Loading…'));
load();

async function load() {
  try {
    D = (await api.get('/admin/classes/form', classId ? { class: String(classId) } : {})).data;
  } catch (e) {
    fill(root, h('div', { class: 'component-theme p-4 text-dark-red' }, e.message));
    return;
  }
  draw();
}

const select = (key, list, value, prompt) => h('select', { class: 'form-select custom-select', 'data-key': key },
  h('option', { value: '' }, prompt), list.map((o) => h('option', { value: o.id, selected: o.id === value ? true : null }, o.name)));

function draw() {
  const c = D.class ?? {};
  const tutor = select('tutor', D.tutors.map((t) => ({ id: t.id, name: `${t.name} · ${t.email}` })), c.tutor, '- Select -');
  const subject = select('subject', D.subjects, c.subject, '- Select -');
  const medium = select('medium', D.mediums, c.medium, '- Select -');
  const year = select('year', D.years, c.year, '- Select -');
  const exam = select('exam_type', D.exam_types, c.exam_type, '- Select -');
  const name = h('input', { class: 'form-control custom-input', 'data-key': 'name', 'data-limit': '100', value: c.name ?? '', placeholder: 'Leave empty to use "2027 A/L Physics-Sinhala"' });
  const active = h('input', { class: 'form-check-input', type: 'checkbox', role: 'switch', checked: c.active !== false ? true : null });
  const img = h('img', { class: 'cf-cover-img', alt: 'Class cover', src: c.cover ? COVERS + c.cover : new URL('../../../imgs/common/empty-thumbnail.webp', import.meta.url).href });
  const pick = h('input', { type: 'file', class: 'd-none', accept: 'image/jpeg,image/png,image/webp', 'data-key': 'cover', onchange: (e) => {
    file = e.target.files[0] ?? null;
    if (file) img.src = URL.createObjectURL(file);
  } });
  const save = h('button', { type: 'button', class: 'custom-btn px-5', onclick: () => submit({ tutor, subject, medium, year, exam, name, active }, save) }, classId ? 'Save Changes' : 'Create Class');

  fill(actions, h('a', { class: 'custom-btn px-4 text-decoration-none d-inline-flex align-items-center', href: 'classes' }, 'All Classes'), save.cloneNode(true));
  actions.lastChild.onclick = () => save.click();

  fill(root,
    h('div', { class: 'row g-4' },
      h('div', { class: 'col-12 col-xl-8' },
        h('div', { class: 'component-theme p-4' },
          h('div', { class: 'sub-topic mb-3' }, 'Class details'),
          field('Tutor', tutor, { required: true, info: 'The tutor who teaches the class. It appears in that tutor\'s app and counts against their plan.' }),
          h('div', { class: 'row gx-3' },
            h('div', { class: 'col-md-6' }, field('Subject', subject, { required: true, info: 'What the class teaches, for example Combined Maths.' })),
            h('div', { class: 'col-md-6' }, field('Medium', medium, { required: true, info: 'The language the class is taught in: Sinhala, Tamil or English.' })),
            h('div', { class: 'col-md-6' }, field('Exam Year', year, { required: true, info: 'The year the students sit the exam, for example 2027.' })),
            h('div', { class: 'col-md-6' }, field('Exam', exam, { required: true, info: 'The exam the class prepares for, for example A/L, O/L or Grade 5 Scholarship.' }))),
          field('Class Name', name, { info: 'What students see. Empty: made from the year, exam, subject and medium, for example "2027 A/L Physics-Sinhala". Up to 100 characters.' }),
          h('label', { class: 'form-check form-switch d-flex align-items-center gap-2 mt-2' }, active, h('span', { class: 'text-14 main-text' }, 'Active (open for students)')))),
      h('div', { class: 'col-12 col-xl-4' },
        h('div', { class: 'component-theme p-4' },
          h('div', { class: 'sub-topic mb-3' }, 'Cover'),
          img,
          h('p', { class: 'text-13 sub-text mt-2 mb-3' }, '16:9 picture, up to 2 MB, JPG, PNG or WebP. Optional.'),
          h('button', { type: 'button', class: 'custom-btn w-100 d-inline-flex align-items-center justify-content-center gap-2', onclick: () => pick.click() }, icon('upload', { size: 18 }), 'Choose a Picture'), pick,
          h('div', { class: 'text-12 text-dark-red mt-1 d-none', 'data-err': 'cf-cover' })))),
    h('div', { class: 'd-flex justify-content-end mt-4' }, save));
  pick.id = 'cf-cover';
}

async function submit(f, btn) {
  showErrors(root, {});
  const body = new FormData();
  body.append('data', JSON.stringify({
    tutor: f.tutor.value, subject: f.subject.value, medium: f.medium.value, year: f.year.value, exam_type: f.exam.value, name: f.name.value, active: f.active.checked,
  }));
  if (file) body.append('cover', file);
  btn.disabled = true;
  try {
    const { data } = await api.post(classId ? `/admin/classes/${classId}` : '/admin/classes', body);
    Toast.success(data.message);
    setTimeout(() => { location.href = 'classes'; }, 900);
  } catch (e) {
    btn.disabled = false;
    showErrors(root, e.details?.fields ?? e.details ?? {});
    root.querySelector('.is-invalid')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    Toast.error(e.message);
  }
}
