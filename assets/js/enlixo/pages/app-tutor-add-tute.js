// Tutor → Class → Add Tute (tutor/add-tute.php?class-id=).
// One form: what the tute is, how students get it (download / posted to their home), who gets it (everyone, or only
// students whose fee for a month is paid), then the files or the parcel details. Saved with POST /tutor/tutes.
// A posted tute makes one delivery per student with their address; the tutor follows them on couriered-tute.
import { h, fill } from '../core/dom.js';
import { ApiClient } from '../core/ApiClient.js';
import { QuickGuide } from '../components/QuickGuide.js';
import { Toast } from '../components/Toast.js';
import { infoTip } from '../components/InfoTip.js';
import { icon } from '../core/icons.js';

const api = new ApiClient();
const root = document.getElementById('tute-form-root');
const classId = Number(root.dataset.classId || 0);
const MAX_BYTES = 10 * 1024 * 1024;
const OK_TYPES = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

let meta = null;
const state = { type: 'download', who: 'all', month: '', files: [], items: [''], courier: '', courierOther: '', dispatch: '', note: '' };

QuickGuide.attachAll();
const saveBtn = h('button', { type: 'button', class: 'custom-btn ex-btn-lg px-5 border-0', onclick: () => save() }, 'Save Tute');
document.getElementById('tute-actions').append(saveBtn);

fill(root, h('div', { 'aria-busy': 'true' }, [55, 80, 40, 70].map((w) => h('span', { class: 'ex-skeleton d-block mb-3', style: { width: `${w}%`, height: '44px' } }))));
load();

async function load() {
  try {
    meta = (await api.get('/tutor/tutes/form', { class: String(classId) })).data;
    state.month = meta.this_month;
    draw();
  } catch (e) {
    fill(root, h('div', { class: 'text-center py-5' },
      h('p', { class: 'main-text mb-1' }, 'Couldn\'t load the form'),
      h('p', { class: 'text-14 sub-text' }, e.message),
      h('button', { type: 'button', class: 'custom-btn ex-btn-md px-4 border-0', onclick: load }, 'Try again')));
  }
}

// ---------- small pieces ----------
const err = (key) => h('div', { class: 'text-12 text-dark-red mt-1', 'data-err': key });
const label = (text, { required = false, info = null, forId = null } = {}) => h('label', { class: `text-14 main-text mb-2 d-inline-flex align-items-center gap-2${required ? ' required' : ''}`, for: forId }, text, info ? infoTip(info, text) : null);
const section = (n, title, sub, ...body) => h('section', { class: 'ex-tf-section' },
  h('div', { class: 'ex-tf-head' }, h('span', { class: 'ex-tf-step' }, String(n)), h('div', {}, h('div', { class: 'sub-topic m-0' }, title), sub ? h('div', { class: 'text-12 sub-text' }, sub) : null)),
  ...body);
const choice = (name, value, current, iconName, title, text, onPick) => h('label', { class: `ex-tf-choice${current === value ? ' active' : ''}` },
  h('input', { type: 'radio', name, value, class: 'visually-hidden', checked: current === value ? true : null, onchange: () => onPick(value) }),
  h('span', { class: 'ex-tf-choice-ico' }, icon(iconName, { size: 22 })),
  h('span', { class: 'min-w-0' }, h('span', { class: 'ex-tf-choice-title' }, title), h('span', { class: 'ex-tf-choice-text' }, text)),
  h('span', { class: 'ex-tf-choice-tick' }, icon('check', { size: 18 })));
const plural = (n, one, many = `${one}s`) => `${n} ${n === 1 ? one : many}`;

function audienceCount() {
  if (meta.class.free || state.who === 'all') return meta.students;
  return meta.months.find((m) => m.value === state.month)?.paid ?? 0;
}

