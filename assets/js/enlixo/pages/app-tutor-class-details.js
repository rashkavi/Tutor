// Tutor → Classes → one class (tutor/class-details.php?class-id=). A small dashboard for the class:
// facts (when, where, fee), four stat cards, attendance per week, the next class days, what needs the tutor,
// and links to everything in the class with their counts. Data: GET /tutor/classes/{id}/summary (same rules as
// the main dashboard: Enlixo\Dashboard\TutorDashboard::classSummary).
import { h, fill } from '../core/dom.js';
import { ApiClient } from '../core/ApiClient.js';
import { QuickGuide } from '../components/QuickGuide.js';
import { StatCard } from '../components/StatCard.js';
import { ActionMenu } from '../components/Popover.js';
import { icon } from '../core/icons.js';

const api = new ApiClient();
const root = document.getElementById('class-details-root');
const id = Number(root.dataset.classId || 0);
const q = `class-id=${id}`;
const lkr = (n) => `LKR ${Math.round(n).toLocaleString('en-LK')}`;
const plural = (n, one, many = `${one}s`) => `${n} ${n === 1 ? one : many}`;

QuickGuide.attachAll();
document.getElementById('class-details-actions').append(
  ActionMenu.button('Action', [
    { label: 'Add a student', icon: 'user-add', href: `add-student?${q}` },
    { label: 'Add a tute', icon: 'tutes', href: `add-tute?${q}` },
    { label: 'Mark attendance', icon: 'attendance', href: `class-attendance?${q}` },
    { label: 'Time table', icon: 'timetable', href: `class-time-table?${q}` },
  ], { className: 'custom-outline-btn ex-btn-lg px-4 border-0' }),
  h('a', { href: `edit-class?${q}`, class: 'custom-btn ex-btn-lg px-4 border-0 d-inline-flex align-items-center gap-2 text-decoration-none' }, icon('edit', { size: 18 }), 'Edit Class'),
);

skeleton();
load();

function skeleton() {
  const bone = (w, hgt, extra = {}) => h('span', { class: 'ex-skeleton d-block', style: { width: w, height: `${hgt}px`, ...extra } });
  fill(root, h('div', { 'aria-busy': 'true', 'aria-label': 'Loading the class' },
    h('div', { class: 'ex-skel-card mb-4' }, h('div', { class: 'd-flex gap-3' }, [0, 1, 2, 3].map(() => bone('22%', 44)))),
    h('div', { class: 'row g-3 mb-4' }, [0, 1, 2, 3].map(() => h('div', { class: 'col-12 col-md-6 col-xl-3' }, h('div', { class: 'ex-skel-card ex-skel-stat' }, bone('45%', 12), h('div', { class: 'mt-3' }, bone('35%', 26)))))),
    h('div', { class: 'row g-4' }, h('div', { class: 'col-12 col-xl-8' }, h('div', { class: 'ex-skel-card', style: { height: '300px' } }, bone('30%', 16), h('div', { class: 'mt-4' }, bone('100%', 200)))),
      h('div', { class: 'col-12 col-xl-4' }, h('div', { class: 'ex-skel-card', style: { height: '300px' } }, bone('40%', 16), [0, 1, 2].map(() => h('div', { class: 'mt-4' }, bone('90%', 40))))))));
}

async function load() {
  try {
    draw((await api.get(`/tutor/classes/${id}/summary`)).data);
  } catch (e) {
    fill(root, h('div', { class: 'component-theme text-center py-5' },
      h('p', { class: 'main-text mb-1' }, 'Couldn\'t load this class'),
      h('p', { class: 'text-14 sub-text' }, e.message),
      h('button', { type: 'button', class: 'custom-btn ex-btn-md px-4 border-0', onclick: () => { skeleton(); load(); } }, 'Try again')));
  }
}

const fact = (ico, k, v) => h('div', { class: 'ex-dl-fact' }, h('span', { class: 'ex-dl-fact-ico' }, icon(ico, { size: 18 })), h('span', { class: 'min-w-0' }, h('span', { class: 'ex-dl-fact-k' }, k), h('span', { class: 'ex-dl-fact-v' }, v)));
const panel = (title, sub, body, link = null) => h('div', { class: 'component-theme p-4 h-100 d-flex flex-column' },
  h('div', { class: 'd-flex align-items-start gap-3 mb-3' },
    h('div', { class: 'flex-grow-1' }, h('div', { class: 'ex-panel-title' }, title), sub ? h('div', { class: 'text-12 sub-text' }, sub) : null),
    link ? h('a', { class: 'ex-hd-link', href: link[1] }, link[0]) : null),
  body);
