import { h, fill } from '../core/dom.js';
import { QuickGuide } from './QuickGuide.js';
import { Popover } from './Popover.js';
import { ImageCropper } from './ImageCropper.js';
import { icon } from '../core/icons.js';

const DAYS = [[1, 'Monday'], [2, 'Tuesday'], [3, 'Wednesday'], [4, 'Thursday'], [5, 'Friday'], [6, 'Saturday'], [7, 'Sunday']];
const INFO_SVG = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm1 15h-2v-6h2v6Zm0-8h-2V7h2v2Z"/></svg>';

/**
 * (i) next to a field label. Only fields that need explaining have one (the schema leaves it out of obvious fields).
 * Hover, focus or tap shows a small card: what the field does, and what to enter.
 */
export function fieldInfo(text, label, enter = '') {
  if (!text && !enter) return null;
  const btn = h('button', { type: 'button', class: 'ex-info-btn ex-info-sm', 'aria-label': `About ${label}: ${text} ${enter}`.trim(), html: INFO_SVG });
  // The same card as the V3 field info icon: field name, what it is, then what to enter.
  return new Popover({ trigger: btn, align: 'start', className: 'ex-qg-pop', hover: true,
    content: QuickGuide.card({ title: label, subtitle: 'Field guide', intro: text, points: enter ? [enter] : [] }) }).el;
}

/** (i) next to a section title: opens a small quick-guide card. */
function sectionInfo(title, text) {
  if (!text) return null;
  const btn = h('button', { type: 'button', class: 'ex-info-btn', 'aria-label': `About ${title}`, html: INFO_SVG });
  return new Popover({ trigger: btn, align: 'start', className: 'ex-qg-pop', content: QuickGuide.card({ title, intro: text }), hover: true }).el;
}

/**
 * Draws a form from a server schema in the ENLIXO V3 style, using Bootstrap's grid and the app's own
 * classes (sub-topic, custom-input, custom-select, required, text-dark-red ...).
 *
 *   const form = new FormBuilder({ sections, fields, lookups, onChange }).mount(el);
 *   form.values(); form.coverFile(); form.showErrors({ field: 'message', 'schedule.0': '...' })
 *
 * Field types: text, textarea, number, date, select, chips (drawn as a select), radio, switch, yesno, schedule, image.
 * An `image` field is drawn as the upload panel on the left of the first section, like V3's profile photo:
 * a chosen picture opens the Crop Image modal, and `onGenerateImage(field)` (optional) adds a "Generate thumbnail" button.
 * A select whose options come from exam_types / subjects / mediums gets "+ Add new" when `onAddOption(field)` is given.
 * `showIf: [otherKey, [allowed values]]` hides a field until the other field matches.
 * Also: type email / tel, `suggest: lookupName` (type-ahead from a list of words), `help` (grey line under the field),
 * `width: 'half' | 'full'`, and on an image field `aspect` (1 = square photo) and `hint`.
 * The image panel sits on the left of the section the image field belongs to.
 * `bars: true` draws each section as a white card with a blue title bar (the public application form).
 */
export class FormBuilder {
  constructor({ sections, fields, lookups = {}, values = {}, file = null, imageUrl = null, onChange = () => {}, lockBadge = () => null, onLocked = () => {}, onAddOption = null, onGenerateImage = null, bars = false }) {
    this.o = { sections, fields, lookups, onChange, lockBadge, onLocked, onAddOption, onGenerateImage, bars };
    this.v = {};
    for (const f of fields) this.v[f.key] = values[f.key] ?? f.default ?? (f.type === 'schedule' ? [] : f.type === 'switch' ? false : '');
    this.file = file;
    this.imageUrl = imageUrl; // the saved cover when editing
    this.removed = false;
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
      if (image && image.section === s.key) {
        body = h('div', { class: 'row gx-4 gy-3 m-0 p-0' },
          h('div', { class: 'col-12 col-md-4 col-xl-3 p-0' }, this.#image(image)),
          h('div', { class: 'col-12 col-md-8 col-xl-9 ps-md-4 pe-0' }, grid));
      }
      if (this.o.bars) {
        this.el.append(h('section', { class: 'ex-bar-section mb-4', 'aria-label': s.title },
          h('div', { class: 'ex-bar-head' }, s.icon ? icon(s.icon, { size: 20 }) : null, h('span', {}, s.title), s.sub ? h('span', { class: 'ex-bar-sub d-none d-md-inline' }, s.sub) : null),
          h('div', { class: 'p-4' }, body)));
      } else {
        this.el.append(h('section', { class: `ex-bsform-section ${first ? '' : 'mt-5'}`, 'aria-label': s.title }, head, body));
      }
      first = false;
    }
    target.append(this.el);
    this.#applyShowIf();
    return this;
  }

