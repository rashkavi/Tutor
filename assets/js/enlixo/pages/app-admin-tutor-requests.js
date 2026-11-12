// Admin → Requested Tutors (admin/requested-users.php). Online tutor sign-up requests.
// GET /admin/tutor-requests (all, with counts); the list filters in the page. A row opens the request:
// Approve → add-tutor?request=ID (Add Tutor filled in), Reject {reason}, Open again.
import { DataView } from '../components/DataView.js';
import { ActionMenu } from '../components/Popover.js';
import { QuickGuide } from '../components/QuickGuide.js';
import { StatCard } from '../components/StatCard.js';
import { api, h, fill, icon, BsModal, Toast, chip, cell, person, facts, plural } from './admin-common.js';

QuickGuide.attachAll();
const root = document.getElementById('requests-page');
document.getElementById('requests-actions').append(ActionMenu.button('Action', [
  { label: 'All Tutors', icon: 'tutors', href: 'tutors' },
  { label: 'Add Tutor', icon: 'user-add', href: 'add-tutor' },
  { label: 'Sign-up page', icon: 'link', href: '../tutor-registration-application.php' },
  { label: 'Plans & Prices', icon: 'plans', href: 'plans' },
]));

const status = (st) => (st === 'pending' ? chip('waiting', 'Pending') : chip(st));
const PLAN = { start: 'START', grow: 'GROW', pro: 'PRO' };
const when = (d) => new Date(d.replace(' ', 'T')).toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit' });
const ago = (d) => {
  const m = Math.round((Date.now() - new Date(d.replace(' ', 'T'))) / 60000);
  if (m < 60) return `${Math.max(1, m)} min ago`;
  if (m < 1440) return plural(Math.round(m / 60), 'hour') + ' ago';
  return plural(Math.round(m / 1440), 'day') + ' ago';
};
const wa = (n) => `https://wa.me/94${String(n).replace(/\D/g, '').replace(/^0|^94/, '')}`;

let all = [];
let counts = {};
let show = new URLSearchParams(location.search).get('status') ?? 'pending';
const statsBox = h('div', { class: 'mb-4' });
const holder = h('div');
fill(root, statsBox, holder);

const local = {
  get: async (_ep, p) => {
    const q = (p.q ?? '').toLowerCase();
    let rows = all.filter((r) => (!show || r.status === show) && (!p.plan || r.plan === p.plan) && (!p.mode || r.mode === p.mode)
      && (!q || `${r.name} ${r.email} ${r.mobile} ${r.subject} ${r.institute} ${r.district} ${r.ref}`.toLowerCase().includes(q)));
    if (p.sort) {
      const k = p.sort;
      rows = [...rows].sort((a, b) => ((a[k] ?? '') > (b[k] ?? '') ? 1 : (a[k] ?? '') < (b[k] ?? '') ? -1 : 0) * (p.dir === 'desc' ? -1 : 1));
    }
    const per = Number(p.per_page) || 10;
    return { data: rows.slice((p.page - 1) * per, p.page * per), meta: { total: rows.length, pages: Math.max(1, Math.ceil(rows.length / per)), per_page: per } };
  },
};

function stats() {
  const pick = (s) => () => { show = show === s ? '' : s; const u = new URL(location); s && show ? u.searchParams.set('status', s) : u.searchParams.delete('status'); history.replaceState(null, '', u); stats(); view.reload(); };
  fill(statsBox, StatCard.row([
    { tone: 'orange', icon: 'hourglass', label: 'Waiting for You', value: counts.pending ?? 0, caption: 'requests to call and decide', onClick: pick('pending'), selected: show === 'pending' },
    { tone: 'blue', icon: 'application', label: 'This Week', value: counts.week ?? 0, caption: 'new requests in the last 7 days', onClick: pick(''), selected: show === '' },
    { tone: 'green', icon: 'user-check', label: 'Approved', value: counts.approved ?? 0, caption: 'became tutors', onClick: pick('approved'), selected: show === 'approved' },
    { tone: 'red', icon: 'user-cross', label: 'Rejected', value: counts.rejected ?? 0, caption: 'not taken', onClick: pick('rejected'), selected: show === 'rejected' },
  ], { cols: 'col-12 col-md-6 col-xl-3' }));
}

