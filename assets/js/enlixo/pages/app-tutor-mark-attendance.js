// Tutor → Academic → Attendance → Mark Attendance (tutor/mark-attendance.php?session=<class day>).
// No class day chosen: today's classes (Start Marking / Continue) + the last 7 days to correct.
// A class day: context strip → stat cards → [ QR Code | Barcode | Manual tabs (only the ways on in Settings) ] beside
// the Scanned Student card (who, how marked, fees due with Record Payment) and the recent scans.
import { StatCard } from '../components/StatCard.js';
import { Tabs } from '../components/Tabs.js';
import { QuickGuide } from '../components/QuickGuide.js';
import {
  api, h, fill, icon, lkr, plural, cell, person, panel, simpleTable, BsModal, Toast, EmptyState, previewNote,
  METHODS, statusChip, stateChip, scanCard, beep, ampm, longDay, dm, today,
} from './attendance-common.js';

QuickGuide.attachAll();
const root = document.getElementById('mark-page');
const params = new URLSearchParams(location.search);
const sessionId = Number(params.get('session')) || null;
let S; // the class day with its roster
const recent = [];
let lastCode = ''; let lastAt = 0; let busy = false; let current = null; // the result shown on the card
let cardHost; let recentHost; let statsHost; let rosterHost;

const actions = document.getElementById('mark-actions');
const gear = h('div', { class: 'col-6 d-flex justify-content-end align-items-center' },
  h('a', { class: 'bg-transparent border-0 dark-theme', href: 'attendance-settings', title: 'Attendance Settings', 'aria-label': 'Attendance Settings' }, icon('settings', { size: 30 })));

// ------------------------------------------------------------------ choose a class day
async function picker() {
  fill(actions, gear, h('div', { class: 'col-6' }, h('a', { class: 'custom-btn w-100 text-decoration-none', href: 'attendance-dashboard' }, 'Attendance Dashboard')));
  const { data } = await api.get('/tutor/attendance/today');
  const tile = (s) => {
    const btn = s.state === 'live' ? h('a', { class: 'custom-btn w-100 text-decoration-none', href: `?session=${s.id}` }, s.expected - s.unmarked ? 'Continue Marking' : 'Start Marking')
      : s.state === 'done' ? h('a', { class: 'custom-btn-outline w-100 text-decoration-none', href: `?session=${s.id}` }, 'View and Correct')
        : h('button', { type: 'button', class: 'custom-btn-outline w-100', disabled: true }, `Opens at ${ampm(addMin(s.start, -data.settings.times.open_before))}`);
    const marked = s.expected - s.unmarked;
    return h('div', { class: 'col-12 col-md-6 col-xl-4' }, h('div', { class: `component-theme p-4 h-100 d-flex flex-column gap-3 at-day${s.state === 'live' ? ' at-day-live' : ''}` },
      h('div', { class: 'd-flex justify-content-between align-items-start gap-2' },
        h('div', { class: 'min-w-0' }, h('div', { class: 'text-16 main-text fw-semibold text-truncate', title: s.class.name }, s.class.short), h('div', { class: 'text-13 sub-text' }, `${ampm(s.start)} - ${ampm(s.end)} · ${s.mode}`)),
        stateChip(s.state)),
      h('div', {}, h('div', { class: 'd-flex justify-content-between text-13 mb-1' }, h('span', { class: 'sub-text' }, s.mode === 'Online' ? 'Joined or marked' : 'Marked'), h('span', { class: 'main-text fw-medium' }, `${marked} of ${s.expected}`)),
        h('span', { class: 'ad-bar d-block w-100' }, h('i', { style: { width: `${s.expected ? (marked / s.expected) * 100 : 0}%` } }))),
      s.mode === 'Online' ? h('div', { class: 'text-12 sub-text' }, 'Zoom marks students who join. Mark the others here.') : null,
      h('div', { class: 'mt-auto' }, btn)));
  };
  fill(root, previewNote(),
    h('div', { class: 'col-12 mb-2 d-flex align-items-center gap-2' }, h('span', { class: 'sub-topic' }, `Today's Classes`), h('span', { class: 'text-13 sub-text' }, longDay(today()))),
    data.today.length ? h('div', { class: 'row g-4 mb-4' }, data.today.map(tile)) : h('div', { class: 'component-theme mb-4' }, EmptyState.empty({ icon: 'calendar-empty', title: 'No classes today', text: 'Your class days come from the Time Table. Pick an earlier day below to correct it.' })),
    h('div', { class: 'col-12' }, panel('Last 7 Days', 'Open a finished class day to correct a mark, for example a student who forgot the card.', simpleTable([
      { label: 'Class Day', render: (s) => cell(`${s.day} ${dm(s.date)}`, `${ampm(s.start)} - ${ampm(s.end)}`) },
      { label: 'Class', render: (s) => cell(s.class.short, s.mode) },
      { label: 'Attended', render: (s) => cell(`${s.attended} of ${s.expected}`, `${s.rate}%`) },
      { label: 'Absent', render: (s) => cell(String(s.absent), s.watched ? `${s.watched} watched later` : null) },
      { label: '', render: (s) => h('a', { class: 'custom-btn ex-btn-sm px-3 text-decoration-none', href: `?session=${s.id}&tab=manual` }, 'Correct') },
    ], data.recent, { title: 'No class days in the last 7 days', text: 'Finished class days are listed here.' }))));
}
const addMin = (t, m) => { const v = Number(t.slice(0, 2)) * 60 + Number(t.slice(3)) + m; return `${String(Math.floor(((v + 1440) % 1440) / 60)).padStart(2, '0')}:${String(((v % 60) + 60) % 60).padStart(2, '0')}`; };

