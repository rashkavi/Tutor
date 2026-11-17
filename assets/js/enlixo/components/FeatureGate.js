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

  /** The lock badge. Hover (or keyboard focus) opens a small card: what the feature does, the plan that has it, Upgrade. */
  badge(key, { compact = false } = {}) {
    const label = this.label(key);
    if (!label) return null;
    const f = this.features.get(key);
    const el = h('span', { class: 'ex-lock-badge', tabindex: '0', 'aria-label': `${f?.name ?? 'This feature'}: available on ${label}` },
      svg(label === 'PRO' ? 'crown' : 'lock'), compact ? null : label);
    const show = () => LockTip.show(el, { name: f?.name ?? 'Premium feature', text: f?.description ?? '', plan: label, onUpgrade: () => this.openUpgrade(key) });
    el.addEventListener('mouseenter', show);
    el.addEventListener('focus', show);
    el.addEventListener('mouseleave', () => LockTip.hideSoon());
    el.addEventListener('blur', () => LockTip.hideSoon());
    return el;
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

/** One hover card for every lock badge on the page, placed against the window (menus and cards may clip their contents). */
const LockTip = {
  el: null,
  timer: null,
  build() {
    if (this.el) return this.el;
    this.el = h('div', { class: 'ex-lock-tip', role: 'tooltip' });
    this.el.addEventListener('mouseenter', () => clearTimeout(this.timer));
    this.el.addEventListener('mouseleave', () => this.hideSoon());
    this.el.addEventListener('focusout', (e) => { if (!this.el.contains(e.relatedTarget)) this.hideSoon(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') this.hide(); });
    window.addEventListener('scroll', () => this.hide(), { passive: true, capture: true });
    document.body.append(this.el);
    return this.el;
  },
  show(anchor, { name, text, plan, onUpgrade }) {
    clearTimeout(this.timer);
    const tip = this.build();
    const addon = plan === 'ADD-ON';
    tip.replaceChildren(
      h('div', { class: 'ex-lock-tip-plan' }, svg('crown'), addon ? 'Add-on' : `Available on ${plan}`),
      h('div', { class: 'ex-lock-tip-title' }, name),
      text ? h('div', { class: 'ex-lock-tip-text' }, text) : null,
      h('button', { type: 'button', class: 'custom-btn ex-lock-tip-btn', onclick: (e) => { e.preventDefault(); e.stopPropagation(); this.hide(); onUpgrade(); } },
        addon ? 'Get this add-on' : `Upgrade to ${plan}`));
    tip.classList.add('is-open');
    const r = anchor.getBoundingClientRect();
    const w = tip.offsetWidth;
    const hgt = tip.offsetHeight;
    const left = Math.max(12, Math.min(window.innerWidth - w - 12, r.left + r.width / 2 - w / 2));
    const below = r.bottom + 8 + hgt <= window.innerHeight - 8;
    tip.style.left = `${left}px`;
    tip.style.top = `${below ? r.bottom + 8 : Math.max(8, r.top - 8 - hgt)}px`;
  },
  hideSoon() {
    clearTimeout(this.timer);
    this.timer = setTimeout(() => this.hide(), 180);
  },
  hide() {
    clearTimeout(this.timer);
    this.el?.classList.remove('is-open');
  },
};
