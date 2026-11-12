


const initializeThemeTutor = () => {
    // Get theme elements
    const htmlElement = document.documentElement;
    const themeItems = document.querySelectorAll('.theme-item');
    const lightIcon = document.querySelector('.light-theme-icon');
    const darkIcon = document.querySelector('.dark-theme-icon');

    // Theme setting function
    const setTheme = (theme) => {
        // Update UI icons
        if (theme === 'light') {
            lightIcon?.classList.remove('d-none');
            darkIcon?.classList.add('d-none');
        } else {
            lightIcon?.classList.add('d-none');
            darkIcon?.classList.remove('d-none');
        }

        // Update attributes
        htmlElement.setAttribute('data-bs-theme', theme);
        htmlElement.setAttribute('data-custom-theme', theme);

        // Save to localStorage (persists across tabs and sessions)
        localStorage.setItem('theme', theme);

        // Update dropdown UI
        themeItems.forEach(item => {
            const checkIcon = item.querySelector('.theme-check');
            const isActive = item.getAttribute('data-theme') === theme;
            item.classList.toggle('active-theme', isActive);
            checkIcon.style.display = isActive ? 'block' : 'none';
        });
    };

    const initThemeUI = () => {
        const storedTheme = localStorage.getItem('theme');
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: light)').matches;
        const initialTheme = storedTheme || (systemPrefersDark ? 'dark' : 'light');

        setTheme(initialTheme);
        document.body.style.opacity = '1'; // Fade in content
    };

    themeItems.forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            setTheme(e.currentTarget.getAttribute('data-theme'));
        });
    });

    window.addEventListener('storage', (event) => {
        if (event.key === 'theme') {
            setTheme(event.newValue);
        }
    });

    initThemeUI();
};

var messageContent;
const initializeMessages = () => {
    messageContent = new RichTextEditor("#content");

    new TomSelect("#class-selector", {
        plugins: ['remove_button'],
        placeholder: "Select classes...",
        maxItems: null,
        create: false
    });

    const dropdown = document.getElementById("messageSourceDropdown");
    const trigger = document.getElementById("messageSourceTrigger");
    const menu = document.getElementById("messageSourceMenu");
    const hiddenInput = document.getElementById("message-source");

    trigger.addEventListener("click", function () {
        dropdown.classList.toggle("open");
    });

    document.querySelectorAll(".premium-option:not(.premium-locked)").forEach(option => {
        option.addEventListener("click", function () {
            const value = this.dataset.value;
            const icon = this.querySelector("iconify-icon").getAttribute("icon");
            const text = this.querySelector(".option-left span:last-child").textContent;

            hiddenInput.value = value;

            trigger.querySelector(".selected-option").innerHTML = `
    <iconify-icon icon="${icon}" width="18"></iconify-icon>
    <span>${text}</span>
`;

            document.querySelectorAll(".premium-option").forEach(opt => opt.classList.remove("active"));
            this.classList.add("active");

            dropdown.classList.remove("open");
        });
    });

    document.addEventListener("click", function (e) {
        if (!dropdown.contains(e.target)) {
            dropdown.classList.remove("open");
        }
    });
}

const initializeIntergrations = () => {
    const dropdown = document.getElementById("messageSourceDropdown");
    const trigger = document.getElementById("messageSourceTrigger");
    const menu = document.getElementById("messageSourceMenu");
    const hiddenInput = document.getElementById("integration-type");

    trigger.addEventListener("click", function () {
        dropdown.classList.toggle("open");
    });

    document.querySelectorAll(".premium-option:not(.premium-locked)").forEach(option => {
        option.addEventListener("click", function () {
            const value = this.dataset.value;
            const icon = this.querySelector("iconify-icon").getAttribute("icon");
            const text = this.querySelector(".option-left span:last-child").textContent;

            hiddenInput.value = value;

            trigger.querySelector(".selected-option").innerHTML = `
    <iconify-icon icon="${icon}" width="18"></iconify-icon>
    <span>${text}</span>
`;

            document.querySelectorAll(".premium-option").forEach(opt => opt.classList.remove("active"));
            this.classList.add("active");

            dropdown.classList.remove("open");
        });
    });

    document.addEventListener("click", function (e) {
        if (!dropdown.contains(e.target)) {
            dropdown.classList.remove("open");
        }
    });
}

