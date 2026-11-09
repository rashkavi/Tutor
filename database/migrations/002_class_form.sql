-- 002: one-page class creation form (docs/TUTOR-PRODUCT-PLAN.md, step 1).
-- Additive only: new nullable columns on `classes`, two new tables. Nothing renamed or dropped.
-- class_name is widened from 45 to 100 characters (the old form already allowed 100, so long names were failing).

ALTER TABLE `classes`
  MODIFY `class_name` varchar(100) NOT NULL,
  ADD COLUMN `class_type` varchar(20) NULL DEFAULT NULL COMMENT 'theory | revision | paper | other',
  ADD COLUMN `description` text NULL,
  ADD COLUMN `delivery_mode` varchar(10) NULL DEFAULT NULL COMMENT 'physical | online | hybrid',
  ADD COLUMN `location` varchar(150) NULL DEFAULT NULL,
  ADD COLUMN `capacity` int NULL DEFAULT NULL,
  ADD COLUMN `enrollment_mode` varchar(10) NULL DEFAULT NULL COMMENT 'open | approval | invite',
  ADD COLUMN `starts_on` date NULL DEFAULT NULL,
  ADD COLUMN `is_free` tinyint(1) NOT NULL DEFAULT 0;

-- Per-tutor customisation of the class form: hidden optional fields + the tutor's own fields (JSON).
CREATE TABLE IF NOT EXISTS `tutor_class_form_settings` (
  `tutor_id` int NOT NULL,
  `config` json NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tutor_id`),
  CONSTRAINT `fk_tcfs_tutor` FOREIGN KEY (`tutor_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Values of the tutor's own fields, per class.
CREATE TABLE IF NOT EXISTS `class_custom_field_values` (
  `classes_class_id` int NOT NULL,
  `field_key` varchar(40) NOT NULL,
  `value` text NULL,
  PRIMARY KEY (`classes_class_id`, `field_key`),
  CONSTRAINT `fk_ccfv_class` FOREIGN KEY (`classes_class_id`) REFERENCES `classes` (`class_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
