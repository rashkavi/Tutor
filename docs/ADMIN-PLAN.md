# ENLIXO Tutor: Admin side plan (platform owner)

The **admin** is the ENVIIZ team that runs ENLIXO Tutor. The admin's customers are **tutors**: each tutor pays a
monthly or yearly **subscription** (START / GROW / PRO from the pricing sheet) and can buy **add-ons**.
The admin side is where the team adds tutors, starts their free trial, puts them on a plan, bills them,
checks their payments and sees how the business is doing. Each tutor sees the same billing data from their side.

It looks exactly like the Tutor side and ENLIXO V3: same side menu, header card, stat cards, table toolbar,
`.fin-dlg` dialogs, Solar icons, no borders, one button size.

## 1. What a platform admin needs (research summary)

Subscription businesses (Stripe Billing, Chargebee, Paddle and the SaaS billing guides listed at the end) agree on a short list:

| Need | Why | In ENLIXO Tutor |
|---|---|---|
| **One place per customer** | Support and finance answer "what does this tutor have and owe?" in one look | Tutor details page: profile, plan, status, students used, invoices, payments, history |
| **Clear subscription states** | Everyone must know if a tutor can work, must pay, or is blocked | Trial → Active → Payment due → Overdue (grace) → Suspended → Cancelled, plus Complimentary |
| **Free trial without friction** | Trials convert best when the tutor sees every feature and gets reminders before it ends | Trial on PRO by default, no payment needed, reminders 3 days and 1 day before the end, admin can extend once with a reason |
| **Invoices, not just payments** | A payment must say what it paid for; tutors need a proper invoice / receipt | Invoice per billing period with lines (plan, extra students, add-ons, discount, tax) and a printable receipt |
| **Dunning (follow-up of unpaid bills)** | Most lost revenue is "forgot to pay", not "wanted to leave" | Invoice 7 days before renewal, reminders, grace period, then suspension; one-click reactivation after payment |
| **Plan changes with fair pricing** | Tutors grow; upgrades should be instant | Upgrade now (pay the difference for the days left), downgrade at the next renewal and only if the students fit the new limit |
| **Manual control with a reason** | Small markets need deals and exceptions | Custom price, discount, extra student packs, extend trial, mark as paid, void invoice: each needs a reason and is logged |
| **Key numbers** | Run the business | MRR, collected this month, overdue, trials and trial conversion, tutors by plan, churn, tutors near their student limit |
| **Audit log** | Money changes must be traceable | Every billing change is logged (who, when, before / after, reason) |

## 2. Tutor subscription life cycle

