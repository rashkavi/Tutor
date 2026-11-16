-- 016: Message Center (src/Messaging). Additive only; the old message_center table is kept for its history.
-- tutor_messages = one message a tutor sent (channel, who to, text, counts); tutor_message_recipients = one row per
-- person it went to (address, sent / failed / skipped); tutor_whatsapp = how the tutor sends WhatsApp messages.

CREATE TABLE IF NOT EXISTS `tutor_messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `tutor_id` int(11) NOT NULL,
  `channel` varchar(10) NOT NULL COMMENT 'email | whatsapp',
  `mode` varchar(10) NOT NULL COMMENT 'email | phone (tutor sends from own phone) | enlixo (ENLIXO number) | own (tutor API number)',
  `audience` varchar(10) NOT NULL COMMENT 'students | parents | both',
  `subject` varchar(150) DEFAULT NULL,
  `body` text NOT NULL,
  `class_ids` varchar(500) NOT NULL,
  `total` int(11) NOT NULL DEFAULT 0,
  `sent` int(11) NOT NULL DEFAULT 0,
  `failed` int(11) NOT NULL DEFAULT 0,
  `skipped` int(11) NOT NULL DEFAULT 0,
  `status` varchar(10) NOT NULL DEFAULT 'sending' COMMENT 'sending | done',
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `finished_at` datetime DEFAULT NULL,
  PRIMARY KEY (`message_id`),
  KEY `tutor_messages_tutor` (`tutor_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tutor_message_recipients` (
  `recipient_id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL,
  `kind` varchar(10) NOT NULL COMMENT 'student | parent',
  `student_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `class_name` varchar(150) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `address` varchar(150) DEFAULT NULL COMMENT 'e-mail or WhatsApp number',
  `status` varchar(10) NOT NULL DEFAULT 'pending' COMMENT 'pending | sent | failed | skipped',
  `error` varchar(255) DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  PRIMARY KEY (`recipient_id`),
  KEY `tutor_message_recipients_msg` (`message_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tutor_whatsapp` (
  `tutor_id` int(11) NOT NULL,
  `mode` varchar(10) NOT NULL DEFAULT 'phone' COMMENT 'phone | enlixo | own',
  `phone_number_id` varchar(40) DEFAULT NULL,
  `business_account_id` varchar(40) DEFAULT NULL,
  `display_number` varchar(30) DEFAULT NULL,
  `verified_name` varchar(150) DEFAULT NULL,
  `token_enc` text DEFAULT NULL COMMENT 'access token, encrypted (src/Messaging/SecretBox.php); never sent to a browser',
  `template_name` varchar(100) DEFAULT NULL,
  `template_lang` varchar(10) NOT NULL DEFAULT 'en',
  `connected_via` varchar(10) DEFAULT NULL COMMENT 'manual | embedded',
  `connected_at` datetime DEFAULT NULL,
  `checked_at` datetime DEFAULT NULL,
  `last_error` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`tutor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
