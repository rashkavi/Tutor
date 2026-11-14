// Admin → Tutors → Requested Tutors → one request (admin/requested-user-details.php?id=), like V3's requested student page:
// header with Actions + Approve, the tutor's card on the left, the steps and the details on the right, the history below.
// Data: GET /admin/tutor-requests/{id}. Approve opens Add Tutor filled in (add-tutor?request=ID).
import { QuickGuide } from '../components/QuickGuide.js';
import { ActionMenu } from '../components/Popover.js';
import { DetailPage } from '../components/DetailPage.js';
import { api, h, fill, icon, lkr } from './admin-common.js';
import { PLAN, statusChip, when, rejectRequest, reopenRequest, noteRequest } from './tutor-request-common.js';

QuickGuide.attachAll();
const root = document.getElementById('request-page');
const actions = document.getElementById('request-actions');
const id = Number(new URLSearchParams(location.search).get('id') || 0);
const open = new Set(['classes', 'plan']);
let r = null;
let plans = [];

fill(root, DetailPage.loading());
load();

async function load() {
  try {
    const [req, info] = await Promise.all([api.get(`/admin/tutor-requests/${id}`), api.get('/public/tutor-signup').catch(() => ({ data: { plans: [] } }))]);
    r = req.data;
    plans = info.data.plans ?? [];
    document.title = `${r.ref} ${r.name} - Admin`;
    draw();
  } catch (e) {
    fill(root, DetailPage.notFound(e.status === 404 ? 'This request was not found.' : `Couldn't load the request: ${e.message}`, ['Back to Requested Tutors', 'requested-users']));
  }
}

const day = (s) => (s ? String(s).slice(0, 10) : '');
const calls = () => r.history.filter((x) => x.what === 'Called the tutor');

