import { h } from '../core/dom.js';
import { icon } from '../core/icons.js';

/**
 * The V3 pager under every table (students.php): Previous on the left, numbered pages in the middle, Next on the right.
 * Same classes as V3 (.pagination-wrapper, .previous-button, .pagination-list > .page-item, .next-button).
 */
export class Pagination {
  /** Returns page numbers with gaps, V3 style (first, last and two around the current one): [1, '…', 4, 5, 6, 7, 8, '…', 20] */
  static pages(current, total) {
    const out = [];
    let gapBefore = false;
    let gapAfter = false;
    for (let i = 1; i <= total; i++) {
      if (i === 1 || i === total || Math.abs(current - i) <= 2) out.push(i);
      else if (i < current && !gapBefore) { gapBefore = true; out.push('…'); }
      else if (i > current && !gapAfter) { gapAfter = true; out.push('…'); }
    }
    return out;
  }

  static render({ page, pages, total }, onPage) {
    if (total === 0) return null;
    const nav = (label, target, disabled, left) => h('button', {
      type: 'button', class: `col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center gap-1 component-theme ${left ? 'previous-button' : 'next-button'}`,
      disabled, 'aria-label': `${label} page`, onclick: () => onPage(target),
    }, left ? icon('arrow-left', { size: 18 }) : null, h('span', { class: 'text-14 main-text fw-semibold' }, label), left ? null : icon('arrow-right', { size: 18 }));
    return h('div', { class: 'm-0 g-0 component-theme pagination-wrapper ex-pager' },
      h('nav', { class: 'col-12 px-2 m-0 container-fluid', 'aria-label': 'Pagination' },
        h('div', { class: 'row p-4 align-items-center justify-content-between' },
          nav('Previous', page - 1, page <= 1, true),
          h('div', { class: 'col-8 col-lg-10 custom-pagination d-flex justify-content-center' },
            h('ul', { class: 'pagination-list d-flex mb-0' }, Pagination.pages(page, pages).map((p) => (p === '…'
              ? h('li', { class: 'd-flex align-items-end h-100 sub-text px-1' }, '…')
              : h('li', { class: `page-item${p === page ? ' active' : ''}` },
                h('button', { type: 'button', class: 'py-2 px-3', 'aria-current': p === page ? 'page' : null, onclick: () => onPage(p) }, String(p))))))),
          nav('Next', page + 1, page >= pages, false))));
  }
}
