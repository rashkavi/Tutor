import { h } from '../core/dom.js';
import { icon } from '../core/icons.js';
import { Popover, ActionMenu } from './Popover.js';

/**
 * The one summary card for every Tutor page: the ENLIXO V3 dashboard stat card
 * (same markup and classes as V3's statCardHtml(): .stats-mini-card, .stats-mini-top, .stats-mini-value ...).
 * Icon tile + title (+ optional ⋯ menu), big number, change chip + period text, mini bars on the right.
 *
 *   StatCard.row([
 *     { icon: 'application', label: 'Applications', value: 8, caption: '3 this week',
 *       trend: { text: '+20%', dir: 'up' }, bars: [{ label: 'Mon 3 Nov', value: 1 }, ...],
 *       menu: [{ label: 'Show these', icon: 'filter', onClick }], onClick, selected },
 *   ], { cols: 'col-12 col-md-6 col-xl-3' });
 *
 * - `onClick` makes the whole card a button (e.g. filter the list below); `selected` marks the active one.
 * - `menu` items are ActionMenu items. No menu, no ⋯ (never an empty menu).
 * - `bars`: one series, oldest first; the last bar (now) is dark blue, the rest light (V3 renderMiniCharts()).
 */
export class StatCard {
  static card({ icon: ico = 'info', label, value, caption = '', trend = null, bars = null, menu = null, onClick = null, selected = false }) {
    const menuBtn = menu?.length
      ? new Popover({
        trigger: h('button', { type: 'button', class: 'stats-mini-menu', 'aria-label': `${label}: more` }, icon('dots', { size: 18 })),
        content: (pop) => ActionMenu.list(menu, pop),
      }).el
      : null;
    menuBtn?.addEventListener('click', (e) => e.stopPropagation());

    const max = bars?.length ? Math.max(1, ...bars.map((b) => b.value)) : 1;
    const chart = bars?.length
      ? h('div', { class: 'stats-mini-chart', role: 'img', 'aria-label': `${label}: ${bars.map((b) => `${b.label} ${b.value}`).join(', ')}` },
        bars.map((b, i) => h('span', { class: `stats-mini-bar${i === bars.length - 1 ? ' active' : ''}`, title: `${b.label}: ${b.value}`, style: { height: `${Math.max((b.value / max) * 36, 8)}px` } })))
      : null;

    const text = String(value);
    const change = trend ? h('span', { class: `stats-mini-change ${{ up: 'positive', down: 'negative' }[trend.dir] ?? 'neutral'}` }, trend.text) : null;

    return h('div', {
      class: `stats-mini-card h-100${onClick ? ' ex-clickable' : ''}${selected ? ' ex-selected' : ''}`,
      role: onClick ? 'button' : null, tabindex: onClick ? '0' : null, 'aria-pressed': onClick ? String(!!selected) : null,
      onclick: onClick, onkeydown: onClick ? (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); onClick(e); } } : null,
    },
    h('div', { class: 'stats-mini-top' },
      h('div', { class: 'stats-mini-left' }, h('div', { class: 'stats-mini-icon dark-theme' }, icon(ico, { size: 16 })), h('span', { class: 'stats-mini-title' }, label)),
      menuBtn),
    h('div', { class: 'stats-mini-body' },
      h('div', { class: 'stats-mini-content' },
        h('div', { class: `stats-mini-value${text.length > 7 ? ' is-long' : ''}` }, text),
        h('div', { class: 'stats-mini-meta' }, change, caption ? h('span', { class: 'stats-mini-period' }, caption) : null)),
      chart));
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
