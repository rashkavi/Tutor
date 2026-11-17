// Tutor → Academic → Class Recordings (tutor/recordings.php). V3 order: header card (gear → Recording Types, Add
// Recording, which opens tutor/add-recording.php) → stat cards → one table card with tabs (Recordings, Activity Log). ?class=<id> opens one class.
// Data: GET /tutor/recordings (list), /tutor/recordings/overview (cards, classes, types, log), /tutor/recordings/days.
import { DataView } from '../components/DataView.js';
import { StatCard } from '../components/StatCard.js';
import { Tabs } from '../components/Tabs.js';
import { ActionMenu } from '../components/Popover.js';
import { QuickGuide } from '../components/QuickGuide.js';
import {
  api, has, lockBadge, gate, h, fill, icon, BsModal, Toast, infoTip, EmptyState, cell, field, plural, platformTile, stateChip,
  dayText, shortDay, ampm, stamp, minutes, logTable, player, showFieldErrors, clearOnEdit, linkPicker,
} from './recordings-common.js';

QuickGuide.attachAll();
const root = document.getElementById('recordings-page');
const IMGS = new URL('../../../imgs/', import.meta.url).href;
let O = null; // overview: stats, classes, types, can_manage, can_expire, log
let view = null;
let tabs = null;
const statsEl = h('div');
const listEl = h('div');

const currentClass = () => new URLSearchParams(location.search).get('class') ?? '';
const DAY = new URLSearchParams(location.search).get('day') ?? ''; // opened from a class day on the Time Table
const className = (id) => O?.classes.find((c) => String(c.id) === String(id))?.name;

// ------------------------------------------------------------------ header
function header() {
  fill(document.getElementById('recordings-actions'),
    h('div', { class: 'col-6 d-flex justify-content-end align-items-center' },
      h('a', { class: 'bg-transparent border-0 dark-theme', href: 'recording-types', title: 'Recording Types', 'aria-label': 'Recording Types settings' }, icon('settings', { size: 30 }))),
    h('div', { class: 'col-6' }, O.can_manage ? h('button', { type: 'button', class: 'custom-btn w-100', onclick: () => edit(null) }, 'Add Recording') : null));
}

// ------------------------------------------------------------------ stat cards
function stats() {
  const s = O.stats;
  const cls = className(currentClass());
  fill(statsEl, StatCard.row([
    {
      icon: 'recordings', label: 'Recordings', value: s.total, trend: StatCard.trend(s.this_month, s.last_month),
      caption: `${s.this_month} added this month${cls ? ` · ${cls}` : ''}`, bars: s.bars,
      menu: O.can_manage ? [{ label: 'Add a recording', icon: 'add', onClick: () => edit(null) }] : null,
    },
    { icon: 'clock', label: 'Hours Recorded', value: minutes(s.minutes), caption: s.total ? `about ${minutes(Math.round(s.minutes / s.total))} each` : 'no recordings yet' },
    { icon: 'users', label: 'Students Watched', value: s.watchers, caption: 'opened a recording this month' },
    {
      icon: 'hourglass', label: 'Opens Later', value: s.scheduled,
      caption: [s.expiring ? `${s.expiring} close within 7 days` : null, s.expired ? `${s.expired} closed` : null].filter(Boolean).join(' · ') || 'none closing soon',
      onClick: () => view?.setFilters({ ...view.state.filters, state: 'scheduled' }),
      menu: [{ label: 'Show recordings that open later', icon: 'filter', onClick: () => view?.setFilters({ ...view.state.filters, state: 'scheduled' }) },
        { label: 'Show closed recordings', icon: 'filter', onClick: () => view?.setFilters({ ...view.state.filters, state: 'expired' }) }],
    },
  ], { cols: 'col-12 col-md-6 col-xl-3' }));
}

async function loadOverview() {
  ({ data: O } = await api.get('/tutor/recordings/overview', currentClass() ? { class: currentClass() } : {}));
}

// ------------------------------------------------------------------ table
function thumb(r) {
  const box = h('span', { class: 'rc-thumb' });
  if (r.thumb) {
    const img = h('img', { alt: '', loading: 'lazy', src: r.thumb.startsWith('http') ? r.thumb : IMGS + r.thumb });
    img.onerror = () => img.replaceWith(icon('play', { size: 22 }));
    box.append(img);
  } else box.append(icon('play', { size: 22 }));
  box.append(h('span', { class: 'rc-len' }, minutes(r.duration)));
  return box;
}

