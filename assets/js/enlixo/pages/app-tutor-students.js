// Tutor → Students (tutor/students.php). Same look as the rest of the tutor app, built from the shared DataView.
import { h, fmt } from '../core/dom.js';
import { ApiClient } from '../core/ApiClient.js';
import { DataView } from '../components/DataView.js';
import { FeatureGate } from '../components/FeatureGate.js';

const api = new ApiClient();
const gate = await FeatureGate.load(api).catch(() => null);

const assets = new URL('../../../', import.meta.url).href; // <root>/assets/

function avatar(s) {
  if (s.profile_img) {
    const img = h('img', { src: `${assets}imgs/students/${encodeURIComponent(s.profile_img)}`, class: 'hwr-40', alt: '' });
    img.onerror = () => img.replaceWith(initials(s));
    return img;
  }
  return initials(s);
}

const initials = (s) => h('div', { class: 'd-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium' }, fmt.initials(`${s.first_name} ${s.last_name}`));

const icon = (name) => h('iconify-icon', { icon: name, width: '16', height: '16' });

// Header buttons: locked ones stay visible with the premium badge
const actions = document.getElementById('students-actions');
if (gate) {
  actions.append(
    h('button', { class: 'ex-btn', onclick: gate.guard('comms.whatsapp', () => { location.href = 'message-center'; }) }, icon('logos:whatsapp-icon'), 'Message', gate.badge('comms.whatsapp')),
    h('a', { class: 'ex-btn ex-btn-primary', href: 'add-student-to-class' }, icon('mdi:plus'), 'Add student'),
  );
}

new DataView({
  api,
  endpoint: '/tutor/students',
  key: 'students',
  perPage: 10,
  perPageOptions: [10, 25, 50, 100],
  className: 'ex-flat',
  noun: ['student', 'students'],
  searchPlaceholder: 'Search name, ID, email or phone',
  filters: [
    { key: 'class', label: 'Class', allLabel: 'All classes', options: async () => (await api.get('/tutor/classes')).data.map((c) => ({ value: c.id, label: c.name })) },
    { key: 'status', label: 'Status', type: 'chips', options: [{ value: '1', label: 'Active' }, { value: '2', label: 'Inactive' }] },
  ],
  columns: [
    { key: 'name', label: 'Student Name', sortable: true, avatar: true, render: (s) => h('div', { class: 'd-flex align-items-center gap-2' }, avatar(s), h('div', {}, h('div', { class: 'text-14 main-text fw-medium' }, `${s.first_name} ${s.last_name}`), h('div', { class: 'text-12 sub-text' }, `#${s.id}`))) },
    { key: 'whatsapp', label: 'WhatsApp Number', render: (s) => h('span', { class: 'text-14 main-text' }, s.whatsapp ?? '') },
    { key: 'classes', label: 'Classes', render: (s) => h('span', { class: 'text-14 main-text' }, s.classes ?? '') },
    { key: 'gender', label: 'Gender', render: (s) => h('span', { class: `badge ${s.gender === 'Male' ? 'table-btn-primary text-primary' : 'table-btn-pink text-dark-pink'} br-0 py-2 px-3 d-inline-flex align-items-center gap-1 fw-normal` }, h('iconify-icon', { icon: s.gender === 'Male' ? 'mdi:gender-male' : 'mdi:gender-female', width: '14' }), s.gender) },
    { key: 'date', label: 'Admission Date', sortable: true, render: (s) => h('span', { class: 'text-14 main-text' }, fmt.date(s.date_registered)) },
    { key: 'status', label: 'Status', render: (s) => h('span', { class: `table-btn ${s.status === 'Active' ? 'table-btn-success' : 'table-btn-danger'} d-inline-block px-3` }, s.status) },
    { key: 'actions', label: '', width: '48px', render: (s) => h('div', { class: 'dropdown' },
      h('iconify-icon', { icon: 'charm:menu-kebab', width: '20', height: '20', class: 'sub-text cursor-pointer', 'data-bs-toggle': 'dropdown', 'aria-expanded': 'false', role: 'button', tabindex: '0', 'aria-label': 'Actions' }),
      h('ul', { class: 'dropdown-menu border-0 shadow br-5' },
        h('li', {}, h('a', { class: 'dropdown-item text-14 main-text d-flex align-items-center gap-2', href: `edit-student?student-id=${encodeURIComponent(s.id)}` }, h('iconify-icon', { icon: 'ic:sharp-edit', width: '18' }), 'Edit')))) },
  ],
  empty: { icon: '🎓', title: 'No students yet', text: 'Add a student to one of your classes to see them here.', action: h('a', { class: 'ex-btn ex-btn-primary', href: 'add-student-to-class' }, 'Add student') },
  onLocked: (e) => gate?.openUpgrade(e.details),
}).mount(document.getElementById('students-list'));
