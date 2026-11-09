import { h, clear, fill, svg, toCsv, download } from '../core/dom.js';
import { isAbort, FeatureLockedError } from '../core/ApiClient.js';
import { UrlState } from '../core/UrlState.js';
import { SearchInput } from './SearchInput.js';
import { FilterBar } from './FilterBar.js';
import { Pagination } from './Pagination.js';
import { LoadingState } from './LoadingState.js';
import { EmptyState } from './EmptyState.js';
import { Popover } from './Popover.js';
import { Toast } from './Toast.js';

/**
 * The one list component, laid out like the ENLIXO V3 portal:
 *
 *   [ 40 Students ] [ 🔍 Search ......................... ] [ ⚙ ▾ ] [ Export Report ⤓ ]
 *                                                              Results Per Page [10 ▾]
 *                                          Update ticked students [- Select - ▾] [Apply]
 *   [☐] Student Name ⇅ | ... | Status | ⋮
 *
 * Search + filters + sort + pagination + loading/empty/no-results/error, state synced to the URL,
 * stale requests cancelled.
 *
 *   new DataView({
 *     api, endpoint: '/tutor/students', key: 'students', noun: ['student', 'students'],
 *     searchPlaceholder: 'Search by name, e-mail, mobile or WhatsApp',
 *     filters: [{ key: 'class', label: 'Class', options: async () => [...] }],   // shown in the filter dropdown
 *     columns: [{ key: 'name', label: 'Student Name', sortable: true, render: (row) => ... }],
 *     perPageOptions: [10, 25, 50, 100],
 *     bulk: { actions: [{ value: '1', label: 'Mark Active' }], onApply: async (ids, action) => {...} }, // ticks + "Update ticked"
 *     exportCsv: { filename: 'students.csv', columns: [['Name', (r) => ...], ...] },  // "Export Report" (all matching rows)
 *     // or: layout: 'cards', renderCard: (row) => Element
 *     // or: renderBody: (items) => Element, skeleton: () => Element   (custom layouts)
 *     empty: { icon, title, text, action },
 *   }).mount(container);
 */
export class DataView {
  constructor(opts) {
    this.o = { perPage: 10, layout: 'table', urlPrefix: '', rowKey: 'id', ...opts };
    this.o.defaultPerPage = this.o.perPage;
    const filterKeys = (this.o.filters ?? []).map((f) => f.key);
    this.url = new UrlState(['q', 'page', 'per_page', 'sort', 'dir', ...filterKeys], { prefix: this.o.urlPrefix });
    const s = this.url.read();
    if (s.per_page && this.o.perPageOptions?.includes(Number(s.per_page))) this.o.perPage = Number(s.per_page);
    this.state = { q: s.q ?? '', page: Number(s.page ?? 1), sort: s.sort ?? this.o.defaultSort ?? '', dir: s.dir ?? 'asc', filters: Object.fromEntries(filterKeys.filter((k) => s[k]).map((k) => [k, s[k]])) };
    this.result = null;
    this.loading = false;
    this.error = null;
    this.selected = new Set();
  }

  get #selectable() { return !!this.o.bulk && this.o.layout === 'table' && !this.o.renderBody; }

