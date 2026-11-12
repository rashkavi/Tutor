import { h } from '../core/dom.js';
import { icon as ico } from '../core/icons.js';

/**
 * One dialog look for every Tutor page: the ENLIXO V3 "Finance dialog" (.fin-dlg, style rules in enlixo-ui.css).
 * Header = icon tile + title + grey sub line + round close icon; the body scrolls; footer bar with
 * Cancel / Close on the left of the main button. Built on Bootstrap's Modal (focus, Esc, backdrop).
 *
 *   const m = BsModal.open({ title, subtitle, icon: 'edit', tone: 'danger', body, size: 'lg', footerStart: [btn], footerEnd: [cancel, save] });
 *   m.close();
 *
 * Buttons: BsModal.button('Save', { primary: true, onclick }) -> custom-btn; secondary -> custom-btn-outline;
 * { danger: true } -> custom-danger-btn, { success: true } -> custom-success-btn (approve / confirm).
 * footerStart items sit at the far left of the bar (an extra action), footerEnd at the right.
 */
export class BsModal {
  static button(text, { primary = false, icon = null, onclick = null, type = 'button', danger = false, success = false } = {}) {
    const cls = danger && primary ? 'custom-danger-btn' : success ? 'custom-success-btn' : primary ? 'custom-btn' : 'custom-btn-outline';
    return h('button', { type, class: `${cls}${danger && !primary ? ' text-dark-red' : ''} d-inline-flex align-items-center justify-content-center gap-2`, onclick },
      icon ? h('span', { class: 'ex-btn-ico', 'aria-hidden': 'true', html: icon }) : null, text);
  }

  static open({ title, subtitle = '', icon = 'info', tone = '', body, size = '', footerStart = [], footerEnd = [], onClose = null, static: isStatic = false }) {
    const titleId = `m-${Math.random().toString(36).slice(2, 8)}`;
    const subs = (Array.isArray(subtitle) ? subtitle : [subtitle]).filter(Boolean);
    const el = h('div', { class: 'modal fade fin-dlg ex-v3-modal', tabindex: '-1', 'aria-labelledby': titleId, 'aria-hidden': 'true', 'data-bs-backdrop': isStatic ? 'static' : null },
      h('div', { class: `modal-dialog modal-dialog-centered modal-dialog-scrollable ${size ? `modal-${size}` : ''}` },
        h('div', { class: 'modal-content border-0' },
          h('div', { class: 'fin-dlg-head' },
            icon ? h('span', { class: `fin-dlg-icon${tone ? ` tone-${tone}` : ''}` }, ico(icon, { size: 22 })) : null,
            h('div', { class: 'fin-dlg-titles' },
              h('h2', { class: 'fin-dlg-title', id: titleId }, title),
              subs.length ? h('div', { class: 'fin-dlg-sub' }, subs.flatMap((s, i) => (i ? [h('span', { class: 'fin-dot', 'aria-hidden': 'true' }), h('span', null, s)] : [h('span', null, s)]))) : null),
            h('button', { type: 'button', class: 'fin-dlg-close', 'data-bs-dismiss': 'modal', 'aria-label': 'Close' }, ico('close', { size: 22 }))),
          h('div', { class: 'modal-body fin-dlg-body' }, body),
          (footerStart.length || footerEnd.length)
            ? h('div', { class: 'fin-dlg-foot' },
              footerStart.length ? h('div', { class: 'd-flex gap-2 me-auto' }, footerStart) : null,
              footerEnd)
            : null)));
    document.body.append(el);
    const modal = new window.bootstrap.Modal(el);
    el.addEventListener('hidden.bs.modal', () => { el.remove(); onClose?.(); });
    modal.show();
    return { el, close: () => modal.hide(), body: el.querySelector('.modal-body') };
  }

  /** Yes / no question (V3 FIN.ask look). Resolves true when the main button is pressed. */
  static confirm({ title, text, yes = 'Yes', no = 'Cancel', danger = false, success = false, icon = null }) {
    return new Promise((resolve) => {
      let answer = false;
      const tone = danger ? 'danger' : success ? 'success' : '';
      const el = h('div', { class: 'modal fade fin-dlg fin-ask', tabindex: '-1', 'aria-hidden': 'true', role: 'alertdialog' },
        h('div', { class: 'modal-dialog modal-dialog-centered' },
          h('div', { class: 'modal-content border-0' },
            h('div', { class: 'modal-body fin-dlg-body text-center position-relative' },
              h('button', { type: 'button', class: 'fin-dlg-close position-absolute top-0 end-0 m-2', 'data-bs-dismiss': 'modal', 'aria-label': 'Close' }, ico('close', { size: 22 })),
              h('span', { class: `fin-ask-icon${tone ? ` tone-${tone}` : ''}` }, ico(icon ?? (danger ? 'danger' : 'question'), { size: 28 })),
              h('div', { class: 'fin-ask-title' }, title),
              text ? h('div', { class: 'fin-ask-text' }, text) : null),
            h('div', { class: 'fin-dlg-foot' },
              BsModal.button(no, { onclick: () => modal.hide() }),
              BsModal.button(yes, { primary: true, danger, success, onclick: () => { answer = true; modal.hide(); } })))));
      document.body.append(el);
      const modal = new window.bootstrap.Modal(el);
      el.addEventListener('hidden.bs.modal', () => { el.remove(); resolve(answer); });
      modal.show();
    });
  }
}
