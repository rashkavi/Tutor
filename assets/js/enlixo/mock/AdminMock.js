// Sample data for the Admin side preview (docs/ADMIN-PLAN.md, step A). Answers the same endpoints the PHP API will
// (/admin/... and /tutor/subscription) in the browser, so the pages are already written against the real contract.
// Remove the transport from the page scripts once step B (migration 007 + src/Platform) is in.
const FIRST = ['Ruwan', 'Dilani', 'Kasun', 'Nadeesha', 'Chamara', 'Sanduni', 'Lahiru', 'Tharushi', 'Nuwan', 'Hiruni', 'Ishara', 'Pradeep', 'Amaya', 'Dinesh', 'Sachini', 'Asanka', 'Nethmi', 'Chathura', 'Malsha', 'Tharindu'];
const LAST = ['Perera', 'Silva', 'Fernando', 'Jayasinghe', 'Bandara', 'Gunawardena', 'Rathnayake', 'Herath', 'Dissanayake', 'Wickramasinghe'];
const SUBJECTS = ['Combined Maths', 'Physics', 'Chemistry', 'Biology', 'ICT', 'English', 'Accounting', 'Economics', 'Sinhala', 'Science'];
const BRANDS = ['Academy', 'Institute', 'Classes', 'Education', 'Tuition', 'Learning'];

export const PLANS = [
  { key: 'start', id: 1, name: 'START', monthly: 2500, yearly_discount: 250, limit: 50, pack_size: 50, pack_price: 1000, tagline: 'Starter access package', features: ['Timetable and class scheduling', 'Manual and QR attendance', 'Bank transfer and manual approval', 'Basic reports'], tutors: 0 },
  { key: 'grow', id: 2, name: 'GROW', monthly: 5000, yearly_discount: 500, limit: 150, pack_size: 50, pack_price: 1000, tagline: 'For active tutors and class managers', features: ['Everything in START', 'Online card payments', 'WhatsApp and SMS (your own API)', 'Attendance and payment analytics'], tutors: 0 },
  { key: 'pro', id: 3, name: 'PRO', monthly: 8000, yearly_discount: 800, limit: 300, pack_size: 50, pack_price: null, tagline: 'Advanced controls and analytics', features: ['Everything in GROW', 'Parent login and payment dashboard', 'Arrears tracking dashboard', 'Controlled and expiry-based recordings'], tutors: 0 },
];
export const ADDONS = [
  { key: 'gateway', name: 'ENVIIZ Payment Gateway', model: 'Monthly + per payment', price: 2000, note: 'LKR 2,000 a month + 2.5% of each card payment', on: true },
  { key: 'streaming', name: 'ENVIIZ Secure Streaming', model: 'Monthly', price: 1500, note: 'From LKR 1,500 a month', on: true },
  { key: 'zoom', name: 'ENVIIZ Zoom Account', model: 'Monthly', price: 1800, note: 'LKR 1,500 - 2,000 a month', on: true },
  { key: 'sms', name: 'ENVIIZ SMS Service', model: 'Pay as you go', price: null, note: 'LKR 0.85 per SMS, billed for last month', on: true },
  { key: 'whatsapp', name: 'ENVIIZ WhatsApp API', model: 'Per conversation', price: null, note: 'LKR 4 per conversation, billed for last month', on: true },
  { key: 'idcards', name: 'Physical ID Card Printing', model: 'One-time', price: 15000, note: 'LKR 15,000 setup + LKR 120 per card', on: false },
];
const STATUS = {
  trial: 'Trial', active: 'Active', due: 'Payment Due', overdue: 'Overdue', suspended: 'Suspended', cancelled: 'Cancelled', complimentary: 'Complimentary',
};
const RULES = { trial_days: 7, trial_plan: 'pro', trial_student_cap: 30, invoice_days_before: 7, grace_days: 7, reminders: '7, 3 and 1 days before; 1 and 5 days after', tax_on: false, tax_rate: 18, invoice_prefix: 'EN-INV', yearly_months_free: 'Plan discount' };
const BANKS = [
  { id: 1, bank: 'Commercial Bank', branch: 'Maharagama', name: 'ENVIIZ Softwares (Pvt) Ltd', number: '1000 4567 8901', on: true },
  { id: 2, bank: 'Bank of Ceylon', branch: 'Nugegoda', name: 'ENVIIZ Softwares (Pvt) Ltd', number: '8512 3344', on: true },
];

