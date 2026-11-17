// Tutor → Who Watched (tutor/recording-watch.php?recording-id=): stat cards, then every student of the class with the
// minutes watched, % of the length, times opened, last watched and (GROW) extra time given inline.
// API: GET /tutor/recordings/{id}/watch, POST /tutor/recordings/{id}/extra.
import { QuickGuide } from '../components/QuickGuide.js';
import { StatCard } from '../components/StatCard.js';
import { api, h, fill, icon, day, simpleTable, Toast } from './admin-common.js';

QuickGuide.attachAll();
const root = document.getElementById('rw-page');
const actions = document.getElementById('rw-actions');
const id = Number(new URLSearchParams(location.search).get('recording-id') || 0);
let D = null;
let filter = 'all';
let giving = null; // student id with the extra-time panel open

fill(root, h('div', { class: 'component-theme p-5 text-center sub-text' }, 'Loading…'));
load();

async function load() {
  try { D = (await api.get(`/tutor/recordings/${id}/watch`)).data; } catch (e) {
    fill(root, h('div', { class: 'component-theme p-4 text-dark-red' }, e.message));
    return;
  }
  document.title = `Who Watched ${D.recording.title}`;
  draw();
}

function draw() {
  const r = D.recording;
  const all = D.students;
  const watched = all.filter((s) => s.sessions > 0);
  const full = all.filter((s) => s.percent >= 80);
  const avg = watched.length ? Math.round(watched.reduce((a, s) => a + s.watched_minutes, 0) / watched.length) : 0;
  const list = filter === 'not' ? all.filter((s) => s.sessions === 0) : filter === 'watched' ? watched : all;
  fill(actions,
    h('a', { class: 'custom-btn px-4 text-decoration-none d-inline-flex align-items-center', href: 'recordings' }, 'All Recordings'),
    all.length > watched.length ? h('a', { class: 'custom-btn px-4 text-decoration-none d-inline-flex align-items-center gap-2', href: 'message-center' }, icon('send', { size: 18 }), 'Remind Students') : null);
  const tab = (key, label, n) => h('button', { type: 'button', class: `nav-link${filter === key ? ' active' : ''}`, onclick: () => { filter = key; draw(); } }, label, ' ', h('span', { class: 'tab-count' }, String(n)));
  fill(root,
    h('div', { class: 'component-theme p-4 mb-4 d-flex flex-wrap gap-3 align-items-center' },
      h('span', { class: 'fe-form-ico' }, icon('recordings', { size: 22 })),
      h('div', { class: 'min-w-0 flex-grow-1' }, h('div', { class: 'text-18 main-text fw-semibold' }, r.title),
        h('div', { class: 'text-13 sub-text' }, `${r.class.name} · class day ${day(r.day.date)} · ${r.duration} min`))),
    StatCard.row([
      StatCard.card({ icon: 'users', label: 'Students', value: all.length, caption: 'in the class' }),
      StatCard.card({ icon: 'view', label: 'Watched', value: watched.length, caption: `${all.length ? Math.round((watched.length / all.length) * 100) : 0}% opened it` }),
      StatCard.card({ icon: 'check', label: 'Watched most', value: full.length, caption: '80% or more of it' }),
      StatCard.card({ icon: 'clock', label: 'Average', value: `${avg} min`, caption: 'per student who watched' }),
    ]),
    h('div', { class: 'component-theme p-4' },
      h('ul', { class: 'nav nav-tabs mb-3' }, h('li', { class: 'nav-item' }, tab('all', 'All', all.length)), h('li', { class: 'nav-item' }, tab('watched', 'Watched', watched.length)), h('li', { class: 'nav-item' }, tab('not', 'Not opened', all.length - watched.length))),
      simpleTable([
        { label: 'Student', render: (s) => h('div', { class: 'd-flex flex-column' }, h('span', { class: 'text-14 main-text' }, s.name), h('span', { class: 'text-12 sub-text' }, s.whatsapp || '-')) },
        { label: 'Watched', render: (s) => h('div', { class: 'd-flex flex-column gap-1', style: { minWidth: '140px' } }, h('span', { class: 'text-14 main-text' }, s.sessions ? `${s.watched_minutes} min · ${s.percent}%` : 'Not opened'),
          h('span', { class: 'rw-bar' }, h('span', { style: { width: `${Math.min(100, s.percent)}%` } }))) },
        { label: 'Times opened', render: (s) => h('span', { class: 'text-14' }, String(s.sessions)) },
        { label: 'Last watched', render: (s) => h('span', { class: 'text-14' }, s.last_at ? day(String(s.last_at).slice(0, 10)) : '-') },
        { label: 'Extra time', render: (s) => h('span', { class: 'text-14' }, s.extra_minutes ? `+${s.extra_minutes} min` : '-') },
        { label: '', render: (s) => (D.can_give_time ? h('button', { type: 'button', class: 'custom-btn table-btn px-3', onclick: () => { giving = giving === s.id ? null : s.id; draw(); } }, 'Give Time')
          : h('a', { class: 'text-13 dark-theme', href: 'upgrade?feature=recordings.controlled_access' }, 'GROW')) },
      ], list, { title: filter === 'not' ? 'Everyone opened it' : 'Nobody watched it yet', text: filter === 'not' ? 'Every student of the class has opened this recording.' : 'Students who play it are listed here with the time they watched.' }),
      giving ? extraPanel(all.find((s) => s.id === giving)) : null));
}

function extraPanel(s) {
  const minutes = h('input', { class: 'form-control custom-input', type: 'number', min: 0, max: 600, value: s.extra_minutes || 30, placeholder: 'Minutes, for example 30' });
  const reason = h('input', { class: 'form-control custom-input', placeholder: 'Why, for example "Missed the class, poor connection"', 'data-limit': '200', value: s.extra_reason || '' });
  const save = h('button', { type: 'button', class: 'custom-btn px-4', onclick: async () => {
    save.disabled = true;
    try {
      const { data } = await api.post(`/tutor/recordings/${id}/extra`, { student: s.id, minutes: Number(minutes.value), reason: reason.value });
      Toast.success(data.message);
      giving = null;
      load();
    } catch (e) { save.disabled = false; Toast.error(e.message); }
  } }, 'Save');
  return h('div', { class: 'rw-extra mt-3', id: 'rw-extra' },
    h('div', { class: 'text-16 main-text fw-semibold mb-2' }, `Extra watch time for ${s.name}`),
    h('div', { class: 'row g-2 align-items-end' },
      h('div', { class: 'col-12 col-md-3' }, h('label', { class: 'text-13 sub-text mb-1' }, 'Minutes'), minutes),
      h('div', { class: 'col-12 col-md-6' }, h('label', { class: 'text-13 sub-text mb-1' }, 'Reason'), reason),
      h('div', { class: 'col-12 col-md-3 d-flex gap-2' }, h('button', { type: 'button', class: 'custom-btn px-3', onclick: () => { giving = null; draw(); } }, 'Cancel'), save)),
    h('div', { class: 'text-12 sub-text mt-2' }, 'Added to what the recording allows, for this student only. Enter 0 to take it away.'));
}
