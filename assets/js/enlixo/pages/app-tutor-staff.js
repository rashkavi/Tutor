// Tutor → Settings → Staff & Roles (tutor/staff.php, GROW feature staff.custom_roles).
// Staff tab: invite an assistant (name, e-mail, mobile, role) -> a set-password e-mail (the link also shows here for
// WhatsApp), change their role, switch them off / on, send a new link. Roles tab: the tutor's roles and what each may do.
// API: GET/POST /tutor/staff, PUT /tutor/staff/{id}, POST /tutor/staff/{id}/resend, GET /permissions, /tutor/roles (CRUD).
import { QuickGuide } from '../components/QuickGuide.js';
import { StatCard } from '../components/StatCard.js';
import { api, h, fill, icon, day, chip, field, showErrors, simpleTable, person, Toast } from './admin-common.js';

QuickGuide.attachAll();
const root = document.getElementById('st-page');
const actions = document.getElementById('st-actions');
let tab = new URLSearchParams(location.search).get('tab') === 'roles' ? 'roles' : 'staff';
let S = null; // {staff, roles}
let R = null; // roles with permissions
let M = null; // permission modules
let inviting = false;
let lastLink = null; // {name, link} after an invite / resend
let draft = null; // role being edited

fill(root, h('div', { class: 'component-theme p-5 text-center sub-text' }, 'Loading…'));
load();

async function load() {
  try {
    const [s, r, m] = await Promise.all([api.get('/tutor/staff'), api.get('/tutor/roles'), api.get('/permissions')]);
    S = s.data; R = r.data; M = m.data;
  } catch (e) {
    if (e.code === 'feature_locked' || e.status === 402) return locked();
    fill(root, h('div', { class: 'component-theme p-4 text-dark-red' }, e.message));
    return;
  }
  draw();
}

function locked() {
  fill(actions);
  fill(root, h('div', { class: 'component-theme p-5 text-center' },
    h('div', { class: 'empty-state' },
      h('div', { class: 'empty-state-icon' }, icon('lock', { size: 28 })),
      h('div', { class: 'empty-state-title' }, 'Staff accounts are part of GROW'),
      h('div', { class: 'empty-state-text' }, 'Add a class assistant, a cashier or a paper marker, each with their own sign-in, and choose exactly what each one can see and do.'),
      h('a', { class: 'custom-btn px-4 mt-3 text-decoration-none d-inline-flex align-items-center', href: 'upgrade?feature=staff.custom_roles' }, 'See GROW'))));
}

function draw() {
  const active = S.staff.filter((s) => s.active);
  fill(actions,
    tab === 'staff'
      ? h('button', { type: 'button', class: 'custom-btn px-4 d-inline-flex align-items-center gap-2', onclick: () => { inviting = true; draw(); root.querySelector('[data-key="first_name"]')?.focus(); } }, icon('add', { size: 18 }), 'Invite Staff')
      : h('button', { type: 'button', class: 'custom-btn px-4 d-inline-flex align-items-center gap-2', onclick: () => newRole() }, icon('add', { size: 18 }), 'New Role'));
  const t = (key, label, n) => h('button', { type: 'button', class: `nav-link${tab === key ? ' active' : ''}`, onclick: () => { tab = key; history.replaceState(null, '', key === 'roles' ? '?tab=roles' : location.pathname); draw(); } }, label, ' ', h('span', { class: 'tab-count' }, String(n)));
  fill(root,
    StatCard.row([
      { label: 'Staff', value: S.staff.length, icon: 'users', caption: 'invited so far' },
      { label: 'Active', value: active.length, icon: 'check', caption: 'can sign in' },
      { label: 'Waiting to join', value: active.filter((s) => !s.joined).length, icon: 'hourglass', caption: 'no password yet' },
      { label: 'Roles', value: R.length - 1, icon: 'shield', caption: 'besides Owner' },
    ], { className: '' }),
    h('div', { class: 'component-theme p-4 mt-4' },
      h('ul', { class: 'nav nav-tabs mb-4' }, h('li', { class: 'nav-item' }, t('staff', 'Staff', S.staff.length)), h('li', { class: 'nav-item' }, t('roles', 'Roles', R.length - 1))),
      tab === 'staff' ? staffTab() : rolesTab()));
}

