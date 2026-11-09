// One HTTP client for every page. Standard envelope: { ok, data, meta } / { ok:false, error:{ code, message, details } }.
// - cancels the previous request with the same `key` (stale search results never win)
// - turns `feature_locked` into a FeatureLockedError so pages can open the upgrade modal
// - works against MockBackend in demo mode
export class ApiError extends Error {
  constructor(status, code, message, details = {}) {
    super(message);
    this.status = status;
    this.code = code;
    this.details = details;
  }
}

export class FeatureLockedError extends ApiError {}

// assets/js/enlixo/core/ApiClient.js → <site root>/api/v1 (works at the domain root and in a sub-folder like XAMPP's)
export const DEFAULT_API_BASE = new URL('../../../../api/v1', import.meta.url).href.replace(/\/$/, '');

export class ApiClient {
  #controllers = new Map();

  constructor({ baseUrl = DEFAULT_API_BASE, token = null, transport = null } = {}) {
    this.baseUrl = baseUrl;
    this.token = token;
    this.transport = transport; // optional MockBackend
  }

  get(path, params = {}, opts = {}) {
    const qs = new URLSearchParams(Object.entries(params).filter(([, v]) => v !== '' && v !== null && v !== undefined)).toString();
    return this.request('GET', qs ? `${path}?${qs}` : path, null, opts);
  }
  post(path, body, opts) { return this.request('POST', path, body, opts); }
  put(path, body, opts) { return this.request('PUT', path, body, opts); }
  delete(path, body, opts) { return this.request('DELETE', path, body, opts); }

  async request(method, path, body = null, { key = null } = {}) {
    if (key) this.#controllers.get(key)?.abort();
    const controller = new AbortController();
    if (key) this.#controllers.set(key, controller);

    try {
      const res = this.transport
        ? await this.transport.handle(method, path, body, controller.signal)
        : await this.#fetch(method, path, body, controller.signal);
      if (!res.payload?.ok) {
        const e = res.payload?.error ?? { code: 'network', message: 'Something went wrong.' };
        const Err = e.code === 'feature_locked' ? FeatureLockedError : ApiError;
        throw new Err(res.status, e.code, e.message, e.details ?? {});
      }
      return { data: res.payload.data, meta: res.payload.meta ?? {} };
    } finally {
      if (key && this.#controllers.get(key) === controller) this.#controllers.delete(key);
    }
  }

  async #fetch(method, path, body, signal) {
    let response;
    try {
      response = await fetch(this.baseUrl + path, {
        method,
        signal,
        credentials: 'same-origin', // uses the existing PHP login session
        headers: { Accept: 'application/json', 'X-Requested-With': 'enlixo', ...(body ? { 'Content-Type': 'application/json' } : {}), ...(this.token ? { Authorization: `Bearer ${this.token}` } : {}) },
        body: body ? JSON.stringify(body) : undefined,
      });
    } catch (e) {
      if (e.name === 'AbortError') throw e;
      throw new ApiError(0, 'network', 'No connection. Check your internet and try again.');
    }
    const payload = await response.json().catch(() => null);
    return { status: response.status, payload };
  }
}

export const isAbort = (e) => e?.name === 'AbortError';
