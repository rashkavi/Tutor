-- 006: Tutor dashboard (tutor/dashboard.php).
-- Additive only. Run after 005. The dashboard works before this runs: it then shows no exam countdown
-- and no "left" numbers (the Joined and Left chart shows joined students only).

-- When an enrolment last changed status (active <-> inactive). The trigger keeps it right for every page,
-- old and new, so no existing code has to change. Used for "students who left this month".
ALTER TABLE `students_has_classes`
  ADD COLUMN `status_changed_at` datetime NULL DEFAULT NULL;

DROP TRIGGER IF EXISTS `trg_shc_status_changed`;
CREATE TRIGGER `trg_shc_status_changed` BEFORE UPDATE ON `students_has_classes`
FOR EACH ROW SET NEW.`status_changed_at` = IF(NEW.`status_status_id` <> OLD.`status_status_id`, NOW(), NEW.`status_changed_at`);

-- The exam day of each exam the tutor teaches for (e.g. 2026 A/L), for the countdown on the dashboard.
-- One row per tutor + exam type + exam year; the tutor sets it from the dashboard ("Exam dates").
CREATE TABLE IF NOT EXISTS `tutor_exam_dates` (
  `tutor_exam_date_id` int NOT NULL AUTO_INCREMENT,
  `tutor_id` int NOT NULL,
  `exam_type_exam_type_id` int NOT NULL,
  `year_year_id` int NOT NULL,
  `exam_date` date NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`tutor_exam_date_id`),
  UNIQUE KEY `uq_ted` (`tutor_id`, `exam_type_exam_type_id`, `year_year_id`),
  CONSTRAINT `fk_ted_tutor` FOREIGN KEY (`tutor_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The dashboard reads payments by month and attendance by session; these keep it fast.
-- (Skip a line if your database already has an index on those columns.)
ALTER TABLE `student_has_payments` ADD INDEX `idx_shp_month_status` (`date_added`, `payment_status_payment_status_id`);
ALTER TABLE `time_table` ADD INDEX `idx_tt_class_date` (`classes_class_id`, `date`);
ALTER TABLE `attendance` ADD INDEX `idx_att_tt_student` (`time_table_time_table_id`, `students_student_id`);
