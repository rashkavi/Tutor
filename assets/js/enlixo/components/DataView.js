import { h, clear, fill } from '../core/dom.js';
import { isAbort, FeatureLockedError } from '../core/ApiClient.js';
import { UrlState } from '../core/UrlState.js';
import { SearchInput } from './SearchInput.js';
import { FilterBar } from './FilterBar.js';
import { Pagination } from './Pagination.js';
import { LoadingState } from './LoadingState.js';
import { EmptyState } from './EmptyState.js';

/**
 * The one list component: search + filters + sort + pagination + loading/empty/no-results/error,
 * state synced to the URL, stale requests cancelled.
 *
 *   new DataView({
 *     api, endpoint: '/tutor/students', key: 'students',
 *     searchPlaceholder: 'Search name, ID or phone',
 *     filters: [{ key: 'class', label: 'Class', options: async () => [...] }],
 *     columns: [{ key: 'name', label: 'Student', sortable: true, avatar: true, render: (row) => ... }],
 *     // or: layout: 'cards', renderCard: (row) => Element
 *     // or: renderBody: (items) => Element, skeleton: () => Element   (custom layouts, e.g. grouped by month)
 *     empty: { icon, title, text, action },
 *   }).mount(container);
 */
export class DataView {
  constructor(opts) {
    this.o = { perPage: 10, layout: 'table', urlPrefix: '', ...opts };
    this.o.defaultPerPage = this.o.perPage;
    const filterKeys = (this.o.filters ?? []).map((f) => f.key);
    this.url = new UrlState(['q', 'page', 'per_page', 'sort', 'dir', ...filterKeys], { prefix: this.o.urlPrefix });
    const s = this.url.read();
    if (s.per_page && this.o.perPageOptions?.includes(Number(s.per_page))) this.o.perPage = Number(s.per_page);
    this.state = { q: s.q ?? '', page: Number(s.page ?? 1), sort: s.sort ?? this.o.defaultSort ?? '', dir: s.dir ?? 'asc', filters: Object.fromEntries(filterKeys.filter((k) => s[k]).map((k) => [k, s[k]])) };
    this.result = null;
    this.loading = false;
    this.error = null;
  }

  mount(target) {
    this.search = new SearchInput({ value: this.state.q, placeholder: this.o.searchPlaceholder ?? 'Search…', onSearch: (q) => this.#change({ q, page: 1 }) });
    this.filterBar = this.o.filters?.length ? new FilterBar({ filters: this.o.filters, values: this.state.filters, onChange: (filters) => this.#change({ filters, page: 1 }) }) : null;
    this.countEl = h('div', { class: 'ex-result-count', 'aria-live': 'polite' });
    this.bodyEl = h('div', { class: 'ex-dv-body' });
    this.footEl = h('div');

    const toolbar = h('div', { class: 'ex-toolbar' });
    this.search.mount(toolbar);
    this.filterBar?.mount(toolbar);
    if (this.o.toolbarExtra) toolbar.append(this.o.toolbarExtra);
    toolbar.append(this.countEl);

    this.el = h('div', { class: `ex-card ex-dv ex-root ${this.o.className ?? ''}` }, toolbar, this.bodyEl, this.footEl);
    target.append(this.el);
    this.load();
    return this;
  }

  reload() { return this.load(); }

  #change(patch) {
    this.state = { ...this.state, ...patch };
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
      const { data, meta } = await this.o.api.get(this.o.endpoint, {
        q: this.state.q, page: this.state.page, per_page: this.o.perPage, sort: this.state.sort, dir: this.state.dir, ...this.state.filters, ...(this.o.params ?? {}),
      }, { key: this.o.key ?? this.o.endpoint });
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
    fill(this.countEl, 
      meta ? `${meta.total} ${meta.total === 1 ? this.o.noun?.[0] ?? 'result' : this.o.noun?.[1] ?? 'results'}` : '',
      this.#hasCriteria ? h('button', { class: 'ex-link-btn', onclick: this.#clearAll }, 'Clear') : '',
    );

    clear(this.footEl);
    if (first) return this.#renderSkeleton();
    if (this.error) return fill(this.bodyEl, EmptyState.error(this.error.message, () => this.load()));
    if (!this.result.items.length) {
      return fill(this.bodyEl, this.#hasCriteria ? EmptyState.noResults(this.#clearAll) : EmptyState.empty(this.o.empty));
    }
    fill(this.bodyEl, this.o.renderBody ? this.o.renderBody(this.result.items) : this.o.layout === 'cards' ? this.#cards() : this.#table());
    const pager = Pagination.render({ page: this.state.page, pages: meta.pages, total: meta.total, perPage: meta.per_page }, (page) => this.#change({ page }));
    if (pager) {
      if (this.o.perPageOptions) {
        pager.append(h('label', { class: 'ex-per-page ex-small ex-muted' }, 'Per page',
          h('select', { class: 'ex-select', onchange: (e) => { this.o.perPage = Number(e.target.value); this.#change({ page: 1, per_page: this.o.perPage }); } },
            this.o.perPageOptions.map((n) => h('option', { value: n, selected: n === this.o.perPage }, String(n))))));
      }
      this.footEl.append(pager);
    }
  }

  #renderSkeleton() {
    if (this.o.skeleton) {
      fill(this.bodyEl, this.o.skeleton());
    } else if (this.o.layout === 'cards') {
      fill(this.bodyEl, h('div', { class: 'ex-grid ex-grid-cards ex-card-pad' }, LoadingState.cards(this.o.perPage > 6 ? 6 : this.o.perPage)));
    } else {
      fill(this.bodyEl, h('div', { class: 'ex-table-wrap' }, h('table', { class: 'ex-table' }, this.#thead(), h('tbody', {}, LoadingState.tableRows(this.o.columns, Math.min(this.o.perPage, 8))))));
    }
  }

  #thead() {
    return h('thead', {}, h('tr', {}, this.o.columns.map((c) => {
      const active = this.state.sort === c.key;
      const arrow = active ? (this.state.dir === 'asc' ? ' ↑' : ' ↓') : '';
      return h('th', {
        class: c.sortable ? 'ex-sortable' : '', style: c.width ? { width: c.width } : null,
        'aria-sort': active ? (this.state.dir === 'asc' ? 'ascending' : 'descending') : null,
        onclick: c.sortable ? () => this.#change({ sort: c.key, dir: active && this.state.dir === 'asc' ? 'desc' : 'asc', page: 1 }) : null,
      }, c.label + arrow);
    })));
  }

  #table() {
    return h('div', { class: 'ex-table-wrap' }, h('table', { class: 'ex-table' }, this.#thead(),
      h('tbody', {}, this.result.items.map((row) => h('tr', {}, this.o.columns.map((c) => h('td', {}, c.render ? c.render(row) : row[c.key])))))));
  }

  #cards() {
    return h('div', { class: 'ex-grid ex-grid-cards ex-card-pad' }, this.result.items.map((row) => this.o.renderCard(row)));
  }
}