// ------------------------------------------------------------------ one class day
function stats() {
  const done = S.state === 'done';
  fill(statsHost, StatCard.row([
    { icon: 'students', label: 'Students in Class', value: S.expected, caption: S.mode === 'Online' ? 'online class' : 'expected today' },
    { icon: 'user-check', label: S.mode === 'Online' ? 'Joined / Present' : 'Present', value: S.present + S.joined, caption: `${S.expected ? Math.round(((S.present + S.joined + S.late) / S.expected) * 100) : 0}% attended`, trend: S.methods.QR + S.methods.Barcode ? { text: `${S.methods.QR + S.methods.Barcode} scanned`, dir: 'up' } : null },
    { icon: 'late', label: 'Late', value: S.late, caption: `after ${S.settings.times.late_after} minutes` },
    done ? { icon: 'user-cross', label: 'Absent', value: S.absent, caption: S.watched ? `${S.watched} watched the recording` : 'did not come' }
      : { icon: 'hourglass', label: 'Not Marked Yet', value: S.unmarked, caption: S.unmarked ? 'still to come' : 'everyone is marked', onClick: S.settings.methods.manual ? () => { manualFilter = 'none'; showTab('manual'); } : null },
  ], { cols: 'col-12 col-md-6 col-xl-3' }));
}

function context() {
  const w = S.window;
  const note = S.state === 'live' ? `Scanning is open until ${ampm(w.closes)}` : S.state === 'done' ? (S.can_correct === false ? 'Finished. You can look, but not change the marks.' : 'Finished. Use Manual to correct a mark.') : `Scanning opens at ${ampm(w.opens)}`;
  return h('div', { class: 'col-12 component-theme p-3 mb-4 d-flex flex-wrap align-items-center gap-3' },
    h('span', { class: 'ad-todo-icon' }, icon(S.mode === 'Online' ? 'online' : 'physical', { size: 22 })),
    h('div', { class: 'min-w-0' }, h('div', { class: 'sub-topic text-truncate' }, S.class.name), h('div', { class: 'text-13 sub-text' }, `${longDay(S.date)} · ${ampm(S.start)} - ${ampm(S.end)} · ${S.mode}`)),
    stateChip(S.state), h('span', { class: 'text-13 sub-text' }, note),
    h('div', { class: 'flex-grow-1' }),
    h('a', { class: 'custom-btn-outline px-4 text-decoration-none', href: 'mark-attendance' }, 'Change Class'));
}

