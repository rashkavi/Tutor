var currentSelectedMonth;
const now = new Date();
let isSubjectLectureSchedule = false;
let subject = null;

const monthNames = [
    "January", "February", "March", "April", "May", "June",
    "July", "August", "September", "October", "November", "December"
];

let lectures = {};
let calLoaded = false;

const getLectureScheduleData = () => {
    fetch('api/getLectureScheduleData')
        .then((response) => response.text())
        .then((data) => {
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                lectures = response.lectures;
                calLoaded = true;
                generateCalendar(now.getFullYear(), now.getMonth());
                initLecturePopovers();
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}
const getClassLectureScheduleData = (id) => {
    isSubjectLectureSchedule = true;
    subject = id;
    fetch(`api/getLectureScheduleData?class=${id}`)
        .then((response) => response.text())
        .then((data) => {
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                lectures = response.lectures;
                calLoaded = true;
                generateCalendar(now.getFullYear(), now.getMonth());
                initLecturePopovers();

            }
        })
        .catch((error) => {
            console.log(error);
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const getSelectedLecturerScheduleData = (lecturerd) => {
    fetch('api/getSelectedLecturerScheduleData?lecturer=' + lecturerd)
        .then((response) => response.text())
        .then((data) => {
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                lectures = response.lectures;
                calLoaded = true;
                generateCalendar(now.getFullYear(), now.getMonth());
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

// ---------- V3 Lecture Schedule look (2026-11-10) ----------
// Month grid with the days of the next/previous month greyed, today ringed, each lecture a small
// card with a status badge, a hover card with the details, and a click to open the full details.
let currentSelectedYear = now.getFullYear();
const CAL_MAX_PER_DAY = 3;
const calEsc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const calSvg = {
    left: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 12H4m0 0l6-6m-6 6l6 6"/></svg>',
    right: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12h16m0 0l-6-6m6 6l-6 6"/></svg>',
    calendar: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M2 12c0-3.771 0-5.657 1.172-6.828S6.229 4 10 4h4c3.771 0 5.657 0 6.828 1.172S22 8.229 22 12v2c0 3.771 0 5.657-1.172 6.828S17.771 22 14 22h-4c-3.771 0-5.657 0-6.828-1.172S2 17.771 2 14zM7 4V2.5M17 4V2.5M2.5 9h19"/></svg>',
    clock: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l2.5 2.5"/></svg>',
    user: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="6" r="4"/><path d="M20 17.5c0 2.485 0 4.5-8 4.5s-8-2.015-8-4.5S7.582 13 12 13s8 2.015 8 4.5Z"/></svg>',
};
// status → [css key, badge text]
function calStatus(status) {
    const s = String(status || '').toLowerCase();
    if (s.includes('ongoing') || s.includes('live')) return ['live', 'Live'];
    if (s.includes('complete')) return ['done', 'Completed'];
    if (s.includes('cancel')) return ['cancelled', 'Cancelled'];
    if (s.includes('postpone')) return ['postponed', 'Postponed'];
    if (s.includes('leave')) return ['leave', 'Study Leave'];
    if (s.includes('upcoming')) return ['upcoming', 'Upcoming'];
    return ['pending', status || 'Pending'];
}
const calKey = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

function generateCalendar(year, month) {
    currentSelectedMonth = month;
    currentSelectedYear = year;
    const el = document.getElementById('calendar');
    if (!el) return;
    el.classList.add('ex-cal');

    const first = new Date(year, month, 1);
    const start = new Date(year, month, 1 - first.getDay());
    const last = new Date(year, month + 1, 0);
    const weeks = Math.ceil((first.getDay() + last.getDate()) / 7);
    const todayKey = calKey(new Date());
    const isThisMonth = year === new Date().getFullYear() && month === new Date().getMonth();

    let monthCount = 0;
    let html = `
      <div class="ex-cal-nav">
        <button type="button" class="py-2 px-3 bl-solid br-8 d-flex align-items-center justify-content-center gap-1 component-theme previous-button" aria-label="Previous month" onclick="prevMonthSchedule()">${calSvg.left}<span class="text-14 main-text fw-semibold">Previous</span></button>
        <div class="ex-cal-title"><h2>${monthNames[month]} ${year}</h2>
          ${isThisMonth ? '' : '<button type="button" class="ex-cal-today-btn" onclick="todaySchedule()">Back to today</button>'}</div>
        <button type="button" class="py-2 px-3 bl-solid br-8 d-flex align-items-center justify-content-center gap-1 component-theme next-button" aria-label="Next month" onclick="nextMonthSchedule()"><span class="text-14 main-text fw-semibold">Next</span>${calSvg.right}</button>
      </div>
      <div class="ex-cal-legend" aria-label="What the colours mean">
        <span><i class="ex-cal-badge ex-st-live">Live</i> on now</span>
        <span><i class="ex-cal-badge ex-st-upcoming">Upcoming</i> not started</span>
        <span><i class="ex-cal-badge ex-st-done">Completed</i> finished</span>
        <span><i class="ex-cal-badge ex-st-cancelled">Cancelled</i> or postponed</span>
        <span class="ex-cal-legend-tip">Point at a lecture for its details; click a day to schedule one.</span>
      </div>
      <div class="ex-cal-scroll"><div class="ex-cal-grid" role="grid" aria-label="${monthNames[month]} ${year}">
        ${['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((d) => `<div class="ex-cal-dow" role="columnheader">${d}</div>`).join('')}`;

    for (let i = 0; i < weeks * 7; i++) {
        const d = new Date(start.getFullYear(), start.getMonth(), start.getDate() + i);
        const key = calKey(d);
        const inMonth = d.getMonth() === month;
        const isToday = key === todayKey;
        const dayLectures = inMonth ? (lectures[key] || []) : [];
        monthCount += dayLectures.length;
        const shown = dayLectures.slice(0, CAL_MAX_PER_DAY);
        const more = dayLectures.length - shown.length;
        html += `
        <div class="ex-cal-cell${inMonth ? '' : ' ex-out'}${isToday ? ' ex-today' : ''}${d.getDay() === 0 ? ' ex-sun' : ''}" role="gridcell"
             ${inMonth ? `data-date="${key}" tabindex="0" aria-label="${d.toDateString()}, ${dayLectures.length ? `${dayLectures.length} lecture${dayLectures.length > 1 ? 's' : ''}` : 'no lectures'}"` : ''}>
          <div class="ex-cal-day"><span class="ex-cal-num">${d.getDate()}</span>${isToday ? '<span class="ex-cal-today">Today</span>' : ''}</div>
          ${shown.map((l) => {
              const [st, label] = calStatus(l.status);
              return `<button type="button" class="ex-cal-ev ex-st-${st}" data-lecture-id="${calEsc(l.lectureId)}" aria-label="${calEsc(`${l.startTime} ${label}`)}">
                  <span class="ex-cal-ev-time">${calEsc(l.startTime)} · ${calEsc(l.lectureType)}</span>
                  <span class="ex-cal-ev-title">${l.class}</span>
                  <i class="ex-cal-badge ex-st-${st}">${calEsc(label)}</i>
                </button>`;
          }).join('')}
          ${more > 0 ? `<button type="button" class="ex-cal-more" data-more="${key}">+${more} more</button>` : ''}
        </div>`;
    }
    html += '</div></div>';
    if (calLoaded && !monthCount) {
        html += `<div class="ex-empty py-4"><div class="ex-empty-icon" aria-hidden="true">${calSvg.calendar.replace(/width="14" height="14"/, 'width="28" height="28"')}</div>
          <p class="ex-empty-title">No lectures in ${monthNames[month]}</p>
          <p class="ex-empty-text">Click a day, or use Schedule a Class, to add one.</p></div>`;
    }
    el.innerHTML = html;

    el.querySelectorAll('.ex-cal-cell[data-date]').forEach((cell) => {
        const open = () => showLectureModal(cell.dataset.date, lectures[cell.dataset.date] || []);
        cell.addEventListener('click', open);
        cell.addEventListener('keydown', (e) => { if (e.target === cell && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); open(); } });
    });
    el.querySelectorAll('.ex-cal-ev').forEach((item) => item.addEventListener('click', (e) => {
        e.stopPropagation();
        bootstrap.Popover.getInstance(item)?.hide();
        showLectureDetails(item.dataset.lectureId);
    }));
    el.querySelectorAll('.ex-cal-more').forEach((b) => b.addEventListener('click', (e) => {
        e.stopPropagation();
        showLectureModal(b.dataset.more, lectures[b.dataset.more] || []);
    }));
}

const prevMonthSchedule = () => {
    const d = new Date(currentSelectedYear, currentSelectedMonth - 1, 1);
    generateCalendar(d.getFullYear(), d.getMonth());
    initLecturePopovers();
};

const nextMonthSchedule = () => {
    const d = new Date(currentSelectedYear, currentSelectedMonth + 1, 1);
    generateCalendar(d.getFullYear(), d.getMonth());
    initLecturePopovers();
};

const todaySchedule = () => {
    generateCalendar(new Date().getFullYear(), new Date().getMonth());
    initLecturePopovers();
};

function showLectureModal(date, dayLectures) {
    const modalDateEl = document.getElementById("eventModalLabel");
    const modalContentEl = document.getElementById("eventDetails");

    // Format the date nicely
    const dateObj = new Date(date);
    const formattedDate = dateObj.toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });

    modalDateEl.textContent = formattedDate;

    const inputDate = new Date(date);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    inputDate.setHours(0, 0, 0, 0);

    const isFutureOrToday = inputDate.getTime() >= today.getTime();

    if (dayLectures.length > 0) {
        modalContentEl.innerHTML = `
        <div class="lecture-cards-container">
            ${dayLectures.map(lecture =>
            `
            <div class="lecture-card ${lecture.status.toLowerCase()} d-flex flex-column">
                <div class="lecture-card-header ${lecture.status.toLowerCase()}">
                    <div class="subject-cover">
                        <img src="${lecture.subjectImage}" 
                             alt="${lecture.subject}"
                             onerror="this.src='https://dashboard.enviiz.com/assets/imgs/common/empty-thumbnail.webp'">
                    </div>
                    <div class="lecturer-meta">
                        <h4>${lecture.subject}</h4>
                        <div class="subject-badge">Lecture Type : ${lecture.lectureType}</div>
                    </div>
                    <div class="lecture-time">${lecture.startTime} - ${lecture.endTime}</div>
                </div>
                <div class="lecture-card-body">
                    <div class="d-flex flex-column">
                        <span class="lecture-time">Lecturer</span>
                        <span class="text-14 man-text">${lecture.lecturerName}</span>
                    </div>
                   
                    <div class="status-indicator">
                        <span class="status-dot ${lecture.status.toLowerCase()}"></span>
                        <span>${lecture.status}</span>
                    </div>
                </div>
               <div class="d-flex justify-content-between align-items-center mb-3 px-3">
               ${lecture.status === 'Ongoing' && (lecture.lectureType === 'Online' || lecture.lectureType === 'Both')
                ? `
                    <button class="table-btn table-btn-success active" onclick="window.open('${lecture.joinLink}', '_blank')">
                        Join Now
                    </button>
                `
                : ''}

                <div class="flex-grow-1"></div>
                <div class="dropdown">
                    <button class="table-btn table-btn-dark-theme dropdown-toggle br-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Action
                    </button>
                    <ul class="dropdown-menu z-3">
                        <li><a class="dropdown-item" href="lecture-details?time-table-id=${lecture.lectureId}">Lecture Details</a></li>
                        <li><a class="dropdown-item" href="mark-attendance?session=${lecture.lectureId}">Mark Attendance</a></li>
                        <li><a class="dropdown-item" href="time-table-attendance?time-table-id=${lecture.lectureId}&class-id=${lecture.classId}">Attendance List</a></li>
                        <li><a class="dropdown-item" href="add-recording?time-table-id=${lecture.lectureId}&class-id=${lecture.classId}">Add Recording</a></li>
                        <li><a class="dropdown-item" href="edit-lecture?time-table-id=${lecture.lectureId}&class-id=${lecture.classId}">Edit Lecture</a></li>
                    </ul>
                </div>
            </div>

            </div>
            `).join('')}
                ${isFutureOrToday ? `
            <button class="custom-btn mt-3 px-4" onclick="window.location.href = 'schedule-class?class-id=${subject}&date=${date}'">
                        Schedule a Lecture
            </button>
            ` : ''}
        </div>`;
    } else {
        modalContentEl.innerHTML = `
        <div class="no-lectures">
            <div class="no-lectures-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="#6c757d" viewBox="0 0 16 16">
                    <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                    <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
                </svg>
            </div>
            <h5>No Lectures Scheduled</h5>
            <p>There are no classes scheduled for this day</p>
            ${isFutureOrToday ?
                isSubjectLectureSchedule ? `
            <button class="custom-btn mt-3 px-4" onclick="window.location.href = 'schedule-class?class-id=${subject}&date=${date}'">
                        Schedule a Lecture
            </button>
            `: `
             <button class="custom-btn mt-3 px-4" onclick="window.location.href = 'schedule-class?date=${date}'">
                        Schedule a Lecture
            </button>
            ` : ''}
           
        </div>`;
    }

    showDayInline();
}
// The classes of a day are shown in a panel under the calendar (not a pop-up), using the same title and list.
function showDayInline() {
    const modal = document.getElementById('eventModal');
    const title = document.getElementById('eventModalLabel');
    const list = document.getElementById('eventDetails');
    if (!modal || !list) return;
    let panel = document.getElementById('eventDayPanel');
    if (!panel) {
        panel = document.createElement('section');
        panel.id = 'eventDayPanel';
        panel.className = 'component-theme p-4 mt-4 ex-day-panel';
        panel.setAttribute('aria-live', 'polite');
        modal.parentNode.insertBefore(panel, modal);
    }
    panel.innerHTML = `<div class="d-flex justify-content-between align-items-center gap-3 mb-3">
        <h2 class="text-18 main-text fw-semibold m-0">${title ? title.textContent : 'Classes'}</h2>
        <button type="button" class="custom-outline-btn px-3" onclick="document.getElementById('eventDayPanel').remove()">Close</button>
    </div>` + list.innerHTML;
    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
}


/** A lecture opens its own page (no pop-up). */
function showLectureDetails(lectureId) {
    location.href = `lecture-details?time-table-id=${encodeURIComponent(lectureId)}`;
}

function initLecturePopovers() {
    const all = Object.values(lectures).flat();
    document.querySelectorAll('.ex-cal-ev').forEach((el) => {
        bootstrap.Popover.getInstance(el)?.dispose();
        const l = all.find((x) => String(x.lectureId) === el.dataset.lectureId);
        if (!l) return;
        const [st, label] = calStatus(l.status);
        const day = new Date(`${Object.keys(lectures).find((k) => lectures[k].includes(l))}T00:00`);
        const content = `
        <div class="ex-cal-pop-card ex-st-${st}">
          <div class="ex-cal-pop-head">
            <img src="${calEsc(l.subjectImage)}" alt="" onerror="this.style.visibility='hidden'">
            <div><div class="ex-cal-pop-title">${l.class}</div><div class="ex-cal-pop-sub">${calEsc(l.title || l.lectureType)}</div></div>
          </div>
          <div class="ex-cal-pop-when">
            <span>${calSvg.calendar} ${day.toLocaleDateString('en-GB', { day: '2-digit', month: 'long', year: 'numeric' })}</span>
            <span>${calSvg.clock} ${calEsc(l.startTime)} - ${calEsc(l.endTime)}</span>
          </div>
          <div class="ex-cal-pop-sec"><div class="ex-cal-pop-label">Tutor</div><div class="ex-cal-pop-val">${calSvg.user} ${calEsc(l.lecturerName)}</div></div>
          <div class="ex-cal-pop-sec"><div class="ex-cal-pop-label">Class</div>
            <div class="ex-cal-pop-chips"><span>${l.subject}</span><span>${l.exam_type} ${l.year}</span><span>${calEsc(l.lectureType)}</span></div></div>
          <div class="ex-cal-pop-foot"><i class="ex-cal-badge ex-st-${st}">${calEsc(label)}</i><span>Click for details</span></div>
        </div>`;
        new bootstrap.Popover(el, {
            trigger: 'hover focus', html: true, placement: 'auto', container: 'body',
            customClass: 'ex-cal-pop', title: '', content, sanitize: false,
        });
    });
}

generateCalendar(now.getFullYear(), now.getMonth());
