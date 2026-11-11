// Tutor → Settings → Attendance (tutor/attendance-settings.php). One card with tabs, like Fee Settings; each tab saves on
// its own (PUT /tutor/attendance-settings { part, ..., summary }) and every change goes to the Activity Log.
import { Tabs } from '../components/Tabs.js';
import { QuickGuide } from '../components/QuickGuide.js';
import { idCard, loadBarcode, printCards } from './id-card.js';
import {
  api, has, lockBadge, guard, BsModal, plural, h, fill, icon, cell, field, showErrors, simpleTable, Toast, infoTip, EmptyState, previewNote, METHODS, scanCard, ampm,
} from './attendance-common.js';

QuickGuide.attachAll();
const root = document.getElementById('attendance-settings-page');
let D;

fill(document.getElementById('settings-actions'),
  h('div', { class: 'col-6' }),
  h('div', { class: 'col-6' }, h('a', { class: 'custom-btn w-100 text-decoration-none', href: 'mark-attendance' }, 'Mark Attendance')));

const sw = (checked, attrs = {}) => h('div', { class: 'form-check form-switch m-0' }, h('input', { class: 'form-check-input', type: 'checkbox', role: 'switch', checked, ...attrs }));
const rule = (title, help, control, badge = null) => h('div', { class: 'ad-rule' },
  h('div', { class: 'pe-3' }, h('div', { class: 'text-14 main-text fw-medium d-flex align-items-center gap-2' }, title, badge), h('div', { class: 'text-12 sub-text' }, help)), control);
const sel = (opts, value, attrs = {}) => h('select', { class: 'form-select custom-select', ...attrs }, opts.map(([v, t]) => h('option', { value: v, selected: String(v) === String(value) }, t)));
const errLine = (key) => h('div', { class: 'text-12 text-dark-red mt-2 d-none', 'data-err-for': key });
const showErr = (p, map) => { p.querySelectorAll('[data-err-for]').forEach((e) => { const m = map[e.dataset.errFor]; e.textContent = m ?? ''; e.classList.toggle('d-none', !m); }); };
const saveBtn = (label, fn) => h('div', { class: 'd-flex justify-content-end mt-4' }, h('button', { type: 'button', class: 'custom-btn px-5', onclick: fn }, label));
async function put(p, body, ok) {
  try { ({ data: { settings: D.settings } } = await api.put('/tutor/attendance-settings', body)); } catch (e) { showErr(p, e.details ?? {}); showErrors(p, e.details ?? {}); Toast.error?.(e.message); return false; }
  showErr(p, {}); Toast.success(ok); return true;
}