function showCard(res) {
  current = res;
  fill(cardHost, res ? scanCard(res, {
    settings: S.settings,
    onMark: (status, force) => (res.from === 'manual' ? markOne(res.card.student.id, status, force) : submit(res.card.student.sid, res.method ?? 'qr', true)),
    onSkip: () => { Toast.success(`${res.card.student.name} was not marked.`); showCard({ ...res, result: 'view', message: 'Not marked' }); },
    onPaid: async () => { // after Record Payment: scan again (a paid student is now marked), or refresh the card
      if (['ask', 'blocked'].includes(res.result)) submit(res.card.student.sid, res.method ?? 'qr', false, true);
      else { const { data } = await api.get(`/tutor/attendance/sessions/${S.id}/students/${res.card.student.id}`); showCard({ ...res, card: data }); }
    },
  }) : h('div', { class: 'at-card at-card-empty' }, h('div', { class: 'p-4 text-center' },
    h('span', { class: 'at-wait-icon' }, icon('scan', { size: 30 })),
    h('div', { class: 'text-16 main-text fw-semibold mt-3' }, 'Waiting for a scan'),
    h('div', { class: 'text-13 sub-text mt-1' }, S.settings.card.fees ? 'The student\'s details, attendance and any fees due show here as soon as a card is scanned.' : 'The student\'s details show here as soon as a card is scanned.'))));
}

function drawRecent() {
  fill(recentHost, recent.length ? h('div', {}, recent.map((r) => h('button', { type: 'button', class: 'at-recent', onclick: () => r.card && showCard(r) },
    h('span', { class: `at-dot at-dot-${r.tone}` }),
    h('span', { class: 'flex-grow-1 min-w-0 text-start' }, h('span', { class: 'text-14 main-text d-block text-truncate' }, r.card?.student.name ?? r.code), h('span', { class: 'text-12 sub-text d-block text-truncate' }, r.message)),
    h('span', { class: 'text-12 sub-text' }, r.at))))
    : EmptyState.empty({ icon: 'history', title: 'No scans yet', text: 'Every scan of this class day is listed here, newest first.' }));
}

const TONE = { marked: 'success', late: 'orange', already: 'primary', ask: 'orange', blocked: 'danger', not_in_class: 'danger', unknown: 'danger', closed: 'danger' };
async function submit(code, method, force = false, quiet = false) {
  const now = Date.now();
  if (!force && !quiet && code === lastCode && now - lastAt < S.settings.times.repeat_seconds * 1000) return; // same card held in front of the camera
  if (busy) return; busy = true; lastCode = code; lastAt = now;
  try {
    const { data } = await api.post(`/tutor/attendance/sessions/${S.id}/scan`, { code, method, force });
    const res = { ...data, method, tone: TONE[data.result] ?? 'primary', at: new Date().toTimeString().slice(0, 5), code };
    showCard(res);
    if (S.settings.sound) beep(['marked', 'late'].includes(data.result));
    if (!quiet || ['marked', 'late'].includes(data.result)) { recent.unshift(res); recent.splice(30); drawRecent(); }
    if (data.counts) { Object.assign(S, data.counts); refreshRoster(); stats(); }
  } catch (e) { Toast.error(e.message); } finally { busy = false; }
}

