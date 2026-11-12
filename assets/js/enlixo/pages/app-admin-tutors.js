// Admin → All Tutors (admin/tutors.php). V3 list: toolbar + filters + ticked rows + kebab. Data: GET /admin/tutors.
import { DataView } from '../components/DataView.js';
import { ActionMenu } from '../components/Popover.js';
import { QuickGuide } from '../components/QuickGuide.js';
import { api, h, lkr, day, inDays, chip, cell, person, meter, previewNote, Toast, STATUS_OPTIONS, PLAN_OPTIONS } from './admin-common.js';

QuickGuide.attachAll();
const root = document.getElementById('tutors-list');
document.getElementById('tutors-actions').append(ActionMenu.button('Action', [
  { label: 'Add Tutor', icon: 'user-add', href: 'add-tutor' },
  { label: 'Requested Tutors', icon: 'application', href: 'requested-users' },
  { label: 'Plans & Prices', icon: 'plans', href: 'plans' },
]));

const when = (t) => {
  if (t.status === 'trial') return cell(`Trial ends ${day(t.trial_ends)}`, inDays(t.trial_ends));
  if (t.status === 'cancelled') return cell(`Ended ${day(t.period_end)}`);
  if (t.status === 'complimentary') return cell(`Free until ${day(t.period_end)}`);
  if (['overdue', 'suspended'].includes(t.status)) return cell(`Was due ${day(t.period_end)}`, inDays(t.period_end));
  return cell(`Renews ${day(t.period_end)}`, inDays(t.period_end));
};
const remind = async (ids) => {
  const res = await Promise.allSettled(ids.map((id) => api.post(`/admin/tutors/${id}/remind`, {})));
  const sent = res.filter((r) => r.status === 'fulfilled' && r.value.data.sent).length;
  const nothing = res.filter((r) => r.status === 'rejected').length;
  const failed = ids.length - sent - nothing;
  if (sent) Toast.success(`Payment reminder e-mailed to ${sent} ${sent === 1 ? 'tutor' : 'tutors'}.`);
  if (failed) Toast.error(`${failed} e-mail${failed === 1 ? '' : 's'} could not be sent. The reminder is in their history; call or WhatsApp them.`);
  if (nothing) Toast.error(`${nothing} ${nothing === 1 ? 'tutor has' : 'tutors have'} nothing to pay, so no reminder was sent.`);
};

const holder = document.createElement('div');
root.append(...[previewNote()].filter(Boolean), holder);
const view = new DataView({
  api,
  endpoint: '/admin/tutors',
  key: 'tutors',
  perPage: 10,
  perPageOptions: [10, 25, 50, 100],
  noun: ['tutor', 'tutors'],
  searchPlaceholder: 'Search by name, institute, e-mail or mobile',
  filters: [
    { key: 'status', label: 'Status', allLabel: '- All Status -', options: STATUS_OPTIONS },
    { key: 'plan', label: 'Plan', allLabel: '- All Plans -', options: PLAN_OPTIONS },
    { key: 'cycle', label: 'Billing', allLabel: '- All -', options: [{ value: 'monthly', label: 'Monthly' }, { value: 'yearly', label: 'Yearly' }] },
  ],
  bulk: { actions: [{ value: 'remind', label: 'Send payment reminder' }], onApply: (ids) => remind(ids) },
  exportCsv: {
    filename: `tutors-${new Date().toISOString().slice(0, 10)}.csv`,
    columns: [['Tutor ID', (t) => t.id], ['Name', (t) => t.name], ['Institute', (t) => t.brand], ['E-mail', (t) => t.email], ['Mobile', (t) => t.mobile], ['Plan', (t) => t.plan_name], ['Billing', (t) => t.cycle], ['Status', (t) => t.status_label], ['Trial ends', (t) => t.trial_ends ?? ''], ['Period ends', (t) => t.period_end ?? ''], ['Students', (t) => t.students], ['Limit', (t) => t.limit], ['Monthly price', (t) => t.price], ['Balance', (t) => t.balance]],
  },
  columns: [
    { key: 'name', label: 'Tutor', sortable: true, render: (t) => h('a', { class: 'text-decoration-none', href: `tutor-details?tutor-id=${t.id}` }, person(t.name, t.brand)) },
    { key: 'plan', label: 'Plan', sortable: true, render: (t) => cell(t.plan_name, t.status === 'trial' ? 'Free trial, all features' : t.status === 'complimentary' ? 'Free access' : `${t.cycle === 'yearly' ? 'Yearly' : 'Monthly'} · ${lkr(t.price)}${t.custom_price ? ' (custom)' : ''}`) },
    { key: 'status', label: 'Status', render: (t) => chip(t.status) },
    { key: 'period_end', label: 'Renews / Trial Ends', sortable: true, render: when },
    { key: 'students', label: 'Students', sortable: true, render: (t) => meter(t.students, t.limit) },
    { key: 'balance', label: 'Balance', sortable: true, render: (t) => cell(t.balance ? lkr(t.balance) : 'Nothing due', t.balance ? 'open invoice' : null) },
    { key: 'actions', label: '', width: '48px', render: (t) => ActionMenu.kebab([
      { label: 'View Tutor', icon: 'view', href: `tutor-details?tutor-id=${t.id}` },
      { label: 'Invoices', icon: 'invoice', href: `tutor-details?tutor-id=${t.id}&tab=invoices` },
      { label: 'Payment History', icon: 'receipt', href: `tutor-details?tutor-id=${t.id}&tab=payments` },
      t.balance ? { label: 'Send Payment Reminder', icon: 'send', onClick: () => remind([t.id]) } : null,
      t.status === 'trial' ? { label: 'Extend Trial', icon: 'hourglass', href: `tutor-details?tutor-id=${t.id}&do=extend` } : null,
    ], `Actions for ${t.name}`) },
  ],
  empty: { icon: 'tutors', title: 'No tutors yet', text: 'Add a tutor or approve a sign-up request to see them here.', action: h('a', { class: 'custom-btn px-4 text-decoration-none', href: 'add-tutor' }, 'Add Tutor') },
}).mount(holder);
window.adminTutors = view;
