// Tutor → Lecture Details (tutor/lecture-details.php, printed by PHP): moves the header buttons into the page header
// and wires the copy buttons (online link, Zoom passcode).
import { QuickGuide } from '../components/QuickGuide.js';
import { Toast } from '../components/Toast.js';

QuickGuide.attachAll();
const src = document.getElementById('ld-actions-src');
const target = document.getElementById('ld-actions');
if (src && target) { target.replaceChildren(...src.children); src.remove(); }
document.querySelectorAll('.ld-copy').forEach((b) => b.addEventListener('click', async () => {
  try { await navigator.clipboard.writeText(b.dataset.copy); Toast.success('Copied.'); } catch { Toast.error('Could not copy. Select the text and copy it.'); }
}));
