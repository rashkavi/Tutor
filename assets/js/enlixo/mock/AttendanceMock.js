// Sample data for the Attendance preview (docs/ATTENDANCE-PLAN.md, step A). Answers the same endpoints the PHP API will
// (/tutor/attendance/..., /tutor/attendance-settings) in the browser. Students, classes and fees come from the shared
// FeesMock, so the fees shown when a student is scanned are the same as on Student Fees and the Fee Card, and a payment
// recorded from the scan card shows up there too. Every other path (/me/access, /tutor/fees/...) goes to FeesMock.
import { FeesMock, CLASSES } from './FeesMock.js';

const DAY = 86400000;
const today = new Date(new Date().toDateString());
const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const TODAY = iso(today);
const addDays = (s, n) => iso(new Date(new Date(`${s}T00:00:00`).getTime() + n * DAY));
const hm = (mins) => { const m = Math.max(0, Math.min(23 * 60 + 55, Math.round(mins / 5) * 5)); return `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`; };
const toMin = (t) => Number(t.slice(0, 2)) * 60 + Number(t.slice(3, 5));
const nowMin = () => { const d = new Date(); return d.getHours() * 60 + d.getMinutes(); };
const nowHm = () => new Date().toTimeString().slice(0, 5);
const label = (key, long = false) => new Date(`${key}-01T00:00:00`).toLocaleDateString('en-GB', long ? { month: 'long', year: 'numeric' } : { month: 'short' });
const dayLabel = (s) => new Date(`${s}T00:00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
function rng(seed) { let s = seed; return () => ((s = (s * 16807) % 2147483647) - 1) / 2147483646; }
// The tutor's name and subject printed on the ID cards (step B: from the tutor profile).
export const TUTOR = { name: 'Mr. K. R. Perera', subject: 'Combined Mathematics', phone: '0771234567' };
const QS = new URLSearchParams(globalThis.location?.search ?? '');
// Preview who is signed in: ?as=assistant = an assistant with "Mark attendance"; add &can=correct for "Correct finished class days".
const OWNER = QS.get('as') !== 'assistant';
const CAN_CORRECT = OWNER || QS.get('can') === 'correct';
const err = (message, details = {}, status = 400, code = 'validation') => Object.assign(new Error(message), { details, status, code });

// When each class meets (weekday 0 = Sunday). Online classes are Zoom classes: joining marks "Joined Online" by itself.
const SLOTS = { 11: [6, '08:00', '12:00'], 12: [0, '14:00', '17:00'], 13: [3, '15:30', '18:30'], 14: [4, '19:00', '21:00'], 15: [2, '16:00', '18:00'] };
const WEEKDAY = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
export const ATTENDED = ['present', 'late', 'joined'];

export class AttendanceMock {
  constructor() {
    this.fees = FeesMock.shared();
    this.settings = {
      methods: { qr: true, barcode: true, manual: true },
      default_method: 'qr',
      card: { class: true, fees: true, attendance: true, phone: false },
      owing: { count: 'overdue', action: 'ask', min_months: 1 },
      sound: true,
      times: { open_before: 30, close_after: 30, late_after: 15, repeat_seconds: 60 },
      auto_absent: true,
      code: 'student_id',
      ids: { prefix: 'ST', digits: 5, next: 2470 },
    };
    const r = rng(77);
    this.sessions = [];
    this.records = new Map(); // `${session}:${student}` -> { status, time, method, late_min, by }
    this.habit = new Map(this.fees.enrolments.map((e) => [e.id, 0.5 + r() * 0.48]));
    let sid = 5001;
    const first = addDays(TODAY, -26 * 7);
    const last = iso(new Date(today.getFullYear(), today.getMonth() + 2, 0)); // end of next month
    for (let d = first; d <= last; d = addDays(d, 1)) {
      if (d === TODAY) continue; // today's classes are made below, around the time the preview is opened
      const wd = new Date(`${d}T00:00:00`).getDay();
      CLASSES.forEach((c) => {
        const [w, start, end] = SLOTS[c.id];
        if (w !== wd) return;
        this.sessions.push({ id: sid++, class: c.id, date: d, start, end, mode: c.mode, cancelled: d < TODAY && r() < 0.03 });
      });
    }
    // Today: one class going on now (scanning open), one later today, one online class that finished this morning.
    const n = nowMin();
    const live = Math.max(0, n - 25);
    this.sessions.push(
      { id: sid++, class: 12, date: TODAY, start: hm(Math.min(live - 300, 7 * 60)), end: hm(Math.min(live - 180, 9 * 60)), mode: 'Online', cancelled: false },
      { id: sid++, class: 13, date: TODAY, start: hm(live), end: hm(live + 180), mode: 'Physical', cancelled: false },
      { id: sid++, class: 11, date: TODAY, start: hm(Math.min(n + 150, 21 * 60)), end: hm(Math.min(n + 270, 23 * 60 + 30)), mode: 'Physical', cancelled: false },
    );
    this.sessions.sort((a, b) => a.date.localeCompare(b.date) || a.start.localeCompare(b.start));

    for (const s of this.sessions) {
      const st = this.state(s);
      if (s.cancelled || st === 'upcoming') continue;
      const roster = this.roster(s);
      roster.forEach((e) => {
        const p = this.habit.get(e.id);
        if (st === 'live') { if (r() > 0.62) return; } // part of the class has come in so far
        const x = r();
        let rec;
        if (s.mode === 'Online') {
          if (x < p) rec = { status: 'joined', time: hm(toMin(s.start) + Math.floor(r() * 12) - 5), method: 'Zoom', late_min: 0, by: 'Zoom' };
          else if (x < p + (1 - p) * 0.55) rec = { status: 'watched', time: '20:10', method: 'App', late_min: 0, by: 'Recording' };
          else rec = { status: 'absent', time: null, method: 'Manual', late_min: 0, by: 'You' };
        } else if (x < p) {
          const late = r() < 0.16 ? 10 + Math.floor(r() * 35) : 0;
          const t = late ? toMin(s.start) + late : toMin(s.start) - Math.floor(r() * 25);
          const m = r();
          rec = { status: late ? 'late' : 'present', time: hm(t), method: m < 0.62 ? 'QR' : m < 0.86 ? 'Barcode' : 'Manual', late_min: late, by: m < 0.86 ? 'Scanner' : 'Nimali (assistant)' };
        } else if (st !== 'live') {
          rec = { status: 'absent', time: null, method: 'Manual', late_min: 0, by: 'System' };
        }
        if (rec) this.records.set(`${s.id}:${e.student}`, rec);
      });
    }

    this.log = [
      { when: `${addDays(TODAY, -3)} 08:12`, by: 'You', what: 'Late after changed from 10 to 15 minutes' },
      { when: `${addDays(TODAY, -12)} 17:40`, by: 'You', what: 'Turned on Barcode scanning' },
      { when: `${addDays(TODAY, -40)} 10:05`, by: 'You', what: 'Students who owe fees: changed from "Just show" to "Ask before marking"' },
    ];
  }

  async handle(method, path, body, signal) {
    const [route, query] = path.split('?');
    if (!route.startsWith('/tutor/attendance')) {
      return this.fees.handle(method, path, body, signal);
    }
    await new Promise((res) => setTimeout(res, 80 + Math.random() * 160));
    const q = Object.fromEntries(new URLSearchParams(query ?? ''));
    try {
      return { status: 200, payload: { ok: true, ...this.#route(method, route, q, body ?? {}) } };
    } catch (e) {
      return { status: e.status ?? 400, payload: { ok: false, error: { code: e.code ?? 'validation', message: e.message, details: e.details ?? {} } } };
    }
  }

  // ---------------------------------------------------------------- helpers
  cls(id) { return CLASSES.find((c) => c.id === Number(id)); }
  student(id) { return this.fees.students.find((s) => s.id === Number(id)); }
  state(s) {
    if (s.cancelled) return 'cancelled';
    if (s.date < TODAY) return 'done';
    if (s.date > TODAY) return 'upcoming';
    const n = nowMin();
    if (n < toMin(s.start) - (this.settings?.times?.open_before ?? 30)) return 'upcoming';
    if (n < toMin(s.end)) return 'live';
    return 'done';
  }
  roster(s) { return this.fees.enrolments.filter((e) => e.class === s.class && e.status === 'active' && e.since <= s.date.slice(0, 7)); }
  rec(s, studentId) { return this.records.get(`${s.id}:${studentId}`) ?? null; }
  #inRange(q) {
    const from = q.from || addDays(TODAY, -29); const to = q.to || TODAY;
    return { from, to, list: this.sessions.filter((s) => s.date >= from && s.date <= to && (!q.class || s.class === Number(q.class))) };
  }
  #page(rows, q, search) {
    const term = (q.q ?? '').toLowerCase();
    let list = term ? rows.filter((r) => search(r).toLowerCase().includes(term)) : rows;
    if (q.sort) { const dir = q.dir === 'desc' ? -1 : 1; const v = (r) => (typeof r[q.sort] === 'object' ? r[q.sort]?.name : r[q.sort]); list = [...list].sort((a, b) => (v(a) > v(b) ? dir : v(a) < v(b) ? -dir : 0)); }
    const per = Number(q.per_page ?? 10); const page = Number(q.page ?? 1);
    return { data: list.slice((page - 1) * per, page * per), meta: { total: list.length, page, per_page: per, pages: Math.max(1, Math.ceil(list.length / per)) } };
  }
  /** Fees the student still owes, by the "count as owing" setting (overdue only, or every unpaid month incl. this one). */
  owed(studentId, classId = null) {
    const any = this.settings.owing.count === 'any';
    const fees = this.fees.fees.filter((f) => f.student === Number(studentId) && (!classId || f.class === Number(classId)) && ['unpaid', 'rejected'].includes(f.status) && f.net > 0 && (any || f.due < TODAY));
    const waiting = this.fees.fees.filter((f) => f.student === Number(studentId) && (!classId || f.class === Number(classId)) && f.status === 'waiting');
    const free = this.fees.enrolments.some((e) => e.student === Number(studentId) && (!classId || e.class === Number(classId)) && e.free === 'free');
    return {
      total: fees.reduce((a, f) => a + f.net, 0), count: fees.length,
      months: fees.map((f) => ({ id: f.id, month: f.month, label: label(f.month), long: label(f.month, true), net: f.net, status: f.status === 'unpaid' && f.due < TODAY ? 'overdue' : f.status, class: this.cls(f.class).short })),
      waiting: waiting.length, waiting_amount: waiting.reduce((a, f) => a + f.net, 0), free_card: free,
    };
  }
  #counts(s) {
    const c = { expected: 0, present: 0, late: 0, joined: 0, watched: 0, absent: 0, unmarked: 0, methods: { QR: 0, Barcode: 0, Manual: 0 } };
    this.roster(s).forEach((e) => {
      c.expected += 1;
      const r = this.rec(s, e.student);
      if (!r) { c.unmarked += 1; return; }
      c[r.status] += 1;
      if (['present', 'late'].includes(r.status) && c.methods[r.method] != null) c.methods[r.method] += 1;
    });
    c.attended = c.present + c.late + c.joined;
    c.rate = c.expected ? Math.round((c.attended / c.expected) * 100) : 0;
    return c;
  }
  #session(s) {
    const c = this.cls(s.class);
    return { id: s.id, date: s.date, day: WEEKDAY[new Date(`${s.date}T00:00:00`).getDay()], start: s.start, end: s.end, mode: s.mode, state: this.state(s), class: { id: c.id, name: c.name, short: c.short }, ...this.#counts(s) };
  }
  #streak(e, upto) {
    const done = this.sessions.filter((s) => s.class === e.class && !s.cancelled && s.date <= upto && this.state(s) === 'done' && e.since <= s.date.slice(0, 7));
    let n = 0;
    for (let i = done.length - 1; i >= 0; i -= 1) { const r = this.rec(done[i], e.student); if (r && ATTENDED.includes(r.status)) break; n += 1; }
    const lastS = [...done].reverse().find((s) => ATTENDED.includes(this.rec(s, e.student)?.status));
    return { streak: n, last: lastS?.date ?? null };
  }
  #person(st) { return { id: st.id, sid: st.sid, name: st.name, whatsapp: st.whatsapp }; }

  /** What the scan card shows about one student for one class day. */
  card(studentId, s) {
    const st = this.student(studentId);
    const enrol = s ? this.fees.enrolments.find((e) => e.student === st.id && e.class === s.class && e.status === 'active') : null;
    const month = TODAY.slice(0, 7);
    const monthDays = enrol ? this.sessions.filter((x) => x.class === enrol.class && !x.cancelled && x.date.startsWith(month) && this.state(x) !== 'upcoming') : [];
    const att = monthDays.filter((x) => ATTENDED.includes(this.rec(x, st.id)?.status)).length;
    const { streak, last } = enrol ? this.#streak(enrol, TODAY) : { streak: 0, last: null };
    return {
      student: { ...this.#person(st), joined: st.joined },
      classes: this.fees.enrolments.filter((e) => e.student === st.id && e.status === 'active').map((e) => this.cls(e.class).short),
      enrolled: !!enrol,
      record: s ? this.rec(s, st.id) : null,
      fees: this.owed(st.id),
      attendance: { held: monthDays.length, attended: att, rate: monthDays.length ? Math.round((att / monthDays.length) * 100) : null, last, streak },
    };
  }

  // ---------------------------------------------------------------- routes
  #route(method, route, q, b) {
    let m;
    if (method === 'GET' && route === '/tutor/attendance/overview') return { data: this.overview(q) };
    if (method === 'GET' && route === '/tutor/attendance/sessions') {
      const { list } = this.#inRange(q);
      let rows = list.map((s) => this.#session(s));
      if (q.state) rows = rows.filter((r) => r.state === q.state);
      if (q.mode) rows = rows.filter((r) => r.mode === q.mode);
      const rank = { live: 0, done: 1, cancelled: 1, upcoming: 2 }; // going on now, then the latest finished, then the next ones
      rows.sort((a, b2) => rank[a.state] - rank[b2.state] || (a.state === 'upcoming' ? a.date.localeCompare(b2.date) || a.start.localeCompare(b2.start) : b2.date.localeCompare(a.date) || b2.start.localeCompare(a.start)));
      return this.#page(rows, q, (r) => `${r.class.name} ${r.date} ${r.day}`);
    }
    if (method === 'GET' && route === '/tutor/attendance/students') return this.#page(this.studentRows(q), q, (r) => `${r.student.name} ${r.student.sid} ${r.student.whatsapp} ${r.class.name}`);
    if (method === 'GET' && route === '/tutor/attendance/today') return { data: this.todayList() };
    if ((m = route.match(/^\/tutor\/attendance\/sessions\/(\d+)$/)) && method === 'GET') return { data: this.detail(Number(m[1])) };
    if ((m = route.match(/^\/tutor\/attendance\/sessions\/(\d+)\/scan$/)) && method === 'POST') return { data: this.scan(Number(m[1]), b) };
    if ((m = route.match(/^\/tutor\/attendance\/sessions\/(\d+)\/mark$/)) && method === 'POST') return { data: this.mark(Number(m[1]), b) };
    if ((m = route.match(/^\/tutor\/attendance\/sessions\/(\d+)\/finish$/)) && method === 'POST') return { data: this.finish(Number(m[1])) };
    if ((m = route.match(/^\/tutor\/attendance\/sessions\/(\d+)\/students\/(\d+)$/)) && method === 'GET') return { data: this.card(Number(m[2]), this.sessions.find((s) => s.id === Number(m[1]))) };
    if (method === 'GET' && route === '/tutor/attendance-settings') return { data: { settings: this.settings, log: this.log, classes: CLASSES.map((c) => ({ id: c.id, name: c.name, schedule: `${WEEKDAY[SLOTS[c.id][0]]} ${SLOTS[c.id][1]}`, mode: c.mode })) } };
    if (method === 'PUT' && route === '/tutor/attendance-settings') return { data: this.saveSettings(b) };
    if (method === 'GET' && route === '/tutor/attendance/id-cards') {
      if (!this.fees.has('id.cards')) throw Object.assign(err('Student ID Cards are on GROW.', { feature: 'id.cards' }, 403), { code: 'feature_locked' });
      const ens = this.fees.enrolments.filter((e) => e.status === 'active' && (!q.class || e.class === Number(q.class)));
      const ids = [...new Set(ens.map((e) => e.student))];
      const students = ids.map((id) => { const st = this.student(id); return { ...this.#person(st), classes: this.fees.enrolments.filter((e) => e.student === id && e.status === 'active').map((e) => this.cls(e.class).short) }; }).sort((a, z) => a.name.localeCompare(z.name));
      return { data: { tutor: TUTOR, students } };
    }
    throw err(`No sample data for ${method} ${route}.`, {}, 404);
  }

  overview(q) {
    const { from, to, list } = this.#inRange(q);
    const done = list.filter((s) => this.state(s) === 'done');
    const sum = (ss) => ss.reduce((a, s) => { const c = this.#counts(s); ['expected', 'present', 'late', 'joined', 'watched', 'absent', 'attended'].forEach((k) => { a[k] += c[k]; }); return a; }, { expected: 0, present: 0, late: 0, joined: 0, watched: 0, absent: 0, attended: 0 });
    const t = sum(done);
    const rate = (x) => (x.expected ? Math.round((x.attended / x.expected) * 100) : 0);
    const span = Math.round((new Date(`${to}T00:00:00`) - new Date(`${from}T00:00:00`)) / DAY) + 1;
    const prev = sum(this.sessions.filter((s) => s.date >= addDays(from, -span) && s.date <= addDays(from, -1) && (!q.class || s.class === Number(q.class)) && this.state(s) === 'done'));
    // trend bars: the rate of the 8 periods of the same length up to this one
    const bars = Array.from({ length: 8 }, (_, i) => {
      const a = addDays(from, -(7 - i) * span); const z = addDays(to, -(7 - i) * span);
      const x = sum(this.sessions.filter((s) => s.date >= a && s.date <= z && (!q.class || s.class === Number(q.class)) && this.state(s) === 'done'));
      return { label: span === 1 ? dayLabel(a) : `${dayLabel(a)} - ${dayLabel(z)}`, value: rate(x) };
    });
    // chart: one class per bar for a single day, one bar per day up to a month, one bar per week for longer ranges
    let groups;
    if (span === 1) groups = done.map((s) => ({ label: this.cls(s.class).short, list: [s] }));
    else if (span <= 31) groups = Array.from({ length: span }, (_, i) => { const d = addDays(from, i); return { label: dayLabel(d), list: done.filter((s) => s.date === d) }; });
    else {
      groups = [];
      for (let d = from; d <= to; d = addDays(d, 7)) { const z = addDays(d, 6) > to ? to : addDays(d, 6); groups.push({ label: dayLabel(d), list: done.filter((s) => s.date >= d && s.date <= z) }); }
    }
    const chart = { labels: groups.map((g) => g.label), present: [], late: [], joined: [], watched: [], absent: [] };
    groups.forEach((g) => { const x = sum(g.list); ['present', 'late', 'joined', 'watched', 'absent'].forEach((k) => chart[k].push(x[k])); });
    const byClass = CLASSES.filter((c) => !q.class || c.id === Number(q.class)).map((c) => {
      const ss = done.filter((s) => s.class === c.id); const x = sum(ss);
      return { id: c.id, name: c.name, short: c.short, mode: c.mode, held: ss.length, rate: rate(x), absent: x.absent, schedule: `${WEEKDAY[SLOTS[c.id][0]]} ${SLOTS[c.id][1]}` };
    });
    const follow = this.fees.enrolments.filter((e) => e.status === 'active' && (!q.class || e.class === Number(q.class))).map((e) => {
      const { streak, last } = this.#streak(e, to);
      const st = this.student(e.student); const o = this.owed(e.student, e.class);
      return { student: this.#person(st), class: { id: e.class, short: this.cls(e.class).short, name: this.cls(e.class).name }, streak, last, owed: o.total, owed_months: o.count };
    }).filter((x) => x.streak >= 2).sort((a, b) => b.streak - a.streak || b.owed - a.owed);
    const live = list.filter((s) => this.state(s) === 'live').map((s) => this.#session(s));
    return {
      from, to, span,
      classes: CLASSES.map((c) => ({ id: c.id, name: c.name, short: c.short })),
      stats: {
        rate: { value: rate(t), before: rate(prev), bars },
        held: { value: done.length, cancelled: list.filter((s) => s.cancelled).length, upcoming: list.filter((s) => this.state(s) === 'upcoming').length },
        present: { value: t.present + t.joined, physical: t.present, online: t.joined },
        late: { value: t.late, share: t.attended ? Math.round((t.late / t.attended) * 100) : 0 },
        absent: { value: t.absent, students: new Set(done.flatMap((s) => this.roster(s).filter((e) => this.rec(s, e.student)?.status === 'absent').map((e) => e.student))).size },
        watched: { value: t.watched, share: t.watched + t.absent ? Math.round((t.watched / (t.watched + t.absent)) * 100) : 0 },
      },
      split: { present: t.present, late: t.late, joined: t.joined, watched: t.watched, absent: t.absent },
      chart, by_class: byClass, follow_up: follow.slice(0, 8), follow_total: follow.length, live,
      counts: { sessions: list.length, students: new Set(this.fees.enrolments.filter((e) => !q.class || e.class === Number(q.class)).map((e) => e.student)).size },
    };
  }

  studentRows(q) {
    const { to, list } = this.#inRange(q);
    const done = list.filter((s) => this.state(s) === 'done');
    let rows = this.fees.enrolments.filter((e) => e.status === 'active' && (!q.class || e.class === Number(q.class))).map((e) => {
      const mine = done.filter((s) => s.class === e.class && e.since <= s.date.slice(0, 7));
      const c = { held: mine.length, attended: 0, present: 0, late: 0, joined: 0, watched: 0, absent: 0 };
      mine.forEach((s) => { const r = this.rec(s, e.student); if (r) { c[r.status] += 1; if (ATTENDED.includes(r.status)) c.attended += 1; } });
      const { streak, last } = this.#streak(e, to);
      const o = this.owed(e.student, e.class);
      const st = this.student(e.student); const cl = this.cls(e.class);
      return { id: e.id, student: this.#person(st), class: { id: cl.id, name: cl.name, short: cl.short, mode: cl.mode }, ...c, rate: c.held ? Math.round((c.attended / c.held) * 100) : null, streak, last, owed: o.total, owed_months: o.count, name: st.name };
    }).filter((r) => r.held > 0);
    if (q.band) rows = rows.filter((r) => (q.band === 'low' ? r.rate < 50 : q.band === 'mid' ? r.rate >= 50 && r.rate < 80 : r.rate >= 80));
    if (q.owes === 'yes') rows = rows.filter((r) => r.owed > 0);
    if (q.owes === 'no') rows = rows.filter((r) => r.owed === 0);
    if (q.streak === 'yes') rows = rows.filter((r) => r.streak >= 2);
    if (!q.sort) rows.sort((a, b) => a.rate - b.rate || a.name.localeCompare(b.name));
    return rows;
  }

  todayList() {
    const recent = this.sessions.filter((s) => s.date < TODAY && s.date >= addDays(TODAY, -7) && !s.cancelled).map((s) => this.#session(s)).reverse();
    return { today: this.sessions.filter((s) => s.date === TODAY).map((s) => this.#session(s)), recent, settings: this.settings, now: nowHm() };
  }

  detail(id) {
    const s = this.sessions.find((x) => x.id === id);
    if (!s) throw err('This class day was not found.', {}, 404);
    const out = this.#session(s);
    out.roster = this.roster(s).map((e) => {
      const st = this.student(e.student); const o = this.owed(e.student);
      return { student: this.#person(st), record: this.rec(s, e.student), owed: o.total, owed_months: o.count, waiting: o.waiting, free_card: o.free_card };
    }).sort((a, b) => a.student.name.localeCompare(b.student.name));
    out.window = { opens: hm(toMin(s.start) - this.settings.times.open_before), closes: hm(toMin(s.end) + this.settings.times.close_after) };
    out.settings = this.settings;
    out.owner = OWNER;
    out.can_correct = CAN_CORRECT;
    out.sample_codes = out.roster.filter((x) => !x.record).slice(0, 40).map((x) => x.student.sid);
    return out;
  }

  #setRec(s, studentId, status, method) {
    if (!status) { this.records.delete(`${s.id}:${studentId}`); return null; }
    let late = 0; const time = nowHm();
    if (status === 'present' && s.date === TODAY && this.state(s) === 'live') {
      const after = nowMin() - toMin(s.start);
      if (after > this.settings.times.late_after) { status = 'late'; late = after; }
    }
    const rec = { status, time: ['absent'].includes(status) ? null : (s.date === TODAY ? time : s.start), method, late_min: late, by: method === 'Manual' ? 'You' : 'Scanner' };
    this.records.set(`${s.id}:${studentId}`, rec);
    return rec;
  }

  scan(id, b) {
    const s = this.sessions.find((x) => x.id === id);
    const code = String(b.code ?? '').trim().toUpperCase();
    if (!code) throw err('Scan a student card or type the Student ID.', { code: 'Type the Student ID, for example ST02405.' });
    if (this.state(s) !== 'live') return { result: 'closed', message: `Scanning is closed for this class day. Use Manual to change a mark.` };
    const st = this.fees.students.find((x) => x.sid === code || String(x.id) === code);
    if (!st) return { result: 'unknown', code, message: `No student has the code "${code}".` };
    const card = this.card(st.id, s);
    if (!card.enrolled) return { result: 'not_in_class', message: `${st.name} is not in ${this.cls(s.class).short}.`, card };
    if (card.record && ATTENDED.includes(card.record.status)) return { result: 'already', message: `${st.name} was already marked at ${card.record.time}.`, card };
    const o = this.settings.owing;
    const owes = card.fees.count >= o.min_months && card.fees.total > 0;
    if (owes && b.force && !OWNER) throw err('Only the tutor can mark a student who owes fees.', {}, 403, 'owner_only');
    if (owes && !OWNER && o.action !== 'show') return { result: 'blocked', message: `Not marked: ${st.name} owes fees. Only the tutor can mark this student.`, card, can_override: false };
    if (owes && o.action === 'ask' && !b.force) return { result: 'ask', message: `${st.name} owes ${card.fees.count} month${card.fees.count === 1 ? '' : 's'}. Mark present?`, card, can_override: true };
    if (owes && o.action === 'block' && !b.force) return { result: 'blocked', message: `Not marked: ${st.name} must pay the fees first.`, card, can_override: true };
    const rec = this.#setRec(s, st.id, 'present', b.method === 'barcode' ? 'Barcode' : b.method === 'manual' ? 'Manual' : 'QR');
    return { result: rec.status === 'late' ? 'late' : 'marked', message: rec.status === 'late' ? `${st.name}: Late, ${rec.late_min} min after the start` : `${st.name}: Present`, card: this.card(st.id, s), counts: this.#counts(s) };
  }

  mark(id, b) {
    const s = this.sessions.find((x) => x.id === id);
    if (this.state(s) === 'upcoming') throw err('This class has not started yet.');
    if (this.state(s) === 'done' && !CAN_CORRECT) throw err('Correcting a finished class day needs the permission "Correct finished class days". Ask the tutor to give it to your role in Settings → Roles.', {}, 403, 'forbidden');
    const ids = (b.students ?? [b.student]).map(Number).filter(Boolean);
    if (!ids.length) throw err('Choose a student.');
    if (b.status && !['present', 'late', 'absent', 'joined', 'watched'].includes(b.status)) throw err('Choose Present, Late or Absent.');
    const o = this.settings.owing;
    if (b.status && ATTENDED.includes(b.status) && (o.action === 'block' || !OWNER) && o.action !== 'show' && ids.length === 1 && (!b.force || !OWNER)) {
      const owed = this.owed(ids[0]);
      if (owed.count >= o.min_months && owed.total > 0) throw err(OWNER ? 'This student must pay the fees first.' : 'This student owes fees. Only the tutor can mark them.', { card: this.card(ids[0], s), can_override: OWNER }, 409, 'fees_due');
    }
    ids.forEach((sid) => {
      if (b.status === 'late' && !(b.late_min > 0)) { this.records.set(`${s.id}:${sid}`, { status: 'late', time: nowHm(), method: 'Manual', late_min: Math.max(1, nowMin() - toMin(s.start)), by: 'You' }); return; }
      this.#setRec(s, sid, b.status, 'Manual');
    });
    return { counts: this.#counts(s), card: ids.length === 1 ? this.card(ids[0], s) : null };
  }

  finish(id) {
    const s = this.sessions.find((x) => x.id === id);
    let n = 0;
    this.roster(s).forEach((e) => { if (!this.rec(s, e.student)) { this.records.set(`${s.id}:${e.student}`, { status: 'absent', time: null, method: 'Manual', late_min: 0, by: 'You' }); n += 1; } });
    return { marked_absent: n, counts: this.#counts(s) };
  }

  saveSettings(b) {
    const errors = {};
    const s = structuredClone(this.settings);
    if (b.part === 'methods') {
      if (!b.methods || !Object.values(b.methods).some(Boolean)) errors.methods = 'Keep at least one way to mark attendance switched on.';
      else if (!b.methods[b.default_method]) errors.default_method = 'Choose a way that is switched on.';
      if (!Object.keys(errors).length) { s.methods = b.methods; s.default_method = b.default_method; s.code = b.code ?? s.code; }
    } else if (b.part === 'card') {
      s.card = { ...s.card, ...b.card }; s.owing = { ...s.owing, ...b.owing }; s.sound = b.sound ?? s.sound;
    } else if (b.part === 'times') {
      const t = b.times ?? {};
      const check = (k, lo, hi, msg) => { const v = Number(t[k]); if (!Number.isFinite(v) || v < lo || v > hi) errors[k] = msg; };
      check('open_before', 0, 180, 'Enter minutes from 0 to 180, for example 30.');
      check('close_after', 0, 180, 'Enter minutes from 0 to 180, for example 30.');
      check('late_after', 0, 120, 'Enter minutes from 0 to 120, for example 15.');
      check('repeat_seconds', 5, 600, 'Enter seconds from 5 to 600, for example 60.');
      if (!Object.keys(errors).length) { s.times = Object.fromEntries(Object.entries(t).map(([k, v]) => [k, Number(v)])); s.auto_absent = !!b.auto_absent; }
    } else if (b.part === 'ids') {
      const prefix = String(b.ids?.prefix ?? '').trim().toUpperCase();
      const digits = Number(b.ids?.digits); const next = Number(b.ids?.next);
      if (!/^[A-Z0-9/\-]{1,10}$/.test(prefix)) errors.prefix = 'Use 1 to 10 letters, numbers, / or -, for example ST or MATHS-.';
      if (![3, 4, 5, 6].includes(digits)) errors.digits = 'Choose 3 to 6 digits.';
      // the highest number already given with this prefix (sample students are ST02400 - ST02469)
      const used = prefix === 'ST' ? 2469 : 0;
      if (!Number.isInteger(next) || next < 1) errors.next = 'Enter a whole number, for example 1.';
      else if (next <= used) errors.next = `ST${String(used).padStart(5, '0')} is already used. Start from ${used + 1} or higher.`;
      else if (String(next).length > digits) errors.next = `${next} has more than ${digits} digits. Choose more digits or a smaller number.`;
      if (!Object.keys(errors).length) s.ids = { prefix, digits, next };
    }
    if (Object.keys(errors).length) throw err('Please check the highlighted fields.', errors);
    this.settings = s;
    this.log.unshift({ when: `${TODAY} ${nowHm()}`, by: 'You', what: b.summary ?? 'Attendance settings saved' });
    return { saved: true, settings: s };
  }
}
