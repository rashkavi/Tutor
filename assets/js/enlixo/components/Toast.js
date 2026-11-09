import { h } from '../core/dom.js';

export class Toast {
  static #host() {
    return document.querySelector('.ex-toasts') ?? document.body.appendChild(h('div', { class: 'ex-toasts', role: 'status', 'aria-live': 'polite' }));
  }

  static show(message, type = 'info', ms = 3200) {
    const el = h('div', { class: `ex-toast ex-${type}` }, message);
    Toast.#host().append(el);
    setTimeout(() => el.remove(), ms);
  }

  static success(m) { Toast.show(m, 'success'); }
  static error(m) { Toast.show(m, 'error', 5000); }
}
