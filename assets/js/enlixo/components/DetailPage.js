import { h, fmt, debounce } from '../core/dom.js';
import { icon } from '../core/icons.js';

/**
 * The one "details page" look (ENLIXO V3 requested-student / student-payment pages), used by
 *   tutor/student-application.php   one student application
 *   admin/requested-user-details.php one tutor sign-up request
 *   tutor/fee-details.php            one student fee and its slip
 *   admin/tutor-payment-details.php  one tutor subscription payment
 *
 *   DetailPage.layout(left, right, below)   4 / 8 columns, then full-width blocks
 *   DetailPage.profile({ name, photo, subtitle, ref, status, dot, mobile, whatsapp, email, lines, facts })
 *   DetailPage.card(title, body, { tools, top })   a white card with a title row
 *   DetailPage.steps([{ title, sub, state: 'done' | 'now' | 'wait' | 'bad' | 'todo' }])
 *   DetailPage.sections(list, openSet, redraw)   collapsible label / value groups with "n of m filled"
 *   DetailPage.receipt({ label, ref, amountLabel, amount, chip, facts: [[label, value]] })   V3 receipt card
 *   DetailPage.history(rows, { title, search })   table of who did what
 *   DetailPage.notFound(text, back: [label, href])
 * Styles: assets/css/enlixo-ui.css (.ex-profile, .ex-stepper, .ex-acc, .ex-receipt).
 */
export class DetailPage {
  static layout(left, right, ...below) {
    return [
      h('div', { class: 'row g-4 mb-4' }, h('div', { class: 'col-12 col-xl-4' }, left), h('div', { class: 'col-12 col-xl-8 d-flex flex-column gap-4' }, right)),
      ...below.filter(Boolean),
    ];
  }

  static waLink(n) {
    const d = String(n ?? '').replace(/\D/g, '');
    if (!d) return null;
    return `https://wa.me/${d.startsWith('0') ? `94${d.slice(1)}` : d}`;
  }

  static profile({ name, photo = null, subtitle = '', ref = null, refLabel = 'Ref.', status = null, dot = null, mobile = '', whatsapp = '', email = '', lines = [], facts = [] }) {
    const initials = fmt.initials(String(name || '?').replace(/^(Mr|Mrs|Miss|Ms|Dr|Prof|Rev|Pr)\.?\s+/i, '') || '?');
    const pic = photo ? h('img', { src: photo, alt: '', onerror: (e) => e.target.replaceWith(document.createTextNode(initials)) }) : initials;
    const contact = (ico, label, href) => h(href ? 'a' : 'span', { class: `ex-contact-btn${href ? '' : ' opacity-50'}`, href: href ?? null, target: href?.startsWith('http') ? '_blank' : null, rel: 'noopener', 'aria-disabled': href ? null : 'true' }, icon(ico, { size: 20 }), label);
    const line = (ico, k, v) => (v ? h('div', { class: 'ex-info-line' }, h('span', { class: 'ex-info-ico' }, icon(ico, { size: 18 })), h('div', { class: 'min-w-0 flex-grow-1' }, h('div', { class: 'text-12 sub-text' }, k), h('div', { class: 'text-14 main-text text-break' }, v)),
      h('button', { type: 'button', class: 'ex-copy', title: `Copy ${k.toLowerCase()}`, 'aria-label': `Copy ${k.toLowerCase()}`, onclick: (e) => { navigator.clipboard?.writeText(v); e.currentTarget.classList.add('ok'); setTimeout(() => e.target.closest('.ex-copy')?.classList.remove('ok'), 1200); } }, icon('copy', { size: 16 }))) : null);
    const meta = (k, v) => (v ? h('div', { class: 'ex-meta-row' }, h('span', {}, k), h('span', {}, v)) : null);
    const wa = whatsapp || mobile;
    return h('div', { class: 'ex-profile h-100' },
      h('div', { class: 'ex-profile-banner' }),
      h('div', { class: 'px-4 pb-4 text-center' },
        h('div', { class: 'ex-profile-photo' }, pic, dot ? h('span', { class: 'ex-profile-dot', style: { background: dot } }) : null),
        h('div', { class: 'ex-profile-name mt-3' }, name),
        subtitle ? h('div', { class: 'text-12 sub-text' }, subtitle) : null,
        h('div', { class: 'd-flex justify-content-center flex-wrap gap-2 mt-3' }, ref ? h('span', { class: 'ex-tag-v3 gap-1' }, icon('hashtag', { size: 14 }), `${refLabel} ${ref}`) : null, status),
        h('div', { class: 'd-flex gap-2 mt-4' },
          contact('phone', 'Call', mobile ? `tel:${mobile}` : null),
          contact('whatsapp', 'WhatsApp', DetailPage.waLink(wa)),
          contact('mail', 'E-mail', email ? `mailto:${email}` : null)),
        h('div', { class: 'text-start mt-3' },
          line('phone', 'Mobile', mobile),
          line('whatsapp', 'WhatsApp', whatsapp),
          line('mail', 'E-mail', email),
          lines.map(([ico, k, v]) => line(ico, k, v))),
        facts.some(([, v]) => v) ? h('div', { class: 'text-start mt-3 ex-panel-soft px-3 py-2' }, facts.map(([k, v]) => meta(k, v))) : null));
  }