function openEditClassModal(el) {
    const data = JSON.parse(el.dataset.class);

    const subjectWrapper = document.querySelector('[data-input="edit-subject"]');
    const subjectTextInput = subjectWrapper.querySelector('.search-input');
    const subjectHiddenInput = subjectWrapper.querySelector('input[type="hidden"]');

    subjectHiddenInput.value = data.subjects_subject_id;
    subjectTextInput.value = data.subject;
    document.getElementById('edit-year').value = data.year_year_id;
    document.getElementById('edit-exam-type').value = data.exam_type_exam_type_id;
    document.getElementById('edit-medium').value = data.medium_medium_id;

    // Populate text input
    document.getElementById('edit-class-name').value = data.class_name;

    // Handle thumbnail (optional image)
    const coverImg = document.getElementById('edit-class-cover');

    if (data.class_cover && data.class_cover !== '') {
        coverImg.src = '../assets/imgs/classes-covers/' + data.class_cover;
    } else {
        coverImg.src = '../assets/imgs/common/empty-thumbnail.webp';
    }

    // Clear file input (important for edit)
    document.getElementById('edit-cover-input').value = '';

    $("#save-edit").off("click").on("click", function () {
        editClass(this, data.class_id);
    });

    bindExamTypeYearFilter('edit-exam-type', 'edit-year');
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('editClass'));
    modal.show();
}

const initializeAttendance = (present, absent, watched) => {

    function makeDonut(canvasId, valuePct, color) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return;

        // destroy existing instance if re-rendering (safe for ajax)
        if (ctx._chart) ctx._chart.destroy();

        ctx._chart = new Chart(ctx, {
            type: "doughnut",
            data: {
                labels: ["Value", "Rest"],
                datasets: [{
                    data: [valuePct, 100 - valuePct],
                    backgroundColor: [color, "rgba(148,163,184,0.18)"],
                    borderWidth: 0,
                    hoverOffset: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: "78%",
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        enabled: false
                    }
                },
                animation: {
                    duration: 0
                }
            }
        });
    }

    // update center text
    document.getElementById("presentPct").textContent = present + "%";
    document.getElementById("absentPct").textContent = absent + "%";
    document.getElementById("watchPct").textContent = watched + "%";

    // donuts (use your theme vars visually)
    makeDonut("donutPresent", present, getComputedStyle(document.documentElement).getPropertyValue("--dark-green").trim() || "#16a34a");
    makeDonut("donutAbsent", absent, getComputedStyle(document.documentElement).getPropertyValue("--dark-red").trim() || "#ef4444");
    makeDonut("donutWatched", watched, getComputedStyle(document.documentElement).getPropertyValue("--dark-purple").trim() || "#AF52DE");
}

const initializeClassAttendance = (classId) => {

    let classData = {};

    const monthNames = [
        "January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"
    ];

    const now = new Date();
    let calYear = now.getFullYear();
    let calMonth = now.getMonth();

    fetch('api/getConductedClassScheduleData?class=' + classId)
        .then((response) => response.text())
        .then((data) => {
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                classData = response.lectures;
                bindCalendarNav();
                renderCalendar(calYear, calMonth);
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });

    const pad2 = (n) => String(n).padStart(2, "0");
    const toKey = (y, m, d) => `${y}-${pad2(m + 1)}-${pad2(d)}`;

    const proTooltip = document.createElement("div");
    proTooltip.className = "pro-tooltip";
    document.body.appendChild(proTooltip);

    const showTooltip = (x, y, dateKey, data) => {
        proTooltip.innerHTML = `
<div class="tt-title">${data.name}</div>
<div class="tt-row"><span>Date</span><strong>${dateKey}</strong></div>
<div class="tt-row"><span>Time</span><strong>${data.time}</strong></div>
<div class="tt-pill">
<span style="width:8px;height:8px;border-radius:50%;background:#0d6efd;display:inline-block"></span>
Class Day
</div>
`;

        const pad = 12;
        const rect = proTooltip.getBoundingClientRect();
        const w = rect.width || 260;
        const h = rect.height || 140;

        let left = x + 14;
        let top = y - 10;

        if (left + w > window.innerWidth - pad) left = x - w - 14;
        if (top + h > window.innerHeight - pad) top = window.innerHeight - h - pad;
        if (top < pad) top = pad;

        proTooltip.style.left = left + "px";
        proTooltip.style.top = top + "px";
        proTooltip.classList.add("show");
    };

    const hideTooltip = () => {
        proTooltip.classList.remove("show");
    };

    function renderCalendar(year, month) {
        const calTitle = document.getElementById("calTitle");
        const calGrid = document.getElementById("calGrid");

        if (!calTitle || !calGrid) return;

        calTitle.textContent = `${monthNames[month]} ${year}`;
        calGrid.innerHTML = "";

        const first = new Date(year, month, 1);
        const startDay = first.getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const prevMonthDays = new Date(year, month, 0).getDate();
        const totalCells = 42;

        for (let i = 0; i < totalCells; i++) {
            let dNum, dMonth = month,
                dYear = year;
            let isOut = false;

            if (i < startDay) {
                dNum = prevMonthDays - (startDay - 1 - i);
                dMonth = month - 1;
                if (dMonth < 0) {
                    dMonth = 11;
                    dYear--;
                }
                isOut = true;
            } else if (i >= startDay + daysInMonth) {
                dNum = i - (startDay + daysInMonth) + 1;
                dMonth = month + 1;
                if (dMonth > 11) {
                    dMonth = 0;
                    dYear++;
                }
                isOut = true;
            } else {
                dNum = i - startDay + 1;
            }

            const key = toKey(dYear, dMonth, dNum);
            const data = classData[key] || null;
            const hasClass = !!data;


            const cell = document.createElement("div");
            cell.className = `pro-day ${isOut ? "is-out" : ""} ${hasClass ? "has-class" : ""}`;
            cell.dataset.date = key;

            cell.onclick = () => {
                filterClassAttendanceData(data.id, classId);
            }

            cell.innerHTML = `
<span>${dNum}</span>

`;

            if (hasClass) {
                cell.addEventListener("mouseenter", (e) => showTooltip(e.clientX, e.clientY, key, data));
                cell.addEventListener("mousemove", (e) => showTooltip(e.clientX, e.clientY, key, data));
                cell.addEventListener("mouseleave", hideTooltip);
            }

            cell.addEventListener("click", () => {
                filterClassAttendanceData(data.id, classId);
            });

            calGrid.appendChild(cell);
        }
    }

    function bindCalendarNav() {
        const prevBtn = document.getElementById("calPrev");
        const nextBtn = document.getElementById("calNext");

        if (prevBtn) {
            prevBtn.onclick = () => {
                calMonth--;
                if (calMonth < 0) {
                    calMonth = 11;
                    calYear--;
                }
                renderCalendar(calYear, calMonth);
            };
        }

        if (nextBtn) {
            nextBtn.onclick = () => {
                calMonth++;
                if (calMonth > 11) {
                    calMonth = 0;
                    calYear++;
                }
                renderCalendar(calYear, calMonth);
            };
        }
    }



}

