import { h } from '../core/dom.js';
import { BsModal } from './BsModal.js';

const ICON = {
  zoomOut: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5M8 11h6"/></svg>',
  zoomIn: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5M8 11h6M11 8v6"/></svg>',
  rotL: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 9h9a6 6 0 1 1 0 12H8"/><path d="m8 5-4 4 4 4"/></svg>',
  rotR: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 9h-9a6 6 0 1 0 0 12h5"/><path d="m16 5 4 4-4 4"/></svg>',
  flip: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3v18M9 6 3 18h6V6ZM15 6l6 12h-6V6Z"/></svg>',
  reset: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 11a8 8 0 1 0-2.3 5.7"/><path d="M20 4v7h-7"/></svg>',
  choose: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/></svg>',
  crop: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 2v14a2 2 0 0 0 2 2h14"/><path d="M18 22V8a2 2 0 0 0-2-2H2"/></svg>',
  drag: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M5 9l-3 3 3 3M9 5l3-3 3 3M15 19l-3 3-3-3M19 9l3 3-3 3M2 12h20M12 2v20"/></svg>',
  mouse: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="6" y="3" width="12" height="18" rx="6"/><path d="M12 7v4"/></svg>',
  keys: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M6 10h.01M10 10h.01M14 10h.01M18 10h.01M7 14h10"/></svg>',
};

/**
 * "Crop Image" (ENLIXO V3): drag the picture, pull a frame corner, zoom, rotate, flip, live preview.
 *
 *   const file = await ImageCropper.open({ source: fileOrDataUrl, aspect: 16 / 9 });  // File or null (cancelled)
 *
 * The result is a JPEG at the frame's real resolution (at most `maxWidth` wide) and under `maxBytes`.
 */
