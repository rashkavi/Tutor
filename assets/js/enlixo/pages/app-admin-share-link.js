// Admin → Share Registration Link (admin/share-registration-link.php): the sign-up link for new tutors, general and one
// per plan, with Copy, WhatsApp and e-mail. The site address comes from this page's own address (admin/ is under it).
import { QuickGuide } from '../components/QuickGuide.js';
import { api, h, fill, icon, lkr, Toast } from './admin-common.js';

QuickGuide.attachAll();
const root = document.getElementById('sl-page');
const actions = document.getElementById('sl-actions');
const base = new URL('../', location.href).href.replace(/\/$/, '');
const form = `${base}/tutor-registration-application`;

fill(root, h('div', { class: 'component-theme p-5 text-center sub-text' }, 'Loading…'));
load();

async function load() {
  let plans = [];
  try { plans = (await api.get('/public/tutor-signup')).data.plans ?? []; } catch { /* the general link still works */ }
  const links = [
    { title: 'Sign-up form', sub: 'The tutor chooses the plan', url: form, ico: 'link' },
    ...plans.filter((p) => !p.custom).map((p) => ({ title: `${p.name} plan`, sub: p.free ? `Free, up to ${p.limit} students` : `${lkr(p.monthly)} a month · up to ${p.limit ?? 'any'} students`, url: `${form}?plan=${p.key}`, ico: 'crown' })),
  ];
  const copy = async (url, btn) => {
    try { await navigator.clipboard.writeText(url); } catch { const t = h('textarea', {}, url); document.body.append(t); t.select(); document.execCommand('copy'); t.remove(); }
    btn.textContent = 'Copied';
    Toast.success('Link copied.');
    setTimeout(() => { btn.textContent = 'Copy Link'; }, 1500);
  };
  const msg = (url) => `Hi! Join ENLIXO Tutor to run your classes, fees, attendance and recordings in one app. Sign up here: ${url}`;
  fill(actions, h('a', { class: 'custom-btn px-4 text-decoration-none d-inline-flex align-items-center', href: 'requested-users' }, 'Requested Tutors'));
  fill(root, h('div', { class: 'row g-4' }, links.map((l) => {
    const btn = h('button', { type: 'button', class: 'custom-btn px-4' }, 'Copy Link');
    btn.onclick = () => copy(l.url, btn);
    return h('div', { class: 'col-12 col-lg-6' }, h('div', { class: 'component-theme p-4 h-100 d-flex flex-column gap-3' },
      h('div', { class: 'd-flex align-items-center gap-3' }, h('span', { class: 'fe-form-ico' }, icon(l.ico, { size: 22 })),
        h('div', { class: 'min-w-0' }, h('div', { class: 'text-16 main-text fw-semibold' }, l.title), h('div', { class: 'text-13 sub-text' }, l.sub))),
      h('div', { class: 'sl-url', 'data-no-i18n': '' }, l.url),
      h('div', { class: 'd-flex flex-wrap gap-2 mt-auto' }, btn,
        h('a', { class: 'custom-btn px-4 text-decoration-none d-inline-flex align-items-center gap-2', href: `https://wa.me/?text=${encodeURIComponent(msg(l.url))}`, target: '_blank', rel: 'noopener' }, icon('whatsapp', { size: 18 }), 'WhatsApp'),
        h('a', { class: 'custom-btn px-4 text-decoration-none d-inline-flex align-items-center gap-2', href: `mailto:?subject=${encodeURIComponent('Join ENLIXO Tutor')}&body=${encodeURIComponent(msg(l.url))}` }, icon('mail', { size: 18 }), 'E-mail'))));
  })));
}