var selectedDays = [];

function dateChipBoxAction(clickedElement) {
    const chip = $(clickedElement);
    const day = chip.data('day-id');

    chip.toggleClass('selected');

    if (chip.hasClass('selected')) {
        selectedDays.push(day);
    } else {
        selectedDays = selectedDays.filter(d => d !== day);
    }

};

function initializeAddTute() {
    $("#payment-check").on("change", function () {
        if ($(this).is(":checked")) {
            $("#payment-area").removeClass("d-none");
        } else {
            $("#payment-area").addClass("d-none");
        }
    });

    $("#online-check-box").on("change", function () {
        if ($(this).is(":checked")) {
            $("#courier-check-box").prop("checked", false);
            $("#attachment-area").removeClass("d-none");
            $("#tute-title-wrap").addClass("d-none");
        } else {
            $("#courier-check-box").prop("checked", true);
        }
    });

    $("#courier-check-box").on("change", function () {
        if ($(this).is(":checked")) {
            $("#online-check-box").prop("checked", false);
            $("#tute-title-wrap").removeClass("d-none");
            $("#attachment-area").addClass("d-none");
        } else {
            $("#online-check-box").prop("checked", true);
        }
    });


    const wrap = document.getElementById("tute-title-wrap");

    function refreshAddButtons() {
        const rows = wrap.querySelectorAll(".input-plus-row");
        rows.forEach((row, idx) => {
            const btn = row.querySelector(".input-plus-btn");
            btn.style.display = (idx === rows.length - 1) ? "grid" : "none";
        });
    }

    wrap.addEventListener("click", (e) => {
        const btn = e.target.closest(".input-plus-btn");
        if (!btn) return;

        const row = document.createElement("div");
        row.className = "input-plus-row mt-2";
        row.innerHTML = `
    <input type="text" class="form-control custom-input" name="title[]" placeholder="Enter title">
    <button type="button" class="input-plus-btn" aria-label="Add title">
      <iconify-icon icon="ic:round-add" width="20"></iconify-icon>
    </button>
  `;

        // ✅ insert before feedback inside THIS wrap (safe)
        const feedback = wrap.querySelector("#title-feedback");
        if (feedback) {
            wrap.insertBefore(row, feedback);
        } else {
            wrap.appendChild(row); // fallback
        }

        refreshAddButtons();
    });

    refreshAddButtons();

    new CreateInfo({
        target: '#tute-payment-guide-info',
        title: 'Payment Guide',
        placement: 'right',

        steps: [],

        note: ' If enabled, you must select the payment month below. Only students with a valid payment for that month will be able to access this tute.',

        youtubeLink: '',
        videoTitle: ''
    });

    new CreateInfo({
        target: '#tute-titles-guide-info',
        title: 'Courier Tute Guide',
        placement: 'right',
        steps: [],
        note: 'Add the titles of the tutes that will be delivered via courier.At least one title is required.',


        youtubeLink: '',
        videoTitle: ''
    });

}

