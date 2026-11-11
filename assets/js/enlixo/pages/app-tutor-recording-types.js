// Tutor → Settings → Recording Types (tutor/recording-types.php), like ENLIXO V3 Settings → Recording Types.
// One card with tabs: Recording Types (the list: drag to order, switch on / off, edit, delete + "Test a link")
// and Activity Log. Add / edit open a dialog. Data: /api/v1/tutor/recording-types.
import { Tabs } from '../components/Tabs.js';
import { QuickGuide } from '../components/QuickGuide.js';
import { ActionMenu } from '../components/Popover.js';
import {
  api, h, fill, icon, BsModal, Toast, infoTip, EmptyState, field, plural, platformTile, sortable, grip, logTable, showFieldErrors, clearOnEdit,
} from './recordings-common.js';

QuickGuide.attachAll();
const root = document.getElementById('recording-types-page');
let D;
let tabs;

fill(document.getElementById('types-actions'),
  h('div', { class: 'col-6' }),
  h('div', { class: 'col-6' }, h('button', { type: 'button', class: 'custom-btn w-100', onclick: () => edit(null) }, 'Add Recording Type')));

const tag = (text, tone = '') => h('span', { class: `rc-tag ${tone}` }, text);
const sw = (checked, attrs = {}) => h('div', { class: 'form-check form-switch m-0' }, h('input', { class: 'form-check-input', type: 'checkbox', role: 'switch', checked, ...attrs }));

async function call(fn, ok) {
  try {
    const { data } = await fn();
    if (data?.types) D.types = data.types;
    if (ok) Toast.success(ok);
    await reloadLog();
    return data;
  } catch (e) {
    Toast.error(e.message);
    draw();
    throw e;
  }
}
async function reloadLog() {
  try { ({ data: { log: D.log } } = await api.get('/tutor/recording-types')); } catch { /* keep the old log */ }
  draw();
}

// ------------------------------------------------------------------ list
function row(t) {
  const items = [
    { label: 'Edit', icon: 'edit', onClick: () => edit(t) },
    { label: 'Test a link', icon: 'link', onClick: () => tester.pick(t.id) },
    t.used
      ? { label: 'Delete', icon: 'delete', onClick: () => Toast.error(`${plural(t.used, 'recording')} use ${t.name}, so it cannot be deleted. Switch it off instead: those recordings keep working.`) }
      : { label: 'Delete', icon: 'delete', danger: true, onClick: () => remove(t) },
  ];
  return h('div', { class: `rc-row${t.active ? '' : ' is-off'}`, 'data-id': String(t.id) },
    grip(t.name),
    platformTile(t.platform_key),
    h('div', { class: 'rc-info' },
      h('div', { class: 'rc-name' }, t.name,
        tag(t.embeddable ? 'Plays in the page' : 'Opens in a new tab', t.embeddable ? 'ok' : 'new-tab'),
        t.used ? tag(plural(t.used, 'recording')) : null,
        t.active ? null : tag('Off', 'off')),
      h('div', { class: 'rc-meta' }, [t.platform_label, t.allowed_hosts, t.link_example].filter(Boolean).join(' · '))),
    h('div', { class: 'rc-actions' },
      h('label', { class: 'visually-hidden', for: `rt-on-${t.id}` }, `Switch ${t.name} on or off`),
      sw(t.active, { id: `rt-on-${t.id}`, title: t.active ? 'Switch off' : 'Switch on', onchange: (e) => toggle(t, e.target.checked) }),
      ActionMenu.kebab(items, `Actions for ${t.name}`)));
}

async function toggle(t, on) {
  await call(() => api.post(`/tutor/recording-types/${t.id}/status`, { active: on }), on ? `${t.name} is switched on.` : `${t.name} is switched off. Recordings already added keep working.`).catch(() => {});
}

async function remove(t) {
  const yes = await BsModal.confirm({ title: `Delete ${t.name}?`, text: 'No recording uses it, so nothing else changes. This cannot be undone.', yes: 'Delete', danger: true });
  if (yes) await call(() => api.delete(`/tutor/recording-types/${t.id}`), `${t.name} deleted.`).catch(() => {});
}

