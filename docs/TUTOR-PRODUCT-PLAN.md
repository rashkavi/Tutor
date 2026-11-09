# ENLIXO Tutor: product plan (tutor-first)

ENLIXO V3 is built for a **university**: many lecturers, departments, intakes, modules and semesters, with
admins doing most of the work. ENLIXO Tutor is for **one tutor** (sometimes with one or two assistants)
running tuition classes in Sri Lanka. We keep V3's **look and structure** (side menu, header card with
Action menu, list toolbar, tables) but change **how each part works** so it fits a tutor's day.

## The tutor's year in one picture

1. Create classes for the coming exam year (e.g. "2027 A/L Physics, Sinhala medium, Theory").
2. Set the weekly time and the monthly fee once. The timetable and Zoom links fill in by themselves.
3. Students join (open link, or tutor approves). Parents can follow.
4. Every class day: mark attendance (QR or tick list), share the recording and tutes.
5. Every month: fees are due. Students upload slips or pay by card; the tutor approves; arrears show.
6. Near the exam: paper classes, revision classes, MCQ papers, results.

## How each part differs from V3 (university)

| Area | University (V3) | Tutor (this product) |
|---|---|---|
| **Classes** | Course → intake → module → batch, created by admins | One "class" = exam type + exam year + subject + medium + class type (Theory / Revision / Paper). The tutor creates it in **one form** with the weekly time and fee in the same place. |
| **Students** | Registered via admissions, have a student number per programme | A student can attend **several tutors**. Each tutor only controls the student's **enrolment in their own classes** (active / inactive / pending), never the student's account. |
| **Timetable** | Semester timetables built by coordinators | Weekly slots per class (e.g. every Saturday 8-12). Sessions are generated 2 weeks ahead, with Zoom meetings for online slots. Extra / cancelled classes are one-off changes. |
| **Attendance** | Lecture attendance with eligibility % for exams | Per class day. QR scan at the door for physical classes, auto from Zoom join for online, recording watch-time counts as "watched". Used to follow up absent students and to tell parents. |
| **Payments** | Semester / course fees, instalments, finance office | **Monthly class fee** (the most common), due day of month, optional late fee, discounts for siblings / free cards, "pay to unlock this month's recordings". Tutor approves bank slips themselves. |
| **Recordings** | Lecture capture library per module | Recordings per class day, available only to students who paid that month, with watch-time limits (3x video length by default) and expiry. |
| **Tutes / materials** | Course materials | Downloadable tutes per class and **couriered tutes** (printed and posted). |
| **Exams** | Formal exams, grading schemes, transcripts | MCQ papers and paper-class marks, rank lists per class. |
| **Staff** | Many roles, departments | Owner + a few assistants (card marker, payment checker). Custom roles on GROW/PRO. |
| **Messaging** | Notices by department | WhatsApp / SMS to a class, to absent students, or to students with arrears. |
| **Reports** | Academic reports | Monthly income, collection rate, arrears, attendance trend per class. |

## Build order (step by step)

Every step reuses the shared kit (DataView, ActionMenu, page header, Form with info icons) so the look stays the same.

| # | Step | What the tutor gets | Status |
|---|---|---|---|
| 1 | **Class creation form** | One page: basics, how it runs (physical / online, weekly times, hall), fee, enrolment, cover. Info (i) icon on every field. Tutor can hide optional fields and add their own fields. | ✅ this step |
| 2 | Classes list (V3 list layout) | Search, filter by exam type / year / subject / medium / status, cards or table, Action menu. | next |
| 3 | Class page | Tabs: Overview, Students, Timetable, Attendance, Payments, Recordings, Tutes, all scoped to the class. | |
| 4 | Timetable | Week / list view, generated sessions, one-off extra or cancelled class, Zoom links. | |
| 5 | Attendance | Mark per session (tick list + QR), absent list, export (GROW). | |
| 6 | Payments | Monthly fees per student, slip approval, arrears (PRO), card (GROW). | |
| 7 | Recordings & tutes | Per class day, paid-month access, watch limits (PRO). | |
| 8 | Messages | To a class / absentees / arrears. | |
| 9 | Settings | Profile, roles & staff, integrations, class form customisation, package. | |
| 10 | Student & parent portal | My classes → timetable, recordings, payments per class. | |

## Step 1 details: class form

* **Fields** (★ = always required):
  * Basics: ★ Exam type, ★ Exam year, ★ Subject, ★ Medium, Class type, Class name (auto-suggested), Description.
  * How it runs: ★ Delivery (Physical / Online / Both), Hall / location, Weekly times (day, start, end, physical or online), Create Zoom meetings automatically, Start date.
  * Fee: Monthly fee (LKR), Fee due day, Free class.
  * Enrolment: Who can join (Anyone with the link / I approve each student / Only students I add), Maximum students.
  * Cover image.
* **Customise form** (per tutor): show or hide every optional field, and add own fields (text, number, date, dropdown, yes/no) with their own help text and "required" switch. Values are saved per class.
* **Saving** creates the class, its weekly schedule rows and its monthly fee plan in one go (one transaction).
* Data: additive migration `002_class_form.sql` (new columns on `classes`, plus `tutor_class_form_settings` and `class_custom_field_values`). Nothing is renamed.
