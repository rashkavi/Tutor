// Shared by Admin → Requested Tutors (the list) and one request (admin/requested-user-details.php):
// the status chip, plan names, and the Reject / Open again / Add note / Called actions.
import { api, h, BsModal, Toast, chip } from './admin-common.js';

export const PLAN = { free: 'FREE', start: 'STARTER', grow: 'GROW', pro: 'CUSTOM' };
export const statusChip = (st) => (st === 'pending' ? chip('waiting', 'Pending') : chip(st));
export const when = (d) => (d ? new Date(String(d).replace(' ', 'T')).toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit' }) : '');
export const detailsUrl = (id) => `requested-user-details?id=${id}`;

function textDialog({ title, subtitle, icon, tone = '', label, placeholder, help, button, danger = false, required = true, send }) {
  const box = h('textarea', { class: 'form-control custom-input', rows: 3, maxlength: 500, placeholder, id: 'rq-text' });
  const err = h('div', { class: 'text-12 text-dark-red mt-1 d-none' });
  const m = BsModal.open({
    title, subtitle, icon, tone,
    body: h('div', {}, h('label', { class: `text-14 main-text mb-1 ${required ? 'required' : ''}`, for: 'rq-text' }, label), box, err, help ? h('div', { class: 'text-12 sub-text mt-1' }, help) : null),
    footerEnd: [
      BsModal.button('Cancel', { onclick: () => m.close() }),
      BsModal.button(button, { primary: !danger, danger, onclick: async (e) => {
        const btn = e.currentTarget;
        btn.disabled = true;
        try {
          await send(box.value);
          m.close();
        } catch (x) {
          btn.disabled = false;
          err.textContent = x.details?.reason ?? x.details?.note ?? x.message;
          err.classList.remove('d-none');
          box.classList.add('is-invalid');
        }
      } }),
    ],
  });
}

export function rejectRequest(r, done) {
  textDialog({
    title: `Reject ${r.name}?`, subtitle: 'The request moves to Rejected. You can open it again later.', icon: 'close', tone: 'danger',
    label: 'Reason', placeholder: 'For example: Not a tutor, wrong numbers, asked to join later', help: 'Only admins see the reason. No e-mail is sent.',
    button: 'Reject Request', danger: true,
    send: async (reason) => { await api.post(`/admin/tutor-requests/${r.id}/reject`, { reason }); Toast.success(`${r.name}'s request was rejected.`); done(); },
  });
}

export function noteRequest(r, done, { called = false } = {}) {
  textDialog({
    title: called ? `Called ${r.name}` : 'Add a Note',
    subtitle: called ? 'Marks the "Called" step. Write what you talked about.' : 'Kept in the history of this request. Only admins see it.',
    icon: called ? 'phone' : 'edit', label: called ? 'What did you talk about?' : 'Note', required: !called,
    placeholder: called ? 'For example: Has 120 students, wants to start next week on GROW' : 'For example: Asked to call back on Monday after 4 PM',
    button: called ? 'Save Call' : 'Save Note',
    send: async (note) => { await api.post(`/admin/tutor-requests/${r.id}/note`, { note, called }); Toast.success(called ? 'Call saved.' : 'Note saved.'); done(); },
  });
}

export async function reopenRequest(r, done) {
  try {
    await api.post(`/admin/tutor-requests/${r.id}/reopen`, {});
    Toast.success(`${r.name}'s request is pending again.`);
    done();
  } catch (e) { Toast.error(e.message); }
}