// ------------------------------------------------------------------ add / edit dialog
function edit(t) {
  const isNew = !t;
  const v = t ?? { name: '', platform_key: '', allowed_hosts: '', link_example: '' };
  const name = h('input', { class: 'form-control custom-input', 'data-key': 'name', value: v.name, placeholder: 'Enter a name, for example Zoom', 'data-limit': '60', maxlength: 60 });
  const platform = h('select', { class: 'form-select custom-select', 'data-key': 'platform_key' },
    h('option', { value: '', disabled: true, selected: !v.platform_key }, '- Select -'),
    D.platforms.map((p) => h('option', { value: p.key, selected: p.key === v.platform_key, disabled: t?.used > 0 && p.key !== v.platform_key }, p.label)));
  const hosts = h('input', { class: 'form-control custom-input', 'data-key': 'allowed_hosts', value: v.allowed_hosts, placeholder: 'Enter the websites, for example dropbox.com, onedrive.com', 'data-limit': '255', maxlength: 255 });
  const example = h('input', { class: 'form-control custom-input', 'data-key': 'link_example', value: v.link_example, placeholder: 'Enter an example link, for example https://zoom.us/rec/share/abc', 'data-limit': '255', maxlength: 255 });
  const help = h('div', { class: 'ad-soft p-3 d-flex gap-3 align-items-start mb-3' });
  const hostsWrap = h('div', {}, field('Allowed websites', hosts, { required: true, info: 'Links of this type must be on one of these websites. Separate them with commas. A website also covers its parts, so dropbox.com accepts www.dropbox.com.' }));
  const drawHelp = () => {
    const p = D.platforms.find((x) => x.key === platform.value);
    hostsWrap.classList.toggle('d-none', platform.value !== 'custom');
    fill(help, platformTile(p?.key ?? 'custom'), h('div', {},
      h('div', { class: 'text-14 main-text fw-semibold' }, p ? `${p.label}: ${p.embeddable ? 'plays in the page' : 'opens in a new tab'}` : 'Choose where the recordings are kept'),
      h('div', { class: 'text-12 sub-text' }, p ? p.help : 'This decides how a pasted link is checked and how students watch it.')));
  };
  platform.addEventListener('change', drawHelp);
  const body = h('div', {},
    field('Name', name, { required: true, info: 'The name you and your students see, for example "Zoom" or "My Google Drive". Up to 60 characters.' }),
    field('Where the recordings are kept', platform, { required: true, info: t?.used ? `${plural(t.used, 'recording')} use this type, so this cannot change. Add a new type for another website.` : 'Pick the website. Each one has its own link check, so a wrong or mixed-up link is stopped before it is saved. Pick Other website to allow any website you list.' }),
    help, hostsWrap,
    field('Example link', example, { info: 'Shown as the hint in the link box when you add a recording, so you remember what to copy. It must pass the check of this type. Leave it empty if you do not need a hint.' }));
  clearOnEdit(body);
  drawHelp();
  const m = BsModal.open({
    title: isNew ? 'Add a Recording Type' : `Edit ${t.name}`, subtitle: isNew ? 'A new place recordings can come from' : [t.platform_label, t.used ? plural(t.used, 'recording') : 'Not used yet'],
    icon: isNew ? 'add' : 'edit', body,
    footerEnd: [BsModal.button('Cancel', { onclick: () => m.close() }), BsModal.button(isNew ? 'Add Recording Type' : 'Save Changes', {
      primary: true,
      onclick: async (e) => {
        const btn = e.currentTarget; btn.disabled = true;
        const payload = { name: name.value, platform_key: platform.value, allowed_hosts: hosts.value, link_example: example.value };
        try {
          const { data } = await (isNew ? api.post('/tutor/recording-types', payload) : api.put(`/tutor/recording-types/${t.id}`, payload));
          D.types = data.types;
          m.close();
          Toast.success(isNew ? `${data.type.name} added and switched on.` : 'Saved.');
          reloadLog();
        } catch (err) {
          showFieldErrors(body, err.details ?? {});
          if (!Object.keys(err.details ?? {}).length) Toast.error(err.message);
        } finally { btn.disabled = false; }
      },
    })],
  });
  setTimeout(() => name.focus(), 300);
}

