import { h, fill } from '../core/dom.js';
import { BsModal } from './BsModal.js';

const SPARK = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l1.9 5.6L19.5 9.5l-5.6 1.9L12 17l-1.9-5.6L4.5 9.5l5.6-1.9L12 2Zm7 12 .9 2.6 2.6.9-2.6.9L19 21l-.9-2.6-2.6-.9 2.6-.9L19 14Z"/></svg>';
const STYLES = [
  { key: 'bold', name: 'Bold poster', hint: 'Dark blue, big headline', swatch: 'linear-gradient(135deg,#0b1f44,#0061D2)' },
  { key: 'photo', name: 'Photo style', hint: 'A study scene for the subject', swatch: 'linear-gradient(135deg,#6b8f71,#d7b377)' },
  { key: 'minimal', name: 'Minimal', hint: 'Light, simple icons', swatch: 'linear-gradient(135deg,#f4f6fb,#cfd8ea)' },
];

/**
 * "Generate thumbnail": makes a 16:9 class cover from the details on the form.
 * With AI set up on the server it asks the image model (POST /tutor/classes/thumbnail);
 * otherwise, or if the tutor prefers, it draws a clean designed cover in the browser.
 *
 *   const dataUrl = await ThumbnailMaker.open({ api, details, labels, aiReady });  // string or null
 */
export class ThumbnailMaker {
  static open({ api, details, labels, aiReady }) {
    return new Promise((resolve) => {
      let style = 'bold';
      let image = null;
      let busy = false;
      const stage = h('div', { class: 'ex-thumb-stage', 'aria-live': 'polite' });
      const error = h('p', { class: 'text-12 text-dark-red m-0 mt-2', role: 'alert' });
      const note = h('p', { class: 'text-12 sub-text m-0 mt-2' });

      const empty = () => fill(stage, h('div', { class: 'ex-thumb-empty' },
        h('span', { class: 'ex-thumb-spark', html: SPARK, 'aria-hidden': 'true' }),
        h('span', { class: 'text-14 main-text' }, 'Your thumbnail will show here'),
        h('span', { class: 'text-12 sub-text' }, aiReady ? 'Pick a style and press Generate.' : 'Pick a style and press Make cover.')));
      const loading = () => fill(stage, h('div', { class: 'ex-thumb-empty' },
        h('span', { class: 'spinner-border text-primary', role: 'status', 'aria-hidden': 'true' }),
        h('span', { class: 'text-14 main-text' }, 'Making your thumbnail…'),
        h('span', { class: 'text-12 sub-text' }, 'This takes about 10 to 30 seconds.')));
      empty();

      const chips = [labels.subject, [labels.year, labels.exam].filter(Boolean).join(' '), labels.type, labels.medium].filter(Boolean);
      const styleCards = h('div', { class: 'row g-2', role: 'radiogroup', 'aria-label': 'Style' }, STYLES.map((s) => h('div', { class: 'col-12 col-md-4' },
        h('label', { class: 'ex-choice bl-solid d-flex gap-2 align-items-center p-2 h-100' },
          h('input', { class: 'form-check-input mt-0 flex-none', type: 'radio', name: 'thumb-style', value: s.key, checked: s.key === style, onchange: () => { style = s.key; } }),
          h('span', { class: 'ex-thumb-swatch flex-none', style: { background: s.swatch }, 'aria-hidden': 'true' }),
          h('span', {}, h('span', { class: 'd-block text-14 main-text' }, s.name), h('span', { class: 'd-block text-12 sub-text' }, s.hint))))));

      const run = async (useAi) => {
        if (busy) return;
        busy = true;
        error.textContent = '';
        note.textContent = '';
        go.disabled = true;
        simple.disabled = true;
        use.classList.add('d-none');
        loading();
        try {
          if (useAi) {
            const { data } = await api.post('/tutor/classes/thumbnail', { ...details, style });
            image = data.image;
            note.textContent = `You can make ${data.remaining} more today.`;
          } else {
            image = ThumbnailMaker.drawCover(labels, style);
            note.textContent = 'A simple cover made from your class details.';
          }
          fill(stage, h('img', { src: image, alt: `Thumbnail for ${labels.subject}` }));
          use.classList.remove('d-none');
          go.classList.replace('custom-btn', 'custom-outline-btn');
          go.classList.remove('border-0');
          go.lastChild.textContent = useAi ? 'Try again' : (aiReady ? 'Generate with AI' : 'Make again');
        } catch (e) {
          image = null;
          empty();
          error.textContent = e.message;
          if (e.code === 'ai_not_configured') aiReady = false;
        } finally {
          busy = false;
          go.disabled = false;
          simple.disabled = false;
        }
      };

      const go = BsModal.button(aiReady ? 'Generate' : 'Make cover', { primary: true, icon: SPARK, onclick: () => run(aiReady) });
      const simple = BsModal.button('Make a simple one', { onclick: () => run(false) });
      if (!aiReady) simple.classList.add('d-none');
      const use = BsModal.button('Use this', { primary: true, onclick: () => { result = image; m.close(); } });
      use.classList.add('d-none');
      let result = null;

      const m = BsModal.open({
        title: 'Generate thumbnail',
        subtitle: aiReady ? 'AI makes a cover from your class details. You can crop it before using it.' : 'We make a cover from your class details. You can crop it before using it.',
        size: 'lg',
        body: h('div', {},
          h('div', { class: 'd-flex flex-wrap gap-2 mb-3' }, chips.map((c) => h('span', { class: 'ex-tag-v3' }, c))),
          h('span', { class: 'd-block text-14 main-text mb-2' }, 'Style'),
          styleCards,
          h('div', { class: 'mt-3' }, stage, error, note),
          aiReady ? null : h('p', { class: 'text-12 sub-text m-0 mt-2' }, 'AI pictures are not switched on for this site yet, so this makes a designed cover with your subject and exam year.')),
        footerStart: [simple],
        footerEnd: [BsModal.button('Cancel', { onclick: () => m.close() }), go, use],
        onClose: () => resolve(result),
      });
    });
  }

