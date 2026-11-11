-- 011: Bunny Stream connection on a recording type, and recording types for pre-recorded classes.
-- Run after 010_recording_types.sql. Additive only and safe to run more than once.
--
-- tutor_recording_types.bunny_library_id / bunny_api_key: a Bunny Stream type can be connected to the tutor's
--   Bunny library. Then a Bunny link, or only the video ID, is enough: the link check confirms the video is in
--   that library and reads its title and length. The API key is stored encrypted (APP_KEY), never sent to a browser.
-- pre_recordings gets the same columns lecture_recording got in 010 (type, clean link, passcode, opens on).

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tutor_recording_types' AND COLUMN_NAME = 'bunny_library_id');
SET @q = IF(@c = 0, 'ALTER TABLE `tutor_recording_types` ADD COLUMN `bunny_library_id` INT NULL COMMENT ''Bunny Stream library of this type (bunny only)''', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tutor_recording_types' AND COLUMN_NAME = 'bunny_api_key');
SET @q = IF(@c = 0, 'ALTER TABLE `tutor_recording_types` ADD COLUMN `bunny_api_key` TEXT NULL COMMENT ''Bunny Stream API key, AES-256-GCM encrypted with APP_KEY''', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_recordings' AND COLUMN_NAME = 'tutor_recording_types_id');
SET @q = IF(@c = 0, 'ALTER TABLE `pre_recordings` ADD COLUMN `tutor_recording_types_id` INT NULL', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_recordings' AND COLUMN_NAME = 'recording_url');
SET @q = IF(@c = 0, 'ALTER TABLE `pre_recordings` ADD COLUMN `recording_url` VARCHAR(600) NULL COMMENT ''clean link that is played; the iframe is generated, never typed''', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_recordings' AND COLUMN_NAME = 'access_passcode');
SET @q = IF(@c = 0, 'ALTER TABLE `pre_recordings` ADD COLUMN `access_passcode` VARCHAR(100) NULL COMMENT ''Zoom / Drive passcode shown to students''', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_recordings' AND COLUMN_NAME = 'unlock_at');
SET @q = IF(@c = 0, 'ALTER TABLE `pre_recordings` ADD COLUMN `unlock_at` DATETIME NULL COMMENT ''students see it from this time; NULL = at once''', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @c = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pre_recordings' AND INDEX_NAME = 'idx_pre_recordings_type');
SET @q = IF(@c = 0, 'ALTER TABLE `pre_recordings` ADD KEY `idx_pre_recordings_type` (`tutor_recording_types_id`)', 'SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