function availability(r) {
  const sub = r.state === 'scheduled' ? `Opens ${stamp(r.unlock_at.replace('T', ' '))}`
    : r.state === 'expired' ? `Closed after ${shortDay(r.expire_date)}`
      : r.expire_date ? `Until ${shortDay(r.expire_date)}` : 'No end date';
  return h('div', { class: 'd-flex flex-column gap-1 align-items-start' }, stateChip(r.state), h('span', { class: 'text-12 sub-text' }, sub));
}

function rowMenu(r) {
  const items = [
    { label: 'Watch', icon: 'play', onClick: () => watch(r) },
    { label: 'Who watched', icon: 'users', href: `recording-watch?recording-id=${r.id}` },
    O.can_manage ? { label: 'Edit', icon: 'edit', onClick: () => edit(r) } : null,
    r.url ? { label: 'Copy the link', icon: 'copy', onClick: async () => { try { await navigator.clipboard.writeText(r.url); Toast.success('Link copied.'); } catch { Toast.error('Could not copy the link.'); } } } : null,
    { label: 'Class details', icon: 'view-details', href: `class-details?class-id=${r.class.id}` },
    O.can_manage ? { label: 'Delete', icon: 'delete', danger: true, onClick: () => remove(r) } : null,
  ];
  return h('div', { class: 'd-flex align-items-center gap-2 justify-content-end' },
    h('button', { type: 'button', class: 'custom-btn table-btn px-3', onclick: () => watch(r) }, 'Watch'),
    ActionMenu.kebab(items, `Actions for ${r.title}`));
}

function table(p) {
  view = new DataView({
    api,
    endpoint: '/tutor/recordings',
    key: 'recordings',
    perPage: 10,
    perPageOptions: [10, 25, 50, 100],
    params: DAY ? { day: DAY } : null,
    noun: ['recording', 'recordings'],
    searchPlaceholder: 'Search by title, class or class day',
    filters: [
      { key: 'class', label: 'Class', allLabel: '- All Classes -', options: async () => O.classes.map((c) => ({ value: String(c.id), label: c.name })) },
      { key: 'type', label: 'Recording Type', allLabel: '- All Types -', options: async () => [...O.types.map((t) => ({ value: String(t.id), label: t.name })), { value: 'old', label: 'Added before (old)' }] },
      { key: 'state', label: 'Availability', allLabel: '- All -', options: [{ value: 'available', label: 'Available' }, { value: 'scheduled', label: 'Opens later' }, { value: 'expired', label: 'Closed' }] },
    ],
    exportCsv: {
      filename: `class-recordings-${new Date().toISOString().slice(0, 10)}.csv`,
      columns: [['Title', (r) => r.title], ['Class', (r) => r.class.name], ['Class Day', (r) => r.day.date], ['Type', (r) => r.type.name], ['Link', (r) => r.url],
        ['Minutes', (r) => r.duration], ['Opens On', (r) => r.unlock_at ?? ''], ['Available Until', (r) => r.expire_date ?? ''], ['Watched By', (r) => r.watched], ['Added', (r) => r.added_at]],
    },
    columns: [
      { key: 'title', label: 'Recording', sortable: true, render: (r) => h('div', { class: 'd-flex align-items-center gap-3' }, thumb(r),
        h('div', { class: 'd-flex flex-column min-w-0' }, h('span', { class: 'rc-title', title: r.title }, r.title),
          h('span', { class: 'text-12 sub-text' }, r.class.name),
          h('span', { class: 'text-12 sub-text' }, `${dayText(r.day.date)}${r.day.start ? ` · ${ampm(r.day.start)}` : ''}`))) },
      { key: 'type', label: 'Type', render: (r) => h('div', { class: 'd-flex align-items-center gap-2' }, platformTile(r.type.key, 16), cell(r.type.name, r.type.embeddable ? 'Plays in the page' : 'Opens in a new tab')) },
      { key: 'state', label: 'Availability', render: availability },
      { key: 'watched', label: 'Watched', render: (r) => h('a', { class: 'text-decoration-none', href: `recording-watch?recording-id=${r.id}` }, cell(plural(r.watched, 'student'), r.watched ? 'see who watched' : 'not opened yet')) },
      { key: 'actions', label: 'Action', width: '140px', render: rowMenu },
    ],
    empty: {
      title: 'No recordings yet',
      text: 'Add the recording of a class day so students who missed it can watch it.',
      action: O.can_manage ? h('button', { type: 'button', class: 'custom-btn px-4', onclick: () => edit(null) }, 'Add Recording') : null,
    },
  }).mount(p);
  // keep the stat cards in step with the Class filter
  let lastClass = currentClass();
  const origLoad = view.load.bind(view);
  view.load = async (...args) => {
    const r = await origLoad(...args);
    if ((view.state.filters.class ?? '') !== lastClass) {
      lastClass = view.state.filters.class ?? '';
      try { await loadOverview(); stats(); } catch { /* cards keep the old numbers */ }
    }
    return r;
  };
}

