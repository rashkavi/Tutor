import { h, svg } from '../core/dom.js';
import { AppShell } from '../layout/AppShell.js';
import { Toast } from '../components/Toast.js';

const shell = await AppShell.boot({ area: 'tutor', active: 'packages', title: 'Package' });
const { gate, page } = shell;
const { plans } = gate.access;
const current = gate.plan.key;
const order = ['start', 'grow', 'pro'];
const highlight = new URLSearchParams(location.search).get('highlight');

page.append(shell.header('Your package', 'Everything is visible. Items with a lock unlock when you upgrade.'));

page.append(h('div', { class: 'ex-pricing', style: { marginBottom: '22px' } }, order.map((key) => {
  const p = plans[key];
  const isCurrent = key === current;
  const isUp = order.indexOf(key) > order.indexOf(current);
  return h('div', { class: `ex-card ex-price-card ${isCurrent ? 'ex-current' : ''} ${key === 'ex-pro' ? 'ex-featured' : ''}` },
    h('div', { style: { display: 'flex', alignItems: 'center', gap: '8px' } }, h('span', { class: `ex-plan-chip ${key === 'ex-pro' ? 'ex-pro' : ''}` }, key === 'pro' ? svg('crown') : null, p.name), isCurrent ? h('span', { class: 'ex-badge ex-primary' }, 'Current') : null),
    h('div', { class: 'ex-price' }, p.price),
    h('div', { class: 'ex-muted' }, `Up to ${p.student_limit} students`),
    isUp ? h('button', { class: key === 'pro' ? 'ex-btn ex-btn-premium' : 'ex-btn ex-btn-primary', onclick: () => Toast.success(`Upgrade request for ${p.name} sent. (Payment flow: plan task 2.5)`) }, `Upgrade to ${p.name}`)
      : h('button', { class: 'ex-btn', disabled: true }, isCurrent ? 'Your plan' : 'Included'));
})));

const byCategory = new Map();
for (const f of gate.access.features) {
  if (!byCategory.has(f.category)) byCategory.set(f.category, []);
  byCategory.get(f.category).push(f);
}

for (const [category, features] of byCategory) {
  page.append(h('div', { class: 'ex-card', style: { marginBottom: '14px' } },
    h('div', { class: 'ex-card-head' }, h('h3', {}, category)),
    h('div', { class: 'ex-table-wrap' }, h('table', { class: 'ex-table' },
      h('thead', {}, h('tr', {}, h('th', {}, 'Feature'), order.map((k) => h('th', { style: { width: '110px', textAlign: 'center' } }, plans[k].name)))),
      h('tbody', {}, features.map((f) => {
        const row = h('tr', { class: f.unlocked ? '' : 'ex-locked-row', id: `f-${f.key}`, style: f.key === highlight ? { outline: '2px solid var(--gold-2)', outlineOffset: '-2px' } : null },
          h('td', {}, h('div', { style: { fontWeight: 600, display: 'flex', alignItems: 'center' } }, f.name, gate.badge(f.key)), h('div', { class: 'ex-small ex-muted' }, f.description)),
          order.map((k) => h('td', { style: { textAlign: 'center' } }, f.addon ? h('span', { class: 'ex-badge' }, 'Add-on') : planHas(f, k) ? h('span', { style: { color: 'var(--success)', fontWeight: 700 } }, '✔') : h('span', { class: 'ex-muted' }, '—'))));
        return row;
      }))))));
}

if (highlight) setTimeout(() => document.getElementById(`f-${highlight}`)?.scrollIntoView({ block: 'center', behavior: 'smooth' }), 100);

function planHas(f, planKey) {
  return f.plans.includes(planKey);
}
