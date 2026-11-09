import { h } from '../core/dom.js';

export class Meter {
  static render(value, max, { warnAt = 0.8 } = {}) {
    const ratio = max ? Math.min(1, value / max) : 0;
    const cls = ratio >= 1 ? 'danger' : ratio >= warnAt ? 'warning' : '';
    return h('div', { class: `ex-meter ${cls}`, role: 'progressbar', 'aria-valuemin': '0', 'aria-valuemax': String(max), 'aria-valuenow': String(value) }, h('span', { style: { width: `${ratio * 100}%` } }));
  }
}