const DAY = 86400000;
const today = new Date(new Date().toDateString());
const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const addDays = (d, n) => new Date(d.getTime() + n * DAY);
const addMonths = (d, n) => { const x = new Date(d); x.setMonth(x.getMonth() + n); return x; };
function rng(seed) { let s = seed; return () => ((s = (s * 16807) % 2147483647) - 1) / 2147483646; }

export class AdminMock {
  constructor() {
    const r = rng(7);
    const pick = (a) => a[Math.floor(r() * a.length)];
    const plan = (k) => PLANS.find((p) => p.key === k);
    this.tutors = [];
    this.invoices = [];
    this.payments = [];
    this.history = [];
    let inv = 1;
    let pay = 1;
    const statuses = ['active', 'active', 'active', 'active', 'active', 'active', 'trial', 'trial', 'due', 'due', 'overdue', 'active', 'suspended', 'complimentary', 'active', 'cancelled', 'active', 'trial', 'due', 'active', 'overdue', 'active', 'active', 'grow'];
    for (let i = 0; i < 38; i++) {
      const fn = pick(FIRST); const ln = pick(LAST); const subject = pick(SUBJECTS);
      let status = statuses[i % statuses.length]; if (status === 'grow') status = 'active';
      const key = i === 0 ? 'grow' : pick(['start', 'start', 'grow', 'grow', 'grow', 'pro']);
      const p = plan(status === 'trial' ? 'pro' : key);
      const cycle = r() > 0.8 ? 'yearly' : 'monthly';
      const monthsOn = status === 'trial' ? 0 : 1 + Math.floor(r() * 14);
      const joined = status === 'trial' ? addDays(today, -Math.floor(r() * 7)) : addDays(addMonths(today, -monthsOn), -Math.floor(r() * 20) - 7);
      const extra = p.key !== 'pro' && r() > 0.8 ? 1 : 0;
      const limit = status === 'trial' ? RULES.trial_student_cap : p.limit + extra * p.pack_size;
      const students = status === 'trial' ? Math.floor(r() * 26) : Math.min(limit, Math.floor(limit * (0.25 + r() * 0.8)));
      const custom = status !== 'trial' && r() > 0.88;
      const price = custom ? p.monthly - 500 : p.monthly;
      const addons = r() > 0.7 ? [pick(['zoom', 'streaming', 'gateway'])] : [];
      const periodEnd = {
        active: addDays(today, 9 + Math.floor(r() * 18)), due: addDays(today, 1 + Math.floor(r() * 6)), overdue: addDays(today, -(1 + Math.floor(r() * 5))),
        suspended: addDays(today, -12), cancelled: addDays(today, -20), complimentary: addMonths(today, 3), trial: null,
      }[status];
      const t = {
        id: 101 + i, title: r() > 0.5 ? 'Mr.' : 'Ms.', first_name: fn, last_name: ln, name: `${fn} ${ln}`,
        brand: `${ln} ${subject} ${pick(BRANDS)}`, subject,
        email: `${fn}.${ln}${i}@gmail.com`.toLowerCase(), mobile: `07${Math.floor(r() * 9)}${String(Math.floor(r() * 1e7)).padStart(7, '0')}`,
        joined: iso(joined), plan: p.key, plan_name: p.name, cycle, status, status_label: STATUS[status],
        trial_ends: status === 'trial' ? iso(addDays(joined, RULES.trial_days)) : null,
        period_end: periodEnd ? iso(periodEnd) : null,
        students, limit, extra_packs: extra, price, custom_price: custom ? 'Launch offer for the first year' : null, addons,
        balance: 0, last_login: iso(addDays(today, -Math.floor(r() * 4))),
      };
      if (i === 0) Object.assign(t, { title: 'Mr.', first_name: 'Ruwan', last_name: 'Perera', name: 'Ruwan Perera', brand: 'Perera Physics Academy', subject: 'Physics', email: 'ruwan.perera@gmail.com', status: 'due', status_label: STATUS.due, period_end: iso(addDays(today, 4)), students: 138, limit: 150, addons: ['zoom'], cycle: 'monthly', price: 5000, custom_price: null, extra_packs: 0 });
      status = t.status;
      this.tutors.push(t);

      // invoices + payments for each paid month
      const monthly = (t.cycle === 'yearly' ? (t.price - plan(t.plan).yearly_discount) * 12 : t.price);
      const addonTotal = t.addons.reduce((a, k) => a + (ADDONS.find((x) => x.key === k).price ?? 0), 0) * (t.cycle === 'yearly' ? 12 : 1);
      const packTotal = t.extra_packs * (plan(t.plan).pack_price ?? 0) * (t.cycle === 'yearly' ? 12 : 1);
      const periods = status === 'trial' || status === 'complimentary' ? 0 : t.cycle === 'yearly' ? Math.max(1, Math.ceil(monthsOn / 12)) : monthsOn;
      for (let k = periods; k >= 1; k--) {
        const len = t.cycle === 'yearly' ? 12 : 1;
        const end = t.period_end ? addMonths(new Date(t.period_end), -(k - 1) * len + (status === 'due' ? len : 0)) : today;
        const start = addMonths(end, -len);
        const isLast = k === 1;
        const issued = addDays(start, -RULES.invoice_days_before);
        const lines = [{ text: `${plan(t.plan).name} plan, ${t.cycle === 'yearly' ? '12 months' : '1 month'}${t.custom_price ? ' (custom price)' : ''}`, amount: monthly }];
        if (packTotal) lines.push({ text: `Extra students, ${t.extra_packs} × 50`, amount: packTotal });
        t.addons.forEach((a) => { const ad = ADDONS.find((x) => x.key === a); if (ad.price) lines.push({ text: ad.name, amount: ad.price * len }); });
        const total = monthly + packTotal + addonTotal;
        let st = 'paid';
        if (isLast && ['due', 'overdue', 'suspended'].includes(status)) st = status === 'due' ? 'open' : 'overdue';
        if (isLast && status === 'cancelled') st = 'void';
        const due = start;
        const invoice = { id: inv, number: `EN-INV-${start.getFullYear()}-${String(inv).padStart(5, '0')}`, tutor_id: t.id, tutor: t.name, brand: t.brand, plan: plan(t.plan).name,
          period_from: iso(start), period_to: iso(addDays(end, -1)), issued: iso(issued), due: iso(due), lines, subtotal: total, tax: 0, total, paid: st === 'paid' ? total : 0, status: st, void_reason: st === 'void' ? 'Tutor cancelled before the period started' : null };
        inv += 1;
        this.invoices.push(invoice);
        if (st === 'paid') {
          const method = r() > 0.85 ? 'Cash' : 'Bank transfer';
          this.payments.push({ id: pay, ref: `PAY-${String(pay).padStart(5, '0')}`, tutor_id: t.id, tutor: t.name, invoice: invoice.number, invoice_id: invoice.id, amount: total, method, date: iso(addDays(due, -Math.floor(r() * 5))), status: 'approved', slip: method === 'Bank transfer', checked_by: 'Kaveesha (Finance)', reason: null });
          pay += 1;
          if (r() > 0.93) { this.payments.push({ id: pay, ref: `PAY-${String(pay).padStart(5, '0')}`, tutor_id: t.id, tutor: t.name, invoice: invoice.number, invoice_id: invoice.id, amount: total, method: 'Bank transfer', date: iso(addDays(due, -8)), status: 'rejected', slip: true, checked_by: 'Kaveesha (Finance)', reason: 'Slip amount does not match the invoice' }); pay += 1; }
        } else if (st !== 'void') {
          t.balance += total;
          if (status === 'due' && r() > 0.4 || i === 0) {
            this.payments.push({ id: pay, ref: `PAY-${String(pay).padStart(5, '0')}`, tutor_id: t.id, tutor: t.name, invoice: invoice.number, invoice_id: invoice.id, amount: total, method: 'Bank transfer', date: iso(addDays(today, -Math.floor(r() * 2))), status: 'waiting', slip: true, checked_by: null, reason: null });
            pay += 1;
          }
        }
      }
      this.history.push({ tutor_id: t.id, when: `${t.joined} 10:12`, by: 'Kaveesha (Admin)', what: status === 'trial' ? `Added with a ${RULES.trial_days}-day free trial on PRO` : `Added on ${plan(t.plan).name}, ${t.cycle}`, reason: null });
    }
    this.payments.sort((a, b) => b.date.localeCompare(a.date));
    this.invoices.sort((a, b) => b.issued.localeCompare(a.issued));
    PLANS.forEach((p) => { p.tutors = this.tutors.filter((t) => t.plan === p.key && !['cancelled', 'trial'].includes(t.status)).length; });
  }