  values() { return { ...this.v }; }
  coverFile() { return this.file; }
  /** Editing: the tutor removed the saved cover and chose no new one. */
  coverRemoved() { return this.removed && !this.file; }

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
    if (f.width === 'half') return 'col-12 col-md-6';
    return 'col-12 col-md-6 col-lg-4';
  }

  #col(f) {
    const id = `f-${f.key}`;
    const label = h('label', { class: `text-14 main-text ${f.required ? 'required' : ''}`, for: ['radio', 'schedule'].includes(f.type) ? null : id, id: `${id}-label` }, f.label);
    const addable = f.type === 'select' && this.o.onAddOption && ['exam_types', 'subjects', 'mediums'].includes(f.options);
    const col = h('div', { class: `${this.#colClass(f)} d-flex flex-column`, 'data-key': f.key },
      h('div', { class: 'd-flex align-items-center gap-1' }, label, fieldInfo(f.info, f.label, f.enter), f.locked ? this.o.lockBadge(f) : null,
        addable ? h('button', { type: 'button', class: 'ex-link-add ms-auto', onclick: () => this.#addOption(f, id) }, '+ Add new') : null),
      this.#input(f, id),
      f.help ? h('span', { class: 'text-12 sub-text mt-1' }, f.help) : null,
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
            h('label', { class: 'ex-choice d-flex gap-2 p-3 h-100', for: `${id}-${i}` },
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
      case 'email':
      case 'tel':
        return h('input', { ...common, class: 'form-control custom-input mt-2', type: f.type, inputmode: f.type, autocomplete: f.type === 'tel' ? 'tel' : 'email', maxlength: f.maxlength ?? null, value: val, placeholder: f.placeholder ?? null, oninput: (e) => set(e.target.value) });
      default:
        if (f.suggest) {
          return h('div', { class: 'mt-2' },
            h('input', { ...common, class: 'form-control custom-input', type: 'text', list: `${id}-list`, autocomplete: 'off', maxlength: f.maxlength ?? null, value: val, placeholder: f.placeholder ?? null, oninput: (e) => set(e.target.value) }),
            h('datalist', { id: `${id}-list` }, (this.o.lookups[f.suggest] ?? []).map((w) => h('option', { value: w }))));
        }
        return h('input', { ...common, class: 'form-control custom-input mt-2', type: 'text', maxlength: f.maxlength ?? null, value: val, placeholder: f.placeholder ?? (f.auto && !f.required ? 'Leave empty to use the suggested name' : `Enter ${f.label.toLowerCase()}`), oninput: (e) => set(e.target.value) });
    }
  }

  async #addOption(f, id) {
    const added = await this.o.onAddOption(f);
    if (!added) return;
    (this.o.lookups[f.options] ??= []).push({ ...added, own: true });
    const select = document.getElementById(id);
    select?.append(h('option', { value: added.value }, added.label));
    if (select) select.value = added.value;
    this.set(f.key, added.value);
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
    let original = null; // what the tutor picked or generated, so "Crop again" starts from the full picture

    const show = (file) => {
      this.file = file;
      if (!file && this.imageUrl) { this.imageUrl = null; this.removed = true; }
      fill(box, file ? h('img', { src: URL.createObjectURL(file), alt: `${f.label} preview` }) : empty());
      actions.classList.toggle('d-none', !file);
      this.set(f.key, file ? file.name : '');
    };
    const crop = async (source) => {
      const file = await ImageCropper.open({ source, aspect: f.aspect ?? 16 / 9, fileName: f.key === 'cover' ? 'class-cover.jpg' : `${f.key}.jpg` });
      if (file) { original = source; show(file); }
    };
    const input = h('input', { id, type: 'file', accept: 'image/jpeg,image/png,image/webp', class: 'd-none', onchange: (e) => {
      const file = e.target.files[0];
      input.value = '';
      if (!file) return;
      if (!/^image\/(jpeg|png|webp)$/.test(file.type)) { this.showErrors({ [f.key]: 'Use a JPG, PNG or WebP image.' }); return; }
      if (file.size > 15 * 1024 * 1024) { this.showErrors({ [f.key]: 'This picture is too big. Use one under 15 MB.' }); return; }
      crop(file);
    } });
    const actions = h('div', { class: `d-flex justify-content-center gap-3 mt-2 ${this.file || this.imageUrl ? '' : 'd-none'}` },
      h('button', { type: 'button', class: 'ex-link-add', onclick: () => crop(original ?? this.file ?? this.imageUrl) }, 'Crop again'),
      h('button', { type: 'button', class: 'ex-link-add text-dark-red', onclick: () => { original = null; show(null); } }, 'Remove image'));
    if (this.file) fill(box, h('img', { src: URL.createObjectURL(this.file), alt: `${f.label} preview` }));
    else if (this.imageUrl) fill(box, h('img', { src: this.imageUrl, alt: f.label }));

    const generate = this.o.onGenerateImage
      ? h('button', { type: 'button', class: 'custom-outline-btn ex-btn-md w-100 mt-3 d-inline-flex align-items-center justify-content-center gap-2', onclick: async () => {
        const dataUrl = await this.o.onGenerateImage(f);
        if (dataUrl) await crop(dataUrl);
      } }, h('span', { class: 'ex-btn-ico', 'aria-hidden': 'true', html: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l1.9 5.6L19.5 9.5l-5.6 1.9L12 17l-1.9-5.6L4.5 9.5l5.6-1.9L12 2Zm7 12 .9 2.6 2.6.9-2.6.9L19 21l-.9-2.6-2.6-.9 2.6-.9L19 14Z"/></svg>' }), 'Generate thumbnail')
      : null;

    const col = h('div', { class: 'd-flex flex-column', 'data-key': f.key },
      h('div', { class: 'd-flex align-items-center gap-1 mb-2' }, h('span', { class: `text-14 main-text ${f.required ? 'required' : ''}` }, f.label), fieldInfo(f.info, f.label, f.enter)),
      h('div', { class: `ex-image-panel p-3${f.aspect === 1 ? ' ex-square' : ''}` },
        h('div', { class: 'position-relative' }, box,
          h('label', { class: 'upload-image text-white p-2 bottom-0 start-0 m-0 text-14 gap-2', for: id, role: 'button', tabindex: '0',
            onkeydown: (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } } },
            h('span', { 'aria-hidden': 'true', html: '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M11 16V7.85l-2.6 2.6L7 9l5-5 5 5-1.4 1.45-2.6-2.6V16h-2Zm-5 4a2 2 0 0 1-2-2v-3h2v3h12v-3h2v3a2 2 0 0 1-2 2H6Z"/></svg>' }), 'Upload Image')),
        input,
        actions,
        h('p', { class: 'text-center text-12 sub-text mt-2 mb-0' }, f.hint ?? 'JPG, PNG or WebP. You can crop it to 16:9 after choosing.'),
        generate),
      h('span', { class: 'ex-feedback text-dark-red text-12', role: 'alert' }));
    this.rows[f.key] = col;
    return col;
  }
}
