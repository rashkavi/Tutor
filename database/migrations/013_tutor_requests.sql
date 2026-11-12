-- 013: online tutor sign-up requests (tutor-registration-application.php) and the admin decision on them.
-- Additive only. Run after 012. Nothing existing is renamed or dropped.
--
-- A tutor fills the public form: who they are, what they teach, the plan they want (START / GROW / PRO,
-- monthly or yearly) and the add-ons they are interested in. The request waits in Admin > Tutors > Requested Tutors.
-- Approve opens Add Tutor with everything filled in; saving it makes the tutor account and the subscription
-- (PlatformBilling::addTutor) and links it back here (tutor_id). Reject keeps the reason.
-- request_status: 1 Pending, 2 Approved, 3 Rejected (unchanged).

ALTER TABLE `requested_users`
  ADD COLUMN `plan_key` varchar(20) NULL DEFAULT NULL COMMENT 'start | grow | pro, the plan the tutor asked for',
  ADD COLUMN `billing_cycle` varchar(10) NOT NULL DEFAULT 'monthly' COMMENT 'monthly | yearly',
  ADD COLUMN `addons` varchar(255) NULL DEFAULT NULL COMMENT 'JSON list of add-on keys the tutor is interested in',
  ADD COLUMN `institute_name` varchar(100) NULL DEFAULT NULL,
  ADD COLUMN `main_subject` varchar(60) NULL DEFAULT NULL,
  ADD COLUMN `grades` varchar(100) NULL DEFAULT NULL COMMENT 'e.g. A/L, O/L, Grade 6-9',
  ADD COLUMN `class_mode` varchar(10) NULL DEFAULT NULL COMMENT 'physical | online | both',
  ADD COLUMN `district` varchar(45) NULL DEFAULT NULL,
  ADD COLUMN `expected_students` int NULL DEFAULT NULL,
  ADD COLUMN `heard_from` varchar(60) NULL DEFAULT NULL,
  ADD COLUMN `message` varchar(500) NULL DEFAULT NULL,
  ADD COLUMN `ip` varchar(45) NULL DEFAULT NULL,
  ADD COLUMN `tutor_id` int NULL DEFAULT NULL COMMENT 'users.user_id made when the request was approved',
  ADD COLUMN `decided_by` int NULL DEFAULT NULL,
  ADD COLUMN `decided_at` datetime NULL DEFAULT NULL,
  ADD COLUMN `decision_note` varchar(255) NULL DEFAULT NULL COMMENT 'reason for a rejection, or the admin note',
  ADD KEY `idx_requested_users_email` (`email`),
  ADD KEY `idx_requested_users_status_date` (`request_status_request_status_id`, `requested_date_time`);

-- Old rows were saved with status 0 by some versions; they are pending.
UPDATE `requested_users` SET `request_status_request_status_id` = 1 WHERE `request_status_request_status_id` NOT IN (1, 2, 3);
