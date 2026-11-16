// Tutor → a receipt page (tutor/fee-receipt.php?receipt-id=).
import { QuickGuide } from '../components/QuickGuide.js';
import { DetailPage } from '../components/DetailPage.js';
import { mountReceipt, fill } from './fees-common.js';

QuickGuide.attachAll();
const root = document.getElementById('rc-page');
const id = Number(new URLSearchParams(location.search).get('receipt-id') || 0);
fill(root, DetailPage.loading());
mountReceipt(root, document.getElementById('rc-actions'), id)
  .catch((e) => fill(root, DetailPage.notFound(e.status === 404 ? 'This receipt was not found.' : `Couldn't load the receipt: ${e.message}`, ['Back to Student Fees', 'fees'])));
