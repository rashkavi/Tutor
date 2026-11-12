-- =====================================================================================================
-- ENLIXO Tutor: SAMPLE DATA so the dashboards, class pages and tute deliveries have something to show.
--
-- !! For a TEST / DEMO copy of the database only. Do not run it on the live database: it adds fees,
-- !! attendance and addresses to the students who are there, and real students would see them.
--
-- How to use (phpMyAdmin / HeidiSQL / mysql CLI), on the test copy:
--   1. Run the migrations first (database/migrations/ 001 ... 012).
--   2. Run this file once. It uses the classes and students already in the database (no fixed ids),
--      and every date is relative to today, so the data looks current whenever you run it.
--   3. To take it all out again: database/samples/remove-sample-data.sql
--
-- Every row it adds carries a marker, so remove-sample-data.sql removes exactly these rows:
--   class_payment_plans.discount_note = 'SAMPLE-DATA'      class_schedules.created_by = 0
--   time_table.lecture = 'SAMPLE-DATA'                     student_has_payments.refference LIKE 'SMPL-%'
--   student_has_address.address_line_2 = 'Sample address'  parents.email LIKE 'sample.parent%@example.com'
--   lecture_recording.recording_description = 'SAMPLE-DATA'
--   students_has_classes.student_class_id = 'SAMPLE'        class_tutes.tute_description = 'SAMPLE-DATA'
--   attendance / tute_deliveries: the rows of the sample class days / sample tutes
--
-- What you get, for every tutor's classes:
--   * classes with no students get 12 students from the tutor's other classes
--   * a monthly fee plan (only for classes that have none) and two weekly times
--   * class days from 6 weeks ago to 3 weeks ahead, plus one class today
--   * attendance on past class days (about 75% present, 10% watched the recording, 15% absent)
--   * monthly fees for the last 6 months (from the month each student joined): older months mostly paid, a few in arrears; this month a mix of
--     paid, slip waiting for approval and not paid
--   * recordings for past online class days, an address for 3 in 4 students, parents for some students
--   * one posted tute per class for this month's paid students, with parcels at every step
-- =====================================================================================================

SET NAMES utf8mb4;
SET @today := CURDATE();
SET @month0 := DATE_FORMAT(CURDATE(), '%Y-%m-01');

-- numbers -42 .. 21 (days around today) and 0 .. 5 (months back)
DROP TEMPORARY TABLE IF EXISTS sample_n;
CREATE TEMPORARY TABLE sample_n (n INT PRIMARY KEY);
INSERT INTO sample_n (n)
SELECT a.d + b.d * 10 - 42 FROM
  (SELECT 0 d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) a,
  (SELECT 0 d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6) b
WHERE a.d + b.d * 10 - 42 <= 21;

