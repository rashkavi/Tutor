import { h, fmt } from '../core/dom.js';
import { AppShell } from '../layout/AppShell.js';
import { DataView } from '../components/DataView.js';
import { Toast } from '../components/Toast.js';

const shell = await AppShell.boot({ area: 'tutor', active: 'students', title: 'Students' });
const { api, gate, page } = shell;

const PAYMENT_BADGE = { paid: 'success', pending: 'warning', overdue: 'danger' };
const atLimit = !gate.access.students.limit || gate.access.students.count < gate.access.students.limit;

page.append(shell.header('Students', 'Everyone enrolled in your classes.', h('div', { style: { display: 'flex', gap: '8px' } },
  h('button', { class: 'ex-btn', onclick: gate.guard('comms.whatsapp', () => Toast.success('WhatsApp composer would open here.')) }, '💬 Message', gate.badge('comms.whatsapp')),
  h('button', { class: 'ex-btn', onclick: gate.guard('attendance.export', () => Toast.success('Export started.')) }, '⬇ Export', gate.badge('attendance.export')),
  h('button', { class: 'ex-btn ex-btn-primary', disabled: !atLimit, title: atLimit ? null : 'Student limit reached: upgrade to add more' }, '+ Add student'))));

new DataView({
  api,
  endpoint: '/tutor/students',
  key: 'students',
  perPage: 10,
  noun: ['student', 'students'],
  searchPlaceholder: 'Search name, student ID or phone',
  filters: [
    { key: 'class', label: 'Class', allLabel: 'All classes', options: async () => (await api.get('/tutor/classes')).data.map((c) => ({ value: c.id, label: c.name })) },
    { key: 'payment', label: 'Payment', allLabel: 'Any payment', options: [{ value: 'paid', label: 'Paid' }, { value: 'pending', label: 'Pending' }, { value: 'overdue', label: 'Overdue' }] },
    { key: 'status', label: 'Status', type: 'chips', options: [{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }] },
  ],
  columns: [
    { key: 'name', label: 'Student', sortable: true, avatar: true, render: (s) => h('div', { class: 'ex-cell-user' }, h('div', { class: 'ex-avatar' }, fmt.initials(s.name)), h('div', {}, h('div', { style: { fontWeight: 600 } }, s.name), h('div', { class: 'ex-small ex-muted' }, s.student_no))) },
    { key: 'phone', label: 'Phone' },
    { key: 'class_name', label: 'Class', sortable: true },
    { key: 'payment', label: 'This month', render: (s) => h('span', { class: `ex-badge ${PAYMENT_BADGE[s.payment]}` }, s.payment) },
    { key: 'status', label: 'Status', render: (s) => h('span', { class: `ex-badge ${s.status === 'ex-active' ? 'ex-primary' : ''}` }, s.status) },
    { key: 'joined', label: 'Joined', sortable: true, render: (s) => fmt.date(s.joined) },
  ],
  empty: { icon: '🎓', title: 'No students yet', text: 'Share your class link or add a student to get started.' },
  onLocked: (e) => gate.openUpgrade(e.details),
}).mount(page);
