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

    async function apply(code) {
        code = ['en', 'si', 'ta'].includes(code) ? code : 'en';
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
