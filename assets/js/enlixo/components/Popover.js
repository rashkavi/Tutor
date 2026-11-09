import { h } from '../core/dom.js';

/**
 * A button that opens a small panel under it (filters, Action menu, row kebab).
 * No Bootstrap needed, so it works on the real pages and in design-demo alike.
 * Closes on outside click, Esc, or when an item calls close().
 */
export class Popover {
  constructor({ trigger, content, align = 'end', className = '' }) {
    this.trigger = trigger;
    this.content = content; // Element or (popover) => Element
    this.panel = h('div', { class: `ex-popover ex-${align} ${className}`, role: 'dialog', hidden: true });
    this.el = h('div', { class: 'ex-popover-host' }, trigger, this.panel);
    trigger.setAttribute('aria-haspopup', 'true');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.addEventListener('click', (e) => { e.stopPropagation(); this.isOpen ? this.close() : this.open(); });
    this.onDoc = (e) => { if (!this.el.contains(e.target)) this.close(); };
    // Fixed positioning, so a menu in a scrolling table is never clipped; it flips above when there's no room below.
    this.onMove = (e) => { if (!e || e.type === 'resize' || !this.panel.contains(e.target)) this.close(); };
    this.onKey = (e) => { if (e.key === 'Escape') { this.close(); trigger.focus(); } };
  }

  get isOpen() { return !this.panel.hidden; }

  open() {
    Popover.current?.close();
    Popover.current = this;
    this.panel.replaceChildren(typeof this.content === 'function' ? this.content(this) : this.content);
    this.panel.hidden = false;
    this.#place();
    this.trigger.setAttribute('aria-expanded', 'true');
    document.addEventListener('click', this.onDoc);
    document.addEventListener('keydown', this.onKey);
    window.addEventListener('resize', this.onMove);
    window.addEventListener('scroll', this.onMove, true);
    this.panel.querySelector('select, input, button, a')?.focus();
  }

  #place() {
    const r = this.trigger.getBoundingClientRect();
    const p = this.panel;
    Object.assign(p.style, { position: 'fixed', top: '', bottom: '', left: '', right: '' });
    const below = window.innerHeight - r.bottom;
    if (below < p.offsetHeight + 12 && r.top > below) p.style.bottom = `${window.innerHeight - r.top + 6}px`;
    else p.style.top = `${r.bottom + 6}px`;
    if (p.classList.contains('ex-start')) p.style.left = `${Math.max(8, r.left)}px`;
    else p.style.right = `${Math.max(8, document.documentElement.clientWidth - r.right)}px`;
  }

  close() {
    if (!this.isOpen) return;
    this.panel.hidden = true;
    this.trigger.setAttribute('aria-expanded', 'false');
    document.removeEventListener('click', this.onDoc);
    document.removeEventListener('keydown', this.onKey);
    window.removeEventListener('resize', this.onMove);
    window.removeEventListener('scroll', this.onMove, true);
    if (Popover.current === this) Popover.current = null;
  }
}

/**
 * Dropdown list of actions, e.g. the blue "Action" button in a page header or a row's ⋮ menu.
 *   ActionMenu.button('Action', items)   ActionMenu.kebab(items)
 * Item: { label, icon?: Element, href?, onClick?, locked?: Element (premium badge), danger? }
 */
export class ActionMenu {
  static list(items, pop) {
    return h('ul', { class: 'ex-menu', role: 'menu' }, items.filter(Boolean).map((it) => h('li', { role: 'none' },
      h(it.href && !it.onClick ? 'a' : 'button', {
        role: 'menuitem', class: `ex-menu-item ${it.danger ? 'ex-danger' : ''}`, href: it.href ?? null, type: it.href ? null : 'button',
        onclick: it.onClick ? (e) => { pop.close(); it.onClick(e); } : null,
      }, it.icon ?? null, h('span', {}, it.label), it.locked ?? null))));
  }

  static button(label, items, { className = 'ex-btn ex-btn-primary ex-btn-action' } = {}) {
    const trigger = h('button', { type: 'button', class: className }, label, h('span', { class: 'ex-caret', 'aria-hidden': 'true' }, '▾'));
    return new Popover({ trigger, content: (pop) => ActionMenu.list(items, pop) }).el;
  }

  static kebab(items, label = 'Actions') {
    const trigger = h('button', { type: 'button', class: 'ex-kebab', 'aria-label': label, html: '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>' });
    return new Popover({ trigger, content: (pop) => ActionMenu.list(items, pop) }).el;
  }
}
