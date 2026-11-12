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
    // V3 markup (emptyStateHtml() in the V3 functions.js): .empty-state > .empty-state-icon + -title + -text
    return h('div', { class: 'empty-state' },
      h('div', { class: 'empty-state-icon', 'aria-hidden': 'true' }, solar(name, { size: 28 })),
      h('div', { class: 'empty-state-title' }, title),
      text ? h('div', { class: 'empty-state-text' }, text) : null,
      action ? h('div', { class: 'mt-3' }, action) : null);
  }

  static noResults(onClear) {
    return EmptyState.empty({
      icon: 'search',
      title: 'No matching results',
      text: 'Try changing the search or filters.',
      action: onClear && h('button', { type: 'button', class: 'custom-btn-outline px-4', onclick: onClear }, 'Clear search and filters'),
    });
  }

  static error(message, onRetry) {
    return EmptyState.empty({
      icon: 'danger',
      title: 'Couldn\'t load this',
      text: message,
      action: onRetry && h('button', { type: 'button', class: 'custom-btn px-4', onclick: onRetry }, 'Try again'),
    });
  }
}