const empty = (ico, text) => h('div', { class: 'ex-hd-empty' }, h('span', { class: 'ex-empty-ico' }, icon(ico, { size: 26 })), h('p', { class: 'text-14 sub-text m-0' }, text));

function draw(d) {
  const c = d.class;
  const s = d.stats;
  const next = d.next[0];
  const trend = s.attendance !== null && s.attendanceBefore !== null ? StatCard.trend(s.attendance, s.attendanceBefore) : null;

  fill(root,
    h('div', { class: 'component-theme p-4 mb-4' },
      h('div', { class: 'ex-dl-facts' },
        fact('timetable', 'Every week', c.times.length ? c.times.join(', ') : 'No weekly times yet'),
        fact(c.mode === 'Online' ? 'online' : 'location', 'Where', c.mode === 'Online' ? 'Online (Zoom)' : [c.mode, c.location].filter(Boolean).join(' · ')),
        fact('money', 'Monthly fee', c.free ? 'Free class' : c.fee ? `${lkr(c.fee)}${c.dueDay ? `, due on the ${c.dueDay}${['th', 'st', 'nd', 'rd'][(c.dueDay % 100 > 10 && c.dueDay % 100 < 14) ? 0 : (c.dueDay % 10 < 4 ? c.dueDay % 10 : 0)]}` : ''}` : 'Not set'),
        fact('language', 'Medium and year', `${c.medium} · ${c.year}`),
        c.active ? null : h('div', { class: 'ex-dl-fact' }, h('span', { class: 'ex-hd-pill ex-hd-tone-bad' }, 'This class is inactive')))),
    StatCard.row([
      { tone: 'blue', icon: 'students', label: 'Students', value: s.students, trend: s.newStudents ? { text: `+${s.newStudents}`, dir: 'up' } : null,
        caption: [s.newStudents ? 'joined this month' : 'active in this class', s.inactive ? `${s.inactive} inactive` : ''].filter(Boolean).join(' · '), menu: [{ label: 'Open students', icon: 'view', href: `class-students?${q}` }] },
      d.money
        ? { tone: 'green', icon: 'payments', label: 'Fees This Month', value: lkr(s.paid), caption: s.due ? `${s.paidPct ?? 0}% of ${lkr(s.due)}` : 'no fees due this month', menu: [{ label: 'Open payments', icon: 'view', href: `class-payments?${q}` }] }
        : { tone: 'green', icon: 'tutes', label: 'Tutes', value: s.tutes, caption: 'shared with this class', menu: [{ label: 'Open tutes', icon: 'view', href: `class-tutes?${q}` }] },
      { tone: 'purple', icon: 'attendance', label: 'Attendance', value: s.attendance === null ? '-' : `${s.attendance}%`, trend,
        caption: s.attendance === null ? 'no finished classes in the last 4 weeks' : 'last 4 weeks, watched recordings count', menu: [{ label: 'Open attendance', icon: 'view', href: `class-attendance?${q}` }] },
      { tone: 'orange', icon: 'clock', label: 'Next Class', value: next ? (next.isToday ? 'Today' : next.day) : '-', caption: next ? `${next.time} · ${next.mode}` : 'nothing on the time table', menu: [{ label: 'Open time table', icon: 'view', href: `class-time-table?${q}` }] },
    ], { cols: 'col-12 col-md-6 col-xl-3' }),
    h('div', { class: 'row g-4 mb-4' },
      h('div', { class: 'col-12 col-xl-8' }, panel('Attendance by Week', 'Last 6 weeks. Watched = was absent but watched the recording.', weeksChart(d.weeks), ['Attendance', `class-attendance?${q}`])),
      h('div', { class: 'col-12 col-xl-4' }, panel('Needs your attention', d.todo.length ? 'Do these first' : '', d.todo.length
        ? h('div', {}, d.todo.map((t) => h('a', { class: 'ex-cd-todo', href: t.href }, h('span', { class: `ex-cd-todo-ico tone-${t.tone}` }, icon(t.icon, { size: 20 })), h('span', { class: 'flex-grow-1 text-14 main-text' }, t.label), icon('chevron-right', { size: 16 }))))
        : empty('check', 'Nothing is waiting for you in this class.')))),
    h('div', { class: 'row g-4' },
      h('div', { class: 'col-12 col-xl-5' }, panel('Next Class Days', null, d.next.length
        ? h('ul', { class: 'ex-hd-list' }, d.next.map((n) => h('li', { class: 'ex-hd-row' },
          h('span', { class: `ex-hd-date${n.isToday ? ' ex-today' : ''}` }, h('span', { class: 'ex-hd-date-d' }, new Date(`${n.date}T00:00:00`).getDate()), h('span', { class: 'ex-hd-date-m' }, new Date(`${n.date}T00:00:00`).toLocaleDateString('en-GB', { month: 'short' }))),
          h('span', { class: 'flex-grow-1 min-w-0' }, h('span', { class: 'text-14 fw-medium ex-hd-title d-block' }, n.isToday ? 'Today' : n.day), h('span', { class: 'text-12 sub-text d-block' }, [n.time, n.mode, n.title].filter(Boolean).join(' · '))))))
        : empty('timetable', 'No class days coming up. Add weekly times to the class.'), ['Time table', `class-time-table?${q}`])),
      h('div', { class: 'col-12 col-xl-7' }, panel('In this class', null, h('div', { class: 'row g-3' }, [
        ['students', 'Students', s.students, `class-students?${q}`],
        ['timetable', 'Time Table', d.next.length ? `${d.next.length}+ coming` : 'none coming', `class-time-table?${q}`],
        ['attendance', 'Attendance', s.attendance === null ? '-' : `${s.attendance}%`, `class-attendance?${q}`],
        ['payments', 'Payments', d.money ? (s.unpaidStudents ? `${s.unpaidStudents} not paid` : 'all paid') : '', `class-payments?${q}`],
        ['recordings', 'Recordings', s.recordings, `class-recordings?${q}`],
        ['pre-recorded', 'Pre-Recorded', s.preRecordings, `class-pre-recordings?${q}`],
        ['tutes', 'Tutes', s.tutes, `class-tutes?${q}`],
        ['location', 'Tute Deliveries', s.parcels ? `${s.parcels} to send` : 'all sent', `couriered-tute?${q}`],
      ].map(([ico, label, val, href]) => h('div', { class: 'col-6 col-md-3' }, h('a', { class: 'ex-cd-link', href },
        h('span', { class: 'ex-cd-link-ico' }, icon(ico, { size: 22 })), h('span', { class: 'ex-cd-link-label' }, label), h('span', { class: 'ex-cd-link-val' }, String(val))))))))));
}

