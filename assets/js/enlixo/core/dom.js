// Tiny DOM helper. Children that are strings become text nodes, so user data is never parsed as HTML.
export function h(tag, attrs = {}, ...children) {
  const el = document.createElement(tag);
  for (const [key, value] of Object.entries(attrs ?? {})) {
    if (value === null || value === undefined || value === false) continue;
    if (key.startsWith('on') && typeof value === 'function') el.addEventListener(key.slice(2).toLowerCase(), value);
    else if (key === 'class') el.className = value;
    else if (key === 'style' && typeof value === 'object') Object.assign(el.style, value);
    else if (key === 'dataset') Object.assign(el.dataset, value);
    else if (key === 'html') el.innerHTML = value; // only for trusted static markup (icons)
    else if (value === true) el.setAttribute(key, '');
    else el.setAttribute(key, value);
  }
  append(el, children);
  return el;
}

export function append(el, children) {
  for (const child of children.flat(Infinity)) {
    if (child === null || child === undefined || child === false) continue;
    el.append(child instanceof Node ? child : document.createTextNode(String(child)));
  }
  return el;
}

export function clear(el) {
  while (el.firstChild) el.removeChild(el.firstChild);
  return el;
}

export function debounce(fn, ms = 300) {
  let t;
  return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

export const fmt = {
  date: (d) => new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }),
  time: (t) => { const [hh, mm] = t.split(':').map(Number); return `${(hh % 12) || 12}:${String(mm).padStart(2, '0')} ${hh < 12 ? 'AM' : 'PM'}`; },
  minutes: (sec) => { if (sec < 600) { const m = Math.floor(sec / 60), s = Math.round(sec % 60); return m ? `${m}m ${String(s).padStart(2, '0')}s` : `${s}s`; } const m = Math.round(sec / 60); return m >= 60 ? `${Math.floor(m / 60)}h ${m % 60}m` : `${m} min`; },
  money: (n) => `LKR ${Number(n).toLocaleString('en-LK')}`,
  initials: (name) => name.split(/\s+/).filter(Boolean).slice(0, 2).map((s) => s[0].toUpperCase()).join(''),
};

// Inline SVG icons (static, trusted).
export const icon = {
  lock: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 1.5a5 5 0 0 0-5 5V9H6a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-9a2 2 0 0 0-2-2h-1V6.5a5 5 0 0 0-5-5Zm-3 5a3 3 0 1 1 6 0V9H9V6.5Zm3 7a2 2 0 0 1 1 3.73V19h-2v-1.77A2 2 0 0 1 12 13.5Z"/></svg>',
  crown: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 7l4.5 4L12 4l4.5 7L21 7l-2 12H5L3 7Zm2.4 14h13.2v1.5H5.4V21Z"/></svg>',
  search: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>',
  play: '<svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>',
};

export function svg(name, cls = '') {
  return h('span', { class: `ex-svg-icon ${cls}`, html: icon[name], 'aria-hidden': 'true' });
}

/** Replace an element's children (arrays and nested arrays allowed). */
export function fill(el, ...children) {
  return append(clear(el), children);
}
