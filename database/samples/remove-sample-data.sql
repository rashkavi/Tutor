-- =====================================================================================================
-- ENLIXO Tutor: removes everything database/samples/dashboard-sample-data.sql added (rows with its markers).
-- Real data is not touched. Safe to run more than once.
-- If you never ran migration 012, delete the "tute" lines (the first three DELETEs) before running this.
-- =====================================================================================================
DELETE d FROM tute_deliveries d JOIN class_tutes t ON t.class_tute_id = d.class_tutes_class_tute_id WHERE t.tute_description = 'SAMPLE-DATA';
DELETE a FROM class_tutes_has_attachments a JOIN class_tutes t ON t.class_tute_id = a.class_tutes_class_tute_id WHERE t.tute_description = 'SAMPLE-DATA';
DELETE FROM class_tutes WHERE tute_description = 'SAMPLE-DATA';

DELETE ps FROM parents_has_students ps JOIN parents p ON p.parent_id = ps.parents_parent_id WHERE p.email LIKE 'sample.parent%@example.com';
DELETE FROM parents WHERE email LIKE 'sample.parent%@example.com';
DELETE FROM student_has_address WHERE address_line_2 = 'Sample address';

DELETE FROM lecture_recording WHERE recording_description = 'SAMPLE-DATA';
DELETE pp FROM student_has_penalty_payments pp JOIN student_has_payments p ON p.student_has_payment_id = pp.student_has_payments_student_has_payment_id WHERE p.refference LIKE 'SMPL-%';
DELETE pl FROM student_has_payment_logs pl JOIN student_has_payments p ON p.student_has_payment_id = pl.student_has_payments_student_has_payment_id WHERE p.refference LIKE 'SMPL-%';
DELETE ps FROM student_has_payments_slips ps JOIN student_has_payments p ON p.student_has_payment_id = ps.student_has_payments_student_has_payment_id WHERE p.refference LIKE 'SMPL-%';
DELETE FROM student_has_payments WHERE refference LIKE 'SMPL-%';

DELETE a FROM attendance a JOIN time_table t ON t.time_table_id = a.time_table_time_table_id WHERE t.lecture = 'SAMPLE-DATA';
DELETE r FROM lecture_recording r JOIN time_table t ON t.time_table_id = r.time_table_time_table_id WHERE t.lecture = 'SAMPLE-DATA';
DELETE FROM time_table WHERE lecture = 'SAMPLE-DATA';
DELETE FROM class_schedules WHERE created_by = 0;
DELETE FROM class_payment_plans WHERE discount_note = 'SAMPLE-DATA'
  AND NOT EXISTS (SELECT 1 FROM student_has_payments p WHERE p.class_payment_plans_class_payment_plan_id = class_payment_plans.class_payment_plan_id);
DELETE FROM students_has_classes WHERE student_class_id = 'SAMPLE'
  AND NOT EXISTS (SELECT 1 FROM student_has_payments p WHERE p.students_has_class_students_has_class_id = students_has_classes.students_has_class_id);
