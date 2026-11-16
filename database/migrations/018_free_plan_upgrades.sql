-- 018: FREE becomes a real plan, and "try the next plan" previews (November 2026 plan matrix, docs/PLANS.md).
-- Run after 017, then `php bin/seed.php` so the plan features follow src/Catalog/FeatureCatalog.php.
--
--   FREE     up to 50 students, the basics, no parent login, 3 classes, 4 tutes / 8 recordings / 100 messages a month,
--            Zoom link pasted by hand, manual attendance. Never invoiced.
--   STARTER  everything a small class needs: parent login, Zoom auto-scheduling, QR attendance, tutes by post ...
--   GROW     almost everything; CUSTOM agreed with the team.
--
-- Before this, FREE was STARTER with complimentary access. Those tutors move to the FREE plan here; their data stays.

INSERT INTO `payment_plans` (`payment_plan`, `plan_key`, `per_month_amount`, `annual_discount`, `student_limit`, `sort_order`, `extra_pack_price`, `tagline`)
SELECT 'Free', 'free', 0, 0, 50, 0, NULL, 'For tutors starting out, up to 50 students'
 WHERE NOT EXISTS (SELECT 1 FROM `payment_plans` WHERE `plan_key` = 'free');

-- one "try the next plan for 7 days" per tutor (PlatformBilling action 'preview'); the preview plan's features while it runs
ALTER TABLE `users_has_payment_plans`
  ADD COLUMN `preview_plan_key` varchar(20) NULL,
  ADD COLUMN `preview_until` date NULL,
  ADD COLUMN `preview_used_at` datetime NULL;

-- tutors approved from a FREE sign-up (STARTER + complimentary "FREE plan ...") are now on the FREE plan
UPDATE `users_has_payment_plans` s
  JOIN `payment_plans` f ON f.`plan_key` = 'free'
   SET s.`payment_plans_payment_plan_id` = f.`payment_plan_id`, s.`status` = 'active', s.`period_end` = NULL, s.`status_reason` = NULL
 WHERE s.`status` = 'complimentary' AND s.`status_reason` LIKE 'FREE plan%';
