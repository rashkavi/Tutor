-- 019: subjects for Grade 5 Scholarship classes (safe to run again).
-- The class form lists only the subjects of the chosen exam type (src/Classes/ExamRules.php); the Scholarship exam
-- had no subject of its own, so tutors had to pick an O/L or A/L subject. Run `php bin/seed.php` after it
-- (the Class Cover Generator feature is STARTER and up).

INSERT INTO `subjects` (`subject`) SELECT 'Grade 5 Scholarship' WHERE NOT EXISTS (SELECT 1 FROM `subjects` WHERE `subject` = 'Grade 5 Scholarship');
INSERT INTO `subjects` (`subject`) SELECT 'Environment' WHERE NOT EXISTS (SELECT 1 FROM `subjects` WHERE `subject` = 'Environment');
-- the default subject list spelt English with a double i
UPDATE `subjects` SET `subject` = 'English' WHERE `subject` = 'Engliish' AND NOT EXISTS (SELECT 1 FROM (SELECT `subject` FROM `subjects` WHERE `subject` = 'English') e);