-- ---------------------------------------------------------------------------------------------------
-- 1. Students in classes that have none (12 students from the same tutor's other classes)
-- ---------------------------------------------------------------------------------------------------
INSERT INTO students_has_classes (student_class_id, students_student_id, classes_class_id, status_status_id, added_at, added_by)
SELECT 'SAMPLE', pick.student_id, pick.class_id, 1, DATE_SUB(NOW(), INTERVAL 70 DAY), pick.tutor_id
FROM (
  SELECT DISTINCT c.class_id, c.tutor_id, sc.students_student_id AS student_id
  FROM classes c
  JOIN classes other ON other.tutor_id = c.tutor_id AND other.class_id <> c.class_id
  JOIN students_has_classes sc ON sc.classes_class_id = other.class_id AND sc.status_status_id = 1
  WHERE c.status_status_id = 1
    AND NOT EXISTS (SELECT 1 FROM students_has_classes x WHERE x.classes_class_id = c.class_id)
) pick
WHERE (SELECT COUNT(*) FROM students_has_classes y JOIN classes oc ON oc.class_id = y.classes_class_id
        WHERE oc.tutor_id = pick.tutor_id AND oc.class_id <> pick.class_id AND y.status_status_id = 1 AND y.students_student_id < pick.student_id) < 12;

-- ---------------------------------------------------------------------------------------------------
-- 2. A monthly fee plan for classes without one (A/L 3000, O/L 2000, others 1500; due on the 10th)
-- ---------------------------------------------------------------------------------------------------
INSERT INTO class_payment_plans (classes_class_id, amount, users_user_id, date_added, due_date, discount_type_discount_type_id, discount, discount_note, panelty_payment_option_panelty_payment_option_id)
SELECT c.class_id,
       CASE WHEN LOWER(e.exam_type) LIKE '%a/l%' THEN 3000 WHEN LOWER(e.exam_type) LIKE '%o/l%' THEN 2000 ELSE 1500 END,
       c.tutor_id, DATE_SUB(NOW(), INTERVAL 7 MONTH), 10, 1, 0, 'SAMPLE-DATA', 1
FROM classes c JOIN exam_type e ON e.exam_type_id = c.exam_type_exam_type_id
WHERE c.status_status_id = 1 AND NOT EXISTS (SELECT 1 FROM class_payment_plans p WHERE p.classes_class_id = c.class_id);

-- ---------------------------------------------------------------------------------------------------
-- 3. Two weekly times per class (one physical, one online), only for classes without weekly times
--    weekday 1 = Monday ... 7 = Sunday
-- ---------------------------------------------------------------------------------------------------
INSERT INTO class_schedules (classes_class_id, weekday, start_time, end_time, lecture_type_lecture_type_id, auto_create_zoom, valid_from, status_status_id, created_by)
SELECT c.class_id, MOD(c.class_id * 2, 7) + 1, '15:30:00', '17:30:00', 1, 0, DATE_SUB(@today, INTERVAL 60 DAY), 1, 0
FROM classes c WHERE c.status_status_id = 1 AND NOT EXISTS (SELECT 1 FROM class_schedules s WHERE s.classes_class_id = c.class_id);
INSERT INTO class_schedules (classes_class_id, weekday, start_time, end_time, lecture_type_lecture_type_id, auto_create_zoom, valid_from, status_status_id, created_by)
SELECT c.class_id, MOD(c.class_id * 2 + 3, 7) + 1, '19:00:00', '20:30:00', 2, 0, DATE_SUB(@today, INTERVAL 60 DAY), 1, 0
FROM classes c WHERE c.status_status_id = 1 AND EXISTS (SELECT 1 FROM class_schedules s WHERE s.classes_class_id = c.class_id AND s.created_by = 0)
  AND (SELECT COUNT(*) FROM class_schedules s2 WHERE s2.classes_class_id = c.class_id) = 1;

-- ---------------------------------------------------------------------------------------------------
-- 4. Class days from 6 weeks ago to 3 weeks ahead for those weekly times, and one extra class today
-- ---------------------------------------------------------------------------------------------------
INSERT INTO time_table (classes_class_id, `date`, start_time, end_time, duration, lecture_type_lecture_type_id, lecture, users_user_id, date_added, time_table_status_time_table_status_id, lecture_title, class_schedules_class_schedule_id)
SELECT s.classes_class_id, DATE_ADD(@today, INTERVAL n.n DAY), s.start_time, s.end_time, (TIME_TO_SEC(s.end_time) - TIME_TO_SEC(s.start_time)) / 60,
       s.lecture_type_lecture_type_id, 'SAMPLE-DATA', c.tutor_id, NOW(), 1,
       ELT(1 + MOD(n.n + 42, 5), 'Theory: new unit', 'Past paper discussion', 'Revision', 'Model paper', 'Questions and answers'),
       s.class_schedule_id
FROM class_schedules s
JOIN classes c ON c.class_id = s.classes_class_id
JOIN sample_n n ON WEEKDAY(DATE_ADD(@today, INTERVAL n.n DAY)) + 1 = s.weekday
WHERE s.created_by = 0
  AND NOT EXISTS (SELECT 1 FROM time_table t WHERE t.class_schedules_class_schedule_id = s.class_schedule_id AND t.`date` = DATE_ADD(@today, INTERVAL n.n DAY));

INSERT INTO time_table (classes_class_id, `date`, start_time, end_time, duration, lecture_type_lecture_type_id, lecture, users_user_id, date_added, time_table_status_time_table_status_id, lecture_title)
SELECT c.class_id, @today, '18:30:00', '20:00:00', 90, 2, 'SAMPLE-DATA', c.tutor_id, NOW(), 1, 'Extra class'
FROM classes c
WHERE c.status_status_id = 1
  AND c.class_id = (SELECT MIN(c2.class_id) FROM classes c2 WHERE c2.tutor_id = c.tutor_id AND c2.status_status_id = 1)
  AND NOT EXISTS (SELECT 1 FROM time_table t WHERE t.classes_class_id = c.class_id AND t.`date` = @today AND t.lecture = 'SAMPLE-DATA' AND t.class_schedules_class_schedule_id IS NULL);

-- ---------------------------------------------------------------------------------------------------
-- 5. Attendance on past sample class days: 2 present, 4 watched the recording, 3 absent
-- ---------------------------------------------------------------------------------------------------
INSERT INTO attendance (students_student_id, time_table_time_table_id, attendance_status_attendance_status_id, updated_at, users_user_id)
SELECT sc.students_student_id, t.time_table_id,
       CASE WHEN MOD(sc.students_student_id * 7 + t.time_table_id * 13, 20) < 3 THEN 3
            WHEN MOD(sc.students_student_id * 7 + t.time_table_id * 13, 20) < 5 THEN 4
            ELSE 2 END,
       TIMESTAMP(t.`date`, t.end_time), t.users_user_id
FROM time_table t
JOIN students_has_classes sc ON sc.classes_class_id = t.classes_class_id AND sc.status_status_id = 1 AND DATE(sc.added_at) <= t.`date`
WHERE t.lecture = 'SAMPLE-DATA' AND t.`date` < @today
  AND NOT EXISTS (SELECT 1 FROM attendance a WHERE a.time_table_time_table_id = t.time_table_id AND a.students_student_id = sc.students_student_id);

-- ---------------------------------------------------------------------------------------------------
-- 6. Monthly fees for the last 6 months (fee month = date_added, as the app does)
--    older months: paid, about 1 in 9 still unpaid (arrears); last month: 1 in 5 unpaid (overdue)
--    this month: paid / slip waiting for approval / not paid yet, about a third each
-- ---------------------------------------------------------------------------------------------------
INSERT INTO student_has_payments (students_has_class_students_has_class_id, class_payment_plans_class_payment_plan_id, amount, discount, discount_type_discount_type_id, discount_note,
                                  panelty_payment_option_panelty_payment_option_id, due_date, refference, payment_method_payment_method_id, date_added, date_paid, payment_status_payment_status_id)
SELECT f.eid, f.plan_id, f.amount, 0, 1, NULL, 1, f.due,
       CONCAT('SMPL-', f.eid, '-', f.m),
       CASE WHEN f.st IN (2, 3) THEN 1 + MOD(f.eid + f.m, 4) END,
       f.added,
       CASE WHEN f.st IN (2, 3) THEN LEAST(TIMESTAMP(DATE_ADD(f.added, INTERVAL MOD(f.eid, 9) + 2 DAY), '10:30:00'), NOW()) END,
       f.st
FROM (
  SELECT sc.students_has_class_id eid, m.n m,
         (SELECT p.class_payment_plan_id FROM class_payment_plans p WHERE p.classes_class_id = sc.classes_class_id ORDER BY p.class_payment_plan_id DESC LIMIT 1) plan_id,
         (SELECT p.amount FROM class_payment_plans p WHERE p.classes_class_id = sc.classes_class_id ORDER BY p.class_payment_plan_id DESC LIMIT 1) amount,
         TIMESTAMP(DATE_SUB(@month0, INTERVAL m.n MONTH), '08:00:00') added,
         DATE_ADD(DATE_SUB(@month0, INTERVAL m.n MONTH), INTERVAL 9 DAY) due,
         CASE
           WHEN m.n = 0 THEN ELT(1 + MOD(sc.students_has_class_id, 3), 3, 2, 1)
           WHEN m.n = 1 THEN IF(MOD(sc.students_has_class_id, 5) = 0, 1, 3)
           ELSE IF(MOD(sc.students_has_class_id + m.n, 9) = 0, 1, 3)
         END st
  FROM students_has_classes sc
  JOIN classes c ON c.class_id = sc.classes_class_id AND c.status_status_id = 1 AND c.is_free = 0
  JOIN sample_n m ON m.n BETWEEN 0 AND 5
  WHERE sc.status_status_id = 1 AND DATE_SUB(@month0, INTERVAL m.n MONTH) >= DATE_FORMAT(sc.added_at, '%Y-%m-01')  -- not before the student joined
) f
WHERE f.plan_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM student_has_payments x WHERE x.students_has_class_students_has_class_id = f.eid
                    AND DATE_FORMAT(x.date_added, '%Y-%m') = DATE_FORMAT(f.added, '%Y-%m'));

