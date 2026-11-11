// Student ID card (GROW: 'id.cards'). The code on the card follows Attendance Settings → Ways to Mark:
// QR Code on → a QR code, Barcode on → a barcode (both when both are on). Both hold the Student ID, which is also
// printed under the code so a card that does not scan can be typed. The card is a printed object, so its colours are
// fixed (white card, blue band) in light and dark theme; the same CSS is used on the page and in the print window.
import qrcode from '../../../plugins/qrcode-generator/qrcode.mjs';
import { h } from './fees-common.js';

export const ID_CARD_CSS = `
.idc { width: 340px; aspect-ratio: 85.6 / 54; background: #fff; color: #101828; border-radius: 12px; overflow: hidden;
  box-shadow: 0 2px 12px rgba(15, 23, 42, .12); display: flex; flex-direction: column; font-family: inherit; position: relative; }
.idc-band { background: #0061D2; color: #fff; padding: 8px 14px; display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.idc-tutor { font-size: 13px; font-weight: 700; line-height: 1.15; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.idc-subject { font-size: 10px; opacity: .85; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.idc-kind { font-size: 9px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; background: rgba(255, 255, 255, .18); padding: 3px 7px; border-radius: 4px; white-space: nowrap; }
.idc-body { flex: 1; display: flex; gap: 12px; padding: 10px 14px 6px; min-height: 0; }
.idc-info { flex: 1; min-width: 0; display: flex; flex-direction: column; justify-content: center; gap: 3px; }
.idc-avatar { width: 46px; height: 46px; border-radius: 50%; background: #E6F0FB; color: #0061D2; display: inline-flex; flex-shrink: 0; align-self: flex-start; align-items: center; justify-content: center; font-size: 17px; font-weight: 700; margin-bottom: 2px; }
.idc-name { font-size: 15px; font-weight: 700; line-height: 1.2; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.idc-sid { font-size: 12px; font-weight: 600; color: #0061D2; letter-spacing: .03em; }
.idc-classes { font-size: 10px; color: #667085; line-height: 1.3; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.idc-qr { width: 96px; align-self: center; flex-shrink: 0; display: flex; flex-direction: column; align-items: center; gap: 2px; }
.idc-qr svg { width: 96px; height: 96px; display: block; }
.idc-bar { padding: 0 14px 6px; display: flex; flex-direction: column; align-items: center; }
.idc-bar svg { width: 100%; height: 30px; display: block; }
.idc-code-text { font-size: 9px; color: #667085; letter-spacing: .08em; }
.idc-has-bar .idc-qr { width: 78px; } .idc-has-bar .idc-qr svg { width: 78px; height: 78px; }
.idc-has-bar .idc-body { padding-bottom: 4px; }
.idc-foot { font-size: 9px; color: #667085; padding: 0 14px 7px; display: flex; justify-content: space-between; gap: 8px; }
.idc-sample::after { content: 'SAMPLE'; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-18deg);
  font-size: 46px; font-weight: 800; letter-spacing: .12em; color: rgba(16, 24, 40, .08); pointer-events: none; }
.idc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 16px; justify-items: center; }
`;

// Inject once on the page (the print window gets the same text).
if (typeof document !== 'undefined' && !document.getElementById('idc-css')) {
  document.head.append(Object.assign(document.createElement('style'), { id: 'idc-css', textContent: ID_CARD_CSS }));
}

const svgNode = (markup) => { const t = document.createElement('template'); t.innerHTML = markup.trim(); return t.content.firstElementChild; };

function qrSvg(text) {
  const q = qrcode(0, 'M');
  q.addData(text);
  q.make();
  return svgNode(q.createSvgTag({ cellSize: 4, margin: 0, scalable: true, title: `Student ID ${text}` }));
}

// JsBarcode (MIT, CODE128 only) is loaded on first use.
let barcodeLib = null;
export function loadBarcode() {
  if (window.JsBarcode) return Promise.resolve(window.JsBarcode);
  barcodeLib ??= new Promise((resolve, reject) => {
    const s = document.createElement('script');
    s.src = new URL('../../../plugins/jsbarcode/JsBarcode.code128.min.js', import.meta.url).href;
    s.onload = () => resolve(window.JsBarcode);
    s.onerror = () => { barcodeLib = null; reject(new Error('The barcode could not be drawn. Check the connection and try again.')); };
    document.head.append(s);
  });
  return barcodeLib;
}

function barSvg(text) {
  const el = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  window.JsBarcode(el, text, { format: 'CODE128', displayValue: false, margin: 0, height: 40, width: 2 });
  el.setAttribute('preserveAspectRatio', 'none');
  return el;
}

const initials = (name) => name.split(/\s+/).filter(Boolean).slice(0, 2).map((x) => x[0]).join('').toUpperCase();

/**
 * One card. student = { sid, name, classes: [] }, tutor = { name, subject, phone }, methods = { qr, barcode } (on / off).
 * Call loadBarcode() first when methods.barcode is on. sample = true draws the "SAMPLE" watermark (START plan).
 */
export function idCard(student, tutor, methods, { sample = false } = {}) {
  const qr = !!methods.qr;
  const bar = !!methods.barcode && !!window.JsBarcode;
  return h('div', { class: `idc${bar ? ' idc-has-bar' : ''}${sample ? ' idc-sample' : ''}` },
    h('div', { class: 'idc-band' },
      h('div', { class: 'min-w-0' }, h('div', { class: 'idc-tutor' }, tutor.name), h('div', { class: 'idc-subject' }, tutor.subject)),
      h('span', { class: 'idc-kind' }, 'Student ID')),
    h('div', { class: 'idc-body' },
      h('div', { class: 'idc-info' },
        bar ? null : h('span', { class: 'idc-avatar' }, initials(student.name)),
        h('div', { class: 'idc-name' }, student.name),
        h('div', { class: 'idc-sid' }, student.sid),
        h('div', { class: 'idc-classes' }, (student.classes ?? []).join(' · '))),
      qr ? h('div', { class: 'idc-qr' }, qrSvg(student.sid), bar ? null : h('span', { class: 'idc-code-text' }, student.sid)) : null),
    bar ? h('div', { class: 'idc-bar' }, barSvg(student.sid), h('span', { class: 'idc-code-text' }, student.sid)) : null,
    h('div', { class: 'idc-foot' }, h('span', {}, qr || bar ? 'Scan at the class to mark attendance' : 'Show this card at the class'), h('span', {}, tutor.phone ?? '')));
}

/** Print the cards on A4 in a new window (8 cards a page, cut lines from the gaps). */
export function printCards(cards, title = 'Student ID Cards') {
  const w = window.open('', '_blank');
  if (!w) return false;
  w.document.write(`<!DOCTYPE html><html><head><meta charset="utf-8"><title>${title.replace(/</g, '&lt;')}</title><style>
    ${ID_CARD_CSS}
    @page { size: A4; margin: 10mm; }
    body { margin: 0; font-family: 'Poppins', 'Segoe UI', Arial, sans-serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .sheet { display: grid; grid-template-columns: repeat(2, 85.6mm); gap: 6mm 8mm; justify-content: center; }
    .idc { width: 85.6mm; box-shadow: none; border: 0.2mm dashed #c0c7d2; border-radius: 3mm; break-inside: avoid; }
  </style></head><body><div class="sheet"></div></body></html>`);
  w.document.close();
  const sheet = w.document.querySelector('.sheet');
  cards.forEach((c) => sheet.append(w.document.importNode(c, true)));
  setTimeout(() => { w.focus(); w.print(); }, 300);
  return true;
}
