# ENLIXO Tutor: payments on the tutor side

Two kinds of money pass through a tutor's account:

| | Who pays whom | Where the tutor sees it | Built by |
|---|---|---|---|
| **Student fees** | Students pay the tutor a monthly class fee | Finance → **Student Fees**, a student's **Fee Card**, Settings → **Fees & Receipts** | this plan (step A preview here, step B real data) |
| **Subscription** | The tutor pays ENLIXO for START / GROW / PRO | Finance → **My Subscription** (plan, invoices, pay by slip, payment history) | the Admin side plan (`docs/ADMIN-PLAN.md`, PR #13; backend = migration 007 + `src/Platform/`) |

Same look on both: V3 header card, stat cards, `table_toolbar` lists, `.fin-dlg` dialogs, Solar icons, no borders, one button size.
The old pages (`payments`, `class-payments`, `student-payment`, `class-payment-plans`, `add/edit-payment-plan`, `billing`, `pricing`,
`subscription-payments`, `pay-subscription-renewal`) keep working until the new ones are on real data, then they redirect.

## 1. How a tuition class is paid (what the pages are built around)

* One **monthly fee per class**. The fee row is created on the 1st for every active student (the existing `automatedClassFee` rule),
  due on the class's due day. A student in two classes has two fees a month.
* Students pay three ways: **bank slip in the app** (existing upload, tutor checks it), **cash at the class** (tutor or assistant records it),
  **card** (GROW+, gateway; later phase).
* **Free cards / half cards / discounts** per student and class, with a reason (`fees.advanced`, PRO).
* **Late fee** once, a few days after the due date (the existing penalty options stay readable).
* **Unpaid months close that month's recordings and tutes** (setting, links to plan step 7).
* Reminders on **WhatsApp**: one student at a time opens WhatsApp with the text ready (every plan, no API needed);
  automatic and bulk reminders use the WhatsApp API (`comms.whatsapp`, GROW+).

## 2. Fee states (one chip colour each)

| State | Meaning | Colour |
|---|---|---|
| Not Paid Yet | Created, before the due date | yellow |
| Overdue | Not paid after the due date (derived, not stored) | red |
| Slip to Check | Student uploaded a slip (legacy status 2) | orange |
| Slip Rejected | Tutor rejected the slip with a reason; still owed (legacy 4) | red |
| Paid | Slip approved or payment recorded; has a receipt number (legacy 3) | green |
| Free Card | Net fee is 0 after a free card | purple |

Net fee = amount - discount + late fees (the dashboard rule in `TutorDashboard`). Unpaid counts only students still active in the class.

## 3. Pages

| Menu | Page | What the tutor does there |
|---|---|---|
| Finance → Student Fees | `tutor/fees` | Month switcher + class filter → 6 stat cards (Collected, Still to Collect, Slips to Check, Owed From Earlier Months, Free Cards & Discounts, Collection Rate) → Fees Collected chart (12 months) + Classes this month → tabs **{Month} Fees**, **Slips to Check** ("Check One by One": Approve & Next), **Unpaid** (months owed per student, WhatsApp reminder, tick + bulk reminder, export), **Receipts** (all receipts, export). Header: gear (Fee Settings) + **Record Payment**. |
| (from any student) | `tutor/student-fees?student-id=` | The **Fee Card**: one box per month per class (green / orange / red / purple), stat cards, receipts; Record Payment, Free Card / Discount, WhatsApp. |
| Settings → Fees & Receipts | `tutor/fee-settings` | Tabs: How Students Pay (methods, bank accounts shown in the app, note), Due Dates & Late Fees (+ close recordings of unpaid months), Free Cards & Discounts (types), Reminders (before / on / after due, message with live WhatsApp preview), Receipts (number prefix, auto-send, who may approve = Roles), Class Fees (fee + due day per class, edit on the class form), Activity Log. |
| Finance → My Subscription | `tutor/subscription` | PR #13 (admin side): plan card, open invoice, Pay Now with slip, Invoices, Payment History. |

Dialogs: **Check Payment Slip** (fee breakdown, the slip, "amount matches" check, history, Reject with reason / Approve, Approve & Next),
**Record a Payment** (student, tick unpaid months + pay in advance, Cash / Bank deposit / Card, date, reference, WhatsApp receipt switch, live total),
**Receipt** (printable, Send on WhatsApp), **Free Card or Discount**, **Fee Reminder**.

## 4. Package locks (from `FeatureCatalog`)

| Feature | START | GROW | PRO |
|---|---|---|---|
| Monthly fees, slips, cash, receipts, Fee Card, this month's unpaid list, one-by-one WhatsApp reminder | ✓ | ✓ | ✓ |
| Card payments (`payments.online_card`), bulk / automatic WhatsApp reminders (`comms.whatsapp`), Fees Collected chart (`reports.payments`) | | ✓ | ✓ |
| Owed from earlier months / arrears (`payments.arrears`), free cards and discounts per student (`fees.advanced`) | | | ✓ |

Locked items stay visible with the plan badge and open the upgrade dialog; the API refuses them too.

## 5. Data (additive migration `008_student_fees.sql`; 006 = dashboard, 007 = platform billing)

* `student_has_payments` + `receipt_no`, `checked_by`, `checked_at`, `reject_reason`, `recorded_by`, `concession_id` (nothing renamed).
* `payment_method` + Cash, Bank deposit (found by name, never by id).
* `fee_receipts` (tutor, number unique per tutor, enrolment, amount, method, paid on, reference, note, received by, void + reason) and
  `fee_receipt_lines` (receipt → fee rows): one receipt can pay several months.
* `student_fee_concessions` (enrolment, kind free / percent / fixed, value, from month, until month, reason, by): applied when the month's fee is created.
* `tutor_fee_settings` (one JSON row per tutor: methods, due day, late fee, lock unpaid, reminders, receipt prefix + next number),
  `tutor_bank_accounts`, `tutor_fee_concession_types`, `fee_reminders` (log), `fee_activity_logs` (before / after, who, when).
* Code: `src/Fees/` (`FeeRepository`, `FeeService` approve / reject / record / concession, `ReceiptNumbers` with a row lock,
  `FeeSettings`, `FeeOverview`), API `/api/v1/tutor/fees/...`, `/tutor/fee-settings`, `/tutor/students/{id}/fees`,
  daily job `bin/student-fees-daily.php` (create the month's fees with concessions, late fees, reminders) replacing the per-page-load `automatedClassFee` / `automatedPaneltyPayment` calls.
* Student portal (plan step 10): shows the tutor's bank accounts and the reject reason, receipt download.

## 6. Build order

| # | Step | Status |
|---|---|---|
| A | This plan + preview pages on sample data (`FeesMock`, `?plan=start` / `grow` shows the locks) | ✅ for review |
| B | Migration 008, `src/Fees`, API; pages on real data; receipts; old fee pages redirect | |
| C | Daily job (fees with concessions, late fees, WhatsApp reminders), lock recordings of unpaid months | |
| D | Card payments (gateway), parent payment dashboard (PRO), income reports | |

## 7. Questions for Rashmi

1. **Free cards on START / GROW?** The pricing sheet puts "discounts, penalties and custom fee rules per student" on PRO. Free cards are very common; keep them PRO, or allow a simple free card on every plan?
2. **Partly paid months:** today a month is paid or not. Should a student be able to pay half a month (Part Paid)?
3. **Late fee:** keep the old options (per day, per slot) or only "once, N days after the due date" (shown in the preview)?
4. **Assistants:** may an assistant record cash, or only check slips? (Uses the existing roles: "Approve / reject payments".)
5. **Receipt numbers:** one series for the tutor (`RC-00001`), or one per class?
