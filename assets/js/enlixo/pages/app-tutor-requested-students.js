// Tutor → Students → Requested Students (tutor/requested-students.php), like V3 Requested Students:
// Application Link button, six stat cards (click one to see only those), and the list (search, filters, export).
// Data: GET /tutor/applications, /tutor/applications/stats, /tutor/application-link.
import { h, fill, fmt } from '../core/dom.js';
import { ApiClient } from '../core/ApiClient.js';
import { icon } from '../core/icons.js';
import { DataView } from '../components/DataView.js';
import { ActionMenu } from '../components/Popover.js';
import { StatCard } from '../components/StatCard.js';
import { BsModal } from '../components/BsModal.js';
import { QuickGuide } from '../components/QuickGuide.js';
import { Toast } from '../components/Toast.js';
import { ApplicationActions, STATUS, statusPill, fullName } from '../components/ApplicationActions.js';

const api = new ApiClient();
const PHOTOS = new URL('../../../imgs/students/', import.meta.url).href;
const statsEl = document.getElementById('requests-stats');
let classes = [];
let stats = null;
let view;

QuickGuide.attachAll();
document.getElementById('requests-actions').append(
  h('button', { type: 'button', class: 'custom-btn ex-btn-lg px-5 border-0', onclick: () => openLink() }, icon('link', { size: 20 }), 'Application Link'));

const stamp = (d) => (d ? d.slice(0, 16) : '');
function avatar(a) {
  const init = h('span', { class: 'ex-avatar' }, fmt.initials(fullName(a) || '?'));
  if (!a.photo) return init;
  const img = h('img', { class: 'ex-avatar', alt: '', src: PHOTOS + encodeURIComponent(a.photo) });
  img.onerror = () => img.replaceWith(init);
  return img;
}

// ---------- stat cards ----------
fill(statsEl, h('div', { class: 'row g-3 mb-4 placeholder-glow' }, [0, 1, 2, 3, 4, 5].map(() => h('div', { class: 'col-12 col-md-6 col-xl-4' }, h('span', { class: 'placeholder col-12', style: { height: '118px' } })))));

async function loadStats() {
  try {
    stats = (await api.get('/tutor/applications/stats')).data;
    drawStats();
  } catch (e) {
    fill(statsEl, h('div', { class: 'component-theme p-4 mb-4 text-14 sub-text' }, `Couldn't load the numbers: ${e.message}`));
  }
}

function drawStats() {
  const active = view?.state.filters.status ?? '';
  const show = (status) => () => view.setFilters(status ? { ...view.state.filters, status } : Object.fromEntries(Object.entries(view.state.filters).filter(([k]) => k !== 'status')));
  const dayName = (d) => new Date(`${d}T00:00`).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' });
  const thisWeek = stats.days.reduce((n, d) => n + d.count, 0);
  const cards = [
    {
      icon: 'application', label: 'Applications', value: stats.total, selected: !active, onClick: show(''),
      trend: StatCard.trend(stats.this_month, stats.last_month), caption: `${stats.this_month} this month vs last month`,
      bars: stats.days.map((d) => ({ label: dayName(d.date), value: d.count })),
      menu: [{ label: 'Show all applications', icon: 'view-details', onClick: show('') }, { label: 'Copy application link', icon: 'copy', onClick: () => copyLink() }],
    },
    ...Object.entries(STATUS).map(([key, s]) => ({
      icon: s.icon, label: s.label, value: stats.by_status[key] ?? 0, caption: s.caption, selected: active === key, onClick: show(key),
      menu: [{ label: `Show ${s.label.toLowerCase()} only`, icon: 'filter', onClick: show(key) }],
    })),
  ];
  cards[0].caption = thisWeek ? `${stats.this_month} this month, ${thisWeek} in the last 7 days` : cards[0].caption;
  fill(statsEl, StatCard.row(cards, { cols: 'col-12 col-md-6 col-xl-4' }));
}

// ---------- list ----------
async function move(a, to) {
  const done = await ApplicationActions.move(api, a, to, { classes });
  if (done) { view.reload(); loadStats(); }
}

view = new DataView({
  api,
  endpoint: '/tutor/applications',
  key: 'applications',
  perPage: 10,
  perPageOptions: [10, 25, 50, 100],
  noun: ['application', 'applications'],
  searchPlaceholder: 'Search by reference, name, e-mail or WhatsApp',
  filters: [
    { key: 'status', label: 'Status', type: 'chips', options: Object.entries(STATUS).map(([value, s]) => ({ value, label: s.label })) },
    { key: 'class', label: 'Class', allLabel: 'All classes', options: async () => classes },
  ],
  exportCsv: {
    filename: `requested-students-${new Date().toISOString().slice(0, 10)}.csv`,
    columns: [['Reference', (a) => a.ref], ['Name', fullName], ['E-mail', (a) => a.email], ['WhatsApp', (a) => a.whatsapp], ['Class', (a) => a.class_name], ['Applied On', (a) => stamp(a.created_at)], ['Status', (a) => STATUS[a.status]?.label ?? a.status], ['Note', (a) => a.status_note]],
  },
  columns: [
    { key: 'name', label: 'Applicant', sortable: true, render: (a) => h('div', { class: 'ex-person' }, avatar(a), h('div', {}, h('div', {}, fullName(a)), h('div', { class: 'ex-ref' }, a.ref ?? ''))) },
    { key: 'contact', label: 'Contact', render: (a) => h('div', {}, h('div', {}, a.email || '-'), h('div', { class: 'ex-ref' }, a.whatsapp ?? '')) },
    { key: 'class', label: 'Class', wrap: true, render: (a) => a.class_name ?? h('span', { class: 'sub-text' }, 'No class chosen') },
    { key: 'date', label: 'Applied On', sortable: true, render: (a) => stamp(a.created_at) },
    { key: 'status', label: 'Status', sortable: true, render: (a) => statusPill(a.status) },
    { key: 'actions', label: 'Action', width: '150px', render: (a) => h('div', { class: 'd-flex align-items-center gap-2' },
      h('a', { class: 'custom-btn ex-btn-sm px-3 text-decoration-none', href: `student-application?id=${encodeURIComponent(a.id)}` }, 'View'),
      ApplicationActions.items(a, () => {}).length ? ActionMenu.kebab(ApplicationActions.items(a, (to) => move(a, to)), `Actions for ${fullName(a)}`) : null) },
  ],
  empty: {
    icon: '📝', title: 'No applications yet',
    text: 'Share your application link. Students who fill it in show up here.',
    action: h('button', { type: 'button', class: 'custom-btn ex-btn-md px-4 border-0', onclick: () => openLink() }, 'Application Link'),
  },
}).mount(document.getElementById('requests-list'));

