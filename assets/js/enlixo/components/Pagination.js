import { h } from '../core/dom.js';

export class Pagination {
  /** Returns page numbers with gaps: [1, '…', 4, 5, 6, '…', 20] */
  static pages(current, total) {
    const set = new Set([1, total, current - 1, current, current + 1].filter((p) => p >= 1 && p <= total));
    const sorted = [...set].sort((a, b) => a - b);
    const out = [];
    sorted.forEach((p, i) => { if (i && p - sorted[i - 1] > 1) out.push('…'); out.push(p); });
    return out;
  }

  static render({ page, pages, total, perPage }, onPage) {
    if (total === 0) return null;
    const from = (page - 1) * perPage + 1;
    const to = Math.min(total, page * perPage);
    return h('nav', { class: 'ex-pagination', 'aria-label': 'Pagination' },
      h('span', { class: 'ex-info' }, `Showing ${from}–${to} of ${total}`),
      h('button', { class: 'ex-page-btn', disabled: page <= 1, 'aria-label': 'Previous page', onclick: () => onPage(page - 1) }, '‹'),
      Pagination.pages(page, pages).map((p) => p === '…'
        ? h('span', { class: 'ex-muted' }, '…')
        : h('button', { class: `ex-page-btn ${p === page ? 'ex-active' : ''}`, 'aria-current': p === page ? 'page' : null, onclick: () => onPage(p) }, String(p))),
      h('button', { class: 'ex-page-btn', disabled: page >= pages, 'aria-label': 'Next page', onclick: () => onPage(page + 1) }, '›'));
  }
}
