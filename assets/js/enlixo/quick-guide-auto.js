// Loaded on every page by assets/js/init.js. Turns every page-title (i) (data-quick-guide, written by PHP
// enlixo_form_header) into the Quick guide card, also on pages whose own script does not do it, and on (i) that
// appear later. Also lets the older createInfo.js pop-ups play their video in the same player.
import { QuickGuide } from './components/QuickGuide.js';

window.EnlixoGuide = { play: (url, title) => QuickGuide.play(url, title), youtubeId: (url) => QuickGuide.youtubeId(url) };

const run = () => {
  QuickGuide.attachAll();
  new MutationObserver((list) => {
    if (list.some((m) => [...m.addedNodes].some((n) => n.nodeType === 1 && (n.matches?.('[data-quick-guide]') || n.querySelector?.('[data-quick-guide]'))))) {
      QuickGuide.attachAll();
    }
  }).observe(document.body, { childList: true, subtree: true });
};
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run, { once: true }); else run();
