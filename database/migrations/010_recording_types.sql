-- 010: Recording types (Settings -> Recording Types) and the extra columns class recordings need.
-- Same idea as ENLIXO V3 md/recording-types-migration.sql, but per tutor: every tutor chooses their own
-- platforms. The defaults (YouTube, Bunny Stream, Zoom, Google Drive, Vimeo) are added for a tutor the first
-- time their list is opened (src/Recordings/RecordingTypeRepository::ensureDefaults), so nothing is seeded here.
-- Additive only and safe to run more than once.

CREATE TABLE IF NOT EXISTS `tutor_recording_types` (
  `recording_type_id` INT NOT NULL AUTO_INCREMENT,
  `owner_user_id`     INT NOT NULL,                     -- the tutor (users.user_id). Not called tutor_id on purpose (legacy joins).
  `name`              VARCHAR(60)  NOT NULL,            -- shown to the tutor, e.g. "Zoom"
  `platform_key`      VARCHAR(20)  NOT NULL,            -- link checker: youtube | bunny | zoom | vimeo | drive | custom
  `allowed_hosts`     VARCHAR(255) NULL,                -- custom only: comma separated websites, e.g. dropbox.com
  `link_example`      VARCHAR(255) NULL,                -- shown as the hint of the link box
  `is_embeddable`     TINYINT(1)   NOT NULL DEFAULT 1,  -- 1 = plays inside the page, 0 = opens in a new tab
  `sort_order`        INT          NOT NULL DEFAULT 0,
  `is_active`         TINYINT(1)   NOT NULL DEFAULT 1,
  `added_by`          INT NULL,
  `added_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`recording_type_id`),
  UNIQUE KEY `uq_tutor_recording_type` (`owner_user_id`, `name`),
  KEY `idx_tutor_recording_types_owner` (`owner_user_id`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- New columns on lecture_recording (each added only when missing). Old rows keep working: a row with no type is
-- an old YouTube embed (integrations_integration_id NULL) or an uploaded Bunny video (integration set).
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lecture_recording' AND COLUMN_NAME = 'tutor_recording_types_id');
SET @q = IF(@c = 0, 'ALTER TABLE `lecture_recording` ADD COLUMN `tutor_recording_types_id` INT NULL', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lecture_recording' AND COLUMN_NAME = 'recording_url');
SET @q = IF(@c = 0, 'ALTER TABLE `lecture_recording` ADD COLUMN `recording_url` VARCHAR(600) NULL COMMENT ''clean link that is played; the iframe is generated, never typed''', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lecture_recording' AND COLUMN_NAME = 'access_passcode');
SET @q = IF(@c = 0, 'ALTER TABLE `lecture_recording` ADD COLUMN `access_passcode` VARCHAR(100) NULL COMMENT ''Zoom / Drive passcode shown to students''', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lecture_recording' AND COLUMN_NAME = 'unlock_at');
SET @q = IF(@c = 0, 'ALTER TABLE `lecture_recording` ADD COLUMN `unlock_at` DATETIME NULL COMMENT ''students see it from this time; NULL = at once''', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @c = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lecture_recording' AND INDEX_NAME = 'idx_lecture_recording_type');
SET @q = IF(@c = 0, 'ALTER TABLE `lecture_recording` ADD KEY `idx_lecture_recording_type` (`tutor_recording_types_id`)', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