// ------------------------------------------------------------------ Ways to Mark
function methods(p) {
  const s = D.settings;
  const picked = { ...s.methods };
  const def = sel([], s.default_method, { 'data-key': 'default_method' });
  const drawDefault = () => fill(def, ...Object.keys(METHODS).filter((k) => picked[k]).map((k) => h('option', { value: k, selected: k === s.default_method }, METHODS[k].label)));
  const tiles = h('div', { class: 'at-methods' }, Object.entries(METHODS).map(([k, m]) => h('label', { class: 'at-method' },
    h('input', { type: 'checkbox', checked: picked[k], onchange: (e) => { picked[k] = e.target.checked; drawDefault(); } }),
    h('span', { class: 'at-method-tick' }, icon('check', { size: 14 })),
    h('span', { class: 'at-method-icon' }, icon(m.icon, { size: 26 })),
    h('span', { class: 'text-16 main-text fw-semibold' }, m.label),
    h('span', { class: 'text-13 sub-text' }, m.help),
    h('span', { class: 'at-method-need' }, icon(k === 'qr' ? 'camera' : k === 'barcode' ? 'keyboard' : 'checklist', { size: 14 }), m.need))));
  drawDefault();
  p.append(
    h('div', { class: 'sub-topic mb-1 d-flex align-items-center gap-2' }, 'How you mark attendance', infoTip('Tick every way you use. You can tick more than one, for example QR codes at the door and Manual for a student who forgot the card. Mark Attendance shows a tab for each way you tick.', 'How you mark attendance')),
    h('div', { class: 'text-12 sub-text mb-3' }, 'Tick one or more. Mark Attendance shows only the ways you tick.'),
    tiles, errLine('methods'),
    h('div', { class: 'row gx-4 mt-4' },
      h('div', { class: 'col-md-6' }, field('Open first', def, { required: true, info: 'The tab Mark Attendance opens on. Pick the way you use most, for example QR Code if students scan at the door.' }), errLine('default_method')),
      h('div', { class: 'col-md-6' }, field('What the code holds', sel([['student_id', 'Student ID, for example ST02405']], s.code, { disabled: true }), { info: 'The QR code and the barcode on a student card hold the Student ID, so a printed card and the card in the app both work. The Student ID is also printed under the code, so you can type it when a card does not scan.' }))),
    h('div', { class: 'ad-soft p-3 d-flex gap-3 align-items-start mt-2' },
      h('span', { class: 'ad-todo-icon' }, icon('id-card', { size: 20 })),
      h('div', { class: 'flex-grow-1' },
        h('div', { class: 'text-14 main-text fw-semibold d-flex align-items-center gap-2' }, 'Student ID cards', lockBadge('id.cards')),
        h('div', { class: 'text-12 sub-text' }, 'The code on a student ID card follows what you tick here: a QR code for QR Code, a barcode for Barcode. Make and print the cards on the Student ID Cards tab.'))),
    saveBtn('Save Changes', async () => {
      await put(p, { part: 'methods', methods: picked, default_method: def.value, code: 'student_id', summary: `Ways to mark: ${Object.keys(picked).filter((k) => picked[k]).map((k) => METHODS[k].label).join(', ') || 'none'}` }, 'Saved. Mark Attendance now shows these tabs.');
    }));
}