  mount(target) {
    this.search = new SearchInput({ value: this.state.q, placeholder: this.o.searchPlaceholder ?? 'Search…', onSearch: (q) => this.#change({ q, page: 1 }) });
    this.countEl = h('div', { class: 'ex-count-pill', 'aria-live': 'polite' });
    this.bodyEl = h('div', { class: 'ex-dv-body' });
    this.footEl = h('div');

    const row1 = h('div', { class: 'ex-toolbar' }, this.countEl);
    this.search.mount(row1);
    if (this.o.filters?.length) row1.append(this.#filterButton());
    if (this.o.toolbarExtra) row1.append(this.o.toolbarExtra);
    if (this.o.exportCsv) row1.append(h('button', { type: 'button', class: 'ex-btn ex-btn-primary ex-btn-export', onclick: (e) => this.#export(e.currentTarget) }, 'Export Report', svg('download', 'ex-btn-icon')));

    const row2 = h('div', { class: 'ex-toolbar-sub' });
    if (this.o.perPageOptions) {
      row2.append(h('label', { class: 'ex-per-page' }, 'Results Per Page',
        h('select', { class: 'ex-select', onchange: (e) => { this.o.perPage = Number(e.target.value); this.#change({ page: 1 }); } },
          this.o.perPageOptions.map((n) => h('option', { value: n, selected: n === this.o.perPage }, String(n))))));
    }
    if (this.#selectable) row2.append(this.#bulkBar());

    this.el = h('div', { class: `ex-card ex-dv ex-root ${this.o.className ?? ''}` }, row1, row2.childNodes.length ? row2 : null, this.bodyEl, this.footEl);
    target.append(this.el);
    this.load();
    return this;
  }

  reload() { return this.load(); }

  /** Current search + filters + sort, as API params (used by load and export). */
  get params() {
    return { q: this.state.q, sort: this.state.sort, dir: this.state.dir, ...this.state.filters, ...(this.o.params ?? {}) };
  }

  #filterButton() {
    this.filterBar = new FilterBar({ filters: this.o.filters, values: this.state.filters, stacked: true, onChange: (filters) => this.#change({ filters, page: 1 }) });
    const panel = h('div', { class: 'ex-filter-panel' },
      h('div', { class: 'ex-filter-panel-head' }, h('strong', {}, 'Filters'), h('button', { type: 'button', class: 'ex-link-btn', onclick: () => { this.filterBar.reset({}); this.#change({ filters: {}, page: 1 }); } }, 'Clear all')));
    this.filterBar.mount(panel);
    this.filterBadge = h('span', { class: 'ex-filter-count', hidden: true });
    const trigger = h('button', { type: 'button', class: 'ex-btn ex-btn-primary ex-btn-filter', 'aria-label': 'Filters' }, svg('sliders', 'ex-btn-icon'), this.filterBadge, svg('caret', 'ex-btn-caret'));
    return new Popover({ trigger, content: panel, className: 'ex-popover-filters' }).el;
  }

  #bulkBar() {
    const select = h('select', { class: 'ex-select', 'aria-label': `Update ticked ${this.o.noun?.[1] ?? 'rows'}` },
      h('option', { value: '' }, '- Select -'), this.o.bulk.actions.map((a) => h('option', { value: a.value }, a.label)));
    const apply = h('button', { type: 'button', class: 'ex-btn ex-btn-primary ex-btn-apply', onclick: async () => {
      if (!select.value) return Toast.error('Choose what to do with the ticked rows.');
      if (!this.selected.size) return Toast.error(`Tick at least one ${this.o.noun?.[0] ?? 'row'} first.`);
      apply.disabled = true;
      try {
        await this.o.bulk.onApply([...this.selected], select.value, this);
        this.selected.clear();
        select.value = '';
        await this.load();
      } catch (e) {
        if (e instanceof FeatureLockedError && this.o.onLocked) this.o.onLocked(e); else Toast.error(e.message);
      } finally { apply.disabled = false; }
    } }, 'Apply');
    return h('div', { class: 'ex-bulk' }, h('label', { class: 'ex-field' }, h('span', { class: 'ex-field-label' }, `Update ticked ${this.o.noun?.[1] ?? 'rows'}`), select), apply);
  }

  async #export(btn) {
    const { exportCsv: x, api, endpoint } = this.o;
    btn.disabled = true;
    try {
      const rows = [];
      for (let page = 1, pages = 1; page <= pages && page <= 100; page++) {
        const { data, meta } = await api.get(endpoint, { ...this.params, page, per_page: 100 });
        rows.push(...data);
        pages = meta.pages ?? 1;
      }
      download(x.filename ?? 'report.csv', toCsv([x.columns.map(([label]) => label), ...rows.map((r) => x.columns.map(([, get]) => get(r)))]));
    } catch (e) {
      if (e instanceof FeatureLockedError && this.o.onLocked) this.o.onLocked(e); else Toast.error(e.message);
    } finally { btn.disabled = false; }
  }

  #change(patch) {
    this.state = { ...this.state, ...patch };
    this.selected.clear();
    this.url.write({ q: this.state.q, page: this.state.page, per_page: this.o.perPage === this.o.defaultPerPage ? '' : this.o.perPage, sort: this.state.sort, dir: this.state.sort ? this.state.dir : '', ...this.state.filters, ...Object.fromEntries((this.o.filters ?? []).filter((f) => !(f.key in this.state.filters)).map((f) => [f.key, ''])) });
    this.load();
  }

  #clearAll = () => {
    this.search.set('');
    this.filterBar?.reset({});
    this.#change({ q: '', filters: {}, page: 1 });
  };

  get #hasCriteria() {
    return this.state.q !== '' || Object.keys(this.state.filters).length > 0;
  }

  async load() {
    this.loading = true;
    this.error = null;
    this.#render();
    try {
      const { data, meta } = await this.o.api.get(this.o.endpoint, { ...this.params, page: this.state.page, per_page: this.o.perPage }, { key: this.o.key ?? this.o.endpoint });
      this.result = { items: data, meta };
      if (meta.pages && this.state.page > meta.pages) return this.#change({ page: meta.pages });
    } catch (e) {
      if (isAbort(e)) return;
      if (e instanceof FeatureLockedError && this.o.onLocked) this.o.onLocked(e);
      this.error = e;
    }
    this.loading = false;
    this.#render();
  }

  #render() {
    const first = this.loading && !this.result;
    this.el.classList.toggle('ex-is-refreshing', this.loading && !!this.result);
    this.el.setAttribute('aria-busy', String(this.loading));

    const meta = this.result?.meta;
    const n = Object.keys(this.state.filters).length;
    if (this.filterBadge) { this.filterBadge.hidden = !n; this.filterBadge.textContent = String(n); }
    fill(this.countEl, meta ? `${meta.total} ${this.#cap(meta.total === 1 ? this.o.noun?.[0] ?? 'result' : this.o.noun?.[1] ?? 'results')}` : '…');
    this.countEl.classList.toggle('ex-has-clear', this.#hasCriteria);
    if (this.#hasCriteria && meta) this.countEl.append(h('button', { class: 'ex-link-btn', onclick: this.#clearAll, title: 'Clear search and filters' }, 'Clear'));

    clear(this.footEl);
    if (first) return this.#renderSkeleton();
    if (this.error) return fill(this.bodyEl, EmptyState.error(this.error.message, () => this.load()));
    if (!this.result.items.length) {
      return fill(this.bodyEl, this.#hasCriteria ? EmptyState.noResults(this.#clearAll) : EmptyState.empty(this.o.empty));
    }
    fill(this.bodyEl, this.o.renderBody ? this.o.renderBody(this.result.items) : this.o.layout === 'cards' ? this.#cards() : this.#table());
    const pager = Pagination.render({ page: this.state.page, pages: meta.pages, total: meta.total, perPage: meta.per_page }, (page) => this.#change({ page }));
    if (pager) this.footEl.append(pager);
  }

  #cap(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

  #renderSkeleton() {
    if (this.o.skeleton) {
      fill(this.bodyEl, this.o.skeleton());
    } else if (this.o.layout === 'cards') {
      fill(this.bodyEl, h('div', { class: 'ex-grid ex-grid-cards ex-card-pad' }, LoadingState.cards(this.o.perPage > 6 ? 6 : this.o.perPage)));
    } else {
      const cols = this.#selectable ? [{ key: '_tick', width: '48px' }, ...this.o.columns] : this.o.columns;
      fill(this.bodyEl, h('div', { class: 'ex-table-wrap' }, h('table', { class: 'ex-table' }, this.#thead(), h('tbody', {}, LoadingState.tableRows(cols, Math.min(this.o.perPage, 8))))));
    }
  }

  #thead() {
    const items = this.result?.items ?? [];
    let tickAll = null;
    if (this.#selectable) {
      const all = items.length > 0 && items.every((r) => this.selected.has(r[this.o.rowKey]));
      const some = items.some((r) => this.selected.has(r[this.o.rowKey]));
      tickAll = h('th', { class: 'ex-tick' }, h('input', { type: 'checkbox', class: 'ex-check', 'aria-label': 'Tick all on this page', checked: all, disabled: !items.length, onchange: (e) => {
        items.forEach((r) => (e.target.checked ? this.selected.add(r[this.o.rowKey]) : this.selected.delete(r[this.o.rowKey])));
        this.#render();
      } }));
      queueMicrotask(() => { const cb = tickAll.querySelector('input'); if (cb) cb.indeterminate = some && !all; });
    }
    return h('thead', {}, h('tr', {}, tickAll, this.o.columns.map((c) => {
      const active = this.state.sort === c.key;
      return h('th', {
        class: `${c.sortable ? 'ex-sortable' : ''} ${active ? `ex-sorted-${this.state.dir}` : ''}`, style: c.width ? { width: c.width } : null,
        'aria-sort': active ? (this.state.dir === 'asc' ? 'ascending' : 'descending') : null,
        tabindex: c.sortable ? '0' : null,
        onclick: c.sortable ? () => this.#change({ sort: c.key, dir: active && this.state.dir === 'asc' ? 'desc' : 'asc', page: 1 }) : null,
        onkeydown: c.sortable ? (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); e.currentTarget.click(); } } : null,
      }, h('span', { class: 'ex-th' }, c.label ?? '', c.sortable ? svg('sort', 'ex-sort-icon') : null));
    })));
  }

  #table() {
    return h('div', { class: 'ex-table-wrap' }, h('table', { class: 'ex-table' }, this.#thead(),
      h('tbody', {}, this.result.items.map((row) => {
        const id = row[this.o.rowKey];
        const ticked = this.selected.has(id);
        return h('tr', { class: ticked ? 'ex-ticked' : '' },
          this.#selectable ? h('td', { class: 'ex-tick' }, h('input', { type: 'checkbox', class: 'ex-check', 'aria-label': 'Tick row', checked: ticked, onchange: (e) => {
            e.target.checked ? this.selected.add(id) : this.selected.delete(id);
            this.#render();
          } })) : null,
          this.o.columns.map((c) => h('td', { class: c.wrap ? 'ex-wrap' : null }, c.render ? c.render(row) : row[c.key])));
      }))));
  }

  #cards() {
    return h('div', { class: 'ex-grid ex-grid-cards ex-card-pad' }, this.result.items.map((row) => this.o.renderCard(row)));
  }
}
