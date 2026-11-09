import { h } from '../core/dom.js';

/**
 * One modal look for every Tutor page (ENLIXO V3): white, square corners, title + small grey subtitle,
 * body, and a footer with the secondary button(s) on the left and the main button on the right.
 * Built on Bootstrap's Modal, so focus, Esc and the backdrop behave the same everywhere.
 *
 *   const m = BsModal.open({ title, subtitle, body, size: 'lg', footerStart: [btn], footerEnd: [cancel, save] });
 *   m.close();
 *
 * Buttons: BsModal.button('Save', { primary: true, onclick }) gives the standard custom-btn / custom-outline-btn.
 */
export class BsModal {
  static button(text, { primary = false, icon = null, onclick = null, type = 'button', danger = false } = {}) {
    const cls = primary ? 'custom-btn border-0' : 'custom-outline-btn';
    return h('button', { type, class: `${cls} ex-btn-md px-4 d-inline-flex align-items-center justify-content-center gap-2${danger ? ' ex-btn-danger-fill' : ''}`, onclick },
      icon ? h('span', { class: 'ex-btn-ico', 'aria-hidden': 'true', html: icon }) : null, text);
  }

  static open({ title, subtitle = '', body, size = '', footerStart = [], footerEnd = [], onClose = null, static: isStatic = false }) {
    const titleId = `m-${Math.random().toString(36).slice(2, 8)}`;
    const el = h('div', { class: 'modal fade ex-v3-modal', tabindex: '-1', 'aria-labelledby': titleId, 'aria-hidden': 'true', 'data-bs-backdrop': isStatic ? 'static' : null },
      h('div', { class: `modal-dialog modal-dialog-centered modal-dialog-scrollable ${size ? `modal-${size}` : ''}` },
        h('div', { class: 'modal-content br-0 border-0' },
          h('div', { class: 'modal-header border-0 px-4 pt-4 pb-0 align-items-start' },
            h('div', { class: 'flex-grow-1' },
              h('h2', { class: 'ex-modal-title m-0', id: titleId }, title),
              subtitle ? h('p', { class: 'text-12 sub-text m-0 mt-1' }, subtitle) : null),
            h('button', { type: 'button', class: 'btn-close', 'data-bs-dismiss': 'modal', 'aria-label': 'Close' })),
          h('div', { class: 'modal-body px-4 py-3' }, body),
          (footerStart.length || footerEnd.length)
            ? h('div', { class: 'modal-footer ex-v3-modal-foot px-4 py-3 d-flex justify-content-between' },
              h('div', { class: 'd-flex gap-2' }, footerStart),
              h('div', { class: 'd-flex gap-2' }, footerEnd))
            : null)));
    document.body.append(el);
    const modal = new window.bootstrap.Modal(el);
    el.addEventListener('hidden.bs.modal', () => { el.remove(); onClose?.(); });
    modal.show();
    return { el, close: () => modal.hide(), body: el.querySelector('.modal-body') };
  }

  /** Yes / no question. Resolves true when the main button is pressed. */
  static confirm({ title, text, yes = 'Yes', no = 'Cancel', danger = false }) {
    return new Promise((resolve) => {
      let answer = false;
      const m = BsModal.open({
        title,
        size: 'sm',
        body: h('p', { class: 'text-14 main-text m-0' }, text),
        footerEnd: [
          BsModal.button(no, { onclick: () => m.close() }),
          BsModal.button(yes, { primary: true, danger, onclick: () => { answer = true; m.close(); } }),
        ],
        onClose: () => resolve(answer),
      });
    });
  }
}
