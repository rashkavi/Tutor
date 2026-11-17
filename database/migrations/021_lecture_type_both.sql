-- 021: a class day can run in the hall and online at the same time ("Both"; safe to run again).
-- Students in the hall are marked there, the others join with the online link. Run `php bin/seed.php` after it
-- (recurring class days and several class notes per day are STARTER and up, docs/PLANS.md).
INSERT INTO `lecture_type` (`lecture_type_id`, `lecture_type`) SELECT 4, 'Both' WHERE NOT EXISTS (SELECT 1 FROM `lecture_type` WHERE `lecture_type_id` = 4);