// keep the selected stat card in step with the Status filter
const origLoad = view.load.bind(view);
view.load = async (...args) => { const r = await origLoad(...args); if (stats) drawStats(); return r; };

api.get('/tutor/student-form').then((r) => { classes = r.data.lookups.classes; }).catch(() => {});
loadStats();

// ---------- Application Link ----------
let link = null;
const linkUrl = () => new URL(`../apply?t=${encodeURIComponent(link.token)}`, location.href).href;

async function copyLink() {
  try {
    link ??= (await api.get('/tutor/application-link')).data;
    await navigator.clipboard.writeText(linkUrl());
    Toast.success('Application link copied.');
  } catch (e) { Toast.error(e.message || 'Couldn\'t copy the link.'); }
}

async function openLink() {
  try { link = (await api.get('/tutor/application-link')).data; } catch (e) { Toast.error(e.message); return; }
  const body = h('div');
  const draw = () => {
    const url = linkUrl();
    const input = h('input', { class: 'form-control custom-input ex-h48', readonly: true, value: url, 'aria-label': 'Application link', onfocus: (e) => e.target.select() });
    fill(body, h('div', { class: 'row gy-3' },
      h('p', { class: 'col-12 text-14 main-text m-0' }, 'Share this link with new students. They fill in your student form, get a reference number, and show up in Requested Students for you to approve.'),
      h('div', { class: 'col-12' },
        h('label', { class: 'text-14 main-text mb-2' }, 'Your link'),
        h('div', { class: 'd-flex gap-2' }, input,
          BsModal.button('Copy', { primary: true, icon: '', onclick: async () => { input.select(); try { await navigator.clipboard.writeText(url); Toast.success('Copied.'); } catch { document.execCommand('copy'); } } }))),
      h('div', { class: 'col-12 d-flex flex-wrap gap-2' },
        h('a', { class: 'custom-outline-btn ex-btn-md px-4 text-decoration-none', href: `https://wa.me/?text=${encodeURIComponent(`Apply for my classes here: ${url}`)}`, target: '_blank', rel: 'noopener' }, icon('whatsapp', { size: 18 }), 'Share on WhatsApp'),
        h('a', { class: 'custom-outline-btn ex-btn-md px-4 text-decoration-none', href: url, target: '_blank', rel: 'noopener' }, icon('view', { size: 18 }), 'Open the form'),
        h('a', { class: 'custom-outline-btn ex-btn-md px-4 text-decoration-none', href: 'student-form-settings' }, icon('settings', { size: 18 }), 'Change the questions')),
      h('div', { class: 'col-12 ex-panel-soft p-3 d-flex align-items-center gap-3' },
        h('div', { class: 'form-check form-switch m-0 ex-switch-bs' },
          h('input', { class: 'form-check-input', type: 'checkbox', role: 'switch', id: 'link-open', checked: link.is_open, onchange: async (e) => {
            try { link = (await api.post('/tutor/application-link', { open: e.target.checked })).data; Toast.success(link.is_open ? 'You are taking applications.' : 'The link is closed. Nobody can apply.'); draw(); } catch (err) { Toast.error(err.message); e.target.checked = !e.target.checked; }
          } })),
        h('label', { for: 'link-open', class: 'flex-grow-1' },
          h('span', { class: 'd-block text-14 main-text' }, link.is_open ? 'Taking applications' : 'Closed'),
          h('span', { class: 'd-block text-12 sub-text' }, link.is_open ? 'Anyone with the link can apply.' : 'The link shows "not taking applications". Turn it on again any time.')))));
  };
  draw();
  const m = BsModal.open({
    title: 'Application Link',
    subtitle: 'One link for all your classes. Students pick the class on the form.',
    size: 'lg',
    body,
    footerStart: [BsModal.button('Make a new link', { onclick: async () => {
      const ok = await BsModal.confirm({ title: 'Make a new link?', text: 'The old link stops working. Use this if the link was shared somewhere it should not be.', yes: 'Make a new link' });
      if (!ok) return;
      try { link = (await api.post('/tutor/application-link', { renew: true })).data; draw(); Toast.success('New link ready. Share it again.'); } catch (e) { Toast.error(e.message); }
    } })],
    footerEnd: [BsModal.button('Done', { primary: true, onclick: () => m.close() })],
  });
}