function draw() {
  // header: Actions menu + the main button
  const items = [
    r.status === 'pending' && { label: 'Mark as Called', icon: 'phone', onClick: () => noteRequest(r, load, { called: true }) },
    { label: 'Add Note', icon: 'edit', onClick: () => noteRequest(r, load) },
    { label: 'WhatsApp', icon: 'whatsapp', href: DetailPage.waLink(r.whatsapp || r.mobile) },
    r.status === 'pending' && { label: 'Reject', icon: 'close', danger: true, onClick: () => rejectRequest(r, load) },
    r.status === 'rejected' && { label: 'Open Again', icon: 'undo', onClick: () => reopenRequest(r, load) },
  ].filter(Boolean);
  const main = r.status === 'pending' ? h('a', { class: 'custom-btn ex-btn-lg px-5 text-decoration-none d-inline-flex align-items-center', href: `add-tutor?request=${r.id}` }, 'Approve')
    : r.status === 'approved' && r.tutor_id ? h('a', { class: 'custom-btn ex-btn-lg px-5 text-decoration-none d-inline-flex align-items-center', href: `tutor-details?tutor-id=${r.tutor_id}` }, 'Open Tutor')
      : r.status === 'rejected' ? h('button', { type: 'button', class: 'custom-btn ex-btn-lg px-5 border-0', onclick: () => reopenRequest(r, load) }, 'Open Again') : null;
  fill(actions, ActionMenu.button('Actions', items, { className: 'custom-outline-btn ex-btn-lg px-4 d-inline-flex align-items-center gap-2' }), main);

  const plan = plans.find((p) => p.key === r.plan);
  const price = !plan ? '-' : plan.free ? 'Free' : plan.custom ? 'Agreed with the team' : `${lkr(plan.monthly)} a month`;
  const offerPrice = r.promo && plan && !plan.free && !plan.custom ? lkr(Math.round((plan.monthly * (100 - r.promo.percent)) / 1000) * 10) : null;
  const sections = [
    { key: 'classes', title: 'Classes', icon: 'classes', items: [
      { label: 'Main subject', value: r.subject, required: true }, { label: 'Grades or exams', value: r.grades },
      { label: 'Institute / class name', value: r.institute }, { label: 'How they teach', value: r.mode_label, required: true },
      { label: 'District', value: r.district }, { label: 'About how many students', value: r.students },
      { label: 'Heard about us from', value: r.heard_from }, { label: 'NIC', value: r.nic },
    ] },
    { key: 'plan', title: 'Plan they picked', icon: 'plans', items: [
      { label: 'Plan', value: PLAN[r.plan] }, { label: 'Billing', value: r.plan === 'free' || r.plan === 'pro' ? '-' : (r.cycle === 'yearly' ? 'Yearly' : 'Monthly') },
      { label: 'Price', value: price }, { label: 'Offer code', value: r.promo ? `${r.promo.code}: ${r.promo.title}` : null },
      offerPrice ? { label: 'Price with the offer', value: `${offerPrice} a month` } : null,
      { label: 'Add-ons they are interested in', value: r.addons.length ? r.addons.join(', ').replaceAll('_', ' ') : null, wide: true },
    ].filter(Boolean) },
    { key: 'request', title: 'Request details', icon: 'application', items: [
      { label: 'Reference', value: r.ref }, { label: 'Status', node: statusChip(r.status) },
      { label: 'Requested on', value: when(r.requested_at) }, { label: 'Decided by', value: r.decided_by },
      { label: 'Message from the tutor', value: r.message, wide: true },
      r.decision_note ? { label: r.status === 'rejected' ? 'Reason' : 'Admin note', value: r.decision_note, wide: true } : null,
    ].filter(Boolean) },
  ];
  const allItems = sections.flatMap((s) => s.items.filter((i) => !i.node));
  const filled = allItems.filter((i) => i.value !== null && i.value !== undefined && String(i.value).trim() !== '').length;

  const called = calls();
  const steps = [
    { title: 'Received', sub: day(r.requested_at), state: 'done' },
    called.length ? { title: 'Called', sub: `By ${called[called.length - 1].by} · ${day(called[called.length - 1].at)}`, state: 'done' }
      : r.status === 'pending' ? { title: 'Call the tutor', sub: 'Talk before you approve', state: 'now' } : { title: 'Called', sub: 'Not marked', state: 'todo' },
    r.status === 'approved' ? { title: 'Approved', sub: [r.decided_by && `By ${r.decided_by}`, day(r.decided_at)].filter(Boolean).join(' · '), state: 'done' }
      : r.status === 'rejected' ? { title: 'Rejected', sub: r.decision_note ?? day(r.decided_at), state: 'bad' }
        : { title: 'Decision', sub: 'Approve or reject', state: called.length ? 'now' : 'todo' },
    r.status === 'approved' && r.tutor_id ? { title: 'Tutor account', sub: `Tutor #${r.tutor_id}`, state: 'done' } : { title: 'Tutor account', sub: 'After approval', state: 'todo' },
  ];

  const right = [
    DetailPage.card('Request', [
      h('div', { class: 'mb-4' }, DetailPage.steps(steps)),
      r.promo ? h('div', { class: 'ex-panel-soft p-3 mb-3 d-flex align-items-center gap-3' }, h('span', { class: 'ex-acc-ico' }, icon('gift', { size: 18 })),
        h('div', { class: 'text-14 main-text' }, h('b', {}, `Offer ${r.promo.code}: `), `${r.promo.title}. Approve fills in the discounted price on Add Tutor.`)) : null,
      DetailPage.sections(sections, open, draw),
    ], { top: DetailPage.progress(filled, allItems.length), tools: DetailPage.expandTools(sections, open, draw), cls: 'h-100' }),
  ];

  fill(root, ...DetailPage.layout(
    DetailPage.profile({
      name: r.name, subtitle: [r.subject, r.institute].filter(Boolean).join(' · ') || 'Tutor sign-up', ref: r.ref, status: statusChip(r.status),
      dot: { pending: 'var(--dark-orange)', approved: 'var(--dark-green)', rejected: 'var(--dark-red)' }[r.status],
      mobile: r.mobile, whatsapp: r.whatsapp, email: r.email,
      lines: [['location', 'District', r.district]],
      facts: [['Requested on', when(r.requested_at)], ['Plan', PLAN[r.plan]], ['Students', r.students ? `About ${r.students}` : null], ['Called', called.length ? `${called.length} time${called.length > 1 ? 's' : ''}` : 'Not yet'], ['Last update', when(r.history[0]?.at ?? r.requested_at)]],
    }),
    right,
    DetailPage.history(r.history.map((x) => ({ who: x.by, when: when(x.at), what: x.what, note: x.note })).concat([{ who: r.name, when: when(r.requested_at), what: 'Sent the request', note: 'Website sign-up form' }]), { title: 'History' })));
}
