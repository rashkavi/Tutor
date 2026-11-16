/*
 * Translates the whole page, not only the elements that carry a data-i18n key.
 *
 * The old language switch (initializeLanguage() in assets/js/script.js) only changed elements with data-i18n="key",
 * once, when the page loaded. Menus, dashboards, lists and dialogs drawn later by JavaScript stayed in English.
 * This file:
 *   1. reads assets/i18n/phrases.<lang>.json: { "phrases": { "English text": "translation" },
 *      "patterns": [["^(\\d+) Students$", "සිසුන් $1"]] } (captured parts are translated too when the dictionary has them),
 *   2. also uses the existing en.json / <lang>.json pairs (same key → English text and its translation),
 *   3. translates every text node and the placeholder / title / aria-label of every element,
 *   4. keeps watching the page (MutationObserver), so text added later is translated as it appears,
 *   5. remembers the English, so switching back to English restores it.
 * Mark an element data-no-i18n to leave it (and everything inside) alone, e.g. names typed by people.
 *
 *   EnlixoI18n.apply('si')   // called by the language menu (script.js); 'en' switches back
 */
(function () {
    const SKIP = new Set(['SCRIPT', 'STYLE', 'NOSCRIPT', 'TEXTAREA', 'CODE', 'PRE', 'SVG', 'svg', 'CANVAS', 'IFRAME']);
    const ATTRS = ['placeholder', 'title', 'aria-label'];
    const original = new WeakMap(); // text node -> English text
    const written = new WeakMap(); // text node -> what we wrote (so our own change is not taken as new English)
    const originalAttr = new WeakMap(); // element -> { attr: English }
    const cache = {};
    let lang = 'en';
    let dict = null; // Map(English -> translation)
    let patterns = []; // [RegExp, template]
    let observer = null;
    let queued = new Set();
    let scheduled = false;

    const norm = (s) => s.replace(/\s+/g, ' ').trim();

    async function fetchJson(url) {
        try {
            const r = await fetch(url, { cache: 'force-cache' });
            return r.ok ? await r.json() : null;
        } catch (e) {
            return null;
        }
    }

    function base() {
        // the pages live one folder down (tutor/, portal/, parent/), like the other ../assets links
        const s = document.querySelector('script[src*="i18n-auto.js"]');
        return s ? s.getAttribute('src').replace(/js\/i18n-auto\.js.*$/, '') : '../assets/';
    }

    async function load(code) {
        if (cache[code]) return cache[code];
        const root = base();
        const [phrases, en, other] = await Promise.all([
            fetchJson(`${root}i18n/phrases.${code}.json`),
            fetchJson(`${root}i18n/en.json`),
            fetchJson(`${root}i18n/${code}.json`),
        ]);
        const map = new Map();
        if (en && other) {
            for (const [k, v] of Object.entries(en)) {
                if (typeof v === 'string' && typeof other[k] === 'string' && other[k].trim()) map.set(norm(v), other[k]);
            }
        }
        for (const [k, v] of Object.entries(phrases?.phrases ?? {})) map.set(norm(k), v);
        const pats = (phrases?.patterns ?? []).map(([re, to]) => [new RegExp(re), to]);
        cache[code] = { map, pats };
        return cache[code];
    }

    function translate(text) {
        const key = norm(text);
        if (!key || !/[A-Za-z]/.test(key)) return null;
        const hit = dict.get(key);
        if (hit !== undefined) return hit;
        // "Physical · 6:00 AM - 7:30 AM": translate each part on its own
        if (key.includes(' · ')) {
            const parts = key.split(' · ');
            const done = parts.map((p) => translate(p));
            if (done.some((t) => t !== null)) return parts.map((p, i) => done[i] ?? p).join(' · ');
        }
        for (const [re, to] of patterns) {
            const m = key.match(re);
            if (m) return to.replace(/\$(\d)/g, (_, i) => { const part = m[Number(i)] ?? ''; return dict.get(norm(part)) ?? part; }).replace(/^[\s,]+|[\s,]+$/g, '');
        }
        return null;
    }

    function skipped(el) {
        for (let e = el; e && e !== document.body; e = e.parentElement) {
            if (SKIP.has(e.tagName) || e.isContentEditable || e.hasAttribute('data-no-i18n')) return true;
        }
        return false;
    }

    function doText(node) {
        const now = node.nodeValue;
        if (written.get(node) !== now) original.set(node, now); // new or changed by the page: that is the English
        const en = original.get(node);
        if (en === undefined) return;
        let next = en;
        if (lang !== 'en') {
            const t = translate(en);
            if (t !== null) {
                const lead = en.match(/^\s*/)[0];
                const trail = en.match(/\s*$/)[0];
                next = lead + t + trail;
            }
        }
        if (next !== now) {
            written.set(node, next);
            node.nodeValue = next;
        } else {
            written.set(node, now);
        }
    }

    function doAttrs(el) {
        let saved = originalAttr.get(el);
        for (const a of ATTRS) {
            if (!el.hasAttribute(a)) continue;
            const now = el.getAttribute(a);
            saved ??= {};
            const mine = saved[`w:${a}`];
            if (mine !== now) saved[a] = now;
            const en = saved[a];
            const t = lang === 'en' ? en : (translate(en) ?? en);
            saved[`w:${a}`] = t;
            if (t !== now) el.setAttribute(a, t);
        }
        if (saved) originalAttr.set(el, saved);
    }

    function walk(root) {
        if (!root) return;
        if (root.nodeType === Node.TEXT_NODE) {
            if (root.parentElement && !skipped(root.parentElement)) doText(root);
            return;
        }
        if (root.nodeType !== Node.ELEMENT_NODE || skipped(root)) return;
        doAttrs(root);
        const tw = document.createTreeWalker(root, NodeFilter.SHOW_ELEMENT | NodeFilter.SHOW_TEXT, {
            acceptNode: (n) => (n.nodeType === Node.ELEMENT_NODE && (SKIP.has(n.tagName) || n.hasAttribute('data-no-i18n') || n.isContentEditable)
                ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT),
        });
        for (let n = tw.nextNode(); n; n = tw.nextNode()) {
            if (n.nodeType === Node.TEXT_NODE) doText(n); else doAttrs(n);
        }
    }

    function flush() {
        scheduled = false;
        const nodes = [...queued];
        queued = new Set();
        observer?.disconnect();
        nodes.forEach(walk);
        watch();
    }

    function queue(n) {
        queued.add(n);
        if (!scheduled) {
            scheduled = true;
            requestAnimationFrame(flush);
        }
    }

    function watch() {
        if (!dict || !document.body) return;
        observer ??= new MutationObserver((list) => {
            for (const m of list) {
                if (m.type === 'characterData') queue(m.target);
                else if (m.type === 'attributes') queue(m.target);
                else m.addedNodes.forEach(queue);
            }
        });
        observer.observe(document.body, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ATTRS });
    }

    // Sinhala and Tamil letters: the pages ask for 'Inter', which has none, so the browser used whatever system font it
    // found (a different look on every phone, tiny Tamil on buttons). These faces add Noto Sans Sinhala / Tamil to the
    // 'Inter' family for those letters only (unicode-range), so every 'Inter' font stack shows them the same way. The
    // English design's negative letter-spacing breaks the joined letters, so it is switched off in those languages.
    function fonts() {
        if (document.getElementById('enlixo-indic-fonts')) return;
        const f = `${base()}fonts/`;
        const face = (family, file, range) => `@font-face{font-family:'${family}';src:url('${f}${file}') format('woff2');font-weight:100 900;font-style:normal;font-display:swap;unicode-range:${range};}`;
        const si = 'U+0964-0965,U+0D81-0DF4,U+1CF2,U+200C-200D,U+25CC';
        const ta = 'U+0964-0965,U+0B82-0BFA,U+200C-200D,U+20B9,U+25CC';
        const css = ['Inter', 'Noto Sans Sinhala'].map((n) => face(n, 'noto-sans-sinhala.woff2', si)).join('')
            + ['Inter', 'Noto Sans Tamil'].map((n) => face(n, 'noto-sans-tamil.woff2', ta)).join('')
            + 'html:is([lang=si],[lang=ta]) body *:not(code):not(pre){letter-spacing:normal !important}'
            + 'html:is([lang=si],[lang=ta]) :is(h1,h2,h3,h4,h5,h6){line-height:1.35}'
            // Bootstrap gives body / buttons / inputs a system-ui stack without 'Inter': add the Noto fonts there too
            + "html:is([lang=si],[lang=ta]) :is(body,button,input,select,textarea,.btn,.form-control,.form-select){font-family:'Inter','Noto Sans Sinhala','Noto Sans Tamil',system-ui,-apple-system,'Segoe UI',Roboto,sans-serif}"
            // translated button words are longer: let them wrap inside the button instead of running out of it
            + 'html:is([lang=si],[lang=ta]) :is(.custom-btn,.custom-outline-btn,.custom-btn-outline,.lp-btn){white-space:normal;line-height:1.25;text-align:center;overflow-wrap:break-word}'
            + 'html:is([lang=si],[lang=ta]) .current-lang-text{white-space:nowrap}'
            // stat card titles were cut with "..." after one line; longer translated titles take a second line
            + 'html:is([lang=si],[lang=ta]) :is(.stats-mini-title,.stats-mini-period){white-space:normal;overflow:visible;text-overflow:clip}';
        const s = document.createElement('style');
        s.id = 'enlixo-indic-fonts';
        s.textContent = css;
        document.head.appendChild(s);
    }

    async function apply(code) {
        code = ['en', 'si', 'ta'].includes(code) ? code : 'en';
        if (code !== 'en') fonts();
        if (code !== 'en') {
            const d = await load(code);
            dict = d.map;
            patterns = d.pats;
        } else {
            dict ??= new Map();
        }
        lang = code;
        document.documentElement.setAttribute('lang', code);
        observer?.disconnect();
        walk(document.body);
        if (code === 'en') return; // nothing to keep translating
        watch();
    }

    window.EnlixoI18n = { apply, translate: (t) => (lang === 'en' || !dict ? t : (translate(t) ?? t)), get lang() { return lang; } };

    // Start in the language chosen last time (stored by the language menu), before the page script draws.
    let saved = 'en';
    try { saved = localStorage.getItem('lang') || 'en'; } catch (e) { /* storage blocked: stay in English */ }
    const start = () => apply(saved);
    if (saved !== 'en') {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true }); else start();
    }
}());
