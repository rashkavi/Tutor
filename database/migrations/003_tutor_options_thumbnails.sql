-- 003: tutor-only dropdown options + AI class thumbnails.
-- Additive only. Run after 002. Existing tables are not changed.
--
-- A tutor's own exam type / subject / medium is a normal row in exam_type / subjects / medium (so every
-- existing join and foreign key keeps working), plus a row here saying which tutor owns it.
--   no row here = a default option, every tutor sees it (all existing options)
--   a row here  = only that tutor sees it
CREATE TABLE IF NOT EXISTS `tutor_lookup_owners` (
  `list` varchar(20) NOT NULL COMMENT 'exam_types | subjects | mediums',
  `option_id` int NOT NULL COMMENT 'exam_type_id / subject_id / medium_id',
  `tutor_id` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`list`, `option_id`),
  KEY `idx_tlo_tutor` (`tutor_id`, `list`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per AI request, used for the daily limit per tutor.
CREATE TABLE IF NOT EXISTS `ai_usage_log` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `tutor_id` int NOT NULL,
  `kind` varchar(30) NOT NULL COMMENT 'class_thumbnail',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_usage_tutor_day` (`tutor_id`, `kind`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
