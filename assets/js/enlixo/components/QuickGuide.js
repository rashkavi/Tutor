import { h } from '../core/dom.js';
import { Popover } from './Popover.js';

/**
 * The one (i) help card for every page (ENLIXO V3 "Quick guide"): dark-blue header with the title, a short intro,
 * a checklist, and a video when the guide has one. Used by
 *   - the page title (i): PHP enlixo_form_header() writes <button data-quick-guide='{"title","intro","points","video"}'>
 *   - the (i) next to a hard field or panel: infoTip() in InfoTip.js
 *   - the older createInfo.js pop-ups (same markup and classes)
 * Video links live in one file, assets/guides/videos.json, so they can be changed without touching code.
 *
 *   QuickGuide.attachAll()          // also done on every page by quick-guide-auto.js
 *   QuickGuide.play(url)            // opens the video player
 */
const INFO = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 2a8 8 0 1 1 0 16 8 8 0 0 1 0-16Zm1 11h-2v-5h2v5Zm0-7h-2V6h2v2Z"/></svg>';
const CHECK = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm-1.2 14.2-4-4 1.4-1.4 2.6 2.6 5.6-5.6 1.4 1.4-7 7Z"/></svg>';
const PLAY = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.5v13l11-6.5z"/></svg>';

export class QuickGuide {
  /** YouTube video id from a watch, share, embed or shorts link (or a pasted iframe), else null. */
  static youtubeId(url) {
    if (!url) return null;
    const src = String(url).match(/src\s*=\s*["']([^"']+)["']/i)?.[1] ?? String(url);
    const m = src.match(/(?:youtu\.be\/|youtube(?:-nocookie)?\.com\/(?:embed\/|shorts\/|live\/|watch\?(?:.*&)?v=))([A-Za-z0-9_-]{6,})/);
    return m ? m[1] : null;
  }

  static card({ title, intro = '', points = [], subtitle = 'Quick guide', video = '', videoTitle = 'Watch the video guide' }) {
    const id = QuickGuide.youtubeId(video);
    return h('div', { class: 'ex-qg', role: 'note' },
      h('div', { class: 'ex-qg-head' },
        h('span', { class: 'ex-qg-icon', 'aria-hidden': 'true', html: INFO }),
        h('div', {}, h('strong', {}, title), h('small', {}, subtitle))),
      h('div', { class: 'ex-qg-body' },
        intro ? h('p', {}, intro) : null,
        points.length ? h('ul', {}, points.map((p) => h('li', {}, h('span', { class: 'ex-qg-check', 'aria-hidden': 'true', html: CHECK }), h('span', {}, p)))) : null,
        video ? h('button', { type: 'button', class: 'ex-qg-video', 'aria-label': `${videoTitle}: ${title}`, onclick: (e) => { e.stopPropagation(); QuickGuide.play(video, title); } },
          id ? h('img', { src: `https://img.youtube.com/vi/${id}/hqdefault.jpg`, alt: '', loading: 'lazy', onerror: (e) => { e.target.replaceWith(h('span', { class: 'ex-qg-video-blank' })); } }) : h('span', { class: 'ex-qg-video-blank' }),
          h('span', { class: 'ex-qg-play', 'aria-hidden': 'true', html: PLAY }),
          h('span', { class: 'ex-qg-video-label' }, videoTitle)) : null));
  }

  /** Plays a guide video over the page (YouTube, without leaving the page); other links open in a new tab. */
  static play(url, title = 'Video guide') {
    const id = QuickGuide.youtubeId(url);
    if (!id) { window.open(url, '_blank', 'noopener'); return; }
    const close = () => { overlay.remove(); document.removeEventListener('keydown', onKey); last?.focus?.(); };
    const onKey = (e) => { if (e.key === 'Escape') close(); };
    const last = document.activeElement;
    const btn = h('button', { type: 'button', class: 'ex-qg-player-close', 'aria-label': 'Close the video' }, '×');
    const overlay = h('div', { class: 'ex-qg-player', role: 'dialog', 'aria-modal': 'true', 'aria-label': title, onclick: (e) => { if (e.target === overlay) close(); } },
      h('div', { class: 'ex-qg-player-box' },
        h('div', { class: 'ex-qg-player-head' }, h('span', {}, title), btn),
        h('div', { class: 'ex-qg-player-frame' },
          h('iframe', { src: `https://www.youtube-nocookie.com/embed/${id}?autoplay=1&rel=0`, title, allow: 'autoplay; encrypted-media; picture-in-picture; fullscreen', allowfullscreen: true }))));
    btn.addEventListener('click', close);
    document.addEventListener('keydown', onKey);
    document.body.append(overlay);
    btn.focus();
  }

  static attachAll(root = document) {
    root.querySelectorAll('[data-quick-guide]').forEach((btn) => {
      if (btn.dataset.qgBound) return;
      btn.dataset.qgBound = '1';
      let data;
      try { data = JSON.parse(btn.dataset.quickGuide); } catch { return; }
      const host = btn.parentElement;
      const placeholder = document.createComment('qg');
      host.replaceChild(placeholder, btn);
      const pop = new Popover({ trigger: btn, align: 'start', className: 'ex-qg-pop', content: QuickGuide.card(data), hover: true });
      host.replaceChild(pop.el, placeholder);
    });
  }
}