| Status | When | Tutor can | Students can |
|---|---|---|---|
| **Trial** | Added with a free trial (default 7 days, the old system's rule) | Use everything in the trial plan | Use everything |
| **Active** | A paid period is running | Use the plan | Use everything |
| **Payment Due** | The renewal invoice is open (from 7 days before the period ends) | Use the plan; banner "Invoice due on ..." | Use everything |
| **Overdue** | The period ended and the invoice is not paid; inside the grace period (default 7 days) | Use the plan; red banner | Use everything |
| **Suspended** | Grace period over, or the admin suspended them | Sign in, see Billing and pay; everything else is locked | See "This class is paused, please contact your tutor"; recordings they paid for stay |
| **Cancelled** | Tutor or admin ended the subscription | Sign in, see Billing and old invoices | No access |
| **Complimentary** | Admin gives free access (partner, demo) until a date | Use the plan | Use everything |

Paying an open or overdue invoice (slip approved, card, or marked paid by the admin) moves the tutor back to **Active** at once.

## 3. Plans, add-ons and prices (from the pricing sheet)

| Plan | Monthly price (admin sets the exact figure) | Students | Extra students |
|---|---|---|---|
| START | LKR 2,500 – 3,000 | 50 | + LKR 1,000 per 50 |
| GROW | LKR 5,000 – 6,000 | 150 | + LKR 1,000 per 50 |
| PRO | LKR 8,000 – 10,000 | 300 | Custom price |

* Yearly billing = 12 × monthly price minus the plan's yearly discount (the existing `annual_discount`).
* Add-ons: ENVIIZ Payment Gateway (LKR 2,000 / month + 2 – 3 % per payment), ENVIIZ Secure Streaming (from LKR 1,500 / month),
  ENVIIZ Zoom Account (LKR 1,500 – 2,000 / month), ENVIIZ SMS (pay as you go), ENVIIZ WhatsApp API (per conversation),
  Physical ID Cards (one-time setup + per card). Monthly add-ons go on the tutor's invoice; usage add-ons are billed for last month's usage.
* A tutor can get a **custom price** (deal) with a reason. Plan features stay as in `payment_plan_features` (migration 001).

## 4. Finance

**Invoice** (`EN-INV-2026-00001`): one per tutor per billing period. Lines: plan, extra student packs, monthly add-ons,
usage add-ons, discount, tax (off by default, see question 3). Statuses: Open → Paid / Part Paid / Void; "Overdue" = open after the due date.

* Created automatically 7 days before the period ends (daily job), for the first paid period when a trial is converted,
  and for the price difference on an upgrade. The admin can also add a one-off invoice (ID card printing, setup).
* Void needs a reason; a paid invoice is never edited: a credit note or refund is added instead.

**Payment** = money received against an invoice:
* Bank transfer: the tutor uploads the slip on their Billing page (the existing slip flow), the admin approves or rejects it with a reason.
* Recorded by the admin (cash, cheque, transfer seen in the bank).
* Card through a gateway (later phase).

A payment that covers the invoice marks it Paid, extends the period and sends the receipt e-mail.

**Tutor payment history** is the same data on both sides:
* Admin: Finance → Payments (everyone) and Tutor details → Payments (one tutor).
* Tutor: Billing → My Subscription (plan, students used, next invoice, pay / upload slip), Invoices, Payment History (status of every slip, receipt download).

## 5. Admin pages

| Menu | Page | What it does |
|---|---|---|
| Dashboard | `admin/dashboard` | Stat cards (paying tutors, on trial, MRR, collected this month, overdue, slips to check), charts (revenue 12 months, tutors by plan, trials and conversions, new vs cancelled), Waiting for you, recent payments, tutors near their student limit |
| Tutors | `admin/tutors` | All tutors: plan, status, renews / trial ends, students used, balance; filters by plan, status, billing cycle; bulk e-mail reminder |
| | `admin/add-tutor` | One page: tutor details → plan and billing (plan cards, monthly / yearly, custom price, extra students, add-ons) → how to start (free trial / paid now / complimentary) with a live summary of what will happen |
| | `admin/tutor-details?tutor-id=` | Profile card + subscription card (change plan, extend trial, add students, discount, suspend / reactivate, cancel) + tabs Invoices, Payments, Usage, History |
| | `admin/requested-users` | Tutor sign-up requests (existing page; restyled later, Approve opens Add Tutor filled in) |
| Finance | `admin/invoices` | Every invoice; filter by status, plan, month; void, record payment, send reminder, print |
| | `admin/tutor-payments` | Slips waiting (approve / reject), all payments, export |
| Plans & Billing | `admin/plans` | Plans & Prices, Add-ons, Trial & Billing Rules (trial days and plan, renewal invoice days, grace days, reminders, tax), Bank Accounts (shown to tutors), Invoice details |
| Content | News, Classes | Existing pages, unchanged |

Tutor side: `tutor/subscription` (My Subscription, Invoices, Payment History) next to the old Billing page, which it replaces once approved.

## 6. Data (additive migration `007_platform_billing.sql`, nothing renamed)

* `payment_plans` + `extra_pack_size`, `extra_pack_price`, `is_public`.
* `users_has_payment_plans` (the tutor's subscription row, the old pages keep reading it) + `status`, `billing_cycle`,
  `trial_ends_at`, `period_start`, `period_end`, `custom_price`, `custom_price_reason`, `extra_packs`, `grace_ends_at`, `suspended_at`, `cancelled_at`.
* `platform_invoices`, `platform_invoice_lines`, `platform_credit_notes`.
* `users_has_payment_plans_payments` stays the payment record (with `users_payments_slips`) + `platform_invoices_invoice_id`, `method`, `approved_by`, `reject_reason`.
* `platform_settings` (trial, grace, reminders, tax, bank accounts, invoice prefix), `platform_billing_logs` (before / after JSON, reason).
* Code: `src/Platform/` (`Subscriptions`, `Invoices`, `Payments`, `PlatformSettings`, `PlatformDashboard`), API `/api/v1/admin/...` and `/api/v1/tutor/subscription`,
  daily job `bin/platform-billing-daily.php` (renewal invoices, reminders, overdue, suspension, trial ending).

## 7. Build order

| # | Step | Status |
|---|---|---|
| A | This plan + preview pages with sample data (admin shell, dashboard, tutors, add tutor, tutor details, invoices, payments, plans & rules, tutor's subscription page) | ✅ done |
| B | Migration 007, services (`src/Platform/`), admin + tutor API; pages on real data; Add Tutor creates the user + subscription (the tutor signs in with an OTP) | ✅ done |
| C | Daily job (`bin/platform-billing-daily.php`): first invoice before the trial ends, renewal invoices, overdue, suspension; e-mail reminder from the admin side | ✅ done (WhatsApp reminders, receipts and printable invoices still open) |
| D | Upgrades with the price difference, credit notes, revenue reports, card gateway, coupons | |

### Running it

1. Run `database/migrations/007_platform_billing.sql` (idempotent). It turns every existing subscription into Trial or Active.
2. Add a cron line, once a day after midnight Sri Lanka time: `php /path/to/bin/platform-billing-daily.php`. The admin dashboard and the tutor's My Subscription page also run it when they open, so nothing waits for the cron.
3. Admin > Plans > Bank Accounts: add the bank account tutors pay into (the tutor page shows only accounts that are on).

### Defaults used until the questions below are answered

Trial 7 days on PRO with 30 students; first invoice at most 3 days before the trial ends; renewal invoice 7 days before; suspended 7 days after the due date; VAT off (18 % ready); prices as in the live database; bank slip only. All of these except the plan prices are in Admin > Plans > Trial & Billing Rules. Students of a suspended tutor are not blocked yet.

## 8. Questions for Rashmi

1. **Trial:** 7 days (as now) or 14? On PRO with all features (recommended) or on the chosen plan? Limit students during the trial (for example 30)?
2. **Grace period:** how many days after the due date before a tutor is suspended (7 recommended)? Should students keep access while the tutor is suspended?
3. **Tax:** is ENVIIZ VAT registered? If yes we add VAT (18 %) to invoices; until then tax stays off.
4. **Exact prices** inside the ranges of the pricing sheet (for example START 2,500 or 3,000)?
5. **Card payments** for subscriptions: which gateway (PayHere, the ENVIIZ gateway)? Bank slip + manual first.
6. **Admin team:** only owners, or also Finance and Support staff with fewer rights?

Sources: [Stripe, subscription management features](https://stripe.com/en-be/resources/more/subscription-management-features-explained-and-how-to-choose-a-software-solution),
[Dodo Payments, SaaS billing best practices](https://dodopayments.com/blogs/saas-billing-best-practices),
[Flexprice, subscription data model](https://flexprice.io/questions/designing-a-subscription-data-model-and-api),
[Solidgate, billing dashboard](https://docs.solidgate.com/billing/subscriptions/subscriptions-1.0/billing-dashboard/),
[Sri Lanka VAT guide 2026](https://trykintsugi.com/sales-tax-guides/apac/sri-lanka).
