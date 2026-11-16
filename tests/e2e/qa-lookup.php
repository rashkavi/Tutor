<?php
declare(strict_types=1);

/**
 * Ids the end-to-end tests need, read from the TEST database:  php tests/e2e/qa-lookup.php
 * Prints JSON: a fee row of a QA tutor class (set to "waiting" so it can be checked), a class day of the QA tutor,
 * an open invoice of the QA tutor, a class day of the QA parent's child and one of another class.
 */
use Enlixo\App;

$config = require dirname(__DIR__, 2) . '/src/bootstrap.php';
$db = (new App($config))->db();
$tutor = (int) ($db->value("SELECT user_id FROM users WHERE user_email = 'qa.tutor@enlixo.test'") ?? 0);
$fee = (int) ($db->value(
    'SELECT p.student_has_payment_id FROM student_has_payments p JOIN students_has_classes h ON h.students_has_class_id = p.students_has_class_students_has_class_id
       JOIN classes c ON c.class_id = h.classes_class_id WHERE c.tutor_id = :t ORDER BY p.student_has_payment_id DESC LIMIT 1',
    ['t' => $tutor],
) ?? 0);
if ($fee) {
    $db->execute('UPDATE student_has_payments SET payment_status_payment_status_id = 2 WHERE student_has_payment_id = :p', ['p' => $fee]);
}
$child = (int) ($db->value("SELECT s.student_id FROM students s JOIN login_details ld ON ld.login_details_id = s.login_details_login_details_id WHERE ld.whatsapp = '+94719990002'") ?? 0);
echo json_encode([
    'tutor' => $tutor,
    'tutor_fee' => $fee,
    'tutor_day' => (int) ($db->value('SELECT t.time_table_id FROM time_table t JOIN classes c ON c.class_id = t.classes_class_id WHERE c.tutor_id = :t ORDER BY t.date DESC LIMIT 1', ['t' => $tutor]) ?? 0),
    'tutor_invoice' => (int) ($db->value("SELECT platform_invoice_id FROM platform_invoices WHERE users_user_id = :t ORDER BY platform_invoice_id DESC LIMIT 1", ['t' => $tutor]) ?? 0),
    'child_day' => (int) ($db->value('SELECT t.time_table_id FROM time_table t JOIN students_has_classes h ON h.classes_class_id = t.classes_class_id WHERE h.students_student_id = :s ORDER BY t.date DESC LIMIT 1', ['s' => $child]) ?? 0),
    'other_day' => (int) ($db->value('SELECT t.time_table_id FROM time_table t WHERE t.classes_class_id NOT IN (SELECT classes_class_id FROM students_has_classes WHERE students_student_id IN (SELECT students_student_id FROM parents_has_students WHERE parents_parent_id = (SELECT parent_id FROM parents WHERE whatsapp = \'+94719990003\'))) LIMIT 1') ?? 0),
], JSON_PRETTY_PRINT), "\n";