// ------------------------------------------------------------------ When a Student Is Scanned
const SAMPLE = {
  result: 'ask', message: 'Kavindu Perera owes 2 months. Mark present?',
  card: {
    student: { id: 2405, sid: 'ST02405', name: 'Kavindu Perera', whatsapp: '0771234567' }, classes: ['Theory 2026'], enrolled: true, record: null,
    fees: { total: 6200, count: 2, months: [{ id: 0, label: 'Oct', long: 'October 2026', class: 'Theory 2026', net: 3200, status: 'overdue' }, { id: 0, label: 'Nov', long: 'November 2026', class: 'Theory 2026', net: 3000, status: 'overdue' }], waiting: 0, waiting_amount: 0, free_card: false },
    attendance: { held: 2, attended: 1, rate: 50, last: null, streak: 1 },
  },
};
function scanned(p) {
  const s = structuredClone(D.settings);
  const preview = h('div', { class: 'at-sticky' });
  const draw = () => {
    const res = structuredClone(SAMPLE);
    if (s.owing.action === 'show') Object.assign(res, { result: 'marked', message: 'Kavindu Perera: Present', card: { ...res.card, record: { status: 'present', time: '15:52', method: 'QR', late_min: 0 } } });
    if (s.owing.action === 'block') Object.assign(res, { result: 'blocked', message: 'Not marked: Kavindu Perera must pay the fees first.' });
    fill(preview, h('div', { class: 'text-12 sub-text fw-semibold text-uppercase mb-2' }, 'What you will see'), scanCard(res, { settings: s, onMark: () => {}, onSkip: () => {} }));
    preview.querySelectorAll('button, a').forEach((b) => { b.setAttribute('tabindex', '-1'); b.style.pointerEvents = 'none'; });
  };
  const toggle = (key, title, help, locked = false) => rule(title, help, sw(locked ? true : s.card[key], { disabled: locked, onchange: (e) => { s.card[key] = e.target.checked; draw(); } }));
  const actions = h('div', { class: 'at-choices' }, [
    ['show', 'Mark and show the fees', 'The student is marked Present. The fees due are shown so you can remind them.'],
    ['ask', 'Ask before marking', 'You see the fees due and press Mark Present or Do Not Mark.'],
    ['block', 'Do not mark until paid', 'The student is not marked. Record the payment, or press Mark Present Anyway.'],
  ].map(([v, t, sub]) => h('label', { class: 'fe-tile' }, h('input', { type: 'radio', name: 'owing-action', value: v, checked: s.owing.action === v, onchange: () => { s.owing.action = v; draw(); } }),
    h('span', { class: 'text-14 main-text fw-medium' }, t), h('span', { class: 'text-12 sub-text' }, sub))));
  const left = h('div', { class: 'col-12 col-xl-7' },
    h('div', { class: 'sub-topic mb-1' }, 'Show on the card'),
    toggle('name', 'Photo, name and Student ID', 'Always shown, so you can see it is the right student.', true),
    toggle('class', 'Classes', 'The classes the student is in.'),
    toggle('fees', 'Fees due', 'Unpaid months and the total, with Record Payment and the Fee Card. Comes from Student Fees.'),
    toggle('attendance', 'Attendance this month', 'Class days attended this month, the last day they came and how many they missed in a row.'),
    toggle('phone', 'WhatsApp number', 'Shows the number and a Message button. Off by default because others may see the screen.'),
    h('div', { class: 'sub-topic mt-4 mb-1 d-flex align-items-center gap-2' }, 'Students who owe fees', infoTip('What happens when a student who has not paid is scanned. Most tutors choose "Ask before marking": the class is not stopped and you decide for each student.', 'Students who owe fees')),
    h('div', { class: 'text-12 sub-text mb-3' }, 'Works only when "Fees due" is shown on the card.'),
    actions,
    h('div', { class: 'row gx-3 mt-3' },
      h('div', { class: 'col-md-6' }, field('Count as owing', sel([['overdue', 'Only months past the due date'], ['any', 'Every unpaid month, this month too']], s.owing.count, { onchange: (e) => { s.owing.count = e.target.value; } }), { info: 'Past the due date: a November fee due on 10 November counts from 11 November. Every unpaid month: it counts from the day the fee is created.' })),
      h('div', { class: 'col-md-6' }, field('Owing at least', sel([[1, '1 month'], [2, '2 months'], [3, '3 months']], s.owing.min_months, { onchange: (e) => { s.owing.min_months = Number(e.target.value); } }), { info: 'For example 2 months: a student who owes only one month is marked as usual; the rule starts from the second month.' }))),
    h('div', { class: 'ad-soft p-3 d-flex gap-3 align-items-start mt-3' },
      h('span', { class: 'ad-todo-icon' }, icon('lock', { size: 20 })),
      h('div', {}, h('div', { class: 'text-14 main-text fw-semibold' }, 'Only you can mark a student who owes fees'),
        h('div', { class: 'text-12 sub-text' }, 'Mark Present and Mark Present Anyway are shown only to you. Assistants who mark attendance see the fees due and can record the payment, but cannot mark the student until it is paid.'))),
    rule('Sound on scan', 'A short high beep when a student is marked, a low beep when something needs you.', sw(s.sound, { onchange: (e) => { s.sound = e.target.checked; } })),
    saveBtn('Save Changes', () => put(p, { part: 'card', card: s.card, owing: s.owing, sound: s.sound, summary: `Scan card and fee rule saved (${{ show: 'Mark and show the fees', ask: 'Ask before marking', block: 'Do not mark until paid' }[s.owing.action]})` }, 'Saved.')));
  p.append(h('div', { class: 'row g-4' }, left, h('div', { class: 'col-12 col-xl-5' }, preview)));
  draw();
}

