-- 012: tutes that are paid for and tutes that are posted (couriered).
-- Additive only. Run after 011. Nothing existing is renamed or dropped.
--
-- Who gets a tute (unchanged rule, now used everywhere):
--   class_tutes.payment_month NULL      = every active student of the class
--   class_tutes.payment_month 'YYYY-MM' = only students whose fee for that month is paid (status 3 approved, 8 paid by card)
--
-- A courier tute gets one tute_deliveries row per student who gets it. The address is copied from the student's
-- address when the row is made, so a later change of address does not change a parcel that was already sent.

ALTER TABLE `class_tutes`
  ADD COLUMN `courier_name` varchar(60) NULL DEFAULT NULL COMMENT 'courier usually used for this tute, e.g. Sri Lanka Post',
  ADD COLUMN `dispatch_on` date NULL DEFAULT NULL COMMENT 'day the tutor plans to post it',
  ADD COLUMN `student_note` varchar(255) NULL DEFAULT NULL COMMENT 'shown to students with the delivery',
  ADD COLUMN `added_by` int NULL DEFAULT NULL;

CREATE TABLE IF NOT EXISTS `tute_deliveries` (
  `tute_delivery_id` int NOT NULL AUTO_INCREMENT,
  `class_tutes_class_tute_id` int NOT NULL,
  `students_student_id` int NOT NULL,
  `recipient_name` varchar(150) NULL,
  `phone` varchar(20) NULL,
  `address_line_1` varchar(100) NULL,
  `address_line_2` varchar(100) NULL,
  `city` varchar(45) NULL,
  `district` varchar(45) NULL,
  `postal_code` varchar(10) NULL,
  `status` enum('pending','packed','dispatched','delivered','returned') NOT NULL DEFAULT 'pending',
  `courier_name` varchar(60) NULL,
  `tracking_no` varchar(40) NULL,
  `dispatched_on` date NULL,
  `delivered_on` date NULL,
  `note` varchar(255) NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int NULL,
  PRIMARY KEY (`tute_delivery_id`),
  UNIQUE KEY `uq_tute_delivery` (`class_tutes_class_tute_id`, `students_student_id`),
  KEY `idx_tute_delivery_student` (`students_student_id`),
  KEY `idx_tute_delivery_status` (`class_tutes_class_tute_id`, `status`),
  CONSTRAINT `fk_tute_delivery_tute` FOREIGN KEY (`class_tutes_class_tute_id`) REFERENCES `class_tutes` (`class_tute_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tute_delivery_student` FOREIGN KEY (`students_student_id`) REFERENCES `students` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
