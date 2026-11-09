import { h } from '../core/dom.js';

// Skeletons that match the final layout, plus a button "busy" helper.
export class LoadingState {
  static tableRows(columns, rows = 6) {
    return Array.from({ length: rows }, (_, i) => h('tr', { 'aria-hidden': 'true' },
      columns.map((col, c) => h('td', {},
        c === 0 && col.avatar
          ? h('div', { class: 'ex-cell-user' }, h('div', { class: 'ex-skeleton ex-skeleton-circle', style: { width: '32px', height: '32px' } }), LoadingState.line(`${55 + ((i * 7) % 30)}%`))
          : LoadingState.line(`${40 + ((i * 13 + c * 17) % 45)}%`),
      ))));
  }

  static cards(count = 6, height = 180) {
    return Array.from({ length: count }, () => h('div', { class: 'ex-card', 'aria-hidden': 'true' },
      h('div', { class: 'ex-skeleton', style: { height: '96px', borderRadius: '12px 12px 0 0' } }),
      h('div', { class: 'ex-card-pad', style: { display: 'grid', gap: '10px', minHeight: `${height - 96}px` } },
        LoadingState.line('70%'), LoadingState.line('45%'), LoadingState.line('55%'))));
  }

  static list(count = 4) {
    return Array.from({ length: count }, () => h('div', { class: 'ex-session-row', 'aria-hidden': 'true' },
      h('div', { class: 'ex-skeleton', style: { width: '52px', height: '48px', borderRadius: '10px' } }),
      h('div', { style: { flex: 1, display: 'grid', gap: '8px' } }, LoadingState.line('50%'), LoadingState.line('30%'))));
  }

  static line(width = '60%') {
    return h('div', { class: 'ex-skeleton ex-skeleton-line', style: { width } });
  }

  static spinner() {
    return h('span', { class: 'ex-spinner', role: 'status', 'aria-label': 'Loading' });
  }

  /** Puts a button into a busy state while `promise` runs. */
  static async button(button, promise, busyLabel = 'Saving…') {
    const original = [...button.childNodes];
    button.disabled = true;
    button.replaceChildren(LoadingState.spinner(), busyLabel);
    try {
      return await promise;
    } finally {
      button.disabled = false;
      button.replaceChildren(...original);
    }
  }
}
