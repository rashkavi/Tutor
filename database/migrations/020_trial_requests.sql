-- 020: "Try a plan free for 7 days" becomes a request the ENLIXO admin approves (safe to run again).
-- The tutor asks from the Upgrade page; Admin > Tutors > Trial Requests approves (the 7-day preview starts then) or
-- rejects with a reason. src/Platform/TrialRequests.php.
CREATE TABLE IF NOT EXISTS `tutor_trial_requests` (
  `request_id` int(11) NOT NULL AUTO_INCREMENT,
  `tutor_id` int(11) NOT NULL,
  `plan_key` varchar(20) NOT NULL,
  `note` varchar(500) NULL,
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `days` int(11) NOT NULL DEFAULT 7,
  `decided_by` int(11) NULL,
  `decided_at` datetime NULL,
  `decision_note` varchar(500) NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`request_id`),
  KEY `idx_trial_req_tutor` (`tutor_id`, `status`),
  KEY `idx_trial_req_status` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