// ------------------------------------------------------------------ Student ID Cards (GROW; START sees a locked sample)
const SAMPLE_TUTOR = { name: 'Mr. K. R. Perera', subject: 'Combined Mathematics', phone: '0771234567' };
const SAMPLE_STUDENT = { sid: 'ST02405', name: 'Kavindu Perera', classes: ['Theory 2026', 'Paper 2027'] };

async function idCards(p) {
  const on = has('id.cards');
  const m = D.settings.methods;
  const codes = [m.qr ? 'a QR code' : null, m.barcode ? 'a barcode' : null].filter(Boolean);
  if (m.barcode) await loadBarcode().catch(() => null);
  const cls = sel([['', 'All classes'], ...D.classes.map((c) => [c.id, c.name])], '', { 'data-key': 'class' });

  const generate = guard('id.cards', async () => {
    let data;
    try { ({ data } = await api.get('/tutor/attendance/id-cards', cls.value ? { class: cls.value } : {})); } catch (e) { Toast.error(e.message); return; }
    if (!data.students.length) { Toast.error('No active students in this class.'); return; }
    if (m.barcode) await loadBarcode().catch(() => null);
    const cards = data.students.map((s) => idCard(s, data.tutor, m));
    const className = cls.value ? cls.selectedOptions[0].textContent : 'All classes';
    const md = BsModal.open({
      title: 'Student ID Cards', subtitle: [className, plural(cards.length, 'card')], icon: 'id-card', size: 'xl',
      body: h('div', {}, h('div', { class: 'text-13 sub-text mb-3' }, 'Check the cards, then print them on A4 (8 cards a page). Cut along the dashed lines.'), h('div', { class: 'idc-grid' }, cards)),
      footerEnd: [
        BsModal.button('Close', { onclick: () => md.close() }),
        BsModal.button('Print Cards', { primary: true, onclick: () => { if (!printCards(cards, `Student ID Cards · ${className}`)) Toast.error('Allow pop-ups for this site to print the cards.'); } }),
      ],
    });
  });

  const preview = h('div', { class: 'ad-soft p-4 d-flex flex-column align-items-center gap-3' },
    h('div', { class: 'text-12 sub-text fw-semibold text-uppercase align-self-start' }, on ? 'How a card looks' : 'Sample card'),
    idCard(SAMPLE_STUDENT, SAMPLE_TUTOR, m, { sample: !on }));

  const left = h('div', { class: 'col-12 col-xl-6' },
    h('div', { class: 'sub-topic mb-1 d-flex align-items-center gap-2' }, 'Student ID cards', lockBadge('id.cards'),
      infoTip('A card for every student with their name, Student ID and classes. Students show it at the class and it is scanned to mark attendance.', 'Student ID cards')),
    h('div', { class: 'text-12 sub-text mb-3' }, on ? 'Make cards for one class or for every student, then print them.' : 'Making and printing cards is on GROW. Here is how a card looks.'),
    h('div', { class: 'ad-soft p-3 d-flex gap-3 align-items-start mb-3' },
      h('span', { class: 'ad-todo-icon' }, icon(m.qr ? 'qr' : m.barcode ? 'barcode' : 'id-card', { size: 20 })),
      h('div', {}, h('div', { class: 'text-14 main-text fw-semibold' }, codes.length ? `The card has ${codes.join(' and ')}` : 'The card has no code'),
        h('div', { class: 'text-12 sub-text' }, codes.length
          ? 'It follows the ways you tick on the Ways to Mark tab. The code holds the Student ID, which is also printed on the card.'
          : 'You mark by hand only. Tick QR Code or Barcode on the Ways to Mark tab to put a code on the card.'))),
    on ? field('Class', cls, { info: 'Pick a class to make cards only for its students, or All classes for every active student. A student in two classes gets one card with both classes.' }) : null,
    h('div', { class: 'd-flex justify-content-end mt-4' },
      h('button', { type: 'button', class: 'custom-btn px-5 d-inline-flex align-items-center gap-2', onclick: generate }, on ? icon('printer', { size: 18 }) : icon('lock', { size: 18 }), 'Make ID Cards')));
  p.append(h('div', { class: 'row g-4' }, left, h('div', { class: 'col-12 col-xl-6' }, preview)));
}

