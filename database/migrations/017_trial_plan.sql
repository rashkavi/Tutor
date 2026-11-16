-- 017: a free trial on a chosen plan (Admin › Tutor › Give a Trial, PlatformBilling::action('trial')).
-- Empty = the global trial of Trial & Billing Rules (trial_plan, trial_student_cap), as before. Additive only.
ALTER TABLE `users_has_payment_plans`
  ADD COLUMN `trial_plan_key` varchar(20) NULL DEFAULT NULL COMMENT 'features during this trial; NULL = rules trial_plan',
  ADD COLUMN `trial_student_cap` int(11) NULL DEFAULT NULL COMMENT 'most students during this trial; NULL = rules trial_student_cap';
