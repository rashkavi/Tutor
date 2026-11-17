// The Add / Edit Recording form (tutor/add-recording.php). It used to be a pop-up on Class Recordings; it is a page now.
//   const f = recordingForm({ O, rec, cls: '12', day: '345' });  // O = GET /tutor/recordings/overview
//   card.append(f.el);  await f.save();  // POST /tutor/recordings or PUT /tutor/recordings/{id}; field errors drawn under the fields
import {
  api, h, fill, Toast, infoTip, field, plural, platformTile, dayText, ampm, lockBadge, showFieldErrors, clearOnEdit, linkPicker,
} from './recordings-common.js';

export function recordingForm({ O, rec = null, cls: startClass = '', day: startDay = '' }) {
  const types = O.types.filter((t) => t.active || t.id === rec?.type.id);
  const v = {
    class: rec?.class.id ?? startClass ?? '', day: rec?.day.id ?? startDay ?? '', title: rec?.title ?? '', description: rec?.description ?? '',
    type: rec?.type.id ?? types[0]?.id ?? '', link: rec?.url ?? '', passcode: rec?.passcode ?? '', duration: rec?.duration ?? '', unlock_at: rec?.unlock_at ?? '', expire_date: rec?.expire_date ?? '',
  };
  if (rec?.type.old && rec.type.key === 'youtube') v.type = types.find((t) => t.platform_key === 'youtube')?.id ?? v.type;
  const keepUpload = rec?.type.key === 'upload';
  const canExpire = O.can_expire;

  const inp = (key, attrs = {}) => h('input', { class: 'form-control custom-input', 'data-key': key, value: v[key], ...attrs });
  const cls = h('select', { class: 'form-select custom-select', 'data-key': 'class' }, h('option', { value: '', disabled: true, selected: !v.class }, '- Select -'),
    O.classes.map((c) => h('option', { value: c.id, selected: String(c.id) === String(v.class) }, c.active ? c.name : `${c.name} (inactive)`)));
  const day = h('select', { class: 'form-select custom-select', 'data-key': 'day', disabled: true }, h('option', { value: '' }, '- Select -'));
  const dayHint = h('div', { class: 'text-12 sub-text mt-n2 mb-3' });
  let days = [];
  const title = inp('title', { placeholder: 'Enter a title, for example Week 4: Integration', 'data-limit': '100', maxlength: 100 });
  const desc = h('textarea', { class: 'form-control custom-input', 'data-key': 'description', rows: 3, placeholder: 'Enter what the class covered, for example the lessons and page numbers', 'data-limit': '1000', maxlength: 1000 }, v.description);
  const duration = inp('duration', { type: 'number', min: 1, max: 900, placeholder: 'Enter the length in minutes, for example 120' });
  const unlock = inp('unlock_at', { type: 'datetime-local', placeholder: 'Choose the day and time students can start watching' });
  const expire = inp('expire_date', { type: 'date', placeholder: 'Choose the last day students can watch', disabled: !canExpire && !v.expire_date, min: new Date().toISOString().slice(0, 10) });

  // class days of the chosen class
  const drawDays = async (keep) => {
    day.disabled = true;
    fill(dayHint);
    if (!cls.value) { fill(day, h('option', { value: '' }, 'Choose a class first')); return; }
    fill(day, h('option', { value: '' }, 'Loading class days…'));
    try { ({ data: days } = await api.get('/tutor/recordings/days', { class: cls.value })); } catch (e) { days = []; Toast.error(e.message); }
    if (!days.length) {
      fill(day, h('option', { value: '' }, 'No class days yet'));
      fill(dayHint, 'This class has no class day that has started. Add class days on the Time Table first.');
      return;
    }
    fill(day, h('option', { value: '', disabled: true, selected: !keep }, '- Select -'),
      days.map((d) => h('option', { value: d.id, selected: String(d.id) === String(keep) },
        `${dayText(d.date)} · ${ampm(d.start)}${d.title ? ` · ${d.title}` : ''}${d.recordings && String(d.id) !== String(rec?.day.id) ? ` (has ${plural(d.recordings, 'recording')})` : ''}`)));
    day.disabled = false;
    drawDayHint();
  };
  const drawDayHint = () => {
    const d = days.find((x) => String(x.id) === day.value);
    fill(dayHint, d ? `${d.title || 'Class day'} · ${ampm(d.start)} - ${ampm(d.end)}` : '');
    if (d && !title.value.trim() && d.title) title.value = d.title;
  };
  cls.addEventListener('change', () => drawDays(''));
  day.addEventListener('change', drawDayHint);

  // where the video is (shared with the pre-recorded class form)
  const picker = linkPicker(types, v, {
    onVideo: (info) => {
      if (!title.value.trim() && info.title) title.value = info.title;
      if (!String(duration.value).trim() && info.minutes) duration.value = info.minutes;
    },
  });

  const sec = (text, info) => h('div', { class: 'sub-topic mb-3 d-flex align-items-center gap-2' }, text, info ? infoTip(info, text) : null);
  const where = keepUpload
    ? h('div', { class: 'ad-soft p-3 d-flex gap-3 align-items-start mb-3' }, platformTile('upload'), h('div', {},
      h('div', { class: 'text-14 main-text fw-semibold' }, 'Uploaded video'),
      h('div', { class: 'text-12 sub-text' }, 'This video was uploaded before and stays as it is. You can change the details below.')))
    : picker.el;

  const card = (...kids) => h('div', { class: 'col-12 component-theme p-4 mb-4' }, ...kids);
  const el = h('div', {},
    card(sec('Class day', 'The class and the day this recording is from. Students of that class see it in the app.'),
      h('div', { class: 'row gx-3' },
        h('div', { class: 'col-md-6' }, field('Class', cls, { required: true, info: 'The class this video belongs to. Only its students can watch it.' })),
        h('div', { class: 'col-md-6' }, field('Class day', day, { required: true, info: 'Days of this class that have already started, newest first. Add class days on the Time Table.' }), dayHint))),
    card(sec('Recording', 'Where the video is kept and what students see in the list.'),
      field('Title', title, { required: true, info: 'What students see in the list, for example "Week 4: Integration". Up to 100 characters.' }),
      where,
      h('div', { class: 'row gx-3' },
        h('div', { class: 'col-md-6' }, field('Length (minutes)', duration, { required: true, info: 'How long the video is, in minutes. For example 120 for a two-hour class. Watch-time limits are counted from this.' }))),
      field('Description', desc, { info: 'Anything that helps students, for example the lessons covered or the tute pages. Up to 1000 characters.' })),
    card(sec('When students can watch', 'Leave both empty to show the recording at once, with no end date.'),
      h('div', { class: 'row gx-3' },
        h('div', { class: 'col-md-6' }, field('Opens on', unlock, { info: 'Students see the recording from this day and time, for example the day after the class. Empty = at once.' })),
        h('div', { class: 'col-md-6' }, h('div', { class: 'position-relative' }, field('Available until', expire, { info: canExpire ? 'The last day students can watch. After it the recording closes for them. Empty = no end date.' : 'Closing a recording on a date is on the GROW plan.' }),
          canExpire ? null : h('span', { class: 'position-absolute top-0 end-0' }, lockBadge('recordings.expiry')))))));

  clearOnEdit(el);
  drawDays(v.day);

  async function save() {
    const payload = {
      class: Number(cls.value) || 0, day: Number(day.value) || 0, title: title.value, description: desc.value, duration: duration.value,
      ...picker.values(), unlock_at: unlock.value, expire_date: expire.value,
    };
    try {
      await (rec ? api.put(`/tutor/recordings/${rec.id}`, payload) : api.post('/tutor/recordings', payload));
      return { ok: true, class: payload.class };
    } catch (err) {
      const map = { ...(err.details ?? {}) };
      showFieldErrors(el, map);
      if (!Object.keys(map).length) Toast.error(err.message);
      else Toast.error('Please check the highlighted fields.');
      el.querySelector('.is-invalid')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return { ok: false };
    }
  }

  return { el, save, noTypes: !types.length && !keepUpload };
}