// ---------------------------------------------------------------- staff
function staffTab() {
  const roles = S.roles;
  return h('div', {},
    lastLink ? h('div', { class: 'st-link mb-4' },
      h('div', { class: 'flex-grow-1' }, h('div', { class: 'text-14 main-text fw-medium' }, `Set-password link for ${lastLink.name}`),
        h('div', { class: 'text-12 sub-text' }, 'Works for 7 days. Copy it and send it on WhatsApp if the e-mail does not arrive.'),
        h('code', { class: 'st-url' }, lastLink.link)),
      h('button', { type: 'button', class: 'custom-btn-outline px-3', onclick: () => { navigator.clipboard?.writeText(lastLink.link); Toast.success('Link copied.'); } }, 'Copy'),
      h('button', { type: 'button', class: 'bg-transparent border-0 sub-text', 'aria-label': 'Close', onclick: () => { lastLink = null; draw(); } }, icon('close', { size: 18 }))) : null,
    inviting ? inviteForm(roles) : null,
    simpleTable([
      { label: 'Staff Member', render: (s) => person(s.name, s.email) },
      { label: 'Mobile', render: (s) => s.mobile || h('span', { class: 'sub-text' }, 'Not given') },
      { label: 'Role', render: (s) => h('select', { class: 'form-select custom-select st-role', 'aria-label': `Role of ${s.name}`, disabled: !s.active ? true : null, onchange: (e) => change(s, { role_id: Number(e.target.value) }) },
        roles.map((r) => h('option', { value: r.id, selected: r.id === s.role_id ? true : null }, r.name))) },
      { label: 'Status', render: (s) => !s.active ? chip('cancelled', 'Switched off') : s.joined ? chip('active', 'Active') : chip('due', 'Invited') },
      { label: 'Last Active', render: (s) => h('span', { class: 'text-14 main-text' }, s.last_active ? day(s.last_active.slice(0, 10)) : 'Never') },
      { label: '', render: (s) => h('div', { class: 'd-flex gap-2 justify-content-end' },
        s.active && !s.joined ? h('button', { type: 'button', class: 'custom-btn table-btn', onclick: (e) => resend(s, e.target) }, 'Send Link Again') : null,
        h('button', { type: 'button', class: s.active ? 'custom-btn-outline table-btn' : 'custom-btn table-btn', onclick: () => change(s, { active: !s.active }) }, s.active ? 'Switch Off' : 'Switch On')) },
    ], S.staff, { icon: 'inbox', title: 'No staff yet', text: roles.length ? 'Invite a class assistant, a cashier or a marker with Invite Staff.' : 'Make a role on the Roles tab first, then invite your staff.' }));
}

function inviteForm(roles) {
  const input = (key, ph, extra = {}) => h('input', { class: 'form-control custom-input', 'data-key': key, placeholder: ph, ...extra });
  const f = {
    first_name: input('first_name', 'Enter the first name', { 'data-limit': '45' }),
    last_name: input('last_name', 'Enter the last name', { 'data-limit': '45' }),
    email: input('email', 'Enter their e-mail address', { type: 'email', 'data-limit': 'email' }),
    mobile: input('mobile', 'Enter their mobile number', { type: 'tel', 'data-limit': '15' }),
    role_id: h('select', { class: 'form-select custom-select', 'data-key': 'role_id' }, h('option', { value: '' }, '- Select -'), roles.map((r) => h('option', { value: r.id }, r.name))),
  };
  const box = h('div', { class: 'st-invite mb-4' },
    h('div', { class: 'sub-topic mb-3' }, 'Invite a staff member'),
    h('div', { class: 'row gx-3' },
      h('div', { class: 'col-md-6' }, field('First Name', f.first_name, { required: true })),
      h('div', { class: 'col-md-6' }, field('Last Name', f.last_name, { required: true })),
      h('div', { class: 'col-md-6' }, field('E-mail', f.email, { required: true, info: 'They get a "Set my password" e-mail here and sign in on the tutor sign-in page with this address. It must not already have an ENLIXO account. For example nimal@gmail.com.' })),
      h('div', { class: 'col-md-6' }, field('Mobile', f.mobile, { info: 'Their Sri Lankan mobile number, so you can send the link on WhatsApp. For example 0771234567.' })),
      h('div', { class: 'col-md-6' }, field('Role', f.role_id, { required: true, info: 'What this person may see and do in your account. Make or change roles on the Roles tab. They never see your subscription or billing.' }))),
    roles.length ? null : h('div', { class: 'text-13 text-dark-red mb-3' }, 'You have no roles yet. Open the Roles tab and make one first.'),
    h('div', { class: 'd-flex gap-2 justify-content-end' },
      h('button', { type: 'button', class: 'custom-btn-outline px-4', onclick: () => { inviting = false; draw(); } }, 'Cancel'),
      h('button', { type: 'button', class: 'custom-btn px-4', onclick: (e) => invite(f, box, e.target) }, 'Send Invitation')));
  return box;
}