// ------------------------------------------------------------------ test a link
const tester = (() => {
  const type = h('select', { class: 'form-select custom-select', 'aria-label': 'Recording type' });
  const link = h('input', { class: 'form-control custom-input', placeholder: 'Paste a recording link or embed code to test', 'data-limit': '600', maxlength: 600 });
  const result = h('div', { class: 'rc-result d-none', role: 'status' });
  const drawTypes = () => {
    const keep = type.value;
    fill(type, h('option', { value: '', disabled: true, selected: !keep }, '- Select -'), D.types.filter((t) => t.active).map((t) => h('option', { value: t.id, selected: String(t.id) === keep }, t.name)));
    const t = D.types.find((x) => String(x.id) === type.value);
    link.placeholder = t?.link_example ? `Paste a link, for example ${t.link_example}` : 'Paste a recording link or embed code to test';
  };
  type.addEventListener('change', drawTypes);
  const check = async () => {
    if (!type.value) { fill(result, icon('danger', { size: 20 }), h('span', {}, 'Choose a recording type first.')); result.className = 'rc-result bad'; return; }
    try {
      const { data: r } = await api.post('/tutor/recording-types/check', { type: Number(type.value), link: link.value });
      result.className = `rc-result ${r.ok ? 'ok' : 'bad'}`;
      fill(result, icon(r.ok ? 'check' : 'danger', { size: 20 }), h('div', { class: 'min-w-0' },
        h('div', { class: 'fw-semibold' }, r.ok ? `This ${r.type} link is fine` : 'This link would be stopped'),
        h('div', { class: 'text-13 text-break' }, r.ok ? `Saved as ${r.url}. ${r.embeddable ? 'Students watch it inside ENLIXO.' : 'Students get a button that opens it in a new tab.'}` : r.error),
        r.suggest ? h('button', { type: 'button', class: 'btn btn-link p-0 text-13', onclick: () => { type.value = String(r.suggest); drawTypes(); check(); } }, 'Check it as that type') : null));
    } catch (e) { Toast.error(e.message); }
  };
  link.addEventListener('keydown', (e) => { if (e.key === 'Enter') check(); });
  const el = h('div', { class: 'ad-soft p-4' },
    h('div', { class: 'sub-topic mb-1 d-flex align-items-center gap-2' }, 'Test a link', infoTip('Try a link against a type without saving anything. You see the link that would be saved, and if it belongs to another type, which one.', 'Test a link')),
    h('div', { class: 'text-12 sub-text mb-3' }, 'See what happens to a link before you add a recording.'),
    field('Recording type', type),
    field('Recording link', link),
    h('div', { class: 'd-flex justify-content-end' }, h('button', { type: 'button', class: 'custom-btn-outline px-4', onclick: check }, 'Check the Link')),
    h('div', { class: 'mt-3' }, result));
  return { el, drawTypes, pick: (id) => { tabs?.show('types'); type.value = String(id); drawTypes(); link.focus(); result.classList.add('d-none'); } };
})();

// ------------------------------------------------------------------ tabs
function list(p) {
  const on = D.types.filter((t) => t.active).length;
  const rows = h('div', { class: 'rc-list' }, D.types.map(row));
  if (D.types.length > 1) sortable(rows, (ids) => call(() => api.post('/tutor/recording-types/order', { ids: ids.map(Number) }), 'Order saved.').catch(() => {}));
  tester.drawTypes();
  p.append(h('div', { class: 'row g-4' },
    h('div', { class: 'col-12 col-xl-7' },
      h('div', { class: 'sub-topic mb-1 d-flex align-items-center gap-2' }, 'Your recording types', infoTip('These are offered, in this order, when you add a recording. Switch a type off to hide it; recordings already added keep working. At least one must stay on.', 'Your recording types')),
      h('div', { class: 'text-12 sub-text mb-3 d-flex align-items-center gap-1 flex-wrap' }, `${on} of ${plural(D.types.length, 'type')} switched on. To change the order, hold`, h('span', { class: 'ex-grip-sample' }, icon('drag-dots', { size: 14 })), 'and drag a type up or down.'),
      D.types.length ? rows : EmptyState.empty({ title: 'No recording types yet', text: 'Add the websites your recordings are kept on, for example YouTube or Google Drive.' })),
    h('div', { class: 'col-12 col-xl-5' }, h('div', { class: 'at-sticky' }, tester.el))));
}

function draw() {
  if (!tabs) return;
  tabs.tabs[0].badge = D.types.length;
  tabs.tabs[1].badge = D.log.length;
  tabs.refresh();
}

async function load() {
  try {
    ({ data: D } = await api.get('/tutor/recording-types'));
    const card = h('div', { class: 'col-12 component-theme p-4' });
    tabs = new Tabs({ tabs: [
      { id: 'types', label: 'Recording Types', badge: D.types.length, render: list },
      { id: 'log', label: 'Activity Log', badge: D.log.length, render: (p) => p.append(logTable(D.log, 'Every change to your recording types is listed here.')) },
    ] });
    tabs.mount(card);
    fill(root, card);
  } catch (e) { fill(root, EmptyState.error(e.message, () => load())); }
}
load();
