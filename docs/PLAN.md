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
| 7.3 | Parent dashboard child switcher | ⬜ |

## Moving the real pages (started 2026-11-09)
| Page | Status |
|---|---|
| `tutor/students.php`: list now uses `DataView` (search, class + status filters, skeleton loading, paging) via `GET /api/v1/tutor/students` | ✅ |
| `portal/watch-recording.php`, `portal/watch-pre-recording.php`: watch-time meter + lock (PRO only), enrolment check on pre-recordings | ✅ |
| `tutor/payments`, `tutor/attendance`, `tutor/class-students`, `tutor/time-table` | ⬜ next |
| Student portal: timetable + recordings grouped by class | ⬜ |
| Tutor settings: custom roles page | ⬜ |
| Zoom weekly schedule UI | ⬜ |
| Lock badges in the side menu | ⬜ |

## Open questions for Rashmi
1. Recording limit default: is **3 × video length** right, or do you want a fixed number of views like V3?
2. Plan that unlocks custom roles: the pricing sheet doesn't mention staff roles. I put it in **GROW and PRO** (START only has the Owner role). OK?

Answered: the existing code is now in the repo; `duration` columns are **minutes** (confirmed from `portal/watch-recording.php`).