  async handle(method, path, body) {
    await new Promise((res) => setTimeout(res, 120 + Math.random() * 200));
    const [route, query] = path.split('?');
    const q = Object.fromEntries(new URLSearchParams(query ?? ''));
    try {
      return { status: 200, payload: { ok: true, ...this.#route(method, route, q, body ?? {}) } };
    } catch (e) {
      return { status: e.status ?? 400, payload: { ok: false, error: { code: e.code ?? 'error', message: e.message, details: e.details ?? {} } } };
    }
  }

  #page(rows, q, search) {
    const term = (q.q ?? '').toLowerCase();
    let list = term ? rows.filter((r) => search(r).toLowerCase().includes(term)) : rows;
    if (q.sort) { const dir = q.dir === 'desc' ? -1 : 1; list = [...list].sort((a, b) => (a[q.sort] > b[q.sort] ? dir : a[q.sort] < b[q.sort] ? -dir : 0)); }
    const per = Number(q.per_page ?? 10); const page = Number(q.page ?? 1);
    return { data: list.slice((page - 1) * per, page * per), meta: { total: list.length, page, per_page: per, pages: Math.max(1, Math.ceil(list.length / per)) } };
  }

  #tutor(id) {
    const t = this.tutors.find((x) => x.id === Number(id));
    if (!t) throw Object.assign(new Error('Tutor not found.'), { status: 404 });
    return t;
  }

