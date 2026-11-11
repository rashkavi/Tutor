import { h, clear, fill } from '../core/dom.js';

/**
 * V3 tabs (Bootstrap nav nav-tabs + nav-link, drawn by the shared "TABS" rule: plain text, open tab blue with a blue underline).
 * A count goes in a chip: { id, label, badge: 3 }. Lazy panels, ?tab= in the URL.
 */
export class Tabs {
  constructor({ tabs, param = 'tab' }) {
    this.tabs = tabs; // [{ id, label, badge?, render: (panel) => void }]
    this.param = param;
    const fromUrl = new URLSearchParams(location.search).get(param);
    this.active = tabs.some((t) => t.id === fromUrl) ? fromUrl : tabs[0].id;
  }

  mount(target) {
    this.bar = h('ul', { class: 'nav nav-tabs mb-4', role: 'tablist' });
    this.panel = h('div', { role: 'tabpanel' });
    target.append(this.bar, this.panel);
    this.#show(this.active);
    return this;
  }

  /** Open a tab from code (unknown ids are ignored). */
  show(id) {
    if (this.tabs.some((t) => t.id === id)) this.#show(id);
  }

  /** Draw the open tab again (after its data or a badge changed). */
  refresh() {
    this.#show(this.active);
  }

  #show(id) {
    this.active = id;
    const params = new URLSearchParams(location.search);
    params.set(this.param, id);
    history.replaceState(null, '', `${location.pathname}?${params}`);
    fill(this.bar, ...this.tabs.map((t) => h('li', { class: 'nav-item', role: 'presentation' },
      h('button', { type: 'button', class: `nav-link${t.id === id ? ' active' : ''}`, role: 'tab', 'aria-selected': String(t.id === id), onclick: () => this.#show(t.id) },
        t.label, t.badge != null && t.badge !== '' ? (t.badge instanceof Node ? t.badge : h('span', { class: 'tab-count' }, String(t.badge))) : null))));
    clear(this.panel);
    this.tabs.find((t) => t.id === id).render(this.panel);
  }
}
