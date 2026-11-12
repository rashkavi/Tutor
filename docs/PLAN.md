# ENLIXO Tutor: task plan

Based on: the live DB (`tutor-db.sql`), the SRS & Architecture Blueprint, the ENLIXO pricing sheet (START / GROW / PRO),
and the research in [RESEARCH.md](RESEARCH.md).

Rules for every task:
- **Additive DB changes only.** New tables / new columns in `database/migrations/`. Existing tables are not renamed, so the live app keeps working.
- **Object-oriented, reusable.** Backend: Controller → Service → Repository. Frontend: ES6 classes in `public/assets/js/`, one component per job, reused on every page.
- **Every list page uses `DataView`** (search + filters + pagination + skeleton loading + empty/error states).
- **Every gated feature uses `FeatureGate`** on the UI and `EntitlementService::require()` on the API.
- **Every staff action uses a permission** checked by `PermissionService`.

Status: ✅ done in this branch · 🟡 started · ⬜ to do

## Phase 0: Foundation
| # | Task | Status |
|---|------|--------|
| 0.1 | Repo skeleton, autoloader, config, PDO database, router, JSON responses, error handler | ✅ |
| 0.2 | Design system CSS (tokens, light/dark, cards, tables, badges, buttons, premium lock styles) | ✅ |
| 0.3 | Core JS: `ApiClient` (abort, errors, mock mode), `Component` base class, `Modal`, `Toast` | ✅ |
| 0.4 | Simple test runner (`php tests/run.php`) with no external packages | ✅ |
| 0.5 | Auth: login, session tokens in `users_devices`, single active device for students | ⬜ |

## Phase 1: Consistent lists (search, filters, loading)
| # | Task | Status |
|---|------|--------|
| 1.1 | `SearchInput` (debounced), `FilterBar` (config-driven selects/dates/chips), `Pagination` | ✅ |
| 1.2 | `LoadingState` (table/card skeletons, button spinners), `EmptyState` (empty, no-results, error + retry) | ✅ |
| 1.3 | `DataView` orchestrator: URL-synced state, cancels stale requests, table or card renderer | ✅ |
| 1.4 | Backend `ListQuery` (search, filters, sort whitelist, page/per-page) used by all repositories | ✅ |
| 1.6 | Demo pages for every pattern (`public/index.html`), run on in-browser mock data | ✅ |
| 1.5 | Move existing pages (students, payments, requests, attendance, message center) to `DataView` | ⬜ (needs the existing app code in this repo) |

## Phase 2: Packages and premium locks
| # | Task | Status |
|---|------|--------|
| 2.1 | Tables `features`, `payment_plan_features`, `addons`, `tutor_addons`; seed START/GROW/PRO from the pricing sheet | ✅ |
| 2.2 | `EntitlementService` (has / require / limit / usage) from the tutor's active plan + add-ons | ✅ |
| 2.3 | `FeatureGate` UI: lock badge, greyed preview, upgrade modal naming the plan that unlocks it | ✅ |
| 2.4 | Sidebar shows all modules with lock badges; student-limit usage meter | ✅ (demo) |
| 2.5 | Upgrade / add-on purchase flow wired to `users_has_payment_plans` | ⬜ |

## Phase 3: Custom roles (tutor settings)
| # | Task | Status |
|---|------|--------|
| 3.1 | Tables `permissions`, `tutor_roles`, `tutor_role_permissions`, `tutor_staff`, `audit_logs`; seed permission catalogue | ✅ |
| 3.2 | `PermissionService` (role permissions ∩ plan entitlements; Owner = all) | ✅ |
| 3.3 | Settings → Roles page: role list, create/edit with grouped permission matrix, system role protected | ✅ |
| 3.4 | Invite staff user with a role; delete-role "move users to…" flow | ⬜ |

## Phase 4: Student portal per class (timetable + recordings)
| # | Task | Status |
|---|------|--------|
| 4.1 | "My Classes" page: one card per enrolled class | ✅ (demo) |
| 4.2 | Class page tabs: Timetable · Recordings · Tutes; recordings grouped by month | ✅ (demo) |
| 4.3 | Lock recordings/tutes of unpaid months (uses `student_has_payments`) | ⬜ |
| 4.4 | Weekly calendar view across all classes | ⬜ |
| 4.5 | PHP endpoints for the student class list, class timetable and class recordings (the demo pages use mock data for these today) | ⬜ |