async function markOne(studentId, status, force = false, students = null) {
  try {
    const { data } = await api.post(`/tutor/attendance/sessions/${S.id}/mark`, students ? { students, status } : { student: studentId, status, force });
    Object.assign(S, data.counts);
    await refreshRoster(true);
    stats();
    if (data.card) {
      const st = data.card.record?.status; // a Present mark after the late time is saved as Late
      const word = { present: 'Present', late: `Late (${data.card.record?.late_min} min)`, absent: 'Absent', joined: 'Joined Online', watched: 'Watched Recording' }[st];
      showCard({ result: st === 'late' ? 'late' : ['present', 'joined'].includes(st) ? 'marked' : 'view', message: st ? `Marked ${word} by hand` : 'Mark removed', card: data.card, from: 'manual' });
    }
  } catch (e) {
    if (e.code === 'fees_due' || e.details?.card) showCard({ result: 'blocked', message: e.message, card: e.details.card, can_override: e.details.can_override, from: 'manual' });
    else Toast.error(e.message);
  }
}

// --- QR Code tab
function qrTab(p) {
  const box = h('div', { class: 'at-camera' },
    h('div', { class: 'at-frame' }, h('i', {}), h('i', {}), h('i', {}), h('i', {})),
    h('div', { class: 'at-camera-text' }, icon('camera', { size: 28 }), h('div', { class: 'mt-2' }, 'Camera preview'), h('div', { class: 'text-12' }, 'Hold the QR code inside the square')));
  const start = h('button', { type: 'button', class: 'custom-btn flex-grow-1', disabled: S.state !== 'live', onclick: () => Toast.success('The camera starts here in the next step. In this preview, press Try a Sample Scan.') }, 'Start Camera');
  p.append(box, h('div', { class: 'd-flex gap-2 mt-3' }, start, sampleBtn('qr')),
    h('div', { class: 'text-12 sub-text mt-2' }, S.state === 'live' ? 'The camera stays on: students scan one after another. The same card is counted once.' : 'Scanning works only while the class is going on. Use Manual to change a mark.'));
}
// --- Barcode tab
function barcodeTab(p) {
  const input = h('input', { class: 'form-control custom-input at-code', placeholder: 'Scan the card, or type the Student ID and press Enter', 'data-limit': '30', autocomplete: 'off', disabled: S.state !== 'live',
    onkeydown: (e) => { if (e.key === 'Enter' && input.value.trim()) { submit(input.value.trim(), 'barcode'); input.value = ''; } } });
  p.append(h('div', { class: 'at-barcode' }, icon('barcode', { size: 56 }), h('div', { class: 'text-14 main-text fw-medium mt-2' }, 'Ready for the barcode scanner'), h('div', { class: 'text-12 sub-text' }, 'Keep the cursor in the box below. The scanner types the Student ID and presses Enter for you.')),
    h('div', { class: 'd-flex gap-2 mt-3' }, h('div', { class: 'flex-grow-1' }, input), sampleBtn('barcode')),
    h('div', { class: 'text-12 sub-text mt-2' }, 'A card does not scan? Type the Student ID printed under the barcode, for example ST02405.'));
  setTimeout(() => input.focus(), 50);
}
let sampleN = 0;
function sampleBtn(method) {
  return h('button', { type: 'button', class: 'custom-btn-outline px-4', disabled: S.state !== 'live', title: 'Preview only: scans a sample student card', onclick: () => {
    sampleN += 1;
    const unmarked = S.roster.filter((r) => !r.record);
    const owing = unmarked.filter((r) => r.owed > 0);
    const code = sampleN % 7 === 0 ? 'ST09999' : (sampleN % 3 === 0 && owing.length ? owing[0] : unmarked[0] ?? S.roster[0])?.student.sid;
    submit(code, method);
  } }, 'Try a Sample Scan');
}
// --- Manual tab
let manualFilter = 'all'; let manualQ = '';
function manualTab(p) {
  rosterHost = h('div');
  const filters = [['all', 'All'], ['none', 'Not Marked'], ['present', 'Present'], ['late', 'Late'], ['absent', 'Absent'], ['owes', 'Owes Fees']];
  const seg = h('div', { class: 'ad-seg flex-wrap' }, filters.map(([v, t]) => h('label', {}, h('input', { type: 'radio', name: 'mm-filter', value: v, checked: manualFilter === v, onchange: () => { manualFilter = v; drawRoster(); } }), t)));
  const search = h('input', { class: 'form-control custom-input', type: 'search', placeholder: 'Search students by name or Student ID', value: manualQ, oninput: (e) => { manualQ = e.target.value.toLowerCase(); clearTimeout(search.t); search.t = setTimeout(drawRoster, 300); } });
  const locked = S.state === 'done' && S.can_correct === false;
  p.append(
    locked ? h('div', { class: 'ad-soft p-3 d-flex gap-3 align-items-start mb-3' },
      h('span', { class: 'ad-todo-icon' }, icon('lock', { size: 20 })),
      h('div', {}, h('div', { class: 'text-14 main-text fw-semibold' }, 'This class day is finished'),
        h('div', { class: 'text-12 sub-text' }, 'Your role cannot correct finished class days. The tutor can give the permission "Correct finished class days" to your role in Settings → Roles.'))) : null,
    h('div', { class: 'mb-2' }, search), h('div', { class: 'mb-3' }, seg),
    rosterHost,
    locked ? null : h('div', { class: 'd-flex flex-wrap justify-content-end gap-2 mt-3' },
      h('button', { type: 'button', class: 'custom-btn-outline px-4', onclick: () => bulk('present') }, 'Mark Listed Present'),
      h('button', { type: 'button', class: 'custom-btn-outline px-4', onclick: () => bulk('absent') }, 'Mark Listed Absent')));
  drawRoster();
}
const listed = () => S.roster.filter((r) => {
  const st = r.record?.status ?? 'none';
  const okF = manualFilter === 'all' || (manualFilter === 'owes' ? r.owed > 0 : st === manualFilter);
  return okF && (!manualQ || `${r.student.name} ${r.student.sid}`.toLowerCase().includes(manualQ));
});
async function bulk(status) {
  const rows = listed().filter((r) => (r.record?.status ?? 'none') !== status);
  if (!rows.length) { Toast.success('Everyone listed is already marked that way.'); return; }
  const ok = await BsModal.confirm({ title: `Mark ${plural(rows.length, 'student')} ${status === 'present' ? 'Present' : 'Absent'}?`, text: 'Every student in the list below (after search and filter) is marked. You can change one student afterwards.', yes: `Mark ${status === 'present' ? 'Present' : 'Absent'}`, success: status === 'present', danger: status === 'absent' });
  if (ok) { await markOne(null, status, false, rows.map((r) => r.student.id)); Toast.success(`${plural(rows.length, 'student')} marked ${status === 'present' ? 'Present' : 'Absent'}.`); }
}
function drawRoster() {
  if (!rosterHost) return;
  const rows = listed();
  const lockedRow = S.state === 'done' && S.can_correct === false;
  const btn = (r, st, text) => h('button', { type: 'button', disabled: lockedRow, class: `at-mark at-mark-${st}${r.record?.status === st ? ' on' : ''}`, 'aria-pressed': String(r.record?.status === st), onclick: () => markOne(r.student.id, r.record?.status === st ? '' : st) }, text);
  fill(rosterHost, simpleTable([
    { label: 'Student', render: (r) => h('button', { type: 'button', class: 'bg-transparent border-0 p-0 text-start', title: 'Show this student', onclick: async () => { const { data } = await api.get(`/tutor/attendance/sessions/${S.id}/students/${r.student.id}`); showCard({ result: 'view', message: 'Picked from the list', card: data, from: 'manual' }); } },
      person(r.student.name, [r.student.sid, r.record?.time ? `${ampm(r.record.time)} · ${r.record.method}` : null].filter(Boolean).join(' · '))) },
    S.settings.card.fees ? { label: 'Fees', render: (r) => (r.owed > 0 ? h('span', { class: 'fe-mchip' }, `Owes ${lkr(r.owed)}`) : r.free_card ? h('span', { class: 'text-12 sub-text' }, 'Free card') : h('span', { class: 'text-12 sub-text' }, r.waiting ? 'Slip waiting' : 'Paid')) } : null,
    { label: 'Mark', render: (r) => h('div', { class: 'd-flex gap-1' }, S.mode === 'Online' && S.state === 'done' ? btn(r, 'joined', 'Joined') : btn(r, 'present', 'Present'), btn(r, 'late', 'Late'), btn(r, 'absent', 'Absent')) },
  ].filter(Boolean), rows, manualQ || manualFilter !== 'all' ? { title: 'No matching students', text: 'Try changing the search or filters.' } : { title: 'No students in this class', text: 'Students added to the class show here.' }, { fit: true }));
}
async function refreshRoster(redraw = true) {
  const { data } = await api.get(`/tutor/attendance/sessions/${S.id}`);
  S = { ...data };
  if (redraw) drawRoster();
}

