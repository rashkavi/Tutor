-- 005: student form settings, Requested Students (applications) and the public application link.
-- Additive only. Run after 004. Existing tables are not changed.

-- Per-tutor customisation of the student form (same shape as tutor_class_form_settings).
CREATE TABLE IF NOT EXISTS `tutor_student_form_settings` (
  `tutor_id` int NOT NULL,
  `config` json NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tutor_id`),
  CONSTRAINT `fk_tsfs_tutor` FOREIGN KEY (`tutor_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Values of fields that have no column in the old tables (NIC, school, the tutor's own fields), per student and tutor.
CREATE TABLE IF NOT EXISTS `student_custom_field_values` (
  `students_student_id` int NOT NULL,
  `tutor_id` int NOT NULL,
  `field_key` varchar(40) NOT NULL,
  `value` text NULL,
  PRIMARY KEY (`students_student_id`, `tutor_id`, `field_key`),
  CONSTRAINT `fk_scfv_student` FOREIGN KEY (`students_student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The tutor's public application link (apply?t=TOKEN). One per tutor; it can be closed and a new token made.
CREATE TABLE IF NOT EXISTS `tutor_application_links` (
  `tutor_id` int NOT NULL,
  `token` varchar(32) NOT NULL,
  `is_open` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tutor_id`),
  UNIQUE KEY `uq_tal_token` (`token`),
  CONSTRAINT `fk_tal_tutor` FOREIGN KEY (`tutor_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Requested Students: one row per application.
CREATE TABLE IF NOT EXISTS `student_applications` (
  `application_id` int NOT NULL AUTO_INCREMENT,
  `ref_no` varchar(20) NULL,
  `tutor_id` int NOT NULL,
  `classes_class_id` int NULL,
  `status` varchar(12) NOT NULL DEFAULT 'pending' COMMENT 'pending | in_review | on_hold | approved | rejected',
  `first_name` varchar(45) NOT NULL,
  `last_name` varchar(45) NOT NULL,
  `email` varchar(100) NULL,
  `whatsapp` varchar(20) NULL,
  `data` json NOT NULL COMMENT 'every answer on the form, by field key',
  `photo` varchar(80) NULL,
  `message` text NULL COMMENT 'message from the applicant',
  `status_note` varchar(500) NULL COMMENT 'reason shown on the stepper (rejected / on hold)',
  `reviewed_by` int NULL,
  `reviewed_at` datetime NULL,
  `decided_by` int NULL,
  `decided_at` datetime NULL,
  `students_student_id` int NULL COMMENT 'set when approved and registered',
  `ip` varchar(45) NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`application_id`),
  UNIQUE KEY `uq_sa_ref` (`ref_no`),
  KEY `idx_sa_tutor_status` (`tutor_id`, `status`, `created_at`),
  KEY `idx_sa_ip` (`ip`, `created_at`),
  CONSTRAINT `fk_sa_tutor` FOREIGN KEY (`tutor_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- What happened to each application, newest last (the History table on the application page).
CREATE TABLE IF NOT EXISTS `student_application_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `application_id` int NOT NULL,
  `user_id` int NULL COMMENT 'NULL = the applicant / system',
  `action` varchar(30) NOT NULL,
  `remark` varchar(500) NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sah_app` (`application_id`),
  CONSTRAINT `fk_sah_app` FOREIGN KEY (`application_id`) REFERENCES `student_applications` (`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
