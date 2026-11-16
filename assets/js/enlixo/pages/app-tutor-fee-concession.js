// Tutor → Free Card / Discount page (tutor/fee-concession.php?student-id=): the classes come from the Fee Card data.
import { QuickGuide } from '../components/QuickGuide.js';
import { DetailPage } from '../components/DetailPage.js';
import { api, has, gate, mountConcession, fill } from './fees-common.js';

QuickGuide.attachAll();
const root = document.getElementById('cc-page');
const id = Number(new URLSearchParams(location.search).get('student-id') || 0);
fill(root, DetailPage.loading());
(async () => {
  if (!has('fees.advanced')) { gate.openUpgrade('fees.advanced'); fill(root, DetailPage.notFound('Free cards and discounts are part of the GROW plan.', ['Back to the Fee Card', `student-fees?student-id=${id}`])); return; }
  try {
    const { data: D } = await api.get(`/tutor/students/${id}/fees`);
    mountConcession(root, document.getElementById('cc-actions'), { student: D.student, classes: D.classes });
  } catch (e) {
    fill(root, DetailPage.notFound(e.status === 404 ? 'This student was not found.' : `Couldn't load the student: ${e.message}`, ['Back to Student Fees', 'fees']));
  }
})();