## Phase 5: Recording watch-time limits (like ENLIXO V3)
| # | Task | Status |
|---|------|--------|
| 5.1 | Tables `recording_access_rules`, `recording_watch_sessions`, `recording_student_overrides` | ✅ |
| 5.2 | `RecordingAccessService`: allowed time (multiplier / fixed / unlimited), expiry, overrides, server-side heartbeat crediting | ✅ |
| 5.3 | API: start session, heartbeat, status | ✅ |
| 5.4 | Player page: heartbeat every 15 s only while playing and visible, usage meter, 80% warning, lock card when used up, watermark | ✅ |
| 5.5 | Tutor report: watch time per student + "grant extra time" | ⬜ |
| 5.6 | Bunny.net signed embed URLs for PRO "Controlled recording access" | ⬜ |

## Phase 6: Zoom auto-scheduling
| # | Task | Status |
|---|------|--------|
| 6.1 | `MeetingProvider` interface; `ZoomClient` supporting tutor OAuth (refresh token) and Server-to-Server OAuth (add-on) | ✅ |
| 6.2 | Table `class_schedules` (weekly slots) and `zoom_meetings` (encrypted start URL) | ✅ |
| 6.3 | `ScheduleService`: generate next N weeks of `time_table` rows from weekly slots + conflict check + create Zoom meetings | ✅ |
| 6.4 | Cron script `bin/generate-sessions.php` | ✅ |
| 6.5 | Zoom webhook endpoint: signature check, URL validation, attendance, `recording.completed` → `lecture_recording` | 🟡 (signature + validation done) |
| 6.6 | Tutor UI: connect Zoom, weekly schedule editor | ⬜ |

## Phase 7: Hardening
| # | Task | Status |
|---|------|--------|
| 7.1 | CSRF, rate limiting on login and heartbeat | ⬜ |
| 7.2 | Webhook log table + retry worker | ⬜ |
| 7.3 | Parent dashboard child switcher | ✅ (`parent/dashboard?child=ID`) |

## Moving the real pages (started 2026-11-09)
| Page | Status |
|---|---|
| `tutor/students.php`: list now uses `DataView` (search, class + status filters, skeleton loading, paging) via `GET /api/v1/tutor/students` | ✅ |
| `portal/watch-recording.php`, `portal/watch-pre-recording.php`: watch-time meter + lock (PRO only), enrolment check on pre-recordings | ✅ |
| ENLIXO V3 layout: grouped side menu (Academic / Finance / Communication / Settings), header card with Action menu, count + search + filter dropdown + Export Report, Results Per Page, "Update ticked" bulk action, ticked table with sort icons. Applied to Students | ✅ |
| Create a Class (`tutor/create-class.php`): one page with weekly times + fee, (i) info on every field, "Customise form" (hide fields, add own fields). See docs/TUTOR-PRODUCT-PLAN.md step 1 | ✅ |
| Dashboards for all four roles (`tutor/`, `admin/`, `portal/`, `parent/dashboard.php`): real numbers only (the sample chart, calendar and parent figures are gone), one shared kit (`includes/enlixo/dashboard.php`, `Enlixo\Dashboard\DashboardRepository`, `app-dashboard.js`). See "Dashboards" below | ✅ |
| Admin side menu lists only pages that exist in `admin/` | ✅ |
| `tutor/payments`, `tutor/attendance`, `tutor/class-students`, `tutor/time-table` | ⬜ next |
| Student portal: timetable + recordings grouped by class | ⬜ |
| Tutor settings: custom roles page | ⬜ |
| Zoom weekly schedule UI | ⬜ |
| Lock badges in the side menu | ⬜ |

## Dashboards (done 2026-11-12)

The tutor and admin dashboards are main's API pages (`app-tutor-dashboard.js`, `app-admin-dashboard.js`). The student and parent
dashboards are drawn by PHP from `DashboardRepository`. All four now look the same:

* **No "Dashboard › Overview" header card.** The top of the page is a greeting card ("Good morning, Nimal", today's date, one line
  about today) that also holds the Action menu and the main button (Start Class, Pay Fees).
* **Four stat cards to a row** on large screens, each with its own light colour (blue, green, orange, purple; `StatCard` `tone`).
  The tutor's "Waiting for You" and "Classes This Week" cards were dropped from the row because they have their own panels below;
  the admin's "Overdue" and "Slips to Check" are in "Waiting for You".