  /** A designed 1280×720 cover drawn in the browser (no AI). Returns a PNG data URL. */
  static drawCover({ subject = '', exam = '', year = '', type = '', medium = '' }, style = 'bold') {
    const W = 1280;
    const H = 720;
    const c = h('canvas', { width: W, height: H });
    const x = c.getContext('2d');
    const theme = {
      bold: { bg: ['#071a3a', '#0d3b8c'], accent: '#ffb627', text: '#ffffff', sub: '#c9d8f5', shape: 'rgba(255,255,255,.07)' },
      photo: { bg: ['#23412f', '#8a6a35'], accent: '#ffd166', text: '#ffffff', sub: '#f1ead8', shape: 'rgba(255,255,255,.08)' },
      minimal: { bg: ['#f7f9fd', '#dfe7f5'], accent: '#0061D2', text: '#14213d', sub: '#4E5566', shape: 'rgba(0,97,210,.07)' },
    }[style] ?? {};
    const g = x.createLinearGradient(0, 0, W, H);
    g.addColorStop(0, theme.bg[0]);
    g.addColorStop(1, theme.bg[1]);
    x.fillStyle = g;
    x.fillRect(0, 0, W, H);
    // soft shapes
    x.fillStyle = theme.shape;
    for (const [cx, cy, r] of [[1080, 120, 260], [1180, 620, 200], [140, 690, 160], [930, 420, 90]]) { x.beginPath(); x.arc(cx, cy, r, 0, Math.PI * 2); x.fill(); }
    x.strokeStyle = theme.shape;
    x.lineWidth = 2;
    for (let i = 0; i < 9; i += 1) { x.beginPath(); x.moveTo(760 + i * 60, 0); x.lineTo(560 + i * 60, H); x.stroke(); }
    // accent bar + text
    x.fillStyle = theme.accent;
    x.fillRect(96, 180, 10, 300);
    const fit = (text, max, start) => { let s = start; do { x.font = `800 ${s}px "Segoe UI", Inter, Arial, sans-serif`; s -= 4; } while (x.measureText(text).width > max && s > 40); };
    x.textBaseline = 'alphabetic';
    x.fillStyle = theme.accent;
    x.font = '700 34px "Segoe UI", Inter, Arial, sans-serif';
    x.fillText([year, exam].filter(Boolean).join('  ').toUpperCase(), 140, 236);
    x.fillStyle = theme.text;
    const words = subject.toUpperCase().split(/\s+/);
    const lines = [];
    for (const w of words) { const last = lines.at(-1); if (last && (last + ' ' + w).length <= 14) lines[lines.length - 1] = `${last} ${w}`; else lines.push(w); }
    const shown = lines.slice(0, 2);
    fit(shown.reduce((a, b) => (a.length > b.length ? a : b), ''), 980, shown.length > 1 ? 120 : 150);
    const lh = parseInt(x.font.match(/(\d+)px/)[1], 10);
    shown.forEach((ln, i) => x.fillText(ln, 136, 236 + 24 + lh * (i + 1) - lh * 0.12));
    x.fillStyle = theme.sub;
    x.font = '500 34px "Segoe UI", Inter, Arial, sans-serif';
    x.fillText([type, medium ? `${medium} medium` : ''].filter(Boolean).join('  ·  '), 140, 236 + 24 + lh * shown.length + 70);
    return c.toDataURL('image/png');
  }
}
