// Tutor → Add Recording / Edit Recording (tutor/add-recording.php): the recording form as a page, header Save button
// and the same button under the form. data-id (edit), data-class, data-day come from the address.
import { QuickGuide } from '../components/QuickGuide.js';
import { api, h, fill, Toast, EmptyState } from './recordings-common.js';
import { recordingForm } from './recording-form.js';

QuickGuide.attachAll();
const root = document.getElementById('rf-page');
const id = Number(root.dataset.id || 0);
const back = (cls) => `recordings${cls ? `?class=${encodeURIComponent(cls)}` : ''}`;

async function start() {
  fill(root, h('div', { class: 'component-theme p-5 text-center sub-text' }, 'Loading…'));
  let O; let rec = null;
  try {
    [{ data: O }, rec] = await Promise.all([
      api.get('/tutor/recordings/overview'),
      id ? api.get(`/tutor/recordings/${id}`).then((r) => r.data) : Promise.resolve(null),
    ]);
  } catch (e) { fill(root, EmptyState.error(e.message, start)); return; }
  if (!O.can_manage) {
    fill(root, h('div', { class: 'component-theme p-4' }, EmptyState.render?.({ title: 'You cannot add recordings', text: 'Ask the tutor to give you the Recordings permission.' }) ?? 'You cannot add recordings.'));
    return;
  }
  const f = recordingForm({ O, rec, cls: root.dataset.class !== '0' ? root.dataset.class : '', day: root.dataset.day !== '0' ? root.dataset.day : '' });
  if (f.noTypes) {
    fill(root, h('div', { class: 'component-theme p-4 d-flex flex-wrap align-items-center gap-3' },
      h('span', { class: 'text-14 main-text flex-grow-1' }, 'Switch on at least one recording type first, so we know where your videos are kept.'),
      h('a', { class: 'custom-btn px-4 text-decoration-none', href: 'recording-types' }, 'Recording Types')));
    return;
  }
  const label = id ? 'Save Changes' : 'Save Recording';
  const buttons = [];
  const go = async () => {
    buttons.forEach((b) => { b.disabled = true; b.textContent = 'Saving…'; });
    const r = await f.save();
    if (r.ok) {
      Toast.success(id ? 'Recording saved.' : 'Recording added. Students of the class can see it.');
      setTimeout(() => { location.href = back(r.class); }, 800);
      return;
    }
    buttons.forEach((b) => { b.disabled = false; b.textContent = label; });
  };
  const btn = (cls = 'custom-btn w-100') => { const b = h('button', { type: 'button', class: cls, onclick: go }, label); buttons.push(b); return b; };
  // header: the Save button like Create a Class (the breadcrumb is the way back)
  fill(document.getElementById('rf-actions'), btn('custom-btn ex-btn-lg px-5 border-0'));
  fill(root, f.el,
    h('div', { class: 'row p-0 m-0 g-0 justify-content-end' }, h('div', { class: 'col-12 col-lg-4' }, h('div', { class: 'row gx-3 justify-content-end' }, h('div', { class: 'col-6' }, btn())))));
}
start();
