// Admin → Finance → Invoices (admin/invoices.php). Stat cards + V3 list. Data: GET /admin/invoices.
import { DataView } from '../components/DataView.js';
import { StatCard } from '../components/StatCard.js';
import { ActionMenu } from '../components/Popover.js';
import { QuickGuide } from '../components/QuickGuide.js';
import { api, h, lkr, plural, invoiceColumns, invoiceDialog, previewNote, PLAN_OPTIONS } from './admin-common.js';

QuickGuide.attachAll();
const root = document.getElementById('invoices-list');
document.getElementById('invoices-actions').append(ActionMenu.button('Action', [
  { label: 'Payments to Check', icon: 'receipt', href: 'tutor-payments?status=waiting' },
  { label: 'Trial & Billing Rules', icon: 'settings', href: 'plans?tab=rules' },
]));

const all = (await api.get('/admin/invoices', { per_page: 1000 })).data;
const sum = (st) => all.filter((i) => i.status === st);
const month = new Date().toISOString().slice(0, 7);
const go = (st) => () => { location.href = `invoices?status=${st}`; };
const holder = h('div');
root.append(...[previewNote(), StatCard.row([
  { icon: 'invoice', label: 'Open', value: lkr(sum('open').reduce((a, i) => a + i.total, 0)), caption: `${plural(sum('open').length, 'invoice')} not yet due`, onClick: go('open') },
  { icon: 'warning', label: 'Overdue', value: lkr(sum('overdue').reduce((a, i) => a + i.total, 0)), caption: `${plural(sum('overdue').length, 'invoice')} past the due date`, onClick: go('overdue') },
  { icon: 'receipt', label: 'Paid This Month', value: lkr(sum('paid').filter((i) => i.due.startsWith(month)).reduce((a, i) => a + i.total, 0)), caption: `${plural(sum('paid').filter((i) => i.due.startsWith(month)).length, 'invoice')}`, onClick: go('paid') },
  { icon: 'stop', label: 'Void', value: sum('void').length, caption: 'cancelled with a reason', onClick: go('void') },
])].filter(Boolean), holder);

const reload = () => view.reload();
const view = new DataView({
  api,
  endpoint: '/admin/invoices',
  key: 'invoices',
  perPage: 10,
  perPageOptions: [10, 25, 50, 100],
  noun: ['invoice', 'invoices'],
  searchPlaceholder: 'Search by invoice number, tutor or institute',
  filters: [
    { key: 'status', label: 'Status', allLabel: '- All Status -', options: [{ value: 'open', label: 'Open' }, { value: 'overdue', label: 'Overdue' }, { value: 'paid', label: 'Paid' }, { value: 'void', label: 'Void' }] },
    { key: 'plan', label: 'Plan', allLabel: '- All Plans -', options: PLAN_OPTIONS },
  ],
  exportCsv: {
    filename: `invoices-${new Date().toISOString().slice(0, 10)}.csv`,
    columns: [['Invoice', (r) => r.number], ['Tutor', (r) => r.tutor], ['Institute', (r) => r.brand], ['Plan', (r) => r.plan], ['Period from', (r) => r.period_from], ['Period to', (r) => r.period_to], ['Issued', (r) => r.issued], ['Due', (r) => r.due], ['Total', (r) => r.total], ['Paid', (r) => r.paid], ['Status', (r) => r.status]],
  },
  columns: invoiceColumns({ admin: true, open: (r) => invoiceDialog(r, { admin: true, reload }) }),
  empty: { icon: 'invoice', title: 'No invoices yet', text: 'Invoices appear here when trials end and paid periods start.' },
}).mount(holder);
