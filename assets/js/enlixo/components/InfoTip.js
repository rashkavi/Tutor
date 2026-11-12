import { h, svg } from '../core/dom.js';
import { Popover } from './Popover.js';
import { QuickGuide } from './QuickGuide.js';

/**
 * The (i) next to a hard field or a panel title. Same card as the page Quick guide (dark-blue header, text, optional
 * checklist and video), so every (i) in ENLIXO Tutor looks the same. Opens on hover and on click / Enter (phones).
 *   infoTip('The day the exam starts ...', 'Exam date')
 *   infoTip('...', 'Fee month', { points: ['...'], video: 'https://youtu.be/...' })
 * Use it only where a label is not clear on its own (docs/UI-STANDARD.md "Text").
 */
export function infoTip(text, label = 'More info', { points = [], video = '' } = {}) {
  if (!text) return null;
  const trigger = h('button', { type: 'button', class: 'ex-info-btn', 'aria-label': `${label}: ${text}` }, svg('info'));
  const pop = new Popover({ trigger, align: 'start', className: 'ex-qg-pop', hover: true, content: QuickGuide.card({ title: label, subtitle: 'Help', intro: text, points, video }) });
  return pop.el;
}
