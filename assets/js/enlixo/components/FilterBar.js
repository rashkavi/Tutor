import { Component } from '../core/Component.js';
import { h } from '../core/dom.js';

/**
 * Config-driven filters. Each filter: { key, label, type: 'select' | 'chips' | 'date', options: [{value,label}] | async () => [...] }
 * Options can load asynchronously (e.g. the tutor's classes); a "Loading…" option shows meanwhile.
 */
export class FilterBar extends Component {
  constructor({ filters, values = {}, onChange }) {
    super({ filters, onChange });
    this.values = { ...values };
    this.options = {};
    for (const f of filters) {
      if (typeof f.options === 'function') {
        this.options[f.key] = null;
        f.options().then((opts) => { this.options[f.key] = opts; this.update(); }).catch(() => { this.options[f.key] = []; this.update(); });
      } else {
        this.options[f.key] = f.options ?? [];
      }
    }
  }

  set(key, value) {
    if (value === '' || value === null || value === undefined) delete this.values[key];
    else this.values[key] = value;
    this.update();
    this.props.onChange({ ...this.values });
  }

  reset(values = {}) {
    this.values = { ...values };
    this.update();
  }

  render() {
    return h('div', { class: 'ex-filters' }, this.props.filters.map((f) => this.#renderFilter(f)));
  }

  #renderFilter(f) {
    const value = this.values[f.key] ?? '';
    const opts = this.options[f.key];
    if (f.type === 'chips') {
      return h('div', { class: 'ex-chip-group', role: 'group', 'aria-label': f.label },
        [{ value: '', label: f.allLabel ?? 'All' }, ...(opts ?? [])].map((o) =>
          h('button', { type: 'button', class: `ex-chip ${String(o.value) === String(value) ? 'ex-active' : ''}`, 'aria-pressed': String(String(o.value) === String(value)), onclick: () => this.set(f.key, o.value) }, o.label)));
    }
    if (f.type === 'date') {
      return h('input', { class: 'ex-input', type: 'date', value, 'aria-label': f.label, onchange: (e) => this.set(f.key, e.target.value) });
    }
    return h('select', { class: 'ex-select', 'aria-label': f.label, disabled: opts === null, onchange: (e) => this.set(f.key, e.target.value) },
      h('option', { value: '' }, opts === null ? 'Loading…' : f.allLabel ?? `All ${f.label.toLowerCase()}`),
      (opts ?? []).map((o) => h('option', { value: o.value, selected: String(o.value) === String(value) }, o.label)));
  }
}
