-- ENLIXO Tutor: migration 001
-- Additive only: adds new tables/columns on top of the live schema (u155757515_enlixo_tutor).
-- No existing table or column is renamed or dropped.

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- 1. Packages: features, plan entitlements, add-ons
-- ---------------------------------------------------------------------------

ALTER TABLE `payment_plans`
  ADD COLUMN `plan_key` VARCHAR(20) NULL AFTER `payment_plan`,
  ADD COLUMN `student_limit` INT NULL DEFAULT NULL COMMENT 'NULL = unlimited',
  ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0;

UPDATE `payment_plans` SET `plan_key` = 'start', `student_limit` = 50,  `sort_order` = 1 WHERE `payment_plan_id` = 1;
UPDATE `payment_plans` SET `plan_key` = 'grow',  `student_limit` = 150, `sort_order` = 2 WHERE `payment_plan_id` = 2;
UPDATE `payment_plans` SET `plan_key` = 'pro',   `student_limit` = 300, `sort_order` = 3 WHERE `payment_plan_id` = 3;

CREATE TABLE IF NOT EXISTS `features` (
  `feature_id` INT NOT NULL AUTO_INCREMENT,
  `feature_key` VARCHAR(60) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `category` VARCHAR(60) NOT NULL,
  `description` VARCHAR(255) NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`feature_id`),
  UNIQUE KEY `uq_features_key` (`feature_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payment_plan_features` (
  `payment_plan_feature_id` INT NOT NULL AUTO_INCREMENT,
  `payment_plans_payment_plan_id` INT NOT NULL,
  `features_feature_id` INT NOT NULL,
  PRIMARY KEY (`payment_plan_feature_id`),
  UNIQUE KEY `uq_plan_feature` (`payment_plans_payment_plan_id`, `features_feature_id`),
  CONSTRAINT `fk_ppf_plan` FOREIGN KEY (`payment_plans_payment_plan_id`) REFERENCES `payment_plans` (`payment_plan_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ppf_feature` FOREIGN KEY (`features_feature_id`) REFERENCES `features` (`feature_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `addons` (
  `addon_id` INT NOT NULL AUTO_INCREMENT,
  `addon_key` VARCHAR(60) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `pricing_note` VARCHAR(150) NULL,
  `features_feature_id` INT NOT NULL COMMENT 'feature this add-on unlocks',
  PRIMARY KEY (`addon_id`),
  UNIQUE KEY `uq_addons_key` (`addon_key`),
  CONSTRAINT `fk_addons_feature` FOREIGN KEY (`features_feature_id`) REFERENCES `features` (`feature_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tutor_addons` (
  `tutor_addon_id` INT NOT NULL AUTO_INCREMENT,
  `tutor_id` INT NOT NULL,
  `addons_addon_id` INT NOT NULL,
  `starts_at` DATETIME NOT NULL,
  `ends_at` DATETIME NULL,
  PRIMARY KEY (`tutor_addon_id`),
  KEY `idx_tutor_addons_tutor` (`tutor_id`),
  CONSTRAINT `fk_tutor_addons_user` FOREIGN KEY (`tutor_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_tutor_addons_addon` FOREIGN KEY (`addons_addon_id`) REFERENCES `addons` (`addon_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 2. Custom roles for tutor staff
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `permissions` (
  `permission_id` INT NOT NULL AUTO_INCREMENT,
  `permission_key` VARCHAR(60) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `module` VARCHAR(60) NOT NULL,
  `requires_feature_key` VARCHAR(60) NULL COMMENT 'permission only works if plan has this feature',
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`permission_id`),
  UNIQUE KEY `uq_permissions_key` (`permission_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tutor_roles` (
  `tutor_role_id` INT NOT NULL AUTO_INCREMENT,
  `tutor_id` INT NOT NULL COMMENT 'owner tutor (tenant)',
  `name` VARCHAR(60) NOT NULL,
  `description` VARCHAR(200) NULL,
  `is_system` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tutor_role_id`),
  UNIQUE KEY `uq_tutor_role_name` (`tutor_id`, `name`),
  CONSTRAINT `fk_tutor_roles_user` FOREIGN KEY (`tutor_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tutor_role_permissions` (
  `tutor_roles_tutor_role_id` INT NOT NULL,
  `permissions_permission_id` INT NOT NULL,
  PRIMARY KEY (`tutor_roles_tutor_role_id`, `permissions_permission_id`),
  CONSTRAINT `fk_trp_role` FOREIGN KEY (`tutor_roles_tutor_role_id`) REFERENCES `tutor_roles` (`tutor_role_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_trp_permission` FOREIGN KEY (`permissions_permission_id`) REFERENCES `permissions` (`permission_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tutor_staff` (
  `tutor_staff_id` INT NOT NULL AUTO_INCREMENT,
  `tutor_id` INT NOT NULL,
  `staff_user_id` INT NOT NULL,
  `tutor_roles_tutor_role_id` INT NOT NULL,
  `status_status_id` INT NOT NULL DEFAULT 1,
  `added_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tutor_staff_id`),
  UNIQUE KEY `uq_tutor_staff` (`tutor_id`, `staff_user_id`),
  CONSTRAINT `fk_tutor_staff_tutor` FOREIGN KEY (`tutor_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_tutor_staff_user` FOREIGN KEY (`staff_user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_tutor_staff_role` FOREIGN KEY (`tutor_roles_tutor_role_id`) REFERENCES `tutor_roles` (`tutor_role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `user_roles` (`user_role_id`, `user_role`) VALUES (3, 'Staff')
  ON DUPLICATE KEY UPDATE `user_role` = VALUES(`user_role`);

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `audit_log_id` BIGINT NOT NULL AUTO_INCREMENT,
  `tutor_id` INT NULL,
  `users_user_id` INT NOT NULL,
  `action` VARCHAR(80) NOT NULL,
  `entity` VARCHAR(60) NOT NULL,
  `entity_id` VARCHAR(40) NULL,
  `details` JSON NULL,
  `ip_address` VARCHAR(50) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`audit_log_id`),
  KEY `idx_audit_tutor_time` (`tutor_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 3. Recording watch-time limits
--    recording_kind: 'lecture' = lecture_recording, 'pre' = pre_recordings
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `recording_access_rules` (
  `recording_access_rule_id` INT NOT NULL AUTO_INCREMENT,
  `recording_kind` ENUM('lecture','pre') NOT NULL,
  `recording_id` INT NOT NULL,
  `limit_mode` ENUM('unlimited','multiplier','fixed_minutes') NOT NULL DEFAULT 'multiplier',
  `multiplier` DECIMAL(4,2) NULL DEFAULT 3.00,
  `fixed_minutes` INT NULL,
  `available_from` DATETIME NULL,
  `expires_at` DATETIME NULL,
  `updated_by` INT NOT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`recording_access_rule_id`),
  UNIQUE KEY `uq_recording_rule` (`recording_kind`, `recording_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `recording_watch_sessions` (
  `recording_watch_session_id` BIGINT NOT NULL AUTO_INCREMENT,
  `session_token` CHAR(40) NOT NULL,
  `students_student_id` INT NOT NULL,
  `recording_kind` ENUM('lecture','pre') NOT NULL,
  `recording_id` INT NOT NULL,
  `started_at` DATETIME NOT NULL,
  `last_heartbeat_at` DATETIME NOT NULL,
  `watched_seconds` INT NOT NULL DEFAULT 0,
  `last_position_seconds` INT NOT NULL DEFAULT 0,
  `ip_address` VARCHAR(50) NULL,
  `user_agent` VARCHAR(255) NULL,
  PRIMARY KEY (`recording_watch_session_id`),
  UNIQUE KEY `uq_watch_session_token` (`session_token`),
  KEY `idx_watch_student_recording` (`students_student_id`, `recording_kind`, `recording_id`),
  CONSTRAINT `fk_watch_student` FOREIGN KEY (`students_student_id`) REFERENCES `students` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `recording_student_overrides` (
  `recording_student_override_id` INT NOT NULL AUTO_INCREMENT,
  `students_student_id` INT NOT NULL,
  `recording_kind` ENUM('lecture','pre') NOT NULL,
  `recording_id` INT NOT NULL,
  `extra_minutes` INT NOT NULL DEFAULT 0,
  `expires_at` DATETIME NULL COMMENT 'overrides rule expiry for this student',
  `reason` VARCHAR(200) NULL,
  `granted_by` INT NOT NULL,
  `granted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`recording_student_override_id`),
  UNIQUE KEY `uq_override` (`students_student_id`, `recording_kind`, `recording_id`),
  CONSTRAINT `fk_override_student` FOREIGN KEY (`students_student_id`) REFERENCES `students` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 4. Zoom auto-scheduling
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `class_schedules` (
  `class_schedule_id` INT NOT NULL AUTO_INCREMENT,
  `classes_class_id` INT NOT NULL,
  `weekday` TINYINT NOT NULL COMMENT '1 = Monday ... 7 = Sunday (ISO-8601)',
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `lecture_type_lecture_type_id` INT NOT NULL COMMENT '1 Physical, 2 Online',
  `auto_create_zoom` TINYINT(1) NOT NULL DEFAULT 1,
  `valid_from` DATE NOT NULL,
  `valid_until` DATE NULL,
  `status_status_id` INT NOT NULL DEFAULT 1,
  `created_by` INT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`class_schedule_id`),
  KEY `idx_class_schedules_class` (`classes_class_id`),
  CONSTRAINT `fk_class_schedules_class` FOREIGN KEY (`classes_class_id`) REFERENCES `classes` (`class_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `time_table`
  ADD COLUMN `class_schedules_class_schedule_id` INT NULL,
  ADD UNIQUE KEY `uq_time_table_schedule_date` (`class_schedules_class_schedule_id`, `date`);

CREATE TABLE IF NOT EXISTS `zoom_meetings` (
  `zoom_meeting_row_id` INT NOT NULL AUTO_INCREMENT,
  `time_table_time_table_id` INT NOT NULL,
  `integrations_integration_id` INT NULL,
  `zoom_meeting_id` VARCHAR(64) NOT NULL,
  `join_url` VARCHAR(512) NOT NULL,
  `start_url_encrypted` TEXT NOT NULL,
  `passcode` VARCHAR(20) NULL,
  `status` ENUM('scheduled','started','ended','cancelled','failed') NOT NULL DEFAULT 'scheduled',
  `last_error` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`zoom_meeting_row_id`),
  UNIQUE KEY `uq_zoom_time_table` (`time_table_time_table_id`),
  KEY `idx_zoom_meeting_id` (`zoom_meeting_id`),
  CONSTRAINT `fk_zoom_time_table` FOREIGN KEY (`time_table_time_table_id`) REFERENCES `time_table` (`time_table_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `integration_webhook_logs` (
  `integration_webhook_log_id` BIGINT NOT NULL AUTO_INCREMENT,
  `provider` VARCHAR(30) NOT NULL,
  `event` VARCHAR(80) NOT NULL,
  `payload` JSON NOT NULL,
  `processed_at` DATETIME NULL,
  `error` VARCHAR(255) NULL,
  `received_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`integration_webhook_log_id`),
  KEY `idx_webhook_provider_time` (`provider`, `received_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
