// Keeps list state (search, filters, page, sort) in the URL so refresh/back/share keep the same view.
export class UrlState {
  constructor(keys, { prefix = '' } = {}) {
    this.keys = keys;
    this.prefix = prefix;
  }

  read() {
    const params = new URLSearchParams(location.search);
    const out = {};
    for (const key of this.keys) {
      const v = params.get(this.prefix + key);
      if (v !== null) out[key] = v;
    }
    return out;
  }

  write(state) {
    const params = new URLSearchParams(location.search);
    for (const key of this.keys) {
      const v = state[key];
      if (v === undefined || v === null || v === '' || (key === 'page' && Number(v) === 1)) params.delete(this.prefix + key);
      else params.set(this.prefix + key, v);
    }
    const qs = params.toString();
    history.replaceState(null, '', qs ? `${location.pathname}?${qs}` : location.pathname);
  }
}
