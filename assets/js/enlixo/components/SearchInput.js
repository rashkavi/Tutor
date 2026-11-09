import { Component } from '../core/Component.js';
import { h, debounce, svg } from '../core/dom.js';

// Debounced search box with clear button. Esc clears.
export class SearchInput extends Component {
  constructor({ value = '', placeholder = 'Search…', delay = 300, onSearch }) {
    super({ placeholder, delay, onSearch });
    this.value = value;
    this.emit = debounce((v) => onSearch(v), delay);
  }

  render() {
    const input = h('input', {
      class: 'ex-input', type: 'search', value: this.value, placeholder: this.props.placeholder, 'aria-label': this.props.placeholder,
      oninput: (e) => { this.value = e.target.value; clearBtn.hidden = !this.value; this.emit(this.value.trim()); },
      onkeydown: (e) => { if (e.key === 'Escape' && this.value) { e.preventDefault(); this.set(''); this.props.onSearch(''); } },
    });
    const clearBtn = h('button', { class: 'ex-clear', type: 'button', 'aria-label': 'Clear search', hidden: !this.value, onclick: () => { this.set(''); this.props.onSearch(''); input.focus(); } }, '×');
    this.input = input;
    return h('div', { class: 'ex-search' }, svg('search', 'ex-search-icon'), input, clearBtn);
  }

  set(value) {
    this.value = value;
    if (this.input) this.input.value = value;
    this.el?.querySelector('.ex-clear')?.toggleAttribute('hidden', !value);
  }
}
