// Tutor → Create a Class (tutor/create-class.php), ENLIXO V3 form style.
// Fields come from the server (GET /tutor/class-form), so the tutor's own fields and hidden fields apply here.
// Saved with POST /tutor/classes: class + weekly times + monthly fee in one go.
import { h, fill } from '../core/dom.js';
import { ApiClient, ApiError, FeatureLockedError } from '../core/ApiClient.js';
import { FormBuilder, fieldInfo } from '../components/FormBuilder.js';
import { QuickGuide } from '../components/QuickGuide.js';
import { FeatureGate } from '../components/FeatureGate.js';
import { Toast } from '../components/Toast.js';

const api = new ApiClient();
const root = document.getElementById('class-form-root');
const TYPE_LABEL = { theory: 'Theory', revision: 'Revision', paper: 'Paper', other: '' };

let gate = null;
let schema = null;
let form = null;

QuickGuide.attachAll();

// Header: gear (customise form) + Save, like V3's Add New Student
const saveBtn = h('button', { type: 'button', class: 'custom-btn px-5 border-0', onclick: () => save() }, 'Save Class');
const gearBtn = h('button', { type: 'button', class: 'btn p-0 border-0 bg-transparent ex-gear', 'aria-label': 'Customise this form', title: 'Customise this form', onclick: () => openCustomiser(),
  html: '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68 1.65 1.65 0 0 0 10 3.17V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c.14.32.35.6.6.82.27.2.6.32.95.32H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/></svg>' });
document.getElementById('class-actions').append(gearBtn, saveBtn);

fill(root, h('div', { class: 'ex-root' }, h('div', { class: 'placeholder-glow' }, [70, 45, 85, 60].map((w) => h('span', { class: 'placeholder col-12 mb-3', style: { width: `${w}%`, height: '40px' } })))));
load();

async function load() {
  try {
    [gate, schema] = await Promise.all([FeatureGate.load(api).catch(() => null), api.get('/tutor/class-form').then((r) => r.data)]);
    draw();
  } catch (e) {
    fill(root, h('div', { class: 'text-center py-5' },
      h('p', { class: 'main-text mb-1' }, 'Couldn\'t load the form'),
      h('p', { class: 'text-14 sub-text' }, e.message),
      h('button', { type: 'button', class: 'custom-btn px-4 border-0', onclick: load }, 'Try again')));
  }
}

const label = (list, v) => (schema.lookups[list] ?? []).find((o) => String(o.value) === String(v))?.label ?? '';

function suggestedName(v) {
  if (!v.subject) return '';
  const parts = [label('years', v.year), label('exam_types', v.exam_type), label('subjects', v.subject), TYPE_LABEL[v.class_type] ?? ''].filter(Boolean);
  const medium = label('mediums', v.medium);
  return `${parts.join(' ')}${medium ? ` (${medium})` : ''}`;
}

function draw() {
  const previous = form?.values() ?? {};
  const host = h('div');
  form = new FormBuilder({
    sections: schema.sections,
    fields: schema.fields,
    lookups: schema.lookups,
    values: previous,
    lockBadge: (f) => gate?.badge(f.feature) ?? null,
    onLocked: (f) => gate?.openUpgrade(f.feature),
    onChange: (v, key) => {
      if (key === 'delivery_mode') form.redrawSchedule?.();
      const nameInput = document.getElementById('f-name');
      if (nameInput) nameInput.placeholder = suggestedName(v) || 'Leave empty to use the suggested name';
    },
  }).mount(host);
  fill(root, host);
}

async function save() {
  if (!form) return;
  const v = form.values();
  if (!v.name?.trim()) v.name = suggestedName(v);
  const body = new FormData();
  body.append('data', JSON.stringify(v));
  if (form.coverFile()) body.append('cover', form.coverFile());
  saveBtn.disabled = true;
  saveBtn.textContent = 'Saving…';
  try {
    const { data } = await api.post('/tutor/classes', body);
    Toast.success(`"${data.name}" created${data.slots ? ` with ${data.slots} weekly ${data.slots === 1 ? 'time' : 'times'}` : ''}.`);
    setTimeout(() => { location.href = `class-details?class-id=${encodeURIComponent(data.id)}`; }, 900);
  } catch (e) {
    if (e instanceof FeatureLockedError) gate?.openUpgrade(e.details);
    else if (e instanceof ApiError && e.details?.fields) { form.showErrors(e.details.fields); Toast.error('Please check the highlighted fields.'); }
    else Toast.error(e.message);
    saveBtn.disabled = false;
    saveBtn.textContent = 'Save Class';
  }
}