const initializeAddStudent = () => {
    const firstNameInput = document.getElementById("first-name");
    const lastNameInput = document.getElementById("last-name");
    const fullNameInput = document.getElementById("full-name");
    const initialsInput = document.getElementById("name-with-initials");

    function updateNames() {

        const firstName = firstNameInput.value.trim();
        const lastName = lastNameInput.value.trim();

        if (firstName || lastName) {
            fullNameInput.value = (firstName + " " + lastName).trim();
        } else {
            fullNameInput.value = "";
        }

        if (firstName && lastName) {

            const parts = firstName.split(/\s+/);

            const initials = parts
                .filter(p => p.length > 0)
                .map(p => p.charAt(0).toUpperCase() + ".")
                .join(" ");
            initialsInput.value = initials + " " + lastName;
        } else {
            initialsInput.value = "";
        }
    }

    // Trigger on typing
    firstNameInput.addEventListener("input", updateNames);
    lastNameInput.addEventListener("input", updateNames);

    $("#guardian-whatsapp").on('change', function () {
        $("#gurdian-details").hasClass('d-none') ? '' : $("#gurdian-details").addClass('d-none');
        $("#gurdian-sub-topic").text('Please enter guardian details');
        $("#grelationship-selector").val('').prop("disabled", false);
        $("#gtitle-selector").val('').prop("disabled", false);
        $("#guardian-fname").val('').prop("disabled", false);
        $("#guardian-lname").val('').prop("disabled", false);
        $("#guardian-email").val('').prop("disabled", false);
        $("#guardian-mobile").val('').prop("disabled", false);
    });

    new CreateInfo({
        target: '#add-gurdian-guide-info',
        title: 'How to Add a Guardian',
        placement: 'right',
        steps: [
            'Type the guardian WhatsApp number in the search field',
            'Click the Search button',
            'If the guardian is already registered, the parent details will load automatically',
            'If the guardian is not registered, fill in the guardian information in the provided fields',
            'After entering the details, click Save Changes to add the guardian'
        ],
        note: 'Use the guardian WhatsApp number to quickly find existing parent records.',
        youtubeLink: '',
        videoTitle: ''
    });
}

function trimTextByWidth() {
    const items = document.querySelectorAll(".js-trim");

    const w = window.innerWidth;
    const max = (w < 1750 && w > 998) ? 30 : 1760;
    items.forEach(el => {
        const full = el.dataset.fullTitle || el.textContent.trim();
        el.textContent = full.length > max ? full.slice(0, max - 1).trim() + "…" : full;
    });
}

window.addEventListener("resize", trimTextByWidth);
document.addEventListener("DOMContentLoaded", trimTextByWidth);


const initializePayments = async () => {
    console.log("initializePayments");

    await Promise.all([
        loadMonthlyPaymentsData(),
        loadPaymentsSummary()
    ]);

}

const loadMonthlyPaymentsData = (id) => {
    return new Promise((resolve) => {
        fetch(`api/loadMonthlyPaymentsData.php`)
            .then((response) => response.text())
            .then((data) => {
                console.log(data);
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                } else {
                    setLineChart({
                        chart: "incomeLineChart",
                        labels: response.labels,
                        label: 'Income',
                        data: response.values,
                    });
                }
            })
            .catch((error) => {
                console.log(error);
                setTimeout(() => { showToast("error", "Something went wrong. Please try again."); }, 1000);
            });
        setTimeout(() => {
            resolve(); // Resolve when done
        }, 1000);
    });

}

const loadPaymentsSummary = () => {
    const payment = $("#payRange").val();
    return new Promise((resolve) => {
        fetch(`api/loadPaymentsSummary.php?payment=${payment}`)
            .then((response) => response.text())
            .then((data) => {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                } else {
                    $("#invoiceTotal").text('LKR ' + response.values[0]);
                    setDoughnutChart({
                        chart: 'invoiceSemiDonut',
                        labels: response.labels,
                        label: 'LKR',
                        data: response.values, // Data for the chart
                        backgroundColor: [chartGreenBlue, chartYellow, chart_color_2, chartRed], // Custom background colors

                    });
                }
            })
            .catch((error) => {
                console.log(error);
                setTimeout(() => { showToast("error", "Something went wrong. Please try again."); }, 1000);
            });
        setTimeout(() => {
            resolve(); // Resolve when done
        }, 1000);
    });
}


function filterConductedClasses(selectedDate) {
    const select = document.getElementById("class");
    const options = select.querySelectorAll("option");

    // reset select to default
    select.value = "0";

    options.forEach((opt, index) => {
        if (index === 0) return; // keep "- Select Class -"

        const optDate = opt.getAttribute("data-date");

        // show only matching date
        opt.hidden = (optDate !== selectedDate);
        $("#title").val('');

        if ($("#class").prop("disabled")) {
            $("#class").prop("disabled", false);
        }
    });
}

function setRecordingTitle(select) {

    const selectedOption = select.options[select.selectedIndex];

    if (select.value == "0") {
        $("#title").val('');
        return;
    }

    const lectureTitle = selectedOption.getAttribute("data-title");
    const lectureDuration = selectedOption.getAttribute("data-duration");
    console.log(lectureDuration);
    $("#title").val(lectureTitle + " - Recording");
    $("#duration").val(lectureDuration);

}
const updateClassStudentStatus = (update, id, classId) => {
    selectedUsers = [];
    selectedUsers.push(id);
    unSelectedUsers = [];
    applySelectedClassStudentUpdate(update, classId);
}