  #route(method, route, q, body) {
    let m;
    if (method === 'GET' && route === '/admin/dashboard') return { data: this.dashboard() };
    if (method === 'GET' && route === '/admin/tutors') {
      const rows = this.tutors.filter((t) => (!q.plan || t.plan === q.plan) && (!q.status || t.status === q.status) && (!q.cycle || t.cycle === q.cycle));
      return this.#page(rows, q, (t) => `${t.name} ${t.brand} ${t.email} ${t.mobile}`);
    }
    if (method === 'POST' && route === '/admin/tutors') return { data: this.#add(body) };
    if ((m = route.match(/^\/admin\/tutors\/(\d+)$/)) && method === 'GET') {
      const t = this.#tutor(m[1]);
      return { data: { tutor: t, plan: PLANS.find((p) => p.key === t.plan), invoices: this.invoices.filter((i) => i.tutor_id === t.id), payments: this.payments.filter((p) => p.tutor_id === t.id), history: this.history.filter((x) => x.tutor_id === t.id), addons: ADDONS, plans: PLANS, rules: RULES } };
    }
    if ((m = route.match(/^\/admin\/tutors\/(\d+)\/(\w+)$/)) && method === 'POST') return { data: this.#action(this.#tutor(m[1]), m[2], body) };
    if (method === 'GET' && route === '/admin/invoices') {
      const rows = this.invoices.filter((i) => (!q.status || i.status === q.status) && (!q.plan || i.plan.toLowerCase() === q.plan) && (!q.tutor || i.tutor_id === Number(q.tutor)));
      return this.#page(rows, q, (i) => `${i.number} ${i.tutor} ${i.brand}`);
    }
    if (method === 'GET' && route === '/admin/payments') {
      const rows = this.payments.filter((p) => (!q.status || p.status === q.status) && (!q.method || p.method === q.method) && (!q.tutor || p.tutor_id === Number(q.tutor)));
      return this.#page(rows, q, (p) => `${p.ref} ${p.tutor} ${p.invoice}`);
    }
    if ((m = route.match(/^\/admin\/payments\/(\d+)\/(approve|reject)$/)) && method === 'POST') {
      const p = this.payments.find((x) => x.id === Number(m[1]));
      if (m[2] === 'reject' && !(body.reason ?? '').trim()) throw Object.assign(new Error('Give a reason.'), { details: { reason: 'Tell the tutor why, for example "The slip amount does not match".' } });
      Object.assign(p, { status: m[2] === 'approve' ? 'approved' : 'rejected', checked_by: 'You', reason: body.reason ?? null });
      if (m[2] === 'approve') { const i = this.invoices.find((x) => x.id === p.invoice_id); if (i) Object.assign(i, { status: 'paid', paid: i.total }); const t = this.#tutor(p.tutor_id); t.balance = 0; t.status = 'active'; t.status_label = STATUS.active; }
      return { data: p };
    }
    if (method === 'GET' && route === '/admin/plans') return { data: { plans: PLANS, addons: ADDONS, rules: RULES, banks: BANKS } };
    if (method === 'GET' && route === '/admin/lookups') return { data: { plans: PLANS, addons: ADDONS, rules: RULES } };
    if (method === 'GET' && route === '/tutor/subscription') {
      const t = this.tutors[0];
      return { data: { tutor: t, plan: PLANS.find((p) => p.key === t.plan), plans: PLANS, invoices: this.invoices.filter((i) => i.tutor_id === t.id), payments: this.payments.filter((p) => p.tutor_id === t.id), banks: BANKS.filter((b) => b.on), addons: ADDONS, rules: RULES } };
    }
    if (method === 'POST' || method === 'PUT') return { data: { saved: true } };
    throw Object.assign(new Error(`No sample data for ${method} ${route}.`), { status: 404 });
  }

  #add(b) {
    const errors = {};
    ['first_name', 'last_name', 'email', 'mobile'].forEach((k) => { if (!(b[k] ?? '').trim()) errors[k] = 'This field is required.'; });
    if (b.email && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(b.email)) errors.email = 'Enter a valid e-mail address, for example ruwan@gmail.com.';
    if (b.email && this.tutors.some((t) => t.email === b.email.toLowerCase())) errors.email = 'A tutor with this e-mail address already exists.';
    if (b.custom_price && !(b.custom_price_reason ?? '').trim()) errors.custom_price_reason = 'Say why this tutor gets a different price.';
    if (Object.keys(errors).length) throw Object.assign(new Error('Please check the highlighted fields.'), { details: errors });
    return { id: this.tutors[0].id };
  }

  #action(t, action, b) {
    const needReason = ['extend', 'suspend', 'cancel', 'price'];
    if (needReason.includes(action) && !(b.reason ?? '').trim()) throw Object.assign(new Error('Give a reason.'), { details: { reason: 'The reason is kept in the history.' } });
    this.history.unshift({ tutor_id: t.id, when: `${iso(today)} ${new Date().toTimeString().slice(0, 5)}`, by: 'You', what: { extend: `Trial extended by ${b.days} days`, suspend: 'Suspended', reactivate: 'Reactivated', cancel: 'Subscription cancelled', plan: `Plan changed to ${String(b.plan).toUpperCase()}`, packs: `Extra students set to ${b.packs} × 50`, remind: 'Payment reminder sent', price: 'Custom price set' }[action] ?? action, reason: b.reason ?? null });
    return { ok: true };
  }

