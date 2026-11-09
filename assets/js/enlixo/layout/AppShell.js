import { h, svg, fmt } from '../core/dom.js';
import { ApiClient } from '../core/ApiClient.js';
import { FeatureGate } from '../components/FeatureGate.js';
import { Meter } from '../components/Meter.js';
import { MockBackend } from '../mock/MockBackend.js';

// Demo pages live in /design-demo; resolve links relative to this file so it works in a sub-folder (XAMPP) too.
export const DEMO_BASE = new URL('../../../../design-demo/', import.meta.url).href;

const TUTOR_NAV = [
  { section: 'Teaching' },
  { label: 'Dashboard', icon: '🏠', href: '#' },
  { label: 'Students', icon: '🎓', href: DEMO_BASE + 'tutor/students.html', id: 'students', feature: 'students.registration' },
  { label: 'Classes', icon: '📚', href: '#', feature: 'classes.setup' },
  { label: 'Timetable', icon: '🗓️', href: '#', feature: 'timetable.manage' },
  { label: 'Recordings', icon: '🎬', href: '#', feature: 'recordings.upload' },
  { label: 'Tutes', icon: '📄', href: '#', feature: 'tutes.manage' },
  { label: 'MCQ Exams', icon: '📝', href: '#', feature: 'exams.mcq' },
  { section: 'Money' },
  { label: 'Payments', icon: '💳', href: '#', feature: 'payments.manual_approval' },
  { label: 'Card payments', icon: '💠', href: '#', feature: 'payments.online_card' },
  { label: 'Arrears', icon: '📉', href: '#', feature: 'payments.arrears' },
  { section: 'Engage' },
  { label: 'Attendance', icon: '✅', href: '#', feature: 'attendance.manual' },
  { label: 'WhatsApp', icon: '💬', href: '#', feature: 'comms.whatsapp' },
  { label: 'SMS', icon: '📱', href: '#', feature: 'comms.sms' },
  { label: 'Analytics', icon: '📊', href: '#', feature: 'reports.class_advanced' },
  { section: 'Settings' },
  { label: 'Roles & staff', icon: '🛡️', href: DEMO_BASE + 'tutor/roles.html', id: 'roles', feature: 'staff.custom_roles' },
  { label: 'Integrations', icon: '🔌', href: '#', feature: 'zoom.tutor_integration' },
  { label: 'Package', icon: '👑', href: DEMO_BASE + 'tutor/packages.html', id: 'packages' },
];

const STUDENT_NAV = [
  { label: 'My classes', icon: '📚', href: DEMO_BASE + 'student/classes.html', id: 'classes' },
  { label: 'Timetable', icon: '🗓️', href: '#' },
  { label: 'Payments', icon: '💳', href: '#' },
  { label: 'Profile', icon: '👤', href: '#' },
];

/**
 * Builds the page frame (sidebar, top bar), the API client and the FeatureGate.
 * Demo mode (default until a backend is configured): MockBackend + ?plan=start|grow|pro.
 */
export class AppShell {
  static async boot({ area = 'tutor', active = '', title = '' } = {}) {
    AppShell.#bootSkeleton();
    const params = new URLSearchParams(location.search);
    const cfg = window.ENLIXO_CONFIG ?? { mock: true };
    // Demo: students see the tutor's package (PRO by default so watch limits are on); tutors remember the last choice.
    const plan = params.get('plan') ?? (area === 'student' ? 'pro' : localStorage.getItem('enlixo.mock.plan') ?? 'start');
    if (area === 'tutor') localStorage.setItem('enlixo.mock.plan', plan);

    let api;
    if (cfg.mock || params.has('mock')) {
      const catalog = await fetch(DEMO_BASE + 'mock/catalog.json').then((r) => r.json());
      api = new ApiClient({ transport: new MockBackend(catalog, { plan }) });
    } else {
      api = new ApiClient({ baseUrl: cfg.apiBase ?? '/api/v1', token: localStorage.getItem('enlixo.token') });
    }

    AppShell.applyTheme(localStorage.getItem('theme') ?? 'light');
    const page = h('main', { class: 'ex-page', id: 'content' });
    const shell = new AppShell(area, active, title, api, page, cfg.mock || params.has('mock'));
    shell.gate = area === 'tutor' ? await FeatureGate.load(api) : null;
    shell.mount();
    return shell;
  }

  /** Same attributes the existing app uses (assets/js/init.js), so style.css dark mode applies. */
  static applyTheme(theme) {
    document.documentElement.setAttribute('data-bs-theme', theme);
    document.documentElement.setAttribute('data-custom-theme', theme);
  }

