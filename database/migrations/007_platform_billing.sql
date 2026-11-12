-- ENLIXO Tutor: migration 007, platform billing (docs/ADMIN-PLAN.md, step B).
-- The admin (ENVIIZ) bills tutors: trial -> invoice -> slip -> approve -> active -> renewal -> overdue -> suspended.
-- Additive only. users_has_payment_plans stays the tutor's subscription row and users_has_payment_plans_payments
-- + users_payments_slips stay the payment record, so the old pages and PdoEntitlementRepository keep working.
-- Safe to run again: every column is added only when missing (works on MySQL 8 and MariaDB).

SET NAMES utf8mb4;

-- 1. Plans and add-ons: what Plans & Prices edits
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_plans' AND COLUMN_NAME = 'extra_pack_price');
SET @q = IF(@c = 0, 'ALTER TABLE `payment_plans` ADD COLUMN `extra_pack_price` DOUBLE NULL DEFAULT NULL COMMENT ''LKR a month per 50 extra students, NULL = not sold''', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_plans' AND COLUMN_NAME = 'tagline');
SET @q = IF(@c = 0, 'ALTER TABLE `payment_plans` ADD COLUMN `tagline` VARCHAR(150) NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

UPDATE `payment_plans` SET `extra_pack_price` = 1000, `tagline` = 'Starter access package' WHERE `plan_key` = 'start' AND `tagline` IS NULL;
UPDATE `payment_plans` SET `extra_pack_price` = 1000, `tagline` = 'For active tutors and class managers' WHERE `plan_key` = 'grow' AND `tagline` IS NULL;
UPDATE `payment_plans` SET `tagline` = 'Advanced controls and analytics' WHERE `plan_key` = 'pro' AND `tagline` IS NULL;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'addons' AND COLUMN_NAME = 'price');
SET @q = IF(@c = 0, 'ALTER TABLE `addons` ADD COLUMN `price` DOUBLE NULL DEFAULT NULL COMMENT ''LKR; a month for monthly add-ons, once for one-time ones, NULL = by usage''', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'addons' AND COLUMN_NAME = 'charge_model');
SET @q = IF(@c = 0, 'ALTER TABLE `addons` ADD COLUMN `charge_model` VARCHAR(30) NOT NULL DEFAULT ''Monthly''', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'addons' AND COLUMN_NAME = 'is_offered');
SET @q = IF(@c = 0, 'ALTER TABLE `addons` ADD COLUMN `is_offered` TINYINT(1) NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

UPDATE `addons` SET `price` = 2000, `charge_model` = 'Monthly + per payment' WHERE `addon_key` = 'enviiz_gateway' AND `price` IS NULL;
UPDATE `addons` SET `price` = 1800, `charge_model` = 'Monthly' WHERE `addon_key` = 'enviiz_zoom' AND `price` IS NULL;
UPDATE `addons` SET `price` = 1500, `charge_model` = 'Monthly' WHERE `addon_key` = 'enviiz_streaming' AND `price` IS NULL;
UPDATE `addons` SET `charge_model` = 'Pay as you go' WHERE `addon_key` = 'enviiz_sms';
UPDATE `addons` SET `charge_model` = 'Per conversation' WHERE `addon_key` = 'enviiz_whatsapp';
UPDATE `addons` SET `price` = 15000, `charge_model` = 'One-time', `is_offered` = 0 WHERE `addon_key` = 'physical_id' AND `price` IS NULL;

-- 2. The tutor's subscription (one row per tutor; the latest row is the live one)
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users_has_payment_plans' AND COLUMN_NAME = 'status');
SET @q = IF(@c = 0, 'ALTER TABLE `users_has_payment_plans` ADD COLUMN `status` VARCHAR(20) NULL DEFAULT NULL COMMENT ''trial | active | due | overdue | suspended | cancelled | complimentary; NULL = old row, read as trial''', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users_has_payment_plans' AND COLUMN_NAME = 'billing_cycle');
SET @q = IF(@c = 0, 'ALTER TABLE `users_has_payment_plans` ADD COLUMN `billing_cycle` VARCHAR(10) NOT NULL DEFAULT ''monthly''', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users_has_payment_plans' AND COLUMN_NAME = 'trial_ends_at');
SET @q = IF(@c = 0, 'ALTER TABLE `users_has_payment_plans` ADD COLUMN `trial_ends_at` DATE NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users_has_payment_plans' AND COLUMN_NAME = 'period_start');
SET @q = IF(@c = 0, 'ALTER TABLE `users_has_payment_plans` ADD COLUMN `period_start` DATE NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users_has_payment_plans' AND COLUMN_NAME = 'period_end');
SET @q = IF(@c = 0, 'ALTER TABLE `users_has_payment_plans` ADD COLUMN `period_end` DATE NULL DEFAULT NULL COMMENT ''first day NOT covered (renewal / due date)''', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users_has_payment_plans' AND COLUMN_NAME = 'custom_price');
SET @q = IF(@c = 0, 'ALTER TABLE `users_has_payment_plans` ADD COLUMN `custom_price` DOUBLE NULL DEFAULT NULL COMMENT ''monthly price instead of the plan price''', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users_has_payment_plans' AND COLUMN_NAME = 'custom_price_reason');
SET @q = IF(@c = 0, 'ALTER TABLE `users_has_payment_plans` ADD COLUMN `custom_price_reason` VARCHAR(150) NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users_has_payment_plans' AND COLUMN_NAME = 'extra_packs');
SET @q = IF(@c = 0, 'ALTER TABLE `users_has_payment_plans` ADD COLUMN `extra_packs` INT NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users_has_payment_plans' AND COLUMN_NAME = 'next_plan_id');
SET @q = IF(@c = 0, 'ALTER TABLE `users_has_payment_plans` ADD COLUMN `next_plan_id` INT NULL DEFAULT NULL COMMENT ''downgrade waiting for the next renewal''', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users_has_payment_plans' AND COLUMN_NAME = 'cancel_at_period_end');
SET @q = IF(@c = 0, 'ALTER TABLE `users_has_payment_plans` ADD COLUMN `cancel_at_period_end` TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users_has_payment_plans' AND COLUMN_NAME = 'status_reason');
SET @q = IF(@c = 0, 'ALTER TABLE `users_has_payment_plans` ADD COLUMN `status_reason` VARCHAR(200) NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users_has_payment_plans' AND COLUMN_NAME = 'institute_name');
SET @q = IF(@c = 0, 'ALTER TABLE `users_has_payment_plans` ADD COLUMN `institute_name` VARCHAR(100) NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users_has_payment_plans' AND COLUMN_NAME = 'main_subject');
SET @q = IF(@c = 0, 'ALTER TABLE `users_has_payment_plans` ADD COLUMN `main_subject` VARCHAR(60) NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users_has_payment_plans' AND COLUMN_NAME = 'admin_note');
SET @q = IF(@c = 0, 'ALTER TABLE `users_has_payment_plans` ADD COLUMN `admin_note` VARCHAR(200) NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users_has_payment_plans' AND COLUMN_NAME = 'trial_extended');
SET @q = IF(@c = 0, 'ALTER TABLE `users_has_payment_plans` ADD COLUMN `trial_extended` TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users_has_payment_plans' AND COLUMN_NAME = 'grace_until');
SET @q = IF(@c = 0, 'ALTER TABLE `users_has_payment_plans` ADD COLUMN `grace_until` DATE NULL DEFAULT NULL COMMENT ''admin reactivated: no automatic suspension before this day''', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users_has_payment_plans' AND COLUMN_NAME = 'status_changed_at');
SET @q = IF(@c = 0, 'ALTER TABLE `users_has_payment_plans` ADD COLUMN `status_changed_at` DATETIME NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

-- Old rows: the legacy 7-day trial counted from date_added, or the period of the last accepted payment.
UPDATE `users_has_payment_plans` uhp
   SET uhp.`trial_ends_at` = DATE(DATE_ADD(uhp.`date_added`, INTERVAL 7 DAY))
 WHERE uhp.`status` IS NULL AND uhp.`trial_ends_at` IS NULL;
UPDATE `users_has_payment_plans` uhp
  JOIN (SELECT `users_has_payment_plans_users_has_payment_plan_id` AS sid, MIN(`from`) AS f, MAX(`to`) AS t
          FROM `users_has_payment_plans_payments` WHERE `payment_status_payment_status_id` = 3 GROUP BY sid) p ON p.sid = uhp.`users_has_payment_plan_id`
   SET uhp.`period_start` = DATE(p.f), uhp.`period_end` = DATE(p.t), uhp.`status` = 'active',
       uhp.`billing_cycle` = IF(DATEDIFF(p.t, p.f) > 62, 'yearly', 'monthly')
 WHERE uhp.`status` IS NULL;
UPDATE `users_has_payment_plans` SET `status` = 'trial' WHERE `status` IS NULL;

-- 3. Invoices
CREATE TABLE IF NOT EXISTS `platform_invoices` (
  `platform_invoice_id` INT NOT NULL AUTO_INCREMENT,
  `invoice_number` VARCHAR(30) NOT NULL,
  `users_user_id` INT NOT NULL COMMENT 'the tutor',
  `users_has_payment_plan_id` INT NOT NULL,
  `kind` VARCHAR(12) NOT NULL DEFAULT 'period' COMMENT 'period = a billing period, upgrade = price difference, one_off',
  `plan_name` VARCHAR(50) NOT NULL,
  `billing_cycle` VARCHAR(10) NOT NULL DEFAULT 'monthly',
  `period_from` DATE NULL DEFAULT NULL,
  `period_to` DATE NULL DEFAULT NULL COMMENT 'first day NOT covered',
  `issued_on` DATE NOT NULL,
  `due_on` DATE NOT NULL,
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `tax` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `total` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `paid` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `status` VARCHAR(10) NOT NULL DEFAULT 'open' COMMENT 'open | part | paid | void (overdue = open / part past due_on)',
  `void_reason` VARCHAR(200) NULL DEFAULT NULL,
  `paid_at` DATETIME NULL DEFAULT NULL,
  `created_by` INT NULL DEFAULT NULL COMMENT 'NULL = the daily job',
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`platform_invoice_id`),
  UNIQUE KEY `uq_platform_invoice_number` (`invoice_number`),
  UNIQUE KEY `uq_platform_invoice_period` (`users_has_payment_plan_id`, `kind`, `period_from`),
  KEY `idx_platform_invoices_tutor` (`users_user_id`),
  KEY `idx_platform_invoices_status` (`status`, `due_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `platform_invoice_lines` (
  `platform_invoice_line_id` INT NOT NULL AUTO_INCREMENT,
  `platform_invoice_id` INT NOT NULL,
  `description` VARCHAR(150) NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`platform_invoice_line_id`),
  KEY `idx_platform_invoice_lines_invoice` (`platform_invoice_id`),
  CONSTRAINT `fk_platform_invoice_lines_invoice` FOREIGN KEY (`platform_invoice_id`) REFERENCES `platform_invoices` (`platform_invoice_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Payments: the old table + what the admin side needs
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users_has_payment_plans_payments' AND COLUMN_NAME = 'platform_invoice_id');
SET @q = IF(@c = 0, 'ALTER TABLE `users_has_payment_plans_payments` ADD COLUMN `platform_invoice_id` INT NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users_has_payment_plans_payments' AND COLUMN_NAME = 'bank_reference');
SET @q = IF(@c = 0, 'ALTER TABLE `users_has_payment_plans_payments` ADD COLUMN `bank_reference` VARCHAR(60) NULL DEFAULT NULL COMMENT ''reference typed by the tutor or the admin''', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users_has_payment_plans_payments' AND COLUMN_NAME = 'recorded_by');
SET @q = IF(@c = 0, 'ALTER TABLE `users_has_payment_plans_payments` ADD COLUMN `recorded_by` INT NULL DEFAULT NULL COMMENT ''admin who recorded it; NULL = uploaded by the tutor''', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users_has_payment_plans_payments' AND COLUMN_NAME = 'checked_by');
SET @q = IF(@c = 0, 'ALTER TABLE `users_has_payment_plans_payments` ADD COLUMN `checked_by` INT NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users_has_payment_plans_payments' AND COLUMN_NAME = 'checked_at');
SET @q = IF(@c = 0, 'ALTER TABLE `users_has_payment_plans_payments` ADD COLUMN `checked_at` DATETIME NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users_has_payment_plans_payments' AND COLUMN_NAME = 'reject_reason');
SET @q = IF(@c = 0, 'ALTER TABLE `users_has_payment_plans_payments` ADD COLUMN `reject_reason` VARCHAR(250) NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @c = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users_has_payment_plans_payments' AND INDEX_NAME = 'idx_uhpp_invoice');
SET @q = IF(@c = 0, 'ALTER TABLE `users_has_payment_plans_payments` ADD INDEX `idx_uhpp_invoice` (`platform_invoice_id`)', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

INSERT INTO `payment_method` (`payment_method`)
SELECT 'Cheque' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `payment_method` WHERE `payment_method` = 'Cheque');

-- 5. Settings (trial and billing rules, bank accounts) and the billing history
CREATE TABLE IF NOT EXISTS `platform_settings` (
  `setting_key` VARCHAR(40) NOT NULL,
  `setting_value` TEXT NOT NULL COMMENT 'JSON',
  `updated_by` INT NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `platform_billing_logs` (
  `platform_billing_log_id` INT NOT NULL AUTO_INCREMENT,
  `users_user_id` INT NULL DEFAULT NULL COMMENT 'the tutor; NULL = a settings change',
  `action` VARCHAR(30) NOT NULL,
  `summary` VARCHAR(250) NOT NULL,
  `reason` VARCHAR(250) NULL DEFAULT NULL,
  `before_json` TEXT NULL,
  `after_json` TEXT NULL,
  `done_by` INT NULL DEFAULT NULL COMMENT 'NULL = the daily job',
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`platform_billing_log_id`),
  KEY `idx_platform_billing_logs_tutor` (`users_user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