  dashboard() {
    const T = this.tutors;
    const paying = T.filter((t) => ['active', 'due', 'overdue'].includes(t.status));
    const mrr = paying.reduce((a, t) => a + (t.cycle === 'yearly' ? (t.price - PLANS.find((p) => p.key === t.plan).yearly_discount) : t.price), 0);
    const month = iso(today).slice(0, 7);
    const collected = this.payments.filter((p) => p.status === 'approved' && p.date.startsWith(month)).reduce((a, p) => a + p.amount, 0);
    const overdue = this.invoices.filter((i) => i.status === 'overdue');
    const waiting = this.payments.filter((p) => p.status === 'waiting');
    const trials = T.filter((t) => t.status === 'trial');
    const months = Array.from({ length: 12 }, (_, i) => addMonths(new Date(today.getFullYear(), today.getMonth(), 1), i - 11));
    const label = (d) => d.toLocaleDateString('en-GB', { month: 'short' });
    const r = rng(11);
    const collectedBars = months.map((d, i) => Math.round((60000 + i * 9000 + r() * 15000) / 500) * 500);
    collectedBars[11] = collected;
    return {
      stats: {
        paying: { value: paying.length, new: 4, bars: months.slice(-6).map((d, i) => ({ label: label(d), value: 18 + i * 2 + (i % 2) })) },
        trials: { value: trials.length, ending: trials.filter((t) => (new Date(t.trial_ends) - today) / DAY <= 3).length, bars: months.slice(-6).map((d, i) => ({ label: label(d), value: [3, 5, 4, 6, 5, trials.length][i] })) },
        mrr: { value: mrr, before: Math.round(mrr * 0.92), bars: months.slice(-6).map((d, i) => ({ label: label(d), value: Math.round(mrr * (0.7 + i * 0.06)) })) },
        collected: { value: collected, expected: collected + waiting.reduce((a, p) => a + p.amount, 0) + this.invoices.filter((i) => i.status === 'open').reduce((a, i) => a + i.total, 0), bars: months.slice(-6).map((d, i) => ({ label: label(months[6 + i]), value: collectedBars[6 + i] })) },
        overdue: { value: overdue.reduce((a, i) => a + i.total, 0), tutors: overdue.length },
        slips: { value: waiting.length, amount: waiting.reduce((a, p) => a + p.amount, 0) },
      },
      charts: {
        revenue: { labels: months.map(label), collected: collectedBars, invoiced: collectedBars.map((v, i) => v + (i === 11 ? 32000 : Math.round(r() * 8) * 1000)) },
        plans: PLANS.map((p) => ({ label: p.name, value: T.filter((t) => t.plan === p.key && ['active', 'due', 'overdue'].includes(t.status)).length })),
        trials: { labels: months.slice(-6).map(label), started: [6, 8, 7, 9, 8, 7], converted: [3, 5, 4, 6, 5, 2] },
        growth: { labels: months.slice(-6).map(label), joined: [4, 6, 5, 7, 6, 4], cancelled: [1, 0, 1, 2, 0, 1] },
      },
      todo: [
        { icon: 'receipt', tone: '', label: 'Payment slips to check', count: waiting.length, href: 'tutor-payments?status=waiting' },
        { icon: 'hourglass', tone: 'orange', label: 'Trials ending in 3 days', count: trials.filter((t) => (new Date(t.trial_ends) - today) / DAY <= 3).length, href: 'tutors?status=trial' },
        { icon: 'warning', tone: 'red', label: 'Overdue invoices', count: overdue.length, href: 'invoices?status=overdue' },
        { icon: 'user-add', tone: 'purple', label: 'Tutor sign-up requests', count: 3, href: 'requested-users' },
      ],
      recent: this.payments.filter((p) => p.status !== 'rejected').slice(0, 6),
      near_limit: T.filter((t) => t.limit && t.students / t.limit >= 0.85 && !['cancelled'].includes(t.status)).slice(0, 5),
      trials_list: trials.slice(0, 5),
    };
  }
}
