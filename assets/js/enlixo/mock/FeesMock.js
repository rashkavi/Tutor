// Sample data for the Student Fees preview (docs/FEES-PLAN.md, step A). Answers the same endpoints the PHP API will
// (/tutor/fees/..., /tutor/fee-settings, /tutor/students/{id}/fees, /me/access) in the browser, so the pages are
// already written against the real contract. Drop the transport in fees-common.js once step B (migration 008 + src/Fees) is in.
// ?plan=start or ?plan=grow on any fee page shows the package locks.
const FIRST = ['Sahan', 'Nethmi', 'Kavindu', 'Tharushi', 'Dulaj', 'Imasha', 'Yasiru', 'Sachini', 'Hasitha', 'Dinithi', 'Pasindu', 'Methmi', 'Ravindu', 'Senuri', 'Chamod', 'Hiruni', 'Oshada', 'Nadeesha', 'Lakshan', 'Ishini', 'Vihanga', 'Anjali', 'Kalana', 'Piumi'];
const LAST = ['Perera', 'Silva', 'Fernando', 'Jayasinghe', 'Bandara', 'Gunawardena', 'Rathnayake', 'Herath', 'Dissanayake', 'Wickramasinghe', 'Kumara', 'Weerasinghe'];
const CLASSES = [
  { id: 11, name: '2026 A/L Combined Maths Theory', short: 'Theory 2026', fee: 3000, due_day: 10, mode: 'Physical' },
  { id: 12, name: '2026 A/L Combined Maths Revision', short: 'Revision 2026', fee: 2500, due_day: 10, mode: 'Online' },
  { id: 13, name: '2027 A/L Combined Maths Theory', short: 'Theory 2027', fee: 2800, due_day: 5, mode: 'Physical' },
  { id: 14, name: '2027 A/L Combined Maths Paper Class', short: 'Paper 2027', fee: 1500, due_day: 15, mode: 'Online' },
  { id: 15, name: 'Grade 11 O/L Mathematics', short: 'O/L Maths', fee: 2000, due_day: 10, mode: 'Physical' },
];
const PLAN_FEATURES = {
  'fees.class_based': 'start', 'payments.bank_tracking': 'start', 'payments.manual_approval': 'start', 'reports.basic': 'start',
  'payments.online_card': 'grow', 'comms.whatsapp': 'grow', 'comms.sms': 'grow', 'reports.payments': 'grow',
  'payments.arrears': 'pro', 'fees.advanced': 'pro', 'payments.parent_dashboard': 'pro',
};
const NAMES = {
  'payments.online_card': ['Online Card Payments', 'Students pay their fee by card from the app.'],
  'comms.whatsapp': ['WhatsApp Reminders', 'Send fee reminders to many students at once on WhatsApp.'],
  'reports.payments': ['Payment Analytics', 'Income trends and collection rates.'],
  'payments.arrears': ['Arrears Tracking', 'See who owes what, for every month and every class.'],
  'fees.advanced': ['Free Cards & Discounts', 'Free cards, half cards and discounts for single students.'],
};
const PLANS = { start: { name: 'START', price: 'LKR 2,500 – 3,000 / month' }, grow: { name: 'GROW', price: 'LKR 5,000 – 6,000 / month' }, pro: { name: 'PRO', price: 'LKR 8,000 – 10,000 / month' } };
const RANK = { start: 1, grow: 2, pro: 3 };
const REJECT_REASONS = ['The amount on the slip is not the class fee', 'The slip is not clear, please upload a clear photo', 'The money has not reached my account yet', 'This slip was used before', 'Other'];

const DAY = 86400000;
const today = new Date(new Date().toDateString());
const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const ym = (d) => iso(d).slice(0, 7);
const monthStart = (back) => new Date(today.getFullYear(), today.getMonth() - back, 1);
const label = (key, long = false) => new Date(`${key}-01T00:00:00`).toLocaleDateString('en-GB', long ? { month: 'long', year: 'numeric' } : { month: 'short' });
function rng(seed) { let s = seed; return () => ((s = (s * 16807) % 2147483647) - 1) / 2147483646; }
const err = (message, details = {}, status = 400) => Object.assign(new Error(message), { details, status });

