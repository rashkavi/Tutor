// Admin → Trial Requests (admin/trial-requests.php). Tutors who asked to try STARTER / GROW free for 7 days.
// GET /admin/trial-requests (all, with counts); the list filters in the page.
// Approve {days, note} starts the trial at once; Reject {note} needs a reason the tutor sees (src/Platform/TrialRequests.php).
import { DataView } from '../components/DataView.js';
import { ActionMenu } from '../components/Popover.js';
import { QuickGuide } from '../components/QuickGuide.js';
import { StatCard } from '../components/StatCard.js';
import { api, h, fill, icon, cell, person, plural, BsModal, Toast } from './admin-common.js';
import { PLAN, statusChip, when, textDialog } from './tutor-request-common.js';

QuickGuide.attachAll();
const root = document.getElementById('trial-page');
document.getElementById('trial-actions').append(ActionMenu.button('Action', [
  { label: 'All Tutors', icon: 'tutors', href: 'tutors' },
  { label: 'Requested Tutors', icon: 'application', href: 'requested-users' },
  { label: 'Trial & Billing Rules', icon: 'plans', href: 'plans?tab=rules' },
]));

const ago = (d) => {
  const m = Math.round((Date.now() - new Date(String(d).replace(' ', 'T'))) / 60000);
  if (m < 60) return `${Math.max(1, m)} min ago`;
  if (m < 1440) return plural(Math.round(m / 60), 'hour') + ' ago';
  return plural(Math.round(m / 1440), 'day') + ' ago';
};

let all = [];
let counts = {};
let show = new URLSearchParams(location.search).get('status') ?? 'pending';
const statsBox = h('div', { class: 'mb-4' });
const holder = h('div');
fill(root, statsBox, holder);

const local = {
  get: async (_ep, p) => {
    const q = (p.q ?? '').toLowerCase();
    let rows = all.filter((r) => (!show || r.status === show) && (!p.plan || r.plan === p.plan)
      && (!q || `${r.tutor} ${r.email} ${r.mobile} ${r.note}`.toLowerCase().includes(q)));
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
    { tone: 'orange', icon: 'hourglass', label: 'Waiting for You', value: counts.pending ?? 0, caption: 'trial requests to decide', onClick: pick('pending'), selected: show === 'pending' },
    { tone: 'green', icon: 'check', label: 'Approved', value: counts.approved ?? 0, caption: 'trials started', onClick: pick('approved'), selected: show === 'approved' },
    { tone: 'red', icon: 'close', label: 'Rejected', value: counts.rejected ?? 0, caption: 'with a reason the tutor sees', onClick: pick('rejected'), selected: show === 'rejected' },
    { tone: 'blue', icon: 'application', label: 'All Requests', value: all.length, caption: `${counts.cancelled ?? 0} withdrawn by the tutor`, onClick: pick(''), selected: show === '' },
  ], { cols: 'col-12 col-md-6 col-xl-3' }));
}

function approve(r) {
  const days = h('input', { class: 'form-control custom-input', type: 'number', min: 1, max: 14, value: 7, id: 'tr-days', placeholder: 'Enter the trial length in days, e.g. 7' });
  const note = h('textarea', { class: 'form-control custom-input', rows: 2, maxlength: 500, id: 'tr-note', placeholder: 'For example: Called, has 120 students, wants to see WhatsApp messages' });
  const err = h('div', { class: 'text-12 text-dark-red mt-1 d-none' });
  const m = BsModal.open({
    title: `Start ${PLAN[r.plan] ?? r.plan} trial for ${r.tutor}?`, subtitle: 'The trial starts now. Their own plan and invoices do not change.', icon: 'magic', tone: 'success',
    body: h('div', { class: 'd-flex flex-column gap-3' },
      h('div', {}, h('label', { class: 'text-14 main-text mb-1 required', for: 'tr-days' }, 'Trial length (days)'), days, h('div', { class: 'text-12 sub-text mt-1' }, '1 to 14 days. 7 is what the tutor asked for.')),
      h('div', {}, h('label', { class: 'text-14 main-text mb-1', for: 'tr-note' }, 'Note'), note, h('div', { class: 'text-12 sub-text mt-1' }, 'Kept in the tutor\'s history.')),
      err),
    footerEnd: [
      BsModal.button('Cancel', { onclick: () => m.close() }),
      BsModal.button('Approve & Start Trial', { success: true, onclick: async (e) => {
        const btn = e.currentTarget;
        btn.disabled = true;
        try {
          const { data } = await api.post(`/admin/trial-requests/${r.id}/approve`, { days: Number(days.value) || 7, note: note.value });
          Toast.success(data.message);
          m.close();
          refresh();
        } catch (x) {
          btn.disabled = false;
          err.textContent = x.message;
          err.classList.remove('d-none');
        }
      } }),
    ],
  });
}

