// Tutor → Settings → Student Form (tutor/student-form-settings.php). The same form is used by the
// application link (apply?t=...) and Add New Student, so one change shows on both.
import { mountFormSettings } from '../components/FormSettings.js';

mountFormSettings({
  root: document.getElementById('form-settings-root'),
  actions: document.getElementById('form-settings-actions'),
  endpoint: '/tutor/student-form',
  openHref: 'add-student',
  formName: 'student form',
  savedWith: 'each student',
  sectionIcons: { class: 'classes', personal: 'user', contact: 'communication', guardian: 'students', custom: 'integrations' },
  sectionSubs: {
    class: 'The class the student wants to join.',
    personal: 'Photo, name, gender, date of birth, NIC and school.',
    contact: 'WhatsApp, mobile, e-mail and home address.',
    guardian: 'A parent or guardian. Students fill it in completely or leave it empty.',
    custom: 'Your own questions that don\'t fit the sections above.',
  },
});