let tabsObj;
function showTab(id) { const i = tabsObj.tabs.findIndex((t) => t.id === id); document.querySelectorAll('#mark-tabs .nav-link')[i]?.click(); }

async function session() {
  const { data } = await api.get(`/tutor/attendance/sessions/${sessionId}`);
  S = data;
  fill(actions, gear, h('div', { class: 'col-6' }, h('button', { type: 'button', class: 'custom-btn w-100', disabled: S.state === 'upcoming', onclick: finish }, S.state === 'done' ? 'Done' : 'Finish Class')));
  if (S.state === 'done') actions.querySelector('.custom-btn').onclick = () => { location.href = 'attendance-dashboard'; };
  statsHost = h('div', { class: 'col-12 mb-4' });
  cardHost = h('div');
  recentHost = h('div');
  const card = h('div', { class: 'component-theme p-4 h-100', id: 'mark-tabs' });
  const on = Object.keys(METHODS).filter((k) => S.settings.methods[k]);
  const order = [S.settings.default_method, ...on.filter((k) => k !== S.settings.default_method)].filter((k) => on.includes(k));
  const render = { qr: qrTab, barcode: barcodeTab, manual: manualTab };
  if (S.state !== 'live' && on.includes('manual') && !params.get('tab')) params.set('tab', 'manual'), history.replaceState(null, '', `?${params}`);
  tabsObj = new Tabs({ tabs: order.map((k) => ({ id: k, label: METHODS[k].label, render: render[k] })) });
  tabsObj.mount(card);
  fill(root, previewNote(), context(), statsHost,
    h('div', { class: 'row g-4' },
      h('div', { class: 'col-12 col-xl-7' }, card),
      h('div', { class: 'col-12 col-xl-5 d-flex flex-column gap-4' },
        h('div', { class: 'component-theme p-4' }, h('div', { class: 'sub-topic mb-3' }, 'Scanned Student'), cardHost),
        h('div', { class: 'component-theme p-4' }, h('div', { class: 'sub-topic mb-3' }, 'Recent Scans'), recentHost))));
  stats(); showCard(null); drawRecent();
}

async function finish() {
  const n = S.unmarked;
  const ok = await BsModal.confirm({ title: 'Finish this class?', text: n ? `${plural(n, 'student')} not marked will be marked Absent. You can still correct a mark later from Manual.` : 'Everyone is marked. The attendance is saved.', yes: 'Finish Class', success: true, icon: 'check' });
  if (!ok) return;
  const { data } = await api.post(`/tutor/attendance/sessions/${S.id}/finish`, {});
  Toast.success(data.marked_absent ? `Finished. ${plural(data.marked_absent, 'student')} marked Absent.` : 'Finished. Everyone was already marked.');
  Object.assign(S, data.counts); await refreshRoster(); stats();
}

(async () => {
  fill(root, h('div', { class: 'placeholder-glow' }, h('span', { class: 'placeholder col-12', style: { height: '140px' } })));
  try { if (sessionId) await session(); else await picker(); } catch (e) { fill(root, EmptyState.error(e.message, () => location.reload())); }
})();