export class FeesMock {
  constructor() {
    const r = rng(19);
    const pick = (a) => a[Math.floor(r() * a.length)];
    this.plan = new URLSearchParams(location.search).get('plan') ?? 'pro';
    this.students = [];
    this.enrolments = [];
    this.fees = [];
    this.receipts = [];
    this.reminders = [];
    this.log = [];
    this.concessions = [];
    let fid = 1001; let rid = 1; let eid = 1;
    for (let i = 0; i < 64; i++) {
      const name = `${pick(FIRST)} ${pick(LAST)}`;
      const s = { id: 2400 + i, sid: `ST${String(2400 + i).padStart(5, '0')}`, name, whatsapp: `07${Math.floor(10000000 + r() * 89999999)}`, email: `${name.split(' ')[0].toLowerCase()}${i}@gmail.com`, joined: iso(new Date(today.getTime() - (30 + r() * 300) * DAY)) };
      this.students.push(s);
      const n = r() > 0.75 ? 2 : 1;
      const used = new Set();
      for (let k = 0; k < n; k++) {
        let c = pick(CLASSES); while (used.has(c.id)) c = pick(CLASSES); used.add(c.id);
        const months = 5 + Math.floor(r() * 8);
        const habit = r(); // < .55 pays on time, < .8 a bit late, < .93 owes 1-2 months, rest owes 3+
        const free = r() > 0.94 ? 'free' : r() > 0.93 ? 'half' : null;
        const e = { id: eid++, student: s.id, class: c.id, since: ym(monthStart(months)), status: 'active', free };
        this.enrolments.push(e);
        if (free) this.concessions.push({ id: this.concessions.length + 1, enrolment: e.id, kind: free, value: free === 'half' ? 50 : 100, from: e.since, to: null, reason: free === 'free' ? 'Family cannot afford the fee' : 'Brother also in this class', by: 'You', on: e.since + '-02' });
        for (let back = months; back >= 0; back--) {
          const m = monthStart(back);
          const due = new Date(m.getFullYear(), m.getMonth(), c.due_day);
          const discount = free === 'free' ? c.fee : free === 'half' ? c.fee / 2 : 0;
          const f = { id: fid++, enrolment: e.id, student: s.id, class: c.id, month: ym(m), amount: c.fee, discount, penalty: 0, due: iso(due), status: 'unpaid', paid_on: null, method: null, receipt: null, slip: null, reason: null, checked_by: null };
          f.net = f.amount - f.discount;
          if (f.net <= 0) f.status = 'free';
          else {
            const owe = habit > 0.93 ? 3 + Math.floor(r() * 2) : habit > 0.8 ? 1 + Math.floor(r() * 2) : 0;
            const isCurrent = back === 0;
            if (back < owe || (isCurrent && due > today && r() > 0.45)) f.status = 'unpaid';
            else if (isCurrent && r() > 0.72) f.status = 'waiting';
            else if (back <= 1 && r() > 0.97) f.status = 'rejected';
            else f.status = 'paid';
            if (f.status === 'unpaid' && due < today && back > 0 && r() > 0.5) f.penalty = 200;
            f.net = f.amount - f.discount + f.penalty;
            if (['paid', 'waiting', 'rejected'].includes(f.status)) {
              const paid = new Date(Math.min(today.getTime(), due.getTime() + (habit < 0.55 ? -3 : 6) * DAY - Math.floor(r() * 5) * DAY));
              f.paid_on = iso(paid);
              const bySlip = r() > 0.42 || f.status !== 'paid';
              f.method = bySlip ? 'Bank slip' : 'Cash';
              if (bySlip) f.slip = { file: `slip-${f.id}.jpg`, reference: String(Math.floor(100000 + r() * 899999)), uploaded: `${f.paid_on} ${String(8 + Math.floor(r() * 12)).padStart(2, '0')}:${String(Math.floor(r() * 60)).padStart(2, '0')}`, amount: f.net + (r() > 0.93 ? -500 : 0), bank: pick(['Commercial Bank', 'Bank of Ceylon']) };
              if (f.status === 'rejected') { f.reason = REJECT_REASONS[1]; f.checked_by = 'You'; }
              if (f.status === 'paid') {
                f.checked_by = bySlip ? pick(['You', 'Nimali (Assistant)']) : null;
                f.receipt = `RC-${String(rid).padStart(5, '0')}`;
                this.receipts.push({ id: rid++, no: f.receipt, enrolment: e.id, student: s.id, class: c.id, fees: [f.id], months: [f.month], amount: f.net, method: f.method, paid_on: f.paid_on, by: bySlip ? f.checked_by : pick(['You', 'Nimali (Assistant)']), reference: f.slip?.reference ?? null, status: 'valid' });
              }
            }
          }
          this.fees.push(f);
        }
      }
    }
    this.receipts.sort((a, b) => b.paid_on.localeCompare(a.paid_on) || b.id - a.id);
    this.nextReceipt = rid;
    this.settings = {
      methods: { slip: true, cash: true, card: false },
      banks: [
        { id: 1, bank: 'Commercial Bank', branch: 'Maharagama', name: 'K. R. Perera', number: '8001 2345 6789', on: true },
        { id: 2, bank: 'Bank of Ceylon', branch: 'Nugegoda', name: 'K. R. Perera', number: '7012 3344', on: true },
      ],
      slip_note: 'Write your Student ID on the slip. Upload a clear photo of the whole slip.',
      create_day: 1, default_due_day: 10, grace_days: 5,
      late_fee: { on: true, type: 'fixed', value: 200, after_days: 5 },
      lock_unpaid: { on: true, after_days: 7 },
      concession_types: [
        { id: 1, name: 'Free card', kind: 'free', value: 100, on: true },
        { id: 2, name: 'Half card', kind: 'percent', value: 50, on: true },
        { id: 3, name: 'Sibling discount', kind: 'percent', value: 10, on: true },
        { id: 4, name: 'Top ranker (fixed)', kind: 'fixed', value: 1000, on: false },
      ],
      reminders: { before_days: 2, on_due: true, after_days: 3, repeat_days: 7, channel: 'whatsapp', text: 'Dear {student}, your {class} fee for {month} (LKR {amount}) is due on {due_date}. Please pay and upload the slip in the ENLIXO app. Thank you, {tutor}.' },
      receipts: { prefix: 'RC-', next: rid, footer: 'Thank you. Keep this receipt until the end of the year.', auto_send: true },
      approvers: ['Owner', 'Payment Checker'],
    };
    this.log = [
      { when: `${iso(new Date(today.getTime() - 2 * DAY))} 09:14`, by: 'You', what: 'Late fee changed from LKR 100 to LKR 200 after 5 days' },
      { when: `${iso(new Date(today.getTime() - 9 * DAY))} 18:40`, by: 'You', what: 'Added bank account Bank of Ceylon, Nugegoda' },
      { when: `${iso(new Date(today.getTime() - 30 * DAY))} 11:02`, by: 'You', what: 'Turned on "Close recordings of unpaid months after 7 days"' },
    ];
  }

