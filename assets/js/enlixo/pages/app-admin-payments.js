// Admin → Finance → Payments (admin/tutor-payments.php). Slips to check first, then every payment. Data: GET /admin/payments.
import { DataView } from '../components/DataView.js';
import { StatCard } from '../components/StatCard.js';
import { ActionMenu } from '../components/Popover.js';
import { QuickGuide } from '../components/QuickGuide.js';
import { api, h, lkr, plural, paymentColumns, previewNote } from './admin-common.js';

QuickGuide.attachAll();
const root = document.getElementById('payments-list');
document.getElementById('payments-actions').append(ActionMenu.button('Action', [
  { label: 'Invoices', icon: 'invoice', href: 'invoices' },
  { label: 'Bank Accounts', icon: 'bank', href: 'plans?tab=banks' },
]));

const all = (await api.get('/admin/payments', { per_page: 1000 })).data;
const of = (st) => all.filter((p) => p.status === st);
const month = new Date().toISOString().slice(0, 7);
const go = (st) => () => { location.href = `tutor-payments?status=${st}`; };
const holder = h('div');
const approved = of('approved').filter((p) => p.date.startsWith(month));
root.append(...[previewNote(), StatCard.row([
  { icon: 'receipt', label: 'Slips to Check', value: of('waiting').length, caption: of('waiting').length ? `${lkr(of('waiting').reduce((a, p) => a + p.amount, 0))} waiting` : 'all checked', onClick: go('waiting') },
  { icon: 'wallet', label: 'Received This Month', value: lkr(approved.reduce((a, p) => a + p.amount, 0)), caption: plural(approved.length, 'approved payment'), onClick: go('approved') },
  { icon: 'stop', label: 'Rejected', value: of('rejected').length, caption: 'the tutor was told why', onClick: go('rejected') },
], { cols: 'col-12 col-md-4' })].filter(Boolean), holder);

const reload = () => view.reload();
const view = new DataView({
  api,
  endpoint: '/admin/payments',
  key: 'payments',
  perPage: 10,
  perPageOptions: [10, 25, 50, 100],
  noun: ['payment', 'payments'],
  searchPlaceholder: 'Search by tutor, payment or invoice number',
  filters: [
    { key: 'status', label: 'Status', allLabel: '- All Status -', options: [{ value: 'waiting', label: 'Waiting' }, { value: 'approved', label: 'Approved' }, { value: 'rejected', label: 'Rejected' }] },
    { key: 'method', label: 'Method', allLabel: '- All Methods -', options: ['Bank transfer', 'Cash', 'Cheque', 'Card'].map((m) => ({ value: m, label: m })) },
  ],
  exportCsv: {
    filename: `tutor-payments-${new Date().toISOString().slice(0, 10)}.csv`,
    columns: [['Payment', (r) => r.ref], ['Tutor', (r) => r.tutor], ['Invoice', (r) => r.invoice], ['Paid on', (r) => r.date], ['Method', (r) => r.method], ['Amount', (r) => r.amount], ['Status', (r) => r.status], ['Checked by', (r) => r.checked_by ?? ''], ['Reason', (r) => r.reason ?? '']],
  },
  columns: paymentColumns({ admin: true, reload }),
  empty: { icon: 'receipt', title: 'No payments yet', text: 'Slips tutors upload and payments you record appear here.' },
}).mount(holder);
