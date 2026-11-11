# Attendance: settings, marking and dashboard

Asked 2026-11-11: an Attendance Settings page with QR code, barcode and manual marking (more than one can be on),
the scanned student's details including fees still to pay, and an attendance dashboard filtered by class and by
day, week, month or a custom date range.

## Step A (this PR): preview on sample data

Pages (shared tutor frame, V3 look, Solar icons, no borders):

| Page | What it does |
| --- | --- |
| `tutor/attendance-dashboard.php` | Day / Week / Month / Custom range + arrows, class picker. Live class strip, six stat cards (rate with trend, class days held, present, late, absent, watched recording), Attendance by Day chart (by class for one day, by week for ranges over 31 days), status donut, class rates, Students to Follow Up (missed 2+ in a row, with fees owed), tabs Class Days / Students with search, filters and CSV export. |
| `tutor/mark-attendance.php` | No class day: today's classes (Start / Continue / Opens at ...) and the last 7 days to correct. A class day: context strip, stat cards, tabs for the ways switched on (QR Code, Barcode, Manual), the Scanned Student card and Recent Scans, Finish Class (rest = Absent). |
| `tutor/attendance-settings.php` | Ways to Mark (tick QR / Barcode / Manual, at least one, which tab opens first), When a Student Is Scanned (what the card shows, students who owe fees: mark and show / ask / do not mark until paid, count as owing, minimum months, sound) with a live preview, Times & Rules (opens before, closes after, late after, same card ignored for, mark the rest Absent), Activity Log. |

Scanned Student card: banner (Marked Present / Late / Already Marked / Fees Due: Mark Present? / Not Marked /
Not in This Class / Not Found), name + Student ID + classes + how and when marked, Fees Due (total, month chips,
slips waiting) with Record Payment (the Student Fees dialog), Fee Card and a WhatsApp reminder, and this month's
attendance (attended of held, last came, missed in a row).

Code: `assets/js/enlixo/mock/AttendanceMock.js` answers the API below in the browser. It reuses the shared
`FeesMock` world (`FeesMock.shared()`), so the fees on the scan card are the same as on Student Fees and a payment
recorded from the card clears them. `attendance-common.js` (chips, period maths, scan card), one script per page,
`assets/css/enlixo-attendance.css`.

## Step B: real data

- Migration `009_attendance.sql` (additive): `tutor_attendance_settings` (JSON per tutor), `attendance_scans`
  (every scan / mark: time table id, student, method QR / BARCODE / MANUAL / ZOOM, result, late minutes, by user),
  `attendance_setting_logs`. The legacy `attendance` row stays the one result per student per class day
  (status 1 joined / 2 present / 3 absent / 4 watched) plus a new nullable `late_minutes` column.
- `src/Attendance/` (Settings, Marker, Overview, Roster) and `/api/v1/tutor/attendance/...` with the same routes as the mock:
  `overview`, `sessions`, `sessions/{id}`, `sessions/{id}/scan`, `sessions/{id}/mark`, `sessions/{id}/finish`,
  `sessions/{id}/students/{student}`, `students`, `today`, and `GET|PUT /tutor/attendance-settings`.
- Fees on the card come from `src/Fees` (the student fees work), never from attendance code.
- Real QR camera with a local copy of html5-qrcode (CDNs are not used); barcode scanners need nothing.
- Cron: after a class ends, mark the rest Absent when the setting is on.
- Plan locks: marking on every plan; Attendance Analytics charts and Export on GROW and up (`reports.attendance`, `attendance.export`).

## Open questions

1. Students who owe fees: default "Ask before marking"? Should an assistant be allowed to press "Mark Present Anyway" when it is "Do not mark until paid", or only the tutor?
2. QR / barcode content = the Student ID (ST02405). OK, or a separate card number that can be replaced when a card is lost?
3. Student ID cards: the card in the app is GROW (`id.digital`). Should START tutors print cards with QR and barcode, so QR marking works on every plan?
4. Late: counted as attended (as now)? Should 3 lates count as 1 absent?
5. Online classes: Zoom join = Joined Online; watching the recording later = Watched Recording, not counted as attended. Correct?
6. Who may correct a finished class day, and for how many days?
