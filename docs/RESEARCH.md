# Industry research: what good tutor / LMS platforms do

Short notes on how established platforms handle each thing we are building, and the rule we take from it.
Sources are listed at the end.

## 1. Lists, search, filters and loading (every page)

What good products do:
- **One list pattern everywhere.** Students, payments, recordings, timetable and requests all use the same toolbar: search on the left, filters next to it, result count + "clear filters" on the right.
- **Search is debounced** (about 300 ms) and runs on the server, so it works with thousands of rows.
- **Filter state lives in the URL** (`?q=nimal&class=12&status=paid&page=2`). Refresh, back button and shared links keep the same view.
- **Skeleton loaders, not spinners,** for first load (the page shape appears instantly). Small inline spinners only for button actions ("Saving…").
- **Three non-happy states are designed, not forgotten:** empty ("No recordings yet"), no results ("No match for 'abc' – clear filters"), and error ("Couldn't load – Retry").
- **Stale responses are cancelled** (AbortController) so a slow old search never overwrites a newer one.

Our rule: build this once as a `DataView` class (search + filters + pagination + loading/empty/error) and configure it per page.

## 2. Package (plan) feature gating with lock icons

What good SaaS products do (Notion, Canva, Zoom, Classplus, Teachmint):
- **Show, don't hide.** Locked features stay in the menu and on the page with a lock / crown badge. Hidden features can't sell themselves.
- **Contextual upsell.** Clicking a locked feature opens a small modal: what it does, which plan unlocks it, and an "Upgrade" button. No dead clicks.
- **Preview where possible.** Show a blurred / greyed preview of the screen behind the lock.
- **One source of truth: entitlements.** Code asks `can('recordings.expiry')`, never `if plan == 'PRO'`. Changing what a plan includes is a data change, not a code change.
- **Enforce on the server too.** The UI lock is only for marketing; the API must also refuse a locked feature (HTTP 402/403 with `feature_locked`).
- **Limits are entitlements too** (student limit 50/150/300). Show usage meters ("42 / 50 students") and warn at 80%.
- **Add-ons** (ENVIIZ Zoom, Secure Streaming, SMS…) are separate entitlements that any plan can buy.

## 3. Custom roles and permissions (tutor staff)

What good multi-tenant products do (WorkOS, Retool, Aserto guidance):
- **Permissions are a fixed catalogue defined by us** (`students.view`, `payments.approve`, `recordings.manage`…), grouped by module.
- **Roles are owned by each tutor (tenant).** A tutor can create "Class Assistant", "Cashier", "Paper Marker" and tick which permissions each has.
- **Built-in system roles** (Owner) can't be deleted or edited, so a tutor can never lock themselves out.
- **Effective access = role permissions ∩ plan entitlements.** A Cashier with `payments.online` still can't use it on START.
- **Settings UI = permission matrix** grouped by module, with "select all in module", and a count of users per role. Deleting a role in use asks where to move those users.
- **Audit log** for role changes.

## 4. Recording watch-time limits (like ENLIXO V3)

What Sri Lankan / Indian tuition platforms do (Testpress, Classplus, VdoCipher-based apps, Fermion):
- **Limit by watch time, not by clicks.** Allowed time = video length × multiplier (commonly 2–3×), or a fixed number of minutes, plus an optional expiry date.
- **Server counts the time.** The player sends a heartbeat every ~15 s; the server adds at most the real time since the last heartbeat (so a user can't fake "I watched 0 s"). Paused / hidden tabs don't count.
- **Show the meter to the student:** "38 min of 90 min used". Warn at 80%. When used up, the player is replaced by a lock card ("Watch time used up – contact your tutor").
- **Tutor can grant extra time** per student (override), and see a per-student watch report.
- **Watermark** with student name/ID on the player and **one active session** per student reduce sharing.
- **Organise per class:** each class page has tabs: Timetable, Recordings, Tutes, Payments. Recordings are grouped by month (matching monthly fees), and locked if that month isn't paid.

## 5. Zoom auto-scheduling for lecturers

What good integrations do:
- **Each tutor connects their own Zoom** with OAuth (all plans, "Tutor Zoom Integration"). The ENVIIZ Zoom add-on uses our Server-to-Server OAuth account instead. Both sit behind one `MeetingProvider` interface.
- **Weekly class schedule → automatic sessions.** Tutor sets "Grade 11 Maths – every Saturday 8–10 am, online". A daily job creates the next weeks' timetable rows and the Zoom meetings for them.
- **Conflict check** before creating: same tutor can't be double-booked.
- **Store `start_url` encrypted;** students only get join links through the portal after login (and after payment).
- **Webhooks:** verify Zoom's signature, answer the `endpoint.url_validation` challenge, use `meeting.participant_joined/left` for attendance and `recording.completed` to auto-create the class recording.
- Tokens are cached (~55 min) and refreshed automatically; API failures are retried with backoff and logged.

## Sources
- [SaaS upgrade paywall UX patterns](https://www.saasui.design/blog/saas-upgrade-paywall-ux-patterns)
- [Feature gating & 8 ways to implement it (Stigg)](https://www.stigg.io/blog-posts/feature-gating)
- [What is feature gating in SaaS (Schematic)](https://schematichq.com/blog/what-is-feature-gating)
- [Premium badge vs unlock CTA](https://www.lazyweb.com/research/premium-badge-vs-unlock-cta-which-signals-gating.md)
- [How to design multi-tenant RBAC (WorkOS)](https://workos.com/blog/how-to-design-multi-tenant-rbac-saas)
- [Implementing custom roles in SaaS (Aserto)](https://www.aserto.com/blog/implementing-custom-roles-in-your-saas-application)
- [Configure RBAC (Retool docs)](https://docs.retool.com/4.0/permissions/guides/roles-permissions/)
- [Video expiration, view limits & viewing windows](https://m.polyv.net/news/?p=102001)
- [Restrict how much time users have to watch videos (CM Video Lessons)](https://creativeminds.helpscoutdocs.com/article/2216-cm-video-lessons-manager-cmvlm-restricting-the-time-users-have-to-watch-videos)
- [Fermion recorded video settings](https://docs.fermion.app/setting-up-course/syllabus/recorded-video)
- [Testpress LMS updates](https://blog.testpress.in/whats-new-testpress-lms-june-2022/)
- [Zoom REST API skill / Server-to-Server OAuth](https://skills.sh/zoom/skills/zoom-rest-api)
- [Zoom webhooks (Nango docs)](https://nango.dev/docs/api-integrations/zoom-cc/webhooks.md)
