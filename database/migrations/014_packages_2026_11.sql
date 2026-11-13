-- 014: new packages (November 2026). Data only, run after 013, then run `php bin/seed.php` so the
-- plan features follow src/Catalog/FeatureCatalog.php (GROW now has every feature).
--
--   FREE     up to 50 students, START features, no invoices (sign-up option, approved as START + Complimentary)
--   STARTER  up to 150 students, LKR 3,000 a month (plan_key 'start'), Zoom included
--   GROW     up to 500 students, LKR 7,500 a month (plan_key 'grow'), almost everything
--   CUSTOM   any size, price agreed with the ENLIXO team (plan_key 'pro'; the admin sets the tutor's custom price)
--
-- plan_key stays the same, so tutors already on a plan keep it.

UPDATE `payment_plans` SET `payment_plan` = 'Starter', `student_limit` = 150, `per_month_amount` = 3000,
       `tagline` = 'For small and growing classes' WHERE `plan_key` = 'start';
UPDATE `payment_plans` SET `payment_plan` = 'Grow', `student_limit` = 500, `per_month_amount` = 7500,
       `tagline` = 'Almost everything, for busy tutors' WHERE `plan_key` = 'grow';
UPDATE `payment_plans` SET `payment_plan` = 'Custom', `student_limit` = NULL, `extra_pack_price` = NULL,
       `tagline` = 'Your own package, made with our team' WHERE `plan_key` = 'pro';

-- The free trial used PRO; GROW now has the same features.
UPDATE `platform_settings` SET `setting_value` = REPLACE(`setting_value`, '"trial_plan":"pro"', '"trial_plan":"grow"')
 WHERE `setting_key` = 'rules';
