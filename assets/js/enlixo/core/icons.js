import { ICONS } from './icon-data.js';

/**
 * The one icon library for every Tutor page: Solar icons, served from this site (no CDN).
 * Names are listed in assets/icons/icon-map.json; PHP pages use enlixo_icon() with the same names.
 *   icon('edit')  icon('view-cards', { size: 20, className: 'me-2' })
 */
export function icon(name, { size = 18, className = '', label = '' } = {}) {
  const span = document.createElement('span');
  span.className = `ex-ico ${className}`.trim();
  const body = ICONS[name] ?? ICONS.info;
  span.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 24 24" ${label ? `role="img" aria-label="${label.replace(/"/g, '&quot;')}"` : 'aria-hidden="true" focusable="false"'}>${body}</svg>`;
  return span;
}

/** The same icon as an SVG string, for places that take markup (static buttons). */
export function iconSvg(name, size = 18) {
  return `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 24 24" aria-hidden="true" focusable="false">${ICONS[name] ?? ICONS.info}</svg>`;
}