const openDeletepreRecordingModal = (recordingid) => {
    $("#confirmRemove").off("click");
    $("#confirmRemove").on("click", () => {
        removePreRecording(recordingid);
    });
    $("#removeConfirmModal").modal("show");
}

const openDeleteRecordingModal = (recordingid) => {
    $("#confirmRemove").off("click");
    $("#confirmRemove").on("click", () => {
        removeRecording(recordingid);
    });
    $("#removeConfirmModal").modal("show");
}

const handleClassLectureAttendancePagination = (lectureId, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByName':
            sortCondition2 = 0;
            sortCondition1 = sortCondition1 === 1 ? 2 : 1;
            break;
        case 'sortById':
            sortCondition2 = sortCondition2 === 1 ? 2 : 1;
            sortCondition1 = 0;
            break;
    }

    filterClassLectureAttendance(lectureId);
}

const initializeAddPreRecording = () => {
    $("#youtube-check-box").on("change", function () {
        if ($(this).is(":checked")) {
            $("#video-check-box").prop("checked", false);
            $("#youtube-link-area").removeClass("d-none");
            $("#video-area").addClass("d-none");
        } else {
            $("#video-check-box").prop("checked", true);
            $("#youtube-link-area").addClass("d-none");
            $("#video-area").removeClass("d-none");
        }

    });

    $("#video-check-box").on("change", function () {

        if ($(this).is(":checked")) {
            $("#youtube-check-box").prop("checked", false);
            $("#video-area").removeClass("d-none");
            $("#youtube-link-area").addClass("d-none");
        } else {
            $("#youtube-check-box").prop("checked", true);
            $("#video-area").addClass("d-none");
            $("#youtube-link-area").removeClass("d-none");
        }
    });

    new CreateInfo({
        target: '#youtube-guide-info',
        title: 'Adding a YouTube Video (Embed Link)',
        placement: 'right',
        steps: [
            'Open the video you want to use on YouTube.',
            'Click the "Share" button below the video.',
            'Select the "Embed" option.',
            'Copy the iframe embed code provided by YouTube.',
            'Paste the copied iframe code into the field above.'
        ],
        note: 'Make sure you paste the full iframe embed code. Normal YouTube video links will not work in this field.',
        youtubeLink: '',
        videoTitle: ''
    });

    new CreateInfo({
        target: '#duration-guide-info',
        title: 'Class Duration (Minutes)',
        placement: 'right',
        steps: [],
        note: 'Enter the total duration of the class in minutes. For example: 1 hour = 60 minutes.',

        youtubeLink: '',
        videoTitle: ''
    });

    new CreateInfo({
        target: '#secured-guide-info',
        title: 'Secured Content Protection',
        placement: 'right',

        note: 'Secured content can only be viewed within the platform. Students are not able to download the video, copy the file link, or share it outside the system. This helps protect your lecture materials from unauthorized distribution.',

        youtubeLink: '',
        videoTitle: ''
    });

    new CreateInfo({
        target: '#unsecured-guide-info',
        title: 'Unsecured Content (YouTube Embed)',
        placement: 'right',

        note: 'Unsecured content is delivered using a YouTube iframe embed. The video is streamed directly from YouTube and displayed within the platform. Since the video is hosted externally, it may be accessed or shared through the original YouTube link.',

        youtubeLink: '',
        videoTitle: ''
    });
}

