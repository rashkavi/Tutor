import { h, svg } from '../core/dom.js';
import { Popover } from './Popover.js';

/** The (i) icon next to a label. Hover shows the native tooltip; click / Enter opens a readable bubble (works on phones). */
export function infoTip(text, label = 'More info') {
  if (!text) return null;
  const trigger = h('button', { type: 'button', class: 'ex-info-btn', 'aria-label': `${label}: ${text}`, title: text }, svg('info'));
  const pop = new Popover({ trigger, align: 'start', className: 'ex-tip', content: h('div', { class: 'ex-tip-body', role: 'note' }, text) });
  return pop.el;
}