async function invite(f, box, btn) {
  showErrors(box, {});
  const body = Object.fromEntries(Object.entries(f).map(([k, el]) => [k, el.value.trim()]));
  btn.disabled = true;
  try {
    const { data } = await api.post('/tutor/staff', body);
    Toast[data.invite.sent ? 'success' : 'warning'](data.message);
    lastLink = { name: `${body.first_name} ${body.last_name}`, link: data.invite.link };
    inviting = false;
    await load();
  } catch (e) {
    btn.disabled = false;
    showErrors(box, e.details?.fields ?? {});
    Toast.error(e.message);
  }
}

async function change(s, body) {
  try {
    const { data } = await api.put(`/tutor/staff/${s.id}`, body);
    Toast.success(data.message);
    await load();
  } catch (e) { Toast.error(e.details?.fields ? Object.values(e.details.fields)[0] : e.message); draw(); }
}

async function resend(s, btn) {
  btn.disabled = true;
  try {
    const { data } = await api.post(`/tutor/staff/${s.id}/resend`, {});
    Toast[data.invite.sent ? 'success' : 'warning'](data.message);
    lastLink = { name: s.name, link: data.invite.link };
    draw();
  } catch (e) { btn.disabled = false; Toast.error(e.message); }
}

// ---------------------------------------------------------------- roles
function newRole() {
  tab = 'roles';
  draft = { id: null, name: '', description: '', permissions: new Set(['students.view', 'classes.view']) };
  draw();
  root.querySelector('[data-key="name"]')?.focus();
}

function rolesTab() {
  const list = h('div', { class: 'st-roles' }, R.map((r) => h('button', { type: 'button', class: `st-role-item${draft && draft.id === r.id ? ' is-on' : ''}`, onclick: () => { draft = { ...r, permissions: new Set(r.permissions) }; draw(); } },
    h('span', { class: 'st-role-name' }, r.name, r.is_system ? h('span', { class: 'tab-count ms-2' }, 'Built-in') : null),
    h('span', { class: 'text-12 sub-text' }, `${r.permissions.length} permissions · ${r.user_count} ${r.user_count === 1 ? 'person' : 'people'}`))));
  return h('div', { class: 'row g-4' },
    h('div', { class: 'col-12 col-lg-4' }, list),
    h('div', { class: 'col-12 col-lg-8' }, draft ? editor() : h('div', { class: 'empty-state' },
      h('div', { class: 'empty-state-icon' }, icon('inbox', { size: 28 })),
      h('div', { class: 'empty-state-title' }, R.length > 1 ? 'Pick a role' : 'No roles yet'),
      h('div', { class: 'empty-state-text' }, R.length > 1 ? 'Choose a role on the left to see or change what it can do.' : 'Make a role, for example "Class Assistant" or "Cashier", with New Role.'))));
}