function reject(r) {
  textDialog({
    title: `Reject ${r.tutor}'s trial request?`, subtitle: 'The tutor sees your reason on their Upgrade page and can ask again.', icon: 'close', tone: 'danger',
    label: 'Reason', placeholder: 'For example: Already tried GROW last month. Call us to talk about a discount.', help: 'Up to 500 characters. The tutor sees it.',
    button: 'Reject Request', danger: true,
    send: async (n) => { await api.post(`/admin/trial-requests/${r.id}/reject`, { note: n }); Toast.success('Request rejected.'); refresh(); },
  });
}

const view = new DataView({
  api: local,
  endpoint: '/local/trial-requests',
  key: 'trial-requests',
  perPage: 10,
  perPageOptions: [10, 25, 50],
  noun: ['request', 'requests'],
  searchPlaceholder: 'Search by tutor, e-mail, mobile or note',
  filters: [
    { key: 'plan', label: 'Plan', allLabel: '- All Plans -', options: [{ value: 'start', label: 'STARTER' }, { value: 'grow', label: 'GROW' }] },
  ],
  exportCsv: {
    filename: `trial-requests-${new Date().toISOString().slice(0, 10)}.csv`,
    columns: [['Tutor', (r) => r.tutor], ['E-mail', (r) => r.email], ['Mobile', (r) => r.mobile], ['Plan now', (r) => PLAN[r.current_plan] ?? ''], ['Plan asked', (r) => PLAN[r.plan] ?? r.plan],
      ['Note', (r) => r.note], ['Requested', (r) => r.requested_at], ['Status', (r) => r.status], ['Decided by', (r) => r.decided_by], ['Decided', (r) => r.decided_at], ['Decision note', (r) => r.decision_note]],
  },
  columns: [
    { key: 'tutor', label: 'Tutor', sortable: true, render: (r) => h('a', { class: 'text-decoration-none', href: `tutor-details?tutor-id=${r.tutor_id}` }, person(r.tutor, r.email || r.mobile)) },
    { key: 'plan', label: 'Wants to Try', sortable: true, render: (r) => cell(PLAN[r.plan] ?? r.plan, `Now on ${PLAN[r.current_plan] ?? '-'} · ${r.days} days`) },
    { key: 'note', label: 'Note from Tutor', render: (r) => h('span', { class: 'text-14 main-text text-wrap d-inline-block', style: 'max-width:280px' }, r.note || '-') },
    { key: 'requested_at', label: 'Requested', sortable: true, render: (r) => cell(ago(r.requested_at), when(r.requested_at)) },
    { key: 'status', label: 'Status', render: (r) => h('div', { class: 'd-flex flex-column gap-1' }, statusChip(r.status),
      r.decided_at ? h('span', { class: 'text-12 sub-text' }, `${r.decided_by || 'Tutor'} · ${when(r.decided_at)}`) : null) },
    { key: 'actions', label: '', width: '48px', render: (r) => ActionMenu.kebab([
      r.status === 'pending' && { label: 'Approve & start trial', icon: 'check', onClick: () => approve(r) },
      r.status === 'pending' && { label: 'Reject', icon: 'close', danger: true, onClick: () => reject(r) },
      { label: 'Open tutor', icon: 'tutors', href: `tutor-details?tutor-id=${r.tutor_id}` },
    ].filter(Boolean), `Actions for ${r.tutor}`) },
  ],
  empty: { icon: 'inbox', title: 'No trial requests here', text: 'Tutors ask for a free trial from their Upgrade page. Their requests show here.' },
});

await load();
view.mount(holder);

async function load() {
  const res = await api.get('/admin/trial-requests');
  all = res.data;
  counts = res.meta?.counts ?? {};
  stats();
}
async function refresh() { await load(); view.reload(); }