const view = new DataView({
  api: local,
  endpoint: '/local/requests',
  key: 'tutor-requests',
  perPage: 10,
  perPageOptions: [10, 25, 50],
  noun: ['request', 'requests'],
  searchPlaceholder: 'Search by name, e-mail, mobile, subject or district',
  filters: [
    { key: 'plan', label: 'Plan', allLabel: '- All Plans -', options: Object.entries(PLAN).map(([value, label]) => ({ value, label })) },
    { key: 'mode', label: 'Teaching', allLabel: '- All -', options: [{ value: 'physical', label: 'Physical' }, { value: 'online', label: 'Online' }, { value: 'both', label: 'Both' }] },
  ],
  exportCsv: {
    filename: `tutor-requests-${new Date().toISOString().slice(0, 10)}.csv`,
    columns: [['Ref', (r) => r.ref], ['Name', (r) => r.name], ['E-mail', (r) => r.email], ['Mobile', (r) => r.mobile], ['WhatsApp', (r) => r.whatsapp], ['NIC', (r) => r.nic], ['Subject', (r) => r.subject], ['Grades', (r) => r.grades],
      ['Institute', (r) => r.institute], ['Teaching', (r) => r.mode_label], ['District', (r) => r.district], ['Students', (r) => r.students ?? ''], ['Plan', (r) => PLAN[r.plan] ?? ''], ['Billing', (r) => r.cycle], ['Add-ons', (r) => r.addons.join(' ')],
      ['Heard from', (r) => r.heard_from], ['Message', (r) => r.message], ['Requested', (r) => r.requested_at], ['Status', (r) => r.status], ['Decision note', (r) => r.decision_note ?? '']],
  },
  columns: [
    { key: 'name', label: 'Tutor', sortable: true, render: (r) => h('button', { type: 'button', class: 'btn p-0 border-0 text-start', onclick: () => open(r.id) }, person(r.name, r.institute || r.email)) },
    { key: 'subject', label: 'Teaches', sortable: true, render: (r) => cell(r.subject || '-', [r.grades, r.mode_label].filter(Boolean).join(' · ')) },
    { key: 'plan', label: 'Plan Asked', sortable: true, render: (r) => cell(PLAN[r.plan] ?? '-', r.plan ? `${r.cycle === 'yearly' ? 'Yearly' : 'Monthly'}${r.students ? ` · ~${r.students} students` : ''}` : null) },
    { key: 'mobile', label: 'Contact', render: (r) => h('div', { class: 'd-flex flex-column' },
      h('a', { class: 'text-14 main-text text-decoration-none', href: `tel:${r.mobile}` }, r.mobile),
      h('a', { class: 'text-12 dark-theme text-decoration-none d-inline-flex align-items-center gap-1', href: wa(r.whatsapp || r.mobile), target: '_blank', rel: 'noopener' }, icon('whatsapp', { size: 14 }), 'WhatsApp')) },
    { key: 'requested_at', label: 'Requested', sortable: true, render: (r) => cell(ago(r.requested_at), r.ref) },
    { key: 'status', label: 'Status', render: (r) => status(r.status) },
    { key: 'actions', label: '', width: '48px', render: (r) => ActionMenu.kebab([
      { label: 'View request', icon: 'view', onClick: () => open(r.id) },
      r.status === 'pending' && { label: 'Approve', icon: 'check', href: `add-tutor?request=${r.id}` },
      r.status === 'pending' && { label: 'Reject', icon: 'close', onClick: () => reject(r) },
      r.status === 'approved' && r.tutor_id && { label: 'Open tutor', icon: 'tutors', href: `tutor-details?tutor-id=${r.tutor_id}` },
      r.status === 'rejected' && { label: 'Open again', icon: 'undo', onClick: () => reopen(r) },
    ].filter(Boolean), `Actions for ${r.name}`) },
  ],
  empty: { icon: 'application', title: 'No requests here', text: 'Tutors who send the sign-up form on the website show here.' },
});

await load();
view.mount(holder);
if (new URLSearchParams(location.search).get('request')) open(Number(new URLSearchParams(location.search).get('request')));

async function load() {
  const { data } = await api.get('/admin/tutor-requests');
  all = data.requests;
  counts = data.counts;
  stats();
}
async function refresh() { await load(); view.reload(); }