  static card(title, body, { tools = null, top = null, cls = '' } = {}) {
    return h('div', { class: `component-theme p-4 ${cls}` },
      h('div', { class: 'd-flex flex-wrap align-items-start gap-3 mb-3' },
        h('div', { class: 'flex-grow-1' }, h('div', { class: 'sub-topic' }, title), top),
        tools),
      body);
  }

  static progress(filled, total) {
    const pct = total ? Math.round((filled / total) * 100) : 0;
    return h('div', { class: 'd-flex align-items-center gap-3 mt-2' },
      h('div', { class: 'ex-progress', role: 'progressbar', 'aria-valuenow': String(pct), 'aria-valuemin': '0', 'aria-valuemax': '100', 'aria-label': 'Fields filled' }, h('span', { style: { width: `${pct}%` } })),
      h('span', { class: 'text-12 sub-text' }, `${filled} of ${total} fields filled · ${pct}%`));
  }

  static steps(list) {
    const cls = { done: 'ex-done', now: 'ex-now', wait: 'ex-wait', bad: 'ex-bad', todo: '' };
    const dot = (s, n) => ({ done: icon('check', { size: 18 }), bad: icon('close', { size: 18 }), wait: icon('deactivate', { size: 18 }), now: icon('clock', { size: 18 }) }[s.state] ?? String(n + 1));
    return h('div', { class: 'ex-stepper', style: { gridTemplateColumns: `repeat(${list.length}, 1fr)` } },
      list.map((s, n) => h('div', { class: `ex-step ${cls[s.state] ?? ''}` }, h('span', { class: 'ex-step-dot' }, dot(s, n)), h('span', { class: 'ex-step-title' }, s.title), h('span', { class: 'ex-step-sub' }, s.sub ?? ''))));
  }

  /** list: [{ key, title, icon, items: [{ label, value, required, wide, node }] }] */
  static sections(list, open, redraw) {
    const empty = (i) => i.node == null && (i.value === null || i.value === undefined || String(i.value).trim() === '');
    return h('div', {}, list.map((s) => {
      const isOpen = open.has(s.key);
      const n = s.items.filter((i) => !empty(i)).length;
      return h('div', { class: `ex-acc${isOpen ? ' ex-open' : ''}` },
        h('button', { type: 'button', class: 'ex-acc-head', 'aria-expanded': String(isOpen), onclick: () => { isOpen ? open.delete(s.key) : open.add(s.key); redraw(); } },
          h('span', { class: 'ex-acc-ico' }, icon(s.icon ?? 'view-details', { size: 18 })),
          h('span', {}, s.title),
          h('span', { class: 'ex-acc-count' }, s.meta ?? `${n} of ${s.items.length} filled`),
          h('span', { class: 'ex-acc-chev' }, icon('chevron-down', { size: 18 }))),
        isOpen ? h('div', { class: 'ex-acc-grid' }, s.items.map((i) => h('div', { class: `ex-acc-cell${i.wide ? ' ex-acc-wide' : ''}` },
          h('div', { class: 'ex-acc-k' }, i.label, i.required ? h('span', { class: 'text-dark-red' }, ' *') : null),
          h('div', { class: `ex-acc-v${empty(i) ? ' ex-empty-v' : ''}` }, i.node ?? (empty(i) ? 'Not provided' : String(i.value)))))) : null);
    }));
  }

