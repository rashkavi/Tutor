import { h } from '../core/dom.js';
import { icon } from '../core/icons.js';

/**
 * A button that opens a small panel under it (filters, Action menu, row kebab).
 * No Bootstrap needed, so it works on the real pages and in design-demo alike.
 * Closes on outside click, Esc, or when an item calls close().
 */
export class Popover {
  constructor({ trigger, content, align = 'end', className = '', hover = false }) {
    this.trigger = trigger;
    this.content = content; // Element or (popover) => Element
    this.panel = h('div', { class: `ex-popover ex-${align} ${className}`, role: 'dialog', hidden: true });
    this.el = h('div', { class: 'ex-popover-host' }, trigger, this.panel);
    trigger.setAttribute('aria-haspopup', 'true');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.addEventListener('click', (e) => {
      e.stopPropagation();
      if (hover && this.isOpen && !window.matchMedia('(hover: none)').matches) return; // already open from the hover
      this.isOpen ? this.close() : this.open();
    });
    if (hover) {
      // V3 info tooltips: open on hover / focus, with a short grace period to move the pointer into the card.
      let timer;
      const later = (fn, ms) => { clearTimeout(timer); timer = setTimeout(fn, ms); };
      this.el.addEventListener('mouseenter', () => later(() => { if (!this.isOpen) this.open(); }, 80));
      this.el.addEventListener('mouseleave', () => later(() => this.close(), 180));
      trigger.addEventListener('focus', () => { if (!this.isOpen) this.open(); });
      trigger.addEventListener('blur', () => later(() => this.close(), 180));
    }
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
    this.panel.querySelector('select, input, button, a')?.focus({ preventScroll: true });
  }

  #place() {
    const r = this.trigger.getBoundingClientRect();
    const p = this.panel;
    // 'auto' (not '') so the stylesheet's top/right for the old absolute layout can't stretch or squash the panel.
    Object.assign(p.style, { position: 'fixed', top: 'auto', bottom: 'auto', left: 'auto', right: 'auto' });
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
 * Dropdown list of actions (ENLIXO V3 menu): white card, an outline icon and a label per row, thin lines between rows.
 *   ActionMenu.button('Action', items)   ActionMenu.kebab(items)
 * Item: { label, icon?: icon name (assets/icons/icon-map.json) or Element, href?, onClick?, locked?: Element (premium badge), danger? }
 * A `null` item is skipped; the string '-' adds a thicker gap between groups.
 */
export class ActionMenu {
  static list(items, pop) {
    return h('ul', { class: 'ex-menu', role: 'menu' }, items.filter(Boolean).map((it) => (it === '-'
      ? h('li', { role: 'separator', class: 'ex-menu-sep' })
      : h('li', { role: 'none' },
        h(it.href && !it.onClick ? 'a' : 'button', {
          role: 'menuitem', class: `ex-menu-item ${it.danger ? 'ex-danger' : ''}`, href: it.href ?? null, type: it.href ? null : 'button',
          onclick: it.onClick ? (e) => { pop.close(); it.onClick(e); } : null,
        }, typeof it.icon === 'string' ? icon(it.icon, { size: 20, className: 'ex-menu-ico' }) : (it.icon ?? null), h('span', { class: 'flex-grow-1' }, it.label), it.locked ?? null)))));
  }

  static button(label, items, { className = 'custom-btn ex-btn-md px-4 border-0' } = {}) {
    const trigger = h('button', { type: 'button', class: className }, label, icon('chevron-down', { size: 16 }));
    return new Popover({ trigger, content: (pop) => ActionMenu.list(items, pop) }).el;
  }

  static kebab(items, label = 'Actions') {
    const trigger = h('button', { type: 'button', class: 'ex-kebab', 'aria-label': label }, icon('kebab', { size: 20 }));
    return new Popover({ trigger, content: (pop) => ActionMenu.list(items, pop) }).el;
  }
}