* **Loading:** a skeleton in the shape of the page (`DashboardKit.skeleton()`), light grey with a shimmer. The student and parent pages
  come from PHP, so they show at once with no skeleton.

| Role | Stat cards | Extra |
|---|---|---|
| Student | My classes, classes this week, attendance (6 weeks), fees to pay | Fees to pay with "Pay now", recent payments, my classes. |
| Parent | Children (or classes for one child), classes this week, attendance, fees to pay | Child switcher (`?child=ID`, only the parent's own children), one card per child, fees to pay with "Pay now". |

Rules used: paid = payment status 3 or 8; attended = attendance status 2 or 4 (watched the recording); for students a past class
day with no mark counts as missed (same as the Attendance page). The calendar holds class days from 2 months back to 3 months ahead.

## Classes as premium cards (done 2026-11-12)

Tutor → Classes opens in the **Cards** view (rows and table are still there). Student and parent Classes use the same card
(`includes/enlixo/class-card.php`): cover or a drawn cover in the exam colour, "2027 • A/L" pill (pink A/L, orange O/L, purple
Scholarship), subject, name, medium and students, blue "View Class", round edit and ⋯ buttons.

## Language (done 2026-11-12)

Sinhala and Tamil were hidden in the tutor and student language menus, and only elements with a `data-i18n` key changed, once,
on page load. Now `assets/js/i18n-auto.js` (loaded by `init.js` on every page) translates every piece of text on the page,
including text drawn later by JavaScript, from `assets/i18n/phrases.si.json` / `phrases.ta.json` plus the old key files.
Add wording in `bin/i18n-phrases.py` and run `python3 bin/i18n-phrases.py`. Names, class names and other data typed by people stay
as they are. Chart labels inside the charts are not translated yet.

Not done yet: notes are saved per browser, not in the database; the student and parent "Today's classes" rows don't show a Zoom join button.

## Tutes and class page (done 2026-11-12)

Migration **012_tute_delivery.sql** (additive): four columns on `class_tutes` (courier, planned post date, note to
students, added by) and a new `tute_deliveries` table. Run it after 011.

**Who gets a tute** (`Enlixo\Tutes\TuteRules`, `TuteRepository`), the same rule the old tute pages used:
* `payment_month` empty = every active student of the class (also students who join later).
* `payment_month` = 'YYYY-MM' = only students whose fee for that month is paid: status 3 (approved slip / cash) or 8 (card).
  A student who pays later gets it then. A free class always gives it to everyone.

**Add Tute** (`tutor/add-tute.php`, `app-tutor-add-tute.js`, `POST /api/v1/tutor/tutes`): name and description, Download
(PDF / Word, up to 10 MB each, type checked from the file itself) or Post to their home (courier), who gets it (everyone /
paid for a month, with how many students that is), then files, or the parcel items, courier, planned post date and a note.

**Courier deliveries** (`tutor/couriered-tute.php`, `app-tutor-tute-deliveries.js`): replaces the old page that showed
made-up numbers. One parcel per student who gets the tute; the address is copied from the student's profile when the
parcel is made (so a parcel already sent keeps its address). Steps: To pack → Packed → Sent (courier + tracking number,
date filled in) → Delivered, or Returned. Tick rows to move many at once; Edit one parcel to fix its address; "Add students
who paid later"; Print labels (address labels for the ticked parcels, or every parcel still to send); Download list (CSV).
Couriers offered: Sri Lanka Post (Speed Post / Registered Post), Domex, Prompt Xpress, Koombiyo, Pronto, Certis Lanka,
Trans Express, Hand delivery, or Other. Sri Lanka Post tracking numbers look like BA123456789LK; students get a link to
the Sri Lanka Post tracking page.

**Student**: Tutes → My tute parcels (`portal/couriered-tute-details.php`): each parcel with its progress, courier,
tracking number (Copy, Track parcel), where it is going and the tutor's note. Replaces the old mock page.

**Class page** (`tutor/class-details.php`, `app-tutor-class-details.js`, `GET /api/v1/tutor/classes/{id}/summary`,
`TutorDashboard::classSummary()` so the rules match the dashboard): weekly times, where, fee, medium and year; cards for
students, fees this month, attendance (4 weeks) and the next class; attendance per week; next class days; "Needs your
attention" (slips to approve, students who have not paid, parcels to send); links to everything in the class with counts.
Edit Class opens `edit-class` (the old edit pop-up is gone).

Not done yet: the old `tutor/api/addTute.php` is no longer used by the page but is still on the server; the parent side
does not show tute parcels yet; no automatic SMS/WhatsApp when a parcel is sent.

## Sign out, info icons and welcome card (done 2026-11-12)

* **Sign out works for every role.** `includes/enlixo/sign-out.php` is used by `tutor/`, `admin/`, `portal/` and `parent/`
  `api/signOut.php`. Before: the parent was never signed out (`hasValidSession()` only knows tutors and admins), the admin
  sign-out signed out the tutor instead, and pages without `js/functions.js` had no `signOut()` at all. Now each folder
  signs out only its own role (admin and tutor in one browser stay separate), marks the device inactive, and goes to
  that folder's sign-in page with a normal page load. `assets/js/script.js` gives every page a `signOut()`.
* **Info icons**: one design and one video file (see docs/UI-STANDARD.md "Info icons and videos").
* **Welcome card** after signing in on all four dashboards.

## Open questions for Rashmi
1. Recording limit default: is **3 × video length** right, or do you want a fixed number of views like V3?
2. Plan that unlocks custom roles: the pricing sheet doesn't mention staff roles. I put it in **GROW and PRO** (START only has the Owner role). OK?

Answered: the existing code is now in the repo; `duration` columns are **minutes** (confirmed from `portal/watch-recording.php`).

## Landing page, online tutor sign-up and Requested Tutors (done 2026-11-13)

Migration **013_tutor_requests.sql** (additive): `requested_users` gets the plan the tutor asked for (`plan_key`,
`billing_cycle`, `addons`), what they teach (`main_subject`, `grades`, `institute_name`, `class_mode`, `district`,
`expected_students`), `heard_from`, `message`, `ip`, and the decision (`tutor_id`, `decided_by`, `decided_at`,
`decision_note`). Run it after 012. Run `php bin/seed.php` once so the add-ons and plan features are in the database
(the pages fall back to the pricing sheet in `FeatureCatalog` until then).

**Landing page** (`index.php`, `assets/landing/landing.css`, `landing.js`): hero with a live dashboard mock-up, "why
tutors switch", features, Tutors / Students / Parents, how it works, pricing (prices from Admin > Plans & Prices, the
feature table and add-ons from the pricing sheet), FAQ and sign-in links for every role. Square corners, no libraries,
all motion off for "reduce motion", works without the database.