// ------------------------------------------------------------------ Times & Rules
function times(p) {
  const t = { ...D.settings.times };
  let autoAbsent = D.settings.auto_absent;
  const example = h('div', { class: 'ad-soft p-3 text-14 main-text d-flex gap-3 align-items-start' });
  const drawEx = () => {
    const m = (x) => { const v = ((x % 1440) + 1440) % 1440; return ampm(`${String(Math.floor(v / 60)).padStart(2, '0')}:${String(v % 60).padStart(2, '0')}`); };
    fill(example, h('span', { class: 'ad-todo-icon' }, icon('clock', { size: 20 })), h('div', {},
      h('div', { class: 'fw-semibold' }, 'Example: a class from 4:00 PM to 6:00 PM'),
      h('div', { class: 'text-13 sub-text' }, `Scanning opens at ${m(960 - Number(t.open_before || 0))} and closes at ${m(1080 + Number(t.close_after || 0))}. A student who comes after ${m(960 + Number(t.late_after || 0))} is marked Late.`)));
  };
  const num = (key, ph, min, max) => h('input', { class: 'form-control custom-input', type: 'number', min, max, value: t[key], placeholder: ph, 'data-key': key, oninput: (e) => { t[key] = e.target.value; drawEx(); } });
  p.append(
    rule('Scanning opens', 'Minutes before the class starts. Students who come early can scan in.', num('open_before', 'Enter minutes, for example 30', 0, 180)),
    errLine('open_before'),
    rule('Scanning closes', 'Minutes after the class ends. After that only Manual can change a mark.', num('close_after', 'Enter minutes, for example 30', 0, 180)),
    errLine('close_after'),
    rule('Late after', 'Minutes after the start. A student scanned later is marked Late (still counted as attended).', num('late_after', 'Enter minutes, for example 15', 0, 120)),
    errLine('late_after'),
    rule('Same card again', 'Seconds a second scan of the same card is ignored, so a card held in front of the camera is counted once.', num('repeat_seconds', 'Enter seconds, for example 60', 5, 600)),
    errLine('repeat_seconds'),
    rule('Mark the rest Absent', 'When the class ends, every student not marked becomes Absent. Online classes: students who watch the recording later become "Watched Recording".', sw(autoAbsent, { onchange: (e) => { autoAbsent = e.target.checked; } })),
    h('div', { class: 'mt-3' }, example),
    saveBtn('Save Changes', () => put(p, { part: 'times', times: t, auto_absent: autoAbsent, summary: `Times saved: opens ${t.open_before} min before, closes ${t.close_after} min after, late after ${t.late_after} min` }, 'Saved.')));
  drawEx();
}

function log(p) {
  p.append(simpleTable([
    { label: 'When', render: (l) => cell(l.when) },
    { label: 'Who', render: (l) => cell(l.by) },
    { label: 'What changed', render: (l) => cell(l.what) },
  ], D.log, { title: 'No changes yet', text: 'Every change to these settings is listed here.' }));
}

async function load() {
  try {
    ({ data: D } = await api.get('/tutor/attendance-settings'));
    const card = h('div', { class: 'col-12 component-theme p-4' });
    new Tabs({ tabs: [
      { id: 'methods', label: 'Ways to Mark', render: methods },
      { id: 'scan', label: 'When a Student Is Scanned', render: scanned },
      { id: 'cards', label: 'Student ID Cards', render: idCards },
      { id: 'times', label: 'Times & Rules', render: times },
      { id: 'log', label: 'Activity Log', badge: D.log.length, render: log },
    ] }).mount(card);
    fill(root, previewNote(), card);
  } catch (e) { fill(root, EmptyState.error(e.message, load)); }
}
load();
