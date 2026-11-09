import { h } from '../core/dom.js';

// Accessible modal: focus moves in, Esc / backdrop closes, focus returns to the opener.
export class Modal {
  constructor({ title, body, footer = null, wide = false, className = '', onClose = null }) {
    this.opts = { title, body, footer, wide, className, onClose };
  }

  open() {
    this.opener = document.activeElement;
    const head = this.opts.title === null ? null : h('div', { class: 'ex-modal-head' }, h('h2', { id: 'modal-title' }, this.opts.title), h('button', { class: 'ex-btn ex-btn-ghost ex-btn-sm', 'aria-label': 'Close', onclick: () => this.close() }, '✕'));
    this.dialog = h('div', { class: `ex-modal ${this.opts.wide ? 'ex-wide' : ''} ${this.opts.className}`, role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'modal-title' },
      head, h('div', { class: 'ex-modal-body' }, this.opts.body), this.opts.footer && h('div', { class: 'ex-modal-foot' }, this.opts.footer));
    this.backdrop = h('div', { class: 'ex-modal-backdrop', onclick: (e) => { if (e.target === this.backdrop) this.close(); } }, this.dialog);
    this.onKey = (e) => { if (e.key === 'Escape') this.close(); };
    document.addEventListener('keydown', this.onKey);
    document.body.append(this.backdrop);
    (this.dialog.querySelector('input, select, textarea, .ex-btn-primary, .ex-btn-premium') ?? this.dialog.querySelector('button'))?.focus();
    return this;
  }

  close() {
    document.removeEventListener('keydown', this.onKey);
    this.backdrop?.remove();
    this.opener?.focus?.();
    this.opts.onClose?.();
  }
}