const initializeAddRecording = () => {
    $("#youtube-check-box").on("change", function () {
        if ($(this).is(":checked")) {
            $("#video-check-box").prop("checked", false);
            $("#youtube-link-area").removeClass("d-none");
            $("#video-area").addClass("d-none");
        } else {
            $("#video-check-box").prop("checked", true);
            $("#youtube-link-area").addClass("d-none");
            $("#video-area").removeClass("d-none");
        }

    });

    $("#video-check-box").on("change", function () {

        if ($(this).is(":checked")) {
            $("#youtube-check-box").prop("checked", false);
            $("#video-area").removeClass("d-none");
            $("#youtube-link-area").addClass("d-none");
        } else {
            $("#youtube-check-box").prop("checked", true);
            $("#video-area").addClass("d-none");
            $("#youtube-link-area").removeClass("d-none");
        }
    });

    new CreateInfo({
        target: '#youtube-guide-info',
        title: 'Adding a YouTube Video (Embed Link)',
        placement: 'right',
        steps: [
            'Open the video you want to use on YouTube.',
            'Click the "Share" button below the video.',
            'Select the "Embed" option.',
            'Copy the iframe embed code provided by YouTube.',
            'Paste the copied iframe code into the field above.'
        ],
        note: 'Make sure you paste the full iframe embed code. Normal YouTube video links will not work in this field.',
        youtubeLink: '',
        videoTitle: ''
    });

    new CreateInfo({
        target: '#duration-guide-info',
        title: 'Class Duration (Minutes)',
        placement: 'right',
        steps: [],
        note: 'Enter the total duration of the class in minutes. For example: 1 hour = 60 minutes.',

        youtubeLink: '',
        videoTitle: ''
    });

    new CreateInfo({
        target: '#secured-guide-info',
        title: 'Secured Content Protection',
        placement: 'right',

        note: 'Secured content can only be viewed within the platform. Students are not able to download the video, copy the file link, or share it outside the system. This helps protect your lecture materials from unauthorized distribution.',

        youtubeLink: '',
        videoTitle: ''
    });

    new CreateInfo({
        target: '#unsecured-guide-info',
        title: 'Unsecured Content (YouTube Embed)',
        placement: 'right',

        note: 'Unsecured content is delivered using a YouTube iframe embed. The video is streamed directly from YouTube and displayed within the platform. Since the video is hosted externally, it may be accessed or shared through the original YouTube link.',

        youtubeLink: '',
        videoTitle: ''
    });

    new CreateInfo({
        target: '#expire-date-guide-info',
        title: 'Recording Expiry Date',
        placement: 'right',

        note: 'Set an expiry date to limit how long students can access this lecture or recording. After the selected date, the content will no longer be available to students.',

        youtubeLink: '',
        videoTitle: ''
    });

    new CreateInfo({
        target: '#select-date-guide-info',
        title: 'Select Class Date',
        placement: 'right',

        steps: [
            'Choose the date when the class was conducted.',
            'The system will automatically load the classes scheduled for that day.',
            'Select the relevant class from the list.',
            'Upload the recording for the selected class to continue.'
        ],

        note: 'Make sure the selected date matches the actual class date to attach the recording correctly.',

        youtubeLink: '',
        videoTitle: ''
    });
}


const handleStudentsClassPaymentsPagination = (lectureId, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByName':
            sortCondition2 = sortCondition3 = 0;
            sortCondition1 = sortCondition1 === 1 ? 2 : 1;
            break;
        case 'sortById':
            sortCondition2 = sortCondition2 === 1 ? 2 : 1;
            sortCondition1 = sortCondition3 = 0;
            break;
        case 'sortByDate':
            sortCondition3 = sortCondition3 === 1 ? 2 : 1;
            sortCondition2 = sortCondition3 = 0;
            break;
    }

    filterStudentsClassPayments(lectureId);
}

const initializeEditPaymentPlan = () => {
    new CreateInfo({
        target: '#due-date-info',
        title: 'Payment Due Date',
        placement: 'right',
        steps: [],
        note: 'Enter the monthly payment due date (e.g., 25, 16). This means the payment must be completed before that date each month.',
        youtubeLink: '',
        videoTitle: ''
    });
}

const initializeScheduleLecture = () => {
    $("#physical-check-box").on("change", function () {
        if ($(this).is(":checked")) {
            $("#online-check-box").prop("checked", false);
        } else {
            $("#online-check-box").prop("checked", true);
        }
        if ($("#lecture-link-area").hasClass("d-none") == false) {
            $("#lecture-link-area").addClass("d-none");
        }
    });

    $("#online-check-box").on("change", function () {
        if ($(this).is(":checked")) {
            $("#physical-check-box").prop("checked", false);
            $("#lecture-link-area").removeClass("d-none");
        } else {
            $("#physical-check-box").prop("checked", true);
            $("#lecture-link-area").addClass("d-none");
        }
    });

    $("#oneTimeLecture").on("change", function () {
        if ($(this).is(":checked")) {
            $("#recurringLecture").prop("checked", false);
            $("#onetimeLectureArea").removeClass("d-none");
        } else {
            $("#recurringLecture").prop("checked", true);
            $("#onetimeLectureArea").addClass("d-none");
        }
        if ($("#recurringLectureArea").hasClass("d-none") == false) {
            $("#recurringLectureArea").addClass("d-none");
        }

        if ($("#lectureTypeArea").hasClass("d-none")) {
            $("#lectureTypeArea").removeClass("d-none");
        }

    });

    $("#recurringLecture").on("change", function () {
        if ($(this).is(":checked")) {
            $("#oneTimeLecture").prop("checked", false);
            $("#recurringLectureArea").removeClass("d-none");
        } else {
            $("#oneTimeLecture").prop("checked", true);
            $("#recurringLectureArea").addClass("d-none");
        }
        if ($("#onetimeLectureArea").hasClass("d-none") == false) {
            $("#onetimeLectureArea").addClass("d-none");
        }
        if ($("#lectureTypeArea").hasClass("d-none")) {
            $("#lectureTypeArea").removeClass("d-none");
        }
    });

    // const today = new Date().toISOString().split("T")[0];
    // document.getElementById('lecture-date').setAttribute("min", today);

    // const now = new Date();
    // const currentTime = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    // //console.log(currentTime);

    // document.getElementById('start-time').setAttribute("min", currentTime);
    // document.getElementById('end-time').setAttribute("min", currentTime);

    new CreateInfo({
        target: '#add-recurring-lecture-guide-info',
        title: 'Creating a Recurring Lecture',
        placement: 'right',

        steps: [
            'Choose the lecture start date and end date.',
            'Set the lecture start time and end time.',
            'Select the weekdays on which the lecture should repeat (e.g., Monday, Wednesday).',
            'The system will automatically generate lectures for the selected days within the date range.',
            'Exclude special days such as Poya days or public holidays if necessary.',
            'Use the date checkboxes to skip specific sessions.'
        ],

        note: 'All lectures will be scheduled automatically according to the selected date range, time, and weekdays. You can review or modify individual sessions later if needed.',

        youtubeLink: '',
        videoTitle: ''
    });
}

const handleStudentsPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByName':
            sortCondition2 = 0;
            sortCondition1 = sortCondition1 === 1 ? 2 : 1;
            break;
        case 'sortByDate':
            sortCondition1 = 0;
            sortCondition2 = sortCondition2 == 1 ? 2 : 1;
            break;
    }

    filterStudents();
}

const handleStudentsPaymentsPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByName':
            sortCondition2 = sortCondition3 = 0;
            sortCondition1 = sortCondition1 === 1 ? 2 : 1;
            break;
        case 'sortByDate':
            sortCondition1 = sortCondition3 = 0;
            sortCondition2 = sortCondition2 == 1 ? 2 : 1;
            break;
    }

    filterStudentsPayments();
}

const handleAttendancePagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByName':
            sortCondition2 = 0;
            sortCondition1 = sortCondition1 === 1 ? 2 : 1;
            break;
        case 'sortByActive':
            sortCondition1 = 0;
            sortCondition2 = sortCondition2 == 1 ? 2 : 1;
            break;
    }

    filterAttendance();
}

const handleDownloadableTuteAccessStudentsPagination = (id, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByName':
            sortCondition1 = sortCondition1 === 1 ? 2 : 1;
            break;

    }

    filterDownloadableTuteAccessStudents(id);
}

const initializeCheckout = () => {
    const paymentSlipInput = document.getElementById("payment-slip");
    const selectedFileView = document.getElementById("selectedFileView");
    const selectedFileName = document.getElementById("selectedFileName");
    const selectedFileMeta = document.getElementById("selectedFileMeta");

    paymentSlipInput.addEventListener("change", function () {
        const file = this.files[0];
        if (!file) return;

        selectedFileView.classList.remove("d-none");
        selectedFileName.textContent = file.name;
        selectedFileMeta.textContent = formatFileSize(file.size);
    });

    function removeSelectedPaymentFile() {
        paymentSlipInput.value = "";
        selectedFileView.classList.add("d-none");
        selectedFileName.textContent = "";
        selectedFileMeta.textContent = "";
    }

    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + " B";
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + " KB";
        return (bytes / (1024 * 1024)).toFixed(2) + " MB";
    }

    function submitPlanPayment(button) {
        const amount = document.getElementById("plan-payment-amount").value.trim();
        const reference = document.getElementById("transfer-reference").value.trim();
        const slip = paymentSlipInput.files[0];

        document.getElementById("plan-payment-amount-feedback").textContent = "";
        document.getElementById("transfer-reference-feedback").textContent = "";
        document.getElementById("payment-slip-feedback").textContent = "";

        let hasError = false;

        if (!amount) {
            document.getElementById("plan-payment-amount-feedback").textContent = "Please enter transfer amount";
            hasError = true;
        }

        if (!reference) {
            document.getElementById("transfer-reference-feedback").textContent = "Please enter transfer reference";
            hasError = true;
        }

        if (!slip) {
            document.getElementById("payment-slip-feedback").textContent = "Please upload payment slip";
            hasError = true;
        }

        if (hasError) return;

        button.disabled = true;
        button.innerHTML = `
<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
Processing Payment
`;

    }
}

function formatCurrency(value) {
    return "LKR " + Number(value).toLocaleString();
}