export class ImageCropper {
  static open({ source, aspect = 16 / 9, maxWidth = 1600, maxBytes = 2 * 1024 * 1024, fileName = 'cover.jpg', title = 'Crop Image' }) {
    return new Promise((resolve) => {
      const c = new ImageCropper({ aspect, maxWidth, maxBytes, fileName });
      let result = null;
      const choose = h('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp', class: 'd-none', onchange: (e) => { const f = e.target.files[0]; if (f) c.load(f); e.target.value = ''; } });
      const apply = BsModal.button('Crop & Use', { primary: true, icon: ICON.crop, onclick: async () => {
        apply.disabled = true;
        result = await c.export();
        apply.disabled = false;
        if (result) m.close();
      } });
      c.onEnter = () => apply.click();
      const m = BsModal.open({
        title,
        subtitle: 'Drag the picture to move it. Pull a corner of the frame to resize it.',
        size: 'xl',
        static: true,
        body: h('div', {}, c.el, choose),
        footerStart: [BsModal.button('Choose another', { icon: ICON.choose, onclick: () => choose.click() })],
        footerEnd: [BsModal.button('Cancel', { onclick: () => m.close() }), apply],
        onClose: () => { c.destroy(); resolve(result); },
      });
      m.el.addEventListener('shown.bs.modal', () => { c.fit(); c.load(source); }, { once: true });
    });
  }

  constructor({ aspect, maxWidth, maxBytes, fileName }) {
    Object.assign(this, { aspect, maxWidth, maxBytes, fileName });
    this.img = null;
    this.rot = 0;
    this.flip = false;
    this.scale = 1;
    this.base = 1;
    this.cx = 0;
    this.cy = 0;
    this.frame = { x: 0, y: 0, w: 0, h: 0 };
    this.onEnter = () => {};

    this.canvas = h('canvas', { class: 'ex-crop-canvas', tabindex: '0', role: 'img', 'aria-label': 'Picture to crop. Arrow keys move it, plus and minus zoom, Enter applies.' });
    this.preview = h('canvas', { class: 'ex-crop-preview', 'aria-hidden': 'true' });
    this.zoom = h('input', { type: 'range', class: 'form-range ex-crop-range', min: '0', max: '100', step: '1', value: '0', 'aria-label': 'Zoom', oninput: (e) => this.#zoomTo(this.base * 2 ** (Number(e.target.value) / 25)) });
    this.size = h('span', {}, '');
    const tool = (icon, label, onclick, text = null) => h('button', { type: 'button', class: `ex-crop-tool${text ? ' ex-crop-tool-text' : ''}`, 'aria-label': label, title: label, onclick },
      h('span', { 'aria-hidden': 'true', html: icon }), text);

    this.el = h('div', { class: 'row g-3 ex-cropper' },
      h('div', { class: 'col-12 col-lg-9' },
        h('div', { class: 'ex-crop-stage' }, this.canvas),
        h('div', { class: 'd-flex flex-wrap align-items-center gap-2 mt-3' },
          tool(ICON.zoomOut, 'Zoom out', () => this.#zoomBy(1 / 1.15)),
          h('div', { class: 'flex-grow-1 px-1 ex-crop-range-wrap' }, this.zoom),
          tool(ICON.zoomIn, 'Zoom in', () => this.#zoomBy(1.15)),
          h('span', { class: 'ex-crop-sep', 'aria-hidden': 'true' }),
          tool(ICON.rotL, 'Rotate left', () => this.#rotate(-90)),
          tool(ICON.rotR, 'Rotate right', () => this.#rotate(90)),
          tool(ICON.flip, 'Flip', () => { this.flip = !this.flip; this.draw(); }),
          tool(ICON.reset, 'Reset', () => this.reset(), 'Reset'))),
      h('div', { class: 'col-12 col-lg-3 d-flex flex-column' },
        h('span', { class: 'text-12 main-text mb-2' }, 'Preview'),
        this.preview,
        h('div', { class: 'mt-auto pt-3 border-top ex-crop-hints text-12 sub-text d-flex flex-column gap-2' },
          h('span', {}, h('i', { html: ICON.drag }), 'Drag to move the picture'),
          h('span', {}, h('i', { html: ICON.mouse }), 'Scroll or use the slider to zoom'),
          h('span', {}, h('i', { html: ICON.keys }), 'Arrow keys move, Enter applies'),
          h('span', { class: 'mt-1' }, 'Saved size: ', this.size))));

    this.#bind();
  }

  async load(source) {
    const url = source instanceof Blob ? URL.createObjectURL(source) : source;
    const img = new Image();
    img.decoding = 'async';
    img.src = url;
    try { await img.decode(); } catch { this.size.textContent = 'This picture could not be opened.'; return; }
    if (source instanceof Blob) this.revoke = () => URL.revokeObjectURL(url);
    this.img = img;
    this.reset();
    this.canvas.focus({ preventScroll: true });
  }

  /** Size the canvas to its box (call once the modal is visible). */
  fit() {
    const box = this.canvas.parentElement.getBoundingClientRect();
    this.W = Math.max(280, Math.round(box.width));
    this.H = Math.round(this.W * 0.62);
    const dpr = window.devicePixelRatio || 1;
    Object.assign(this.canvas, { width: this.W * dpr, height: this.H * dpr });
    Object.assign(this.canvas.style, { width: `${this.W}px`, height: `${this.H}px` });
    this.canvas.getContext('2d').setTransform(dpr, 0, 0, dpr, 0, 0);
    const pw = this.preview.parentElement.getBoundingClientRect().width || 160;
    Object.assign(this.preview, { width: Math.round(pw * dpr), height: Math.round((pw / this.aspect) * dpr) });
    Object.assign(this.preview.style, { width: `${pw}px`, height: `${pw / this.aspect}px` });
  }

  reset() {
    if (!this.img) return;
    this.rot = 0;
    this.flip = false;
    let w = this.W * 0.86;
    let hh = w / this.aspect;
    if (hh > this.H * 0.86) { hh = this.H * 0.86; w = hh * this.aspect; }
    this.frame = { x: (this.W - w) / 2, y: (this.H - hh) / 2, w, h: hh };
    const [iw, ih] = this.#dims();
    this.base = Math.max(w / iw, hh / ih);
    this.scale = this.base;
    this.cx = this.W / 2;
    this.cy = this.H / 2;
    this.zoom.value = '0';
    this.draw();
  }

  #dims() {
    const { naturalWidth: w, naturalHeight: hh } = this.img;
    return this.rot % 180 ? [hh, w] : [w, hh];
  }

  #paintImage(ctx) {
    ctx.save();
    ctx.translate(this.cx, this.cy);
    ctx.rotate((this.rot * Math.PI) / 180);
    ctx.scale(this.flip ? -this.scale : this.scale, this.scale);
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(this.img, -this.img.naturalWidth / 2, -this.img.naturalHeight / 2);
    ctx.restore();
  }

  draw() {
    if (!this.img || !this.W) return;
    const ctx = this.canvas.getContext('2d');
    const { x, y, w, h: fh } = this.frame;
    ctx.clearRect(0, 0, this.W, this.H);
    ctx.fillStyle = '#1b2433';
    ctx.fillRect(0, 0, this.W, this.H);
    this.#paintImage(ctx);
    // dim outside the frame
    ctx.save();
    ctx.fillStyle = 'rgba(10, 16, 28, .55)';
    ctx.beginPath();
    ctx.rect(0, 0, this.W, this.H);
    ctx.rect(x, y, w, fh);
    ctx.fill('evenodd');
    ctx.restore();
    // frame, thirds, handles
    ctx.save();
    ctx.strokeStyle = 'rgba(255,255,255,.9)';
    ctx.lineWidth = 1.5;
    ctx.strokeRect(x, y, w, fh);
    ctx.setLineDash([4, 4]);
    ctx.strokeStyle = 'rgba(255,255,255,.35)';
    ctx.lineWidth = 1;
    ctx.beginPath();
    for (const t of [1 / 3, 2 / 3]) { ctx.moveTo(x + w * t, y); ctx.lineTo(x + w * t, y + fh); ctx.moveTo(x, y + fh * t); ctx.lineTo(x + w, y + fh * t); }
    ctx.stroke();
    ctx.setLineDash([]);
    for (const [hx, hy] of this.#handles()) {
      ctx.beginPath();
      ctx.arc(hx, hy, 5.5, 0, Math.PI * 2);
      ctx.fillStyle = '#0061D2';
      ctx.fill();
      ctx.strokeStyle = '#fff';
      ctx.lineWidth = 2;
      ctx.stroke();
    }
    ctx.restore();

    // live preview + saved size
    const p = this.preview.getContext('2d');
    p.save();
    p.setTransform(1, 0, 0, 1, 0, 0);
    p.fillStyle = '#fff';
    p.fillRect(0, 0, this.preview.width, this.preview.height);
    p.scale(this.preview.width / w, this.preview.height / fh);
    p.translate(-x, -y);
    this.#paintImage(p);
    p.restore();
    const [ow, oh] = this.#outSize();
    this.size.textContent = `${ow} × ${oh} px`;
  }

  #handles() {
    const { x, y, w, h: fh } = this.frame;
    return [[x, y], [x + w, y], [x, y + fh], [x + w, y + fh]];
  }

  #outSize() {
    const ow = Math.max(1, Math.min(this.maxWidth, Math.round(this.frame.w / this.scale)));
    return [ow, Math.round(ow / this.aspect)];
  }

  #zoomTo(s, px = this.frame.x + this.frame.w / 2, py = this.frame.y + this.frame.h / 2) {
    if (!this.img) return;
    const next = Math.min(this.base * 16, Math.max(this.base * 0.25, s));
    this.cx = px - ((px - this.cx) * next) / this.scale;
    this.cy = py - ((py - this.cy) * next) / this.scale;
    this.scale = next;
    this.zoom.value = String(Math.max(0, Math.min(100, Math.round(25 * Math.log2(next / this.base)))));
    this.draw();
  }

  #zoomBy(f, px, py) { this.#zoomTo(this.scale * f, px, py); }

  #rotate(deg) {
    this.rot = (this.rot + deg + 360) % 360;
    const [iw, ih] = this.#dims();
    this.base = Math.max(this.frame.w / iw, this.frame.h / ih);
    if (this.scale < this.base) this.scale = this.base;
    this.draw();
  }

  #bind() {
    const pt = (e) => { const r = this.canvas.getBoundingClientRect(); return [e.clientX - r.left, e.clientY - r.top]; };
    let drag = null;
    this.canvas.addEventListener('pointerdown', (e) => {
      if (!this.img) return;
      const [px, py] = pt(e);
      const corners = this.#handles();
      const hit = corners.findIndex(([hx, hy]) => Math.hypot(hx - px, hy - py) <= 14);
      this.canvas.setPointerCapture(e.pointerId);
      drag = hit >= 0 ? { mode: 'resize', anchor: corners[3 - hit], corner: hit } : { mode: 'move', px, py, cx: this.cx, cy: this.cy };
      this.canvas.focus({ preventScroll: true });
    });
    this.canvas.addEventListener('pointermove', (e) => {
      const [px, py] = pt(e);
      if (!drag) {
        const near = this.#handles().findIndex(([hx, hy]) => Math.hypot(hx - px, hy - py) <= 14);
        this.canvas.style.cursor = near < 0 ? 'grab' : (near === 0 || near === 3 ? 'nwse-resize' : 'nesw-resize');
        return;
      }
      if (drag.mode === 'move') {
        this.cx = drag.cx + (px - drag.px);
        this.cy = drag.cy + (py - drag.py);
      } else {
        const [ax, ay] = drag.anchor;
        const right = drag.corner === 1 || drag.corner === 3;
        const down = drag.corner === 2 || drag.corner === 3;
        // width from the pointer, kept to the aspect and inside the canvas
        let w = Math.max(Math.abs(px - ax), Math.abs(py - ay) * this.aspect);
        const maxW = Math.min(right ? this.W - ax : ax, (down ? this.H - ay : ay) * this.aspect);
        w = Math.max(80, Math.min(w, maxW));
        const fh = w / this.aspect;
        this.frame = { x: right ? ax : ax - w, y: down ? ay : ay - fh, w, h: fh };
      }
      this.draw();
    });
    const end = () => { drag = null; };
    this.canvas.addEventListener('pointerup', end);
    this.canvas.addEventListener('pointercancel', end);
    this.canvas.addEventListener('wheel', (e) => { e.preventDefault(); const [px, py] = pt(e); this.#zoomBy(Math.exp(-e.deltaY * 0.0015), px, py); }, { passive: false });
    this.canvas.addEventListener('keydown', (e) => {
      const step = e.shiftKey ? 20 : 5;
      const moves = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };
      if (moves[e.key]) { this.cx += moves[e.key][0]; this.cy += moves[e.key][1]; this.draw(); }
      else if (e.key === '+' || e.key === '=') this.#zoomBy(1.1);
      else if (e.key === '-') this.#zoomBy(1 / 1.1);
      else if (e.key === 'Enter') this.onEnter();
      else return;
      e.preventDefault();
    });
    this.onResize = () => { if (!this.img) return; const old = this.W; this.fit(); const k = this.W / old; this.frame = { x: this.frame.x * k, y: this.frame.y * k, w: this.frame.w * k, h: this.frame.h * k }; this.cx *= k; this.cy *= k; this.scale *= k; this.base *= k; this.draw(); };
    window.addEventListener('resize', this.onResize);
  }

  /** The cropped picture as a JPEG File, shrunk until it fits maxBytes. */
  async export() {
    if (!this.img) return null;
    const [ow, oh] = this.#outSize();
    const out = h('canvas', { width: ow, height: oh });
    const ctx = out.getContext('2d');
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, ow, oh);
    ctx.scale(ow / this.frame.w, oh / this.frame.h);
    ctx.translate(-this.frame.x, -this.frame.y);
    this.#paintImage(ctx);
    for (const q of [0.9, 0.8, 0.7, 0.55]) {
      const blob = await new Promise((r) => out.toBlob(r, 'image/jpeg', q));
      if (blob && blob.size <= this.maxBytes) return new File([blob], this.fileName.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' });
    }
    return null;
  }

  destroy() {
    window.removeEventListener('resize', this.onResize);
    this.revoke?.();
  }
}