async function open(id) {
  let r;
  try { r = (await api.get(`/admin/tutor-requests/${id}`)).data; } catch (e) { Toast.error(e.message); return; }
  const m = BsModal.open({
    title: r.name,
    subtitle: `${r.ref} · requested ${when(r.requested_at)}`,
    icon: 'application',
    size: 'lg',
    body: h('div', { class: 'd-flex flex-column gap-4' },
      h('div', { class: 'd-flex flex-wrap gap-2 align-items-center' }, status(r.status),
        r.decided_at ? h('span', { class: 'text-13 sub-text' }, `${r.status === 'approved' ? 'Approved' : 'Rejected'} by ${r.decided_by ?? 'admin'} on ${when(r.decided_at)}`) : null),
      r.status === 'rejected' && r.decision_note ? h('div', { class: 'ad-soft p-3 text-14' }, h('b', {}, 'Reason: '), r.decision_note) : null,
      h('div', {}, h('div', { class: 'sub-topic mb-2' }, 'Contact'), facts([
        ['E-mail', h('a', { href: `mailto:${r.email}`, class: 'text-decoration-none' }, r.email)],
        ['Mobile', h('a', { href: `tel:${r.mobile}`, class: 'text-decoration-none' }, r.mobile)],
        ['WhatsApp', h('a', { href: wa(r.whatsapp || r.mobile), target: '_blank', rel: 'noopener', class: 'text-decoration-none' }, r.whatsapp || r.mobile)],
        ['NIC', r.nic || '-'],
      ])),
      h('div', {}, h('div', { class: 'sub-topic mb-2' }, 'Classes'), facts([
        ['Main subject', r.subject || '-'], ['Grades or exams', r.grades || '-'], ['Institute', r.institute || '-'], ['Teaching', r.mode_label || '-'],
        ['District', r.district || '-'], ['Students', r.students ?? '-'], ['Heard from', r.heard_from || '-'],
      ])),
      h('div', {}, h('div', { class: 'sub-topic mb-2' }, 'Plan they picked'), facts([
        ['Plan', PLAN[r.plan] ?? '-'], ['Billing', r.cycle === 'yearly' ? 'Yearly' : 'Monthly'], ['Add-ons', r.addons.length ? r.addons.join(', ').replaceAll('_', ' ') : 'None', true],
      ])),
      r.message ? h('div', {}, h('div', { class: 'sub-topic mb-2' }, 'Message'), h('div', { class: 'ad-soft p-3 text-14', style: { whiteSpace: 'pre-wrap' } }, r.message)) : null,
      r.history.length ? h('div', {}, h('div', { class: 'sub-topic mb-2' }, 'History'),
        h('ul', { class: 'list-unstyled m-0 d-flex flex-column gap-2' }, r.history.map((x) => h('li', { class: 'text-13' }, h('b', {}, x.what), ` · ${x.by} · ${when(x.at)}`, x.note ? h('div', { class: 'sub-text' }, x.note) : null)))) : null),
    footerStart: [h('a', { class: 'custom-btn-outline d-inline-flex align-items-center gap-2 px-3 text-decoration-none', href: wa(r.whatsapp || r.mobile), target: '_blank', rel: 'noopener' }, icon('whatsapp', { size: 18 }), 'WhatsApp')],
    footerEnd: [
      BsModal.button('Close', { onclick: () => m.close() }),
      r.status === 'pending' ? BsModal.button('Reject', { danger: true, onclick: () => { m.close(); reject(r); } }) : null,
      r.status === 'pending' ? BsModal.button('Approve', { success: true, onclick: () => { location.href = `add-tutor?request=${r.id}`; } }) : null,
      r.status === 'approved' && r.tutor_id ? BsModal.button('Open Tutor', { primary: true, onclick: () => { location.href = `tutor-details?tutor-id=${r.tutor_id}`; } }) : null,
      r.status === 'rejected' ? BsModal.button('Open Again', { primary: true, onclick: () => { m.close(); reopen(r); } }) : null,
    ].filter(Boolean),
  });
}

function reject(r) {
  const reason = h('textarea', { class: 'form-control custom-input', rows: 3, maxlength: 255, placeholder: 'For example: Not a tutor, wrong numbers, asked to join later', id: 'rq-reason' });
  const err = h('div', { class: 'text-12 text-dark-red mt-1 d-none' });
  const m = BsModal.open({
    title: `Reject ${r.name}?`,
    subtitle: 'The request moves to Rejected. You can open it again later.',
    icon: 'close',
    tone: 'danger',
    body: h('div', {}, h('label', { class: 'text-14 main-text mb-1 required', for: 'rq-reason' }, 'Reason'), reason, err, h('div', { class: 'text-12 sub-text mt-1' }, 'Only admins see the reason. No e-mail is sent.')),
    footerEnd: [
      BsModal.button('Cancel', { onclick: () => m.close() }),
      BsModal.button('Reject Request', { danger: true, onclick: async (e) => {
        const btn = e.currentTarget;
        btn.disabled = true;
        try {
          await api.post(`/admin/tutor-requests/${r.id}/reject`, { reason: reason.value });
          m.close();
          Toast.success(`${r.name}'s request was rejected.`);
          refresh();
        } catch (x) {
          btn.disabled = false;
          err.textContent = x.details?.reason ?? x.message;
          err.classList.remove('d-none');
          reason.classList.add('is-invalid');
        }
      } }),
    ],
  });
}

async function reopen(r) {
  try {
    await api.post(`/admin/tutor-requests/${r.id}/reopen`, {});
    Toast.success(`${r.name}'s request is pending again.`);
    refresh();
  } catch (e) { Toast.error(e.message); }
}