async function refresh() {
  try { await loadOverview(); stats(); } catch { /* ignore */ }
  tabs.tabs[1].badge = O.log.length;
  if (tabs.active === 'list') view?.reload(); else tabs.refresh();
}

// ------------------------------------------------------------------ watch
function watch(r) {
  BsModal.open({
    title: r.title, subtitle: [r.class.name, dayText(r.day.date), r.type.name], icon: 'play', size: 'lg',
    body: h('div', {}, player(r),
      r.description ? h('div', { class: 'text-14 main-text mt-3', style: { whiteSpace: 'pre-line' } }, r.description) : null,
      h('div', { class: 'd-flex flex-wrap gap-3 mt-3 text-13 sub-text' },
        h('span', {}, `Length ${minutes(r.duration)}`), h('span', {}, plural(r.watched, 'student'), ' watched'),
        r.passcode ? h('span', {}, `Passcode ${r.passcode}`) : null,
        r.unlock_at ? h('span', {}, `Opens ${stamp(r.unlock_at.replace('T', ' '))}`) : null,
        r.expire_date ? h('span', {}, `Until ${shortDay(r.expire_date)}`) : null)),
  });
}

async function remove(r) {
  const yes = await BsModal.confirm({
    title: `Delete "${r.title}"?`,
    text: r.watched ? `${plural(r.watched, 'student')} watched it. Students can no longer open it. This cannot be undone.` : 'Students can no longer open it. This cannot be undone.',
    yes: 'Delete', danger: true,
  });
  if (!yes) return;
  try { await api.delete(`/tutor/recordings/${r.id}`); Toast.success('Recording deleted.'); refresh(); } catch (e) { Toast.error(e.message); }
}

// ------------------------------------------------------------------ add / edit: its own page (tutor/add-recording.php)
function edit(rec) {
  if (rec) { location.href = `add-recording?id=${encodeURIComponent(rec.id)}`; return; }
  const c = currentClass() || view?.state.filters.class || '';
  location.href = `add-recording${DAY ? `?time-table-id=${encodeURIComponent(DAY)}` : c ? `?class=${encodeURIComponent(c)}` : ''}`;
}

/** Opened from one class day (Time Table → Recording): say so, with a way back to every recording. */
function dayNote() {
  const p = new URLSearchParams(location.search); p.delete('day');
  return h('div', { class: 'ad-soft p-3 mb-3 d-flex flex-wrap align-items-center gap-3' }, icon('calendar', { size: 20 }),
    h('span', { class: 'text-14 main-text flex-grow-1' }, 'Showing the recordings of one class day.'),
    h('a', { class: 'text-14 dark-theme text-decoration-none', href: `${location.pathname}?${p}` }, 'Show every recording'));
}

// ------------------------------------------------------------------ start
async function start() {
  try {
    await loadOverview();
  } catch (e) { fill(root, EmptyState.error(e.message, start)); return; }
  header();
  stats();
  const card = h('div', { class: 'col-12 component-theme p-4' });
  tabs = new Tabs({ tabs: [
    { id: 'list', label: 'Recordings', render: (p) => { view = null; if (DAY) p.append(dayNote()); p.append(listEl); fill(listEl); table(listEl); } },
    { id: 'log', label: 'Activity Log', badge: O.log.length, render: (p) => p.append(logTable(O.log, 'Every recording added, changed or deleted is listed here.')) },
  ] });
  tabs.mount(card);
  fill(root, statsEl, card);
  const q = new URLSearchParams(location.search);
  if (q.get('add') === '1' && O.can_manage) edit(null); // older links: the form is a page now
  if (q.get('edit') && O.can_manage) edit({ id: q.get('edit') });
}
start();
