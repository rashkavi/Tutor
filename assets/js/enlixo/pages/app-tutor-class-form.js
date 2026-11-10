// Tutor → Settings → Class Form (tutor/class-form-settings.php). The page itself is the shared FormSettings component.
import { mountFormSettings } from '../components/FormSettings.js';

mountFormSettings({
  root: document.getElementById('form-settings-root'),
  actions: document.getElementById('form-settings-actions'),
  endpoint: '/tutor/class-form',
  openHref: 'create-class',
  formName: 'Create a Class form',
  savedWith: 'each class',
  lists: true,
  sectionIcons: { basics: 'classes', running: 'timetable', fee: 'money', enrolment: 'students', custom: 'integrations' },
  sectionSubs: {
    basics: 'What you teach and for which exam, the class name, description and cover.',
    running: 'How and when the class runs: physical or online, hall, weekly times and Zoom.',
    fee: 'The monthly fee and the day it is due.',
    enrolment: 'Who can join the class and how many students.',
    custom: 'Your own fields that don\'t fit the sections above.',
  },
});