/** Attended (blue) + watched (purple) + absent (red) per week, stacked; plain CSS, so no chart library is needed. */
function weeksChart(weeks) {
  const max = Math.max(1, ...weeks.map((w) => w.attended + w.watched + w.absent));
  if (weeks.every((w) => !(w.attended + w.watched + w.absent))) return empty('attendance', 'No finished class days in the last 6 weeks.');
  const key = (cls, text) => h('span', { class: 'd-inline-flex align-items-center gap-1' }, h('i', { class: `ex-hd-chart-key ${cls}` }), text);
  return h('div', { class: 'd-flex flex-column flex-grow-1' },
    h('div', { class: 'd-flex gap-3 text-12 main-text mb-2 justify-content-end' }, key('ex-hd-chart-present', 'Attended'), key('ex-cd-watched', 'Watched'), key('ex-hd-chart-absent', 'Absent')),
    h('div', { class: 'ex-hd-chart', role: 'img', 'aria-label': 'Attendance by week' }, weeks.map((w) => {
      const total = w.attended + w.watched + w.absent;
      const tip = `Week of ${w.label}: ${w.attended} attended, ${w.watched} watched, ${w.absent} absent`;
      return h('div', { class: 'ex-hd-chart-col', tabindex: '0', title: tip, 'aria-label': tip },
        h('div', { class: 'ex-hd-chart-bars' }, total ? [
          h('span', { class: 'ex-hd-chart-total' }, total ? `${Math.round(((w.attended + w.watched) / total) * 100)}%` : ''),
          w.absent ? h('span', { class: 'ex-hd-chart-bar ex-hd-chart-absent', style: { height: `${(w.absent / max) * 100}%` } }) : null,
          w.watched ? h('span', { class: 'ex-hd-chart-bar ex-cd-watched', style: { height: `${(w.watched / max) * 100}%` } }) : null,
          w.attended ? h('span', { class: 'ex-hd-chart-bar ex-hd-chart-present', style: { height: `${(w.attended / max) * 100}%` } }) : null,
        ] : h('span', { class: 'ex-hd-chart-none' })),
        h('span', { class: 'ex-hd-chart-x' }, w.label));
    })));
}