-- ---------------------------------------------------------------------------------------------------
-- 7. Recordings of past online sample class days (sample link; replace with real ones to play them)
-- ---------------------------------------------------------------------------------------------------
INSERT INTO lecture_recording (time_table_time_table_id, recording_link, recording_url, expire_date, duration, recording_title, recording_description, users_user_id, added_at)
SELECT t.time_table_id, 'https://example.com/sample-recording', 'https://example.com/sample-recording', DATE_ADD(t.`date`, INTERVAL 60 DAY),
       COALESCE(t.duration, 90), CONCAT('Recording: ', COALESCE(t.lecture_title, 'Class')), 'SAMPLE-DATA', t.users_user_id, TIMESTAMP(t.`date`, t.end_time)
FROM time_table t
WHERE t.lecture = 'SAMPLE-DATA' AND t.lecture_type_lecture_type_id = 2 AND t.`date` < @today
  AND NOT EXISTS (SELECT 1 FROM lecture_recording r WHERE r.time_table_time_table_id = t.time_table_id);

-- ---------------------------------------------------------------------------------------------------
-- 8. An address for 3 in 4 students who have none (the other quarter shows "No address" on deliveries)
-- ---------------------------------------------------------------------------------------------------
INSERT INTO student_has_address (address_line_1, address_line_2, postal_code, students_student_id, city_city_id)
SELECT CONCAT(10 + MOD(s.student_id * 7, 180), ', ', ELT(1 + MOD(s.student_id, 6), 'Temple Road', 'Galle Road', 'Station Road', 'Lake Drive', 'Hill Street', 'School Lane')),
       'Sample address', LPAD(10000 + MOD(s.student_id * 97, 89999), 5, '0'), s.student_id,
       (SELECT MIN(ci.city_id) FROM city ci WHERE ci.city_id >= MOD(s.student_id * 41, (SELECT MAX(city_id) FROM city)) )
