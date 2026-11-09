const downloadTute = (attachment, name) => {
    if (!attachment) return;

    const basePath = '../assets/uploads/lectures-attachments/';
    const fileUrl = basePath + encodeURIComponent(attachment);

    const link = document.createElement('a');
    link.href = fileUrl;

    link.download = name ? name : attachment;

    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};


const previewTute = (attachment, name) => {
    if (!attachment) return;

    const basePath = '../assets/uploads/lectures-attachments/';
    const fileUrl = basePath + encodeURIComponent(attachment);

    const win = window.open(fileUrl, '_blank', 'noopener,noreferrer');
    if (win && name) {
        win.document.title = name;
    }
};


const initializeDshboard = () => {
    const lineCtx = document.getElementById('deliveryLineChart');

    new Chart(lineCtx, {
        type: 'line',
        data: {
            labels: ['1 Feb', '5 Feb', '10 Feb', '15 Feb', '20 Feb', '25 Feb'],
            datasets: [{
                label: 'Attended',
                data: [0, 0, 0, 0, 0, 0, 0],
                borderColor: '#28a745',
                backgroundColor: 'rgba(40,167,69,0.1)',
                tension: 0.4,
                fill: true,
                pointRadius: 4,
                pointHoverRadius: 6
            },
            {
                label: 'Absented',
                data: [0, 0, 0, 0, 0, 0, 0],
                borderColor: '#FF0000',
                backgroundColor: 'rgba(255, 69, 45, 0.08)',
                tension: 0.4,
                fill: true,
                pointRadius: 4,
                pointHoverRadius: 6
            }
            ]
        },
        options: {
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 15
                    }
                },
                tooltip: {
                    backgroundColor: '#111827'
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    }
                },
                y: {
                    grid: {
                        color: 'rgba(0,0,0,0.05)'
                    },
                    beginAtZero: true
                }
            },
            animation: {
                duration: 0
            }
        }
    });

    const classData = {
        "2026-02-04": {
            name: "Grade 11 English",
            time: "09:00 AM"
        },
        "2026-02-16": {
            name: "ICT Class",
            time: "11:00 AM"
        },
        "2026-02-20": {
            name: "Science Physical",
            time: "02:00 PM"
        },
        "2026-05-19": {
            name: "Math Revision",
            time: "08:30 AM"
        },
        "2026-05-23": {
            name: "Physics Online",
            time: "01:00 PM"
        },
    };

    const monthNames = [
        "January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"
    ];

    // ===== state =====
    const now = new Date();
    let calYear = now.getFullYear();
    let calMonth = now.getMonth();

    // ===== helpers =====
    const pad2 = (n) => String(n).padStart(2, "0");
    const toKey = (y, m, d) => `${y}-${pad2(m + 1)}-${pad2(d)}`;

    // ===== tooltip (one element) =====
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

        // keep tooltip inside viewport
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

    // ===== render =====
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

            // If you want remove chip, delete the pro-events section
            cell.innerHTML = `
<span>${dNum}</span>



`;

            // Hover tooltip only if has class
            if (hasClass) {
                cell.addEventListener("mouseenter", (e) => showTooltip(e.clientX, e.clientY, key, data));
                cell.addEventListener("mousemove", (e) => showTooltip(e.clientX, e.clientY, key, data));
                cell.addEventListener("mouseleave", hideTooltip);
            }

            // Click (you can open modal)
            cell.addEventListener("click", () => {
                console.log("Clicked:", key, data);
                // showLectureModal(key, hasClass ? [data] : []);
            });

            calGrid.appendChild(cell);
        }
    }

    // ===== navigation binding =====
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

    bindCalendarNav();
    renderCalendar(calYear, calMonth);
}

const viewPaymentSlip = (slipName) => {
    const url = "../assets/uploads/payment-slips/" + slipName;
    window.open(url, '_blank');
}


