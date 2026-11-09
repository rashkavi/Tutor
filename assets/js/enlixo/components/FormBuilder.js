import { h, fill } from '../core/dom.js';
import { QuickGuide } from './QuickGuide.js';
import { Popover } from './Popover.js';

const DAYS = [[1, 'Monday'], [2, 'Tuesday'], [3, 'Wednesday'], [4, 'Thursday'], [5, 'Friday'], [6, 'Saturday'], [7, 'Sunday']];
const INFO_SVG = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm1 15h-2v-6h2v6Zm0-8h-2V7h2v2Z"/></svg>';

/** Small (i) next to a field label: hover/focus shows a Bootstrap tooltip, click shows the same text as a card (phones). */
export function fieldInfo(text, label) {
  if (!text) return null;
  const btn = h('button', { type: 'button', class: 'ex-info-btn ex-info-sm', 'aria-label': `${label}: ${text}`, 'data-bs-toggle': 'tooltip', 'data-bs-title': text, html: INFO_SVG });
  if (window.bootstrap?.Tooltip) queueMicrotask(() => new window.bootstrap.Tooltip(btn, { trigger: 'hover focus' }));
  else btn.title = text;
  return btn;
}

/** (i) next to a section title: opens a small quick-guide card. */
function sectionInfo(title, text) {
  if (!text) return null;
  const btn = h('button', { type: 'button', class: 'ex-info-btn', 'aria-label': `About ${title}`, html: INFO_SVG });
  return new Popover({ trigger: btn, align: 'start', className: 'ex-qg-pop', content: QuickGuide.card({ title, intro: text }) }).el;
}

/**
 * Draws a form from a server schema in the ENLIXO V3 style, using Bootstrap's grid and the app's own
 * classes (sub-topic, custom-input, custom-select, required, text-dark-red ...).
 *
 *   const form = new FormBuilder({ sections, fields, lookups, onChange }).mount(el);
 *   form.values(); form.coverFile(); form.showErrors({ field: 'message', 'schedule.0': '...' })
 *
 * Field types: text, textarea, number, date, select, chips (drawn as a select), radio, switch, yesno, schedule, image.
 * An `image` field is drawn as the upload panel on the left of the first section, like V3's profile photo.
 * `showIf: [otherKey, [allowed values]]` hides a field until the other field matches.
 */
export class FormBuilder {
  constructor({ sections, fields, lookups = {}, values = {}, onChange = () => {}, lockBadge = () => null, onLocked = () => {} }) {
    this.o = { sections, fields, lookups, onChange, lockBadge, onLocked };
    this.v = {};
    for (const f of fields) this.v[f.key] = values[f.key] ?? f.default ?? (f.type === 'schedule' ? [] : f.type === 'switch' ? false : '');
    this.file = null;
    this.rows = {};
  }

