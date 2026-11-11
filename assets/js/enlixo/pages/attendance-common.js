// Shared parts of the tutor's Attendance pages (docs/ATTENDANCE-PLAN.md): the status chips, the period filter
// (day / week / month / custom range), and the Scanned Student card that shows who was scanned, how they were marked
// and the fees they still owe. Attendance Dashboard, Mark Attendance and Attendance Settings all use these.
import { ApiClient } from '../core/ApiClient.js';
import { AttendanceMock } from '../mock/AttendanceMock.js';
import { FeatureGate } from '../components/FeatureGate.js';
import {
  h, fill, icon, lkr, short, day, plural, cell, person, panel, field, showErrors, simpleTable, BsModal, Toast, infoTip, EmptyState, recordPayment, waLink,
} from './fees-common.js';

// Step A (preview): sample data in the browser. Step B: drop the transport and the PHP API (src/Attendance) answers.
export const PREVIEW = true;
export const api = new ApiClient(PREVIEW ? { transport: new AttendanceMock() } : {});
export const gate = await FeatureGate.load(api).catch(() => null);
export const has = (key) => !gate || gate.has(key);
export const lockBadge = (key) => gate?.badge(key) ?? null;
export const guard = (key, fn) => (gate ? gate.guard(key, fn) : fn);

