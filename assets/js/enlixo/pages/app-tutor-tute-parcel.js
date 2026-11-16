// Tutor → one tute parcel page (tutor/tute-parcel.php): the form that was the Edit pop-up of Tute Deliveries.
import { h, fill } from '../core/dom.js';
import { ApiClient } from '../core/ApiClient.js';
import { QuickGuide } from '../components/QuickGuide.js';
import { DetailPage } from '../components/DetailPage.js';
import { Toast } from '../components/Toast.js';
import { icon } from '../core/icons.js';

QuickGuide.attachAll();
const api = new ApiClient();
const root = document.getElementById('parcel-page');
const actions = document.getElementById('parcel-actions');
const q = new URLSearchParams(location.search);
const classId = Number(q.get('class-id') || 0);
const tuteId = Number(q.get('tute-id') || 0);
const deliveryId = Number(q.get('delivery-id') || 0);
const presetStep = q.get('step');
const back = `couriered-tute?class-id=${classId}&tute-id=${tuteId}`;

fill(root, DetailPage.loading());
(async () => {
  let data;
  try { data = (await api.get(`/tutor/tutes/${tuteId}/deliveries`)).data; } catch (e) { fill(root, DetailPage.notFound(`Couldn't load the parcel: ${e.message}`, ['Back to Tute Deliveries', back])); return; }
  const d = data.deliveries.find((x) => Number(x.id) === deliveryId);
  if (!d) { fill(root, DetailPage.notFound('This parcel was not found.', ['Back to Tute Deliveries', back])); return; }
  draw(data, d);
})();

function draw(data, d) {
  document.title = `${d.name} · Tute Parcel - Tutor`;
  const f = (key, labelText, value, attrs = {}) => {
    const input = h('input', { class: 'form-control custom-input', value: value ?? '', ...attrs });
    return { key, input, el: h('div', { class: attrs.col ?? 'col-12 col-md-6' }, h('label', { class: 'text-14 main-text mb-2' }, labelText), input, h('div', { class: 'text-12 text-dark-red mt-1', 'data-err': key })) };
  };
  const step = h('select', { class: 'form-select custom-select', 'aria-label': 'Step' }, Object.entries(data.steps).map(([k, v]) => h('option', { value: k, selected: (presetStep ?? d.status) === k ? true : null }, v)));
  const courier = h('select', { class: 'form-select custom-select', 'aria-label': 'Courier' }, h('option', { value: '' }, '- None -'),
    [...new Set([...data.couriers, d.courier_name].filter(Boolean))].map((c) => h('option', { value: c, selected: (d.courier_name || data.tute.courier_name) === c ? true : null }, c)));
  const fields = [
    f('recipient_name', 'Name on the parcel', d.name, { maxlength: '150', placeholder: 'Enter the name to write on the parcel' }),
    f('phone', 'Phone', d.phone, { maxlength: '20', placeholder: 'Enter a phone number, for example 0771234567' }),
    f('address_line_1', 'Address line 1', d.address_line_1, { maxlength: '100', col: 'col-12', placeholder: 'Enter the house number and street' }),
    f('address_line_2', 'Address line 2', d.address_line_2, { maxlength: '100', col: 'col-12', placeholder: 'Enter the area (optional)' }),
    f('city', 'Town', d.city, { maxlength: '45', placeholder: 'Enter the town, for example Maharagama' }),
    f('postal_code', 'Postal code', d.postal_code, { maxlength: '10', placeholder: 'Enter the postal code, for example 10280' }),
    f('tracking_no', 'Tracking number', d.tracking_no, { maxlength: '40', placeholder: 'e.g. BA123456789LK' }),
    f('note', 'Note (only you see it)', d.note, { maxlength: '255', placeholder: 'Add a note for yourself' }),
  ];
  const byKey = Object.fromEntries(fields.map((x) => [x.key, x]));
  const form = h('div', {});
  const save = async (btn) => {
    form.querySelectorAll('[data-err]').forEach((el) => { el.textContent = ''; });
    const payload = { status: step.value, courier_name: courier.value };
    fields.forEach((x) => { payload[x.key] = x.input.value; });
    btn.disabled = true;
    try {
      await api.post(`/tutor/tutes/${tuteId}/deliveries`, { ids: [d.id], ...payload });
      Toast.success('Parcel saved.');
      setTimeout(() => { location.href = back; }, 700);
    } catch (e) {
      Object.entries(e.details ?? {}).forEach(([k, msg]) => { const el = form.querySelector(`[data-err="${k}"]`); if (el) el.textContent = msg; });
      Toast.error(e.message);
      btn.disabled = false;
    }
  };
  const saveBtn = (cls) => h('button', { type: 'button', class: `custom-btn ${cls}`, 'data-save': '1', onclick: (e) => save(e.currentTarget) }, 'Save');
  fill(actions, h('a', { class: 'custom-outline-btn ex-btn-lg px-4 text-decoration-none', href: back }, 'Cancel'), saveBtn('ex-btn-lg px-5'));
  fill(form,
    h('div', { class: 'row gx-3 gy-3' },
      h('div', { class: 'col-12 col-md-6' }, h('label', { class: 'text-14 main-text mb-2' }, 'Step'), step),
      h('div', { class: 'col-12 col-md-6' }, h('label', { class: 'text-14 main-text mb-2' }, 'Courier'), courier),
      byKey.tracking_no.el, byKey.note.el),
    h('div', { class: 'sub-topic mt-4 mb-3' }, 'Address'),
    h('div', { class: 'row gx-3 gy-3' }, ['recipient_name', 'phone', 'address_line_1', 'address_line_2', 'city', 'postal_code'].map((k) => byKey[k].el)),
    h('div', { class: 'text-12 sub-text mt-3' }, 'This changes the address on this parcel only. The student can update their own address in My Profile.'),
    h('div', { class: 'd-flex justify-content-end mt-4' }, saveBtn('px-5')));
  fill(root, h('div', { class: 'component-theme p-4' },
    h('div', { class: 'd-flex align-items-center gap-3 mb-4' }, h('span', { class: 'ex-empty-ico m-0' }, icon('location', { size: 22 })),
      h('div', {}, h('div', { class: 'text-18 main-text fw-semibold' }, d.name), h('div', { class: 'text-13 sub-text' }, `${data.tute.title}${presetStep === 'dispatched' ? ' · add the courier and tracking number, then save' : ''}`))),
    form));
  if (presetStep === 'dispatched') setTimeout(() => byKey.tracking_no.input.focus(), 200);
}