function chnagePeriodPrice() {
    const periodSelect = $("#payment-period");
    const selectedOption = periodSelect.find("option:selected");

    const periodId = selectedOption.val();
    const periodName = selectedOption.data("period");
    const selectedPrice = Number(selectedOption.data("price"));

    const saveBadge = $("#saveBadge");
    const currentPeriodPrice = $("#currentPeriodPrice");
    const oldPriceLine = $("#oldPriceLine");
    const oldPeriodPrice = $("#oldPeriodPrice");

    const selectedBillingText = $("#selectedBillingText");
    const selectedPlanPriceText = $("#selectedPlanPriceText");
    const selectedTotalAmountText = $("#selectedTotalAmountText");

    const bankBillingPeriod = $("#bankBillingPeriod");
    const bankPayableAmount = $("#bankPayableAmount");

    const amountInput = $("#plan-payment-amount");

    let totalAmount = selectedPrice;
    let billingText = periodName;

    if (periodId == "3") {
        totalAmount = selectedPrice * 12;

        currentPeriodPrice.text(formatCurrency(selectedPrice));
        oldPeriodPrice.text(formatCurrency(monthPrice) + "/mo");

        oldPriceLine.show();

        const saveAmount = (monthPrice * 12) - (selectedPrice * 12);
        saveBadge.text("Save " + formatCurrency(saveAmount)).css("display", "inline-flex");

        billingText = "Yearly";
        bankBillingPeriod.text("12 Months");
        selectedPlanPriceText.text(formatCurrency(selectedPrice) + "/mo");
    } else {
        totalAmount = selectedPrice;

        currentPeriodPrice.text(formatCurrency(selectedPrice));

        oldPriceLine.hide();
        saveBadge.hide();

        billingText = "Monthly";
        bankBillingPeriod.text("1 Month");
        selectedPlanPriceText.text(formatCurrency(selectedPrice) + "/mo");
    }

    if (selectedBillingText.length) {
        selectedBillingText.text(billingText);
    }

    if (selectedTotalAmountText.length) {
        selectedTotalAmountText.text(formatCurrency(totalAmount));
    }

    if (bankPayableAmount.length) {
        bankPayableAmount.text(formatCurrency(totalAmount));
    }

    if (amountInput.length) {
        amountInput.val(totalAmount);
    }
}

let currentRenewData = null;

function formatLKR(amount) {
    return "LKR " + Number(amount).toLocaleString();
}

function handleSubscriptionRenewal(data) {
    currentRenewData = data;

    $("#renewModalTitle").text("Renew your " + data.plan);
    $("#renewPlanName").text(data.plan);
    $("#renewExpireDate").text(data.expireDate);

    $("#completeRenewBtn").off("click").on("click", function () {
        // const planName = $("#renewPlanName").text();
        // const period = $("#renewPeriod").val() == "12" ? "12 Months" : "1 Month";
        // const totalAmount = $("#renewTotalPrice").text();

        // $("#bankPlanName").text(planName);
        // $("#bankPlanPeriod").text(period);
        // $("#bankTotalAmount").text(totalAmount);

        // // hide first modal
        // $("#renewSubscriptionModal").modal("hide");

        // // show bank modal
        // setTimeout(function () {
        //     $("#bankPaymentModal").modal("show");
        // }, 100);

        // $("#confirmPaymentBtn").off("click").on("click", function () {
        //     confirmSubscriptionRenewal(this, currentRenewData.planID);
        // });
        confirmSubscriptionRenewal(this, currentRenewData.planID);
    })

    // default yearly selected
    $("#renewPeriod").val("12");

    updateRenewModalAmounts();

    const modal = new bootstrap.Modal(document.getElementById("renewSubscriptionModal"));
    modal.show();
}

function updateRenewModalAmounts() {
    if (!currentRenewData) return;

    const selectedPeriod = $("#renewPeriod").val();

    const monthAmount = Number(currentRenewData.monthAmount);
    const yearAmount = Number(currentRenewData.yearAmount);

    if (selectedPeriod === "12") {
        const yearlyTotal = yearAmount * 12;
        const normalYearlyTotal = monthAmount * 12;
        const saveAmount = normalYearlyTotal - yearlyTotal;

        $("#saveBadge").text("Save " + formatLKR(saveAmount)).show();
        $("#oldPrice").text(formatLKR(monthAmount) + "/mo").show();

        $("#currentPrice").text(Number(yearAmount).toLocaleString());

        $("#renewPlanPeriodText").text("(12 months)");
        $("#renewSummaryOldPrice").text(formatLKR(normalYearlyTotal)).show();
        $("#renewSummaryNewPrice").text(formatLKR(yearlyTotal));
        $("#renewTotalPrice").text(formatLKR(yearlyTotal));
    } else {
        const monthlyTotal = monthAmount;

        $("#saveBadge").hide();
        $("#oldPrice").hide();

        $("#currentPrice").text(Number(monthAmount).toLocaleString());

        $("#renewPlanPeriodText").text("(1 month)");
        $("#renewSummaryOldPrice").hide();
        $("#renewSummaryNewPrice").text(formatLKR(monthlyTotal));
        $("#renewTotalPrice").text(formatLKR(monthlyTotal));
    }
}

function openDisconnectModal(integrationId) {
    document.getElementById("disconnectIntegrationId").value = integrationId;

    const modal = new bootstrap.Modal(
        document.getElementById("disconnectIntegrationModal")
    );

    modal.show();
}

const handleClassStudentsPagination = (id, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByName':
            sortCondition2 = sortCondition3 = 0;
            sortCondition1 = sortCondition1 === 1 ? 2 : 1;
            break;
        case 'sortByDate':
            sortCondition1 = sortCondition3 = 0;
            sortCondition2 = sortCondition2 == 1 ? 2 : 1;
            break;
    }

    filterClassStudents(id);
}