// ---------- the form ----------
function draw() {
  const titleIn = h('input', { id: 'tf-title', class: 'form-control custom-input', maxlength: '100', placeholder: 'e.g. Unit 5: Grammar tute', value: state.title ?? '', oninput: (e) => { state.title = e.target.value; } });
  const descIn = h('textarea', { id: 'tf-desc', class: 'form-control custom-input', rows: '3', maxlength: '200', placeholder: 'What is in it, in one or two lines (students see this)', oninput: (e) => { state.description = e.target.value; } }, state.description ?? '');

  const monthSel = h('select', { id: 'tf-month', class: 'form-select custom-select', onchange: (e) => { state.month = e.target.value; draw(); } },
    meta.months.map((m) => h('option', { value: m.value, selected: m.value === state.month ? true : null }, `${m.label} (${plural(m.paid, 'student')} paid)`)));

  const who = meta.class.free
    ? h('div', { class: 'ex-tf-note' }, icon('info', { size: 18 }), h('span', {}, 'This is a free class, so every student in it gets the tute.'))
    : h('div', {},
      h('div', { class: 'row g-3' },
        h('div', { class: 'col-12 col-lg-6' }, choice('who', 'all', state.who, 'students', 'Every student in this class', `${plural(meta.students, 'active student')} get it now. Students who join later get it too.`, (v) => { state.who = v; draw(); })),
        h('div', { class: 'col-12 col-lg-6' }, choice('who', 'paid', state.who, 'payments', 'Only students who paid for a month', 'A student gets it once the fee for that month is paid (approved slip, cash or card).', (v) => { state.who = v; draw(); }))),
      state.who === 'paid' ? h('div', { class: 'row gx-4 gy-3 mt-1' },
        h('div', { class: 'col-12 col-md-6 col-lg-4' }, label('Fee month', { required: true, forId: 'tf-month', info: 'The month whose class fee must be paid. Usually the month the tute is for.' }), monthSel, err('payment_month'))) : null);

  const body = state.type === 'download' ? filesPart() : courierPart();
  const count = audienceCount();

  fill(root,
    section(1, 'Tute details', 'Students see the name and description.',
      h('div', { class: 'row gx-4 gy-3' },
        h('div', { class: 'col-12 col-lg-6' }, label('Tute name', { required: true, forId: 'tf-title' }), titleIn, err('title')),
        h('div', { class: 'col-12 col-lg-6' }, label('Description', { forId: 'tf-desc' }), descIn, err('description')))),
    section(2, 'How students get it', null,
      h('div', { class: 'row g-3' },
        h('div', { class: 'col-12 col-lg-6' }, choice('type', 'download', state.type, 'tutes', 'Download (PDF or Word)', 'Students download it from their class page.', (v) => { state.type = v; draw(); })),
        h('div', { class: 'col-12 col-lg-6' }, choice('type', 'courier', state.type, 'location', 'Post to their home (courier)', 'A parcel is made for each student, with their address. You mark each one as packed, sent and delivered.', (v) => { state.type = v; draw(); }))),
      err('type')),
    section(3, 'Who gets it', null, who),
    section(4, state.type === 'download' ? 'Files' : 'Parcel and delivery', null, body),
    h('div', { class: 'ex-tf-summary' },
      icon(count ? 'check' : 'info', { size: 20 }),
      h('span', {}, count
        ? [h('b', {}, plural(count, 'student')), state.type === 'courier' ? ` will get a parcel${state.who === 'paid' && !meta.class.free ? ' now; students who pay later are added from the deliveries page' : ''}.` : ' can download it.']
        : 'No student gets it yet. It is saved, and students get it as soon as they pay.')),
    h('div', { class: 'd-flex justify-content-end mt-4' }, h('button', { type: 'button', class: 'custom-btn ex-btn-lg px-5 border-0', onclick: () => save() }, 'Save Tute')));
}

function filesPart() {
  const input = h('input', { type: 'file', class: 'd-none', multiple: true, accept: '.pdf,.doc,.docx', onchange: (e) => { addFiles(e.target.files); e.target.value = ''; } });
  const drop = h('label', { class: 'ex-tf-drop', tabindex: '0', onkeydown: (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } } },
    icon('upload', { size: 30 }),
    h('span', { class: 'text-14 main-text' }, 'Drop files here or ', h('span', { class: 'dark-theme fw-semibold' }, 'choose files')),
    h('span', { class: 'text-12 sub-text' }, 'PDF or Word, up to 10 MB each, up to 20 files'), input);
  drop.addEventListener('dragover', (e) => { e.preventDefault(); drop.classList.add('over'); });
  drop.addEventListener('dragleave', () => drop.classList.remove('over'));
  drop.addEventListener('drop', (e) => { e.preventDefault(); drop.classList.remove('over'); addFiles(e.dataTransfer.files); });
  return h('div', {}, drop,
    state.files.length ? h('ul', { class: 'ex-tf-files' }, state.files.map((f, i) => h('li', {},
      h('span', { class: 'ex-tf-file-ico' }, icon('tutes', { size: 18 })),
      h('span', { class: 'flex-grow-1 min-w-0 text-truncate text-14 main-text' }, f.name),
      h('span', { class: 'text-12 sub-text text-nowrap' }, `${(f.size / 1024 / 1024).toFixed(1)} MB`),
      h('button', { type: 'button', class: 'ex-tf-remove', 'aria-label': `Remove ${f.name}`, onclick: () => { state.files.splice(i, 1); draw(); } }, icon('close', { size: 18 }))))) : null,
    err('files'));
}

function addFiles(list) {
  const bad = [];
  for (const f of list) {
    const okType = OK_TYPES.includes(f.type) || /\.(pdf|docx?)$/i.test(f.name);
    if (!okType) bad.push(`"${f.name}" is not a PDF or Word file.`);
    else if (f.size > MAX_BYTES) bad.push(`"${f.name}" is bigger than 10 MB.`);
    else if (!state.files.some((x) => x.name === f.name && x.size === f.size)) state.files.push(f);
  }
  state.files = state.files.slice(0, 20);
  draw();
  if (bad.length) Toast.error(bad.join(' '));
}

