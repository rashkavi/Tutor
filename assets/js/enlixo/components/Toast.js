import { h } from '../core/dom.js';
import { iconSvg } from '../core/icons.js';

/**
 * Success / error / warning / info messages as the ENLIXO V3 toast (V3 script.js showToast + style.css "TOASTS"):
 * a white card at the top right with a coloured line on the left, the round icon, a bold title ("Success"),
 * the message underneath, a close X and a line along the bottom that runs down until the toast goes.
 *   Toast.success('Class saved')   Toast.error('Please try again')   Toast.show('Copied', 'info')
 * Uses the same <div class="notifications"> host and classes as the older pages' showToast(), so every page looks the same.
 */
const KINDS = {
  success: { title: 'Success', icon: 'toast-success', ms: 3000 },
  error: { title: 'Error', icon: 'toast-error', ms: 5000 },
  warning: { title: 'Warning', icon: 'toast-warning', ms: 5000 },
  info: { title: 'Info', icon: 'toast-info', ms: 3000 },
};

export class Toast {
  static #host() {
    let host = document.querySelector('.notifications');
    if (!host) host = document.body.appendChild(h('div', { class: 'notifications' }));
    host.setAttribute('role', 'status');
    host.setAttribute('aria-live', 'polite');
    return host;
  }

  static #close(el) {
    if (el.classList.contains('hide')) return;
    clearTimeout(el.timeoutId);
    el.classList.add('hide');
    setTimeout(() => el.remove(), 400);
  }

  /** type: success | error | warning | info; ms: how long it stays (errors and warnings stay longer). */
  static show(message, type = 'info', ms) {
    const kind = KINDS[type] ?? KINDS.info;
    const wait = ms ?? kind.ms;
    const icon = h('span', { class: 'custom-toast-icon' });
    icon.innerHTML = iconSvg(kind.icon, 24);
    const close = h('button', { type: 'button', class: 'close-btn', 'aria-label': 'Close message' });
    close.innerHTML = iconSvg('x', 20);
    const el = h('li', { class: `custom-toast ${KINDS[type] ? type : 'info'}`, style: `--toast-ms: ${wait}ms` },
      h('div', { class: 'column' }, icon,
        h('div', { class: 'custom-toast-text' },
          h('span', { class: 'custom-toast-type' }, kind.title),
          h('span', { class: 'custom-toast-message' }, String(message ?? '')))),
      close);
    close.addEventListener('click', () => Toast.#close(el));
    // keep it while the mouse is on it, so a long message can be read
    el.addEventListener('mouseenter', () => { clearTimeout(el.timeoutId); el.classList.add('paused'); });
    el.addEventListener('mouseleave', () => { el.classList.remove('paused'); el.timeoutId = setTimeout(() => Toast.#close(el), 1500); });
    Toast.#host().append(el);
    el.timeoutId = setTimeout(() => Toast.#close(el), wait);
    return el;
  }

  static success(m) { return Toast.show(m, 'success'); }
  static error(m) { return Toast.show(m, 'error'); }
  static warning(m) { return Toast.show(m, 'warning'); }
  static info(m) { return Toast.show(m, 'info'); }
}
