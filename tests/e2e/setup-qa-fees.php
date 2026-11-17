<?php
// QA fees for tests/e2e/fees-journey.e2e.js: the QA tutor's fees of this month start the same every run.
// The first 3 wait for a slip check (status 2), the rest are unpaid and 5 days past the due date (status 1);
// their logs, receipts and discounts are cleared. Only the QA tutor's classes are touched.
declare(strict_types=1);
require dirname(__DIR__, 2) . '/src/bootstrap.php';
$db = \Enlixo\Legacy\Bridge::app()->db();
$tutor = (int) $db->value("SELECT user_id FROM users WHERE user_email = 'qa.tutor@enlixo.test'");
$ids = array_map(static fn ($r) => (int) $r['id'], $db->all(
    "SELECT p.student_has_payment_id id FROM student_has_payments p
       JOIN students_has_classes e ON e.students_has_class_id = p.students_has_class_students_has_class_id
       JOIN classes c ON c.class_id = e.classes_class_id
      WHERE c.tutor_id = :t AND e.status_status_id = 1 AND DATE_FORMAT(p.due_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
      ORDER BY p.student_has_payment_id", ['t' => $tutor]));
$due = date('Y-m-d', strtotime('-5 days'));
foreach ($ids as $i => $id) {
    $db->execute('DELETE FROM student_has_payment_logs WHERE student_has_payments_student_has_payment_id = :i', ['i' => $id]);
    $db->execute("UPDATE student_has_payments SET payment_status_payment_status_id = :s, date_paid = :dp, payment_method_payment_method_id = NULL, refference = '',
                  discount = 0, discount_type_discount_type_id = 1, discount_note = '', due_date = :d WHERE student_has_payment_id = :i",
        ['s' => $i < 3 ? 2 : 1, 'dp' => $i < 3 ? date('Y-m-d H:i:s') : null, 'd' => $due, 'i' => $id]);
}
// "Pay in Advance" rows made by earlier runs (next month) go away again
$db->execute("DELETE p FROM student_has_payments p JOIN students_has_classes e ON e.students_has_class_id = p.students_has_class_students_has_class_id
               JOIN classes c ON c.class_id = e.classes_class_id
              WHERE c.tutor_id = :t AND p.due_date > LAST_DAY(CURDATE()) AND NOT EXISTS (SELECT 1 FROM student_has_payments_slips s WHERE s.student_has_payments_student_has_payment_id = p.student_has_payment_id)
                AND NOT EXISTS (SELECT 1 FROM student_has_payment_logs l WHERE l.student_has_payments_student_has_payment_id = p.student_has_payment_id)", ['t' => $tutor]);
echo json_encode(['tutor' => $tutor, 'fees' => count($ids), 'waiting' => min(3, count($ids))]), "\n";