**Tutor sign-up** (`tutor-registration-application.php`, `assets/landing/signup.js`, `GET|POST /api/v1/public/tutor-signup`,
`Enlixo\Platform\TutorRequests`): About you → Your classes → Plan (START / GROW / PRO, monthly or yearly, add-ons) →
Review. `?plan=grow&cycle=yearly` from the landing page picks the plan. Checks: e-mail and mobile not already used,
one pending request per e-mail, 5 requests an hour per connection, a hidden field for bots. The tutor gets a
"we received your request" e-mail with the reference (TR-00012). The old `api/submitTutorRegistrationApplication.php`
is removed.

**Requested Tutors** (`admin/requested-users.php`, `app-admin-tutor-requests.js`, `/api/v1/admin/tutor-requests`):
cards for Waiting / This week / Approved / Rejected (click to filter), search, plan and teaching filters, CSV.
A request opens with everything the tutor wrote, WhatsApp and call links. **Approve** opens Add Tutor filled in from
the request (plan, billing, add-ons); saving makes the tutor with `PlatformBilling::addTutor`, links the request
(`tutor_id`) and e-mails the tutor how to sign in. **Reject** needs a reason (admins only); **Open again** undoes it.

**FREE plan and separate logins on the landing page (2026-11-13).** Tutors with up to 50 students
(`TutorRequests::FREE_LIMIT`) can ask for FREE: the START features at no cost. It is a sign-up option, not a
`payment_plans` row: on approval Add Tutor opens with START + Complimentary for 12 months
("FREE plan: up to 50 students"), so no invoice is made; extend it while the class stays small. The landing page has an
announcement bar, a "Free for small classes" section with a students calculator (FREE up to 50, then the cheapest plan
with extra packs), a FREE card in Pricing, and a "Three logins" section for the Student Portal (`/portal`), Tutor
Dashboard (`/tutor`) and Parent Portal (`/parent`).
