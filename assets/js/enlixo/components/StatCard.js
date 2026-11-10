import { h } from '../core/dom.js';
import { icon } from '../core/icons.js';
import { Popover, ActionMenu } from './Popover.js';

/**
 * The one summary card for every Tutor page (ENLIXO V3 dashboard cards):
 * a small icon tile, a label and a ⋯ menu on top, a big number, a short grey caption under it,
 * and, when there is a trend to show, a mini bar chart on the right.
 *
 *   StatCard.row([
 *     { icon: 'application', label: 'Applications', value: 8, caption: '3 this week',
 *       trend: { text: '+20%', dir: 'up' }, bars: [{ label: 'Mon 3 Nov', value: 1 }, ...],
 *       menu: [{ label: 'Show these', icon: 'filter', onClick }], onClick, selected },
 *   ], { cols: 'col-12 col-md-6 col-xl-3' });
 *
 * - `onClick` makes the whole card a button (e.g. filter the list below); `selected` marks the active one.
 * - `menu` items are ActionMenu items. No menu, no ⋯ (never an empty menu).
 * - `bars`: one series, oldest first; the last bar (now) is dark blue, the rest light. Hover a bar for its value.
 */
export class StatCard {
  static card({ icon: ico = 'info', label, value, caption = '', trend = null, bars = null, menu = null, onClick = null, selected = false }) {
    const menuBtn = menu?.length
      ? new Popover({
        trigger: h('button', { type: 'button', class: 'ex-stat-menu', 'aria-label': `${label}: more` }, icon('dots', { size: 18 })),
        content: (pop) => ActionMenu.list(menu, pop),
      }).el
      : null;
    menuBtn?.addEventListener('click', (e) => e.stopPropagation());

    const max = bars?.length ? Math.max(1, ...bars.map((b) => b.value)) : 1;
    const chart = bars?.length
      ? h('div', { class: 'ex-stat-bars', role: 'img', 'aria-label': `${label} per day: ${bars.map((b) => `${b.label} ${b.value}`).join(', ')}` },
        bars.map((b, i) => h('span', { class: i === bars.length - 1 ? 'ex-now' : '', title: `${b.label}: ${b.value}`, style: { height: `${Math.max(14, (b.value / max) * 100)}%` } })))
      : null;

    const card = h('div', {
      class: `ex-stat${onClick ? ' ex-clickable' : ''}${selected ? ' ex-selected' : ''}`,
      role: onClick ? 'button' : null, tabindex: onClick ? '0' : null, 'aria-pressed': onClick ? String(!!selected) : null,
      onclick: onClick, onkeydown: onClick ? (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); onClick(e); } } : null,
    },
    h('div', { class: 'ex-stat-head' }, h('span', { class: 'ex-stat-tile' }, icon(ico, { size: 18 })), h('span', { class: 'ex-stat-label' }, label), menuBtn),
    h('div', { class: 'ex-stat-body' },
      h('div', { class: 'ex-stat-main' },
        h('div', { class: 'ex-stat-value' }, String(value)),
        h('div', { class: 'ex-stat-cap' },
          trend ? h('span', { class: `ex-stat-trend ex-${trend.dir ?? 'flat'}` }, trend.text) : null,
          caption)),
      chart));
    return card;
  }

  static row(cards, { cols = 'col-12 col-md-6 col-xl-3', className = 'mb-4' } = {}) {
    return h('div', { class: `row g-3 ${className}` }, cards.filter(Boolean).map((c) => h('div', { class: cols }, StatCard.card(c))));
  }

  /** "+20%" / "-5%" / "New" / "0%" for this period against the last one, like V3. */
  static trend(now, before) {
    if (!before) return now ? { text: 'New', dir: 'up' } : { text: '0%', dir: 'flat' };
    const pct = Math.round(((now - before) / before) * 100);
    return { text: `${pct > 0 ? '+' : ''}${pct}%`, dir: pct > 0 ? 'up' : pct < 0 ? 'down' : 'flat' };
  }
}