FROM students s
WHERE MOD(s.student_id, 4) <> 0
  AND EXISTS (SELECT 1 FROM students_has_classes sc WHERE sc.students_student_id = s.student_id AND sc.status_status_id = 1)
  AND NOT EXISTS (SELECT 1 FROM student_has_address a WHERE a.students_student_id = s.student_id);

-- ---------------------------------------------------------------------------------------------------
-- 9. Parents: one parent for every pair of students (the first 10 students with a class), linked to both.
--    They sign in on parent/ with the WhatsApp number +94770000001, +94770000002 ...
-- ---------------------------------------------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS sample_kids;
CREATE TEMPORARY TABLE sample_kids (rn INT AUTO_INCREMENT PRIMARY KEY, student_id INT, last_name VARCHAR(45));
INSERT INTO sample_kids (student_id, last_name)
SELECT s.student_id, ld.last_name FROM students s JOIN login_details ld ON ld.login_details_id = s.login_details_login_details_id
WHERE EXISTS (SELECT 1 FROM students_has_classes sc WHERE sc.students_student_id = s.student_id AND sc.status_status_id = 1)
  AND NOT EXISTS (SELECT 1 FROM parents_has_students ps WHERE ps.students_student_id = s.student_id)
  AND NOT EXISTS (SELECT 1 FROM parents sp WHERE sp.email LIKE 'sample.parent%@example.com') -- only on the first run
ORDER BY s.student_id LIMIT 10;

INSERT INTO parents (parent_fname, parent_lname, whatsapp, mobile, email, guardian_relation_guardian_relation_id, title_title_id, registered_at)
SELECT ELT(1 + MOD(k.rn, 5), 'Sunil', 'Kumari', 'Nimal', 'Dilani', 'Ruwan'), k.last_name,
       CONCAT('+9477', LPAD((k.rn + 1) DIV 2, 7, '0')), CONCAT('077', LPAD((k.rn + 1) DIV 2, 7, '0')),
       CONCAT('sample.parent', (k.rn + 1) DIV 2, '@example.com'), 1 + MOD(k.rn, 2),
       COALESCE((SELECT title_id FROM title WHERE title LIKE 'M%' ORDER BY title_id LIMIT 1), 1), NOW()
FROM sample_kids k
WHERE MOD(k.rn, 2) = 1
  AND NOT EXISTS (SELECT 1 FROM parents p WHERE p.email = CONCAT('sample.parent', (k.rn + 1) DIV 2, '@example.com'));

