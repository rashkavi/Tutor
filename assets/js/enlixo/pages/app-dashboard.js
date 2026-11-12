// Dashboards (tutor, admin, student, parent). The page is drawn by PHP (includes/enlixo/dashboard.php);
// this adds the three things that need a script: the month calendar, the chart hover card and the notes box.
const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const pad = (n) => String(n).padStart(2, '0');
const el = (tag, cls, text) => {
    const n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined) n.textContent = text;
    return n;
};

class DashCalendar {
    constructor(root) {
        this.root = root;
        this.data = JSON.parse(root.dataset.dashCalendar);
        this.month = this.data.today.slice(0, 7);
        this.picked = this.data.today;
        this.title = root.querySelector('[data-cal-title]');
        this.grid = root.querySelector('[data-cal-grid]');
        this.day = root.querySelector('[data-cal-day]');
        this.prev = root.querySelector('[data-cal-prev]');
        this.next = root.querySelector('[data-cal-next]');
        this.prev.addEventListener('click', () => this.move(-1));
        this.next.addEventListener('click', () => this.move(1));
        this.draw();
    }

    move(step) {
        const [y, m] = this.month.split('-').map(Number);
        const d = new Date(y, m - 1 + step, 1);
        this.month = `${d.getFullYear()}-${pad(d.getMonth() + 1)}`;
        this.draw();
    }

    draw() {
        const [y, m] = this.month.split('-').map(Number);
        this.title.textContent = `${MONTHS[m - 1]} ${y}`;
        // class days are loaded for a few months around today; stop at the ends instead of showing empty months
        this.prev.disabled = this.month <= this.data.from;
        this.next.disabled = this.month >= this.data.to;
        this.grid.replaceChildren();
        const lead = (new Date(y, m - 1, 1).getDay() + 6) % 7; // Monday first
        for (let i = 0; i < lead; i++) this.grid.append(el('span', 'ex-hd-cal-cell'));
        const count = new Date(y, m, 0).getDate();
        for (let d = 1; d <= count; d++) {
            const key = `${this.month}-${pad(d)}`;
            const list = this.data.days[key];
            const cell = el(list ? 'button' : 'span', 'ex-hd-cal-cell', String(d));
            if (key === this.data.today) cell.classList.add('ex-hd-cal-today');
            if (list) {
                cell.type = 'button';
                cell.setAttribute('aria-label', `${d} ${MONTHS[m - 1]}: ${list.length} ${list.length === 1 ? 'class' : 'classes'}`);
                if (key === this.picked) cell.classList.add('ex-hd-cal-picked');
                cell.addEventListener('click', () => {
                    this.picked = key;
                    this.draw();
                });
            }
            this.grid.append(cell);
        }
        this.drawDay();
    }

    drawDay() {
        const list = this.data.days[this.picked];
        const [y, m, d] = this.picked.split('-').map(Number);
        const head = el('strong', '', this.picked === this.data.today ? 'Today' : `${d} ${MONTHS[m - 1]} ${y}`);
        this.day.replaceChildren(head);
        if (!list) {
            this.day.append(el('span', '', 'No classes. Days with a dot have classes.'));
            return;
        }
        const ul = el('ul');
        for (const s of list) {
            const li = el('li');
            li.append(el('span', 'text-truncate', s.c), el('span', 'text-nowrap', s.t));
            ul.append(li);
        }
        this.day.append(ul);
    }
}

// one hover card for every chart column (also shown on keyboard focus)
function chartTips() {
    let tip = null;
    const show = (col) => {
        tip ??= document.body.appendChild(el('div', 'ex-hd-chart-tip'));
        tip.textContent = col.dataset.tip;
        const r = col.getBoundingClientRect();
        tip.style.left = `${Math.min(Math.max(r.left + r.width / 2, 90), window.innerWidth - 90)}px`;
        tip.style.top = `${r.top}px`;
        tip.hidden = false;
    };
    const hide = () => { if (tip) tip.hidden = true; };
    for (const col of document.querySelectorAll('.ex-hd-chart-col[data-tip]')) {
        col.addEventListener('mouseenter', () => show(col));
        col.addEventListener('focus', () => show(col));
        col.addEventListener('mouseleave', hide);
        col.addEventListener('blur', hide);
    }
}

// notes are kept in this browser only, so the label says so
function notes() {
    for (const box of document.querySelectorAll('[data-dash-note]')) {
        const key = `enlixo-note-${box.dataset.dashNote}`;
        const state = box.parentElement.querySelector('[data-note-state]');
        try {
            box.value = localStorage.getItem(key) ?? '';
        } catch {
            box.disabled = true;
            if (state) state.textContent = 'Notes are turned off in this browser.';
            continue;
        }
        let timer;
        box.addEventListener('input', () => {
            clearTimeout(timer);
            if (state) state.textContent = 'Saving...';
            timer = setTimeout(() => {
                try {
                    localStorage.setItem(key, box.value);
                    if (state) state.textContent = 'Saved on this device';
                } catch {
                    if (state) state.textContent = 'Could not save. The note is too long.';
                }
            }, 400);
        });
    }
}

for (const root of document.querySelectorAll('[data-dash-calendar]')) new DashCalendar(root);
chartTips();
notes();