function courierPart() {
  const courierSel = h('select', { id: 'tf-courier', class: 'form-select custom-select', onchange: (e) => { state.courier = e.target.value; draw(); } },
    h('option', { value: '' }, '- Choose later -'),
    meta.couriers.map((c) => h('option', { value: c, selected: state.courier === c ? true : null }, c)),
    h('option', { value: '__other', selected: state.courier === '__other' ? true : null }, 'Other'));
  return h('div', {},
    h('div', { class: 'row gx-4 gy-3' },
      h('div', { class: 'col-12 col-lg-6' },
        label('What is in the parcel', { required: true, info: 'One line per item, so you can check the parcel before you seal it. For example "Unit 5 tute book" and "Model paper 3".' }),
        h('div', { class: 'd-flex flex-column gap-2' }, state.items.map((item, i) => h('div', { class: 'd-flex gap-2' },
          h('input', { class: 'form-control custom-input', maxlength: '100', placeholder: i === 0 ? 'e.g. Unit 5 tute book' : 'Another item', value: item, oninput: (e) => { state.items[i] = e.target.value; } }),
          state.items.length > 1 ? h('button', { type: 'button', class: 'custom-outline-btn px-3', 'aria-label': 'Remove this item', onclick: () => { state.items.splice(i, 1); draw(); } }, icon('close', { size: 18 })) : null))),
        h('button', { type: 'button', class: 'ex-link-add mt-2', onclick: () => { state.items.push(''); draw(); root.querySelectorAll('.ex-tf-section input.custom-input')[state.items.length]?.focus(); } }, '+ Add another item'),
        err('items')),
      h('div', { class: 'col-12 col-lg-6' },
        h('div', { class: 'row gx-3 gy-3' },
          h('div', { class: 'col-12 col-md-6' }, label('Courier', { forId: 'tf-courier', info: 'The courier you usually post with. You can change it for each parcel when you send it, and add the tracking number then.' }), courierSel,
            state.courier === '__other' ? h('input', { class: 'form-control custom-input mt-2', maxlength: '60', placeholder: 'Courier name', value: state.courierOther, oninput: (e) => { state.courierOther = e.target.value; } }) : null, err('courier_name')),
          h('div', { class: 'col-12 col-md-6' }, label('Planned post date', { forId: 'tf-dispatch', info: 'The day you plan to post the parcels. Students see it as "Posting on".' }),
            h('input', { id: 'tf-dispatch', type: 'date', class: 'form-control custom-input', value: state.dispatch, onchange: (e) => { state.dispatch = e.target.value; } }), err('dispatch_on')),
          h('div', { class: 'col-12' }, label('Note to students', { forId: 'tf-note' }),
            h('input', { id: 'tf-note', class: 'form-control custom-input', maxlength: '255', placeholder: 'e.g. Check your address in My Profile before the 15th', value: state.note, oninput: (e) => { state.note = e.target.value; } }), err('student_note'))))),
    h('div', { class: 'ex-tf-note mt-4' }, icon('location', { size: 18 }),
      h('span', {}, 'Each student\'s address is copied from their profile when the parcel is made. Students with no address are marked so you can add one before posting. You can print or download the address list on the deliveries page.')));
}

// ---------- save ----------
async function save() {
  root.querySelectorAll('[data-err]').forEach((el) => { el.textContent = ''; });
  root.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
  const data = {
    class: classId,
    title: document.getElementById('tf-title')?.value ?? '',
    description: document.getElementById('tf-desc')?.value ?? '',
    type: state.type,
    who: meta.class.free ? 'all' : state.who,
    payment_month: state.who === 'paid' ? state.month : '',
  };
  if (state.type === 'courier') {
    Object.assign(data, { items: state.items, courier_name: state.courier === '__other' ? state.courierOther : state.courier, dispatch_on: state.dispatch, student_note: state.note });
  }
  const form = new FormData();
  form.append('data', JSON.stringify(data));
  if (state.type === 'download') state.files.forEach((f) => form.append('files[]', f, f.name));

  const labelText = saveBtn.textContent;
  [saveBtn, ...root.querySelectorAll('.custom-btn')].forEach((b) => { b.disabled = true; });
  saveBtn.textContent = 'Saving…';
  try {
    const res = (await api.post('/tutor/tutes', form)).data;
    Toast.success(res.message);
    setTimeout(() => { window.location.href = res.courier ? `couriered-tute?class-id=${classId}&tute=${res.id}` : `class-tutes?class-id=${classId}`; }, 700);
  } catch (e) {
    const details = e.details ?? {};
    Object.entries(details).forEach(([k, msg]) => {
      const el = root.querySelector(`[data-err="${k}"]`);
      if (el) el.textContent = msg;
    });
    const inputs = { title: 'tf-title', description: 'tf-desc', payment_month: 'tf-month', courier_name: 'tf-courier', dispatch_on: 'tf-dispatch', student_note: 'tf-note' };
    Object.keys(details).forEach((k) => document.getElementById(inputs[k])?.classList.add('is-invalid'));
    root.querySelector('[data-err]:not(:empty)')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    Toast.error(e.message);
    [saveBtn, ...root.querySelectorAll('.custom-btn')].forEach((b) => { b.disabled = false; });
    saveBtn.textContent = labelText;
  }
}
