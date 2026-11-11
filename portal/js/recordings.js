// Student recordings lists (portal/recordings.php, portal/pre-recorded-classes.php), as in the ENLIXO V3 student portal:
// tabs + search over the cards printed by the page (includes/enlixo/student-recordings.php sr_grid()). The open tab is
// remembered per list; the class select reloads the page with ?class=<id>.
(() => {
    const list = document.querySelector('.sr-list');
    if (!list) return;
    const store = {
        get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
        set(k, v) { try { localStorage.setItem(k, v); } catch (e) { /* private mode */ } },
    };
    const tabs = [...list.querySelectorAll('.ac-tab')];
    const cards = [...list.querySelectorAll('.sr-card')];
    const search = list.querySelector('#sr-search');
    const key = 'sr-tab-' + list.dataset.kind;
    let current = store.get(key);
    if (!tabs.some((t) => t.dataset.tab === current)) current = 'all';

    const apply = () => {
        const words = (search ? search.value : '').trim().toLowerCase().split(/\s+/).filter(Boolean);
        let inTab = 0;
        let shown = 0;
        cards.forEach((card) => {
            const tabOk = current === 'all' || card.dataset.tab === current;
            const textOk = words.every((w) => card.dataset.search.includes(w));
            if (tabOk) inTab++;
            const show = tabOk && textOk;
            if (show) shown++;
            card.classList.toggle('d-none', !show);
        });
        tabs.forEach((t) => {
            t.classList.toggle('is-active', t.dataset.tab === current);
            t.setAttribute('aria-selected', t.dataset.tab === current);
        });
        list.querySelectorAll('.sr-empty').forEach((el) => {
            const k = el.dataset.empty;
            el.classList.toggle('d-none', !(inTab === 0 ? k === current : (k === 'search' && shown === 0)));
        });
    };

    tabs.forEach((t) => t.addEventListener('click', () => {
        current = t.dataset.tab;
        store.set(key, current);
        apply();
    }));
    let timer = null;
    if (search) search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(apply, 150); });
    apply();
})();
