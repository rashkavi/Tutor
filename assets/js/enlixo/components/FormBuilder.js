import { h, fill, clear, svg } from '../core/dom.js';
import { infoTip } from './InfoTip.js';

const DAYS = [[1, 'Monday'], [2, 'Tuesday'], [3, 'Wednesday'], [4, 'Thursday'], [5, 'Friday'], [6, 'Saturday'], [7, 'Sunday']];

/**
 * Draws a form from a server schema: sections → fields, each with a label, (i) info, input and error line.
 *
 *   const form = new FormBuilder({ sections, fields, lookups, onChange: (values) => ... }).mount(el);
 *   form.values()            current values
 *   form.showErrors({ field: 'message', 'schedule.0': '...' })
 *
 * Field types: text, textarea, number, date, select, chips, radio, switch, yesno, schedule, image.
 * `showIf: [otherKey, [allowed values]]` hides a field until the other field matches.
 * `locked: true` (package feature missing) shows the field disabled with the premium badge from `lockBadge(field)`.
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
    this.el = h('div', { class: 'ex-form' });
    for (const s of this.o.sections) {
      const fields = this.o.fields.filter((f) => f.section === s.key);
      if (!fields.length) continue;
      this.el.append(h('section', { class: 'ex-form-section ex-card', 'aria-labelledby': `sec-${s.key}` },
        h('header', { class: 'ex-form-section-head' }, h('h2', { id: `sec-${s.key}` }, s.title), infoTip(s.info, s.title)),
        h('div', { class: 'ex-form-grid' }, fields.map((f) => this.#row(f)))));
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
      const base = key.split('.')[0];
      const row = this.rows[base];
      if (!row) continue;
      if (key.includes('.')) {
        const idx = key.split('.')[1];
        const line = row.querySelector(`[data-slot="${idx}"] .ex-slot-error`);
        if (line) { line.textContent = msg; line.hidden = false; }
      } else {
        const err = row.querySelector(':scope > .ex-error');
        err.textContent = msg;
        err.hidden = false;
      }
      row.classList.add('ex-has-error');
      first ??= row;
    }
    first?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    first?.querySelector('input, select, textarea, button')?.focus({ preventScroll: true });
  }

  #clearError(key) {
    const row = this.rows[key];
    if (!row) return;
    row.classList.remove('ex-has-error');
    row.querySelectorAll('.ex-error, .ex-slot-error').forEach((e) => { e.hidden = true; });
  }

  #applyShowIf() {
    for (const f of this.o.fields) {
      if (!f.showIf) continue;
      const [other, allowed] = f.showIf;
      const visible = allowed.includes(this.v[other]);
      this.rows[f.key].hidden = !visible;
    }
  }

  #row(f) {
    const id = `f-${f.key}`;
    const label = h('label', { class: 'ex-label', for: ['chips', 'radio', 'schedule', 'switch', 'yesno'].includes(f.type) ? null : id, id: `${id}-label` },
      f.label, f.required ? h('span', { class: 'ex-req', 'aria-hidden': 'true' }, '*') : null);
    const badge = f.locked ? this.o.lockBadge(f) : null;
    const row = h('div', { class: `ex-form-row ${f.width === 'full' || ['textarea', 'schedule', 'image', 'radio'].includes(f.type) ? 'ex-full' : ''} ${f.locked ? 'ex-locked' : ''}`, 'data-key': f.key },
      h('div', { class: 'ex-label-line' }, label, infoTip(f.info, f.label), badge),
      this.#input(f, id),
      h('div', { class: 'ex-error', role: 'alert', hidden: true }));
    this.rows[f.key] = row;
    return row;
  }

  #input(f, id) {
    const val = this.v[f.key];
    const set = (v) => this.set(f.key, v);
    const common = { id, name: f.key, 'aria-labelledby': `${id}-label`, 'aria-required': f.required ? 'true' : null, disabled: f.locked || null };
    switch (f.type) {
      case 'textarea':
        return h('textarea', { ...common, class: 'ex-input', rows: '3', maxlength: f.maxlength ?? null, oninput: (e) => set(e.target.value) }, val);
      case 'number': {
        const input = h('input', { ...common, class: 'ex-input', type: 'number', inputmode: 'decimal', min: f.min ?? null, max: f.max ?? null, step: f.step ?? null, value: val, oninput: (e) => set(e.target.value) });
        return f.prefix ? h('div', { class: 'ex-input-group' }, h('span', { class: 'ex-addon' }, f.prefix), input) : input;
      }
      case 'date':
        return h('input', { ...common, class: 'ex-input', type: 'date', value: val, oninput: (e) => set(e.target.value) });
      case 'select': {
        const opts = Array.isArray(f.options) ? f.options : this.o.lookups[f.options] ?? [];
        return h('select', { ...common, class: 'ex-select', onchange: (e) => set(e.target.value) },
          h('option', { value: '' }, '- Select -'), opts.map((o) => h('option', { value: o.value, selected: String(o.value) === String(val) }, o.label)));
      }
      case 'chips':
        return h('div', { class: 'ex-segment', role: 'radiogroup', 'aria-labelledby': `${id}-label` }, f.options.map((o) => {
          const b = h('button', { type: 'button', role: 'radio', class: `ex-segment-btn ${o.value === val ? 'ex-active' : ''}`, 'aria-checked': String(o.value === val), onclick: () => {
            b.parentElement.querySelectorAll('button').forEach((x) => { x.classList.toggle('ex-active', x === b); x.setAttribute('aria-checked', String(x === b)); });
            set(o.value);
          } }, o.label);
          return b;
        }));
      case 'radio':
        return h('div', { class: 'ex-radio-cards', role: 'radiogroup', 'aria-labelledby': `${id}-label` }, f.options.map((o) =>
          h('label', { class: 'ex-radio-card' },
            h('input', { type: 'radio', name: f.key, value: o.value, checked: o.value === val, onchange: () => set(o.value) }),
            h('span', {}, h('strong', {}, o.label), o.hint ? h('small', {}, o.hint) : null))));
      case 'switch':
      case 'yesno': {
        const on = f.type === 'yesno' ? val === 'Yes' : !!val;
        return h('label', { class: 'ex-switch' },
          h('input', { type: 'checkbox', role: 'switch', 'aria-labelledby': `${id}-label`, checked: on && !f.locked, disabled: f.locked || null,
            onchange: (e) => (f.locked ? this.o.onLocked(f) : set(f.type === 'yesno' ? (e.target.checked ? 'Yes' : 'No') : e.target.checked)) }),
          h('span', { class: 'ex-switch-track', 'aria-hidden': 'true' }),
          h('span', { class: 'ex-switch-text' }, f.type === 'yesno' ? (on ? 'Yes' : 'No') : (on ? 'On' : 'Off')));
      }
      case 'schedule':
        return this.#schedule(f);
      case 'image':
        return this.#image(f, id);
      default:
        return h('input', { ...common, class: 'ex-input', type: 'text', maxlength: f.maxlength ?? null, value: val, placeholder: f.auto ? 'Leave empty to use the suggested name' : null, oninput: (e) => set(e.target.value) });
    }
  }

  #schedule(f) {
    const list = h('div', { class: 'ex-slots' });
    const draw = () => {
      const hybrid = this.v.delivery_mode === 'hybrid';
      fill(list, this.v[f.key].map((s, i) => h('div', { class: 'ex-slot', 'data-slot': String(i) },
        h('select', { class: 'ex-select', 'aria-label': 'Day', onchange: (e) => upd(i, { weekday: Number(e.target.value) }) },
          DAYS.map(([d, n]) => h('option', { value: d, selected: d === s.weekday }, n))),
        h('input', { class: 'ex-input', type: 'time', 'aria-label': 'Starts at', value: s.start, onchange: (e) => upd(i, { start: e.target.value }) }),
        h('span', { class: 'ex-muted' }, 'to'),
        h('input', { class: 'ex-input', type: 'time', 'aria-label': 'Ends at', value: s.end, onchange: (e) => upd(i, { end: e.target.value }) }),
        hybrid ? h('select', { class: 'ex-select', 'aria-label': 'Physical or online', onchange: (e) => upd(i, { mode: e.target.value }) },
          [['physical', 'In the hall'], ['online', 'Online (Zoom)']].map(([v, n]) => h('option', { value: v, selected: v === s.mode }, n))) : null,
        h('button', { type: 'button', class: 'ex-btn ex-btn-ghost ex-btn-sm', 'aria-label': 'Remove this time', onclick: () => { const next = [...this.v[f.key]]; next.splice(i, 1); this.set(f.key, next); draw(); } }, '✕'),
        h('div', { class: 'ex-slot-error ex-error', hidden: true }))),
      h('button', { type: 'button', class: 'ex-btn ex-btn-sm ex-add-slot', onclick: () => {
        const last = this.v[f.key].at(-1);
        this.set(f.key, [...this.v[f.key], { weekday: last ? Math.min(7, last.weekday + 1) : 6, start: last?.start ?? '08:00', end: last?.end ?? '10:00', mode: this.v.delivery_mode === 'online' ? 'online' : 'physical' }]);
        draw();
        list.querySelector('.ex-slot:last-of-type select')?.focus({ preventScroll: true });
      } }, '+ Add a weekly time'));
    };
    const upd = (i, patch) => { const next = this.v[f.key].map((s, j) => (j === i ? { ...s, ...patch } : s)); this.set(f.key, next); };
    this.redrawSchedule = draw;
    draw();
    return list;
  }

  #image(f, id) {
    const preview = h('div', { class: 'ex-cover-preview' }, h('span', { class: 'ex-muted' }, 'No image yet'));
    const input = h('input', { id, type: 'file', accept: 'image/jpeg,image/png,image/webp', class: 'ex-sr-only', onchange: (e) => {
      const file = e.target.files[0];
      if (!file) return;
      if (file.size > 2 * 1024 * 1024) { this.showErrors({ [f.key]: 'The image must be 2 MB or smaller.' }); input.value = ''; return; }
      this.file = file;
      const url = URL.createObjectURL(file);
      fill(preview, h('img', { src: url, alt: 'Class cover preview' }));
      this.set(f.key, file.name);
    } });
    return h('div', { class: 'ex-cover' }, preview,
      h('div', { class: 'ex-cover-side' },
        h('p', { class: 'ex-muted ex-small' }, 'JPG, PNG or WebP. Up to 2 MB. A wide 16:9 image looks best.'),
        h('label', { class: 'ex-btn ex-btn-primary', for: id }, 'Choose image'), input,
        h('button', { type: 'button', class: 'ex-link-btn', onclick: () => { this.file = null; input.value = ''; fill(preview, h('span', { class: 'ex-muted' }, 'No image yet')); this.set(f.key, ''); } }, 'Remove')));
  }
}