export const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
export const today = () => iso(new Date());
export const addDays = (s, n) => { const d = new Date(`${s}T00:00:00`); d.setDate(d.getDate() + n); return iso(d); };
export const dm = (s) => new Date(`${s}T00:00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
export const longDay = (s) => new Date(`${s}T00:00:00`).toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
export const ampm = (t) => { if (!t) return ''; const [hh, mm] = t.split(':').map(Number); return `${((hh + 11) % 12) + 1}:${String(mm).padStart(2, '0')} ${hh < 12 ? 'AM' : 'PM'}`; };

// One colour and one word per attendance state (V3 status chips). Legacy ids: joined 1, present 2 (+ late), absent 3, watched 4.
export const STATUS = {
  present: ['success', 'Present', 'user-check'],
  late: ['orange', 'Late', 'late'],
  joined: ['primary', 'Joined Online', 'online'],
  watched: ['purple', 'Watched Recording', 'recordings'],
  absent: ['danger', 'Absent', 'user-cross'],
  none: ['grey', 'Not Marked', 'clock'],
};
export const HEX = { present: '#48CD5D', late: '#FF9500', joined: '#0061D2', watched: '#8E5BF3', absent: '#F04438', light: '#B9CFF2' };
export const statusChip = (status, text = null) => {
  const [tone, label] = STATUS[status ?? 'none'] ?? STATUS.none;
  return h('span', { class: `table-btn table-btn-${tone === 'grey' ? 'grey ad-chip-grey' : tone} ad-chip` }, text ?? label);
};
const STATE = { live: ['success', 'Going On Now'], upcoming: ['primary', 'Not Started'], done: ['grey', 'Finished'], cancelled: ['danger', 'Cancelled'] };
export const stateChip = (state) => { const [tone, label] = STATE[state]; return h('span', { class: `table-btn table-btn-${tone === 'grey' ? 'grey ad-chip-grey' : tone} ad-chip` }, label); };
export const METHODS = {
  qr: { label: 'QR Code', icon: 'qr', need: 'A phone or laptop camera', help: 'Students show the QR code on their Student ID card or in the ENLIXO app. Hold it in front of the camera.' },
  barcode: { label: 'Barcode', icon: 'barcode', need: 'A USB or Bluetooth barcode scanner', help: 'Scan the barcode on the printed Student ID card. The scanner types the Student ID for you.' },
  manual: { label: 'Manual', icon: 'checklist', need: 'Nothing extra', help: 'Tick Present, Late or Absent on the class list, one by one or for everyone listed.' },
};

/** A rate as a coloured bar + number (red under 50 %, orange under 80 %). */
export const rateBar = (rate, sub = null) => (rate == null ? cell('-', sub) : h('div', { class: 'd-flex flex-column gap-1', style: { minWidth: '110px' } },
  h('div', { class: 'd-flex align-items-center gap-2' }, h('span', { class: 'ad-bar flex-grow-1' }, h('i', { class: rate < 50 ? 'bad' : rate < 80 ? 'warn' : '', style: { width: `${rate}%` } })), h('span', { class: 'text-14 main-text fw-medium' }, `${rate}%`)),
  sub ? h('span', { class: 'text-12 sub-text' }, sub) : null));

export const previewNote = () => (PREVIEW
  ? h('div', { class: 'col-12 ad-preview mb-4' }, icon('info', { size: 18 }),
    h('span', {}, h('b', {}, 'Preview with sample data. '), 'Scans, marks and settings work on this page, but nothing is saved yet. Your real classes and students come in the next step.',
      gate && gate.plan !== 'pro' ? ` Showing the ${gate.plan.toUpperCase()} plan.` : ''))
  : null);

// ------------------------------------------------------------------ period filter (Dashboard)
/** Day / Week / Month / Custom range, the class picker, and the arrows to step back and forward. */
export function periodRange(p) {
  const t = today();
  if (p.type === 'day') return { from: p.date, to: p.date, title: longDay(p.date), sub: p.date === t ? 'Today' : p.date === addDays(t, -1) ? 'Yesterday' : 'One day' };
  if (p.type === 'week') {
    const d = new Date(`${p.date}T00:00:00`); const back = (d.getDay() + 6) % 7; // weeks start on Monday
    const from = addDays(p.date, -back); const to = addDays(from, 6);
    return { from, to, title: `${dm(from)} - ${dm(to)} ${to.slice(0, 4)}`, sub: from <= t && t <= to ? 'This week' : 'One week' };
  }
  if (p.type === 'month') {
    const d = new Date(`${p.date}T00:00:00`);
    const from = iso(new Date(d.getFullYear(), d.getMonth(), 1)); const to = iso(new Date(d.getFullYear(), d.getMonth() + 1, 0));
    return { from, to, title: d.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' }), sub: t.slice(0, 7) === from.slice(0, 7) ? 'This month' : 'One month' };
  }
  return { from: p.from, to: p.to, title: `${dm(p.from)} ${p.from.slice(0, 4)} - ${dm(p.to)} ${p.to.slice(0, 4)}`, sub: `${plural(Math.round((new Date(p.to) - new Date(p.from)) / 86400000) + 1, 'day')}` };
}
export function stepPeriod(p, dir) {
  const d = new Date(`${p.date}T00:00:00`);
  if (p.type === 'day') d.setDate(d.getDate() + dir);
  if (p.type === 'week') d.setDate(d.getDate() + 7 * dir);
  if (p.type === 'month') { d.setDate(1); d.setMonth(d.getMonth() + dir); }
  return { ...p, date: iso(d) };
}

// ------------------------------------------------------------------ the Scanned Student card
const BANNER = {
  marked: ['success', 'check', 'Marked Present'],
  late: ['orange', 'late', 'Marked Late'],
  already: ['primary', 'info', 'Already Marked'],
  ask: ['orange', 'warning', 'Fees Due: Mark Present?'],
  blocked: ['danger', 'lock', 'Not Marked: Fees Due'],
  not_in_class: ['danger', 'user-cross', 'Not in This Class'],
  unknown: ['danger', 'danger', 'Student Not Found'],
  closed: ['danger', 'clock', 'Scanning Is Closed'],
  view: ['primary', 'user', 'Student'],
};

/**
 * Who was scanned and what to do next. `res` = the scan answer ({ result, message, card }) or { result: 'view', card }.
 * opts: settings (what to show), onMark(status, force) for Mark Present / Mark Anyway, onPaid() after a payment.
 */
export function scanCard(res, { settings, onMark = null, onPaid = () => {}, onSkip = null } = {}) {
  const [tone, ico, title] = BANNER[res.result] ?? BANNER.view;
  const c = res.card;
  const head = h('div', { class: `at-banner at-${tone}` }, icon(ico, { size: 26 }),
    h('div', { class: 'min-w-0' }, h('div', { class: 'at-banner-title' }, title), h('div', { class: 'text-13' }, res.message ?? '')));
  if (!c) return h('div', { class: 'at-card' }, head, h('div', { class: 'p-4 text-14 sub-text' }, res.result === 'unknown' ? 'Check the card, or type the Student ID on the Barcode tab. The Student ID is printed under the code, for example ST02405.' : ''));

  const s = settings.card;
  const rec = c.record;
  const who = h('div', { class: 'at-who' },
    h('span', { class: 'at-avatar' }, c.student.name.split(/\s+/).slice(0, 2).map((x) => x[0]).join('').toUpperCase()),
    h('div', { class: 'min-w-0' },
      h('div', { class: 'at-name text-truncate' }, c.student.name),
      h('div', { class: 'text-13 sub-text' }, c.student.sid, s.class && c.classes.length ? ` · ${c.classes.join(', ')}` : ''),
      rec ? h('div', { class: 'mt-2 d-flex align-items-center gap-2 flex-wrap' }, statusChip(rec.status, rec.status === 'late' ? `Late ${rec.late_min} min` : null),
        rec.time ? h('span', { class: 'text-12 sub-text' }, `${ampm(rec.time)} · ${rec.method}`) : null) : null));

  const parts = [head, who];
  if (s.fees) {
    const f = c.fees;
    const box = f.total > 0
      ? h('div', { class: 'at-fee at-fee-due' },
        h('div', { class: 'd-flex justify-content-between align-items-start gap-2' },
          h('div', {}, h('div', { class: 'text-12 fw-semibold text-uppercase at-fee-label' }, 'Fees Due'), h('div', { class: 'at-fee-amount' }, lkr(f.total))),
          h('span', { class: 'text-12 at-fee-label text-end' }, plural(f.count, 'month'), h('br'), f.months[0] ? `since ${f.months[0].long}` : '')),
        h('div', { class: 'd-flex flex-wrap gap-1 mt-2' }, f.months.map((m) => h('span', { class: `fe-mchip ${m.status === 'rejected' ? 'rej' : ''}`, title: `${m.long} · ${m.class}: ${lkr(m.net)}` }, `${m.label} · ${m.class}`))),
        f.waiting ? h('div', { class: 'text-12 mt-2 at-fee-label' }, `${plural(f.waiting, 'slip')} (${lkr(f.waiting_amount)}) waiting for you to check`) : null)
      : f.waiting
        ? h('div', { class: 'at-fee at-fee-wait' }, icon('receipt', { size: 20 }), h('div', {}, h('div', { class: 'text-14 fw-semibold' }, 'Slip waiting for you'), h('div', { class: 'text-12' }, `${plural(f.waiting, 'slip')}, ${lkr(f.waiting_amount)}. Check it on Student Fees.`)))
        : h('div', { class: 'at-fee at-fee-ok' }, icon('check', { size: 20 }), h('div', {}, h('div', { class: 'text-14 fw-semibold' }, f.free_card ? 'Free card, nothing to pay' : 'All fees paid'), h('div', { class: 'text-12' }, 'No month is waiting to be paid.')));
    const feeBtns = h('div', { class: 'd-flex gap-2 mt-2' },
      f.total > 0 ? h('button', { type: 'button', class: 'custom-btn flex-grow-1 px-3', onclick: () => recordPayment({ student: c.student.id, select: f.months.map((m) => m.id), reload: onPaid }) }, 'Record Payment') : null,
      h('a', { class: 'custom-btn-outline flex-grow-1 px-3 text-decoration-none', href: `student-fees?student-id=${c.student.id}`, target: '_blank' }, 'Fee Card'),
      f.total > 0 ? h('a', { class: 'custom-btn-outline px-3 text-decoration-none', 'aria-label': 'Send a WhatsApp reminder', href: waLink(c.student.whatsapp, `Dear ${c.student.name.split(' ')[0]}, your class fee for ${f.months.map((m) => m.label).join(', ')} (${lkr(f.total)}) is not paid yet. Please pay at the class or upload the slip in the ENLIXO app. Thank you.`), target: '_blank', title: 'Send a WhatsApp reminder' }, icon('whatsapp', { size: 16 })) : null);
    parts.push(h('div', { class: 'at-sec' }, box, feeBtns));
  }
  if (s.attendance && c.enrolled) {
    const a = c.attendance;
    parts.push(h('div', { class: 'at-sec at-facts' },
      h('div', {}, h('span', { class: 'text-12 sub-text' }, 'This month'), h('b', {}, a.held ? `${a.attended} of ${a.held}` : '-'), h('span', { class: 'text-12 sub-text' }, a.rate != null ? `${a.rate}% of class days` : 'no class days yet')),
      h('div', {}, h('span', { class: 'text-12 sub-text' }, 'Last came'), h('b', {}, a.last ? dm(a.last) : 'Not yet'), h('span', { class: 'text-12 sub-text' }, a.last ? day(a.last).slice(-4) : '')),
      h('div', {}, h('span', { class: 'text-12 sub-text' }, 'Missed in a row'), h('b', { class: a.streak >= 2 ? 'text-dark-red' : '' }, String(a.streak)), h('span', { class: 'text-12 sub-text' }, a.streak >= 2 ? 'follow up' : 'class days'))));
  }
  if (s.phone) parts.push(h('div', { class: 'at-sec d-flex justify-content-between align-items-center' }, cell(c.student.whatsapp, 'WhatsApp'), h('a', { class: 'custom-btn-outline px-4 text-decoration-none', href: waLink(c.student.whatsapp, ''), target: '_blank' }, 'Message')));

  const ownerOnly = h('div', { class: 'at-sec' }, h('div', { class: 'text-13 sub-text d-flex align-items-center gap-2' }, icon('lock', { size: 16 }),
    'Only the tutor can mark a student who owes fees. Record the payment, or ask the tutor.'));
  if (onMark && ['ask', 'blocked'].includes(res.result) && res.can_override === false) {
    parts.push(ownerOnly);
  } else if (onMark && res.result === 'ask') {
    parts.push(h('div', { class: 'at-sec d-flex gap-2' },
      h('button', { type: 'button', class: 'custom-btn flex-grow-1', onclick: () => onMark('present', true) }, 'Mark Present'),
      h('button', { type: 'button', class: 'custom-btn-outline flex-grow-1', onclick: () => onSkip?.() }, 'Do Not Mark')));
  } else if (onMark && res.result === 'blocked') {
    parts.push(h('div', { class: 'at-sec' }, h('div', { class: 'text-12 sub-text mb-2' }, 'Your setting: students who owe fees are not marked until they pay. Record the payment, or mark this student anyway. Only you can do this; assistants cannot.'),
      h('button', { type: 'button', class: 'custom-btn-outline w-100', onclick: () => onMark('present', true) }, 'Mark Present Anyway')));
  }
  return h('div', { class: `at-card at-card-${tone}` }, parts);
}

/** Short beep: high for marked, low for a problem (Settings → Sound on scan). */
export function beep(ok) {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const o = ctx.createOscillator(); const g = ctx.createGain();
    o.frequency.value = ok ? 880 : 220; g.gain.value = 0.08; o.connect(g); g.connect(ctx.destination); o.start(); o.stop(ctx.currentTime + (ok ? 0.15 : 0.35));
  } catch { /* no sound */ }
}

export { h, fill, icon, lkr, short, day, plural, cell, person, panel, field, showErrors, simpleTable, BsModal, Toast, infoTip, EmptyState, waLink, recordPayment };
