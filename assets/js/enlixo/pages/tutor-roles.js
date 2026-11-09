import { h, clear, fill } from '../core/dom.js';
import { AppShell } from '../layout/AppShell.js';
import { LoadingState } from '../components/LoadingState.js';
import { EmptyState } from '../components/EmptyState.js';
import { Modal } from '../components/Modal.js';
import { Toast } from '../components/Toast.js';
import { ApiError, FeatureLockedError } from '../core/ApiClient.js';

/** Settings → Roles: list of roles + permission matrix editor. */
class RolesPage {
  constructor(shell) {
    this.shell = shell;
    this.api = shell.api;
    this.gate = shell.gate;
    this.roles = [];
    this.modules = [];
    this.selectedId = null;
    this.draft = null;
  }

  async mount(page) {
    page.append(this.shell.header('Roles & permissions', 'Create roles for your assistants and choose exactly what each one can do.',
      this.gate.has('staff.custom_roles') ? h('button', { class: 'ex-btn ex-btn-primary', onclick: () => this.#newRole() }, '+ New role') : null));

    if (!this.gate.has('staff.custom_roles')) {
      page.append(this.gate.wrap('staff.custom_roles', this.#previewMatrix(), { text: 'Add assistants, cashiers and markers, and pick what each role can see and do.' }));
      return;
    }

    this.listEl = h('div', { class: 'ex-card' }, LoadingState.list(3));
    this.editorEl = h('div', { class: 'ex-card' }, h('div', { class: 'ex-card-pad', style: { display: 'grid', gap: '12px' } }, LoadingState.line('40%'), LoadingState.line('90%'), LoadingState.line('80%'), LoadingState.line('85%')));
    page.append(h('div', { class: 'ex-roles-layout' }, this.listEl, this.editorEl));
    await this.load();
  }

  async load() {
    try {
      const [roles, modules] = await Promise.all([this.api.get('/tutor/roles'), this.api.get('/permissions')]);
      this.roles = roles.data;
      this.modules = modules.data;
      this.#select(this.selectedId ?? this.roles[1]?.id ?? this.roles[0].id);
    } catch (e) {
      if (e instanceof FeatureLockedError) return this.gate.openUpgrade(e.details);
      fill(this.listEl, EmptyState.error(e.message, () => this.load()));
    }
  }

  #select(id) {
    this.selectedId = id;
    const role = this.roles.find((r) => r.id === id);
    this.draft = role ? { ...role, permissions: new Set(role.permissions) } : null;
    this.#renderList();
    this.#renderEditor();
  }

  #newRole() {
    this.selectedId = null;
    this.draft = { id: null, name: '', description: '', is_system: false, permissions: new Set(['students.view', 'classes.view']), user_count: 0 };
    this.#renderList();
    this.#renderEditor();
    this.editorEl.querySelector('input')?.focus();
  }

  #renderList() {
    fill(this.listEl, 
      h('div', { class: 'ex-card-head' }, h('h3', {}, 'Roles'), h('span', { class: 'ex-badge' }, String(this.roles.length))),
      this.roles.map((r) => h('div', { class: `ex-role-item ${r.id === this.selectedId ? 'ex-active' : ''}`, role: 'button', tabindex: '0', onclick: () => this.#select(r.id), onkeydown: (e) => { if (e.key === 'Enter') this.#select(r.id); } },
        h('div', { class: 'ex-avatar', style: { borderRadius: '10px' } }, r.is_system ? '👑' : r.name[0].toUpperCase()),
        h('div', { class: 'ex-meta' }, h('div', { style: { fontWeight: 600 } }, r.name), h('div', { class: 'ex-small ex-muted' }, `${r.permissions.length} permissions · ${r.user_count} ${r.user_count === 1 ? 'user' : 'users'}`)),
        r.is_system ? h('span', { class: 'ex-badge' }, 'Built-in') : null)));
  }

  #renderEditor() {
    const d = this.draft;
    if (!d) return fill(this.editorEl, EmptyState.empty({ icon: '🛡️', title: 'Pick a role' }));
    const readOnly = d.is_system;
    this.errors = {};

    const nameField = h('div', { class: 'ex-field' }, h('label', { for: 'role-name' }, 'Role name'),
      h('input', { class: 'ex-input', id: 'role-name', value: d.name, maxlength: '60', disabled: readOnly, placeholder: 'e.g. Class Assistant', oninput: (e) => { d.name = e.target.value; } }));
    const descField = h('div', { class: 'ex-field' }, h('label', { for: 'role-desc' }, 'Description (optional)'),
      h('input', { class: 'ex-input', id: 'role-desc', value: d.description ?? '', maxlength: '200', disabled: readOnly, placeholder: 'What does this person do?', oninput: (e) => { d.description = e.target.value; } }));
    this.permError = h('div', { class: 'ex-field ex-error', role: 'alert' });

    const saveBtn = h('button', { class: 'ex-btn ex-btn-primary', onclick: () => this.#save(saveBtn, nameField) }, d.id ? 'Save changes' : 'Create role');

    fill(this.editorEl, 
      h('div', { class: 'ex-card-head' }, h('h3', { style: { flex: 1 } }, d.id === null ? 'New role' : d.name),
        !readOnly && d.id ? h('button', { class: 'ex-btn ex-btn-sm ex-btn-danger', onclick: () => this.#confirmDelete(d) }, 'Delete') : null),
      h('div', { class: 'ex-card-pad' },
        readOnly ? h('div', { class: 'ex-badge ex-primary', style: { marginBottom: '14px' } }, 'The Owner role always has everything your package includes. It can\'t be edited.') : null,
        h('div', { class: 'ex-grid ex-grid-2' }, nameField, descField),
        h('div', { style: { display: 'flex', alignItems: 'center', margin: '4px 0 10px' } }, h('h3', { style: { flex: 1 } }, 'What can this role do?'), h('span', { class: 'ex-small ex-muted' }, `${d.permissions.size} selected`)),
        this.permError,
        this.modules.map((m) => this.#module(m, readOnly))),
      readOnly ? null : h('div', { class: 'ex-modal-foot' }, h('button', { class: 'ex-btn', onclick: () => this.#select(this.selectedId ?? this.roles[0].id) }, 'Cancel'), saveBtn));
  }

  #module(m, readOnly) {
    const d = this.draft;
    const selectable = m.permissions.filter((p) => !p.locked);
    const on = selectable.filter((p) => d.permissions.has(p.key)).length;
    const all = h('input', { type: 'checkbox', class: 'ex-check', checked: on > 0 && on === selectable.length, disabled: readOnly || !selectable.length, 'aria-label': `Select all in ${m.module}`,
      onchange: (e) => { selectable.forEach((p) => (e.target.checked ? d.permissions.add(p.key) : d.permissions.delete(p.key))); this.#renderEditorKeepFocus(); } });
    if (on > 0 && on < selectable.length) all.indeterminate = true;

    return h('div', { class: 'ex-perm-module' },
      h('label', { class: 'ex-perm-module-head' }, all, m.module, h('span', { class: 'ex-count' }, `${on}/${m.permissions.length}`)),
      m.permissions.map((p) => p.locked
        ? h('div', { class: 'ex-perm-row ex-locked', role: 'button', tabindex: '0', onclick: () => this.gate.openUpgrade(p.feature), title: 'Not in your package' },
          h('input', { type: 'checkbox', class: 'ex-check', disabled: true, 'aria-label': p.name }), h('span', { class: 'ex-perm-name', style: { flex: 1 } }, p.name), this.gate.badge(p.feature))
        : h('label', { class: 'ex-perm-row' },
          h('input', { type: 'checkbox', class: 'ex-check', checked: d.permissions.has(p.key), disabled: readOnly, onchange: (e) => { e.target.checked ? d.permissions.add(p.key) : d.permissions.delete(p.key); this.#renderEditorKeepFocus(); } }),
          h('span', { style: { flex: 1 } }, p.name))));
  }

  #renderEditorKeepFocus() {
    const scroll = window.scrollY;
    this.#renderEditor();
    window.scrollTo(0, scroll);
  }

  async #save(btn) {
    const d = this.draft;
    const body = { name: d.name, description: d.description, permissions: [...d.permissions] };
    try {
      const { data } = await LoadingState.button(btn, d.id ? this.api.put(`/tutor/roles/${d.id}`, body) : this.api.post('/tutor/roles', body));
      Toast.success(d.id ? 'Role saved' : `Role "${data.name}" created`);
      this.selectedId = data.id;
      await this.load();
    } catch (e) {
      if (e instanceof FeatureLockedError) return this.gate.openUpgrade(e.details);
      if (e instanceof ApiError && e.code === 'validation_failed') return this.#showErrors(e.details.fields ?? {});
      Toast.error(e.message);
    }
  }

  #showErrors(fields) {
    this.editorEl.querySelectorAll('.ex-field').forEach((f) => { f.classList.remove('ex-has-error'); f.querySelector('.ex-error:not([role])')?.remove(); });
    if (fields.name) {
      const f = this.editorEl.querySelector('#role-name').closest('.ex-field');
      f.classList.add('ex-has-error');
      f.append(h('div', { class: 'ex-error' }, fields.name));
    }
    this.permError.textContent = fields.permissions ?? '';
  }

  #confirmDelete(role) {
    const others = this.roles.filter((r) => !r.is_system && r.id !== role.id);
    let moveTo = others[0]?.id ?? null;
    const body = role.user_count
      ? h('div', {}, h('p', {}, `${role.user_count} staff ${role.user_count === 1 ? 'member uses' : 'members use'} this role. Move them to:`),
        h('select', { class: 'ex-select', style: { width: '100%' }, onchange: (e) => { moveTo = Number(e.target.value); } }, others.map((r) => h('option', { value: r.id }, r.name))))
      : h('p', {}, `Delete "${role.name}"? This can't be undone.`);
    const btn = h('button', { class: 'ex-btn ex-btn-primary', style: { background: 'var(--danger)', borderColor: 'var(--danger)' }, onclick: async () => {
      try {
        await LoadingState.button(btn, this.api.delete(`/tutor/roles/${role.id}`, { move_to_role_id: moveTo }), 'Deleting…');
        modal.close();
        Toast.success('Role deleted');
        this.selectedId = null;
        await this.load();
      } catch (e) { Toast.error(e.message); }
    } }, 'Delete role');
    const modal = new Modal({ title: 'Delete role', body, footer: [h('button', { class: 'ex-btn', onclick: () => modal.close() }, 'Cancel'), btn] }).open();
  }

  /** Static preview shown blurred behind the lock on START. */
  #previewMatrix() {
    const rows = ['View students', 'Mark attendance', 'Approve payments', 'Upload recordings', 'Send messages'];
    return h('div', { class: 'ex-roles-layout' },
      h('div', { class: 'ex-card' }, ['Owner', 'Class Assistant', 'Cashier', 'Paper Marker'].map((n) => h('div', { class: 'ex-role-item' }, h('div', { class: 'ex-avatar' }, n[0]), h('div', { class: 'ex-meta' }, n)))),
      h('div', { class: 'ex-card ex-card-pad' }, h('div', { class: 'ex-perm-module' }, h('div', { class: 'ex-perm-module-head' }, 'Permissions'), rows.map((r, i) => h('div', { class: 'ex-perm-row' }, h('input', { type: 'checkbox', class: 'ex-check', checked: i % 2 === 0 }), r)))));
  }
}

const shell = await AppShell.boot({ area: 'tutor', active: 'roles', title: 'Settings' });
await new RolesPage(shell).mount(shell.page);
