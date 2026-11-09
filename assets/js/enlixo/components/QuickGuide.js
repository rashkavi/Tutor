import { h } from '../core/dom.js';
import { Popover } from './Popover.js';

/**
 * ENLIXO V3 "Quick guide": the (i) next to a page title opens a card with a dark-blue header and a checklist.
 * Markup comes from PHP (enlixo_form_header): <button data-quick-guide='{"title","intro","points":[]}'>.
 *   QuickGuide.attachAll()
 */
export class QuickGuide {
  static card({ title, intro = '', points = [] }) {
    const check = () => h('span', { class: 'ex-qg-check', 'aria-hidden': 'true', html: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm-1.2 14.2-4-4 1.4-1.4 2.6 2.6 5.6-5.6 1.4 1.4-7 7Z"/></svg>' });
    return h('div', { class: 'ex-qg', role: 'note' },
      h('div', { class: 'ex-qg-head' },
        h('span', { class: 'ex-qg-icon', 'aria-hidden': 'true', html: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 2a8 8 0 1 1 0 16 8 8 0 0 1 0-16Zm1 11h-2v-5h2v5Zm0-7h-2V6h2v2Z"/></svg>' }),
        h('div', {}, h('strong', {}, title), h('small', {}, 'Quick guide'))),
      h('div', { class: 'ex-qg-body' },
        intro ? h('p', {}, intro) : null,
        points.length ? h('ul', {}, points.map((p) => h('li', {}, check(), h('span', {}, p)))) : null));
  }

  static attachAll(root = document) {
    root.querySelectorAll('[data-quick-guide]').forEach((btn) => {
      if (btn.dataset.qgBound) return;
      btn.dataset.qgBound = '1';
      const data = JSON.parse(btn.dataset.quickGuide);
      const host = btn.parentElement;
      const placeholder = document.createComment('qg');
      host.replaceChild(placeholder, btn);
      const pop = new Popover({ trigger: btn, align: 'start', className: 'ex-qg-pop', content: QuickGuide.card(data) });
      host.replaceChild(pop.el, placeholder);
    });
  }
}
