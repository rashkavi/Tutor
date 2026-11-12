// Tutor → Students (tutor/students.php), laid out like the ENLIXO V3 Students page, built from the shared DataView.
import { h, fmt } from '../core/dom.js';
import { ApiClient } from '../core/ApiClient.js';
import { DataView } from '../components/DataView.js';
import { ActionMenu } from '../components/Popover.js';
import { FeatureGate } from '../components/FeatureGate.js';
import { Toast } from '../components/Toast.js';

const api = new ApiClient();
const gate = await FeatureGate.load(api).catch(() => null);
const assets = new URL('../../../', import.meta.url).href; // <root>/assets/

const fullName = (s) => `${s.first_name ?? ''} ${s.last_name ?? ''}`.trim();
const initials = (s) => h('span', { class: 'ex-avatar' }, fmt.initials(fullName(s) || '?'));
function avatar(s) {
  if (!s.profile_img) return initials(s);
  const img = h('img', { class: 'ex-avatar', alt: '', src: `${assets}imgs/students/${encodeURIComponent(s.profile_img)}` });
  img.onerror = () => img.replaceWith(initials(s));
  return img;
}
// 2026-11-09 09:49 AM, like V3
const stamp = (d) => {
  if (!d) return '';
  const t = new Date(d.replace(' ', 'T'));
  return `${t.getFullYear()}-${String(t.getMonth() + 1).padStart(2, '0')}-${String(t.getDate()).padStart(2, '0')} ${String(t.getHours() % 12 || 12).padStart(2, '0')}:${String(t.getMinutes()).padStart(2, '0')} ${t.getHours() < 12 ? 'AM' : 'PM'}`;
};

let view;
const setStatus = async (ids, status) => {
  const { data } = await api.post('/tutor/students/status', { ids, status: Number(status), class: view.state.filters.class ?? null });
  Toast.success(`${ids.length} ${ids.length === 1 ? 'student' : 'students'} marked ${status === '1' ? 'Active' : 'Inactive'}.`);
  return data;
};

// Header "Action" menu: locked items stay visible with the premium badge
document.getElementById('students-actions').append(ActionMenu.button('Action', [
  { label: 'Add New Student', icon: 'user-add', href: 'add-student' },
  { label: 'Requested Students', icon: 'application', href: 'requested-students' },
  { label: 'Add Registered Student', icon: 'user', href: 'add-registered-student-to-class' },
  { label: 'Upload Students Bulk', icon: 'upload', href: 'upload-student-bulk' },
  gate && { label: 'Send WhatsApp Message', icon: 'communication', locked: gate.badge('comms.whatsapp'), onClick: gate.guard('comms.whatsapp', () => { location.href = 'message-center'; }) },
]));

view = new DataView({
  api,
  endpoint: '/tutor/students',
  key: 'students',
  perPage: 10,
  perPageOptions: [10, 25, 50, 100],
  noun: ['student', 'students'],
  searchPlaceholder: 'Search by name, e-mail, mobile or WhatsApp',
  filters: [
    { key: 'class', label: 'Class', allLabel: 'All classes', options: async () => (await api.get('/tutor/classes')).data.map((c) => ({ value: c.id, label: c.name })) },
    { key: 'status', label: 'Status', type: 'chips', options: [{ value: '1', label: 'Active' }, { value: '2', label: 'Inactive' }] },
  ],
  bulk: {
    actions: [{ value: '1', label: 'Mark as Active' }, { value: '2', label: 'Mark as Inactive' }],
    onApply: (ids, action) => setStatus(ids, action),
  },
  exportCsv: {
    filename: `students-${new Date().toISOString().slice(0, 10)}.csv`,
    columns: [['Student ID', (s) => s.id], ['Student Name', fullName], ['Email Address', (s) => s.email], ['Mobile', (s) => s.mobile], ['WhatsApp Number', (s) => s.whatsapp], ['Classes', (s) => s.classes], ['Gender', (s) => s.gender], ['Admission Date', (s) => stamp(s.date_registered)], ['Status', (s) => s.status]],
  },
  columns: [
    { key: 'name', label: 'Student Name', sortable: true, render: (s) => h('div', { class: 'ex-person' }, avatar(s), h('span', {}, fullName(s))) },
    { key: 'email', label: 'Email Address', render: (s) => s.email ?? '' },
    { key: 'mobile', label: 'Mobile', render: (s) => s.mobile ?? '' },
    { key: 'whatsapp', label: 'WhatsApp Number', render: (s) => s.whatsapp ?? '' },
    { key: 'classes', label: 'Classes', wrap: true, render: (s) => s.classes ?? '' },
    { key: 'date', label: 'Admission Date', sortable: true, render: (s) => stamp(s.date_registered) },
    { key: 'status', label: 'Status', render: (s) => h('span', { class: `ex-status ${s.status === 'Active' ? '' : 'ex-off'}` }, s.status) },
    { key: 'actions', label: '', width: '48px', render: (s) => ActionMenu.kebab([
      { label: 'Edit Student', icon: 'edit', href: `edit-student?student-id=${encodeURIComponent(s.id)}` },
      s.status === 'Active'
        ? { label: 'Mark as Inactive', icon: 'deactivate', danger: true, onClick: () => setStatus([s.id], '2').then(() => view.reload()).catch((e) => Toast.error(e.message)) }
        : { label: 'Mark as Active', icon: 'activate', onClick: () => setStatus([s.id], '1').then(() => view.reload()).catch((e) => Toast.error(e.message)) },
    ], `Actions for ${fullName(s)}`) },
  ],
  empty: { icon: '🎓', title: 'No students yet', text: 'Add a student to one of your classes to see them here.', action: h('a', { class: 'custom-btn ex-btn-md px-4 text-decoration-none', href: 'add-student' }, 'Add New Student') },
  onLocked: (e) => gate?.openUpgrade(e.details),
}).mount(document.getElementById('students-list'));
