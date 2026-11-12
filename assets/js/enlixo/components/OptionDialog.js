import { h } from '../core/dom.js';
import { BsModal } from './BsModal.js';
import { Toast } from './Toast.js';

export const OWN_LISTS = {
  exam_types: { one: 'exam type', title: 'Exam types', example: 'e.g. Grade 9 Term Test', max: 45 },
  subjects: { one: 'subject', title: 'Subjects', example: 'e.g. Combined Mathematics', max: 150 },
  mediums: { one: 'medium', title: 'Mediums', example: 'e.g. Sinhala & English', max: 20 },
};

/**
 * Add (or rename) one of the tutor's own exam types / subjects / mediums.
 * Only this tutor sees it; the default options stay the same for everyone.
 *
 *   const added = await OptionDialog.open({ api, list: 'subjects' });          // {value,label} or null
 *   const renamed = await OptionDialog.open({ api, list, option: {value,label} });
 */
export class OptionDialog {
  static open({ api, list, option = null }) {
    const meta = OWN_LISTS[list];
    return new Promise((resolve) => {
      let result = null;
      const id = `opt-${list}`;
      const input = h('input', { id, class: 'form-control custom-input ex-h48 mt-2', maxlength: String(meta.max), placeholder: meta.example, value: option?.label ?? '', autocomplete: 'off',
        onkeydown: (e) => { if (e.key === 'Enter') { e.preventDefault(); save.click(); } }, oninput: () => { input.classList.remove('is-invalid'); err.textContent = ''; } });
      const err = h('span', { class: 'text-dark-red text-12', role: 'alert' });
      const save = BsModal.button(option ? 'Save' : 'Add', { primary: true, onclick: async () => {
        save.disabled = true;
        try {
          const label = input.value.trim();
          const { data } = option
            ? await api.put(`/tutor/class-form/options/${list}/${encodeURIComponent(option.value)}`, { label })
            : await api.post(`/tutor/class-form/options/${list}`, { label });
          result = data;
          m.close();
        } catch (e) {
          err.textContent = e.details?.fields?.label ?? e.message;
          if (!e.details?.fields?.label) Toast.error(e.message);
          input.classList.add('is-invalid');
          input.focus();
        } finally { save.disabled = false; }
      } });
      const m = BsModal.open({
        title: option ? `Rename ${meta.one}` : `Add your own ${meta.one}`,
        icon: option ? 'edit' : 'plus',
        subtitle: 'Only you see it. The default list stays the same for every tutor.',
        body: h('div', { class: 'd-flex flex-column' },
          h('label', { class: 'text-14 main-text required', for: id }, `${meta.one[0].toUpperCase()}${meta.one.slice(1)} name`),
          input, err),
        footerEnd: [BsModal.button('Cancel', { onclick: () => m.close() }), save],
        onClose: () => resolve(result),
      });
      m.el.addEventListener('shown.bs.modal', () => input.focus(), { once: true });
    });
  }
}