// ---------- Gear: customise the form (Bootstrap modal, app's own classes) ----------
function openCustomiser() {
  if (!schema) return;
  const hidden = new Set(schema.settings.hidden ?? []);
  const custom = structuredClone(schema.settings.custom ?? []);
  const sections = Object.fromEntries(schema.sections.map((s) => [s.key, s.title]));
  const typeNames = { text: 'Text', number: 'Number', date: 'Date', select: 'Dropdown', yesno: 'Yes / No' };
  const customHost = h('div', { class: 'd-flex flex-column gap-3' });
  const errorEl = h('span', { class: 'text-dark-red text-12', role: 'alert' });

  const drawCustom = () => fill(customHost,
    custom.length ? null : h('p', { class: 'text-14 sub-text m-0' }, 'No fields yet. Examples: Institute name, Hall number, Syllabus unit, Batch code.'),
    custom.map((c, i) => h('div', { class: 'bl-solid p-3' },
      h('div', { class: 'row gx-3 gy-2 align-items-end' },
        h('div', { class: 'col-12 col-md-5 d-flex flex-column' }, h('label', { class: 'text-14 main-text required' }, 'Field name'),
          h('input', { class: 'form-control custom-input mt-2', placeholder: 'e.g. Institute name', value: c.label ?? '', maxlength: '60', oninput: (e) => { c.label = e.target.value; } })),
        h('div', { class: 'col-7 col-md-3 d-flex flex-column' }, h('label', { class: 'text-14 main-text' }, 'Type'),
          h('select', { class: 'form-select custom-select mt-2', onchange: (e) => { c.type = e.target.value; drawCustom(); } },
            schema.custom_types.map((t) => h('option', { value: t, selected: t === c.type }, typeNames[t] ?? t)))),
        h('div', { class: 'col-5 col-md-2' }, h('div', { class: 'form-check form-switch mb-2' },
          h('input', { class: 'form-check-input', type: 'checkbox', role: 'switch', id: `cf-req-${i}`, checked: !!c.required, onchange: (e) => { c.required = e.target.checked; } }),
          h('label', { class: 'form-check-label text-14 main-text', for: `cf-req-${i}` }, 'Required'))),
        h('div', { class: 'col-12 col-md-2 d-flex justify-content-md-end gap-1 mb-1' },
          h('button', { type: 'button', class: 'btn btn-light btn-sm', 'aria-label': 'Move up', disabled: i === 0, onclick: () => { [custom[i - 1], custom[i]] = [custom[i], custom[i - 1]]; drawCustom(); } }, '↑'),
          h('button', { type: 'button', class: 'btn btn-light btn-sm text-dark-red', 'aria-label': 'Remove field', onclick: () => { custom.splice(i, 1); drawCustom(); } }, '✕')),
        h('div', { class: 'col-12 d-flex flex-column' }, h('label', { class: 'text-14 main-text' }, 'Help text for the (i) icon'),
          h('input', { class: 'form-control custom-input mt-2', placeholder: 'Optional', value: c.info ?? '', maxlength: '200', oninput: (e) => { c.info = e.target.value; } })),
        c.type === 'select' ? h('div', { class: 'col-12 d-flex flex-column' }, h('label', { class: 'text-14 main-text required' }, 'Choices (one per line)'),
          h('textarea', { class: 'form-control custom-input mt-2', rows: '3', oninput: (e) => { c.options = e.target.value.split('\n'); } }, (c.options ?? []).map((o) => (typeof o === 'string' ? o : o.value)).join('\n'))) : null))),
    custom.length < schema.max_custom ? h('div', {}, h('button', { type: 'button', class: 'custom-outline-btn px-4 text-14 ex-h40', onclick: () => { custom.push({ label: '', type: 'text', required: false, info: '' }); drawCustom(); } }, '+ Add your own field')) : null);
  drawCustom();

  const save = h('button', { type: 'button', class: 'custom-btn px-5 border-0' }, 'Save Form');
  const modalEl = h('div', { class: 'modal fade br-0', tabindex: '-1', 'aria-labelledby': 'customise-title', 'aria-hidden': 'true' },
    h('div', { class: 'modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable br-0' },
      h('div', { class: 'modal-content p-4 br-0' },
        h('div', { class: 'modal-header p-0 border-0' }, h('h1', { class: 'sub-topic m-0', id: 'customise-title' }, 'Customise the class form'), h('button', { type: 'button', class: 'btn-close', 'data-bs-dismiss': 'modal', 'aria-label': 'Close' })),
        h('div', { class: 'modal-body p-0 mt-4' },
          h('div', { class: 'd-flex align-items-center gap-2 mb-3' }, h('span', { class: 'sub-topic text-16' }, 'Show or hide fields'), fieldInfo('Hidden fields disappear from the form. Exam type, year, subject, medium and how you teach are always needed.', 'Show or hide fields')),
          h('div', { class: 'row gx-4 gy-2' }, schema.hideable.map((f) => h('div', { class: 'col-12 col-md-6 col-lg-4' },
            h('div', { class: 'form-check form-switch' },
              h('input', { class: 'form-check-input', type: 'checkbox', role: 'switch', id: `hide-${f.key}`, checked: !hidden.has(f.key), onchange: (e) => (e.target.checked ? hidden.delete(f.key) : hidden.add(f.key)) }),
              h('label', { class: 'form-check-label text-14 main-text', for: `hide-${f.key}` }, f.label, h('span', { class: 'sub-text text-12' }, ` · ${sections[f.section]}`)))))),
          h('div', { class: 'd-flex align-items-center gap-2 mt-5 mb-3' }, h('span', { class: 'sub-topic text-16' }, 'Your own fields'), fieldInfo('Details you always collect for a class. They show under "More details" and are saved with each class.', 'Your own fields')),
          customHost),
        h('div', { class: 'modal-footer p-0 pt-4 border-0 d-flex justify-content-between' }, errorEl,
          h('div', { class: 'd-flex gap-3' }, h('button', { type: 'button', class: 'custom-outline-btn px-4', 'data-bs-dismiss': 'modal' }, 'Cancel'), save)))));
  document.body.append(modalEl);
  const modal = new window.bootstrap.Modal(modalEl);
  modalEl.addEventListener('hidden.bs.modal', () => modalEl.remove());
  save.onclick = async () => {
    save.disabled = true;
    errorEl.textContent = '';
    try {
      await api.put('/tutor/class-form', { hidden: [...hidden], custom });
      schema = (await api.get('/tutor/class-form')).data;
      modal.hide();
      draw();
      Toast.success('Form updated.');
    } catch (e) {
      const msgs = Object.values(e.details?.fields ?? {});
      errorEl.textContent = msgs.length ? msgs.join(' ') : e.message;
    } finally { save.disabled = false; }
  };
  modal.show();
}
