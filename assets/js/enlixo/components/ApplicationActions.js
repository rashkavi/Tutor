import { h } from '../core/dom.js';
import { BsModal } from './BsModal.js';
import { Toast } from './Toast.js';

/**
 * Moving an application (Requested Students list + application page), so both ask the same questions:
 *   In Review   straight away
 *   On Hold     asks what you are waiting for
 *   Reject      asks for the reason
 *   Approve     asks which class (the one they chose is picked) and registers the student
 *   Reopen      a rejected one goes back to Pending
 *
 *   const done = await ApplicationActions.move(api, app, 'approved', { classes });   // null when cancelled
 */
export const STATUS = {
  pending: { label: 'Pending', icon: 'clock', caption: 'Waiting for a first look' },
  in_review: { label: 'In Review', icon: 'view', caption: 'Being checked' },
  on_hold: { label: 'On Hold', icon: 'deactivate', caption: 'Waiting for the student' },
  approved: { label: 'Approved', icon: 'check', caption: 'Registered as students' },
  rejected: { label: 'Rejected', icon: 'close', caption: 'Can be reopened' },
};

export const MOVE_LABEL = { in_review: 'Mark In Review', on_hold: 'Put On Hold', approved: 'Approve', rejected: 'Reject', pending: 'Reopen' };
export const MOVE_ICON = { in_review: 'view', on_hold: 'deactivate', approved: 'user-check', rejected: 'close', pending: 'refresh' };
export const MOVES = {
  pending: ['in_review', 'on_hold', 'approved', 'rejected'],
  in_review: ['on_hold', 'approved', 'rejected'],
  on_hold: ['in_review', 'approved', 'rejected'],
  rejected: ['pending'],
  approved: [],
};

export const statusPill = (s) => h('span', { class: `ex-pill ex-pill-${s}` }, STATUS[s]?.label ?? s);
export const fullName = (a) => `${a.first_name ?? ''} ${a.last_name ?? ''}`.trim();

function field(id, label, el, { required = false, help = '' } = {}) {
  return h('div', { class: 'col-12 d-flex flex-column' },
    h('label', { class: `text-14 main-text ${required ? 'required' : ''}`, for: id }, label), el,
    help ? h('span', { class: 'text-12 sub-text mt-1' }, help) : null,
    h('span', { class: 'text-12 text-dark-red', role: 'alert' }));
}

export class ApplicationActions {
  /** Menu items for an ActionMenu, from the application's current status. */
  static items(app, onMove) {
    const moves = MOVES[app.status] ?? [];
    return moves.map((to) => ({ label: MOVE_LABEL[to], icon: MOVE_ICON[to], danger: to === 'rejected', onClick: () => onMove(to) }));
  }

  static async move(api, app, to, { classes = [] } = {}) {
    const name = fullName(app);
    const send = async (body) => {
      const { data } = await api.post(`/tutor/applications/${app.id ?? app.application_id}/status`, { status: to, ...body });
      return data;
    };
    if (to === 'in_review') {
      try { const r = await send({}); Toast.success(`${name} is In Review.`); return r; } catch (e) { Toast.error(e.message); return null; }
    }
    if (to === 'pending') {
      const ok = await BsModal.confirm({ title: 'Reopen this application?', text: `${name}'s application goes back to Pending, so you can check it again.`, yes: 'Reopen' });
      if (!ok) return null;
      try { const r = await send({}); Toast.success('Reopened.'); return r; } catch (e) { Toast.error(e.message); return null; }
    }

    return new Promise((resolve) => {
      let result = null;
      const note = h('textarea', { id: 'm-note', class: 'form-control custom-input mt-2', rows: '3', maxlength: '500' });
      const err = h('span', { class: 'text-12 text-dark-red', role: 'alert' });
      let body;
      let classSel = null;
      if (to === 'approved') {
        const chosen = String(app.classes_class_id ?? app.class_id ?? '');
        classSel = h('select', { id: 'm-class', class: 'form-select custom-select mt-2' },
          h('option', { value: '' }, '- Select -'),
          classes.map((c) => h('option', { value: c.value, selected: c.value === chosen }, c.label)));
        note.placeholder = 'Optional, e.g. "Paid the first month at the class"';
        body = h('div', { class: 'row gy-3' },
          h('p', { class: 'col-12 text-14 main-text m-0' }, `This registers ${name} as your student and adds them to the class. They sign in with their WhatsApp number.`),
          field('m-class', 'Class', classSel, { required: true, help: chosen ? 'The class they chose. You can pick another one.' : 'They did not choose a class. Pick one.' }),
          field('m-note', 'Note', note), h('div', { class: 'col-12' }, err));
      } else {
        note.placeholder = to === 'rejected' ? 'e.g. The NIC number does not match the date of birth. Please apply again with the correct details.' : 'e.g. Waiting for the payment slip';
        body = h('div', { class: 'row gy-3' },
          h('p', { class: 'col-12 text-14 main-text m-0' }, to === 'rejected' ? `${name} is not added. You can reopen the application later.` : `${name}'s application waits until you are ready.`),
          field('m-note', to === 'rejected' ? 'Reason' : 'What are you waiting for?', note, { required: true, help: 'Saved in the history and shown on the application.' }),
          h('div', { class: 'col-12' }, err));
      }
      const go = BsModal.button(MOVE_LABEL[to], { primary: true, danger: to === 'rejected', success: to === 'approved', onclick: async () => {
        const text = note.value.trim();
        if (to !== 'approved' && !text) { err.textContent = 'Please fill this in.'; note.classList.add('is-invalid'); note.focus(); return; }
        if (classSel && !classSel.value) { err.textContent = 'Choose the class.'; classSel.classList.add('is-invalid'); classSel.focus(); return; }
        go.disabled = true;
        err.textContent = '';
        try {
          result = await send({ note: text, ...(classSel ? { class_id: classSel.value } : {}) });
          Toast.success(to === 'approved' ? `${name} is now your student.` : to === 'rejected' ? 'Application rejected.' : 'Put on hold.');
          m.close();
        } catch (e) {
          err.textContent = Object.values(e.details?.fields ?? {}).join(' ') || e.message;
          go.disabled = false;
        }
      } });
      const m = BsModal.open({
        icon: to === 'approved' ? 'user-check' : to === 'rejected' ? 'close' : 'hourglass',
        tone: to === 'approved' ? 'success' : to === 'rejected' ? 'danger' : 'warning',
        title: to === 'approved' ? `Approve ${name}?` : to === 'rejected' ? `Reject ${name}?` : `Put ${name} on hold?`,
        subtitle: app.ref ?? app.ref_no ?? '',
        body,
        footerEnd: [BsModal.button('Cancel', { onclick: () => m.close() }), go],
        onClose: () => resolve(result),
      });
      m.el.addEventListener('shown.bs.modal', () => (classSel && !classSel.value ? classSel : note).focus(), { once: true });
    });
  }
}
