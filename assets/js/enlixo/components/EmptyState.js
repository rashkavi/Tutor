import { h } from '../core/dom.js';

// The three non-happy states every list needs.
export class EmptyState {
  static empty({ icon = '📭', title = 'Nothing here yet', text = '', action = null } = {}) {
    return h('div', { class: 'ex-empty' }, h('div', { class: 'ex-empty-icon' }, icon), h('h3', {}, title), text && h('div', {}, text), action);
  }

  static noResults(onClear) {
    return EmptyState.empty({
      icon: '🔎',
      title: 'No matches',
      text: 'Try a different search word or remove some filters.',
      action: onClear && h('button', { class: 'ex-btn ex-btn-sm', onclick: onClear }, 'Clear search & filters'),
    });
  }

  static error(message, onRetry) {
    return EmptyState.empty({
      icon: '⚠️',
      title: 'Couldn\'t load this',
      text: message,
      action: onRetry && h('button', { class: 'ex-btn ex-btn-sm ex-btn-primary', onclick: onRetry }, 'Try again'),
    });
  }
}
