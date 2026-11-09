import { h, clear, fill, fmt } from '../core/dom.js';
import { AppShell, DEMO_BASE } from '../layout/AppShell.js';
import { LoadingState } from '../components/LoadingState.js';
import { EmptyState } from '../components/EmptyState.js';

const shell = await AppShell.boot({ area: 'student', active: 'classes', title: 'My classes' });
const { api, page } = shell;

page.append(shell.header('My classes', 'Your timetable and recordings, organised class by class.'));
const grid = h('div', { class: 'ex-grid ex-grid-cards' }, LoadingState.cards(4, 220));
page.append(grid);

async function load() {
  try {
    const { data } = await api.get('/student/classes');
    fill(grid, ...(data.length ? data.map(card) : [EmptyState.empty({ icon: '📚', title: 'You are not in any class yet', text: 'Ask your tutor for the class join link.' })]));
  } catch (e) {
    fill(grid, EmptyState.error(e.message, load));
  }
}

function card(c) {
  const next = c.next_session;
  return h('a', { class: 'ex-card ex-class-card', href: `${DEMO_BASE}student/class.html?id=${c.id}` },
    h('div', { class: 'ex-class-cover', style: { background: `linear-gradient(135deg, ${c.color}, #1e1b4b)` } }, h('div', { class: 'ex-subject' }, `${c.subject} · ${c.medium}`), h('div', { class: 'ex-name' }, c.name)),
    h('div', { class: 'ex-class-body' },
      h('div', { class: 'ex-kv' }, h('span', {}, 'Tutor'), h('b', {}, c.tutor)),
      h('div', { class: 'ex-kv' }, h('span', {}, 'Next class'), h('b', {}, next ? `${fmt.date(next.date)} · ${fmt.time(next.start)}` : 'Not scheduled')),
      h('div', { class: 'ex-kv' }, h('span', {}, 'Recordings'), h('b', {}, String(c.recordings))),
      next?.zoom_ready ? h('span', { class: 'ex-badge ex-success' }, '● Online (Zoom)') : next ? h('span', { class: 'ex-badge' }, 'Physical class') : null));
}

load();
