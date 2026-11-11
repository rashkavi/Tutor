// Student Time Table (portal/time-table.php, portal/class-time-table.php): the ENLIXO V3 student portal calendar
// (lecture-schedule-calender + eventCalender.js).
//  - Phones and tablets (below 992px): a month grid; today is a filled circle, class days are tinted with a dot,
//    tapping a day lists that day's classes underneath.
//  - Large screens: a Week view (class days share the width, free days shrink to a narrow column) or the Month grid
//    (up to three classes a day, "+N more" opens that week). The chosen view is remembered.
// Every class card opens time-table-class-details. Sri Lankan public holidays are added when they arrive.
// Data: api/getTimeTableData (one request per page load; ?class=<id> for one class). The API escapes the text it sends.
(() => {
    const root = document.getElementById('timeTable');
    if (!root) return;

    const ICON = window.SL_ICONS || {};
    const icon = (name) => ICON[name] || '';
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    const MONTH_CELL_CLASSES = 3;
    const classId = root.dataset.classId || '';

    let lectures = {};
    const holidays = {};
    const holidayCache = {};
    let year = new Date().getFullYear();
    let month = new Date().getMonth();
    let view = 'week';
    let weekStart = null;
    let mobileSelected = null;

    const pad = (n) => String(n).padStart(2, '0');
    const toKey = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    const keyOf = (y, m, d) => `${y}-${pad(m + 1)}-${pad(d)}`;
    const startOfWeek = (d) => { const s = new Date(d.getFullYear(), d.getMonth(), d.getDate()); s.setDate(s.getDate() - s.getDay()); return s; };
    const shortDate = (d, withYear) => d.toLocaleDateString('en-GB', withYear ? { day: 'numeric', month: 'short', year: 'numeric' } : { day: 'numeric', month: 'short' });
    const plural = (n, word) => `${n} ${word}${n === 1 ? '' : 'es'}`;
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const store = {
        get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
        set(k, v) { try { localStorage.setItem(k, v); } catch (e) { /* private mode */ } },
    };

    // API status -> card colour
    const statusClass = (status) => {
        switch ((status || '').toLowerCase()) {
            case 'live':
            case 'ongoing': return 'is-live';
            case 'completed': return 'is-completed';
            case 'cancelled': return 'is-cancelled';
            case 'postponed': return 'is-postponed';
            default: return 'is-upcoming';
        }
    };
    const typeIcon = (type) => ((type || '').toLowerCase() === 'physical' ? icon('physical') : icon('online'));
    const detailsUrl = (l) => 'time-table-class-details?time-table-id=' + encodeURIComponent(l.lectureId);

    // ------------------------------------------------------------------ one class card (phone day list + week view)
    const cardHtml = (l) => {
        const st = statusClass(l.status);
        return `<a class="m-lec ${st}" href="${detailsUrl(l)}">
                    <div class="m-lec-time"><strong>${l.startTime}</strong><span>${l.endTime}</span></div>
                    <div class="m-lec-body">
                        <div class="m-lec-top">
                            <span class="m-lec-status">${st === 'is-live' ? '<span class="m-lec-pulse"></span>' : ''}${l.status}</span>
                            <span class="m-lec-type">${typeIcon(l.lectureType)}${l.lectureType}</span>
                        </div>
                        <span class="m-lec-class">${l.class}</span>
                        ${l.title ? `<span class="m-lec-title">${l.title}</span>` : `<span class="m-lec-title">${l.subject}</span>`}
                        <span class="m-lec-tutor">${icon('teacher')}${l.lecturer || l.lecturerName || ''}</span>
                    </div>
                    <span class="m-lec-arrow">${icon('chevron-right')}</span>
                 </a>`;
    };

    // ------------------------------------------------------------------ phone / tablet month grid + day list
    function renderMobile() {
        const grid = document.getElementById('ttMobileGrid');
        if (!grid) return;
        document.getElementById('ttMobileTitle').textContent = `${monthNames[month]} ${year}`;
        const todayKey = toKey(new Date());
        const prefix = `${year}-${pad(month + 1)}-`;
        const days = new Date(year, month + 1, 0).getDate();

        // keep the chosen day when it is in this month, else today, else the first class day, else the 1st
        if (!mobileSelected || !mobileSelected.startsWith(prefix)) {
            const firstClassDay = Object.keys(lectures).filter((d) => d.startsWith(prefix)).sort()[0];
            mobileSelected = todayKey.startsWith(prefix) ? todayKey : (firstClassDay || prefix + '01');
        }

        let html = '';
        for (let i = 0; i < new Date(year, month, 1).getDay(); i++) html += '<span class="m-cal-day is-blank"></span>';
        for (let day = 1; day <= days; day++) {
            const key = prefix + pad(day);
            const list = lectures[key] || [];
            const running = list.filter((l) => !['cancelled', 'postponed'].includes((l.status || '').toLowerCase()));
            const cls = ['m-cal-day'];
            if (list.length) cls.push('has-class');
            if (list.length && !running.length) cls.push('only-cancelled');
            if (key === todayKey) cls.push('is-today');
            if (key === mobileSelected) cls.push('is-selected');
            if (holidays[key]) cls.push('is-holiday');
            if (key < todayKey) cls.push('is-past');
            const label = `${day} ${monthNames[month]}${list.length ? `, ${plural(list.length, 'class')}` : ''}`;
            html += `<button type="button" class="${cls.join(' ')}" data-date="${key}" aria-label="${label}" aria-pressed="${key === mobileSelected}">
                        <span class="m-cal-num">${day}</span>${list.length ? '<span class="m-cal-dot"></span>' : ''}
                     </button>`;
        }
        grid.innerHTML = html;
        renderMobileDay(false);
    }

    function renderMobileDay(scroll) {
        const list = document.getElementById('ttMobileDay');
        if (!list) return;
        const date = new Date(mobileSelected + 'T00:00:00');
        document.getElementById('ttMobileDayTitle').textContent = date.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' });
        const dayLectures = lectures[mobileSelected] || [];
        document.getElementById('ttMobileDayCount').textContent = dayLectures.length ? plural(dayLectures.length, 'class') : '';
        const holiday = holidays[mobileSelected];
        let html = holiday ? `<div class="m-day-holiday">${icon('calendar')}${esc(holiday.name)}</div>` : '';
        html += dayLectures.length
            ? dayLectures.map(cardHtml).join('')
            : `<div class="m-day-empty"><span class="sl-ico">${icon('calendar-empty')}</span><strong class="main-text">No classes on this day</strong><span>Pick another day in the calendar.</span></div>`;
        list.innerHTML = html;
        if (scroll) list.closest('.m-day').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // ------------------------------------------------------------------ large screens: month grid
    function renderMonth() {
        const box = document.getElementById('ttMonth');
        if (!box) return;
        const first = new Date(year, month, 1);
        const days = new Date(year, month + 1, 0).getDate();
        const todayKey = toKey(new Date());
        let html = '<table class="mv-grid"><thead><tr>';
        ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].forEach((d) => { html += `<th>${d}</th>`; });
        html += '</tr></thead><tbody><tr>';
        for (let i = 0; i < first.getDay(); i++) html += '<td class="mv-empty"></td>';
        for (let day = 1; day <= days; day++) {
            const key = keyOf(year, month, day);
            const list = lectures[key] || [];
            const holiday = holidays[key];
            const cls = ['mv-cell'];
            if (key === todayKey) cls.push('is-today');
            if (key < todayKey) cls.push('is-past');
            if (holiday) cls.push('is-holiday');
            if (list.length) cls.push('has-class');
            html += `<td class="${cls.join(' ')}" data-date="${key}"><div class="mv-head"><span class="mv-num">${day}</span>${key === todayKey ? '<span class="mv-today">Today</span>' : ''}</div>`;
            if (holiday) html += `<div class="mv-holiday" title="${esc(holiday.name)}">${icon('calendar')}<span>${esc(holiday.name)}</span></div>`;
            if (list.length) {
                html += '<div class="mv-lectures">';
                list.slice(0, MONTH_CELL_CLASSES).forEach((l) => {
                    html += `<a class="mv-lec ${statusClass(l.status)}" href="${detailsUrl(l)}" title="${l.class} · ${l.startTime} - ${l.endTime} · ${l.status}">
                                <span class="mv-lec-class">${l.class}</span>
                                <span class="mv-lec-meta"><strong>${l.startTime}</strong> · ${l.lectureType}</span>
                             </a>`;
                });
                if (list.length > MONTH_CELL_CLASSES) html += `<button type="button" class="mv-more" data-week="${key}">+${list.length - MONTH_CELL_CLASSES} more</button>`;
                html += '</div>';
            }
            html += '</td>';
            if ((first.getDay() + day) % 7 === 0 && day < days) html += '</tr><tr>';
        }
        const trailing = (7 - (first.getDay() + days) % 7) % 7;
        for (let i = 0; i < trailing; i++) html += '<td class="mv-empty"></td>';
        box.innerHTML = html + '</tr></tbody></table>';
        if (view === 'month') document.getElementById('ttTitle').textContent = `${monthNames[month]} ${year}`;
    }

    // ------------------------------------------------------------------ large screens: week view
    function renderWeek() {
        const box = document.getElementById('ttWeek');
        if (!box || view !== 'week') return;
        const todayKey = toKey(new Date());
        const days = [];
        for (let i = 0; i < 7; i++) {
            const d = new Date(weekStart);
            d.setDate(d.getDate() + i);
            const key = toKey(d);
            days.push({ d, key, list: lectures[key] || [], holiday: holidays[key] || null });
        }
        const end = days[6].d;
        document.getElementById('ttTitle').textContent = `${shortDate(weekStart, weekStart.getFullYear() !== end.getFullYear())} – ${shortDate(end, true)}`;
        const total = days.reduce((n, day) => n + day.list.length, 0);
        const columns = days.map((day) => (total === 0 ? 'minmax(0, 1fr)' : (day.list.length ? 'minmax(170px, 1fr)' : '84px'))).join(' ');

        let html = `<div class="w-grid" style="grid-template-columns:${columns}">`;
        days.forEach((day) => {
            const cls = ['w-day'];
            if (day.list.length) cls.push('has-class');
            if (day.key === todayKey) cls.push('is-today');
            if (day.key < todayKey) cls.push('is-past');
            if (day.holiday) cls.push('is-holiday');
            html += `<div class="${cls.join(' ')}">
                        <div class="w-day-head">
                            <span class="w-dow">${day.d.toLocaleDateString('en-GB', { weekday: 'short' })}</span>
                            <span class="w-date">${day.d.getDate()}</span>
                            ${day.list.length ? `<span class="w-count">${plural(day.list.length, 'class')}</span>` : ''}
                        </div>
                        <div class="w-day-body">
                            ${day.holiday ? `<div class="w-holiday" title="${esc(day.holiday.name)}">${icon('calendar')}<span>${esc(day.holiday.name)}</span></div>` : ''}
                            ${day.list.length ? day.list.map(cardHtml).join('') : (total ? `<div class="w-free">${icon('calendar-empty')}<span>Free</span></div>` : '')}
                        </div>
                     </div>`;
        });
        html += '</div>';
        if (total === 0) {
            html += `<div class="empty-state">
                        <span class="empty-state-icon">${icon('calendar-empty')}</span>
                        <span class="empty-state-title">No classes this week</span>
                        <span class="empty-state-text">Use the arrows to look at other weeks, or switch to the month view.</span>
                     </div>`;
        }
        box.innerHTML = html;
    }

    // ------------------------------------------------------------------ navigation
    const renderAll = () => { renderMobile(); renderMonth(); renderWeek(); };

    function setView(next) {
        view = next === 'month' ? 'month' : 'week';
        store.set('ttView', view);
        root.querySelectorAll('.w-view').forEach((b) => {
            const on = b.dataset.view === view;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-selected', on);
        });
        document.getElementById('ttWeek').classList.toggle('d-none', view !== 'week');
        document.getElementById('ttMonth').classList.toggle('d-none', view !== 'month');
        view === 'week' ? renderWeek() : renderMonth();
    }

    function showMonth(y, m) {
        year = y;
        month = m;
        renderMobile();
        renderMonth();
        loadHolidays(y);
    }

    const stepMonth = (dir) => {
        const d = new Date(year, month + dir, 1);
        showMonth(d.getFullYear(), d.getMonth());
    };

    function step(dir) {
        if (view === 'week') {
            weekStart.setDate(weekStart.getDate() + dir * 7);
            loadHolidays(weekStart.getFullYear());
            renderWeek();
        } else {
            stepMonth(dir);
        }
    }

    function today() {
        const now = new Date();
        weekStart = startOfWeek(now);
        mobileSelected = null;
        showMonth(now.getFullYear(), now.getMonth());
        renderWeek();
    }

    // ------------------------------------------------------------------ Sri Lankan public holidays (never block the classes)
    function loadHolidays(y) {
        if (holidayCache[y]) return holidayCache[y];
        holidayCache[y] = fetch(`https://raw.githubusercontent.com/Dilshan-H/srilanka-holidays/main/json/${y}.json`)
            .then((r) => (r.ok ? r.json() : []))
            .then((data) => {
                const list = Array.isArray(data) ? data : (Array.isArray(data.holidays) ? data.holidays : []);
                list.forEach((h) => { if (h.start) holidays[h.start] = { name: h.summary || 'Holiday' }; });
                renderAll();
            })
            .catch(() => { /* the calendar works without holidays */ });
        return holidayCache[y];
    }

    // ------------------------------------------------------------------ events
    root.addEventListener('click', (e) => {
        const day = e.target.closest('.m-cal-day[data-date]');
        if (day) {
            mobileSelected = day.dataset.date;
            root.querySelectorAll('.m-cal-day.is-selected').forEach((b) => { b.classList.remove('is-selected'); b.setAttribute('aria-pressed', 'false'); });
            day.classList.add('is-selected');
            day.setAttribute('aria-pressed', 'true');
            renderMobileDay(true);
            return;
        }
        const more = e.target.closest('.mv-more');
        if (more) {
            const [y, m, d] = more.dataset.week.split('-').map(Number);
            weekStart = startOfWeek(new Date(y, m - 1, d));
            setView('week');
            return;
        }
        const btn = e.target.closest('[data-tt]');
        if (!btn) return;
        switch (btn.dataset.tt) {
            case 'prev-month': stepMonth(-1); break;
            case 'next-month': stepMonth(1); break;
            case 'prev': step(-1); break;
            case 'next': step(1); break;
            case 'today': today(); break;
            case 'view': setView(btn.dataset.view); break;
        }
    });

    // ------------------------------------------------------------------ load
    weekStart = startOfWeek(new Date());
    view = store.get('ttView') === 'month' ? 'month' : 'week';
    setView(view);
    renderMobile();
    fetch('api/getTimeTableData' + (classId ? '?class=' + encodeURIComponent(classId) : ''))
        .then((r) => r.json())
        .then((data) => {
            if (!data || !data.done) throw new Error(data && data.error ? data.error : 'load');
            lectures = data.lectures && !Array.isArray(data.lectures) ? data.lectures : {};
            renderAll();
            loadHolidays(year);
        })
        .catch(() => {
            if (typeof showToast === 'function') showToast('error', 'Could not load your time table. Please refresh the page.');
        })
        .finally(() => root.classList.remove('is-loading'));
})();
