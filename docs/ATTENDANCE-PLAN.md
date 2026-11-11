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
| `tutor/attendance-settings.php` | Ways to Mark (tick QR / Barcode / Manual, at least one, which tab opens first), When a Student Is Scanned (what the card shows, students who owe fees: mark and show / ask / do not mark until paid, count as owing, minimum months, only the tutor may override, sound) with a live preview, Student IDs & Cards (Student ID format on every plan: letters, digits, next number, new students only; ID cards on GROW: make cards for one class or all students and print them on A4; START: a locked sample card), Times & Rules (opens before, closes after, late after, same card ignored for, mark the rest Absent), Activity Log. |

Scanned Student card: banner (Marked Present / Late / Already Marked / Fees Due: Mark Present? / Not Marked /
Not in This Class / Not Found), name + Student ID + classes + how and when marked, Fees Due (total, month chips,
slips waiting) with Record Payment (the Student Fees dialog), Fee Card and a WhatsApp reminder, and this month's
attendance (attended of held, last came, missed in a row).

Student ID card: tutor name and subject, student name, Student ID, classes, and the code that follows Ways to Mark
(QR Code on = a QR code, Barcode on = a CODE128 barcode, both when both are on, none for Manual only). The Student ID
is printed under the code. Drawn in the browser with local copies of qrcode-generator and JsBarcode (MIT, in
`assets/plugins/`), printed from a new window, 8 cards per A4 page. Preview the assistant view with `?as=assistant`
and the START plan with `?plan=start`.

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
- Plan locks: marking on every plan; Attendance Analytics charts and Export on GROW and up (`reports.attendance`, `attendance.export`);
  Student ID Cards on GROW and up (`id.cards`, added to `FeatureCatalog`), `GET /tutor/attendance/id-cards?class=`.
- `tutor_student_id_format` (prefix up to 10 of A-Z 0-9 / -, 3 - 6 digits, next number): new students take the next
  number under a lock; the next number must be higher than any number already used with the prefix. Existing IDs never change.
- Permission `attendance.correct` ("Correct finished class days", added to `PermissionCatalog`): `mark` on a finished
  class day is refused without it (the tutor always has it). Preview: `?as=assistant` (without) and `&can=correct` (with).
- Owner check on the server: `scan` with `force` and `mark` of a student who owes fees are refused (403 / 409) unless
  the signed-in user is the tutor; an assistant's scan answer carries `can_override: false`.

## Decisions (answered 2026-11-11)

1. Students who owe fees: only the tutor can press Mark Present / Mark Present Anyway. Assistants see the fees and can record the payment.
2. Student ID cards are on GROW. The code on the card follows the ways to mark: QR code for QR, barcode for Barcode.
3. START cannot make cards, but sees a sample card template, locked with the GROW badge and the upgrade dialog.
4. Late counts as attended.
5. Watching the recording later stays "Watched Recording" and does not count as attended.
6. The card number is the Student ID. The tutor sets the Student ID format in Settings.
7. Finished class days can be corrected by anyone whose role has the permission "Correct finished class days". No day limit.
