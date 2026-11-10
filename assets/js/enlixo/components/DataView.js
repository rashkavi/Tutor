import { h, clear, fill, svg, toCsv, download } from '../core/dom.js';
import { isAbort, FeatureLockedError } from '../core/ApiClient.js';
import { UrlState } from '../core/UrlState.js';
import { SearchInput } from './SearchInput.js';
import { FilterBar } from './FilterBar.js';
import { Pagination } from './Pagination.js';
import { LoadingState } from './LoadingState.js';
import { EmptyState } from './EmptyState.js';
import { icon } from '../core/icons.js';
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
    // Same structure and classes as V3's table_toolbar() (includes/components/table-toolbar.php):
    //   [ N Things ] [ search ............ ] [ filter ] [ Export Report ]
    //   (filter row: a label over each select + the refresh "clear" button)       [ Results Per Page ]
    //                                                    [ Update ticked ... ] [ Apply ]
    this.search = new SearchInput({ value: this.state.q, placeholder: this.o.searchPlaceholder ?? 'What do you want to search...', onSearch: (q) => this.#change({ q, page: 1 }) });
    this.countEl = h('span', { id: null }, '…');
    this.bodyEl = h('div', { class: 'ex-dv-body' });
    this.footEl = h('div');
    const hasFilters = !!this.o.filters?.length;
    const hasExport = !!this.o.exportCsv;
    const extra = this.o.toolbarExtra ? 1 : 0;

    const top = h('div', { class: 'row p-0 gy-3 gx-3 align-content-center justify-content-end' },
      h('div', { class: 'col-4 col-md-2 d-flex flex-column justify-content-start' },
        h('div', { class: 'd-flex custom-div align-items-center justify-content-center px-2', 'aria-live': 'polite' },
          h('span', { class: 'text-14 dark-theme fw-medium text-nowrap' }, this.countEl))));
    const searchCol = h('div', { class: `col-8 col-md-${hasExport ? 8 : 10} col-lg-${(hasFilters ? 9 : 10) - (hasExport ? 2 : 0) - extra * 2} d-flex flex-column` });
    this.search.mount(searchCol);
    top.append(searchCol);
    if (hasFilters) top.append(h('div', { class: 'col-12 col-md-2 col-lg-1 d-flex flex-column justify-content-end' }, this.#filterButton()));
    if (this.o.toolbarExtra) top.append(h('div', { class: 'col-12 col-md-2 col-lg-2 d-flex flex-column justify-content-end' }, this.o.toolbarExtra));
    if (hasExport) {
      top.append(h('div', { class: 'col-12 col-md-2 col-lg-2 d-flex flex-column justify-content-end' },
        h('button', { type: 'button', class: 'custom-btn w-100 d-flex align-items-center justify-content-center gap-2', onclick: (e) => this.#export(e.currentTarget) },
          h('span', { class: 'text-14 fw-semibold' }, 'Export Report'), icon('download', { size: 22 }))));
    }
    if (this.filterRow) top.append(this.filterRow);
    if (this.o.perPageOptions) {
      top.append(h('div', { class: 'col-6 d-none d-lg-block' }),
        h('div', { class: 'col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end ms-auto' },
          h('label', { class: 'pe-2 sub-text text-nowrap', for: `${this.uid}-per` }, 'Results Per Page'),
          h('select', { id: `${this.uid}-per`, class: 'form-select custom-select w-25', style: { minWidth: '80px' }, onchange: (e) => { this.o.perPage = Number(e.target.value); this.#change({ page: 1 }); } },
            this.o.perPageOptions.map((n) => h('option', { value: n, selected: n === this.o.perPage }, String(n))))));
    }

    this.el = h('div', { class: `component-theme p-4 ex-dv ${this.o.className ?? ''}` },
      h('div', { class: 'row p-0 m-0 g-0 align-content-center' }, h('div', { class: 'col-12 mb-4' }, top)),
      this.#selectable ? h('div', { class: 'col-12 mb-4' }, this.#bulkBar()) : null,
      this.bodyEl, this.footEl);
    target.append(this.el);
    this.load();
    return this;
  }

  get uid() { return this._uid ??= `dv${Math.random().toString(36).slice(2, 7)}`; }

  reload() { return this.load(); }

  /** Set the filters from outside the list (e.g. a stat card above it). */
  setFilters(filters) {
    this.filterBar?.reset(filters);
    this.#change({ filters, page: 1 });
  }

  /** Current search + filters + sort, as API params (used by load and export). */
  get params() {
    return { q: this.state.q, sort: this.state.sort, dir: this.state.dir, ...this.state.filters, ...(this.o.params ?? {}) };
  }

  /**
   * V3 filters: an outlined toggle button next to the search. It slides a row of labelled
   * dropdowns open below the search row (open at first when a filter is already set).
   */
  #filterButton() {
    this.filterBar = new FilterBar({ filters: this.o.filters, values: this.state.filters, row: true, onChange: (filters) => this.#change({ filters, page: 1 }) });
    this.filterClear = h('button', { type: 'button', class: 'custom-btn-outline tf-clear d-flex align-items-center justify-content-center', title: 'Clear filters', 'aria-label': 'Clear filters', onclick: () => { this.filterBar.reset({}); this.#change({ filters: {}, page: 1 }); } }, icon('refresh', { size: 22 }));
    const fields = h('div', { class: 'row justify-content-end align-items-end gy-3 gx-3' });
    this.filterBar.mount(fields);
    fields.append(h('div', { class: 'col-auto d-flex flex-column justify-content-end' }, this.filterClear));
    this.filterRow = h('div', { class: 'col-12 ex-v3-filters', id: `${this.uid}-filters` }, fields);
    this.filterBadge = h('span', { class: 'tf-badge', hidden: true, title: 'Filters applied' }, '0');
    const toggle = h('button', { type: 'button', class: 'custom-btn w-100 position-relative ex-filter-btn', title: 'Filters', 'aria-label': 'Filters', 'aria-expanded': 'false', 'aria-controls': this.filterRow.id },
      icon('sliders', { size: 24 }), this.filterBadge);
    const setOpen = (open) => {
      toggle.setAttribute('aria-expanded', String(open));
      this.filterRow.hidden = !open;
    };
    toggle.onclick = () => setOpen(toggle.getAttribute('aria-expanded') !== 'true');
    setOpen(Object.keys(this.state.filters).length > 0);
    return toggle;
  }

  #bulkBar() {
    const select = h('select', { class: 'form-select custom-select', id: `${this.uid}-bulk`, 'aria-label': `Update ticked ${this.o.noun?.[1] ?? 'rows'}` },
      h('option', { value: '' }, '- Select -'), this.o.bulk.actions.map((a) => h('option', { value: a.value }, a.label)));
    const apply = h('button', { type: 'button', class: 'custom-btn w-100', onclick: async () => {
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
    return h('div', { class: 'row justify-content-end align-items-end gy-3 gx-3' },
      h('div', { class: 'col-12 col-md-4 col-lg-3 d-flex flex-column justify-content-end' },
        h('label', { class: 'text-12 sub-text mb-1', for: `${this.uid}-bulk` }, `Update ticked ${this.o.noun?.[1] ?? 'rows'}`), select),
      h('div', { class: 'col-12 col-md-2 col-lg-2 d-flex flex-column justify-content-end' }, apply));
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
    if (this.filterClear) this.filterClear.disabled = !n;
    fill(this.countEl, meta ? `${meta.total} ${this.#cap(meta.total === 1 ? this.o.noun?.[0] ?? 'result' : this.o.noun?.[1] ?? 'results')}` : '…');

    clear(this.footEl);
    if (first) return this.#renderSkeleton();
    if (this.error) return fill(this.bodyEl, EmptyState.error(this.error.message, () => this.load()));
    const empty = !this.result.items.length ? (this.#hasCriteria ? EmptyState.noResults(this.#clearAll) : EmptyState.empty(this.o.empty)) : null;
    if (empty && (this.o.renderBody || this.o.layout === 'cards')) return fill(this.bodyEl, h('div', { class: 'bl-solid' }, empty));
    const pager = empty ? null : Pagination.render({ page: this.state.page, pages: meta.pages, total: meta.total, perPage: meta.per_page }, (page) => this.#change({ page }));
    if (this.o.renderBody || this.o.layout === 'cards') {
      fill(this.bodyEl, this.o.renderBody ? this.o.renderBody(this.result.items) : this.#cards());
      if (pager) this.footEl.append(pager);
      return;
    }
    // V3 table: the empty state is a row inside the table, the pager sits under it inside the same wrapper
    fill(this.bodyEl, this.#table(empty, pager));
  }

  #cap(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

  #renderSkeleton() {
    if (this.o.skeleton) {
      fill(this.bodyEl, this.o.skeleton());
    } else if (this.o.layout === 'cards') {
      fill(this.bodyEl, h('div', { class: 'ex-grid ex-grid-cards' }, LoadingState.cards(this.o.perPage > 6 ? 6 : this.o.perPage)));
    } else {
      const cols = this.#selectable ? [{ key: '_tick', width: '48px' }, ...this.o.columns] : this.o.columns;
      fill(this.bodyEl, h('div', { class: 'table-responsive-wrapper' }, h('div', { class: 'table-responsive' }, h('table', { class: 'table bl-solid m-0 ex-v3-table' }, this.#thead(), h('tbody', {}, LoadingState.tableRows(cols, Math.min(this.o.perPage, 8)))))));
    }
  }

  #thead() {
    const items = this.result?.items ?? [];
    let tickAll = null;
    if (this.#selectable) {
      const all = items.length > 0 && items.every((r) => this.selected.has(r[this.o.rowKey]));
      const some = items.some((r) => this.selected.has(r[this.o.rowKey]));
      tickAll = h('th', { class: 'ps-4 py-3 bg-table-header ex-tick' }, h('input', { type: 'checkbox', class: 'form-check-input mt-0', 'aria-label': 'Tick all on this page', checked: all, disabled: !items.length, onchange: (e) => {
        items.forEach((r) => (e.target.checked ? this.selected.add(r[this.o.rowKey]) : this.selected.delete(r[this.o.rowKey])));
        this.#render();
      } }));
      queueMicrotask(() => { const cb = tickAll.querySelector('input'); if (cb) cb.indeterminate = some && !all; });
    }
    return h('thead', {}, h('tr', {}, tickAll, this.o.columns.map((c, i) => {
      const active = this.state.sort === c.key;
      return h('th', {
        scope: 'col',
        class: `${i === 0 && !tickAll ? 'px-4' : ''} py-3 bg-table-header text-14 main-text fw-medium align-middle ${c.sortable ? 'ex-sortable' : ''} ${active ? `ex-sorted-${this.state.dir}` : ''}`, style: c.width ? { width: c.width } : null,
        'aria-sort': active ? (this.state.dir === 'asc' ? 'ascending' : 'descending') : null,
        tabindex: c.sortable ? '0' : null,
        onclick: c.sortable ? () => this.#change({ sort: c.key, dir: active && this.state.dir === 'asc' ? 'desc' : 'asc', page: 1 }) : null,
        onkeydown: c.sortable ? (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); e.currentTarget.click(); } } : null,
      }, h('span', { class: 'ex-th' }, c.label ?? '', c.sortable ? svg('sort', 'ex-sort-icon') : null));
    })));
  }

  #table(empty = null, pager = null) {
    const cols = this.o.columns.length + (this.#selectable ? 1 : 0);
    const rows = empty
      ? [h('tr', {}, h('td', { colspan: cols, class: 'p-0' }, empty))]
      : this.result.items.map((row) => {
        const id = row[this.o.rowKey];
        const ticked = this.selected.has(id);
        return h('tr', { class: ticked ? 'ex-ticked' : '' },
          this.#selectable ? h('td', { class: 'ps-4 py-3 align-middle ex-tick' }, h('input', { type: 'checkbox', class: 'form-check-input mt-0', 'aria-label': 'Tick row', checked: ticked, onchange: (e) => {
            e.target.checked ? this.selected.add(id) : this.selected.delete(id);
            this.#render();
          } })) : null,
          this.o.columns.map((c, i) => h('td', { class: `${i === 0 && !this.#selectable ? 'px-4' : ''} py-3 align-middle text-14 main-text ${c.wrap ? 'cell-wrap' : ''}` }, c.render ? c.render(row) : row[c.key])));
      });
    return h('div', { class: 'table-responsive-wrapper' },
      h('div', { class: 'table-responsive' }, h('table', { class: 'table bl-solid m-0 ex-v3-table' }, this.#thead(), h('tbody', {}, rows))),
      pager ? (pager.classList.add('bl-solid-lrb'), pager) : null);
  }

  #cards() {
    return h('div', { class: 'ex-grid ex-grid-cards' }, this.result.items.map((row) => this.o.renderCard(row)));
  }
}
