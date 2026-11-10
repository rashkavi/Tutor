import { h } from '../core/dom.js';
import { icon as solar } from '../core/icons.js';
import { ICONS } from '../core/icon-data.js';

// Older pages pass an emoji; each one maps to the Solar icon that means the same thing.
const EMOJI = { '📭': 'inbox', '📝': 'application', '🎓': 'students', '🔎': 'search', '⚠️': 'danger', '📄': 'tutes', '🗓️': 'timetable', '🎬': 'recordings', '🛡️': 'lock', '📚': 'classes' };

/**
 * The three non-happy states every list needs, in the V3 style:
 * a round soft-blue icon, a bold title and a grey line under it.
 *   EmptyState.empty({ icon: 'inbox', title: 'No birthdays this week', text: 'Staff birthdays of the next 7 days appear here.' })
 */
export class EmptyState {
  static empty({ icon = 'inbox', title = 'Nothing here yet', text = '', action = null } = {}) {
    const name = ICONS[icon] ? icon : EMOJI[icon] ?? 'inbox';
    return h('div', { class: 'ex-empty' },
      h('div', { class: 'ex-empty-icon', 'aria-hidden': 'true' }, solar(name, { size: 28 })),
      h('p', { class: 'ex-empty-title' }, title),
      text ? h('p', { class: 'ex-empty-text' }, text) : null,
      action ? h('div', { class: 'ex-empty-action' }, action) : null);
  }

  static noResults(onClear) {
    return EmptyState.empty({
      icon: 'search',
      title: 'No matches',
      text: 'Try a different search word or clear the filters.',
      action: onClear && h('button', { type: 'button', class: 'custom-outline-btn ex-btn-md px-4', onclick: onClear }, 'Clear search and filters'),
    });
  }

  static error(message, onRetry) {
    return EmptyState.empty({
      icon: 'danger',
      title: 'Couldn\'t load this',
      text: message,
      action: onRetry && h('button', { type: 'button', class: 'custom-btn ex-btn-md px-4 border-0', onclick: onRetry }, 'Try again'),
    });
  }
}
