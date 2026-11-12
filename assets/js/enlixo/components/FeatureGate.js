import { h, svg } from '../core/dom.js';
import { Modal } from './Modal.js';

/**
 * Package locks in the UI. Locked features stay visible with a premium lock badge;
 * clicking them opens an upgrade modal that names the plan that unlocks them.
 * (The API enforces the same rules: this is presentation only.)
 *
 *   const gate = await FeatureGate.load(api);
 *   gate.badge('comms.whatsapp')                     -> <span class="lock-badge">GROW</span> or null
 *   gate.wrap('reports.class_advanced', previewEl)   -> blurred preview + unlock overlay when locked
 *   button.onclick = gate.guard('payments.online_card', () => openGateway())
 */
export class FeatureGate {
  constructor(access, { upgradeUrl = 'subscription?tab=plans' } = {}) {
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
    return f.required_plan ? f.required_plan.toUpperCase() : 'ADD-ON';
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

  /** @param {string|{feature:string}} keyOrDetails feature key, or error.details from a FeatureLockedError */
  openUpgrade(keyOrDetails) {
    const key = typeof keyOrDetails === 'string' ? keyOrDetails : keyOrDetails.feature;
    const f = this.features.get(key) ?? { name: 'This feature', description: '' };
    const planKey = f.required_plan;
    const plan = planKey ? this.plans[planKey] : null;
    const alsoGet = planKey
      ? this.access.features.filter((x) => !x.unlocked && x.required_plan === planKey && x.key !== key).slice(0, 4)
      : [];

    const body = h('div', { class: 'ex-upgrade-hero' },
      h('div', { class: 'ex-lock-orb', style: { width: '64px', height: '64px' } }, svg('crown')),
      h('h2', { id: 'modal-title' }, `Unlock ${f.name}`),
      h('div', { class: 'ex-muted' }, f.description),
      plan
        ? h('div', { class: 'ex-upgrade-plan', style: { width: '100%' } },
          h('div', { style: { display: 'flex', alignItems: 'center', gap: '8px' } }, h('span', { class: `ex-plan-chip ${planKey === 'ex-pro' ? 'ex-pro' : ''}` }, plan.name), h('b', {}, plan.price)),
          alsoGet.length ? h('div', { class: 'ex-small ex-muted', style: { marginTop: '8px' } }, 'You also get: ', alsoGet.map((x) => x.name).join(' · ')) : null)
        : f.addon ? h('div', { class: 'ex-upgrade-plan', style: { width: '100%' } }, h('b', {}, f.addon.name), h('div', { class: 'ex-small ex-muted' }, f.addon.pricing)) : null);

    const modal = new Modal({
      title: null, className: 'ex-upgrade', body,
      footer: [
        h('button', { class: 'ex-btn', onclick: () => modal.close() }, 'Maybe later'),
        h('a', { class: 'ex-btn ex-btn-premium', href: `${this.upgradeUrl}?highlight=${encodeURIComponent(key)}` }, svg('crown'), plan ? `Upgrade to ${plan.name}` : 'View add-ons'),
      ],
    });
    return modal.open();
  }
}
