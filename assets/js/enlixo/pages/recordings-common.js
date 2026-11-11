// Shared parts of Class Recordings (tutor/recordings.php) and Settings → Recording Types (tutor/recording-types.php).
// Real data from the PHP API (no sample data): /api/v1/tutor/recording-types and /api/v1/tutor/recordings.
import { h, fill } from '../core/dom.js';
import { ApiClient } from '../core/ApiClient.js';
import { icon } from '../core/icons.js';
import { BsModal } from '../components/BsModal.js';
import { Toast } from '../components/Toast.js';
import { infoTip } from '../components/InfoTip.js';
import { EmptyState } from '../components/EmptyState.js';
import { FeatureGate } from '../components/FeatureGate.js';
import { cell, field, simpleTable } from './admin-common.js';

export const api = new ApiClient();
export const gate = await FeatureGate.load(api).catch(() => null);
export const has = (key) => !gate || gate.has(key);
export const lockBadge = (key) => gate?.badge(key) ?? null;

/** One Solar icon per place a recording is kept (the platform). */
export const PLATFORM_ICON = { youtube: 'play', bunny: 'recordings', drive: 'box', vimeo: 'play', zoom: 'online', teams: 'users', custom: 'link', upload: 'upload' };
export const platformTile = (key, size = 20) => h('span', { class: `rc-tile rc-${key}` }, icon(PLATFORM_ICON[key] ?? 'link', { size }));

