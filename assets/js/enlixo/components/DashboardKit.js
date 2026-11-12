import { h } from '../core/dom.js';
import { icon } from '../core/icons.js';
import { ActionMenu } from './Popover.js';

/**
 * Pieces every dashboard shares (tutor, admin; the student and parent pages draw the same markup in PHP).
 *
 *   DashboardKit.greeting({ name: 'Nimal', line: ['You have ', h('b', {}, '2 classes'), ' today.'],
 *     extra: node, primary: linkOrButton, actions: [{ label, icon, href }] })
 *   DashboardKit.skeleton()   // grey blocks in the shape of the page while it loads
 *
 * There is no separate "Dashboard" header card: the greeting card is the header and holds the Action menu.
 */
export class DashboardKit {
  static hello(name = '') {
    const hour = new Date().getHours();
    return `${hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening'}${name ? `, ${name}` : ''}`;
  }

  static greeting({ name = '', line = null, extra = null, primary = null, actions = [], iconName = 'calendar' } = {}) {
    const date = new Date().toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    return h('div', { class: 'col-12 ex-greet mb-4' },
      h('span', { class: 'ex-greet-icon', 'aria-hidden': 'true' }, icon(iconName, { size: 24 })),
      h('div', { class: 'ex-greet-text' },
        h('h1', { class: 'ex-greet-title' }, DashboardKit.hello(name)),
        h('div', { class: 'ex-greet-date' }, date),
        line ? h('div', { class: 'ex-greet-line' }, line) : null,
        extra),
      h('div', { class: 'ex-greet-actions' },
        actions.length ? ActionMenu.button('Action', actions) : null,
        primary));
  }

  /** Same order as the page: greeting, 4 stat cards, a wide + narrow panel, three panels. */
  static skeleton() {
    const bone = (w, hgt = 12, extra = {}) => h('span', { class: 'ex-skeleton d-block', style: { width: w, height: `${hgt}px`, ...extra } });
    const stat = () => h('div', { class: 'col-12 col-md-6 col-xl-3' },
      h('div', { class: 'ex-skel-card ex-skel-stat' },
        h('div', { class: 'd-flex align-items-center gap-2 mb-3' }, bone('30px', 30, { borderRadius: 0 }), bone('45%')),
        bone('55%', 26, { marginBottom: '10px' }), bone('70%', 10)));
    const panel = (cols, height) => h('div', { class: cols },
      h('div', { class: 'ex-skel-card', style: { height: `${height}px` } },
        bone('35%', 16, { marginBottom: '8px' }), bone('55%', 10, { marginBottom: '24px' }),
        bone('100%', height - 110, { borderRadius: 0 })));
    return h('div', { 'aria-busy': 'true', 'aria-label': 'Loading the dashboard' },
      h('div', { class: 'ex-greet ex-skel-greet mb-4' },
        bone('48px', 48, { borderRadius: 0, flex: 'none' }),
        h('div', { class: 'flex-grow-1' }, bone('30%', 18, { marginBottom: '10px' }), bone('50%', 12))),
      h('div', { class: 'row g-3 mb-4' }, [0, 1, 2, 3].map(stat)),
      h('div', { class: 'row g-4 mb-4' }, panel('col-12 col-xl-8', 320), panel('col-12 col-xl-4', 320)),
      h('div', { class: 'row g-4 mb-4' }, panel('col-12 col-md-6 col-xl-4', 280), panel('col-12 col-md-6 col-xl-4', 280), panel('col-12 col-xl-4', 280)));
  }
}