function editor() {
  const d = draft;
  const ro = !!d.is_system;
  const name = h('input', { class: 'form-control custom-input', 'data-key': 'name', 'data-limit': '60', value: d.name, disabled: ro ? true : null, placeholder: 'Enter the role name, for example Class Assistant', oninput: (e) => { d.name = e.target.value; } });
  const desc = h('input', { class: 'form-control custom-input', 'data-key': 'description', 'data-limit': '200', value: d.description ?? '', disabled: ro ? true : null, placeholder: 'Enter what this person does', oninput: (e) => { d.description = e.target.value; } });
  const permErr = h('div', { class: 'text-12 text-dark-red mb-2', 'data-perm-err': '' });
  const box = h('div', {},
    ro ? h('div', { class: 'st-note mb-3' }, 'Owner is you: everything your plan includes. It cannot be changed.') : null,
    h('div', { class: 'row gx-3' },
      h('div', { class: 'col-md-6' }, field('Role Name', name, { required: true, info: 'A short name your staff will recognise, for example Class Assistant, Cashier or Paper Marker. Up to 60 characters.' })),
      h('div', { class: 'col-md-6' }, field('Description', desc, { info: 'One line about what people with this role do. Up to 200 characters.' }))),
    h('div', { class: 'd-flex align-items-center mb-2' }, h('div', { class: 'sub-topic flex-grow-1' }, 'What can this role do?'), h('span', { class: 'text-12 sub-text' }, `${d.permissions.size} selected`)),
    permErr,
    M.map((m) => h('div', { class: 'st-perm-group' },
      h('div', { class: 'text-14 main-text fw-medium mb-2' }, m.module),
      m.permissions.map((p) => h('label', { class: `st-perm${p.locked ? ' is-locked' : ''}` },
        h('input', { type: 'checkbox', class: 'form-check-input', checked: d.permissions.has(p.key) ? true : null, disabled: ro || p.locked ? true : null,
          onchange: (e) => { e.target.checked ? d.permissions.add(p.key) : d.permissions.delete(p.key); } }),
        h('span', { class: 'text-14 main-text' }, p.name),
        p.locked ? h('span', { class: 'tab-count ms-auto' }, 'Not in your plan') : null)))),
    ro ? null : h('div', { class: 'd-flex gap-2 justify-content-end mt-3' },
      d.id && !d.deleting ? h('button', { type: 'button', class: 'custom-danger-btn px-4', onclick: () => { d.deleting = true; draw(); } }, 'Delete') : null,
      h('button', { type: 'button', class: 'custom-btn-outline px-4', onclick: () => { draft = null; draw(); } }, 'Cancel'),
      h('button', { type: 'button', class: 'custom-btn px-4', onclick: (e) => saveRole(box, permErr, e.target) }, d.id ? 'Save Changes' : 'Create Role')),
    d.deleting ? deletePanel(d) : null);
  return box;
}

async function saveRole(box, permErr, btn) {
  const d = draft;
  showErrors(box, {});
  permErr.textContent = '';
  btn.disabled = true;
  try {
    const body = { name: d.name.trim(), description: (d.description ?? '').trim(), permissions: [...d.permissions] };
    const { data } = d.id ? await api.put(`/tutor/roles/${d.id}`, body) : await api.post('/tutor/roles', body);
    Toast.success(d.id ? 'Role saved.' : `Role "${data.name}" created.`);
    await load();
    const r = R.find((x) => x.id === data.id);
    draft = r ? { ...r, permissions: new Set(r.permissions) } : null;
    draw();
  } catch (e) {
    btn.disabled = false;
    const f = e.details?.fields ?? {};
    showErrors(box, f);
    if (f.permissions) permErr.textContent = f.permissions;
    Toast.error(e.message);
  }
}

function deletePanel(d) {
  const others = R.filter((r) => !r.is_system && r.id !== d.id);
  const move = d.user_count > 0 && others.length
    ? h('select', { class: 'form-select custom-select mt-2', 'aria-label': 'Move the staff to' }, others.map((r) => h('option', { value: r.id }, r.name))) : null;
  return h('div', { class: 'st-delete mt-3' },
    h('div', { class: 'text-14 main-text fw-medium' }, `Delete the role "${d.name}"?`),
    d.user_count > 0
      ? (others.length ? h('div', { class: 'text-13 sub-text' }, `${d.user_count} staff use it. Choose the role they move to:`) : h('div', { class: 'text-13 text-dark-red' }, `${d.user_count} staff use it. Make another role first, then delete this one.`))
      : h('div', { class: 'text-13 sub-text' }, 'Nobody uses it. This cannot be undone.'),
    move,
    h('div', { class: 'd-flex gap-2 justify-content-end mt-3' },
      h('button', { type: 'button', class: 'custom-btn-outline px-4', onclick: () => { d.deleting = false; draw(); } }, 'Keep It'),
      d.user_count > 0 && !others.length ? null : h('button', { type: 'button', class: 'custom-danger-btn px-4', onclick: async (e) => {
        e.target.disabled = true;
        try {
          await api.delete(`/tutor/roles/${d.id}`, { move_to_role_id: move ? Number(move.value) : null });
          Toast.success('Role deleted.');
          draft = null;
          await load();
        } catch (err) { e.target.disabled = false; Toast.error(err.message); }
      } }, 'Delete Role')));
}