  async handle(method, path, body) {
    await new Promise((res) => setTimeout(res, 100 + Math.random() * 180));
    const [route, query] = path.split('?');
    const q = Object.fromEntries(new URLSearchParams(query ?? ''));
    try {
      return { status: 200, payload: { ok: true, ...this.#route(method, route, q, body ?? {}) } };
    } catch (e) {
      return { status: e.status ?? 400, payload: { ok: false, error: { code: e.code ?? 'validation', message: e.message, details: e.details ?? {} } } };
    }
  }

  // ---------------------------------------------------------------- helpers
  has(key) { return RANK[this.plan] >= RANK[PLAN_FEATURES[key] ?? 'start']; }
  #locked(key) { if (!this.has(key)) throw Object.assign(err(`${NAMES[key]?.[0] ?? key} is on ${PLANS[PLAN_FEATURES[key]].name}.`, { feature: key }, 403), { code: 'feature_locked' }); }
  #student(id) { return this.students.find((s) => s.id === Number(id)); }
  #class(id) { return CLASSES.find((c) => c.id === Number(id)); }
  #status(f) { return f.status === 'unpaid' && f.due < iso(today) ? 'overdue' : f.status; }
  #fee(f) {
    const s = this.#student(f.student); const c = this.#class(f.class);
    return { ...f, status: this.#status(f), month_label: label(f.month, true), student: { id: s.id, sid: s.sid, name: s.name, whatsapp: s.whatsapp }, class: { id: c.id, name: c.name, short: c.short } };
  }
  #page(rows, q, search) {
    const term = (q.q ?? '').toLowerCase();
    let list = term ? rows.filter((r) => search(r).toLowerCase().includes(term)) : rows;
    if (q.sort) { const dir = q.dir === 'desc' ? -1 : 1; const v = (r) => (typeof r[q.sort] === 'object' ? r[q.sort]?.name : r[q.sort]); list = [...list].sort((a, b) => (v(a) > v(b) ? dir : v(a) < v(b) ? -dir : 0)); }
    const per = Number(q.per_page ?? 10); const page = Number(q.page ?? 1);
    return { data: list.slice((page - 1) * per, page * per), meta: { total: list.length, page, per_page: per, pages: Math.max(1, Math.ceil(list.length / per)) } };
  }
  #owed(f) { return ['unpaid', 'rejected'].includes(f.status) && f.net > 0; }
  #active(f) { return this.enrolments.find((e) => e.id === f.enrolment)?.status === 'active'; }

  #route(method, route, q, body) {
    let m;
    if (method === 'GET' && route === '/me/access') return { data: this.access() };
    if (method === 'GET' && route === '/tutor/fees/overview') return { data: this.overview(q.month ?? ym(today), q.class) };
    if (method === 'GET' && route === '/tutor/fees') {
      const month = q.month ?? ym(today);
      const rows = this.fees.filter((f) => f.month === month && (!q.class || f.class === Number(q.class))).map((f) => this.#fee(f))
        .filter((f) => (!q.status || f.status === q.status || (q.status === 'unpaid' && ['unpaid', 'overdue', 'rejected'].includes(f.status))) && (!q.method || f.method === q.method));
      rows.sort((a, b) => ({ waiting: 0, overdue: 1, rejected: 2, unpaid: 3, paid: 4, free: 5 }[a.status] - { waiting: 0, overdue: 1, rejected: 2, unpaid: 3, paid: 4, free: 5 }[b.status]) || a.student.name.localeCompare(b.student.name));
      return this.#page(rows, q, (f) => `${f.student.name} ${f.student.sid} ${f.student.whatsapp} ${f.class.name} ${f.receipt ?? ''}`);
    }
    if (method === 'GET' && route === '/tutor/fees/slips') {
      const rows = this.fees.filter((f) => f.status === 'waiting' && (!q.class || f.class === Number(q.class))).map((f) => this.#fee(f)).sort((a, b) => a.slip.uploaded.localeCompare(b.slip.uploaded));
      return this.#page(rows, q, (f) => `${f.student.name} ${f.student.sid} ${f.class.name} ${f.slip.reference}`);
    }
    if (method === 'GET' && route === '/tutor/fees/unpaid') {
      const thisMonth = !this.has('payments.arrears');
      const groups = new Map();
      this.fees.filter((f) => this.#owed(f) && this.#active(f) && f.due < iso(today) && (!thisMonth || f.month === ym(today)) && (!q.class || f.class === Number(q.class))).forEach((f) => {
        if (!groups.has(f.enrolment)) groups.set(f.enrolment, []);
        groups.get(f.enrolment).push(this.#fee(f));
      });
      let rows = [...groups.entries()].map(([id, list]) => {
        const s = list[0].student; const rem = this.reminders.filter((x) => x.enrolment === id).at(-1);
        return { id, student: s, class: list[0].class, months: list.map((f) => ({ id: f.id, month: f.month, label: label(f.month), net: f.net, status: f.status })), count: list.length, total: list.reduce((a, f) => a + f.net, 0), oldest: list[0].due, last_reminder: rem?.when ?? null };
      });
      if (q.months) rows = rows.filter((r) => (q.months === '3' ? r.count >= 3 : r.count === Number(q.months)));
      rows.sort((a, b) => b.count - a.count || b.total - a.total);
      return this.#page(rows, q, (r) => `${r.student.name} ${r.student.sid} ${r.class.name}`);
    }
    if (method === 'GET' && route === '/tutor/fees/receipts') {
      const rows = this.receipts.filter((r) => (!q.class || r.class === Number(q.class)) && (!q.method || r.method === q.method) && (!q.month || r.paid_on.startsWith(q.month)))
        .map((r) => ({ ...r, student: this.#student(r.student), class: this.#class(r.class), month_labels: r.months.map((x) => label(x)) }));
      return this.#page(rows, q, (r) => `${r.no} ${r.student.name} ${r.student.sid} ${r.class.name} ${r.reference ?? ''}`);
    }
    if ((m = route.match(/^\/tutor\/fees\/receipts\/(\d+)$/)) && method === 'GET') return { data: this.receipt(Number(m[1])) };
    if ((m = route.match(/^\/tutor\/fees\/(\d+)$/)) && method === 'GET') return { data: this.detail(Number(m[1])) };
    if ((m = route.match(/^\/tutor\/fees\/(\d+)\/(approve|reject)$/)) && method === 'POST') return { data: this.decide(Number(m[1]), m[2], body) };
    if (method === 'GET' && route === '/tutor/fees/students') return { data: this.students.map((s) => ({ id: s.id, sid: s.sid, name: s.name, whatsapp: s.whatsapp })) };
    if ((m = route.match(/^\/tutor\/fees\/students\/(\d+)\/open$/)) && method === 'GET') return { data: this.openFor(Number(m[1])) };
    if (method === 'POST' && route === '/tutor/fees/payments') return { data: this.record(body) };
    if (method === 'POST' && route === '/tutor/fees/concessions') { this.#locked('fees.advanced'); return { data: this.concede(body) }; }
    if (method === 'POST' && route === '/tutor/fees/reminders') return { data: this.remind(body) };
    if ((m = route.match(/^\/tutor\/students\/(\d+)\/fees$/)) && method === 'GET') return { data: this.card(Number(m[1])) };
    if (method === 'GET' && route === '/tutor/fee-settings') return { data: { settings: this.settings, classes: CLASSES.map((c) => ({ ...c, students: this.enrolments.filter((e) => e.class === c.id).length })), log: this.log, reasons: REJECT_REASONS } };
    if (method === 'PUT' && route === '/tutor/fee-settings') {
      if (body.part === 'banks' && body.bank && !(body.bank.number ?? '').trim()) throw err('Please check the highlighted fields.', { number: 'Enter the account number, for example 8001 2345 6789.' });
      this.log.unshift({ when: `${iso(today)} ${new Date().toTimeString().slice(0, 5)}`, by: 'You', what: body.summary ?? 'Settings saved' });
      return { data: { saved: true } };
    }
    throw err(`No sample data for ${method} ${route}.`, {}, 404);
  }

  access() {
    return {
      plan: this.plan, plans: Object.fromEntries(Object.entries(PLANS).map(([k, v]) => [k, v])),
      features: Object.entries(PLAN_FEATURES).map(([key, plan]) => ({ key, name: NAMES[key]?.[0] ?? key, description: NAMES[key]?.[1] ?? '', unlocked: this.has(key), required_plan: plan })),
      permissions: ['payments.view', 'payments.approve', 'fees.manage', 'payments.arrears'],
    };
  }

  overview(month, cls) {
    const inClass = (f) => !cls || f.class === Number(cls);
    const of = (key) => this.fees.filter((f) => f.month === key && inClass(f));
    const sum = (list, st) => list.filter((f) => st.includes(this.#status(f))).reduce((a, f) => a + f.net, 0);
    const now = of(month);
    const months = Array.from({ length: 12 }, (_, i) => ym(monthStart(11 - i)));
    const prev = ym(new Date(new Date(`${month}-01T00:00:00`).getFullYear(), new Date(`${month}-01T00:00:00`).getMonth() - 1, 1));
    const due = (list) => list.filter((f) => f.status !== 'free').reduce((a, f) => a + f.net, 0);
    const arrears = this.fees.filter((f) => this.#owed(f) && this.#active(f) && f.month < month && inClass(f));
    const unpaidNow = now.filter((f) => ['unpaid', 'overdue', 'rejected'].includes(this.#status(f)));
    const free = now.filter((f) => f.discount > 0);
    const collected = sum(now, ['paid']);
    return {
      month, month_label: label(month, true), is_current: month === ym(today),
      months: months.map((k) => ({ value: k, label: label(k, true) })).reverse(),
      classes: CLASSES.map((c) => ({ id: c.id, name: c.name, short: c.short, fee: c.fee, due_day: c.due_day })),
      stats: {
        collected: { value: collected, before: sum(of(prev), ['paid']), bars: months.slice(-6).map((k) => ({ label: label(k), value: sum(of(k), ['paid']) })) },
        to_collect: { value: sum(now, ['unpaid', 'overdue', 'rejected']), students: new Set(unpaidNow.map((f) => f.student)).size, overdue: now.filter((f) => this.#status(f) === 'overdue').length },
        slips: { value: this.fees.filter((f) => f.status === 'waiting' && inClass(f)).length, amount: this.fees.filter((f) => f.status === 'waiting' && inClass(f)).reduce((a, f) => a + f.net, 0) },
        arrears: { value: arrears.reduce((a, f) => a + f.net, 0), students: new Set(arrears.map((f) => f.student)).size },
        free: { value: free.reduce((a, f) => a + f.discount, 0), students: free.length, full: now.filter((f) => f.status === 'free').length },
        rate: { value: due(now) ? Math.round((sum(now, ['paid', 'waiting']) / due(now)) * 100) : 0, bars: months.slice(-6).map((k) => ({ label: label(k), value: due(of(k)) ? Math.round((sum(of(k), ['paid', 'waiting']) / due(of(k))) * 100) : 0 })) },
      },
      chart: { labels: months.map((k) => label(k)), due: months.map((k) => due(of(k))), collected: months.map((k) => sum(of(k), ['paid'])), waiting: months.map((k) => sum(of(k), ['waiting'])) },
      by_class: CLASSES.filter((c) => !cls || c.id === Number(cls)).map((c) => {
        const list = now.filter((f) => f.class === c.id);
        return { id: c.id, name: c.name, short: c.short, fee: c.fee, due_day: c.due_day, due: due(list), collected: sum(list, ['paid']), waiting: sum(list, ['waiting']), unpaid: list.filter((f) => ['unpaid', 'overdue', 'rejected'].includes(this.#status(f))).length, students: list.length };
      }),
      counts: { month: now.length, slips: this.fees.filter((f) => f.status === 'waiting' && inClass(f)).length, unpaid: new Set(this.fees.filter((f) => this.#owed(f) && this.#active(f) && f.due < iso(today) && inClass(f) && (this.has('payments.arrears') || f.month === ym(today))).map((f) => f.enrolment)).size, receipts: this.receipts.length },
      settings: { methods: this.settings.methods, late_fee: this.settings.late_fee },
    };
  }

  detail(id) {
    const f = this.fees.find((x) => x.id === id);
    if (!f) throw err('Fee not found.', {}, 404);
    const out = this.#fee(f);
    const c = this.#class(f.class);
    out.history = [
      { when: `${f.month}-01 00:05`, by: 'System', what: `${label(f.month, true)} fee created: LKR ${c.fee.toLocaleString('en-LK')}${f.discount ? `, ${f.discount >= f.amount ? 'free card' : `discount LKR ${f.discount.toLocaleString('en-LK')}`}` : ''}` },
      f.penalty ? { when: `${iso(new Date(new Date(f.due).getTime() + 5 * DAY))} 00:05`, by: 'System', what: `Late fee LKR ${f.penalty} added (5 days after the due date)` } : null,
      f.slip ? { when: f.slip.uploaded, by: f.student === out.student.id ? out.student.name : 'Student', what: `Slip uploaded, reference ${f.slip.reference}` } : null,
      f.status === 'paid' ? { when: `${f.paid_on} 19:20`, by: f.checked_by ?? 'You', what: f.slip ? `Slip approved, receipt ${f.receipt}` : `Cash received, receipt ${f.receipt}` } : null,
      f.status === 'rejected' ? { when: `${f.paid_on} 19:20`, by: 'You', what: `Slip rejected: ${f.reason}` } : null,
    ].filter(Boolean).reverse();
    out.receipt_id = this.receipts.find((r) => r.no === f.receipt)?.id ?? null;
    out.reasons = REJECT_REASONS;
    return out;
  }

  decide(id, action, b) {
    const f = this.fees.find((x) => x.id === id);
    if (f.status !== 'waiting') throw err('This slip was already checked.');
    if (action === 'reject') {
      if (!(b.reason ?? '').trim()) throw err('Please check the highlighted fields.', { reason: 'Choose why. The student sees it and can upload again.' });
      Object.assign(f, { status: 'rejected', reason: b.reason, checked_by: 'You' });
      return { fee: this.#fee(f) };
    }
    const no = `${this.settings.receipts.prefix}${String(this.nextReceipt).padStart(5, '0')}`;
    const r = { id: this.nextReceipt++, no, enrolment: f.enrolment, student: f.student, class: f.class, fees: [f.id], months: [f.month], amount: f.net, method: 'Bank slip', paid_on: f.paid_on, by: 'You', reference: f.slip.reference, status: 'valid' };
    this.receipts.unshift(r);
    Object.assign(f, { status: 'paid', checked_by: 'You', receipt: no });
    return { fee: this.#fee(f), receipt: r.id, next: this.fees.find((x) => x.status === 'waiting')?.id ?? null };
  }

  openFor(studentId) {
    const s = this.#student(studentId);
    const enrol = this.enrolments.filter((e) => e.student === studentId && e.status === 'active');
    return {
      student: { id: s.id, sid: s.sid, name: s.name, whatsapp: s.whatsapp },
      classes: enrol.map((e) => {
        const c = this.#class(e.class);
        const open = this.fees.filter((f) => f.enrolment === e.id && this.#owed(f)).map((f) => ({ id: f.id, month: f.month, label: label(f.month, true), net: f.net, amount: f.amount, discount: f.discount, penalty: f.penalty, status: this.#status(f) }));
        const last = this.fees.filter((f) => f.enrolment === e.id).at(-1);
        const next = new Date(`${last.month}-01T00:00:00`); next.setMonth(next.getMonth() + 1);
        const nk = ym(next);
        const disc = e.free === 'half' ? c.fee / 2 : e.free === 'free' ? c.fee : 0;
        return { enrolment: e.id, class: { id: c.id, name: c.name }, fee: c.fee, open, advance: { id: `next-${e.id}`, month: nk, label: label(nk, true), net: c.fee - disc, amount: c.fee, discount: disc, penalty: 0, status: 'advance' } };
      }),
    };
  }

  record(b) {
    const errors = {};
    if (!b.student) errors.student = 'Choose the student who paid.';
    if (!b.items?.length) errors.items = 'Tick at least one month.';
    if (!b.method) errors.method = 'Choose how the student paid.';
    if (!b.paid_on) errors.paid_on = 'Choose the day the money was received.';
    else if (b.paid_on > iso(today)) errors.paid_on = 'The day cannot be in the future.';
    if (b.method === 'Bank deposit' && !(b.reference ?? '').trim()) errors.reference = 'Enter the reference on the bank slip, for example 004512.';
    if (Object.keys(errors).length) throw err('Please check the highlighted fields.', errors);
    const fees = b.items.map((id) => this.fees.find((f) => f.id === Number(id))).filter(Boolean);
    const total = fees.reduce((a, f) => a + f.net, 0) + (b.advance ?? []).length * 0;
    const no = `${this.settings.receipts.prefix}${String(this.nextReceipt).padStart(5, '0')}`;
    const first = fees[0] ?? this.fees.find((f) => f.student === Number(b.student));
    const r = { id: this.nextReceipt++, no, enrolment: first.enrolment, student: Number(b.student), class: first.class, fees: fees.map((f) => f.id), months: fees.map((f) => f.month), amount: b.total ?? total, method: b.method, paid_on: b.paid_on, by: 'You', reference: b.reference || null, status: 'valid', note: b.note ?? null };
    this.receipts.unshift(r);
    fees.forEach((f) => Object.assign(f, { status: 'paid', paid_on: b.paid_on, method: b.method, receipt: no, checked_by: 'You' }));
    return { receipt: r.id, no };
  }

  receipt(id) {
    const r = this.receipts.find((x) => x.id === id);
    if (!r) throw err('Receipt not found.', {}, 404);
    const s = this.#student(r.student); const c = this.#class(r.class);
    return { ...r, student: s, class: c, lines: r.fees.map((fid) => { const f = this.fees.find((x) => x.id === fid); return { text: `${label(f.month, true)} fee`, amount: f.amount, discount: f.discount, penalty: f.penalty, net: f.net }; }), tutor: { name: 'K. R. Perera', brand: 'Perera Maths Academy', phone: '077 123 4567', address: 'No. 25, High Level Road, Maharagama' }, footer: this.settings.receipts.footer };
  }

  concede(b) {
    const errors = {};
    if (!b.enrolment) errors.enrolment = 'Choose the class.';
    if (!b.type) errors.type = 'Choose what to give.';
    if (!(b.reason ?? '').trim()) errors.reason = 'Say why. The reason is kept with the student.';
    if (Object.keys(errors).length) throw err('Please check the highlighted fields.', errors);
    return { saved: true };
  }

  remind(b) {
    if (!b.enrolments?.length) throw err('Tick at least one student first.');
    if (b.channel !== 'link' && !this.has('comms.whatsapp')) this.#locked('comms.whatsapp');
    const when = `${iso(today)} ${new Date().toTimeString().slice(0, 5)}`;
    b.enrolments.forEach((id) => this.reminders.push({ enrolment: Number(id), when, channel: b.channel }));
    return { sent: b.enrolments.length };
  }

  card(studentId) {
    const s = this.#student(studentId);
    if (!s) throw err('Student not found.', {}, 404);
    const enrol = this.enrolments.filter((e) => e.student === studentId);
    const fees = this.fees.filter((f) => f.student === studentId).map((f) => this.#fee(f));
    const year = String(today.getFullYear());
    return {
      student: s,
      stats: {
        paid_year: fees.filter((f) => f.status === 'paid' && f.month.startsWith(year)).reduce((a, f) => a + f.net, 0),
        owed: fees.filter((f) => ['unpaid', 'overdue', 'rejected'].includes(f.status) && f.due < iso(today)).reduce((a, f) => a + f.net, 0),
        owed_months: fees.filter((f) => ['unpaid', 'overdue', 'rejected'].includes(f.status) && f.due < iso(today)).length,
        waiting: fees.filter((f) => f.status === 'waiting').length,
        saved: fees.filter((f) => f.discount > 0).reduce((a, f) => a + f.discount, 0),
        last: this.receipts.find((r) => r.student === studentId) ?? null,
      },
      classes: enrol.map((e) => {
        const c = this.#class(e.class);
        return { enrolment: e.id, id: c.id, name: c.name, fee: c.fee, due_day: c.due_day, since: label(e.since, true), concession: this.concessions.find((x) => x.enrolment === e.id) ?? null, months: fees.filter((f) => f.enrolment === e.id).map((f) => ({ id: f.id, month: f.month, label: label(f.month), long: f.month_label, status: f.status, net: f.net, receipt: f.receipt, paid_on: f.paid_on })) };
      }),
      receipts: this.receipts.filter((r) => r.student === studentId).map((r) => ({ ...r, class: this.#class(r.class), month_labels: r.months.map((x) => label(x)) })),
      reminders: this.reminders.filter((x) => enrol.some((e) => e.id === x.enrolment)),
    };
  }
}
