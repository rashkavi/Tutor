import { h, fmt, svg } from '../core/dom.js';
import { AppShell, DEMO_BASE } from '../layout/AppShell.js';
import { Tabs } from '../components/Tabs.js';
import { DataView } from '../components/DataView.js';
import { LoadingState } from '../components/LoadingState.js';
import { EmptyState } from '../components/EmptyState.js';
import { Meter } from '../components/Meter.js';

const classId = Number(new URLSearchParams(location.search).get('id') ?? 1);
const shell = await AppShell.boot({ area: 'student', active: 'classes', title: 'Class' });
const { api, page } = shell;

const head = h('div', {}, h('div', { class: 'ex-page-head' }, h('div', { class: 'ex-grow' }, LoadingState.line('40%'), h('div', { style: { height: '8px' } }), LoadingState.line('25%'))));
page.append(h('a', { href: DEMO_BASE + 'student/classes.html', class: 'ex-small' }, '← My classes'), head);

api.get(`/student/classes/${classId}`).then(({ data: c }) => {
  document.title = `${c.name} · ENLIXO Tutor`;
  head.replaceChildren(shell.header(c.name, `${c.subject} · ${c.medium} medium · ${c.tutor} · ${c.schedule}`));
}).catch((e) => head.replaceChildren(EmptyState.error(e.message)));

const MONTHS = ['2026-11', '2026-10', '2026-09'].map((m) => ({ value: m, label: new Date(`${m}-01`).toLocaleDateString('en-GB', { month: 'long', year: 'numeric' }) }));

new Tabs({
  tabs: [
    { id: 'timetable', label: '🗓️ Timetable', render: (panel) => timetable(panel) },
    { id: 'recordings', label: '🎬 Recordings', render: (panel) => recordings(panel) },
    { id: 'tutes', label: '📄 Tutes', render: (panel) => panel.append(h('div', { class: 'ex-card' }, EmptyState.empty({ icon: '📄', title: 'Tutes come next', text: 'Same list pattern (plan task 4.2).' }))) },
  ],
}).mount(page);

function timetable(panel) {
  new DataView({
    api, endpoint: `/student/classes/${classId}/timetable`, key: 'tt', perPage: 50, urlPrefix: 'tt_', noun: ['session', 'sessions'],
    searchPlaceholder: 'Search lessons',
    filters: [{ key: 'when', label: 'When', type: 'chips', options: [{ value: 'upcoming', label: 'Upcoming' }, { value: 'past', label: 'Past' }] }],
    skeleton: () => h('div', {}, LoadingState.list(5)),
    renderBody: (items) => h('div', {}, items.map(sessionRow)),
    empty: { icon: '🗓️', title: 'No sessions scheduled yet' },
  }).mount(panel);
}

function sessionRow(s) {
  const d = new Date(s.date);
  let action = null;
  if (s.status !== 'active') action = h('span', { class: 'ex-badge ex-warning' }, s.status);
  else if (s.zoom_ready) action = h('button', { class: 'ex-btn ex-btn-primary ex-btn-sm', onclick: () => alert('Opens the Zoom meeting through the portal (join link is only given after login).') }, 'Join on Zoom');
  else if (s.has_recording) action = h('a', { class: 'ex-btn ex-btn-sm', href: '?id=' + classId + '&tab=recordings' }, 'Watch recording');
  return h('div', { class: 'ex-session-row', style: s.upcoming ? null : { opacity: 0.75 } },
    h('div', { class: 'ex-date-box' }, h('div', { class: 'ex-d' }, String(d.getDate())), h('div', { class: 'ex-m' }, d.toLocaleDateString('en-GB', { month: 'short' }))),
    h('div', { style: { flex: 1 } }, h('div', { style: { fontWeight: 600 } }, s.title),
      h('div', { class: 'ex-small ex-muted' }, `${d.toLocaleDateString('en-GB', { weekday: 'long' })} · ${fmt.time(s.start)} – ${fmt.time(s.end)} · `, s.type === 'online' ? 'Online (Zoom)' : 'Physical class')),
    action);
}

function recordings(panel) {
  new DataView({
    api, endpoint: `/student/classes/${classId}/recordings`, key: 'rec', perPage: 50, urlPrefix: 'rec_', noun: ['recording', 'recordings'],
    searchPlaceholder: 'Search recordings',
    filters: [{ key: 'month', label: 'Month', allLabel: 'All months', options: MONTHS }],
    skeleton: () => h('div', { class: 'ex-card-pad' }, LoadingState.list(4)),
    renderBody: (items) => {
      const groups = new Map();
      items.forEach((r) => { if (!groups.has(r.month)) groups.set(r.month, []); groups.get(r.month).push(r); });
      return h('div', { class: 'ex-card-pad' }, [...groups].map(([month, recs]) => h('section', { class: 'ex-month-group' },
        h('h3', {}, MONTHS.find((m) => m.value === month)?.label ?? month, h('span', { class: 'ex-badge' }, String(recs.length)), recs.some((r) => r.status.state === 'unpaid') ? h('span', { class: 'ex-badge ex-danger' }, 'Fee unpaid') : null),
        h('div', { class: 'ex-grid', style: { gap: '10px' } }, recs.map(recordingCard)))));
    },
    empty: { icon: '🎬', title: 'No recordings yet', text: 'Recordings of online classes appear here automatically.' },
  }).mount(panel);
}

const STATE = {
  available: null,
  warning: ['warning', 'Almost used up'],
  exhausted: ['danger', 'Watch time used up'],
  expired: ['danger', 'Expired'],
  unpaid: ['danger', 'Pay fee to unlock'],
};

function recordingCard(r) {
  const s = r.status;
  const locked = !s.can_play;
  const badge = STATE[s.state];
  return h('div', { class: `ex-card ex-rec-card ${locked ? 'ex-locked' : ''}` },
    h('div', { class: 'ex-rec-thumb' }, locked ? svg('lock') : svg('play'), h('span', { class: 'ex-dur' }, fmt.minutes(r.duration_seconds))),
    h('div', { class: 'ex-info' },
      h('div', { style: { fontWeight: 600 } }, r.title),
      h('div', { class: 'ex-small ex-muted' }, `Added ${fmt.date(r.added)}`, s.expires_at ? ` · Available until ${fmt.date(s.expires_at)}` : ''),
      s.allowed_seconds
        ? h('div', { style: { display: 'grid', gap: '4px', maxWidth: '360px' } }, Meter.render(s.used_seconds, s.allowed_seconds), h('div', { class: 'ex-small ex-muted' }, `${fmt.minutes(s.used_seconds)} of ${fmt.minutes(s.allowed_seconds)} watch time used`))
        : h('div', { class: 'ex-small ex-muted' }, 'Unlimited viewing')),
    h('div', { style: { display: 'grid', gap: '8px', justifyItems: 'end' } },
      badge ? h('span', { class: `ex-badge ${badge[0]}` }, badge[1]) : null,
      locked ? h('button', { class: 'ex-btn ex-btn-sm', disabled: true }, 'Locked') : h('a', { class: 'ex-btn ex-btn-primary ex-btn-sm', href: `${DEMO_BASE}student/watch.html?kind=${r.kind}&id=${r.id}&class=${classId}` }, '▶ Watch')));
}
