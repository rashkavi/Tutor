# Plans: FREE, STARTER, GROW, CUSTOM (November 2026)

One source of truth: `src/Catalog/FeatureCatalog.php` (`PLANS`, `features()`, `limits()`, `nextPlan()`). `php bin/seed.php`
writes it to `features` / `payment_plan_features`. Code asks for a feature or a limit, never a plan name.

| | FREE | STARTER | GROW | CUSTOM |
|---|---|---|---|---|
| Price | LKR 0 | LKR 3,000 / month | LKR 7,500 / month | agreed with ENLIXO |
| Students | 50 | 150 (+ packs of 50) | 500 (+ packs) | any |
| Classes | 3 | unlimited | unlimited | unlimited |
| Tutes a month | 4 | unlimited | unlimited | unlimited |
| Recordings a month | 8 | unlimited | unlimited | unlimited |
| Notifications a month (e-mail + WhatsApp, one per person) | 100 | 1,000 | unlimited | unlimited |
| Parent login | no | yes | yes | yes |
| Zoom | paste the link per class day | integration + auto-scheduling | yes | yes |
| Attendance | manual | + QR / barcode | + reports export | yes |
| Tutes by post (courier) | no | yes | yes | yes |
| MCQ exams | no | yes | yes | yes |
| Card payments, WhatsApp / SMS API, arrears, parent payments, controlled / expiring recordings, analytics, ID cards, staff roles | no | no | yes | yes |

## Where it is enforced

- **API:** `EntitlementService::require($tutor, $feature)` (402 `feature_locked`) and `requireWithin($tutor, $limit, $adding)` (402 `limit_reached`, `LimitReachedException`): new class (`ClassController::store` / `status`), tute (`TuteController::store`, courier = `tutes.courier`), recording (`ClassRecordingController::save`), Message Center (`MessageService::create`, people reached). The older `tutor/api/addClass|addTute|addRecording|uploadLectureRecording.php` use `includes/enlixo/plan-guard.php`.
- **Parent login:** `tutor_parent_login_allowed()` in `includes/TutorAccess.php` (sign-in and every parent page through `SessionUser::ifUserIsParent()`): a parent signs in when at least one tutor of their children has `parents.login`. Otherwise the sign-in page says "Parent login is not open for your tutor".
- **Usage:** `PdoEntitlementRepository::usage()` (classes now; tutes, recordings and messages since the 1st of the month).

## Getting tutors to the next plan

- **Upgrade page** `tutor/upgrade.php` (`app-tutor-upgrade.js`, `upgrade.css`): the reason (`?feature=`, `?limit=`, `?plan=`), what the tutor uses with what the next plan gives, the plans side by side (monthly / yearly, the recommended one marked), "Try free for 7 days", an inline confirm (no pop-up), every feature per plan, questions.
- **Nudges, never pop-ups:** the menu plan card (opens Upgrade), a banner when a limit is 80 % used or students are at 90 %, a card at the top of the page when the API answers `limit_reached` / `feature_locked` (`ApiClient` fires `enlixo:upgrade`, the tutor side menu draws it), the dashboard "Your plan" card, lock badges and locked tabs (QR attendance, courier tutes, Zoom connect). `FeatureGate.openUpgrade(key)` goes to the Upgrade page.
- **Try the next plan (preview):** `POST /tutor/subscription/preview {plan}` → `PlatformBilling` action `preview`: 7 days of STARTER or GROW, once per tutor, no invoice, own plan stays (`users_has_payment_plans.preview_*`, migration 018). The admin can give another with a reason.
- **Upgrade from FREE:** the plan works at once (`status` trial on that plan) and its invoice is made now, due in `upgrade_pay_days` (7). Not paid → back to FREE.
- **Reverse trial:** a trial (sign-up or upgrade from FREE) that ends unpaid moves the tutor to FREE when their students fit (`trial_to_free`, on by default), instead of pausing the class portal. Paid plans that are not paid still go overdue → suspended.
- **Going down to FREE:** at the end of the paid period (`next_plan_id`), only when the students fit.

## Setup

1. `database/migrations/018_free_plan_upgrades.sql` (FREE plan row, preview columns, moves "FREE plan" complimentary tutors to FREE).
2. `php bin/seed.php`.
3. Tests: `php tests/run.php`, `node tests/e2e/plans-journey.e2e.js` (10 cases; `setup-qa-tutor.php free`, `qa-tick.php`).
