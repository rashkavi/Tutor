import { h, svg } from '../core/dom.js';

/**
 * Package locks in the UI. Locked features stay visible with a premium lock badge;
 * clicking them opens the Upgrade page (tutor/upgrade.php) for that feature.
 * (The API enforces the same rules: this is presentation only.)
 *
 *   const gate = await FeatureGate.load(api);
 *   gate.badge('comms.whatsapp')                     -> <span class="lock-badge">GROW</span> or null
 *   gate.wrap('reports.class_advanced', previewEl)   -> blurred preview + unlock overlay when locked
 *   button.onclick = gate.guard('payments.online_card', () => openGateway())
 */
export class FeatureGate {
  constructor(access, { upgradeUrl = 'upgrade' } = {}) {
    this.access = access;
    this.upgradeUrl = upgradeUrl;
    this.features = new Map(access.features.map((f) => [f.key, f]));
    this.plans = access.plans ?? {};
  }

  static async load(api, opts) {
    const { data } = await api.get('/me/access');
    return new FeatureGate(data, opts);
  }

  get plan() { return this.access.plan; }
  get permissions() { return this.access.permissions ?? []; }

  has(key) { return this.features.get(key)?.unlocked === true; }
  can(permission) { return this.permissions.includes(permission); }

  label(key) {
    const f = this.features.get(key);
    if (!f || f.unlocked) return null;
    return f.required_plan ? (this.plans[f.required_plan]?.name ?? f.required_plan.toUpperCase()) : 'ADD-ON';
  }

  badge(key, { compact = false } = {}) {
    const label = this.label(key);
    if (!label) return null;
    return h('span', { class: 'ex-lock-badge', title: `Available on ${label}` }, svg(label === 'PRO' ? 'crown' : 'lock'), compact ? null : label);
  }

  guard(key, fn) {
    return (...args) => (this.has(key) ? fn(...args) : this.openUpgrade(key));
  }

  wrap(key, content, { title = null, text = null } = {}) {
    if (this.has(key)) return content;
    const f = this.features.get(key);
    const label = this.label(key);
    return h('div', { class: 'ex-gated' },
      h('div', { class: 'ex-gated-content', 'aria-hidden': 'true', inert: true }, content),
      h('div', { class: 'ex-gated-overlay' }, h('div', { class: 'ex-gated-cta' },
        h('div', { class: 'ex-lock-orb' }, svg('lock')),
        h('h3', {}, title ?? f?.name ?? 'Premium feature'),
        h('div', { class: 'ex-muted ex-small' }, text ?? f?.description ?? ''),
        h('button', { class: 'ex-btn ex-btn-premium', onclick: () => this.openUpgrade(key) }, svg('crown'), label === 'ADD-ON' ? 'Get this add-on' : `Unlock with ${label}`))));
  }

  /**
   * Opens the Upgrade page for the feature (a page, never a pop-up): what it does, the plan that has it, the price,
   * "Try free for 7 days" and "Upgrade". Also takes error.details of a FeatureLockedError (feature or limit).
   * @param {string|{feature?:string, limit?:string}} keyOrDetails
   */
  openUpgrade(keyOrDetails) {
    // From an API error (an object): the page already shows the upgrade card (ApiClient → enlixo:upgrade), so what
    // the tutor typed is not lost by leaving the page. From a click on a locked item (a key): go to the Upgrade page.
    if (typeof keyOrDetails !== 'string') {
      document.querySelector('.ex-bill-banner.is-hit')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }
    const d = { feature: keyOrDetails };
    const q = d.limit ? `limit=${encodeURIComponent(d.limit)}` : `feature=${encodeURIComponent(d.feature ?? '')}`;
    location.href = `${this.upgradeUrl}?${q}`;
  }
}
