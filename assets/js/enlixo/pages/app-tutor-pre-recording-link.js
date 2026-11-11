// tutor/add-pre-recorded-class.php and tutor/edit-pre-recording.php: "Where is the video?" of a pre-recorded class.
// Same recording types and link check as Class Recordings (Settings → Recording Types), plus the passcode and
// "Opens on". The legacy form (addPreRecording / editPreRecording in tutor/js/functions.js) reads the values
// through window.PreRecordingLink and the API checks them again (src/Recordings/PreRecordingLink.php).
import { api, h, fill, icon, field, linkPicker, platformTile, showFieldErrors, clearOnEdit } from './recordings-common.js';

const mount = document.getElementById('rec-link-field');

if (mount) {
  const d = mount.dataset;
  let types = [];
  try {
    ({ data: { types } } = await api.get('/tutor/recording-types'));
  } catch (e) {
    fill(mount, h('div', { class: 'rc-result bad' }, icon('danger', { size: 20 }), h('span', {}, e.message || 'Recording types could not be loaded. Reload the page.')));
  }
  types = types.filter((t) => t.active || String(t.id) === d.type);

  // an old row stored a YouTube iframe: start with YouTube and that link
  let startType = d.type && d.type !== '0' ? d.type : '';
  let startLink = d.link || '';
  if (!startLink && d.oldLink) {
    startLink = d.oldLink;
    startType = types.find((t) => t.platform_key === 'youtube')?.id ?? '';
  }
  const picker = linkPicker(types, { type: startType || (types[0]?.id ?? ''), link: startLink, passcode: d.passcode || '' }, {
    onVideo: (info) => {
      const title = document.getElementById('title');
      const duration = document.getElementById('duration');
      if (title && !title.value.trim() && info.title) title.value = info.title;
      if (duration && !String(duration.value).trim() && info.minutes) duration.value = info.minutes;
    },
  });
  const unlock = h('input', {
    class: 'form-control custom-input', type: 'datetime-local', 'data-key': 'unlock_at', value: d.unlock || '',
    placeholder: 'Choose the day and time students can start watching',
  });

  // an old uploaded video (Bunny upload) stays until the tutor chooses to use a link instead
  let keepVideo = d.upload === '1';
  const where = h('div', {});
  const drawWhere = () => fill(where, keepVideo
    ? h('div', { class: 'ad-soft p-3 d-flex gap-3 align-items-start mb-3' }, platformTile('upload'), h('div', { class: 'flex-grow-1' },
      h('div', { class: 'text-14 main-text fw-semibold' }, 'Uploaded video'),
      h('div', { class: 'text-12 sub-text' }, 'This video was uploaded before and stays as it is.'),
      h('button', { type: 'button', class: 'btn btn-link p-0 text-12', onclick: () => { keepVideo = false; drawWhere(); } }, 'Use a recording link instead')))
    : picker.el);
  drawWhere();

  const body = h('div', { class: 'rc-pre-link' },
    where,
    h('div', { class: 'row gx-3' }, h('div', { class: 'col-md-6' },
      field('Opens on', unlock, { info: 'Students see this pre-recorded class from this day and time. Leave it empty to show it at once.' }))));
  if (types.length || keepVideo) fill(mount, body);
  clearOnEdit(body);

  window.PreRecordingLink = {
    /** Adds the fields to the FormData the legacy form posts. */
    append(form) {
      const v = picker.values();
      form.append('rec_type', keepVideo ? '0' : String(v.type));
      form.append('link', keepVideo ? '' : v.link);
      form.append('passcode', v.passcode);
      form.append('unlock_at', unlock.value);
      form.append('keep_video', keepVideo ? '1' : '0');
    },
    /** Red lines under the fields from the API answer ({ type, link, passcode, unlock_at }). */
    showErrors(res) {
      const map = {};
      ['type', 'link', 'passcode', 'unlock_at'].forEach((k) => { if (res?.[k]) map[k] = res[k]; });
      showFieldErrors(body, map);
    },
  };
}
