-- 022: the online sign-up asks only name, e-mail and mobile, and for STARTER / GROW the bank slip of the first payment
-- (safe to run again). TutorRequests::ensureSlipColumns() adds the same columns by itself when they are missing.
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'requested_users' AND COLUMN_NAME = 'payment_slip');
SET @q = IF(@c = 0, 'ALTER TABLE `requested_users` ADD COLUMN `payment_slip` varchar(120) NULL, ADD COLUMN `payment_amount` decimal(10,2) NULL, ADD COLUMN `terms_accepted_at` datetime NULL', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
