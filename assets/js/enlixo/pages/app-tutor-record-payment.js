// Tutor → Record a Payment page (tutor/record-payment.php): the form of fees-common.js on its own page.
import { QuickGuide } from '../components/QuickGuide.js';
import { mountRecordPayment } from './fees-common.js';

QuickGuide.attachAll();
const q = new URLSearchParams(location.search);
mountRecordPayment(document.getElementById('rp-page'), document.getElementById('rp-actions'), {
  student: Number(q.get('student-id') || 0) || null,
  select: (q.get('months') || '').split(',').map(Number).filter(Boolean),
});
