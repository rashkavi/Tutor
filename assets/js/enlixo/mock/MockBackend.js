// Demo backend that answers the same endpoints as the PHP API, in the browser.
// Used when the page runs with ?mock=1 or without a server. Choose the package with ?plan=start|grow|pro.
// Adds 300–800 ms latency so loading states are visible.

const SUBJECTS = ['Combined Maths', 'Physics', 'Chemistry', 'Biology', 'ICT', 'English'];
const FIRST = ['Nimal', 'Kasun', 'Tharushi', 'Sanduni', 'Ishara', 'Dinuka', 'Hiruni', 'Pasindu', 'Nethmi', 'Ravindu', 'Sachini', 'Yasiru', 'Amaya', 'Kavindu', 'Dulani', 'Chamod', 'Nadeesha', 'Lahiru', 'Senuri', 'Thisara'];
const LAST = ['Perera', 'Fernando', 'Silva', 'Jayasinghe', 'Wickramasinghe', 'Bandara', 'Gunawardena', 'Rathnayake', 'Herath', 'Dissanayake'];
const COLORS = ['#4f46e5', '#0ea5e9', '#16a34a', '#db2777', '#ea580c', '#7c3aed'];

function rng(seed) {
  let s = seed;
  return () => ((s = (s * 16807) % 2147483647) - 1) / 2147483646;
}

export class MockBackend {
  constructor(catalog, { plan = 'start' } = {}) {
    this.catalog = catalog;
    this.plan = plan;
    const r = rng(42);
    this.classes = [
      { id: 1, name: '2026 A/L Combined Maths – Theory', subject: 'Combined Maths', medium: 'Sinhala', exam: 'A/L', tutor: 'Mr. Ruwan Perera', schedule: 'Sat 8:00 AM', color: COLORS[0] },
      { id: 2, name: '2026 A/L Physics – Revision', subject: 'Physics', medium: 'English', exam: 'A/L', tutor: 'Mr. Ruwan Perera', schedule: 'Sun 2:00 PM', color: COLORS[1] },
      { id: 3, name: 'Grade 11 ICT', subject: 'ICT', medium: 'Sinhala', exam: 'O/L', tutor: 'Ms. Dilani Silva', schedule: 'Wed 4:00 PM', color: COLORS[2] },
      { id: 4, name: '2027 A/L Chemistry – Theory', subject: 'Chemistry', medium: 'Sinhala', exam: 'A/L', tutor: 'Mr. Ruwan Perera', schedule: 'Fri 3:30 PM', color: COLORS[3] },
    ];
    this.students = Array.from({ length: 64 }, (_, i) => {
      const name = `${FIRST[Math.floor(r() * FIRST.length)]} ${LAST[Math.floor(r() * LAST.length)]}`;
      const cls = this.classes[Math.floor(r() * this.classes.length)];
      return {
        id: 1000 + i,
        student_no: `EN${String(2400 + i).padStart(5, '0')}`,
        name,
        phone: `07${Math.floor(r() * 9)}${String(Math.floor(r() * 1e7)).padStart(7, '0')}`,
        class_id: cls.id,
        class_name: cls.name,
        status: r() > 0.15 ? 'active' : 'inactive',
        payment: ['paid', 'paid', 'paid', 'pending', 'overdue'][Math.floor(r() * 5)],
        joined: new Date(2026, Math.floor(r() * 10), 1 + Math.floor(r() * 27)).toISOString().slice(0, 10),
      };
    });
    this.roles = JSON.parse(localStorage.getItem('enlixo.mock.roles') ?? 'null') ?? [
      { id: 11, name: 'Class Assistant', description: 'Marks attendance and helps students', is_system: false, permissions: ['students.view', 'classes.view', 'attendance.mark', 'recordings.view', 'tutes.manage'], user_count: 2 },
      { id: 12, name: 'Cashier', description: 'Handles fee payments', is_system: false, permissions: ['students.view', 'payments.view', 'payments.approve'], user_count: 1 },
    ];
  }