  mount(target) {
    const image = this.o.fields.find((f) => f.type === 'image');
    this.el = h('div', { class: 'ex-bsform' });
    let first = true;
    for (const s of this.o.sections) {
      const fields = this.o.fields.filter((f) => f.section === s.key && f.type !== 'image');
      if (!fields.length) continue;
      const grid = h('div', { class: 'row gx-4 gy-3' }, fields.map((f) => this.#col(f)));
      const head = h('div', { class: 'd-flex align-items-center gap-2 mb-3' }, h('span', { class: 'sub-topic' }, s.title), sectionInfo(s.title, s.info));
      let body = grid;
      if (first && image) {
        body = h('div', { class: 'row gx-4 gy-3 m-0 p-0' },
          h('div', { class: 'col-12 col-md-4 col-xl-3 p-0' }, this.#image(image)),
          h('div', { class: 'col-12 col-md-8 col-xl-9 ps-md-4 pe-0' }, grid));
      }
      this.el.append(h('section', { class: `ex-bsform-section ${first ? '' : 'mt-5'}`, 'aria-label': s.title }, head, body));
      first = false;
    }
    target.append(this.el);
    this.#applyShowIf();
    return this;
  }

  values() { return { ...this.v }; }
  coverFile() { return this.file; }

  set(key, value) {
    this.v[key] = value;
    this.#clearError(key);
    this.#applyShowIf();
    this.o.onChange(this.values(), key);
  }

  showErrors(errors) {
    let first = null;
    for (const [key, msg] of Object.entries(errors)) {
      const [base, idx] = key.split('.');
      const row = this.rows[base];
      if (!row) continue;
      const fb = idx !== undefined ? row.querySelector(`[data-slot="${idx}"] .ex-feedback`) : row.querySelector(':scope > .ex-feedback');
      if (fb) fb.textContent = msg;
      row.querySelectorAll(idx !== undefined ? `[data-slot="${idx}"] .custom-input, [data-slot="${idx}"] .custom-select` : ':scope > .custom-input, :scope > .custom-select, :scope .ex-image-panel')
        .forEach((el) => el.classList.add('is-invalid'));
      first ??= row;
    }
    first?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    first?.querySelector('input, select, textarea')?.focus({ preventScroll: true });
  }

  #clearError(key) {
    const row = this.rows[key];
    if (!row) return;
    row.querySelectorAll('.ex-feedback').forEach((e) => { e.textContent = ''; });
    row.querySelectorAll('.is-invalid').forEach((e) => e.classList.remove('is-invalid'));
  }

  #applyShowIf() {
    for (const f of this.o.fields) {
      if (!f.showIf || !this.rows[f.key]) continue;
      const [other, allowed] = f.showIf;
      this.rows[f.key].classList.toggle('d-none', !allowed.includes(this.v[other]));
    }
  }

  #colClass(f) {
    if (['textarea', 'schedule', 'radio'].includes(f.type) || f.width === 'full') return 'col-12';
    if (f.type === 'switch' || f.type === 'yesno') return 'col-12 col-md-6 col-lg-4';
    return 'col-12 col-md-6 col-lg-4';
  }

