-- 004: tutors can drag their classes into their own order on the Classes page.
-- Additive only. NULL = not ordered yet (newest first, after the ordered ones).
ALTER TABLE `classes` ADD COLUMN `sort_order` int NULL DEFAULT NULL;