  /** Page frame skeleton shown instantly while access/package data loads. */
  static #bootSkeleton() {
    AppShell.applyTheme(localStorage.getItem('theme') ?? 'light');
    const line = (w, hgt = 12) => h('div', { class: 'ex-skeleton', style: { width: w, height: `${hgt}px`, margin: '14px 12px' } });
    document.body.replaceChildren(h('div', { class: 'ex-shell ex-root', 'aria-busy': 'true' },
      h('aside', { class: 'ex-sidebar' }, line('70%', 28), Array.from({ length: 9 }, (_, i) => line(`${55 + (i * 11) % 35}%`))),
      h('div', { class: 'ex-main' }, h('header', { class: 'ex-topbar' }, line('160px', 18)),
        h('main', { class: 'ex-page' }, line('240px', 24), line('360px'), h('div', { class: 'ex-card', style: { marginTop: '20px', height: '360px' } })))));
  }

  constructor(area, active, title, api, page, demo) {
    Object.assign(this, { area, active, title, api, page, demo });
  }

  mount() {
    this.sidebar = h('aside', { class: 'ex-sidebar' }, this.#brand(), this.#nav(), this.area === 'tutor' ? this.#usage() : null);
    document.body.replaceChildren(h('div', { class: 'ex-shell ex-root' }, this.sidebar, h('div', { class: 'ex-main' }, this.#topbar(), this.page)));
  }

  #brand() {
    return h('div', { class: 'ex-brand' }, h('div', { class: 'ex-brand-mark' }, 'E'), 'ENLIXO Tutor');
  }

  #nav() {
    const items = this.area === 'tutor' ? TUTOR_NAV : STUDENT_NAV;
    return h('nav', { 'aria-label': 'Main' }, items.map((item) => {
      if (item.section) return h('div', { class: 'ex-nav-section' }, item.section);
      const locked = item.feature && this.gate && !this.gate.has(item.feature);
      return h('a', {
        class: `ex-nav-item ${item.id === this.active ? 'ex-active' : ''} ${locked ? 'ex-is-locked' : ''}`,
        href: locked ? '#' : this.#keepQuery(item.href),
        'aria-current': item.id === this.active ? 'page' : null,
        onclick: locked ? (e) => { e.preventDefault(); this.gate.openUpgrade(item.feature); } : null,
      }, h('span', { class: 'ex-icon' }, item.icon), item.label, locked ? this.gate.badge(item.feature) : null);
    }));
  }

  #usage() {
    const { count, limit } = this.gate.access.students;
    return h('div', { class: 'ex-usage' },
      h('div', { class: 'ex-kv ex-small' }, h('span', {}, 'Students'), h('b', {}, `${count} / ${limit ?? '∞'}`)),
      limit ? Meter.render(count, limit) : null,
      h('div', { class: 'ex-small ex-muted' }, `${this.gate.plan.name} plan`));
  }

  #topbar() {
    const plan = this.gate?.plan;
    return h('header', { class: 'ex-topbar' },
      h('button', { class: 'ex-btn ex-btn-ghost ex-btn-sm', 'aria-label': 'Menu', onclick: () => this.sidebar.classList.toggle('ex-open') }, '☰'),
      h('h2', {}, this.title),
      h('div', { class: 'ex-spacer' }),
      this.demo && this.area === 'tutor' ? this.#planSwitcher() : null,
      plan ? h('span', { class: `ex-plan-chip ${plan.key === 'ex-pro' ? 'ex-pro' : ''}` }, plan.key === 'pro' ? svg('crown') : null, plan.name) : null,
      h('button', { class: 'ex-btn ex-btn-ghost ex-btn-sm', 'aria-label': 'Toggle dark mode', onclick: () => this.#toggleTheme() }, '🌓'),
      h('div', { class: 'ex-avatar' }, fmt.initials(this.area === 'tutor' ? 'Ruwan Perera' : 'Tharushi Silva')));
  }

  #planSwitcher() {
    return h('label', { class: 'ex-small ex-muted', style: { display: 'flex', gap: '6px', alignItems: 'center' } }, 'Demo package',
      h('select', { class: 'ex-select', style: { height: '30px' }, onchange: (e) => { const p = new URLSearchParams(location.search); p.set('plan', e.target.value); location.search = p; } },
        ['start', 'grow', 'pro'].map((k) => h('option', { value: k, selected: this.gate.plan.key === k }, k.toUpperCase()))));
  }

  #toggleTheme() {
    const next = document.documentElement.dataset.customTheme === 'dark' ? 'light' : 'dark';
    AppShell.applyTheme(next);
    localStorage.setItem('theme', next); // same key as assets/js/init.js
  }

  #keepQuery(href) {
    if (href === '#') return href;
    const plan = new URLSearchParams(location.search).get('plan');
    return plan ? `${href}?plan=${plan}` : href;
  }

  header(title, subtitle, actions = null) {
    return h('div', { class: 'ex-page-head' }, h('div', { class: 'ex-grow' }, h('h1', { class: 'ex-h1' }, title), subtitle ? h('p', {}, subtitle) : null), actions);
  }
}