  #col(f) {
    const id = `f-${f.key}`;
    const label = h('label', { class: `text-14 main-text ${f.required ? 'required' : ''}`, for: ['radio', 'schedule'].includes(f.type) ? null : id, id: `${id}-label` }, f.label);
    const col = h('div', { class: `${this.#colClass(f)} d-flex flex-column`, 'data-key': f.key },
      h('div', { class: 'd-flex align-items-center gap-1' }, label, fieldInfo(f.info, f.label), f.locked ? this.o.lockBadge(f) : null),
      this.#input(f, id),
      h('span', { class: 'ex-feedback text-dark-red text-12', role: 'alert' }));
    this.rows[f.key] = col;
    return col;
  }

  #input(f, id) {
    const val = this.v[f.key];
    const set = (v) => this.set(f.key, v);
    const common = { id, name: f.key, 'aria-required': f.required ? 'true' : null, disabled: f.locked || null };
    switch (f.type) {
      case 'textarea':
        return h('textarea', { ...common, class: 'form-control custom-input mt-2', rows: '3', maxlength: f.maxlength ?? null, placeholder: f.placeholder ?? null, oninput: (e) => set(e.target.value) }, val);
      case 'number': {
        const input = h('input', { ...common, class: 'form-control custom-input', type: 'number', inputmode: 'decimal', min: f.min ?? null, max: f.max ?? null, step: f.step ?? null, value: val, placeholder: f.placeholder ?? null, oninput: (e) => set(e.target.value) });
        if (!f.prefix) { input.classList.add('mt-2'); return input; }
        return h('div', { class: 'input-group mt-2 ex-input-group-flat' }, h('span', { class: 'input-group-text custom-input text-14' }, f.prefix), input);
      }
      case 'date':
        return h('input', { ...common, class: 'form-control custom-input mt-2', type: 'date', value: val, oninput: (e) => set(e.target.value) });
      case 'select':
      case 'chips': {
        const opts = Array.isArray(f.options) ? f.options : this.o.lookups[f.options] ?? [];
        return h('select', { ...common, class: 'form-select custom-select mt-2', onchange: (e) => set(e.target.value) },
          f.type === 'select' ? h('option', { value: '' }, '- Select -') : null,
          opts.map((o) => h('option', { value: o.value, selected: String(o.value) === String(val) }, o.label)));
      }
      case 'radio':
        return h('div', { class: 'row gx-3 gy-2 mt-0', role: 'radiogroup', 'aria-labelledby': `${id}-label` }, f.options.map((o, i) =>
          h('div', { class: 'col-12 col-md-4' },
            h('label', { class: 'ex-choice bl-solid d-flex gap-2 p-3 h-100', for: `${id}-${i}` },
              h('input', { class: 'form-check-input mt-1 flex-none', type: 'radio', name: f.key, id: `${id}-${i}`, value: o.value, checked: o.value === val, onchange: () => set(o.value) }),
              h('span', {}, h('span', { class: 'd-block text-14 main-text' }, o.label), o.hint ? h('span', { class: 'd-block text-12 sub-text' }, o.hint) : null)))));
      case 'switch':
      case 'yesno': {
        const on = f.type === 'yesno' ? val === 'Yes' : !!val && !f.locked;
        return h('div', { class: 'form-check form-switch mt-2 ex-switch-bs' },
          h('input', { class: 'form-check-input', type: 'checkbox', role: 'switch', id, checked: on, disabled: f.locked || null,
            onchange: (e) => (f.locked ? this.o.onLocked(f) : set(f.type === 'yesno' ? (e.target.checked ? 'Yes' : 'No') : e.target.checked)) }),
          h('label', { class: 'form-check-label text-14 sub-text', for: id }, f.type === 'yesno' ? 'Yes' : (f.switchText ?? 'Yes')));
      }
      case 'schedule':
        return this.#schedule(f);
      default:
        return h('input', { ...common, class: 'form-control custom-input mt-2', type: 'text', maxlength: f.maxlength ?? null, value: val, placeholder: f.placeholder ?? (f.auto ? 'Leave empty to use the suggested name' : `Enter ${f.label.toLowerCase()}`), oninput: (e) => set(e.target.value) });
    }
  }

  #schedule(f) {
    const list = h('div', { class: 'mt-2 d-flex flex-column gap-2' });
    const upd = (i, patch) => this.set(f.key, this.v[f.key].map((s, j) => (j === i ? { ...s, ...patch } : s)));
    const draw = () => {
      const hybrid = this.v.delivery_mode === 'hybrid';
      fill(list, this.v[f.key].map((s, i) => h('div', { class: 'row gx-2 gy-2 align-items-center', 'data-slot': String(i) },
        h('div', { class: 'col-12 col-md-3' }, h('select', { class: 'form-select custom-select', 'aria-label': 'Day', onchange: (e) => upd(i, { weekday: Number(e.target.value) }) },
          DAYS.map(([d, n]) => h('option', { value: d, selected: d === s.weekday }, n)))),
        h('div', { class: 'col-5 col-md-2' }, h('input', { class: 'form-control custom-input ex-h48', type: 'time', 'aria-label': 'Starts at', value: s.start, onchange: (e) => upd(i, { start: e.target.value }) })),
        h('div', { class: 'col-2 col-md-auto text-center text-14 sub-text' }, 'to'),
        h('div', { class: 'col-5 col-md-2' }, h('input', { class: 'form-control custom-input ex-h48', type: 'time', 'aria-label': 'Ends at', value: s.end, onchange: (e) => upd(i, { end: e.target.value }) })),
        hybrid ? h('div', { class: 'col-10 col-md-3' }, h('select', { class: 'form-select custom-select', 'aria-label': 'In the hall or online', onchange: (e) => upd(i, { mode: e.target.value }) },
          [['physical', 'In the hall'], ['online', 'Online (Zoom)']].map(([v, n]) => h('option', { value: v, selected: v === s.mode }, n)))) : null,
        h('div', { class: 'col-2 col-md-auto' }, h('button', { type: 'button', class: 'btn btn-link text-dark-red p-1', 'aria-label': 'Remove this time', onclick: () => { const next = [...this.v[f.key]]; next.splice(i, 1); this.set(f.key, next); draw(); } }, '✕')),
        h('span', { class: 'col-12 ex-feedback text-dark-red text-12 mt-0' }))),
      h('div', {}, h('button', { type: 'button', class: 'custom-outline-btn px-4 text-14 ex-h40', onclick: () => {
        const last = this.v[f.key].at(-1);
        this.set(f.key, [...this.v[f.key], { weekday: last ? Math.min(7, last.weekday + 1) : 6, start: last?.start ?? '08:00', end: last?.end ?? '10:00', mode: this.v.delivery_mode === 'online' ? 'online' : 'physical' }]);
        draw();
        list.querySelector('[data-slot]:last-of-type select')?.focus({ preventScroll: true });
      } }, '+ Add a weekly time')));
    };
    this.redrawSchedule = draw;
    draw();
    return list;
  }

  #image(f) {
    const id = `f-${f.key}`;
    const empty = () => h('div', { class: 'ex-image-empty', 'aria-hidden': 'true', html: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 5v14H5V5h14m0-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2Zm-4.86 8.86-3 3.87L9 13.14 6 17h12l-3.86-5.14Z"/></svg>' });
    const box = h('div', { class: 'ex-image-box' }, empty());
    const input = h('input', { id, type: 'file', accept: 'image/jpeg,image/png,image/webp', class: 'd-none', onchange: (e) => {
      const file = e.target.files[0];
      if (!file) return;
      if (file.size > 2 * 1024 * 1024) { this.showErrors({ [f.key]: 'The image must be 2 MB or smaller.' }); input.value = ''; return; }
      this.file = file;
      fill(box, h('img', { src: URL.createObjectURL(file), alt: 'Class cover preview' }));
      removeBtn.classList.remove('d-none');
      this.set(f.key, file.name);
    } });
    const removeBtn = h('button', { type: 'button', class: 'btn btn-link p-0 text-12 w-100 d-none', onclick: () => { this.file = null; input.value = ''; fill(box, empty()); removeBtn.classList.add('d-none'); this.set(f.key, ''); } }, 'Remove image');
    const col = h('div', { class: 'd-flex flex-column', 'data-key': f.key },
      h('div', { class: 'd-flex align-items-center gap-1 mb-2' }, h('span', { class: 'text-14 main-text' }, f.label), fieldInfo(f.info, f.label)),
      h('div', { class: 'ex-image-panel bl-solid p-3' },
        h('div', { class: 'position-relative' }, box,
          h('label', { class: 'upload-image text-white p-2 bottom-0 start-0 m-0 text-14 gap-2', for: id, role: 'button', tabindex: '0',
            onkeydown: (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } } },
            h('span', { 'aria-hidden': 'true', html: '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M11 16V7.85l-2.6 2.6L7 9l5-5 5 5-1.4 1.45-2.6-2.6V16h-2Zm-5 4a2 2 0 0 1-2-2v-3h2v3h12v-3h2v3a2 2 0 0 1-2 2H6Z"/></svg>' }), 'Upload Image')),
        input,
        h('p', { class: 'text-center text-12 sub-text mt-2 mb-0' }, 'Image size must be 2MB or less, with a 16:9 aspect ratio for the best look. Accepted formats: JPG, PNG, WebP.'),
        removeBtn),
      h('span', { class: 'ex-feedback text-dark-red text-12', role: 'alert' }));
    this.rows[f.key] = col;
    return col;
  }
}