INSERT INTO parents_has_students (students_student_id, parents_parent_id, added_at)
SELECT k.student_id, p.parent_id, NOW()
FROM sample_kids k JOIN parents p ON p.email = CONCAT('sample.parent', (k.rn + 1) DIV 2, '@example.com')
WHERE NOT EXISTS (SELECT 1 FROM parents_has_students x WHERE x.students_student_id = k.student_id AND x.parents_parent_id = p.parent_id);

-- ---------------------------------------------------------------------------------------------------
-- 10. One posted (courier) tute per class for this month's paid students, parcels at every step.
--     Needs migration 012 (tute_deliveries). If you have not run 012 yet, stop here: everything above is in.
-- ---------------------------------------------------------------------------------------------------
INSERT INTO class_tutes (classes_class_id, tute_title, tute_description, payment_month, tute_types_tute_type_id, date_added, courier_name, dispatch_on, student_note)
SELECT c.class_id, CONCAT(DATE_FORMAT(@today, '%M'), ' tute book'), 'SAMPLE-DATA', DATE_FORMAT(@today, '%Y-%m'), 2, NOW(),
       'Sri Lanka Post (Speed Post)', DATE_ADD(@month0, INTERVAL 14 DAY), 'Posted around the 15th. Check your address in My Profile.'
FROM classes c
WHERE c.status_status_id = 1 AND NOT EXISTS (SELECT 1 FROM class_tutes t WHERE t.classes_class_id = c.class_id AND t.tute_description = 'SAMPLE-DATA');

INSERT INTO class_tutes_has_attachments (class_tutes_class_tute_id, attachment_name, attachment, date_added)
SELECT t.class_tute_id, i.item, NULL, NOW()
FROM class_tutes t JOIN (SELECT 'Tute book' item UNION ALL SELECT 'Model paper') i
WHERE t.tute_description = 'SAMPLE-DATA'
  AND NOT EXISTS (SELECT 1 FROM class_tutes_has_attachments a WHERE a.class_tutes_class_tute_id = t.class_tute_id);

INSERT INTO tute_deliveries (class_tutes_class_tute_id, students_student_id, recipient_name, phone, address_line_1, address_line_2, city, district, postal_code,
                             status, courier_name, tracking_no, dispatched_on, delivered_on)
SELECT t.class_tute_id, s.student_id, LEFT(TRIM(CONCAT(ld.first_name, ' ', ld.last_name)), 150), LEFT(COALESCE(NULLIF(s.mobile, ''), ld.whatsapp), 20),
       a.address_line_1, a.address_line_2, ci.city, di.district, a.postal_code,
       ELT(1 + MOD(s.student_id, 5), 'pending', 'packed', 'dispatched', 'delivered', 'delivered'),
       IF(MOD(s.student_id, 5) >= 2, 'Sri Lanka Post (Speed Post)', NULL),
       IF(MOD(s.student_id, 5) >= 2, CONCAT('BA', LPAD(s.student_id * 7919, 9, '0'), 'LK'), NULL),
       IF(MOD(s.student_id, 5) >= 2, DATE_SUB(@today, INTERVAL 3 DAY), NULL),
       IF(MOD(s.student_id, 5) >= 3, DATE_SUB(@today, INTERVAL 1 DAY), NULL)
FROM class_tutes t
JOIN students_has_classes sc ON sc.classes_class_id = t.classes_class_id AND sc.status_status_id = 1
JOIN students s ON s.student_id = sc.students_student_id
JOIN login_details ld ON ld.login_details_id = s.login_details_login_details_id
LEFT JOIN student_has_address a ON a.student_has_address_id = (SELECT MAX(a2.student_has_address_id) FROM student_has_address a2 WHERE a2.students_student_id = s.student_id)
LEFT JOIN city ci ON ci.city_id = a.city_city_id
LEFT JOIN district di ON di.district_id = ci.district_district_id
WHERE t.tute_description = 'SAMPLE-DATA'
  AND EXISTS (SELECT 1 FROM student_has_payments p WHERE p.students_has_class_students_has_class_id = sc.students_has_class_id
                AND p.payment_status_payment_status_id = 3 AND DATE_FORMAT(p.date_added, '%Y-%m') = t.payment_month)
  AND NOT EXISTS (SELECT 1 FROM tute_deliveries d WHERE d.class_tutes_class_tute_id = t.class_tute_id AND d.students_student_id = s.student_id);

DROP TEMPORARY TABLE IF EXISTS sample_kids;
DROP TEMPORARY TABLE IF EXISTS sample_n;