  async handle(method, path, body, signal) {
    await new Promise((resolve, reject) => {
      const t = setTimeout(resolve, 300 + Math.random() * 500);
      signal?.addEventListener('abort', () => { clearTimeout(t); reject(new DOMException('Aborted', 'AbortError')); });
    });
    const [route, qs] = path.split('?');
    const q = Object.fromEntries(new URLSearchParams(qs ?? ''));
    try {
      return { status: 200, payload: { ok: true, ...this.#route(method, route, q, body ?? {}) } };
    } catch (e) {
      if (e.status) return { status: e.status, payload: { ok: false, error: { code: e.code, message: e.message, details: e.details ?? {} } } };
      throw e;
    }
  }

  #route(method, route, q, body) {
    let m;
    if (method === 'GET' && route === '/me/access') return { data: this.access() };
    if (method === 'GET' && route === '/tutor/students') return this.#list(this.students, q, ['name', 'student_no', 'phone'], { class: 'class_id', status: 'status', payment: 'payment' });
    if (method === 'GET' && route === '/tutor/classes') return { data: this.classes };
    if (method === 'GET' && route === '/permissions') return { data: this.permissionModules() };
    if (route === '/tutor/roles' && method === 'GET') { this.#require('staff.custom_roles'); return { data: [this.#ownerRole(), ...this.roles] }; }
    if (route === '/tutor/roles' && method === 'POST') { this.#require('staff.custom_roles'); return { data: this.#saveRole(null, body) }; }
    if ((m = route.match(/^\/tutor\/roles\/(\d+)$/))) {
      this.#require('staff.custom_roles');
      if (method === 'PUT') return { data: this.#saveRole(Number(m[1]), body) };
      if (method === 'DELETE') { this.roles = this.roles.filter((r) => r.id !== Number(m[1])); this.#persist(); return { data: null }; }
    }
    if (method === 'GET' && route === '/student/classes') return { data: this.classes.map((c) => ({ ...c, next_session: this.timetable(c.id).find((s) => s.upcoming) ?? null, recordings: this.recordings(c.id).length })) };
    if ((m = route.match(/^\/student\/classes\/(\d+)$/))) return { data: this.classes.find((c) => c.id === Number(m[1])) ?? this.#err(404, 'not_found', 'Class not found') };
    if ((m = route.match(/^\/student\/classes\/(\d+)\/timetable$/))) return this.#list(this.timetable(Number(m[1])), q, ['title'], { when: 'when' }, 50);
    if ((m = route.match(/^\/student\/classes\/(\d+)\/recordings$/))) return this.#list(this.recordings(Number(m[1])).map((r) => ({ ...r, status: this.watchStatus(r) })), q, ['title'], { month: 'month' }, 50);
    if ((m = route.match(/^\/student\/recordings\/(\w+)\/(\d+)\/status$/))) return { data: this.watchStatus(this.#rec(Number(m[2]))) };
    if ((m = route.match(/^\/student\/recordings\/(\w+)\/(\d+)\/start$/))) return { data: this.#start(this.#rec(Number(m[2]))) };
    if (route === '/student/recordings/heartbeat') return { data: this.#heartbeat(body) };
    return this.#err(404, 'not_found', `Mock has no ${method} ${route}`);
  }

  // ----- packages -----
  access() {
    const features = this.catalog.features.map((f) => {
      const unlocked = f.plans.includes(this.plan);
      return { key: f.key, name: f.name, category: f.category, description: f.description, unlocked, required_plan: unlocked ? null : ['start', 'grow', 'pro'].find((p) => f.plans.includes(p)) ?? null, addon: f.addon ?? null, plans: f.plans };
    });
    const unlocked = new Set(features.filter((f) => f.unlocked).map((f) => f.key));
    return {
      plan: { key: this.plan, ...this.catalog.plans[this.plan] },
      plans: this.catalog.plans,
      students: { count: { start: 42, grow: 118, pro: 214 }[this.plan], limit: this.catalog.plans[this.plan].student_limit },
      features,
      permissions: this.catalog.permissions.filter((p) => !p.feature || unlocked.has(p.feature)).map((p) => p.key),
    };
  }

  #require(feature) {
    if (!this.access().features.find((f) => f.key === feature)?.unlocked) {
      const f = this.catalog.features.find((x) => x.key === feature);
      this.#err(402, 'feature_locked', `${f.name} is not included in your package.`, { feature, required_plan: ['start', 'grow', 'pro'].find((p) => f.plans.includes(p)) });
    }
  }

  // ----- roles -----
  permissionModules() {
    const unlocked = new Set(this.access().features.filter((f) => f.unlocked).map((f) => f.key));
    const modules = new Map();
    for (const p of this.catalog.permissions) {
      if (!modules.has(p.module)) modules.set(p.module, []);
      modules.get(p.module).push({ key: p.key, name: p.name, feature: p.feature, locked: !!p.feature && !unlocked.has(p.feature) });
    }
    return [...modules].map(([module, permissions]) => ({ module, permissions }));
  }

  #ownerRole() {
    return { id: 0, name: 'Owner', description: 'You. Full access to everything in your package.', is_system: true, permissions: this.access().permissions, user_count: 1 };
  }

  #saveRole(id, body) {
    const name = String(body.name ?? '').trim();
    const perms = [...new Set(body.permissions ?? [])];
    const fields = {};
    if (!name) fields.name = 'Give the role a name (up to 60 characters).';
    else if (name.toLowerCase() === 'owner' || this.roles.some((r) => r.id !== id && r.name.toLowerCase() === name.toLowerCase())) fields.name = 'A role with this name already exists.';
    if (!perms.length) fields.permissions = 'Pick at least one thing this role can do.';
    if (Object.keys(fields).length) this.#err(422, 'validation_failed', 'Please fix the highlighted fields.', { fields });
    let role = this.roles.find((r) => r.id === id);
    if (role) Object.assign(role, { name, description: body.description || null, permissions: perms });
    else this.roles.push(role = { id: Date.now() % 100000, name, description: body.description || null, is_system: false, permissions: perms, user_count: 0 });
    this.#persist();
    return role;
  }

  #persist() { localStorage.setItem('enlixo.mock.roles', JSON.stringify(this.roles)); }

  // ----- student portal -----
  timetable(classId) {
    const cls = this.classes.find((c) => c.id === classId);
    const today = new Date(); today.setHours(0, 0, 0, 0);
    return Array.from({ length: 10 }, (_, i) => {
      const weekday = [6, 0, 3, 5][classId - 1]; // Sat, Sun, Wed, Fri: matches `schedule`
      const d = new Date(today); d.setDate(d.getDate() + ((weekday - today.getDay() + 7) % 7) + (i - 5) * 7);
      const online = (i + classId) % 3 !== 0;
      const status = i === 7 ? 'postponed' : 'active';
      return {
        id: classId * 100 + i, date: d.toISOString().slice(0, 10), start: ['08:00', '14:00', '16:00', '15:30'][classId - 1], end: ['10:00', '16:00', '18:00', '17:30'][classId - 1],
        title: `${cls.subject} – Lesson ${i + 12}`, type: online ? 'online' : 'physical', status,
        upcoming: d >= today, when: d >= today ? 'upcoming' : 'past',
        zoom_ready: online && d >= today && status === 'active', has_recording: online && d < today,
      };
    }).sort((a, b) => (a.upcoming === b.upcoming ? (a.upcoming ? a.date.localeCompare(b.date) : b.date.localeCompare(a.date)) : a.upcoming ? -1 : 1));
  }

  recordings(classId) {
    const cls = this.classes.find((c) => c.id === classId);
    const months = ['2026-11', '2026-10', '2026-09'];
    const out = [];
    months.forEach((month, mi) => {
      for (let i = 0; i < 3; i++) {
        const id = classId * 100 + mi * 10 + i;
        out.push({ kind: 'lecture', id, class_id: classId, month, title: `${cls.subject} – ${['Theory', 'Paper class', 'Revision'][i]} (Week ${4 - i})`, duration_seconds: (75 + ((id * 7) % 60)) * 60, added: `${month}-${String(8 - i * 3).padStart(2, '0')}`, unpaid_month: mi === 0 && classId === 3 });
      }
    });
    out.unshift({ kind: 'lecture', id: classId * 1000 + 1, class_id: classId, month: '2026-11', title: 'Demo: short lesson (90 s video, 3× limit)', duration_seconds: 90, added: '2026-11-08', demo: true });
    return out;
  }

  #rec(id) {
    for (const c of this.classes) {
      const r = this.recordings(c.id).find((x) => x.id === id);
      if (r) return r;
    }
    return this.#err(404, 'not_found', 'Recording not found.');
  }

  // Mirrors RecordingAccessService: 3× duration on PRO, unlimited otherwise; server-side heartbeat crediting.
  #used(rec) {
    const stored = localStorage.getItem(`enlixo.mock.used.${rec.id}`);
    if (stored !== null) return Number(stored);
    const seed = rec.demo ? 210 : [0, 0.35, 0.86, 1, 0.5][rec.id % 5] * rec.duration_seconds * 3;
    return Math.round(seed);
  }

  watchStatus(rec) {
    const limited = this.plan === 'pro';
    const allowed = limited ? rec.duration_seconds * 3 : null;
    const used = Math.min(this.#used(rec), allowed ?? Infinity);
    const expired = limited && rec.id % 9 === 4;
    let state = 'available';
    if (rec.unpaid_month) state = 'unpaid';
    else if (expired) state = 'expired';
    else if (allowed !== null && used >= allowed) state = 'exhausted';
    else if (allowed !== null && used / allowed >= 0.8) state = 'warning';
    const messages = { exhausted: 'You have used all the watch time for this recording. Ask your tutor if you need more time.', expired: 'This recording has expired.', unpaid: 'Pay the November fee to unlock this recording.' };
    return { state, can_play: state === 'available' || state === 'warning', used_seconds: used, allowed_seconds: allowed, remaining_seconds: allowed === null ? null : Math.max(0, allowed - used), expires_at: limited ? '2026-12-31T23:59:59+05:30' : null, message: messages[state] ?? null };
  }

  #start(rec) {
    const status = this.watchStatus(rec);
    if (!status.can_play) this.#err(403, 'recording_locked', status.message, status);
    const token = Array.from(crypto.getRandomValues(new Uint8Array(20)), (b) => b.toString(16).padStart(2, '0')).join('');
    this.sessions ??= new Map();
    this.sessions.set(token, { rec, last: Date.now() });
    return { session_token: token, heartbeat_seconds: 15, status };
  }

  #heartbeat({ session_token: token, resume }) {
    const s = this.sessions?.get(token) ?? this.#err(404, 'session_not_found', 'Playback session expired. Please reopen the recording.');
    const before = this.watchStatus(s.rec);
    if (!before.can_play) return before;
    const elapsed = Math.round((Date.now() - s.last) / 1000);
    let credit = resume ? 0 : Math.max(0, Math.min(elapsed, 20));
    if (before.remaining_seconds !== null) credit = Math.min(credit, before.remaining_seconds);
    localStorage.setItem(`enlixo.mock.used.${s.rec.id}`, String(this.#used(s.rec) + credit));
    s.last = Date.now();
    return this.watchStatus(s.rec);
  }

  // ----- helpers -----
  #list(rows, q, searchKeys, filterMap, defaultPer = 10) {
    let out = rows;
    if (q.q) { const needle = q.q.toLowerCase(); out = out.filter((r) => searchKeys.some((k) => String(r[k]).toLowerCase().includes(needle))); }
    for (const [param, key] of Object.entries(filterMap)) if (q[param]) out = out.filter((r) => String(r[key]) === q[param]);
    if (q.sort && out[0] && q.sort in out[0]) { const dir = q.dir === 'desc' ? -1 : 1; out = [...out].sort((a, b) => String(a[q.sort]).localeCompare(String(b[q.sort]), undefined, { numeric: true }) * dir); }
    const per = Number(q.per_page ?? defaultPer); const page = Math.max(1, Number(q.page ?? 1));
    return { data: out.slice((page - 1) * per, page * per), meta: { total: out.length, page, per_page: per, pages: Math.max(1, Math.ceil(out.length / per)) } };
  }

  #err(status, code, message, details = {}) {
    throw Object.assign(new Error(message), { status, code, details });
  }
}
