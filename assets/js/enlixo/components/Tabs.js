import { h, clear, fill } from '../core/dom.js';

// Tabs with lazy panels and ?tab= in the URL.
export class Tabs {
  constructor({ tabs, param = 'tab' }) {
    this.tabs = tabs; // [{ id, label, badge?, render: (panel) => void }]
    this.param = param;
    const fromUrl = new URLSearchParams(location.search).get(param);
    this.active = tabs.some((t) => t.id === fromUrl) ? fromUrl : tabs[0].id;
  }

  mount(target) {
    this.bar = h('div', { class: 'ex-tabs', role: 'tablist' });
    this.panel = h('div', { role: 'tabpanel' });
    target.append(this.bar, this.panel);
    this.#show(this.active);
    return this;
  }

  #show(id) {
    this.active = id;
    const params = new URLSearchParams(location.search);
    params.set(this.param, id);
    history.replaceState(null, '', `${location.pathname}?${params}`);
    fill(this.bar, ...this.tabs.map((t) => h('button', { class: `ex-tab ${t.id === id ? 'ex-active' : ''}`, role: 'tab', 'aria-selected': String(t.id === id), onclick: () => this.#show(t.id) }, t.label, t.badge ?? null)));
    clear(this.panel);
    this.tabs.find((t) => t.id === id).render(this.panel);
  }
}