  static expandTools(list, open, redraw) {
    return h('div', { class: 'd-flex gap-2' },
      h('button', { type: 'button', class: 'custom-outline-btn ex-btn-sm px-3', onclick: () => { list.forEach((s) => open.add(s.key)); redraw(); } }, icon('expand', { size: 16 }), 'Expand all'),
      h('button', { type: 'button', class: 'custom-outline-btn ex-btn-sm px-3', onclick: () => { open.clear(); redraw(); } }, icon('collapse', { size: 16 }), 'Collapse all'));
  }

  /** V3 receipt card: reference on the left, the amount on the right, then a row of facts */
  static receipt({ label = 'Receipt No', ref = '-', amountLabel = 'Amount', amount = '', chip = null, facts = [], tone = '' }) {
    return h('div', { class: `ex-receipt ${tone}` },
      h('div', { class: 'ex-receipt-top' },
        h('div', {}, h('div', { class: 'ex-receipt-k' }, label), h('div', { class: 'ex-receipt-ref' }, ref), chip ? h('div', { class: 'mt-2' }, chip) : null),
        h('div', { class: 'text-end' }, h('div', { class: 'ex-receipt-k' }, amountLabel), h('div', { class: 'ex-receipt-amount' }, amount))),
      facts.length ? h('div', { class: 'ex-receipt-facts' }, facts.filter(Boolean).map(([k, v, ico]) => h('div', { class: 'ex-receipt-fact' },
        h('span', { class: 'ex-receipt-fico' }, icon(ico ?? 'info', { size: 18 })),
        h('div', { class: 'min-w-0' }, h('div', { class: 'text-12 sub-text' }, k), h('div', { class: 'text-14 main-text fw-medium text-break' }, v ?? '-'))))) : null);
  }

  /** rows: [{ who, when, what, note }] */
  static history(rows, { title = 'Activity Log', search = true } = {}) {
    let q = '';
    const body = h('tbody');
    const draw = () => {
      const list = rows.filter((r) => !q || `${r.who} ${r.what} ${r.note ?? ''} ${r.when}`.toLowerCase().includes(q));
      body.replaceChildren(...(list.length ? list.map((r) => h('tr', {},
        h('td', {}, h('div', { class: 'ex-person' }, h('span', { class: 'ex-avatar' }, fmt.initials(r.who || 'S Y')), h('span', {}, r.who || 'System'))),
        h('td', { class: 'text-nowrap' }, r.when),
        h('td', {}, r.what),
        h('td', {}, r.note ?? ''))) : [h('tr', {}, h('td', { colspan: '4', class: 'text-center text-14 sub-text py-4' }, rows.length ? 'Nothing matches your search.' : 'Nothing has happened yet.'))]));
    };
    draw();
    return h('div', { class: 'component-theme p-4' },
      h('div', { class: 'd-flex flex-wrap align-items-center gap-3 mb-3' },
        h('span', { class: 'sub-topic me-auto' }, title),
        h('span', { class: 'ex-count-pill' }, `${rows.length} ${rows.length === 1 ? 'Entry' : 'Entries'}`),
        search ? h('div', { class: 'position-relative', style: { minWidth: '260px' } }, h('span', { class: 'position-absolute top-50 translate-middle-y sub-text', style: { left: '14px' } }, icon('search', { size: 18 })),
          h('input', { type: 'search', class: 'form-control custom-input ex-h48 ps-5', placeholder: 'Search the history', 'aria-label': 'Search the history', oninput: debounce((e) => { q = e.target.value.trim().toLowerCase(); draw(); }, 200) })) : null),
      h('div', { class: 'table-responsive' },
        h('table', { class: 'table ex-v3-table align-middle m-0 text-14' },
          h('thead', {}, h('tr', {}, h('th', { scope: 'col' }, 'User'), h('th', { scope: 'col' }, 'Date'), h('th', { scope: 'col' }, 'Action'), h('th', { scope: 'col' }, 'Remark'))),
          body)));
  }

  static loading() {
    return h('div', { class: 'row g-4 placeholder-glow' },
      h('div', { class: 'col-12 col-xl-4' }, h('span', { class: 'placeholder col-12', style: { height: '520px' } })),
      h('div', { class: 'col-12 col-xl-8' }, h('span', { class: 'placeholder col-12 mb-4', style: { height: '140px' } }), h('span', { class: 'placeholder col-12', style: { height: '360px' } })));
  }

  static notFound(text, [label, href]) {
    return h('div', { class: 'component-theme text-center py-5' },
      h('p', { class: 'main-text mb-1' }, text),
      h('a', { class: 'custom-btn ex-btn-md px-4 text-decoration-none mt-3 d-inline-flex align-items-center', href }, label));
  }
}