export const plural = (n, one, many = `${one}s`) => `${n} ${n === 1 ? one : many}`;
export const dayText = (d) => (d ? new Date(`${d}T00:00:00`).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }) : '-');
export const shortDay = (d) => (d ? new Date(`${d}T00:00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '-');
export const ampm = (t) => { if (!t) return ''; const [hh, mm] = t.split(':').map(Number); return `${((hh + 11) % 12) + 1}:${String(mm).padStart(2, '0')} ${hh < 12 ? 'AM' : 'PM'}`; };
export const stamp = (s) => (s ? `${new Date(`${s.slice(0, 10)}T00:00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })}, ${ampm(s.slice(11, 16))}` : '-');
export const minutes = (m) => { const n = Number(m) || 0; const hh = Math.floor(n / 60); const mm = n % 60; return hh ? `${hh}h${mm ? ` ${mm}m` : ''}` : `${mm}m`; };

const STATE = { available: ['success', 'Available'], scheduled: ['primary', 'Opens Later'], expired: ['grey', 'Closed'] };
export const stateChip = (s) => { const [tone, label] = STATE[s] ?? STATE.available; return h('span', { class: `table-btn table-btn-${tone === 'grey' ? 'grey ad-chip-grey' : tone} ad-chip` }, label); };

/** Field errors from a 422 answer: { key: message } → red line under the field with data-key="key". */
export function showFieldErrors(root, map = {}) {
  root.querySelectorAll('[data-err]').forEach((e) => { e.classList.add('d-none'); document.getElementById(e.dataset.err)?.classList.remove('is-invalid'); });
  let first = null;
  Object.entries(map).forEach(([key, msg]) => {
    const el = root.querySelector(`[data-key="${key}"]`);
    if (!el) return;
    el.classList.add('is-invalid');
    const err = root.querySelector(`[data-err="${el.id}"]`);
    if (err) { err.textContent = msg; err.classList.remove('d-none'); }
    first ??= el;
  });
  first?.scrollIntoView({ block: 'center', behavior: 'smooth' });
}

/** Clear a field's red line as soon as the field is changed. */
export function clearOnEdit(root) {
  const off = (e) => {
    const el = e.target.closest('[data-key]');
    if (!el?.classList.contains('is-invalid')) return;
    el.classList.remove('is-invalid');
    root.querySelector(`[data-err="${el.id}"]`)?.classList.add('d-none');
  };
  root.addEventListener('input', off);
  root.addEventListener('change', off);
}

/**
 * Drag a row by its ⋮⋮ handle (.ex-grip) to change the order; the arrow keys on the handle move it too.
 * onDrop(ids) gets the new order.
 */
export function sortable(box, onDrop) {
  let dragged = null;
  const ids = () => [...box.children].map((el) => el.dataset.id).filter(Boolean);
  box.addEventListener('pointerdown', (e) => { const item = e.target.closest('.ex-grip') && e.target.closest('[data-id]'); if (item) item.draggable = true; });
  box.addEventListener('pointerup', () => { if (!dragged) box.querySelectorAll('[draggable="true"]').forEach((el) => { el.draggable = false; }); });
  box.addEventListener('dragstart', (e) => {
    dragged = e.target.closest?.('[data-id]');
    if (!dragged) return;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', dragged.dataset.id);
    requestAnimationFrame(() => { dragged?.classList.add('ex-dragging'); box.classList.add('ex-sorting'); });
  });
  box.addEventListener('dragover', (e) => {
    if (!dragged) return;
    e.preventDefault();
    const over = e.target.closest('[data-id]');
    if (!over || over === dragged || over.parentElement !== box) return;
    const r = over.getBoundingClientRect();
    over[e.clientY > r.top + r.height / 2 ? 'after' : 'before'](dragged);
  });
  box.addEventListener('drop', (e) => e.preventDefault());
  const before = ids().join();
  box.addEventListener('dragend', () => {
    if (!dragged) return;
    dragged.classList.remove('ex-dragging');
    dragged.draggable = false;
    box.classList.remove('ex-sorting');
    dragged = null;
    if (ids().join() !== before) onDrop(ids());
  });
  box.addEventListener('keydown', (e) => {
    const grip = e.target.closest('.ex-grip');
    if (!grip || !['ArrowUp', 'ArrowDown'].includes(e.key)) return;
    e.preventDefault();
    const row = grip.closest('[data-id]');
    const sib = e.key === 'ArrowUp' ? row.previousElementSibling : row.nextElementSibling;
    if (!sib) return;
    sib[e.key === 'ArrowUp' ? 'before' : 'after'](row);
    onDrop(ids());
  });
}

export const grip = (label) => h('button', { type: 'button', class: 'ex-grip', title: 'Drag to change the order', 'aria-label': `Change the order of ${label}: drag, or use the up and down arrow keys` }, icon('drag-dots', { size: 20 }));

/** Small "Activity Log" table (When / Who / What changed). */
export const logTable = (rows, text) => simpleTable([
  { label: 'When', render: (l) => cell(stamp(l.when)) },
  { label: 'Who', render: (l) => cell(l.by) },
  { label: 'What changed', render: (l) => cell(l.what) },
], rows, { title: 'No changes yet', text });

/** Embedded player (types that play in the page) or the button that opens the link. */
export function player(rec) {
  if (rec.type.key === 'upload') {
    return h('div', { class: 'ad-soft p-4 text-14 sub-text text-center' }, 'This video was uploaded before. Open it from the old watch page.', h('div', { class: 'mt-3' },
      h('a', { class: 'custom-btn px-4 text-decoration-none', href: `watch-recording?recording-id=${rec.id}`, target: '_blank', rel: 'noopener' }, 'Open the Watch Page')));
  }
  if (rec.type.embeddable && rec.url) {
    return h('div', { class: 'rc-player' }, h('iframe', { src: rec.url, title: rec.title, allow: 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen', allowfullscreen: true, referrerpolicy: 'strict-origin-when-cross-origin' }));
  }
  return h('div', { class: 'ad-soft p-4 text-center' },
    h('div', { class: 'text-14 main-text fw-medium mb-1' }, `${rec.type.name} recordings open on their own website`),
    h('div', { class: 'text-13 sub-text mb-3' }, rec.passcode ? `Passcode: ${rec.passcode}` : 'Students get the same button.'),
    h('a', { class: 'custom-btn px-4 text-decoration-none d-inline-flex align-items-center gap-2', href: rec.url, target: '_blank', rel: 'noopener' }, icon('share', { size: 18 }), `Open in ${rec.type.name}`));
}

/**
 * "Where is the video?": recording type tiles, the link with a live check, and the passcode.
 * Used by the Add / Edit Recording dialog and the pre-recorded class form (tutor/add-pre-recorded-class.php).
 * v = { type, link, passcode }. onVideo({ title, minutes }) is called when a connected Bunny library sent the
 * video details, so the form can fill the title and length.
 * Returns { el, values(): { type, link, passcode } }. Field keys for errors: type, link, passcode.
 */
export function linkPicker(types, v, { onVideo } = {}) {
  const inp = (key, attrs = {}) => h('input', { class: 'form-control custom-input', 'data-key': key, id: `rc-${key}`, value: v[key] ?? '', ...attrs });
  const link = inp('link', { placeholder: 'Paste the recording link', 'data-limit': '600', maxlength: 600, autocomplete: 'off' });
  const passcode = inp('passcode', { placeholder: 'Enter the passcode, if the link asks for one', 'data-limit': '100', maxlength: 100, autocomplete: 'off' });
  const linkState = h('div', { class: 'rc-link-state d-none', role: 'status' });
  const linkNote = h('div', { class: 'rc-note d-none' });
  const linkHelp = h('div', { class: 'text-12 sub-text mt-1' });
  const typePick = h('div', { class: 'rc-types', role: 'radiogroup', 'aria-label': 'Where the video is kept', 'data-key': 'type', id: 'rc-type-pick' },
    types.map((t) => h('label', { class: 'rc-type' },
      h('input', { type: 'radio', name: 'rc-type', value: t.id, checked: String(t.id) === String(v.type), onchange: () => { drawLinkHelp(); checkLink(); } }),
      platformTile(t.platform_key, 16), h('span', { class: 'text-truncate' }, t.name))));
  const typeOf = () => types.find((t) => String(t.id) === String(typePick.querySelector('input:checked')?.value));

  const drawLinkHelp = () => {
    const t = typeOf();
    const bunnyId = t?.platform_key === 'bunny' && t.bunny_library_id;
    link.placeholder = bunnyId ? 'Paste the Bunny video link, or only its video ID'
      : t?.link_example ? `Paste the link, for example ${t.link_example}` : 'Paste the recording link';
    fill(linkHelp, !t ? '' : [
      t.embeddable ? 'Students watch it inside ENLIXO.' : `Students get a button that opens ${t.name} in a new tab.`,
      bunnyId ? ` Connected to your Bunny library ${t.bunny_library_id}${t.bunny_connected ? ': we check the video and fill in its length.' : '.'}` : '',
    ].join(''));
  };

  let checkSeq = 0;
  const checkLink = async () => {
    const t = typeOf();
    linkNote.classList.add('d-none');
    if (!t || !link.value.trim()) { linkState.classList.add('d-none'); return; }
    const seq = ++checkSeq;
    try {
      const { data: r } = await api.post('/tutor/recording-types/check', { type: t.id, link: link.value });
      if (seq !== checkSeq) return;
      linkState.className = `rc-link-state ${r.ok ? 'ok' : 'bad'}`;
      const found = r.video ? ` Bunny video found${r.video.title ? `: "${r.video.title}"` : ''}${r.video.minutes ? `, ${minutes(r.video.minutes)}` : ''}.` : '';
      fill(linkState, icon(r.ok ? 'check' : 'danger', { size: 16 }), h('span', {}, r.ok ? `Link checked.${found}` : r.error,
        r.suggest ? h('button', { type: 'button', class: 'btn btn-link p-0 ms-1 align-baseline', style: { fontSize: '12px' }, onclick: () => { typePick.querySelector(`input[value="${r.suggest}"]`)?.click(); } }, 'Use that type') : null));
      if (r.note) { fill(linkNote, icon('info', { size: 16 }), h('span', {}, r.note)); linkNote.classList.remove('d-none'); }
      if (r.ok && r.video) onVideo?.(r.video);
    } catch { linkState.classList.add('d-none'); }
  };
  let linkTimer;
  link.addEventListener('input', () => { clearTimeout(linkTimer); linkTimer = setTimeout(checkLink, 600); });

  const el = h('div', {},
    h('div', { class: 'mb-3' }, h('label', { class: 'text-14 main-text mb-2 d-inline-flex align-items-center gap-2 required', for: 'rc-type-pick' }, 'Where is the video?',
      infoTip('The website the recording is kept on. Each has its own link check. Add or switch off websites in Settings → Recording Types.', 'Where is the video?')),
    typePick, h('div', { class: 'text-12 text-dark-red mt-1 d-none', 'data-err': 'rc-type-pick' })),
    field('Recording link', link, { required: true, info: 'Copy the share link of the video and paste it here. An embed code (<iframe ...>) works too. For a connected Bunny library the video ID alone is enough. We keep only the clean link, so the video always plays the same way.' }),
    h('div', { class: 'mt-n2 mb-3' }, linkState, linkNote, linkHelp),
    field('Passcode', passcode, { info: 'Only if the link asks for a passcode, for example a Zoom cloud recording. Students see it with a Copy button next to the video.' }));
  drawLinkHelp();
  if (link.value) checkLink();
  return { el, values: () => ({ type: Number(typeOf()?.id ?? 0), link: link.value, passcode: passcode.value }) };
}

export { h, fill, icon, BsModal, Toast, infoTip, EmptyState, cell, field };
